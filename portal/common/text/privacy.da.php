<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Privatlivspolitik</h1>

<p>Her kan du indberette lovovertrædelser. Det gælder overtrædelser begået af medarbejdere i <?=$firma?> eller på virksomhedens vegne. Whistleblowerenheden gennemgår din indberetning. Hvis det er nødvendigt, spørger den andre fagfolk indefra eller udefra. Eller den sender overtrædelsen videre til den kompetente myndighed.</p>

<p>Portalen er en indberetningskanal efter den tyske <a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">whistleblowerlov</a> (HinSchG, engelsk oversættelse). Her forklarer vi, hvordan vi behandler dine personoplysninger. Det drejer sig om oplysninger, du indtaster i portalen, og oplysninger, der opstår gennem din indberetning.</p>

<p>Vi behandler disse oplysninger, fordi HinSchG forpligter os til det: Vi skal drive en whistleblowerenhed og behandle indberetninger. Særligt beskyttede oplysninger, for eksempel om helbred, behandler vi kun, hvis det er nødvendigt for det. Vi må kun videregive oplysninger, hvis denne lov tillader det.</p>

<h2>Hvad der sker med dine oplysninger</h2>

<details class="dse"><summary><h3>Besøg på portalen</h3></summary>
<p><?=operation_sentence()?> Webserveren gemmer ingen IP-adresser. Dens log bruges kun til fejlfinding. Vi sletter den senest efter 2 måneder. <?=privacy_text("statistik")?></p>
<p>Portalen bruger ingen trackere og ingen tjenester fra tredjeparter. Den, der indberetter, får ingen cookies.<?php if (portal_anmeldung_aktiv()) { ?> Kun når teamet logger ind, sætter portalen to teknisk nødvendige cookies til login. De ophører, når du logger ud eller lukker browseren.<?php } ?> Dine indtastninger bruger vi kun til at behandle din indberetning efter HinSchG.</p>
</details>

<details class="dse"><summary><h3>Indberetning og token</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Denne portal modtager kun indberetninger med navn og kontaktoplysninger, for eksempel e-mailadresse eller telefonnummer. Så kan vi kontakte dig, hvis vi har spørgsmål. Du er selv ansvarlig for, at oplysningerne er korrekte. Uden navn kan du indberette til en ekstern whistleblowerordning (se nedenfor).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>I denne portal indberetter du anonymt. Der er ingen felter til navn og kontaktoplysninger. Oplyser du dem alligevel, for eksempel i en besked? Så bruger vi dem kun til at afklare spørgsmål. Ellers kan vi kun kontakte dig via portalen.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Navn og kontaktoplysninger er frivillige, for eksempel e-mailadresse eller telefonnummer. Med disse oplysninger kan vi kontakte dig direkte, hvis vi har spørgsmål. Du er selv ansvarlig for, at de er korrekte. Uden dem kan vi kun kontakte dig via portalen.</p>
<?php
}
?>
<p>Når du har sendt indberetningen, får du en token, en kode til din indberetning. Du ser den kun én gang. Med den kan du til enhver tid læse din indberetning med vores spørgsmål og svar. Så længe sagen ikke er afsluttet, kan du skrive til teamet. Du kan til enhver tid gøre din token ugyldig. Har du mistet den og ikke angivet kontaktoplysninger? Så kan vi ikke svare dig.</p>
<p>Frygter du negative følger, hvis du indberetter til os? Så kan du også henvende dig til en ekstern whistleblowerordning, også uden navn: til <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">den tyske forbundsstats eksterne whistleblowerordning hos Bundesamt für Justiz (hjemmeside på tysk)</a>, ved finansielle tjenesteydelser til <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">BaFins whistleblowerordning (hjemmeside på tysk)</a>, ved kartelovertrædelser til <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Bundeskartellamt (hjemmeside på tysk)</a>, ved overtrædelser af databeskyttelsesreglerne til <a href="#aufsicht">tilsynsmyndigheden (se nedenfor)</a>.</p>
</details>

<details class="dse"><summary><h3>Behandling</h3></summary>
<p>Først læser kun teamet i den interne whistleblowerenhed din indberetning. Ved nye indberetninger og beskeder sender portalen teamet en e-mail. Den indeholder kun indberetnings-id'et<?=setting("Thema_in_Mail") !== "nein" ? " og emnet" : ""?>, ikke indholdet.</p>
<p><?=privacy_text("speicher")?> Teamet bruger oplysningerne til at behandle indberetningen. Andre personoplysninger behandler det kun, hvis det er nødvendigt for det.</p>
<p>Under undersøgelsen kan teamet også gemme materiale, for eksempel dokumenter, fotos eller e-mails. Noget sender du til os, andet finder teamet selv. Dette materiale ligger ikke i portalen, men i et beskyttet arkiv hos whistleblowerenheden. Det samme gælder for det: Kun teamet har adgang, og vi sletter det sammen med indberetningen.</p>
</details>

<details class="dse"><summary><h3>Videregivelse</h3></summary>
<p>Vi videregiver kun oplysninger om dig,</p>
<ul>
<li>hvis det er nødvendigt for behandlingen,</li>
<li>hvis du udtrykkeligt har givet samtykke, eller</li>
<li>hvis du med vilje eller groft uagtsomt har indberettet noget forkert.</li>
</ul>
<p>Vi giver dig besked på forhånd, medmindre det ville bringe en efterforskning eller en retssag i fare. Personer, som din indberetning handler om, får ikke at vide, hvem der har indberettet. Din beskyttelse går forud for deres ret til information og indsigt.</p>
<p>Er du nævnt i en indberetning? Så informerer vi dig om dine oplysninger, så snart det ikke længere bringer undersøgelsen i fare.</p>
</details>

<details class="dse"><summary><h3>Opbevaringsperiode</h3></summary>
<p>Så længe vi skal opbevare en indberetning, kan teamet se alle oplysninger om den. Med din token kan du også se dem. Teamet sletter indberetningen 3 år efter afslutningen. Længere kun, hvis en lov kræver det. <?=privacy_text("sicherung")?></p>
</details>

<h2>Dine rettigheder og kontakt</h2>

<p>Du har disse rettigheder: indsigt i dine oplysninger, berigtigelse, sletning, begrænsning af behandlingen og klage til en tilsynsmyndighed. Disse rettigheder kan være begrænset. Det gælder, hvis det er nødvendigt for at beskytte indberettende og berørte personer eller for ikke at bringe undersøgelsen i fare. Vi kan først slette, når den lovbestemte opbevaringsfrist er udløbet.</p>

<p>Dataansvarlig for behandlingen af dine personoplysninger er:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Er noget uklart, eller har du spørgsmål om dine oplysninger? Så skriv til os: <?=mail_verbergen(setting("DS-Mail"))?>. Disse e-mails går til vores databeskyttelsesteam. Vores databeskyttelsesrådgiver hører også til det. Vi vender hurtigt tilbage til dig.</p>

<p id="aufsicht">Du har også ret til at indgive en klage direkte til <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">en tilsynsmyndighed</a> (hjemmeside på tysk). Den tilsynsmyndighed, der er kompetent for os, er:</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Uofficiel oversættelse – den <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">tyske udgave</a> er gældende.</p>
