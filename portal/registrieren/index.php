<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/../common/header.php");
include_once(__DIR__."/../common/auth.php");
include_once(__DIR__."/../common/spam-protection.php");


/** PHP login mode: confirmation link by mail, key pair now, approval by a member later. Answer first, then the
 *  work: neither text nor time tells known addresses from others. A locked access (refused reset) stays locked. */
function registrieren_portal(string $adresse, string $pw): void {
    echo "<h1>" . t("reg_mail_titel") . "</h1><p>" . t("reg_mail_text") . "</p>";
    include_once(__DIR__."/../common/footer.php");
    if (function_exists("fastcgi_finish_request")) fastcgi_finish_request();
    $domain = setting("Domain");
    benutzer_sperren($adresse);
    if (isset(passwd_eintraege()[strtolower($adresse)])) {
        mail_text($adresse, "Sie haben schon einen Zugang", "Für diese Adresse gibt es schon einen Zugang. Passwort vergessen? <a href=\"https://$domain/passwort/\">Neues Passwort</a>");
    } elseif (!in_array(benutzer_laden($adresse)["status"] ?? "", ["wartet", "gesperrt"], true)) {
        $b = neues_schluesselpaar(["email" => $adresse, "status" => "unbestaetigt", "pw" => passwort_hash($pw)], $pw);
        [$b, $token] = link_erzeugen($b, "bestaetigen");
        if (benutzer_speichern($b)) mail_text($adresse, "Adresse bestätigen", "Bitte bestätigen Sie Ihre Adresse: <a href=\"https://$domain/registrieren/?u=" . benutzer_id($adresse) . "&t=$token\">Adresse bestätigen</a><br>"
            . "Der Link gilt 24 Stunden.<br>Sie haben keinen Zugang beantragt? Dann löschen Sie diese E-Mail.");
    }
    exit;
}


// MAIN

// Text key of the first failed check of the registration form, null if all pass; spam reasons as from spam_formular_pruefen().
function registrierung_fehler(): ?string {
    if ($spam = spam_formular_pruefen()) return $spam;
    if (!is_string($_POST["login_email"]) || !adresse_ok($_POST["login_email"])) return "reg_f_email";
    if (!is_string($_POST["login_pw"])) return "reg_f_pw";
    return passwort_fehler($_POST["login_pw"]);
}

