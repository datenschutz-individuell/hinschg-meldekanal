<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Privacyverklaring</h1>

<p>Hier kunt u overtredingen van de wet melden. Het gaat om overtredingen door medewerkers van <?=$firma?> of namens deze organisatie. Het meldpunt beoordeelt uw melding. Als het nodig is, vraagt het andere deskundigen van binnen of buiten de organisatie om advies. Of het geeft de overtreding door aan de bevoegde autoriteit.</p>

<p>Dit portaal is een meldingskanaal in de zin van de Duitse <a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">wet ter bescherming van klokkenluiders</a> (HinSchG, Engelse vertaling). Hier leggen wij uit hoe wij met uw persoonsgegevens omgaan. Het gaat om gegevens die u in het portaal invoert, en om gegevens die door uw melding ontstaan.</p>

<p>Wij verwerken deze gegevens omdat de HinSchG ons daartoe verplicht: wij moeten een meldpunt hebben en meldingen behandelen. Bijzonder beschermde gegevens, bijvoorbeeld over de gezondheid, verwerken wij alleen als dat daarvoor nodig is. Gegevens doorgeven mogen wij alleen als deze wet dat toestaat.</p>

<h2>Wat er met uw gegevens gebeurt</h2>

<details class="dse"><summary><h3>Bezoek aan het portaal</h3></summary>
<p><?=operation_sentence()?> De webserver slaat geen IP-adressen op. Het logboek dient alleen om fouten op te sporen. Wij verwijderen het uiterlijk na 2 maanden. <?=privacy_text("statistik")?></p>
<p>Het portaal gebruikt geen trackers en geen diensten van derden. Wie meldt, krijgt geen cookies.<?php if (portal_anmeldung_aktiv()) { ?> Alleen als het team inlogt, plaatst het portaal twee technisch noodzakelijke cookies voor het inloggen. Ze vervallen bij het uitloggen of als u de browser sluit.<?php } ?> Uw invoer gebruiken wij alleen om uw melding volgens de HinSchG te behandelen.</p>
</details>

<details class="dse"><summary><h3>Melden en toegangscode</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Dit portaal accepteert alleen meldingen met naam en contactgegevens, bijvoorbeeld e-mailadres of telefoonnummer. Zo kunnen wij u bij vragen bereiken. U bent zelf verantwoordelijk dat de gegevens kloppen. Zonder naam melden kunt u bij een extern meldpunt (zie hieronder).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Via dit portaal meldt u anoniem. Er zijn geen velden voor naam en contactgegevens. Noemt u ze toch, bijvoorbeeld in een bericht? Dan gebruiken wij ze alleen om vragen op te helderen. Anders kunnen wij u alleen via het portaal bereiken.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Naam en contactgegevens zijn vrijwillig, bijvoorbeeld e-mailadres of telefoonnummer. Met deze gegevens kunnen wij u bij vragen direct bereiken. U bent zelf verantwoordelijk dat ze kloppen. Zonder deze gegevens kunnen wij u alleen via het portaal bereiken.</p>
<?php
}
?>
<p>Na het indienen krijgt u een toegangscode. U ziet die maar één keer. Daarmee kunt u uw melding altijd lezen, met onze vragen en antwoorden. Zolang de zaak niet is afgesloten, kunt u het team schrijven. U kunt de toegangscode altijd ongeldig maken. Bent u hem kwijt en hebt u geen contactgegevens opgegeven? Dan kunnen wij u niet antwoorden.</p>
<p>Bent u bang voor nadelen als u bij ons meldt? Dan kunt u zich ook tot een extern meldpunt wenden, daar ook zonder naam: tot het <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">externe meldpunt van de Duitse federale overheid bij het federale Bureau voor Justitie (website in het Duits)</a>, bij financiële diensten tot het <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">klokkenluidersmeldpunt van de BaFin (website in het Duits)</a>, bij kartelinbreuken tot het <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Bundeskartellamt (website in het Duits)</a>, bij inbreuken op de gegevensbescherming tot de <a href="#aufsicht">toezichthouder (zie hieronder)</a>.</p>
</details>

<details class="dse"><summary><h3>Behandeling</h3></summary>
<p>Uw melding wordt eerst alleen gelezen door het team van het interne meldpunt. Bij nieuwe meldingen en berichten stuurt het portaal het team een e-mail. Daarin staan alleen het meldingsnummer<?=setting("Thema_in_Mail") !== "nein" ? " en het onderwerp" : ""?>, niet de inhoud.</p>
<p><?=privacy_text("speicher")?> Het team gebruikt ze om de melding te behandelen. Andere persoonsgegevens verwerkt het alleen als dat daarvoor nodig is.</p>
<p>Bij het onderzoek kan het team ook stukken bewaren, bijvoorbeeld documenten, foto's of e-mails. Sommige stuurt u ons, andere vindt het team zelf. Deze stukken staan niet in het portaal, maar in een beveiligde opslag van het meldpunt. Daarvoor geldt hetzelfde: alleen het team heeft toegang, en wij verwijderen ze samen met de melding.</p>
</details>

<details class="dse"><summary><h3>Doorgeven</h3></summary>
<p>Gegevens over u geven wij alleen door,</p>
<ul>
<li>als dat voor de behandeling nodig is,</li>
<li>als u uitdrukkelijk toestemming hebt gegeven, of</li>
<li>als u opzettelijk of met grove nalatigheid iets onjuists hebt gemeld.</li>
</ul>
<p>Wij laten het u vooraf weten, behalve als dat een onderzoek of een gerechtelijke procedure in gevaar zou brengen. Personen om wie het in uw melding gaat, komen niet te weten wie heeft gemeld. Uw bescherming gaat voor op hun recht op informatie en inzage.</p>
<p>Wordt u in een melding genoemd? Dan informeren wij u over uw gegevens zodra dat het onderzoek niet meer in gevaar brengt.</p>
</details>

<details class="dse"><summary><h3>Bewaartermijn</h3></summary>
<p>Zolang wij een melding moeten bewaren, kan het team alle gegevens daarover zien. Met de toegangscode ziet u ze ook. Het team verwijdert de melding 3 jaar na de afsluiting. Langer alleen als een wet dat vereist. <?=privacy_text("sicherung")?></p>
</details>

<h2>Uw rechten en contact</h2>

<p>U hebt deze rechten: inzage in uw gegevens, rectificatie, wissing, beperking van de verwerking en een klacht bij een toezichthoudende autoriteit. Deze rechten kunnen beperkt zijn. Dat geldt als het nodig is om melders en betrokkenen te beschermen of om het onderzoek niet in gevaar te brengen. Wissen kunnen wij pas na afloop van de wettelijke bewaartermijn.</p>

<p>Verwerkingsverantwoordelijke voor uw persoonsgegevens is:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Is iets niet duidelijk, of hebt u vragen over uw gegevens? Schrijf ons dan: <?=mail_verbergen(setting("DS-Mail"))?>. Deze e-mails gaan naar ons privacyteam. Daar hoort ook onze functionaris voor gegevensbescherming bij. Wij reageren snel.</p>

<p id="aufsicht">U kunt ook rechtstreeks een klacht indienen bij <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">een toezichthoudende autoriteit voor gegevensbescherming</a> (website in het Duits). Voor ons is bevoegd:</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Vertaling ter informatie – de <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">Duitse versie</a> is bindend.</p>
