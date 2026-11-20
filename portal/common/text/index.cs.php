<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Interní&nbsp;kanál&nbsp;pro&nbsp;oznamování<?=$titelzusatz?></h1>
    <p>Zde můžete oznámit porušení zákonů. Jde o porušení ze strany zaměstnanců organizace <?=$bezug ?: $firma?> nebo osob jednajících jejím jménem. Tým, který oznámení vyřizuje, vaše oznámení prověří. Pokud je to nutné, ozve se vám. Pro spory mezi zaměstnanci nebo pro reklamace tento portál není určen.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Seznamte se se svými právy a povinnostmi</h2>
    <p>I ten, kdo oznamuje, má povinnosti. Pište například jen to, co potřebujeme k prověření. V německém zákoně o ochraně oznamovatelů (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, anglický překlad</a>) najdete, jaká porušení zde můžete oznámit. Najdete tam také, jak jste chráněni vy a osoby, které uvedete. <?=t("start_anonym")?></p>

    <h2>2. Interně, nebo externě?</h2>
    <p>Vaše oznámení vyřizuje tým interního kanálu pro oznamování. Jsou to v současnosti <?=setting("Team_cs") ?: str_replace(["Herr ", "Frau ", " und "], ["pan ", "paní ", " a "], setting("Team"))?>. Obáváte se, že by vám oznámení zde mohlo uškodit? Pak využijte prosím externí kanál pro oznamování, například <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">spolkový externí kanál pro oznamování při Spolkovém úřadu pro spravedlnost (web v němčině)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Oznámit <span class="font-normal">nebo</span> číst a doplnit</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Nové oznámení</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Otevřít moje oznámení</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
