<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Forgotten password (PHP login mode): link by mail, new password, new key pair. The old key is lost with the
// password, so the reports must be wrapped again: the access waits for approval by a member – a taken-over
// mailbox alone thus never opens the reports.
include_once(__DIR__."/../common/header.php");
include_once(__DIR__."/../common/login.php");
if (!portal_anmeldung_aktiv()) { echo warnkasten(t("link_ungueltig")); include_once(__DIR__."/../common/footer.php"); exit; }

$u = (string)($_REQUEST["u"] ?? ""); $tk = (string)($_REQUEST["t"] ?? "");
$h = fn($x) => htmlspecialchars($x, ENT_QUOTES);

if ($u !== "" && $tk !== "") {
    benutzer_sperren_id($u);   // a login running at the same time must not overwrite the reset
    $b = benutzer_laden_id($u);
    if (!link_pruefen($b, "zuruecksetzen", $tk) || !isset(passwd_eintraege()[strtolower($b["email"])])) echo warnkasten(t("link_ungueltig"));
    else {
        $pw = (string)($_POST["neu"] ?? "");
        $f = $pw !== "" ? passwort_fehler($pw) : null;
        if ($pw !== "" && !$f && gleiche_herkunft()) {
            $alt = $b;
            $b = erfolg(link_verbrauchen(neues_schluesselpaar($b, $pw)));
            $b["status"] = "wartet";
            $ok = benutzer_speichern($b);
            if ($ok && !($ok = passwd_setzen($b["email"], passwort_hash($pw)))) benutzer_speichern($alt);   // both or neither
            if ($ok) {
                freigabe_anfordern($b, "hat ein neues Passwort gesetzt");
                echo "<h1>" . t("pw_gesetzt_titel") . "</h1><p>" . t("pw_gesetzt_text") . "</p>";
            } else echo warnkasten(t("speichern_fehler"));
        } else {
            echo ($f ? warnkasten(t($f)) : "") . "<h1>" . t("pw_neu_titel") . "</h1><p>" . t("pw_neu_text") . "</p>"
                . "<form class='label-links' method='post' action=''><input type='hidden' name='u' value='" . $h($u) . "'><input type='hidden' name='t' value='" . $h($tk) . "'>"
                . "<input type='email' name='email' value='" . $h($b["email"]) . "' autocomplete='username' hidden>"
                . "<label><span>" . t("konto_pw_neu") . "</span><input type='password' name='neu' required minlength='14' autocomplete='new-password' autofocus></label>"
                . "<button type='submit'>" . symbol("schloss") . t("konto_pw_knopf") . "</button></form>";
        }
    }
}
elseif (isset($_POST["email"])) {
    // Answer first, then the work: the response time must not tell members from others
    echo "<h1>" . t("reg_mail_titel") . "</h1><p>" . t("pw_mail_text") . "</p>";
    include_once(__DIR__."/../common/footer.php");
    if (function_exists("fastcgi_finish_request")) fastcgi_finish_request();
    $adr = trim((string)$_POST["email"]);
    $e = adresse_ok($adr) ? (passwd_eintraege()[strtolower($adr)] ?? null) : null;
    if ($e) {
        benutzer_sperren($adr);
        $b = benutzer_laden($adr) ?? ["email" => $e[0], "status" => "aktiv"];
        [$b, $token] = link_erzeugen($b, "zuruecksetzen");
        $domain = setting("Domain");
        if (benutzer_speichern($b)) mail_text($e[0], "Neues Passwort", "Hier setzen Sie ein neues Passwort: <a href=\"https://$domain/passwort/?u=" . benutzer_id($e[0]) . "&t=$token\">Neues Passwort</a><br>"
            . "Der Link gilt 1 Stunde. Danach muss ein Mitglied der Meldestelle Ihren Zugang wieder freigeben.<br>Sie haben das nicht angefordert? Dann löschen Sie diese E-Mail.");
    }
    exit;
}
else {
    echo "<h1>" . t("pw_titel") . "</h1><p>" . t("pw_text") . "</p><form class='label-links' method='post' action=''>"
        . "<label><span>" . t("reg_email") . "</span><input type='email' name='email' required autocomplete='username' autofocus></label>"
        . "<button type='submit'>" . symbol("senden") . t("pw_knopf") . "</button></form>";
}
include_once(__DIR__."/../common/footer.php");
