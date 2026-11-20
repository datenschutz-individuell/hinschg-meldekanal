<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Login pages of /team in PHP mode: password or passkey, then the second factor. With a password the second
// factor is mandatory (mail code only until TOTP, a security key or a passkey is set up); after a passkey only if the member
// asked for it, and then only TOTP or mail. Failed attempts count until the login is complete. Call anmeldung_erzwingen() before any output.

include_once(__DIR__."/auth.php");
include_once(__DIR__."/totp.php");

const PW_ATTRAPPE = '$argon2id$v=19$m=65536,t=3,p=1$em9qRnBEaWIzckVnMmFFRw$Ec2l6NKY6ukz2zpylt1bj7LZlXHufptF+HMQYmx1Xaw';   // same time for unknown addresses

/** Second factors a member may use now, strongest first: FIDO2 (security key or passkey, only after the password), app,
 *  mail. Mail only when nothing else is set up: it would bypass the others. */
function faktoren(array $b, string $methode): array {
    $f = [];
    if ($methode === "passwort" && (!empty($b["keys2fa"]) || !empty($b["passkeys"]))) $f[] = "schluessel";
    if (!empty($b["totp"])) $f[] = "totp";
    if (!$f) $f[] = "mail";
    return $f;
}

function seite(string $html): never {
    global $logo_ziel;
    $logo_ziel = url("/team");
    include __DIR__."/header.php";
    echo $html;
    include __DIR__."/footer.php";
    exit;
}

// Account line at the end of every team page: name left, a link and "log out" right; the script narrows it step by step
// while it does not fit on one line (joined with "·", then without "Angemeldet als", last two lines). Without script: links wrap below.
function kontozeile(string $ziel, string $text): string {
    return "<p class='hinweis-titel kontozeile'><span><span class='konto-als'>" . t("angemeldet_als") . " </span>"
        . htmlspecialchars(get_name_of_person(get_auth_user(), false)) . "<span class='konto-trenner'> · </span></span>"
        . "<span><a href='$ziel'>$text</a> · <a href='" . url("/team/") . "?abmelden=1'>" . t("anm_abmelden") . "</a></span></p>"
        . "<script>(() => { const p = document.querySelector('.kontozeile'), [a, b] = p.children;
        stufen(p, ['', 'eng', 'enger', 'am-engsten'], () => a.offsetTop === b.offsetTop); })();</script>";
}

function weiter(string $pfad = "/team/"): never {
    header("Location: " . url($pfad), true, 303);
    exit;
}

function freigabe_anfordern(array $b, string $anlass): void {
    $domain = setting("Domain");
    // Members who may approve, except who wants no notifications at all (as everywhere else)
    $an = array_keys(array_filter(passwd_eintraege(), fn($e, $a) => in_array($e[2], ["Team", "DEV"], true) && $e[3] !== "keine" && strcasecmp($a, $b["email"]) !== 0, ARRAY_FILTER_USE_BOTH));
    foreach (array_unique($an) as $a) mail_text($a, "Bitte Zugang prüfen", htmlspecialchars($b["email"]) . " $anlass. Bitte <a href=\"https://$domain/team/\">melden Sie sich an</a> und prüfen Sie den Zugang.<br>"
        . "Geben Sie ihn nur frei, wenn Sie sicher sind: Die Person hat das selbst getan.");
    // The operator only steps in when nobody in the team can approve
    if (!$an && ($admin = setting("Admin-Mail"))) mail_text($admin, "Niemand kann freigeben", htmlspecialchars($b["email"]) . " $anlass. "
        . "Niemand im Team kann den Zugang freigeben. Bitte klären Sie mit der Person, wie es weitergeht.");
}

function anmeldeformular(string $fehler = ""): never {
    $email = htmlspecialchars((string)($_POST["email"] ?? ""), ENT_QUOTES);
    seite(($fehler ? warnkasten($fehler) : "") . "<h1>" . t("anm_titel") . "</h1>"
        . "<form class='label-links' method='post' action=''><p class='anm-art'>" . t("anm_mit_passwort") . "</p>"
        . "<label><span>" . t("reg_email") . "</span><input type='email' name='email' value='$email' required autocomplete='username webauthn' autofocus></label>"
        . "<label><span>" . t("reg_passwort") . "</span><input type='password' name='passwort' required autocomplete='current-password'></label>"
        . "<button type='submit' name='anmelden'>" . symbol("schloss") . t("anm_knopf") . "</button></form>"
        . "<div id='passkey-reihe' hidden><p class='anm-oder'>" . t("anm_oder") . "</p><form class='label-links' onsubmit='return false'><p class='anm-art'>" . t("anm_mit_passkey") . "</p>"
        . "<button type='button' id='passkey-login'>" . symbol("zugangscode") . t("anm_knopf") . "</button></form></div>"
        . "<noscript><p class='hinweis-titel'>" . t("js_passkey") . "</p></noscript><p id='webauthn-meldung'></p>"
        . "<p><a href='" . url("/passwort/") . "'>" . t("anm_vergessen") . "</a> · <a href='" . url("/registrieren/") . "'>" . t("anm_registrieren") . "</a></p>"
        . webauthn_skript());
}