// The link only shows a form, confirmed by POST with the password of the application: mail scanners that open
// links confirm nothing, and nobody confirms a registration someone else made with their address
if (portal_anmeldung_aktiv() && isset($_REQUEST["u"], $_REQUEST["t"])) {
    $u = (string)$_REQUEST["u"]; $tk = (string)$_REQUEST["t"];
    benutzer_sperren_id($u);
    $b = benutzer_laden_id($u);
    $pw = (string)($_POST["passwort"] ?? "");
    $fehler = "";
    if ($pw !== "" && gleiche_herkunft()) {
        if ($s = sperre_text($b ?? [])) $fehler = $s;
        elseif (link_pruefen($b, "bestaetigen", $tk) && !password_verify($pw, (string)($b["pw"] ?? ""))) { $b = fehlversuch($b); $fehler = t("reg_pw_falsch"); }
    }
    if (!link_pruefen($b, "bestaetigen", $tk)) echo warnkasten(t("link_ungueltig"));
    elseif ($pw === "" || $fehler || !gleiche_herkunft()) {
        $h = fn($x) => htmlspecialchars($x, ENT_QUOTES);
        echo ($fehler ? warnkasten($fehler) : "") . "<h1>" . t("reg_bestaetigen") . "</h1><p>" . t("reg_bestaetigen_text") . "</p><form class='label-links' method='post' action=''>"
            . "<input type='hidden' name='u' value='" . $h($u) . "'><input type='hidden' name='t' value='" . $h($tk) . "'>"
            . "<input type='email' name='email' value='" . $h($b["email"]) . "' autocomplete='username' hidden>"
            . "<label><span>" . t("reg_passwort") . "</span><input type='password' name='passwort' required autocomplete='current-password' autofocus></label>"
            . "<button type='submit'>" . symbol("weiter") . t("reg_bestaetigen") . "</button></form>";
    } else {
        $b = erfolg($b);
        $b["status"] = "wartet";
        $b = link_verbrauchen($b);
        benutzer_speichern($b);
        include_once(__DIR__."/../common/login.php");
        freigabe_anfordern($b, "möchte einen Zugang");
        echo "<h1>" . t("reg_bestaetigt_titel") . "</h1><p>" . t("reg_bestaetigt_text") . "</p>";
    }
}
elseif (isset($_POST['login_email'], $_POST['login_pw']) && !($fehler = registrierung_fehler())) {
    // Form data was submitted and passed the checks; on an error the form below shows it with the address kept
    $login_email = $_POST["login_email"]; $login_pw = $_POST["login_pw"];
    if (portal_anmeldung_aktiv()) registrieren_portal($login_email, $login_pw);

    // Hash the password, compatible with NGINX
    $hashed_pw = password_hash($login_pw, PASSWORD_BCRYPT);

    // New user goes into a new file. Admin must copy the user into the passwd file by hand to activate it
    $date = german_date("Y-m-d_H-i-s");
    file_put_contents(KUNDE_DIR.'/'.$date.'.passwd', "$login_email:$hashed_pw:Team:Nachrichten\n", FILE_APPEND);

    $path = KUNDE_DIR;
    $domain = setting("Domain");

    // Tell the operator so they activate the user; "Freischaltung" names their tool, followed by the portal folder
    $admin = setting("Admin-Mail");
    if ($admin) send_mail(
            $admin,"$domain Neuer Benutzer",
	    "$login_email hat sich registriert und wartet auf Freischaltung.\n"
	    . "Vorgeschlagen ist die Rolle Team (sieht alle Meldungen) – bitte vor dem Freischalten prüfen.\n\n".(setting("Freischaltung") ?: "Portal:")." ".basename($path),
            "From: registrieren@$domain\nReply-To: $login_email"
    );
    else error_log("Admin-Mail fehlt in settings.json: Registrierung ".basename($path)."/$date.passwd ohne Benachrichtigung");
    ?>

    <h1><?=t("reg_gespeichert_titel")?></h1>
    <p><?=t("reg_gespeichert_text")?></p>
    <?php
}
else {
    // Address from the failed attempt or passed in the link; ignored if not a string
    $login_email = $_POST["login_email"] ?? $_GET["login_email"] ?? "";
    if (!is_string($login_email) || (!isset($fehler) && !adresse_ok($login_email))) $login_email = "";
    if($login_email!="") { $focus_em=""; $focus_pw=" autofocus"; } else { $focus_em=" autofocus"; $focus_pw=""; }
    if (!empty($fehler)) echo warnkasten(t(str_starts_with($fehler, "spam_") ? "reg_f_spam" : $fehler));
    // Too fast from our own form: keep the password so a generated one stays valid; never for other rejections
    $pw_behalten = ($fehler ?? "") === "spam_zu_schnell" && gleiche_herkunft() && is_string($_POST["login_pw"] ?? null);
    ?>

    <h1><?=t("reg_titel")?></h1>
    <p><?=t("reg_text1", htmlspecialchars(url("/team")))?></p>
    <p><?=t("reg_text2")?></p>
    <p><?=t(portal_anmeldung_aktiv() ? "reg_text3_portal" : "reg_text3", htmlspecialchars(url("/team")))?></p>
    <form class="label-links" action="" method="post">
        <label>
            <span><?=t("reg_email")?></span>
            <input type="text" name="login_email" value="<?=htmlspecialchars($login_email, ENT_QUOTES)?>" required placeholder="<?=t("reg_email_platz")?>" minlength="5" maxlength="40"<?=$focus_em?> <?=remaining_signs_hint()?>>
        </label>
        <label>
            <span><?=t("reg_passwort")?>
                <script>
                    function switchPasswordPreview() {
                        let button = document.getElementById("password_switch");
                        let input = document.getElementById("password_input");
                        if (button.innerHTML === <?=json_encode(t("reg_anzeigen"))?>) {
                            button.innerHTML = <?=json_encode(t("reg_verbergen"))?>;
                            input.setAttribute("type", "text");
                        }
                        else {
                            button.innerHTML = <?=json_encode(t("reg_anzeigen"))?>;
                            input.setAttribute("type", "password");
                        }
                    }
                    document.write("<button type='button' id='password_switch' class='pw-switch-button' onclick='switchPasswordPreview()'>" + <?=json_encode(t("reg_anzeigen"))?> + "</button>");
                </script>
            </span>
            <input id="password_input" type="password" name="login_pw" value="<?=$pw_behalten ? htmlspecialchars($_POST["login_pw"], ENT_QUOTES) : ""?>" required placeholder="<?=t("reg_passwort_platz")?>" minlength="14"<?=$focus_pw?>>
        </label>


        <?=spam_felder()?>
        <button type="submit"><?=symbol("senden")?><?=t("reg_knopf")?></button>
    </form>

    <?php
}

include_once(__DIR__."/../common/footer.php");
