<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Language of the pages for reporting persons. No cookie, no parameter:
//  1. path prefix /xx/ (nginx strips it internally, REQUEST_URI keeps it) – for linking and switching
//  2. else the browser language (Accept-Language) among the portal's languages
//  3. else German.
// A language = lang/xx.php + text/index.xx.php + text/privacy.xx.php; which ones a portal offers
// is set in settings.json "Sprachen". Languages in "Sprachen_in_Pruefung" are reachable only via their prefix
// (for review by the customer), not via the browser language nor in the switcher. Stored report texts and mails stay German.

const STANDARDSPRACHE = "de";


function sprachliste($gewuenscht): array {
    return array_values(array_filter(array_unique((array)$gewuenscht),
        fn($l) => is_string($l) && preg_match('/^[a-z]{2}$/', $l) && is_file(__DIR__."/lang/$l.php")));
}


// Approved languages of this portal whose files exist; the default language always first.
function sprachen(): array {
    static $liste;
    if (!isset($liste)) $liste = sprachliste(array_merge([STANDARDSPRACHE], (array)(setting("Sprachen") ?: [])));
    return $liste;
}


// Approved plus languages under review: only these prefixes are recognised.
function sprachen_erreichbar(): array {
    return sprachliste(array_merge(sprachen(), (array)(setting("Sprachen_in_Pruefung") ?: [])));
}


// [language from the path or "", path without prefix]
function pfad_zerlegen(): array {
    static $teile;
    if (!isset($teile)) {
        $pfad = strtok($_SERVER['REQUEST_URI'] ?? "/", "?");
        // nginx strips any two-letter prefix; a language not offered counts as none.
        $teile = preg_match('#^/([a-z]{2})(/.*)?$#', $pfad, $m)
            ? [in_array($m[1], sprachen_erreichbar()) ? $m[1] : "", ($m[2] ?? "") ?: "/"] : ["", $pfad];
    }
    return $teile;
}


function browser_sprache(): string {
    $beste = STANDARDSPRACHE; $q_beste = 0;
    foreach (explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? "") as $i => $teil) {
        if (!preg_match('/^\s*([a-z]{2})(?=[-;\s]|$)(?:-[A-Za-z0-9]+)*\s*(?:;\s*q=([0-9.]+))?/i', $teil, $m)) continue;
        $q = isset($m[2]) ? (float)$m[2] : 1.0;
        $q -= $i / 1000;   // on equal q the first one listed wins
        if ($q > $q_beste && in_array(strtolower($m[1]), sprachen())) { $beste = strtolower($m[1]); $q_beste = $q; }
    }
    return $beste;
}


function lang(): string {
    static $lang;
    if (!isset($lang)) {
        $lang = pfad_zerlegen()[0] ?: browser_sprache();
    }
    return $lang;
}


function texte($l): array {
    static $texte = [];
    return $texte[$l] ??= (array)include(__DIR__."/lang/$l.php");
}


function text_suchen($key): ?string {
    foreach (array_unique([lang(), STANDARDSPRACHE]) as $l) if (isset(texte($l)[$key])) return texte($l)[$key];
    return null;
}


function t($key, ...$args): string {
    $text = text_suchen($key);
    if ($text === null) { error_log("Übersetzung fehlt: $key"); return $key; }
    return $args ? vsprintf($text, $args) : $text;
}


// Translates a stored German value (legal area, status), otherwise leaves it as is.
function t_wert($wert): string {
    return text_suchen("wert:$wert") ?? $wert;
}


// Show status uniformly: stored stays unbearbeitet/offen/geschlossen, shown is
// „Unbearbeitet“, „In Bearbeitung“, „Abgeschlossen“; portal-specific statuses capitalised.
function status_text(?string $status, ?string $sprache = null): string {
    $status ??= "";
    return mb_ucfirst($sprache ? (texte($sprache)["wert:$status"] ?? $status) : t_wert($status));
}


// Customer setting in the page language: "<Name>_xx", else "<Name>".
function setting_l($name): string {
    return (lang() != STANDARDSPRACHE ? setting($name."_".lang()) : "") ?: setting($name);
}


// Setting in the current language only, no German fallback (free text the customer wrote per language).
function setting_nur_l($name): string {
    return (string)setting(lang() == STANDARDSPRACHE ? $name : $name."_".lang());
}

// Text with "|" break points as spans joined by spaces: one sentence for screen readers, lines for js.php stufen().
function zeilen(string $text): string {
    return implode(" ", array_map(fn($z) => "<span class='zeile'>$z</span>", explode("|", $text)));
}

// Link in a given language (always with prefix so the choice beats the browser language).
function lang_url($lang, $path): string {
    return "/$lang".$path;
}


// Internal link: keeps a chosen prefix; without one the browser decides again.
function url($path): string {
    $praefix = pfad_zerlegen()[0];
    return $praefix ? lang_url($praefix, $path) : $path;
}


// Language switcher for the footer: the portal's other languages, each in its own name.
// Dropdown from one other language on, so the footer looks the same everywhere;
// <details> instead of <select>: no JavaScript, keyboard-operable, no switch on selection (WCAG 3.2.2).
function sprachumschalter(): string {
    $pfad = pfad_zerlegen()[1];
    if (is_string($_GET["case_id"] ?? null)) $pfad .= "?case_id=" . rawurlencode(clean_token($_GET["case_id"]));   // stay in the case
    $links = [];
    foreach (sprachen() as $l) {
        if ($l == lang()) continue;
        $name = texte($l)["sprachwahl"] ?? $l;
        $links[] = '<a href="'.htmlspecialchars(lang_url($l, $pfad)).'" hreflang="'.$l.'" lang="'.$l.'">'.htmlspecialchars($name).'</a>';
    }
    if (!$links) return "";
    return '<details class="sprachmenue"><summary><span role="img" aria-label="'.htmlspecialchars(t("sprache")).'" title="'.htmlspecialchars(t("sprache")).'">🌐</span> '
        .htmlspecialchars(t("sprachwahl")).'</summary><ul><li>'.implode("</li><li>", $links).'</li></ul></details>';
}


// <link rel="alternate"> for search engines. header.php sets the Vary header before the first output.
function sprach_kopf(): string {
    if (count(sprachen()) < 2) return "";
    $pfad = pfad_zerlegen()[1]; $html = "";
    foreach (sprachen() as $l) $html .= '    <link rel="alternate" hreflang="'.$l.'" href="'.htmlspecialchars(lang_url($l, $pfad)).'">'."\n";
    return $html;
}

