// SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
// SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
// End-to-end test of PHP login and encryption against a live portal in PHP login mode, with a real Chromium and
// its virtual authenticator (passkeys with and without PRF). Creates one test report per run and deletes it
// at the end (reason "test", logged like any deletion).
//
// Aufruf: node tests/e2e/portal-e2e.mjs        (vorher einmal: cd tests/e2e && npm install)
// Umgebung:
//   HINWEISE_URL     Portal, z. B. https://dsi.hinwei.se
//   HINWEISE_PHP     Befehl, der PHP als Benutzer des Portals (PHP-Pool) auf dem Server startet (Skript auf stdin),
//                    z. B. "ssh server sudo -u hinweise-dsi php"
//   HINWEISE_KUNDE   Portalordner auf dem Server, z. B. /srv/hinweise/dsi
//   HINWEISE_KONTO   Prüfzugang (Team, keine Mails), wird bei jedem Lauf neu eingerichtet
//   HINWEISE_MAIL    optional: Befehl, der alle Mails an die angehängte Adresse ausgibt (für Registrierung, Reset,
//                    Mailcode), z. B. "ssh server sudo doveadm fetch -u ci-test@example.org text mailbox INBOX header to";
//                    die Testadressen sind Plus-Adressen von HINWEISE_KONTO. Ohne ihn entfallen diese Prüfungen
//   CHROMIUM         Pfad zu Chromium (Standard /usr/bin/chromium); HEADED=1 zeigt das Fenster
import { chromium } from "playwright-core";
import { execFileSync } from "node:child_process";
import { createHmac, randomBytes } from "node:crypto";

const URL = process.env.HINWEISE_URL, PHP = process.env.HINWEISE_PHP, KUNDE = process.env.HINWEISE_KUNDE, KONTO = process.env.HINWEISE_KONTO;
if (!URL || !PHP || !KUNDE || !KONTO) { console.error("HINWEISE_URL, HINWEISE_PHP, HINWEISE_KUNDE, HINWEISE_KONTO setzen"); process.exit(2); }
let fehler = 0;
const ok = (name, bed, info = "") => { console.log(`${bed ? "ok  " : "FEHL"} ${name}${!bed && info ? ` – ${info}` : ""}`); if (!bed) fehler++; };

/** Runs PHP on the server with the portal code loaded; $argv[1..] = args. */
function serverPhp(code, ...args) {
    const [cmd, ...rest] = PHP.split(" ");
    const kopf = `<?php $_SERVER["DOCUMENT_ROOT"] = "${KUNDE}/portal"; require "${KUNDE}/portal/common/helper.php"; require "${KUNDE}/portal/common/login.php"; `;
    return execFileSync(cmd, [...rest, "--", ...args], { input: kopf + code, encoding: "utf8" }).trim();
}

// TOTP like portal/common/totp.php
function b32(s) { const A = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"; let bits = ""; for (const c of s.replace(/[^A-Z2-7]/g, "")) bits += A.indexOf(c).toString(2).padStart(5, "0"); return Buffer.from(bits.match(/.{8}/g).map(b => parseInt(b, 2))); }
function totp(geheim, versatz = 0) {
    const z = Buffer.alloc(8); z.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000) + versatz));
    const h = createHmac("sha1", b32(geheim)).update(z).digest(), o = h[19] & 15;
    return String((h.readUInt32BE(o) & 0x7fffffff) % 1000000).padStart(6, "0");
}
/** Waits for the next TOTP step, so a code is never reused (replay protection). */
async function frischerCode(geheim) { await new Promise(r => setTimeout(r, 30000 - Date.now() % 30000 + 500)); return totp(geheim); }

const PW = "E2e-" + randomBytes(9).toString("base64url") + "Aa1";
const PW2 = "E2e-neu-" + randomBytes(9).toString("base64url") + "Aa1";

