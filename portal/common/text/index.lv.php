<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Iekšējā&nbsp;ziņošanas struktūrvienība<?=$titelzusatz?></h1>
    <p>Šeit Jūs varat ziņot par likumu pārkāpumiem. Runa ir par pārkāpumiem, ko izdarījuši uzņēmuma <?=$bezug ?: $firma?> darbinieki vai kas izdarīti uzņēmuma vārdā. Ziņošanas struktūrvienība pārbauda Jūsu ziņojumu. Ja vajadzīgs, tā sazinās ar Jums. Šis portāls nav paredzēts strīdiem starp darbiniekiem vai klientu sūdzībām.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Ziniet savas tiesības un pienākumus</h2>
    <p>Arī tam, kas ziņo, ir pienākumi. Piemēram, rakstiet tikai to, kas mums vajadzīgs pārbaudei. Vācijas Trauksmes cēlēju aizsardzības likumā (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, tulkojums angļu valodā</a>) ir rakstīts, par kādiem pārkāpumiem Jūs varat šeit ziņot. Tur ir rakstīts arī, kā tiek aizsargāti Jūs un minētās personas. <?=t("start_anonym")?></p>

    <h2>2. Iekšēji vai ārēji?</h2>
    <p>Jūsu ziņojumu izskata iekšējās ziņošanas struktūrvienības komanda. Pašlaik tie ir <?=setting("Team_lv") ?: str_replace(["Herr ", "Frau ", " und "], ["", "", " un "], setting("Team"))?>. Baidāties no nelabvēlīgām sekām, ja ziņosiet šeit? Tad, lūdzu, izmantojiet ārēju ziņošanas kanālu, piemēram, <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">federālo ārējo ziņošanas kanālu pie Federālā tieslietu biroja (tīmekļvietne vācu valodā)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Ziņot <span class="font-normal">vai</span> lasīt un papildināt</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Jauns ziņojums</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Atvērt manu ziņojumu</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
