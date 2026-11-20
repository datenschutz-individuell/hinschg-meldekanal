<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Intern&nbsp;meldpunt<?=$titelzusatz?></h1>
    <p>Hier kunt u overtredingen van de wet melden. Het gaat om overtredingen door medewerkers van <?=$bezug ?: $firma?> of namens deze organisatie. Het meldpunt beoordeelt uw melding. Als het nodig is, neemt het contact met u op. Dit portaal is niet bedoeld voor ruzie tussen medewerkers of voor klachten.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Informeer u</h2>
    <p>Ook wie meldt, heeft plichten. Schrijf bijvoorbeeld alleen wat wij nodig hebben om de zaak te beoordelen. In de Duitse wet ter bescherming van klokkenluiders (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, Engelse vertaling</a>) staat welke overtredingen u hier kunt melden. Daar staat ook hoe u en de genoemde personen beschermd zijn. <?=t("start_anonym")?></p>

    <h2>2. Intern of extern?</h2>
    <p>Uw melding wordt behandeld door het team van het interne meldpunt. Dat zijn op dit moment <?=setting("Team_nl") ?: str_replace(["Herr ", "Frau ", " und "], ["de heer ", "mevrouw ", " en "], setting("Team"))?>. Bent u bang voor nadelen als u hier meldt? Gebruik dan een extern meldpunt, bijvoorbeeld het <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">externe meldpunt van de Duitse federale overheid bij het federale Bureau voor Justitie (website in het Duits)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Melden <span class="font-normal">of</span> lezen en aanvullen</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Nieuwe melding</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Mijn melding openen</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
