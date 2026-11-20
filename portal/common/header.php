<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/../common/helper.php");
// Response depends on Accept-Language (lang.php) – set before any output.
if (count(sprachen()) > 1) header("Vary: Accept-Language", false);
// Pages carry tokens and report histories: keep them out of the browser cache and its "Back" history (shared computers)
header("Cache-Control: no-store");

/* These two should be covered by the combined 16/32px ico
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
 * In case current browser quirks are to be supported …
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#fff">
<meta name="msapplication-TileColor" content="#fff">
<meta name="theme-color" content="#fff">
 */
?>

<!doctype html>
<html lang="<?=t("html_lang")?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?=(lang() == STANDARDSPRACHE ? setting("Title") : setting("Title_".lang())) ?: t("title")?> – <?=setting("Firma")?></title>
<?=sprach_kopf()?>
    <link rel="stylesheet" href="/common/style.php?v=<?= filemtime(__DIR__."/style.php") ?>">
<?php if (setting("Style")) { ?>
    <link rel="stylesheet" href="<?=setting("Style")?>">
<?php } ?>
<?php if (setting("FavIcon")) { ?>
    <link rel="icon" href="<?=setting("FavIcon")?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?=setting("FavIcon")?>" type="image/x-icon">
<?php } ?>
</head>

<body<?=illustriert() ? "" : " class='plain'"?>><div id="main-content">
    <?php
    // JS is loaded in the body because some functions need the body to exist.
    include_once(__DIR__."/js.php");

    if (get_logo()) {
        // Team back to the overview, everyone else to the start page; error pages set $logo_ziel (401 under /team
        // would otherwise lead back into the login dialog).
        $target = $logo_ziel ?? (preg_match('#^/team(/|$)#', rawurldecode(pfad_zerlegen()[1])) ? url("/team") : url("/"));
        ?>

        <header><a href="<?=$target?>"><img class="logo" src="<?=get_logo()?>" alt="<?=htmlspecialchars((string)(setting("Firma") ?: t($target === url("/") ? "startseite" : "team")), ENT_QUOTES)?>"></a></header>
        <br class="neue-zeile">

        <?php
    } ?>
<main>
<?php
    // Without emergency key: warning on every page; only start page and privacy notice stay usable
    if (zweitschluessel_fehlt()) {
        echo warnkasten(t("zweit_fehlt"));
        if (!in_array(pfad_zerlegen()[1], ["/", "/index.php", "/datenschutz.php"], true)) { include __DIR__."/footer.php"; exit; }
    } ?>
<!-- end of header.php -->
