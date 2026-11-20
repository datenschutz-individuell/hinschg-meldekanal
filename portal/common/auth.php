<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Team login in PHP (settings.json "Anmeldung": "portal"; otherwise nginx auth_basic as before).
// <kunde>/passwd stays the list of members (address:bcrypt:role:notification), read by the deadline watcher too.
// Per member <kunde>/users/<id>.json holds what the list cannot:
//   status      unbestaetigt (mail not confirmed) | wartet (needs approval by a member) | aktiv | gesperrt
//   pub         public key (brainpoolP384r1, SubjectPublicKeyInfo DER, base64); cases are wrapped to it
//   sk          key pair (PEM), AES-GCM with the member key MK (32 random bytes)
//   mk          MK, wrapped: "pw" with argon2id(password), "pk:<credential>" with a passkey's PRF output; a new
//               password or passkey thus rewraps only MK
//   sk_neu      after a rekey (emergency.php --umschluesseln): new key pairs, each ECIES-sealed to the previous
//               public key; the next login follows the chain and stores the newest pair as sk
//   rekey       fingerprint of the emergency key the last rekey was done for (makes the script idempotent)
//   totp, keys2fa, passkeys, last  second factors and the method used last
//   passkey_2fa                    ask for a code after a passkey too
//   pw          password hash, only while not yet in passwd (registration)
//   fehl, sperre_bis               failed attempts and lockout (no IP is stored)
//   gen                            password generation; a change ends the other sessions
//   token, token_bis, token_art    mail link (hash) for confirmation or password reset
// Session: key pair and MK lie in the session only encrypted; the key for them is a cookie (never on the server).

include_once(__DIR__."/case.php");   // fall_aendern for wrapping; includes crypto.php

const SITZUNG_LEERLAUF = 30 * 60;
const SITZUNG_HOECHSTENS = 8 * 3600;
const LINK_GUELTIG = ["bestaetigen" => 86400, "zuruecksetzen" => 3600];   // seconds per kind of mail link
const COOKIE_SCHLUESSEL = "hinweise_k";

function benutzer_ordner(): string {
    $o = KUNDE_DIR . "/users";
    is_dir($o) || mkdir($o, 0700);
    return $o;
}

function benutzer_id(string $adresse): string {
    return substr(hash("sha256", strtolower(trim($adresse))), 0, 16);
}

function benutzer_datei_lesen(string $datei): ?array {
    $b = is_file($datei) ? json_decode((string)file_get_contents($datei), true) : null;
    return is_array($b) && isset($b["email"]) ? $b : null;
}

/** By id (from a mail link) or by address. */
function benutzer_laden_id(string $id): ?array {
    return preg_match('/^[0-9a-f]{16}$/', $id) ? benutzer_datei_lesen(benutzer_ordner() . "/$id.json") : null;
}

function benutzer_laden(string $adresse): ?array {
    return benutzer_laden_id(benutzer_id($adresse));
}

function benutzer_speichern(array $b): bool {
    $json = json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false && atomar_schreiben(benutzer_ordner() . "/" . benutzer_id($b["email"]) . ".json", $json, ".user.", 0600)) return true;
    error_log("Benutzer nicht gespeichert: " . benutzer_id($b["email"]));
    return false;
}

function benutzer_loeschen(string $adresse): void {
    @unlink(benutzer_ordner() . "/" . benutzer_id($adresse) . ".json");   // the lock file stays: others may hold it
}

/** Serialises login attempts of one member until the request ends, so parallel attempts cannot bypass the
 *  failure count. Call before benutzer_laden(). */
function benutzer_sperren(string $adresse): void {
    benutzer_sperren_id(benutzer_id($adresse));
}

function benutzer_sperren_id(string $id): void {
    static $griffe = [];
    if (isset($griffe[$id]) || !preg_match('/^[0-9a-f]{16}$/', $id)) return;
    $h = fopen(benutzer_ordner() . "/.$id.lock", "c");
    if ($h && flock($h, LOCK_EX)) $griffe[$id] = $h;
}

