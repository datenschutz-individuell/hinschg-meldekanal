<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Shared code, symlinked from _core into each portal: __DIR__ is _core, portal files hang off DOCUMENT_ROOT.

const CASE_ID_LENGTH = 8;
define("PORTAL_DIR", rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), "/"));
define("KUNDE_DIR", dirname(PORTAL_DIR));

include_once(__DIR__."/lang.php");
include_once(__DIR__."/deadlines.php");
include_once(__DIR__."/crypto.php");


function standardise_newlines($text): string {
    return str_replace("\r", "\n", str_replace("\r\n", "\n", $text));
}


function get_case_id_from_token($token): string {
    // Token = case ID followed by a secret
    return substr($token, 0, CASE_ID_LENGTH);
}


function clean_token($dirty_token_or_case_id): string {
    // Tokens consist of digits and ASCII letters only
    return preg_replace("/[^a-zA-Z0-9]+/", "", $dirty_token_or_case_id);
}


function portal_anmeldung_aktiv(): bool {
    return setting("Anmeldung") === "portal";
}


/** PHP login encrypts every report also for the operator's emergency key; without it the portal must not take
 *  reports (they would be lost with the last password), so it refuses service and says why. */
function zweitschluessel_fehlt(): bool {
    static $fehlt;
    return $fehlt ??= portal_anmeldung_aktiv() && !notfall_schluessel(dirname(KUNDE_DIR));
}


function get_auth_user(): string {
    // PHP login (auth.php): the session; else nginx auth_basic. The settings field "Debug" names a test user only on
    // the command line and PHP's built-in server: on a real server a vhost typo plus "Debug" would open the team page.
    if (portal_anmeldung_aktiv()) return $_SESSION["anmeldung"]["email"] ?? "";
    return $_SERVER['PHP_AUTH_USER'] ?? (str_starts_with(PHP_SAPI, "cli") ? (string)setting("Debug") : "");
}


// Against CSRF: a POST must come from a page of the same portal. Browsers send Basic auth from foreign pages
// too, so SameSite does not help. Without either header (very old browsers) nothing is blocked.
function gleiche_herkunft(): bool {
    $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;
    if ($site !== null) return $site === "same-origin";
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null) return parse_url($origin, PHP_URL_HOST) === explode(":", $_SERVER['HTTP_HOST'] ?? "")[0];
    return true;
}


function get_case_file_path($case_id): string {
    $cases_folder_path = KUNDE_DIR."/cases";
    is_dir($cases_folder_path) || mkdir($cases_folder_path);
    return "$cases_folder_path/$case_id.json";
}


// Case files are replaced atomically (temp file + rename), so readers need no lock. Writers share one lock per
// portal, otherwise load-change-save would drop a message written at the same time.
function unter_fallsperre(callable $f): mixed {
    $h = @fopen(KUNDE_DIR . "/.cases-lock", "c");
    if (!$h || !flock($h, LOCK_EX)) { error_log("Fallsperre fehlgeschlagen: " . KUNDE_DIR); return null; }
    try { return $f(); } finally { flock($h, LOCK_UN); fclose($h); }
}


/** Writes a case under unter_fallsperre(). $neu: only if absent; otherwise only if present, so a case deleted
 *  meanwhile is never recreated. */
function fall_schreiben(string $case_id, array $case_data, bool $neu = false): bool {
    $datei = get_case_file_path($case_id);
    if (file_exists($datei) === $neu) { error_log("Meldung " . ($neu ? "existiert schon" : "nicht (mehr) vorhanden") . ": $case_id"); return false; }
    temp_reste_entfernen(KUNDE_DIR);
    unset($case_data["id"]);   // the file name is the ID (load_case_data, get_all_cases)
    $json = json_encode($case_data);
    if ($json !== false && atomar_schreiben($datei, $json, FALL_TEMP)) return true;
    error_log("Meldung nicht gespeichert: $case_id");
    return false;
}


