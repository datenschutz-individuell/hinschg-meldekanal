<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Deploy pre-check, input on STDIN. Usage: php check-settings.php <portal/common/deadlines.php> <mode>
//   portal [<name>] | operator  < settings.json     prints what is missing or invalid, nothing if all is fine
//   env                < environment file  prints HINWEISE_WURZEL as the deadline watcher reads it
require $argv[1];   // the same rules as portal pages and deadline watcher

if ($argv[2] === "env") { echo umgebungsdatei_lesen(stream_get_contents(STDIN))["HINWEISE_WURZEL"] ?? ""; exit; }

$s = json_decode(stream_get_contents(STDIN), true);
if (!is_array($s)) { echo "lesbare Datei mit gültigem JSON"; exit; }

if ($argv[2] === "operator") {
    // Operator settings for all portals; a portal's sender domain is <ordner>.<Domain-Endung>
    $endung = $s["Domain-Endung"] ?? null;
    if (!is_string($endung) || !absenderdomain_gueltig("x.$endung")) echo "Domain-Endung ";
    if (!is_string($s["Admin-Mail"] ?? null) || !filter_var($s["Admin-Mail"], FILTER_VALIDATE_EMAIL)) echo "Admin-Mail ";
    if (!is_string($s["Aufsicht"]["de"]["Name"] ?? null) || $s["Aufsicht"]["de"]["Name"] === "") echo "Aufsicht (de: Name) ";
    if (($s["Domain"] ?? "") !== "") echo "kein \"Domain\" (gälte für alle Portale) ";
} else {
    // Mandatory details for start page and privacy notice (Art. 13 GDPR); an own "Domain" must work as sender domain
    foreach (["Firma", "Team", "Anschrift", "DS-Mail"] as $f) if (empty($s[$f])) echo "$f ";
    $domain = $s["Domain"] ?? "";
    if (!is_string($domain) || ($domain !== "" && !absenderdomain_gueltig($domain))) echo "gültige Domain (ohne https://, Umlaute als Punycode) ";
    // Other languages show only Persoenlich_<lang>, else the generic sentence: warn, do not block the deploy
    $sprachen = array_merge((array)($s["Sprachen"] ?? []), (array)($s["Sprachen_in_Pruefung"] ?? []));
    if (!empty($s["Persoenlich"])) foreach (array_unique(array_filter($sprachen, fn($l) => is_string($l) && $l !== "de")) as $l)
        if (empty($s["Persoenlich_$l"])) fwrite(STDERR, "WARNUNG: " . (isset($argv[3]) ? "$argv[3]: " : "") . "Persoenlich_$l fehlt, /$l/ zeigt den Standardsatz\n");
}
// Both levels: a typo in "Anmeldung" would silently fall back to basic auth, which trusts any Authorization header
// without nginx auth_basic; one in "Thema_in_Mail" would put the topic in mails the customer meant to keep without
if (!in_array($s["Anmeldung"] ?? "", ["", "portal"], true)) echo "Anmeldung (leer oder \"portal\") ";
// Old key and "1" (texts say "Monate") would silently fall back to the generic backup sentence or read wrong
if (isset($s["Sicherung"])) echo "kein \"Sicherung\" (heißt jetzt Sicherung_Monate) ";
if (isset($s["Sicherung_Monate"]) && (!is_int($s["Sicherung_Monate"]) || $s["Sicherung_Monate"] < 0 || $s["Sicherung_Monate"] === 1)) echo "Sicherung_Monate (0 oder ganze Zahl ab 2) ";
if (!in_array($s["Thema_in_Mail"] ?? "", ["", "ja", "nein"], true)) echo "Thema_in_Mail (leer, \"ja\" oder \"nein\") ";
