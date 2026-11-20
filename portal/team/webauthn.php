<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// WebAuthn for /team (JSON, called by common/webauthn.js). Actions:
//   login_optionen, login_pruefen         passkey instead of password; key from the PRF output
//   2fa_optionen, 2fa_pruefen             security key as second factor after the password
//   neu_optionen, neu_speichern           (logged in) add a passkey ("art": "passkey") or a security key ("2fa")
// Passkeys without PRF cannot unlock the member's key: neu_speichern refuses them, the page offers them as 2FA.
// Binary values travel base64url.

include_once(__DIR__."/../common/helper.php");
include_once(__DIR__."/../common/login.php");
require_once(__DIR__."/../common/vendor/WebAuthn/WebAuthn.php");
foreach (glob(__DIR__."/../common/vendor/WebAuthn/{Binary,CBOR,Attestation,Attestation/Format}/*.php", GLOB_BRACE) as $f) require_once $f;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

header("Content-Type: application/json");
function antwort(array $a, int $code = 200): never { http_response_code($code); echo json_encode($a); exit; }
function b64u(string $roh): string { return rtrim(strtr(base64_encode($roh), "+/", "-_"), "="); }
function roh(string $b64u): string { return (string)base64_decode(strtr($b64u, "-_", "+/"), true); }
/** Member after the password, waiting for a security key; or the logged-in member. */
function nach_passwort(): ?array { $v = vorlaeufig(); return $v && $v["methode"] === "passwort" ? benutzer_laden($v["email"]) : null; }
/** Same conditions as anmeldung_erzwingen(), and approved. */
function eingeloggt(): ?array { $b = angemeldet(); return ($b["status"] ?? "") === "aktiv" ? $b : null; }

if (!portal_anmeldung_aktiv()) antwort(["fehler" => "aus"], 404);
if (zweitschluessel_fehlt()) antwort(["fehler" => t("zweit_fehlt")], 503);
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !gleiche_herkunft()) antwort(["fehler" => "Anfrage abgelehnt"], 403);
sitzung();
$wa = new WebAuthn(setting("Firma") ?: setting("Domain"), setting("Domain"), ["none", "packed", "fido-u2f", "apple", "android-key", "tpm"], true);
$in = json_decode((string)file_get_contents("php://input"), true) ?: [];
$aktion = (string)($_GET["aktion"] ?? "");

/** Member owning a credential: [user, index, list name]. */
function zu_credential(string $id, array $listen): ?array {
    foreach (alle_benutzer() as $b) foreach ($listen as $l) foreach ($b[$l] ?? [] as $i => $c) if (hash_equals($c["id"], $id)) return [$b, $i, $l];
    return null;
}

/** Verifies an assertion against a stored credential and updates its counter; false on failure. */
function pruefen(WebAuthn $wa, array $in, array &$b, string $liste, int $i, ?bool $uv = null): bool {
    try {
        $wa->processGet(roh($in["clientDataJSON"]), roh($in["authenticatorData"]), roh($in["signature"]), $b[$liste][$i]["pem"],
            new ByteBuffer((string)($_SESSION["wa_challenge"] ?? "")), null, $uv ?? $liste === "passkeys");
    } catch (Throwable $e) { error_log("WebAuthn: " . $e->getMessage()); return false; }
    finally { unset($_SESSION["wa_challenge"]); }
    $b[$liste][$i]["zuletzt"] = german_date("Y-m-d");
    return true;
}

function herausforderung(WebAuthn $wa, object $args): never {
    $_SESSION["wa_challenge"] = $wa->getChallenge()->getBinaryString();
    antwort(["optionen" => $args]);
}

/** PRF salt: one per portal. Browsers allow per-credential salts (evalByCredential) only with allowCredentials,
 *  which a passkey login without address lacks; the PRF output differs per passkey anyway. */
function prf_salz(): string { return b64u(hash("sha256", "hinweise-prf|" . setting("Domain"), true)); }

/** PRF output → key for the private key (hashed so the raw PRF value is never used directly). */
function prf_schluessel(string $prf): string { return hash_hkdf("sha256", $prf, SCHLUESSEL_LAENGE, "hinweise-passkey"); }

