<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Služba&nbsp;za&nbsp;unutarnje&nbsp;prijavljivanje<?=$titelzusatz?></h1>
    <p>Ovdje možete prijaviti povrede zakona. Riječ je o povredama koje su počinili zaposlenici ili osobe koje djeluju u ime društva <?=$bezug ?: $firma?>. Služba za prijave razmotrit će Vašu prijavu. Ako je potrebno, javit će Vam se. Ovaj portal nije namijenjen sporovima među zaposlenicima ni prigovorima.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Upoznajte se sa svojim pravima i obvezama</h2>
    <p>I onaj tko prijavljuje ima obveze. Primjerice, napišite samo ono što nam je potrebno za provjeru. U njemačkom Zakonu o zaštiti zviždača (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, prijevod na engleski</a>) piše koje povrede ovdje možete prijaviti. Ondje piše i kako ste zaštićeni Vi i osobe koje navedete. <?=t("start_anonym")?></p>

    <h2>2. Unutarnje ili vanjsko prijavljivanje?</h2>
    <p>Vašu prijavu obrađuje tim službe za unutarnje prijavljivanje. Trenutačno su to: <?=setting("Team_hr") ?: str_replace(["Herr ", "Frau ", " und "], ["g. ", "gđa ", " i "], setting("Team"))?>. Bojite li se da bi Vam prijava ovdje mogla naštetiti? Onda upotrijebite vanjski kanal za prijavljivanje, primjerice <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">savezni vanjski kanal za prijavljivanje pri Saveznom uredu za pravosuđe (mrežna stranica na njemačkom)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Prijavite <span class="font-normal">ili</span> pročitajte i dopunite</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Nova prijava</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Otvori moju prijavu</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