/** Approves a waiting access (registration or reset) under the user lock; null if not waiting or not saved.
 *  Then the caller wraps the reports and only after that sends freigabe_mail(). */
function zugang_freigeben(string $adresse): ?array {
    benutzer_sperren($adresse);
    $b = benutzer_laden($adresse);
    if (($b["status"] ?? "") !== "wartet") return null;
    if (isset($b["pw"]) && !passwd_setzen($b["email"], $b["pw"])) return null;   // registration: not yet in passwd
    unset($b["pw"]);
    $b["status"] = "aktiv";
    return benutzer_speichern($b) ? $b : null;
}

function freigabe_mail(array $b): void {
    mail_text($b["email"], "Zugang freigegeben", "Ihr Zugang ist freigegeben. <a href=\"https://" . setting("Domain") . "/team/\">Anmelden</a>");
}

/** All user files (for approval lists and wrapping). */
function alle_benutzer(): array {
    $alle = [];
    foreach (glob(benutzer_ordner() . "/*.json") ?: [] as $d) if ($b = benutzer_datei_lesen($d)) $alle[strtolower($b["email"])] = $b;
    return $alle;
}

/** Changes passwd under a lock: $aendern gets the lines and returns the new ones. 0640: nginx auth_basic reads it via the group. (Reading: passwd_eintraege().) */
function passwd_aendern(callable $aendern): bool {
    $datei = KUNDE_DIR . "/passwd";
    $h = fopen(KUNDE_DIR . "/.passwd-lock", "c");
    if (!$h || !flock($h, LOCK_EX)) return false;
    try {
        $zeilen = explode("\n", rtrim((string)@file_get_contents($datei), "\n"));
        $ok = atomar_schreiben($datei, implode("\n", $aendern($zeilen)) . "\n", ".passwd.", 0640);
        passwd_eintraege(true);
        return $ok;
    } finally { flock($h, LOCK_UN); fclose($h); }
}

/** Active line of $adresse (same rule as passwd_eintraege). */
function passwd_zeile_von(string $z, string $adresse): bool {
    $f = explode(":", trim($z));
    return !str_starts_with(trim($z), "#") && count($f) >= 4 && strcasecmp($f[0], $adresse) === 0;
}

/** Replaces the hash of an address in passwd, or appends a new line. Refuses an address listed twice: passwd_lesen()
 *  drops it, so a new hash there would never take effect. */
function passwd_setzen(string $adresse, string $hash, string $rolle = "Team", string $benachrichtigung = "Nachrichten"): bool {
    $doppelt = false;
    $ok = passwd_aendern(function ($zeilen) use ($adresse, $hash, $rolle, $benachrichtigung, &$doppelt) {
        $treffer = array_keys(array_filter($zeilen, fn($z) => passwd_zeile_von($z, $adresse)));
        if (count($treffer) > 1) { $doppelt = true; return $zeilen; }
        if ($treffer) { $f = explode(":", $zeilen[$treffer[0]]); $f[1] = $hash; $zeilen[$treffer[0]] = implode(":", $f); }
        else $zeilen[] = "$adresse:$hash:$rolle:$benachrichtigung";
        return $zeilen;
    });
    return $ok && !$doppelt;
}

function passwd_sperren(string $adresse): bool {
    return passwd_aendern(fn($zeilen) => array_map(fn($z) => passwd_zeile_von($z, $adresse) ? "# gesperrt " . german_date("Y-m-d") . " " . $z : $z, $zeilen));
}

// --- passwords and keys ---

// Stricter than FILTER_VALIDATE_EMAIL, which lets "…" local parts with <, >, ", : through (XSS, passwd fields).
function adresse_ok($a): bool {
    return is_string($a) && strlen($a) <= 60 && filter_var($a, FILTER_VALIDATE_EMAIL)
        && preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $a);
}

function passwort_fehler(string $pw): ?string {
    if (strlen($pw) < 14) return "reg_f_laenge";
    if (!preg_match('/[A-ZÄÖÜ]/u', $pw)) return "reg_f_gross";
    if (!preg_match('/[a-zäöüß]/u', $pw)) return "reg_f_klein";
    return null;
}