function german_date($format = "Y-m-d H:i:s"): string {
    $tz = 'Europe/Berlin';
    $timestamp = time();
    try {
        $dt = new DateTime("now", new DateTimeZone($tz));
        $dt->setTimestamp($timestamp);
        return $dt->format($format);
    } catch (Exception $e) {
        error_log("Zeitstempel konnte nicht erzeugt werden: " . $e->getMessage());
        die("Server konnte keinen Zeitstempel generieren!");
    }
}


function get_name_of_person($person, $anonymize_team): string {
    if (!$person) {
        return "EMPTY_PERSON";
    }

    // Anonymised = the reporter's view: the team appears only as the reporting office.
    if ($person === "meldende_person") {
        return t("person_meldende");
    }
    elseif ($anonymize_team) {
        return t("person_meldestelle");
    }

    $parts = explode('@', $person);
    if (count($parts) === 2) {
        return ucwords(implode(' ', explode(".", $parts[0])));
    }
    else {
        return ucwords($person);
    }
}


function generate_random_string($length): string {
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pieces = [];
    $max = strlen($keyspace) - 1;
    for ($i = 0; $i < $length; ++$i) {
        try {
            $pieces [] = $keyspace[random_int(0, $max)];
        } catch (Exception $e) {
            error_log("Kritischer Fehler! Generierung einer Zufallszahl fehlgeschlagen: " . $e->getMessage());
            die("Kritischer Fehler! Generierung einer Zufallszahl fehlgeschlagen!");
        }
    }
    return implode('', $pieces);
}


/** All readable cases; IDs of unreadable case files go to $unlesbar. */
function get_all_cases(?array &$unlesbar = null): Array
{
    $unlesbar = [];
    is_dir(KUNDE_DIR."/cases") || mkdir(KUNDE_DIR."/cases");
    $handle = opendir(KUNDE_DIR."/cases");
    if (!$handle) {
        error_log("Fehler beim Lesen des Meldungen-Ordners!");
        die("Fehler beim Lesen des Meldungen-Ordners!");
    }

    $cases = [];
    while (($file = readdir($handle)) !== false) {
        if (str_ends_with($file, ".json") && strlen($file) == 13) {
            $case = load_json_from_file(KUNDE_DIR."/cases/$file");
            if (!$case) { if (file_exists(KUNDE_DIR."/cases/$file")) $unlesbar[] = substr($file, 0, CASE_ID_LENGTH); continue; }   // deleted meanwhile: not unreadable
            $case["status"] ??= "";   // a case without status must not break the list
            $case["id"] = substr($file, 0, CASE_ID_LENGTH);   // encrypted cases store no token to derive it from
            $cases[] = $case;
        }
    }
    closedir($handle);

    return $cases;
}


function load_json_from_file($file_name, $error_msg = ""): Array {
    if (!$error_msg) $error_msg = "Datei existiert nicht: $file_name";

    if (!file_exists($file_name) || !is_file($file_name)) {
        error_log($error_msg);
        return [];
    }

    $file_data =  file_get_contents($file_name);

    if (!$file_data) {
        error_log("Dateiinhalt kann nicht gelesen werden oder ist leer: " . $file_name);
        return [];
    }

    $json_data = json_decode($file_data, true);

    if (json_last_error() != JSON_ERROR_NONE) {
        error_log("Fehler beim dekodieren von json Datei ($file_name): " . json_last_error_msg());
        return [];
    }

    return $json_data;
}


function illustriert(): bool {
    return setting("Illustrationen") !== "nein";
}