// Fresh test access: password, new key pair, TOTP, no passkeys
const GEHEIM = serverPhp(`
$a = $argv[1]; $pw = $argv[2];
passwd_setzen($a, passwort_hash($pw), "Team", "keine");
$b = neues_schluesselpaar(["email" => $a, "status" => "aktiv"], $pw);
$b["totp"] = ["secret" => totp_neu(), "last" => -1];
benutzer_speichern($b); echo $b["totp"]["secret"];`, KONTO, PW);
ok("Prüfzugang eingerichtet", /^[A-Z2-7]{32}$/.test(GEHEIM));

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || "/usr/bin/chromium", headless: !process.env.HEADED });
const ctx = await browser.newContext({ locale: "de-DE" });
const page = await ctx.newPage();
const cdp = await ctx.newCDPSession(page);
await cdp.send("WebAuthn.enable");
const authenticator = async prf => (await cdp.send("WebAuthn.addVirtualAuthenticator", { options: {
    protocol: "ctap2", transport: "internal", hasResidentKey: true, hasUserVerification: true, isUserVerified: true, hasPrf: prf, automaticPresenceSimulation: true } })).authenticatorId;
/** Submits a form and waits for the answer page, so checks never read the old one. */
const absenden = (p, sel) => Promise.all([p.waitForNavigation(), p.click(sel)]);
const text = async () => (await page.textContent("body")) || "";

async function abmelden() { await page.goto(`${URL}/team/?abmelden=1`); }
async function passwortAnmelden(pw = PW) {
    await page.goto(`${URL}/team/`);
    await page.fill("input[name=email]", KONTO); await page.fill("input[name=passwort]", pw);
    await absenden(page, "button[name=anmelden]");
}
/** A preselected security key starts by itself after the password: wait for it instead of opening another method
 *  alongside (two logins raced: HTTP 400, timeouts). True if it logged in. */
async function schluesselAbwarten() {
    // Whichever comes first: code field, key button, or the team page if the key already logged in
    const stand = async () => {
        for (let i = 0; i < 5; i++) {   // a navigation of the key login destroys the context mid-query: ask the new page
            try { return await (await page.waitForSelector("#schluessel-2fa, input[name=code], .kontozeile a[href*='abmelden']", { state: "attached", timeout: 15000 }))
                .evaluate(x => x.id === "schluessel-2fa" ? "knopf" : x.name === "code" ? "code" : "drin"); }
            catch (e) { if (!/context was destroyed|navigat/i.test(e.message)) return ""; await page.waitForLoadState().catch(() => {}); }
        }
        return "";
    };
    let s = await stand();
    if (s === "knopf") { await page.waitForSelector("#schluessel-2fa", { state: "detached", timeout: 15000 }).catch(() => {}); s = await stand(); }
    return s === "drin";
}
/** Newest mail to $an containing $muster (polls up to 60 s); returns the match. */
async function mail(an, muster) {
    const [cmd, ...rest] = process.env.HINWEISE_MAIL.split(" ");
    for (let i = 0; i < 30; i++) {
        const alle = execFileSync(cmd, [...rest, an], { encoding: "utf8" });   // PHP mail() sends 8bit, no decoding
        const t = [...alle.matchAll(muster)].pop();
        if (t) return t;
        await new Promise(r => setTimeout(r, 2000));
    }
    return null;
}
const plus = tag => KONTO.replace("@", `+${tag}-${randomBytes(3).toString("hex")}@`);

async function totpEingeben(code) { await page.fill("input[name=code]", code); await absenden(page, "form button[type=submit]:not([name])"); }

