<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Own access: password, authenticator app (TOTP), security keys, passkeys. Only in PHP login mode.
include_once(__DIR__."/../../common/helper.php");
include_once(__DIR__."/../../common/login.php");
if (!portal_anmeldung_aktiv()) { http_response_code(404); exit; }
anmeldung_erzwingen();
// Same check as team/index.php: SameSite treats sibling portals (*.<Domain-Endung>) as the same site
if ($_SERVER["REQUEST_METHOD"] === "POST" && !gleiche_herkunft()) { http_response_code(403); exit("Anfrage von fremder Seite abgelehnt."); }

if ($_SERVER["REQUEST_METHOD"] === "POST") benutzer_sperren(get_auth_user());   // fresh state, no lost updates
$b = angemeldet();   // checked again under the lock
if (!$b) { abmelden(); weiter(); }
$hinweis = $fehler = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["pw_aendern"])) {
        $mk = mk_der_sitzung();
        $neu = (string)($_POST["neu"] ?? "");
        $e = passwd_eintraege()[strtolower($b["email"])] ?? null;
        if ($s = sperre_text($b)) $fehler = $s;
        elseif (!$e || !password_verify((string)($_POST["alt"] ?? ""), $e[1])) { $b = fehlversuch($b); $fehler = t("konto_pw_alt_falsch"); }
        elseif ($f = passwort_fehler($neu)) $fehler = t($f);
        elseif (!$mk) $fehler = t("wa_anmelden");
        elseif (!benutzer_speichern($nb = ["gen" => (int)($b["gen"] ?? 0) + 1] + erfolg(mk_mit_passwort($b, $mk, $neu)))) $fehler = t("speichern_fehler");
        // passwd and user file must hold the same password: else the next login makes a new key, needing approval
        elseif (!passwd_setzen($b["email"], passwort_hash($neu))) { benutzer_speichern($b); $fehler = t("speichern_fehler"); }
        else { $b = $nb; $_SESSION["anmeldung"]["gen"] = $b["gen"]; $hinweis = t("konto_pw_geaendert"); }   // other sessions end
    }
    elseif (isset($_POST["totp_start"])) $_SESSION["totp_neu"] = totp_neu();
    elseif (isset($_POST["totp_bestaetigen"]) && isset($_SESSION["totp_neu"])) {
        if (($z = totp_pruefen($_SESSION["totp_neu"], (string)($_POST["code"] ?? ""))) === null) $fehler = t("anm_code_falsch");
        else { $b["totp"] = ["secret" => $_SESSION["totp_neu"], "last" => $z, "seit" => german_date("Y-m-d"),
            "name" => mb_substr(trim(is_string($_POST["totp_name"] ?? null) ? $_POST["totp_name"] : ""), 0, 40) ?: t("konto_totp_name_platz")]; $b["last"] = "totp"; unset($_SESSION["totp_neu"]); benutzer_speichern($b); $hinweis = t("konto_totp_an"); }
    }
    elseif (isset($_POST["weg_totp"])) { unset($b["totp"]); benutzer_speichern($b); $hinweis = t("konto_totp_aus"); }
    elseif (isset($_POST["weg_schluessel"]) || isset($_POST["weg_passkey"])) {
        $liste = isset($_POST["weg_passkey"]) ? "passkeys" : "keys2fa";
        $id = (string)($_POST["weg_passkey"] ?? $_POST["weg_schluessel"]);
        $b[$liste] = array_values(array_filter($b[$liste] ?? [], fn($c) => $c["id"] !== $id));
        unset($b["mk"]["pk:$id"]);
        benutzer_speichern($b);
    }
    elseif (isset($_POST["passkey_2fa"])) { $b["passkey_2fa"] = $_POST["passkey_2fa"] === "ja"; benutzer_speichern($b); }
}

$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
$liste = function (string $art, array $creds) use ($h): string {
    if (!$creds) return "<p>" . t("konto_keine") . "</p>";
    return "<ul class='sammelliste geraeteliste'>" . implode("", array_map(fn($c) => "<li><span class='sammeltitel'>" . $h($c["name"]) . "</span><span class='sammelmeta'>"
        . implode(" · ", array_merge(isset($c["seit"]) ? [t("konto_seit", date("d.m.Y", strtotime($c["seit"])))] : [],
            isset($c["zuletzt"]) ? [t("konto_zuletzt", date("d.m.Y", strtotime($c["zuletzt"])))] : []))
        . "</span><form method='post' action=''><button class='zweitrangig' name='weg_$art' value='" . $h($c["id"]) . "'>" . symbol("ablehnen") . t("konto_entfernen") . "</button></form></li>", $creds)) . "</ul>";
};