// Own line icons, inline so CSS colours them; decorative only (aria-hidden).
const SYMBOLE = [
    "melden"      => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.5-5A8 8 0 1 1 21 12z"/><path d="M12.5 8v4"/><path d="M12.5 15.5h.01"/>',
    "zugangscode" => '<circle cx="8" cy="16" r="5"/><circle cx="8" cy="16" r="1.2"/><path d="M11.5 12.5 20.5 3.5"/><path d="M15.5 8.5l3 3"/><path d="M18.5 5.5l2.5 2.5"/>',
    "antwort"     => '<path d="M4 4h10a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H8l-4 3v-3a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M15 8h5a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1v3l-4-3h-5a1 1 0 0 1-1-1v-3"/>',
    "schutz"      => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
    "suche"       => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/><path d="M8.6 8.8a2 2 0 1 1 2.6 1.9c-.5.2-.7.6-.7 1.1"/><path d="M10.5 13.6h.01"/>',
    "schloss"     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><path d="M12 15v2"/>',
    "stift"       => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
    "papierkorb"  => '<path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
    "senden"      => '<path d="M21 3 3 10.5l7.5 3L14 21z"/><path d="M10.5 13.5 21 3"/>',
    "kopieren"    => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
    "drucker"     => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
    "zurueck"     => '<path d="M10 6 4 12l6 6"/><path d="M4 12h16"/>',
    "weiter"      => '<path d="M14 6l6 6-6 6"/><path d="M20 12H4"/>',
    "ablehnen"    => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
    // Company icon as in the conversation (style.php, .chat-team), filled instead of stroked
    "firma"       => '<path fill="currentColor" stroke="none" fill-rule="evenodd" d="M3 21V4l9-2v5l9 2v12H3zm3-3h3v-2H6v2zm0-4h3v-2H6v2zm0-4h3V8H6v2zm7 8h3v-2h-3v2zm0-4h3v-2h-3v2zm4 4h2v-2h-2v2zm0-4h2v-2h-2v2z"/>',
    "haus"        => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/>',
    "warnung"     => '<path d="M12 3.5 2.5 20h19z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
];
// Red box for messages about something that was prevented; role=alert announces it at once.
function warnkasten(string $text): string {
    return "<div class='warnkasten' role='alert'><p class='warnkasten-titel'>" . symbol("warnung") . t("warnung_titel") . "</p><p>$text</p></div>";
}

// Controller runs the portal itself on its own server: no service provider to mention in the privacy page.
function self_hosted(): bool {
    return setting("Betreiber") === "" && setting("Hoster") === "";
}

// Privacy sentences that depend on who runs the portal: statistics and storage mention the service provider only if
// there is one; a backup retention ("Sicherung_Monate") is stated only where set.
function privacy_text(string $which): string {
    $monate = (int)setting("Sicherung_Monate");   // already resolved in settings(): inherited only without a hoster
    if ($which === "sicherung") return $monate < 1 ? t("dse_sicherung") : t(self_hosted() ? "dse_sicherung_monate_selbst" : "dse_sicherung_monate", $monate);
    return t("dse_{$which}_" . (self_hosted() ? "selbst" : "fremd"));
}

// Who runs the portal and where, for the privacy page: Betreiber empty = the controller, Hoster empty = own server.
function operation_sentence(): string {
    $e = fn($k) => htmlspecialchars(setting($k));
    $sentence = setting("Betreiber") === "" ? t("betrieb_selbst") : t("betrieb_fremd", "<span class='name'>" . $e("Betreiber") . "</span>");
    $sentence .= " " . (setting("Hoster") === "" ? t("betrieb_server_eigen") : t("betrieb_server_hoster", "<span class='name'>" . $e("Hoster") . "</span>"));
    if (setting("Serverstandort") !== "") $sentence .= " " . t("betrieb_standort", "<span class='name'>" . $e("Serverstandort") . "</span>");
    return $sentence . ".";
}

// "Technik und Quellen" at the end of the privacy page: fonts with their licence, read from the
// notes check-fonts.php writes into fonts.css, so every portal lists exactly what it serves.
const REPO_URL = "https://github.com/datenschutz-individuell/hinschg-meldekanal";

