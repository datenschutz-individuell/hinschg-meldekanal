// SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
// SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
// Firefox with KeePassXC-Browser (passkeys and fallback on): "Mit Passkey anmelden" must reach the browser instead
// of failing on options the extension altered (KeePassXC-Browser 1.10 overwrites prf.eval.first, see webauthn.js;
// upstream: https://github.com/keepassxreboot/keepassxc-browser/issues/3176).
// Without the KeePassXC app the extension finds no passkey and falls back – exactly the failing path.
//
// Aufruf: HINWEISE_URL=https://… node tests/e2e/firefox-keepassxc.mjs   (Firefox unter /usr/bin/firefox oder $FIREFOX)
import puppeteer from "puppeteer-core";
import { execFileSync } from "node:child_process";
import { mkdtempSync, readFileSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const URL = process.env.HINWEISE_URL;
if (!URL) { console.error("HINWEISE_URL setzen"); process.exit(2); }
const t = mkdtempSync(join(tmpdir(), "kpxc-"));
try {
    // Current extension from addons.mozilla.org, passkeys switched on by default (the user's setting)
    execFileSync("curl", ["-sfL", "-o", join(t, "a.xpi"), "https://addons.mozilla.org/firefox/downloads/latest/keepassxc-browser/latest.xpi"]);
    execFileSync("unzip", ["-q", join(t, "a.xpi"), "-d", join(t, "x")]);
    const page_js = join(t, "x/background/page.js");
    writeFileSync(page_js, readFileSync(page_js, "utf8").replace("passkeys: false,", "passkeys: true,"));
    rmSync(join(t, "x/META-INF"), { recursive: true, force: true });
    const version = JSON.parse(readFileSync(join(t, "x/manifest.json"), "utf8")).version;

    const b = await puppeteer.launch({ browser: "firefox", executablePath: process.env.FIREFOX || "/usr/bin/firefox", headless: true });
    await b.installExtension(join(t, "x"));
    const p = await b.newPage();
    await new Promise(r => setTimeout(r, 2000));
    await p.goto(`${URL}/team/`);
    await p.waitForSelector("#passkey-login", { visible: true });
    const abgefangen = await p.evaluate(() => !String(navigator.credentials.get).includes("[native code]"));
    await p.click("#passkey-login");
    await new Promise(r => setTimeout(r, 8000));
    const meldung = await p.$eval("#webauthn-meldung", e => e.textContent);
    await b.close();
    // Empty = the browser waits for an authenticator, i.e. the call got through
    const gut = abgefangen && !/could not be converted|TypeError/.test(meldung);
    console.log(`${gut ? "ok  " : "FEHL"} Firefox + KeePassXC-Browser ${version}: Passkey-Aufruf erreicht den Browser${gut ? "" : ` – ${abgefangen ? meldung : "Add-on griff nicht ein"}`}`);
    console.log(gut ? "ERGEBNIS: alles ok" : "ERGEBNIS: 1 Fehler");
    process.exitCode = gut ? 0 : 1;
} finally { rmSync(t, { recursive: true, force: true }); }
