#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Tests for encrypted reports (portal/common/crypto.php, auth.php): lock without emergency key, converting a plain case at a member's login,
# access by token, member and emergency key, new members after approval, reset key pairs, invalidated tokens,
# deadline watcher on encrypted cases, rekeying without new login (emergency.php --umschluesseln, wrong curve, wrong old key),
# operator approval (--freigeben, reset and registration).
# Usage: tests/crypto-test.sh
set -u
cd "$(dirname "$0")/.."; T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
K="$T/kunde"; mkdir -p "$K/portal" "$K/cases"
printf '{"Anmeldung":"portal","Domain":"kunde.example.org"}' > "$K/portal/settings.json"
DOCUMENT_ROOT="$K/portal" php $(php -m | grep -qx sodium || echo -d extension=sodium) -d display_errors=stderr -r '
$_SERVER["DOCUMENT_ROOT"] = getenv("DOCUMENT_ROOT");
require "portal/common/case.php"; require "portal/common/auth.php";
$f = 0;
function paar_pw(array $b, string $pw): ?string { return paar_aus_mk($b, mk_aus_passwort($b, $pw)); }
function ok($name, $bed, $info = "") { global $f; echo ($bed ? "ok   " : "FEHL ") . "$name" . (!$bed && $info ? " – $info" : "") . "\n"; if (!$bed) $f = 1; }
$wurzel = dirname(KUNDE_DIR);
/** scripts/emergency.php with $schluessel on stdin: [rc, output]. */
function notfall_aufruf(string $schluessel, string ...$args): array {
    global $wurzel;
    file_put_contents("$wurzel/notfall.txt", $schluessel);
    exec(PHP_BINARY . " -d sendmail_path=true " . escapeshellarg(getcwd() . "/scripts/emergency.php") . " " . implode(" ", array_map("escapeshellarg", $args)) . " < " . escapeshellarg("$wurzel/notfall.txt") . " 2>&1", $aus, $rc);
    return [$rc, implode(" ", $aus)];
}
/** Contents of all users/ or cases/ files, to show that nothing changed. */
function stand(string $ordner): array { return array_map("file_get_contents", glob(KUNDE_DIR . "/$ordner/*.json")); }
ok("Ohne Zweitschlüssel gesperrt", zweitschluessel_fehlt());
$notfall = schluesselpaar_neu();
file_put_contents("$wurzel/" . NOTFALL_DATEI, base64_encode(oeffentlich($notfall)));
file_put_contents(KUNDE_DIR . "/passwd", "a@example.org:x:Team:keine\nb@example.org:x:Team:keine\n");
$a = neues_schluesselpaar(["email" => "a@example.org", "status" => "aktiv"], "PasswortA-123456"); benutzer_speichern($a);
$pa = paar_pw($a, "PasswortA-123456");
ok("Schlüssel aus Passwort, falsches Passwort scheitert", $pa && !paar_pw($a, "falsch"));
ok("Neues Passwort packt nur MK neu", paar_pw(mk_mit_passwort($a, mk_aus_passwort($a, "PasswortA-123456"), "PasswortA-neu-1234"), "PasswortA-neu-1234") === $pa);
ok("AES-GCM erkennt Veränderung", ($c = sym_verschluesseln(str_repeat("k", 32), "x")) && sym_entschluesseln(str_repeat("k", 32), $c) === "x" && !sym_entschluesseln(str_repeat("k", 32), base64_encode(substr(base64_decode($c), 0, -1) . "y")));
ok("ECIES: fremder Schlüssel öffnet nicht", entsiegeln($notfall, versiegeln(oeffentlich($notfall), "k")) === "k" && !entsiegeln(schluesselpaar_neu(), versiegeln(oeffentlich($notfall), "k")));
$token = "AAAAAAAA" . str_repeat("s", 16);
file_put_contents(KUNDE_DIR . "/cases/AAAAAAAA.json", json_encode(["token" => $token, "title" => "Geheimer Titel", "status" => "unbearbeitet",
    "conversation" => [["person" => "meldende_person", "date" => "2026-11-01 10:00:00", "text" => "Geheimer Text"]]]));