/** Script plus its texts (data attributes, so the script needs no translation). */
function webauthn_skript(): string {
    $h = fn($k) => htmlspecialchars(t($k), ENT_QUOTES);
    return "<div id='webauthn-texte' data-ohne-prf='" . $h("wa_ohne_prf") . "' data-kein-webauthn='" . $h("wa_kein") . "'"
        . " data-nicht-erlaubt-login='" . $h("wa_nicht_login") . "' data-nicht-erlaubt-schluessel='" . $h("wa_nicht_schluessel") . "'"
        . " data-nicht-erlaubt-neu='" . $h("wa_nicht_neu") . "' data-schon-da='" . $h("wa_schon_da") . "' data-nicht-unterstuetzt='" . $h("wa_nicht_unterstuetzt") . "' hidden></div>"
        . "<script src='/common/webauthn.js?v=" . filemtime(__DIR__ . "/webauthn.js") . "' defer></script>";
}

function passwort_anmelden(): never {
    $adr = trim((string)($_POST["email"] ?? ""));
    $pw = (string)($_POST["passwort"] ?? "");
    $e = passwd_eintraege()[strtolower($adr)] ?? null;
    if ($e) benutzer_sperren($adr);
    // Members from the time of nginx login have no file yet: one now, so failed attempts lock them out too
    $b = $adr !== "" ? (benutzer_laden($adr) ?? ($e ? ["email" => $e[0], "status" => "aktiv"] : null)) : null;
    if ($b && ($sperre = sperre_text($b))) anmeldeformular($sperre);
    $ok = password_verify($pw, $e[1] ?? PW_ATTRAPPE);
    if (!$e || !$ok || ($b["status"] ?? "") === "gesperrt") { if ($b) fehlversuch($b); anmeldeformular(t("anm_falsch")); }
    $mk = empty($b["pub"]) ? null : mk_aus_passwort($b, $pw);
    $paar = paar_aus_mk($b, $mk);
    if (!$paar) {
        // No key yet, or the password was set outside the portal: new key, and others must wrap the reports again
        $neu = !empty($b["pub"]);
        $b = neues_schluesselpaar($b, $pw, $paar, $mk);
        if ($neu) { $b["status"] = "wartet"; freigabe_anfordern($b, "hat ein neues Passwort"); }
    }
    unset($b["pw"]);
    if (password_needs_rehash($e[1], PASSWORD_ARGON2ID, ARGON2_PASSWD)) passwd_setzen($b["email"], passwort_hash($pw));   // bcrypt from before
    benutzer_speichern($b);   // failed attempts stay until the second factor (anmeldung_vollenden)
    erster_faktor($b, $paar, $mk, "passwort");
    weiter();
}

