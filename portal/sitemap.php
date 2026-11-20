<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Served as /sitemap.xml (rewrite in hinweise-language.conf; /xx/sitemap.xml redirects there). Only pages open to everyone:
// no login, no access code, no form that creates a report.
include_once(__DIR__."/common/helper.php");
header("Content-Type: application/xml; charset=utf-8");
$basis = "https://" . setting("Domain");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (["/", "/datenschutz.php"] as $pfad) echo "  <url><loc>" . htmlspecialchars($basis . $pfad) . "</loc></url>\n";
echo "</urlset>\n";