switch ($aktion) {
    case "login_optionen":
        $args = $wa->getGetArgs([], 60, true, true, true, true, true, "required");
        $args->publicKey->extensions = (object)["prf" => (object)["eval" => (object)["first" => prf_salz()]]];
        herausforderung($wa, $args);

    case "login_pruefen":
        $treffer = zu_credential((string)($in["id"] ?? ""), ["passkeys"]);
        if ($treffer) { benutzer_sperren($treffer[0]["email"]); $treffer[0] = benutzer_laden($treffer[0]["email"]); }   // fresh after the lock
        [$b, $i] = $treffer ?? [null, false];
        if ($b) $i = array_search((string)$in["id"], array_column($b["passkeys"] ?? [], "id"), true);
        if (!$b || $i === false) antwort(["fehler" => t("wa_unbekannt")], 400);
        if ($sperre = sperre_text($b)) antwort(["fehler" => $sperre], 429);
        if (($b["status"] ?? "") === "gesperrt" || !isset(passwd_eintraege()[strtolower($b["email"])]) || !pruefen($wa, $in, $b, "passkeys", $i)) { fehlversuch($b); antwort(["fehler" => t("wa_fehlgeschlagen")], 400); }
        $mk = sym_entschluesseln(prf_schluessel(roh((string)($in["prf"] ?? ""))), $b["mk"]["pk:" . $b["passkeys"][$i]["id"]] ?? null);
        $paar = paar_aus_mk($b, $mk);
        if (!$paar) antwort(["fehler" => t("wa_prf_fehlt")], 400);
        benutzer_speichern($b);
        erster_faktor($b, $paar, $mk, "passkey");
        if (empty($b["passkey_2fa"])) anmeldung_vollenden($b, $b["last"] ?? "");   // the code method used last stays preselected
        antwort(["weiter" => url("/team/")]);

    case "2fa_optionen":
        $b = nach_passwort();
        // Any FIDO2 credential serves as second factor after the password, passkeys included
        $alle = $b ? array_merge($b["keys2fa"] ?? [], $b["passkeys"] ?? []) : [];
        if (!$alle) antwort(["fehler" => t("wa_fehlgeschlagen")], 400);
        herausforderung($wa, $wa->getGetArgs(array_map(fn($c) => roh($c["id"]), $alle), 60, true, true, true, true, true, "discouraged"));

    case "2fa_pruefen":
        if ($v = vorlaeufig()) benutzer_sperren($v["email"]);
        $b = nach_passwort();
        $i = false;
        foreach (["keys2fa", "passkeys"] as $liste)
            if ($b && ($i = array_search((string)($in["id"] ?? ""), array_column($b[$liste] ?? [], "id"), true)) !== false) break;
        if ($i === false) antwort(["fehler" => t("wa_unbekannt")], 400);
        if ($sperre = sperre_text($b)) antwort(["fehler" => $sperre], 429);
        if (!pruefen($wa, $in, $b, $liste, $i, false)) {   // second factor: possession suffices, like a security key
            fehlversuch($b); antwort(["fehler" => t("wa_fehlgeschlagen")], 400); }
        anmeldung_vollenden($b, "schluessel");
        antwort(["weiter" => url("/team/")]);

    case "neu_optionen":
        $b = eingeloggt();
        if (!$b) antwort(["fehler" => t("wa_anmelden")], 401);
        $passkey = ($in["art"] ?? "") === "passkey";
        $vorhanden = array_map(fn($c) => roh($c["id"]), array_merge($b["passkeys"] ?? [], $b["keys2fa"] ?? []));
        $args = $wa->getCreateArgs(benutzer_id($b["email"]), $b["email"], $b["email"], 60, $passkey, $passkey ? "required" : "discouraged", null, $vorhanden);
        if ($passkey) $args->publicKey->extensions = (object)["prf" => (object)["eval" => (object)["first" => prf_salz()]]];
        $_SESSION["wa_art"] = $passkey ? "passkey" : "2fa";
        herausforderung($wa, $args);

    case "neu_speichern":
        if (get_auth_user()) benutzer_sperren(get_auth_user());
        $b = eingeloggt();
        $mk = mk_der_sitzung();
        if (!$b || !$mk) antwort(["fehler" => t("wa_anmelden")], 401);
        $art = ($in["art"] ?? "") === "2fa" ? "2fa" : ($_SESSION["wa_art"] ?? "2fa");
        try {
            $d = $wa->processCreate(roh($in["clientDataJSON"]), roh($in["attestationObject"]), new ByteBuffer((string)($_SESSION["wa_challenge"] ?? "")), $art === "passkey", true, false);
        } catch (Throwable $e) { error_log("WebAuthn: " . $e->getMessage()); antwort(["fehler" => t("wa_fehlgeschlagen")], 400); }
        finally { unset($_SESSION["wa_challenge"]); }
        $c = ["id" => b64u($d->credentialId), "pem" => $d->credentialPublicKey, "name" => mb_substr(trim((string)($in["name"] ?? "")), 0, 40) ?: german_date("d.m.Y"), "seit" => german_date("Y-m-d")];
        if ($art === "passkey") {
            $prf = roh((string)($in["prf"] ?? ""));
            if (strlen($prf) !== 32) antwort(["fehler" => t("wa_prf_fehlt")], 400);
            $b["passkeys"][] = $c;
            $b["mk"]["pk:" . $c["id"]] = sym_verschluesseln(prf_schluessel($prf), $mk);
        } else $b["keys2fa"][] = $c;
        $b["last"] = "schluessel";   // newly set up counts as used last
        benutzer_speichern($b) || antwort(["fehler" => t("speichern_fehler")], 500);
        antwort(["weiter" => url("/team/konto/")]);
}
antwort(["fehler" => "unbekannte Aktion"], 400);