function sources_html(): string {
    $names = ["OFL" => "SIL Open Font License 1.1", "UFL" => "Ubuntu Font Licence 1.0", "APACHE" => "Apache License 2.0"];
    preg_match_all('~/\* (.+?) – licence: (\w+), full text in (/LICENSE-[a-z0-9-]+\.txt) \*/~', (string)@file_get_contents(PORTAL_DIR . "/fonts.css"), $m, PREG_SET_ORDER);
    $fonts = array_map(fn($f) => htmlspecialchars($f[1]) . " (<a href='" . $f[3] . "'>" . ($names[$f[2]] ?? $f[2]) . "</a>)", $m);
    return "<hr class='quellen'><h1>" . t("quellen_titel") . "</h1><ul>"
        . "<li>" . ($fonts ? t("quellen_schriften", implode(", ", $fonts)) : t("quellen_systemschrift")) . "</li>"
        . "<li>" . t("quellen_symbole") . "</li>"
        . "<li>" . t("quellen_software", "<a href='https://datenschutz-individuell.de' target='extern' rel='noopener noreferrer'>Datenschutz individuell</a>",
            "<a href='" . REPO_URL . "/blob/main/" . (lang() === "de" ? "LICENSE.md" : "LICENSE.en.md") . "' target='extern' rel='noopener noreferrer'>" . t("quellen_lizenz") . "</a>",
            "<a href='" . REPO_URL . "' target='extern' rel='noopener noreferrer'>hinschg-meldekanal</a>") . "</li></ul>";
}

function symbol(string $name): string {
    return '<svg class="symbol" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round">' . SYMBOLE[$name] . '</svg>';
}

function setting($setting_name): string | array {
    return settings()[$setting_name] ?? "";
}


function settings(): Array {
    static $_settings;

    if (!isset($_settings)) {
        $defaults = load_json_from_file(__DIR__."/../common/defaults.json");
        $settings = load_json_from_file(PORTAL_DIR."/settings.json");
        // Operator settings for all portals: settings.json next to the portal folders, outside every web root
        $operator = is_file(dirname(KUNDE_DIR)."/settings.json") ? load_json_from_file(dirname(KUNDE_DIR)."/settings.json") : [];
        // Customer settings override operator settings, which override the defaults (rule shared with the watcher)
        $_settings = einstellungen_zusammenfuehren($defaults, $operator, $settings, basename(KUNDE_DIR));
        // Without a domain suffix mails get an invalid sender and are rejected: say why
        if (!absenderdomain_gueltig($_settings["Domain"])) error_log("Absenderdomain \"".$_settings["Domain"]."\" ungültig: ".absenderdomain_hinweis(dirname(KUNDE_DIR)));

        if (file_exists(PORTAL_DIR."/style.css"))   { $_settings["Style"]    = "/style.css"; }
        if (file_exists(PORTAL_DIR."/favicon.ico")) { $_settings["FavIcon"]  = "/favicon.ico"; }
    }

    return $_settings;
}


// Mail addresses without @ in the source, against harvesters; customer HTML may already contain mailto links.
// Supervisory authority on the privacy page, from "Aufsicht": one entry per language, "de" as fallback.
function aufsicht_html(): string {
    $alle = setting("Aufsicht");
    $a = is_array($alle) ? ($alle[lang()] ?? $alle["de"] ?? []) : [];
    if (!is_string($a["Name"] ?? null) || $a["Name"] === "") { error_log("Einstellung \"Aufsicht\" fehlt"); return ""; }
    $h = fn($x) => htmlspecialchars((string)$x);
    $zeilen = [isset($a["Link"]) ? "<a href=\"" . $h($a["Link"]) . "\" target=\"extern\" rel=\"noopener noreferrer\">" . $h($a["Name"]) . "</a>" : $h($a["Name"])];
    foreach ((array)($a["Anschrift"] ?? []) as $z) $zeilen[] = $h($z);
    if (!empty($a["Mail"])) $zeilen[] = mail_verbergen($h($a["Mail"]));
    if (!empty($a["Telefon"])) $zeilen[] = $h($a["Telefon"]);
    return "<p class=\"eingerueckt\">\n" . implode("<br>\n", $zeilen) . "</p>";
}