/** Argon2id (TR-02102-1 B.1) with the second recommended setting of RFC 9106, 4: 3 passes over 64 MiB. */
function pw_schluessel(string $pw, string $salz): string {
    return sodium_crypto_pwhash(SCHLUESSEL_LAENGE, $pw, $salz, 3, 64 * 1024 * 1024, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
}

const ARGON2_PASSWD = ["memory_cost" => 65536, "time_cost" => 3, "threads" => 1];   // as pw_schluessel

/** Hash for passwd: Argon2id; bcrypt while the web server still checks passwd itself (HTTP Basic Auth). */
function passwort_hash(string $pw): string {
    return portal_anmeldung_aktiv() ? password_hash($pw, PASSWORD_ARGON2ID, ARGON2_PASSWD) : password_hash($pw, PASSWORD_BCRYPT);
}

/** New member key and key pair for $b (also returned), MK wrapped with the password; passkeys go, their wrappings
 *  hold the old MK. */
function neues_schluesselpaar(array $b, string $pw, ?string &$paar = null, ?string &$mk = null): array {
    $paar = schluesselpaar_neu();
    $mk = random_bytes(SCHLUESSEL_LAENGE);
    $b["pub"] = base64_encode(oeffentlich($paar));
    $b["sk"] = sym_verschluesseln($mk, $paar);
    $b["mk"] = [];
    $b["passkeys"] = [];
    unset($b["sk_neu"]);
    return mk_mit_passwort($b, $mk, $pw);
}

function mk_mit_passwort(array $b, string $mk, string $pw): array {
    $salz = random_bytes(16);
    $b["mk"]["pw"] = ["salt" => base64_encode($salz), "box" => sym_verschluesseln(pw_schluessel($pw, $salz), $mk)];
    return $b;
}

/** Member key from the password, or null. */
function mk_aus_passwort(array $b, string $pw): ?string {
    $w = $b["mk"]["pw"] ?? null;
    $salz = $w ? base64_decode((string)$w["salt"], true) : false;
    return $salz ? sym_entschluesseln(pw_schluessel($pw, $salz), $w["box"]) : null;
}

/** Key pair from the member key, or null. Follows a rekey chain (sk_neu) and then stores the newest pair as sk in
 *  $b – the caller saves $b. */
function paar_aus_mk(array &$b, ?string $mk): ?string {
    $paar = $mk ? sym_entschluesseln($mk, $b["sk"] ?? null) : null;
    foreach ($b["sk_neu"] ?? [] as $w) $paar = $paar ? entsiegeln($paar, $w) : null;
    $paar = paar_pruefen($b, $paar);
    if ($paar && isset($b["sk_neu"])) { $b["sk"] = sym_verschluesseln($mk, $paar); unset($b["sk_neu"]); }
    return $paar;
}

/** The unwrapped key pair if it belongs to $b's public key (old formats and foreign keys give null). */
function paar_pruefen(array $b, ?string $paar): ?string {
    return $paar && oeffentlich($paar) === base64_decode((string)($b["pub"] ?? ""), true) ? $paar : null;
}

// --- failed attempts ---

/** Message while locked after failed attempts, else null. Only members get locked, so this shows that an address
 *  is one – accepted, the portal names its team publicly anyway. */
function sperre_text(array $b): ?string {
    $bis = (int)($b["sperre_bis"] ?? 0);
    return $bis > time() ? t("anm_gesperrt", (new DateTime("@$bis"))->setTimezone(new DateTimeZone("Europe/Berlin"))->format("H:i")) : null;
}

/** Counts a failure: from the third on, waiting time doubles (max. 15 min). */
function fehlversuch(array $b): array {
    $b["fehl"] = (int)($b["fehl"] ?? 0) + 1;
    if ($b["fehl"] >= 3) $b["sperre_bis"] = time() + min(2 ** ($b["fehl"] - 3) * 30, 900);
    benutzer_speichern($b);
    return $b;
}

function erfolg(array $b): array {
    unset($b["fehl"], $b["sperre_bis"]);
    return $b;
}

// --- session ---

function sitzung(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name("hinweise");
    session_set_cookie_params(["lifetime" => 0, "path" => "/", "secure" => true, "httponly" => true, "samesite" => "Strict"]);
    ini_set("session.use_strict_mode", "1");
    session_start();
    $jetzt = time();
    $s = $_SESSION["anmeldung"] ?? null;
    if ($s && ($jetzt - $s["seit"] > SITZUNG_HOECHSTENS || $jetzt - $s["zuletzt"] > SITZUNG_LEERLAUF)) abmelden();
    elseif ($s) $_SESSION["anmeldung"]["zuletzt"] = $jetzt;
}

/** Puts key pair and member key into the session, encrypted with a fresh cookie key. */
function schluessel_in_sitzung(string $paar, string $mk): void {
    $k = random_bytes(SCHLUESSEL_LAENGE);
    setcookie(COOKIE_SCHLUESSEL, base64_encode($k), ["path" => "/", "secure" => true, "httponly" => true, "samesite" => "Strict"]);
    $_COOKIE[COOKIE_SCHLUESSEL] = base64_encode($k);
    $_SESSION["paar"] = sym_verschluesseln($k, $paar);
    $_SESSION["mk"] = sym_verschluesseln($k, $mk);
}

function aus_sitzung(string $feld): ?string {
    $k = base64_decode((string)($_COOKIE[COOKIE_SCHLUESSEL] ?? ""), true);
    return $k && isset($_SESSION[$feld]) && strlen($k) === SCHLUESSEL_LAENGE ? sym_entschluesseln($k, $_SESSION[$feld]) : null;
}

function paar_der_sitzung(): ?string { return aus_sitzung("paar"); }
function mk_der_sitzung(): ?string { return aus_sitzung("mk"); }

/** First factor passed: remember who, with which keys, waiting for the second factor. */
function erster_faktor(array $b, string $paar, string $mk, string $methode): void {
    session_regenerate_id(true);
    $_SESSION = ["vorlaeufig" => ["email" => $b["email"], "methode" => $methode, "zeit" => time()]];
    schluessel_in_sitzung($paar, $mk);
}

/** The session is bound to the key pair it holds and to the password generation: a reset or rekey (new pub) and
 *  a password change (gen) end other sessions. */
function anmeldung_abschliessen(array $b): void {
    session_regenerate_id(true);
    unset($_SESSION["vorlaeufig"]);
    $paar = paar_der_sitzung();
    $_SESSION["anmeldung"] = ["email" => $b["email"], "pub" => $paar ? base64_encode(oeffentlich($paar)) : "", "gen" => (int)($b["gen"] ?? 0), "seit" => time(), "zuletzt" => time()];
}

/** User file of the logged-in member while the session is still valid: not locked, listed in passwd, same key.
 *  Status may be "wartet"; the caller decides. */
function angemeldet(): ?array {
    $a = get_auth_user();
    $b = $a ? benutzer_laden($a) : null;
    return $b && ($b["status"] ?? "") !== "gesperrt" && isset(passwd_eintraege()[strtolower($a)])
        && ($_SESSION["anmeldung"]["pub"] ?? "") === ($b["pub"] ?? "") && ($_SESSION["anmeldung"]["gen"] ?? 0) === (int)($b["gen"] ?? 0) ? $b : null;
}

function abmelden(): void {
    $_SESSION = [];
    setcookie(COOKIE_SCHLUESSEL, "", ["expires" => 1, "path" => "/", "secure" => true, "httponly" => true, "samesite" => "Strict"]);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}

function vorlaeufig(): ?array {
    $v = $_SESSION["vorlaeufig"] ?? null;
    return $v && time() - $v["zeit"] < 600 ? $v : null;
}

// --- mail links (registration, reset) ---

function link_erzeugen(array $b, string $art): array {
    $t = generate_random_string(32);
    $b["token"] = hash("sha256", $t);
    $b["token_bis"] = time() + LINK_GUELTIG[$art];
    $b["token_art"] = $art;
    return [$b, $t];
}

function link_pruefen(?array $b, string $art, string $t): bool {
    return $b && ($b["token_art"] ?? "") === $art && time() < (int)($b["token_bis"] ?? 0) && hash_equals((string)($b["token"] ?? ""), hash("sha256", $t));
}

function link_verbrauchen(array $b): array {
    unset($b["token"], $b["token_bis"], $b["token_art"]);
    return $b;
}

/** Second factor (or a passkey alone) passed: log in, remember the method, wrap what is missing. */
function anmeldung_vollenden(array $b, string $methode): void {
    $b = erfolg($b);
    $b["last"] = $methode;
    benutzer_speichern($b);
    anmeldung_abschliessen($b);
    if (($b["status"] ?? "") === "aktiv" && ($paar = paar_der_sitzung())) verpackungen_ergaenzen($paar);
}

// --- roles and wrapping ---

function darf_freigeben(string $adresse): bool {
    return in_array(rolle($adresse), ["Team", "DEV"], true);
}

/** Members who get wrappings: active, with a key, listed in passwd. [fingerprint => [public key, role]] */
function empfaenger_alle(): array {
    $e = [];
    $passwd = passwd_eintraege();
    foreach (alle_benutzer() as $adr => $b) {
        if (($b["status"] ?? "") !== "aktiv" || empty($b["pub"]) || !isset($passwd[$adr])) continue;
        $pub = base64_decode($b["pub"]);
        $e[schluessel_fp($pub)] = [$pub, $passwd[$adr][2]];
    }
    return $e;
}

/** Those of them authorised for a case. [fingerprint => public key] */
function empfaenger(string $case_id, ?array $alle = null): array {
    return array_map(fn($x) => $x[0], array_filter($alle ?? empfaenger_alle(), fn($x) => is_role_authorized_for_case($x[1], $case_id)));
}

/** After a member's login and after an approval: encrypts plain cases and wraps every readable case for all
 *  current recipients; wrappings of members no longer listed go. Missing wrappings of others are added here,
 *  since only a member who can read a case can wrap it. Cases already wrapped as they should be are skipped
 *  without lock or decryption. */
function verpackungen_ergaenzen(string $paar): void {
    $notfall = notfall_schluessel(dirname(KUNDE_DIR));
    $alle = empfaenger_alle();
    foreach (get_all_cases() as $c) {
        $id = $c["id"];
        if (!is_current_user_authorized_for_case($id)) continue;
        $soll = empfaenger($id, $alle);
        $ziel = array_map(fn($fp) => "u:$fp", array_keys($soll));
        if (ist_verschluesselt($c) && isset($c["keys"]["notfall"])) {
            $ist = array_filter(array_keys($c["keys"]), fn($n) => str_starts_with($n, "u:"));
            sort($ist); sort($ziel);
            if ($ist === $ziel) continue;
        }
        fall_aendern($id, function ($m) use ($paar, $soll, $notfall) {
            if (!ist_verschluesselt($m)) {
                if (!$notfall) return null;   // without emergency key a lost password would lose the case
                return fall_verschluesseln($m, random_bytes(SCHLUESSEL_LAENGE), substr((string)$m["token"], CASE_ID_LENGTH), $soll, $notfall);
            }
            $k = fallschluessel_mitglied($m, $paar);
            if (!$k) return null;
            $alt = $m["keys"];
            foreach ($m["keys"] as $name => $w) if (str_starts_with($name, "u:") && !isset($soll[substr($name, 2)])) unset($m["keys"][$name]);
            foreach ($soll as $sid => $pub) $m["keys"]["u:$sid"] ??= versiegeln($pub, $k);
            if (!isset($m["keys"]["notfall"]) && $notfall) $m = notfall_verpacken($m, $notfall, $k);
            return $m["keys"] == $alt ? null : $m;
        });
    }
}