function zweiter_faktor(array $v): never {
    benutzer_sperren($v["email"]);
    $b = benutzer_laden($v["email"]);
    if (!$b) { abmelden(); weiter(); }
    $faktoren = faktoren($b, $v["methode"]);
    $methode = (string)($_GET["methode"] ?? $_POST["methode"] ?? "");
    $grund = "";   // why this method was preselected; empty when chosen by hand
    if (!in_array($methode, $faktoren, true)) {
        $zuletzt = in_array($b["last"] ?? "", $faktoren, true);
        $methode = $zuletzt ? $b["last"] : $faktoren[0];
        if (count($faktoren) > 1) $grund = t($zuletzt ? "anm_grund_zuletzt" : "anm_grund_staerkste");
    }
    // Name the FIDO2 kinds actually set up
    $fido = t("anm_m_schluessel" . (empty($b["passkeys"]) ? "_k" : (empty($b["keys2fa"]) ? "_p" : "")));
    $fehler = "";
    $eingabe = is_string($_POST["code"] ?? null) ? $_POST["code"] : "";
    if (isset($_POST["code"]) && !isset($_POST["neu_senden"])) {   // "send again" posts the empty field along
        if ($sperre = sperre_text($b)) $fehler = $sperre;
        elseif ($methode === "totp" && ($z = totp_pruefen($b["totp"]["secret"], $eingabe, (int)($b["totp"]["last"] ?? -1))) !== null) {
            $b["totp"]["last"] = $z; $b["totp"]["zuletzt"] = german_date("Y-m-d");
            anmeldung_vollenden($b, "totp"); weiter();
        }
        elseif ($methode === "mail" && isset($v["mailcode"]) && time() < $v["mailcode_bis"] && hash_equals($v["mailcode"], hash("sha256", preg_replace('/\D/', "", $eingabe)))) {
            anmeldung_vollenden($b, "mail"); weiter();
        }
        else {
            // An expired mail code is no guess (a new one follows below)
            if (!($methode === "mail" && (empty($v["mailcode"]) || time() >= $v["mailcode_bis"]))) $b = fehlversuch($b);
            $fehler = t("anm_code_falsch");
        }
    }
    if ($methode === "mail" && (empty($v["mailcode"]) || time() >= $v["mailcode_bis"] || isset($_POST["neu_senden"]))) {
        $code = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);
        $_SESSION["vorlaeufig"]["mailcode"] = hash("sha256", $code);
        $_SESSION["vorlaeufig"]["mailcode_bis"] = time() + 600;
        mail_text($b["email"], "Anmeldecode", "Ihr Anmeldecode: <b>$code</b><br>Er gilt 10 Minuten.<br>Sie wollten sich nicht anmelden? Dann ändern Sie bitte Ihr Passwort.");
    }
    // "<b>Method:</b> requested, because …", the field, then the other methods as links
    $kopf = "<p><b>" . ($methode === "schluessel" ? $fido : t("anm_m_$methode")) . ":</b><br>" . t("anm_angefragt", $grund) . "</p>";
    $andere = array_values(array_diff($faktoren, [$methode]));
    $wahl = $andere ? "<p class='viel-abstand'><b>" . t("anm_alternativen") . "</b><br>" . implode("<br>", array_map(fn($f) => "<a href='?methode=$f'>"
        . ($f === "schluessel" ? t("anm_alt_schluessel", $fido) : t("anm_alt_$f")) . "</a>", $andere)) . "</p>" : "";
    $feld = $methode === "schluessel"
        ? "<noscript>" . warnkasten(t("js_schluessel")) . "</noscript>" . knopfpaar("<button type='button' id='schluessel-2fa' hidden>" . symbol("zugangscode") . t("anm_schluessel_knopf") . "</button>",
            "<form class='zurueck' method='get' action=''><button class='zweitrangig' name='abmelden' value='1'>" . symbol("zurueck") . t("anm_abbrechen") . "</button></form>") . "<p id='webauthn-meldung'></p>" . webauthn_skript()
        : "<form class='label-links' method='post' action='?methode=$methode'><input type='hidden' name='methode' value='$methode'>"
          . "<p>" . t("anm_text_$methode") . "</p>"
          . "<label><span>" . t("anm_code") . "</span><input type='text' name='code' inputmode='numeric' autocomplete='one-time-code' pattern='[0-9 ]{6,7}' required autofocus></label>"
          . knopfpaar("<button type='submit'>" . symbol("weiter") . t("anm_pruefen") . "</button>", zurueck_in_formular("anm_abbrechen", "", " name='abmelden' value='1'"))
          . ($methode === "mail" ? "<button type='submit' class='zweitrangig' name='neu_senden' formnovalidate>" . t("anm_neu_senden") . "</button>" : "") . "</form>";
    seite(($fehler ? warnkasten($fehler) : "") . "<h1>" . t("anm_zweiter") . "</h1>$kopf$feld$wahl");
}

/** Shows login pages and exits until someone is fully logged in and approved. */
function anmeldung_erzwingen(): void {
    sitzung();
    if (isset($_GET["abmelden"])) { abmelden(); weiter(); }
    if (get_auth_user()) {
        $b = angemeldet();
        if (!$b) { abmelden(); weiter(); }
        if ($b["status"] !== "aktiv") seite("<h1>" . t("anm_wartet_titel") . "</h1><p>" . t("anm_wartet_text") . "</p><p><a href='?abmelden=1'>" . t("anm_abmelden") . "</a></p>");
        return;
    }
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !gleiche_herkunft()) { http_response_code(403); exit("Anfrage von fremder Seite abgelehnt."); }
    if ($v = vorlaeufig()) zweiter_faktor($v);
    if (isset($_POST["anmelden"])) passwort_anmelden();
    anmeldeformular();
}