function mail_verbergen($html): string {
    $at = '<svg role="img" aria-label="at" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 360 360" height="1em" width="1em"><path fill="currentColor" d="m331 162c0 40-17 96-67 96-20 0-38-14-42-34 0 0-11 34-55 34-43 0-61-34-61-72 0-47 29-87 82-87 26 0 52 7 59 9l-5 79s-3 46 24 46 38-41 38-72c0-73-53-110-112-110-95 0-136 67-136 141 0 75 41 118 117 118 34 0 68-11 83-17v26c-33 15-67 16-83 16-84 0-144-45-144-142 0-94 60-168 163-168 80 0 139 51 139 139zm-198 23c0 25 9 49 38 49s42-37 45-107c-9-2-19-3-28-3-49 0-55 49-55 62"/></svg>';
    // No href: no jump to the page top without JS. common/mail.js sets the mailto link only on hover,
    // focus or click, from the reversed, base64-encoded address in data-m.
    $teile = preg_split('/(<[^>]*>)/', (string)$html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $in_link = 0;
    foreach ($teile as $i => $t) {
        if ($i % 2) { if (preg_match('#^<a[\s>]#i', $t)) $in_link++; elseif (preg_match('#^</a\s*>#i', $t)) $in_link = max(0, $in_link - 1); continue; }
        if ($in_link) continue;
        $teile[$i] = preg_replace_callback('/([A-Za-z0-9._%+-]+)@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', fn($m) =>
            '<a class="mail" tabindex="0" data-m="'.base64_encode(strrev($m[0])).'">'.$m[1].$at.$m[2].'</a>', $t);
    }
    return implode("", $teile);
}


// Only logo.svg, which switches itself (scripts/logo-light-dark.py); the deploy catches legacy formats (logo_pruefen).
// Confirmation as an overlay at the top that fades out by itself (style.php .bestaetigung), not above the heading.
function bestaetigung(string $html): string {
    return "<div class='bestaetigung' role='status'>$html</div>";
}

// Back and forward as a pair (style.php .knopfpaar, js.php): back outlined on the left, forward on the right, stacked
// when a label does not fit. Forward comes first in the DOM, so Enter in a form triggers it.
function knopfpaar(string $weiter, string $zurueck): string {
    return "<div class='partner-button-container knopfpaar'>$weiter$zurueck</div>";
}

// Back button inside a form: leaves by GET to $ziel ("" = this page) without checking the fields.
function zurueck_in_formular(string $text, string $ziel, string $extra = ""): string {
    return "<button class='zweitrangig zurueck' type='submit' formmethod='get' formaction='$ziel' formnovalidate$extra>" . symbol("zurueck") . t($text) . "</button>";
}

// Code in groups of four in the token box: one line, the font shrinks with the width down to 1rem (style.php); below
// that it breaks once, between the two halves. Callers pass trusted codes (token, TOTP secret).
function code_kasten(string $code, string $trenner): string {
    $g = str_split($code, 4); $h = (int)ceil(count($g) / 2);
    $breite = round(strlen(implode($trenner, $g)) * 0.652 + 0.3, 1);   // em at 0.05em letter-spacing (measured), plus room for rounding
    return "<div class='token-rahmen'><div class='token-container' style='--breite:$breite'><span>" . implode($trenner, array_slice($g, 0, $h))
        . "</span>$trenner<wbr><span>" . implode($trenner, array_slice($g, $h)) . "</span></div></div>";
}

function get_logo(): string {
    return file_exists(PORTAL_DIR."/logo.svg") ? "/logo.svg" : "";
}


/** Active lines of passwd: [lowercase address => [address, hash, role, notification]]. Read once per request;
 *  passwd_aendern() (auth.php) drops the cache after writing. */
function passwd_eintraege(bool $neu_lesen = false): array {
    static $e = null;
    if ($e !== null && !$neu_lesen) return $e;
    return $e = passwd_lesen(KUNDE_DIR . "/passwd", fn($t) => error_log("$t – " . basename(KUNDE_DIR)));
}

function rolle(string $adresse): ?string {
    return passwd_eintraege()[strtolower($adresse)][2] ?? null;
}

function is_current_user_authorized_for_case($case_id): bool {
    $rolle = rolle(get_auth_user());
    return $rolle !== null && is_role_authorized_for_case($rolle, $case_id);
}


function is_role_authorized_for_case($user_role, $case_id): bool {
    if ($user_role == "Team" || $user_role == "DEV") return true;
    // Role is neither Team nor DEV, so it is a list [case_id],[case_id],...
    $user_cases = explode(",", $user_role);
    for ($i = 0; $i < count($user_cases); ++$i) {
        $user_cases[$i] = clean_token($user_cases[$i]);
    }
    return in_array($case_id, $user_cases);
}


/** $thema: only for a new report, not stored with it (would stay readable next to an encrypted case). */
function inform_stakeholders($case_data, string $thema = ""): void {
    $case_id = $case_data["id"];
    foreach (passwd_eintraege() as $user_info) {
        $user_role = $user_info[2];
        if (!is_role_authorized_for_case($user_role, $case_id)) continue;

        $user_notify = $user_info[3];
        $is_new_case = count($case_data["conversation"]) == 1;
        if ($user_notify == "keine") continue;
        if ($user_notify == "Fälle" && !$is_new_case) continue;


        $user_address = $user_info[0];
        $domain = setting("Domain");   // for the login link
        $chat_msg = end($case_data["conversation"]);
        $new_state = $chat_msg["status"] ?? "";
        $chat_person = $chat_msg["person"];
        $chat_person_name = get_name_of_person($chat_person, false);

        if ($is_new_case) {
            $mail_title = "$case_id Neue Meldung";
            $mail_message = "Es gibt eine neue Meldung" . ($thema !== "" ? " zum Thema $thema" : "") . ". Bitte <a href=\"https://$domain/team\">melden Sie sich an</a>.";
        }
        elseif ($new_state) {
            $mail_title = "$case_id Statusänderung";
            $mail_message = "$chat_person_name hat den Status geändert. Neuer Status: " . status_text($new_state, "de") . ".";
        }
        elseif ($chat_msg["person"] == "meldende_person" && ($chat_msg["action"] ?? "") == "invalidate_token") {
            $mail_title = "$case_id Zugangscode ungültig";
            $mail_message = "Die meldende Person hat ihren Zugangscode ungültig gemacht. Sie kann die Meldung nicht mehr lesen und nichts mehr schreiben.";
        }
        else {
            $chat_source = $chat_person == "meldende_person" ? "der meldenden Person" : $chat_person_name;
            $mail_title = "$case_id Neue Nachricht $chat_source";
            $mail_message = "Es gibt eine neue Nachricht von $chat_source. Bitte <a href=\"https://$domain/team\">melden Sie sich an</a>.";
        }

        mail_text($user_address, $mail_title, $mail_message);
    }
}


/** HTML mail from no-reply@<domain>, subject prefixed with the domain. */
function mail_text(string $an, string $betreff, string $html): void {
    $domain = setting("Domain");
    send_mail($an, mb_encode_mimeheader("$domain $betreff", "UTF-8", "Q"), "<html><body>$html</body></html>", "From: no-reply@$domain\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8");
}


function send_mail($to, $subject, $message, $additional_headers): bool {
    if (!setting("Debug")) {
        // Envelope sender = From, else SPF/DKIM alignment fails under strict DMARC. The regex admits a single
        // address only, so no further sendmail parameter can be smuggled in.
        $envelope = "";
        if (preg_match('/^From:\s*(?:.*<)?([^<>\s]+@[^<>\s]+)>?/mi', $additional_headers, $m)) {
            $envelope = "-f" . $m[1];
        }
        if (mail($to, $subject, $message, $additional_headers, $envelope)) return true;
        // Never the content: the subject names at most the portal and the kind of mail
        error_log("Mail an <$to> nicht versandt: $subject");
        $datei = KUNDE_DIR . "/" . MAILFEHLER;
        @file_put_contents($datei, (is_file($datei) ? "" : MAILFEHLER_KOPF) . date("Y-m-d H:i:s") . "\t$to\t" . mb_decode_mimeheader($subject) . "\n", FILE_APPEND | LOCK_EX);
        return false;
    }
    error_log("Sende mail an <$to> mit headers: <$additional_headers> | $subject | $message");
    return true;
}