$_SESSION["anmeldung"]["email"] = "a@example.org";
verpackungen_ergaenzen($pa);
$roh = file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"); $m = json_decode($roh, true);
ok("Klartext verschlüsselt, Token nicht gespeichert", ist_verschluesselt($m) && !str_contains($roh, "Geheim") && !str_contains($roh, $token));
ok("Fristen lesen verschlüsselte Meldung", is_array(fristen($m, null, null, new DateTimeImmutable("2026-11-02"))) && count(fristen($m, null, null, new DateTimeImmutable("2026-11-02"))) === 2);
ok("Mitglied entschlüsselt", fall_klartext($m, fallschluessel_mitglied($m, $pa))["title"] === "Geheimer Titel");
ok("Token entschlüsselt, falscher nicht", fall_klartext($m, fallschluessel_token($m, substr($token, 8)))["conversation"][0]["text"] === "Geheimer Text" && !fallschluessel_token($m, str_repeat("x", 16)));
ok("Notfallschlüssel entschlüsselt", entsiegeln($notfall, $m["keys"]["notfall"]) === fallschluessel_mitglied($m, $pa));
$b = neues_schluesselpaar(["email" => "b@example.org", "status" => "wartet"], "PasswortB-123456"); benutzer_speichern($b);
$pb = paar_pw($b, "PasswortB-123456");
verpackungen_ergaenzen($pa); $m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
ok("Wartendes Mitglied bekommt nichts", !fallschluessel_mitglied($m, $pb));
$b["status"] = "aktiv"; benutzer_speichern($b);
verpackungen_ergaenzen($pa); $m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
ok("Nach Freigabe entschlüsselt B", fallschluessel_mitglied($m, $pb) !== null);
$b = neues_schluesselpaar($b, "PasswortB-neu-12345"); benutzer_speichern($b);
verpackungen_ergaenzen($pa); $m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
ok("Neues Schlüsselpaar: alter Schlüssel gilt nicht mehr, neuer schon", !fallschluessel_mitglied($m, $pb) && fallschluessel_mitglied($m, paar_pw($b, "PasswortB-neu-12345")));
// Emergency script: wraps for a member nobody else could wrap for (new key pair, no other login)
$b = neues_schluesselpaar($b, "PasswortB-drei-1234", $pb3); benutzer_speichern($b);
[$rc, $aus] = notfall_aufruf("# Testschlüssel\n" . $notfall, KUNDE_DIR, "b@example.org");   // "#" header is skipped
$m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
ok("Notfallskript verpackt für neues Schlüsselpaar", $rc === 0 && fallschluessel_mitglied($m, $pb3) !== null, $aus);
// Operator approval when nobody in the team can: waiting access becomes active and gets its reports
$b["status"] = "wartet"; $b = neues_schluesselpaar($b, "PasswortB-drei-1234", $pb5); benutzer_speichern($b);
[$rc, $aus] = notfall_aufruf($notfall, "--freigeben", KUNDE_DIR, "b@example.org");
$m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
ok("Notfallskript --freigeben: aktiv und verpackt", $rc === 0 && benutzer_laden("b@example.org")["status"] === "aktiv" && fallschluessel_mitglied($m, $pb5) !== null, $aus);
// Registration (not yet in passwd): approval writes the passwd line; a repeated run changes nothing
$c = neues_schluesselpaar(["email" => "c@example.org", "status" => "wartet", "pw" => "x"], "PasswortC-123456", $pc); benutzer_speichern($c);
$rcs = $ausgaben = [];
for ($i = 0; $i < 2; $i++) [$rcs[], $ausgaben[]] = notfall_aufruf($notfall, "--freigeben", KUNDE_DIR, "c@example.org");
$m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
$aus = implode(" | ", $ausgaben);
ok("Notfallskript --freigeben einer Registrierung: passwd, aktiv, verpackt, wiederholbar", $rcs === [0, 0] && str_contains($ausgaben[0], "freigegeben") && !str_contains($ausgaben[1], "freigegeben") && isset(passwd_eintraege(true)["c@example.org"]) && !isset(benutzer_laden("c@example.org")["pw"]) && fallschluessel_mitglied($m, $pc) !== null, $aus);
file_put_contents(KUNDE_DIR . "/passwd", "a@example.org:x:Team:keine\n"); passwd_eintraege(true);   // written behind the cache
verpackungen_ergaenzen($pa); $m = json_decode(file_get_contents(KUNDE_DIR . "/cases/AAAAAAAA.json"), true);
ok("Aus passwd entfernt: Verpackung weg", count(array_filter(array_keys($m["keys"]), fn($n) => str_starts_with($n, "u:"))) === 1);
$m2 = fall_aendern("AAAAAAAA", function ($m) { unset($m["keys"]["melder"], $m["verifier"]); return $m; });
ok("Token ungültig: Zugang weg", !fallschluessel_token($m2, substr($token, 8)));
// Rekeying: new emergency key, new member pairs; B (not logged in) takes the new pair over at the next normal login
file_put_contents(KUNDE_DIR . "/passwd", "a@example.org:x:Team:keine\nb@example.org:x:Team:keine\n"); passwd_eintraege(true);
$token2 = "BBBBBBBB" . str_repeat("t", 16);
file_put_contents(KUNDE_DIR . "/cases/BBBBBBBB.json", json_encode(["token" => $token2, "title" => "Zweiter Titel", "status" => "unbearbeitet", "conversation" => []]));
verpackungen_ergaenzen($pa);
function umschluesseln(string $alt, string $neu_pub, string $ort = "", string $zusatz = ""): array {   // default: all portals under the root
    global $wurzel;
    return notfall_aufruf($alt, "--umschluesseln", $ort ?: $wurzel, base64_encode($neu_pub), ...($zusatz ? [$zusatz] : []));
}
function fall(string $id): array { return json_decode(file_get_contents(KUNDE_DIR . "/cases/$id.json"), true); }
$notfall2 = schluesselpaar_neu();
$a1 = benutzer_laden("a@example.org");
openssl_pkey_export(openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => "prime256v1"]), $p256);
[$rc, $aus] = umschluesseln($notfall, oeffentlich($p256), KUNDE_DIR);
ok("Umschlüsseln: NEUE_PUB auf anderer Kurve abgelehnt, nichts geändert", $rc === 2 && benutzer_laden("a@example.org") === $a1, $aus);
[$rc, $aus] = umschluesseln($notfall, oeffentlich($notfall2));
$m = fall("BBBBBBBB"); $a2 = benutzer_laden("a@example.org");
ok("Umschlüsseln: Notfall neu, alter Schlüssel und altes Paar gelten nicht mehr", $rc === 0 && entsiegeln($notfall2, $m["keys"]["notfall"]) && !entsiegeln($notfall, $m["keys"]["notfall"]) && !fallschluessel_mitglied($m, $pa), $aus);
ok("Umschlüsseln: meldende Person liest weiter", fall_klartext($m, fallschluessel_token($m, substr($token2, 8)))["title"] === "Zweiter Titel");
[$rc] = umschluesseln($notfall, oeffentlich($notfall2));
ok("Umschlüsseln wiederholbar", $rc === 0 && benutzer_laden("a@example.org")["pub"] === $a2["pub"] && fall("BBBBBBBB")["keys"]["notfall"] === $m["keys"]["notfall"]);
// Meanwhile a case still wrapped to the old emergency key; then the new one is installed and the script runs again
$k3 = random_bytes(32);
file_put_contents(KUNDE_DIR . "/cases/CCCCCCCC.json", json_encode(fall_verschluesseln(["title" => "Dritter", "status" => "unbearbeitet", "conversation" => [], "token" => "CCCCCCCC" . str_repeat("u", 16)], $k3, str_repeat("u", 16), [], oeffentlich($notfall))));
file_put_contents("$wurzel/" . NOTFALL_DATEI, base64_encode(oeffentlich($notfall2)));
$c3 = fall("CCCCCCCC");
[$rc, $aus] = umschluesseln(schluesselpaar_neu(), oeffentlich($notfall2));
ok("Umschlüsseln Zwischenzeit: fremder alter Schlüssel abgelehnt, nichts geändert", $rc === 2 && fall("CCCCCCCC") === $c3, $aus);
// A case wrapped for a third key stops the run before any change; an older case without notfall_fp is opened
$fremd = fall_verschluesseln(["title" => "Fremd", "status" => "unbearbeitet", "conversation" => [], "token" => "EEEEEEEE" . str_repeat("w", 16)], random_bytes(32), str_repeat("w", 16), [], oeffentlich(schluesselpaar_neu()));
file_put_contents(KUNDE_DIR . "/cases/EEEEEEEE.json", json_encode($fremd));
$k4 = random_bytes(32);
$alt4 = fall_verschluesseln(["title" => "Vierter", "status" => "unbearbeitet", "conversation" => [], "token" => "DDDDDDDD" . str_repeat("v", 16)], $k4, str_repeat("v", 16), [], oeffentlich($notfall));
unset($alt4["notfall_fp"]);
file_put_contents(KUNDE_DIR . "/cases/DDDDDDDD.json", json_encode($alt4));
[$rc, $aus] = umschluesseln($notfall, oeffentlich($notfall2));
ok("Umschlüsseln: Meldung für fremden Schlüssel hält den Lauf an, nichts geändert", $rc === 2 && str_contains($aus, "EEEEEEEE") && fall("CCCCCCCC") === $c3, $aus);
unlink(KUNDE_DIR . "/cases/EEEEEEEE.json");
[$rc, $aus] = umschluesseln($notfall, oeffentlich($notfall2));
ok("Umschlüsseln: Meldungen aus der Zwischenzeit, auch ohne notfall_fp", $rc === 0 && entsiegeln($notfall2, fall("CCCCCCCC")["keys"]["notfall"]) === $k3 && entsiegeln($notfall2, fall("DDDDDDDD")["keys"]["notfall"]) === $k4 && fall("DDDDDDDD")["notfall_fp"] === schluessel_fp(oeffentlich($notfall2)), $aus);
$benutzer = stand("users");
[$rc, $aus] = umschluesseln(schluesselpaar_neu(), oeffentlich($notfall2));
ok("Umschlüsseln nach Abschluss mit falschem Schlüssel: nichts zu tun, Mitglieder unverändert", $rc === 0 && str_contains($aus, "nichts") && stand("users") === $benutzer, $aus);
mkdir("$wurzel/leer"); copy("$wurzel/" . NOTFALL_DATEI, "$wurzel/leer/" . NOTFALL_DATEI);
[$rc, $aus] = umschluesseln($notfall, oeffentlich($notfall2), "$wurzel/leer");
ok("Umschlüsseln ohne Portale unter der Wurzel: Abbruch", $rc === 2, $aus);
// Second rekey before B logs in: B follows a chain of two
$notfall3 = schluesselpaar_neu();
[$rc, $aus] = umschluesseln($notfall2, oeffentlich($notfall3));
$b = benutzer_laden("b@example.org");
$pb4 = paar_aus_mk($b, mk_aus_passwort($b, "PasswortB-drei-1234"));   // as at login: $b gets the newest pair
ok("Nicht angemeldetes Mitglied liest nach normaler Anmeldung", $rc === 0 && $pb4 && count(benutzer_laden("b@example.org")["sk_neu"]) === 2 && fall_klartext(fall("BBBBBBBB"), fallschluessel_mitglied(fall("BBBBBBBB"), $pb4))["title"] === "Zweiter Titel", $aus);
benutzer_speichern($b); $b = benutzer_laden("b@example.org");
ok("Nach der Anmeldung ohne Kette", !isset($b["sk_neu"]) && paar_pw($b, "PasswortB-drei-1234") === $pb4);
// A case without emergency wrapping that a member can read stops the run before any change (the member login adds
// the wrapping); one no member can read only warns
function ohne_notfall(string $id, array $an): void { file_put_contents(KUNDE_DIR . "/cases/$id.json", json_encode(fall_verschluesseln(["title" => "Ohne", "status" => "unbearbeitet", "conversation" => [], "token" => $id . str_repeat("x", 16)], random_bytes(32), str_repeat("x", 16), $an, null))); }
ohne_notfall("FFFFFFFF", array_map(fn($e) => $e[0], empfaenger_alle()));
ohne_notfall("GGGGGGGG", []);
file_put_contents("$wurzel/" . NOTFALL_DATEI, base64_encode(oeffentlich($notfall3)));   // the chain left the cases on notfall3
$f6 = fall("FFFFFFFF"); $benutzer = stand("users");
[$rc, $aus] = umschluesseln($notfall3, oeffentlich(schluesselpaar_neu()));
ok("Umschlüsseln: Meldung ohne Notfallverpackung hält den Lauf vor jeder Änderung an", $rc === 2 && str_contains($aus, "FFFFFFFF") && fall("FFFFFFFF") === $f6 && stand("users") === $benutzer, $aus);
mkdir("$wurzel/_alt/portal", 0777, true); mkdir("$wurzel/_alt/cases");   // "_*" is no portal
unlink(KUNDE_DIR . "/cases/FFFFFFFF.json");
$faelle = stand("cases");
[$rc, $aus] = umschluesseln($notfall3, oeffentlich(schluesselpaar_neu()), "", "--pruefen");
ok("Umschlüsseln --pruefen über alle Portale ändert nichts, \"_*\" ist kein Portal, unlesbare Meldung nur gewarnt", $rc === 0 && str_contains($aus, "kunde: ") && str_contains($aus, "bereit") && str_contains($aus, "GGGGGGGG") && !str_contains($aus, "_alt")
    && stand("users") === $benutzer && stand("cases") === $faelle, $aus);
$g = fall("GGGGGGGG"); $notfall5 = schluesselpaar_neu();
[$rc, $aus] = umschluesseln($notfall3, oeffentlich($notfall5));
ok("Umschlüsseln mit verwaister Meldung: Lauf geht durch, sie bleibt unverändert", $rc === 0 && str_contains($aus, "GGGGGGGG") && fall("GGGGGGGG") === $g && entsiegeln($notfall5, fall("BBBBBBBB")["keys"]["notfall"]), $aus);
exit($f);
'