let id = null;   // test report, removed at the end: each one counts against the spam limit for 15 min
try {
    // 1. Test report, encrypted for all active members incl. the fresh test access
    await page.goto(`${URL}/melden/`);
    await page.selectOption("select[name=case_Recht]", "Datenschutz");
    const titel = new Date().toISOString().slice(0, 16);
    await page.fill("input[name=case_title]", titel);
    await page.fill("textarea[name=case_description]", "Automatischer Test der Verschlüsselung, bitte ignorieren.");
    await page.waitForTimeout(5500);   // time token: no earlier than 5 s
    await absenden(page, "form.label-links button[type=submit]");
    const token = (await page.textContent(".token-container") || "").replace(/-/g, "");
    ok("Meldung angelegt", token.length === 24, await text());
    id = token.slice(0, 8);
    const roh = serverPhp(`echo file_get_contents(KUNDE_DIR . "/cases/" . $argv[1] . ".json");`, id);
    ok("Meldung verschlüsselt gespeichert, ohne Token", roh.includes('"keys"') && !roh.includes("Automatischer Test") && !roh.includes(token));

    // 1b. Passkey login without any passkey: a message in the user's terms, not the browser's
    const leer = await authenticator(true);
    await page.goto(`${URL}/team/`); const t0 = Date.now(); await page.click("#passkey-login");
    // A selector, not waitForFunction: that one evaluates a string, which the portal's CSP forbids
    const warten = await page.waitForSelector("#webauthn-meldung:not(:empty)", { timeout: 70000 }).then(() => "", e => e.message.split("\n")[0]);
    const lm = await page.textContent("#webauthn-meldung");
    ok("Ohne Passkey: verständliche Meldung", lm.includes("Keine Anmeldung mit Passkey"), `${Math.round((Date.now() - t0) / 1000)} s, "${lm}" ${warten} ${page.url()}`);
    await cdp.send("WebAuthn.removeVirtualAuthenticator", { authenticatorId: leer });

    // 2. Password + TOTP; wrong code, then the right one; replay refused
    await passwortAnmelden();
    ok("Nach Passwort: zweiter Faktor", (await text()).includes("Zusätzliche Sicherheitsabfrage"));
    await totpEingeben("000000");
    ok("Falscher Code abgelehnt", (await text()).includes("falsch oder abgelaufen"));
    const code = await frischerCode(GEHEIM);
    await totpEingeben(code);
    ok("TOTP-Anmeldung, Meldungstitel entschlüsselt", (await text()).includes(titel), (await text()).slice(0, 300));
    await abmelden(); await passwortAnmelden(); await totpEingeben(code);
    ok("Derselbe Code ein zweites Mal abgelehnt", (await text()).includes("falsch oder abgelaufen"));
    await totpEingeben(await frischerCode(GEHEIM));

    // 3. Passkey with PRF: add, log out, log in with it (no password, no second factor)
    const mitPrf = await authenticator(true);
    await page.goto(`${URL}/team/konto/`);
    await page.fill("#neu-name", "E2E mit PRF"); await page.click("#neu-passkey");
    await page.waitForURL(/\/team\/konto\//); await page.waitForTimeout(500);
    ok("Passkey mit PRF gespeichert", (await text()).includes("E2E mit PRF"), await page.textContent("#webauthn-meldung").catch(() => ""));
    await abmelden(); await page.goto(`${URL}/team/`);
    await page.click("#passkey-login"); await page.waitForURL(`${URL}/team/`); await page.waitForTimeout(500);
    ok("Passkey-Anmeldung ohne zweiten Faktor, Titel entschlüsselt", (await text()).includes(titel), (await text()).slice(0, 300));

    // 3b. Rekey as emergency.php --umschluesseln does it, for the test access only: the next login takes the new pair over
    ok("Umschlüsselung simuliert", serverPhp(`$b = benutzer_laden($argv[1]); $alt = paar_aus_mk($b, mk_aus_passwort($b, $argv[2]));
        $p = schluesselpaar_neu(); $b["sk_neu"][] = versiegeln(base64_decode($b["pub"]), $p); $b["pub"] = base64_encode(oeffentlich($p));
        $m = fall_aendern($argv[3], fn($m) => ($k = fallschluessel_mitglied($m, $alt)) ? array_replace_recursive($m, ["keys" => ["u:" . schluessel_fp(oeffentlich($p)) => versiegeln(oeffentlich($p), $k)]]) : null);
        echo $alt && $m && benutzer_speichern($b) ? "ok" : "fehl";`, KONTO, PW, id) === "ok");
    await abmelden(); await page.goto(`${URL}/team/`);
    await page.click("#passkey-login"); await page.waitForURL(`${URL}/team/`); await page.waitForTimeout(500);
    ok("Nach Umschlüsselung: Passkey-Anmeldung liest, Kette übernommen", (await text()).includes(titel)
        && serverPhp(`echo isset(benutzer_laden($argv[1])["sk_neu"]) ? "kette" : "ok";`, KONTO) === "ok", (await text()).slice(0, 300));

    // 4. Passkey asks for a second factor on request (TOTP or mail only)
    await page.goto(`${URL}/team/konto/`); await page.click("button[name=passkey_2fa]");
    await abmelden(); await page.goto(`${URL}/team/`); await page.click("#passkey-login");
    await page.waitForURL(`${URL}/team/`); await page.waitForTimeout(500);
    ok("Passkey mit gewünschtem zweitem Faktor: Code verlangt, kein Sicherheitsschlüssel angeboten", (await text()).includes("Zusätzliche Sicherheitsabfrage") && !(await text()).includes("Sicherheitsschlüssel"));
    await totpEingeben(await frischerCode(GEHEIM));
    ok("… danach angemeldet", (await text()).includes(titel));
    await page.goto(`${URL}/team/konto/`); await page.click("button[name=passkey_2fa]");
    await cdp.send("WebAuthn.removeVirtualAuthenticator", { authenticatorId: mitPrf });

    // 5. Passkey without PRF: refused with explanation, saved as security key instead
    await authenticator(false);
    await page.goto(`${URL}/team/konto/`);
    await page.fill("#neu-name", "E2E ohne PRF"); await page.click("#neu-passkey");
    await page.waitForSelector("#als-2fa:not([hidden])", { timeout: 15000 }).catch(() => {});
    ok("Ohne PRF: Erklärung und Angebot als Sicherheitsschlüssel", (await page.textContent("#webauthn-meldung")).includes("PRF") && await page.isVisible("#als-2fa"));
    await page.click("#als-2fa"); await page.waitForURL(/\/team\/konto\//); await page.waitForTimeout(500);
    ok("Als Sicherheitsschlüssel gespeichert", (await text()).includes("E2E ohne PRF"));
    await abmelden(); await passwortAnmelden();
    if (!await schluesselAbwarten()) { await page.goto(`${URL}/team/?methode=schluessel`); await schluesselAbwarten(); }
    await page.waitForTimeout(500);
    ok("Passwort + Sicherheitsschlüssel", (await text()).includes(titel), page.url() + " – " + (await page.$eval("#webauthn-meldung", e => e.textContent).catch(() => "")) + " – " + (await text()).slice(0, 300));

    // 6. Password change: old one no longer works, reports still readable
    await page.goto(`${URL}/team/konto/`);
    await page.fill("input[name=alt]", PW); await page.fill("input[name=neu]", PW2); await absenden(page, "button[name=pw_aendern]");
    ok("Passwort geändert", (await text()).includes("Das Passwort ist geändert"));
    await abmelden(); await passwortAnmelden(PW);
    ok("Altes Passwort abgelehnt", (await text()).includes("Adresse oder Passwort falsch"));
    await passwortAnmelden(PW2);
    if (!await schluesselAbwarten()) { await page.goto(`${URL}/team/?methode=totp`); await totpEingeben(await frischerCode(GEHEIM)); }
    ok("Neues Passwort, Titel weiter entschlüsselt", (await text()).includes(titel));

    // 7. Reporter view and three failed logins → wait
    const melder = await ctx.newPage();
    await melder.goto(`${URL}/bearbeiten/`); await melder.fill("input[name=token]", token); await absenden(melder, "form button[type=submit]");
    ok("Meldende Person liest ihre Meldung", ((await melder.textContent("body")) || "").includes("Automatischer Test der Verschlüsselung"));

    // 8. With mail: registration with confirmation link, approval by the test access, mail code as second factor;
    //    password reset waits for approval; refusal locks
    if (process.env.HINWEISE_MAIL) {
        const neu = plus("reg"), NPW = "E2e-reg-" + randomBytes(6).toString("base64url") + "Aa1";
        const fremd = await ctx.newPage();
        await fremd.goto(`${URL}/registrieren/`);
        await fremd.fill("input[name=login_email]", neu); await fremd.fill("input[name=login_pw]", NPW);
        await fremd.waitForTimeout(5500);   // time token as for reports: no earlier than 5 s
        await absenden(fremd, "form button[type=submit]");
        const link = await mail(neu, /https:\/\/[^"]+\/registrieren\/\?u=[0-9a-f]+&(?:amp;)?t=[A-Za-z0-9]+/g);
        ok("Bestätigungslink per Mail", !!link);
        await fremd.goto(link[0].replace("&amp;", "&"));
        ok("Link allein bestätigt nicht", serverPhp(`echo benutzer_laden($argv[1])["status"] ?? "";`, neu) === "unbestaetigt");
        await fremd.fill("input[name=passwort]", NPW + "x"); await absenden(fremd, "form button[type=submit]");
        const ft = ((await fremd.textContent("body")) || "").replace(/\s+/g, " ");
        ok("Falsches Passwort bestätigt nicht", ft.includes("passt nicht"), ft.slice(0, 300));
        await fremd.fill("input[name=passwort]", NPW); await absenden(fremd, "form button[type=submit]");
        ok("Adresse bestätigt, wartet auf Freigabe", ((await fremd.textContent("body")) || "").includes("Adresse bestätigt"));
        await abmelden(); await passwortAnmelden(PW2);
        if (!await schluesselAbwarten()) { await page.goto(`${URL}/team/?methode=totp`); await totpEingeben(await frischerCode(GEHEIM)); }
        ok("Wartender Zugang in der Teamseite", await page.isVisible(`button[name=freigeben][value="${neu}"]`));
        await absenden(page, `button[name=freigeben][value="${neu}"]`);
        ok("Freigegeben", (await text()).includes("freigegeben"), (await text()).replace(/\s+/g, " ").slice(0, 200));
        const neuer = await browser.newContext({ locale: "de-DE" }), np = await neuer.newPage();
        await np.goto(`${URL}/team/`); await np.fill("input[name=email]", neu); await np.fill("input[name=passwort]", NPW); await absenden(np, "button[name=anmelden]");
        const mcode = await mail(neu, /Anmeldecode: <b>(\d{6})<\/b>/g);
        ok("Mailcode als zweiter Faktor", !!mcode);
        await np.fill("input[name=code]", mcode[1]); await absenden(np, "form button[type=submit]:not([name])");
        await np.waitForURL(`${URL}/team/`); await np.waitForLoadState();
        const nt = (await np.textContent("body")) || "";
        ok("Neuer Zugang liest die Meldung", nt.includes(titel), nt.replace(/\s+/g, " ").slice(0, 300));
        await neuer.close();

        // Reset: new password, then waiting; refusal locks the access. First let nginx's throttle refill
        // (2 POSTs a minute to /melden, /registrieren and /passwort, burst 2), registration used three
        await new Promise(r => setTimeout(r, 60000));
        await fremd.goto(`${URL}/passwort/`); await fremd.fill("input[name=email]", neu); await absenden(fremd, "form button[type=submit]");
        const rlink = await mail(neu, /https:\/\/[^"]+\/passwort\/\?u=[0-9a-f]+&(?:amp;)?t=[A-Za-z0-9]+/g);
        ok("Reset-Link per Mail", !!rlink);
        await fremd.goto(rlink[0].replace("&amp;", "&")); await fremd.fill("input[name=neu]", NPW + "x"); await absenden(fremd, "form button[type=submit]");
        ok("Reset: wartet auf Freigabe", ((await fremd.textContent("body")) || "").includes("wieder freigeben"));
        await page.goto(`${URL}/team/`);
        ok("Reset-Zugang in der Teamseite", (await text()).includes("Neues Passwort"));
        await absenden(page, `button[name=ablehnen][value="${neu}"]`);
        const gesperrt = serverPhp(`echo (benutzer_laden($argv[1])["status"] ?? "") . " " . (isset(passwd_eintraege()[strtolower($argv[1])]) ? "aktiv" : "weg");`, neu);
        ok("Abgelehnt: gesperrt und aus passwd", gesperrt === "gesperrt weg", gesperrt);
    }
    // 9. Without JavaScript: password + TOTP works, the passkey button is replaced by a hint
    const ohneJs = await browser.newContext({ locale: "de-DE", javaScriptEnabled: false }), oj = await ohneJs.newPage();
    await oj.goto(`${URL}/team/`);
    ok("Ohne JS: Hinweis statt Passkey-Knopf", ((await oj.textContent("body")) || "").includes("brauchen Sie JavaScript") && !(await oj.isVisible("#passkey-login")));
    await oj.fill("input[name=email]", KONTO); await oj.fill("input[name=passwort]", PW2); await absenden(oj, "button[name=anmelden]");
    await oj.goto(`${URL}/team/?methode=totp`); await oj.fill("input[name=code]", await frischerCode(GEHEIM)); await absenden(oj, "form button[type=submit]:not([name])");
    ok("Ohne JS: Anmeldung mit Passwort und App, Titel entschlüsselt", ((await oj.textContent("body")) || "").includes(titel));
    await oj.goto(`${URL}/team/konto/`);
    ok("Ohne JS: Mein Zugang ohne tote Knöpfe, mit Hinweis", !(await oj.isVisible("#neu-passkey")) && ((await oj.textContent("body")) || "").includes("nur mit JavaScript"));
    await ohneJs.close();

    await abmelden();
    for (let i = 0; i < 3; i++) await passwortAnmelden("falsch-" + i);
    await passwortAnmelden(PW2);
    ok("Nach drei Fehlversuchen gesperrt", (await text()).includes("Zu viele Fehlversuche"));
} catch (e) { ok("Ablauf ohne Ausnahme", false, e.message.split("\n")[0] + " – " + page.url() + " – " + ((await text().catch(() => "")) || "").slice(0, 300)); }
finally {
    await browser.close();
    if (/^[A-Za-z0-9]{8}$/.test(id ?? "")) {
        let r; try { r = serverPhp(`require_once "${KUNDE}/portal/common/case.php"; $_SESSION["anmeldung"]["email"] = $argv[2];
            echo test_sofort_loeschen($argv[1], false) && !file_exists(get_case_file_path($argv[1])) ? "ok" : "nein";`, id, KONTO); } catch (e) { r = e.message.split("\n")[0]; }
        ok("Testmeldung gelöscht", r === "ok", r);
    }
    // Leave the test access without keys (so no report is wrapped for it until the next run) and without passkeys,
    // and remove the plus addresses created by this and earlier runs
    serverPhp(`$b = benutzer_laden($argv[1]); $b["passkeys"] = []; $b["keys2fa"] = []; unset($b["pub"], $b["sk"], $b["mk"], $b["sk_neu"]); $b = erfolg($b); benutzer_speichern($b);
        $p = explode("@", $argv[1])[0] . "+";
        foreach (alle_benutzer() as $a => $x) if (str_starts_with($a, strtolower($p))) benutzer_loeschen($a);
        passwd_aendern(fn($z) => array_values(array_filter($z, fn($l) => !str_starts_with(preg_replace("/^# gesperrt \\S+ /", "", $l), $p))));`, KONTO);
}
console.log(fehler ? `ERGEBNIS: ${fehler} Fehler` : "ERGEBNIS: alles ok");
process.exit(fehler ? 1 : 0);