$totp = !empty($b["totp"])
    ? $liste("totp", [["id" => "totp", "name" => $b["totp"]["name"] ?? t("konto_totp_name_platz")] + array_intersect_key($b["totp"], ["seit" => 1, "zuletzt" => 1])])
    : (isset($_SESSION["totp_neu"])
        ? "<p>" . t("konto_totp_scannen") . "</p><noscript><p class='sammelmeta'>" . t("js_qr") . "</p></noscript><div id='totp-qr' data-uri='" . $h(totp_uri($_SESSION["totp_neu"], $b["email"])) . "'></div>"
          . "<p>" . t("konto_totp_geheim") . "</p>" . code_kasten($_SESSION["totp_neu"], " ")
          . "<form class='label-links' method='post' action=''><label><span>" . t("konto_name") . "</span><input type='text' name='totp_name' maxlength='40' placeholder='" . $h(t("konto_totp_name_platz")) . "'></label>"
          . "<label><span>" . t("anm_code") . "</span><input type='text' name='code' inputmode='numeric' autocomplete='one-time-code' required></label>"
          . "<button name='totp_bestaetigen'>" . symbol("weiter") . t("konto_totp_bestaetigen") . "</button></form>"
          . "<script src='/common/qrcode.js'></script><script>(()=>{const d=document.getElementById('totp-qr');const q=qrcode(0,'M');q.addData(d.dataset.uri);q.make();d.innerHTML=q.createSvgTag(5,4);})();</script>"
        : "<p>" . t("konto_totp_text") . "</p><form method='post' action=''><button name='totp_start'>" . t("konto_totp_einrichten") . "</button></form>");

$ohne_zweiten = faktoren($b, "passwort") === ["mail"];
seite(($fehler ? warnkasten($fehler) : "") . ($hinweis ? bestaetigung($hinweis) : "")
    . "<h1>" . t("konto_titel") . "</h1>"
    . ($ohne_zweiten ? "<div class='fristen-info'>" . t("konto_nur_mail") . "</div>" : "")
    . "<h2>" . t("konto_pw") . "</h2><form class='label-links' method='post' action=''>"
    . "<input type='email' name='email' value='" . $h($b["email"]) . "' autocomplete='username' hidden>"
    . "<label><span>" . t("konto_pw_alt") . "</span><input type='password' name='alt' required autocomplete='current-password'></label>"
    . "<label><span>" . t("konto_pw_neu") . "</span><input type='password' name='neu' required minlength='14' autocomplete='new-password'></label>"
    . "<button name='pw_aendern'>" . symbol("schloss") . t("konto_pw_knopf") . "</button></form>"
    . "<h2>" . t("konto_totp") . "</h2>$totp"
    . "<h2>" . t("konto_schluessel") . "</h2><p>" . t("konto_schluessel_text") . "</p>" . $liste("schluessel", $b["keys2fa"] ?? [])
    . "<div class='neu-reihe' hidden><form class='label-links' onsubmit='return false'><label><span>" . t("konto_name") . "</span><input type='text' id='neu-name-schluessel' maxlength='40' placeholder='" . $h(t("konto_name_platz")) . "'></label></form>"
    . "<p class='knopfreihe'><button type='button' class='zweitrangig' id='neu-schluessel'>" . symbol("schloss") . t("konto_schluessel_neu") . "</button></p></div>"
    . "<h2>" . t("konto_passkeys") . "</h2><p>" . t("konto_passkeys_text") . "</p>" . $liste("passkey", $b["passkeys"] ?? [])
    . "<noscript>" . warnkasten(t("js_passkey_neu")) . "</noscript><div id='neu-reihe' class='neu-reihe' hidden><form class='label-links' onsubmit='return false'><label><span>" . t("konto_name") . "</span><input type='text' id='neu-name' maxlength='40' placeholder='" . $h(t("konto_name_platz")) . "'></label></form>"
    . "<p class='knopfreihe'><button type='button' id='neu-passkey'>" . symbol("zugangscode") . t("konto_passkey_neu") . "</button>"
    . "<button type='button' class='zweitrangig' id='als-2fa' hidden>" . t("konto_als_2fa") . "</button></p></div><p id='webauthn-meldung' role='status'></p>"
    . (!empty($b["passkeys"]) ? "<form method='post' action=''><p>" . t("konto_passkey_2fa_text") . "</p><button class='zweitrangig' name='passkey_2fa' value='" . (!empty($b["passkey_2fa"]) ? "nein" : "ja") . "'>"
        . t(!empty($b["passkey_2fa"]) ? "konto_passkey_2fa_aus" : "konto_passkey_2fa_an") . "</button></form>" : "")
    . webauthn_skript() . kontozeile(url("/team"), t("team_titel")));
