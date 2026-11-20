<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Branded error page for 400/401/403/404/405 (nginx: hinweise-errors.conf, error_page …?code=…).
// nginx keeps the original status; the language comes from the original URI as everywhere.
include_once(__DIR__."/helper.php");
$code = (int)($_GET["code"] ?? 404);
$art = in_array($code, [401, 403, 404]) ? $code : "allgemein";
if ($code === 401) $logo_ziel = url("/");   // otherwise the logo under /team would lead back into the login dialog
include_once(__DIR__."/header.php");
?>
  <div class="fehlerseite">
    <?=symbol(["404" => "suche", "401" => "schloss", "403" => "schloss"][$art] ?? "warnung")?>
    <h1><?=t("fehler_{$art}_titel")?></h1>
    <p><?=t("fehler_{$art}_text", $code)?></p>
    <div class="partner-button-container">
        <form action="<?=url("/")?>"><button type="submit"><?=symbol("haus")?><?=t("fehler_startseite")?></button></form>
    </div>
  </div>
<?php
include_once(__DIR__."/footer.php");
