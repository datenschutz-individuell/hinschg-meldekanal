<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Vidaus&nbsp;pranešimų&nbsp;padalinys<?=$titelzusatz?></h1>
    <p>Čia galite pranešti apie įstatymų pažeidimus. Kalbama apie pažeidimus, kuriuos padarė bendrovės <?=$bezug ?: $firma?> darbuotojai arba kurie padaryti jos vardu. Pranešimų padalinys patikrina Jūsų pranešimą. Jei reikia, jis su Jumis susisiekia. Šis portalas neskirtas darbuotojų ginčams ar klientų skundams.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Žinokite savo teises ir pareigas</h2>
    <p>Pranešantis asmuo taip pat turi pareigų. Pavyzdžiui, rašykite tik tai, ko mums reikia patikrinimui. Vokietijos pranešėjų apsaugos įstatyme (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, vertimas į anglų kalbą</a>) nurodyta, apie kokius pažeidimus galite čia pranešti. Ten taip pat nurodyta, kaip saugomi Jūs ir minimi asmenys. <?=t("start_anonym")?></p>

    <h2>2. Vidaus ar išorės kanalas?</h2>
    <p>Jūsų pranešimą nagrinėja vidaus pranešimų padalinio komanda. Šiuo metu tai <?=setting("Team_lt") ?: str_replace(["Herr ", "Frau ", " und "], ["", "", " ir "], setting("Team"))?>. Bijote neigiamų pasekmių, jei pranešite čia? Tada naudokitės išorės pranešimų teikimo kanalu, pavyzdžiui, <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">federaliniu išorės pranešimų teikimo kanalu prie Federalinės teisingumo tarnybos (svetainė vokiečių kalba)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Pranešti <span class="font-normal">arba</span> skaityti ir papildyti</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Naujas pranešimas</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Atidaryti mano pranešimą</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
