<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit;
// Customer data for the texts in common/text/. All fields in settings.json, "<Feld>_en" for English.
//   Bezug       – HTML replacing "der <Firma mit Link>" in the first sentence (e.g. a group of companies)
//   Team        – names of the core team
//   Anschrift   – HTML block below the company name (address, info address, phone)
//   DS-Mail     – address for data protection questions
//   Titelzusatz – text after the main heading of the start page
//   Zusatz      – HTML at the end of the start page (e.g. demo notice)
//   Farbton     – 0–360, brand hue; tints dark mode (style.php), default 220
$firma = '<span class="name">'.htmlspecialchars(setting("Firma")).'</span>';  // proper name: no hyphenation (style.php)
if (setting("Webseite")) $firma = '<a href="'.setting_l("Webseite").'" target="extern" rel="noopener noreferrer">'.$firma.'</a>';
$bezug = setting_l("Bezug");
// Titelzusatz: red after the main heading of the start page, e.g. „Achtung: Vorführ-Seite!“
$titelzusatz = ($z = setting_l("Titelzusatz")) ? ' <span style="color:var(--farbe-titelzusatz, var(--farbe-fehler, #b3261e));">– '.$z.'</span>' : "";
// The BfJ has no English page on its reporting office (checked 02.11.2026).
$bfj = "https://www.bundesjustizamt.de/DE/MeldestelledesBundes/MeldestelledesBundes_node.html";
