<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Obavijest o zaštiti podataka</h1>

<p>Ovdje možete prijaviti povrede zakona. Riječ je o povredama koje su počinili zaposlenici ili osobe koje djeluju u ime društva <?=$firma?>. Služba za prijave razmotrit će Vašu prijavu. Ako je potrebno, zatražit će mišljenje drugih stručnjaka iz društva ili izvan njega. Ili će povredu proslijediti nadležnom tijelu.</p>

<p>Portal je kanal za prijavljivanje u smislu njemačkog Zakona o zaštiti zviždača (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG</a>, prijevod na engleski). Ovdje objašnjavamo kako postupamo s Vašim osobnim podacima. To su podaci koje unesete u portal i podaci koji nastanu zbog Vaše prijave.</p>

<p>Te podatke obrađujemo jer nas na to obvezuje HinSchG: moramo voditi službu za prijave i obrađivati prijave. Posebno zaštićene podatke, primjerice o zdravlju, obrađujemo samo ako je to za to potrebno. Podatke smijemo dalje dati samo ako to taj zakon dopušta.</p>

<h2>Što se događa s Vašim podacima</h2>

<details class="dse"><summary><h3>Posjet portalu</h3></summary>
<p><?=operation_sentence()?> Mrežni poslužitelj ne pohranjuje IP adrese. Njegov zapisnik služi samo za traženje pogrešaka. Brišemo ga najkasnije nakon 2 mjeseca. <?=privacy_text("statistik")?></p>
<p>Portal ne upotrebljava alate za praćenje ni usluge trećih strana. Tko prijavljuje, ne dobiva kolačiće.<?php if (portal_anmeldung_aktiv()) { ?> Samo kada se tim prijavi, portal postavlja dva tehnički nužna kolačića za prijavu. Prestaju vrijediti kada se odjavite ili zatvorite preglednik.<?php } ?> Vaše unose upotrebljavamo samo za obradu Vaše prijave prema HinSchG-u.</p>
</details>

<details class="dse"><summary><h3>Prijava i pristupni kôd</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Ovaj portal prima samo prijave s imenom i podacima za kontakt, primjerice adresom e-pošte ili brojem telefona. Tako Vas možemo kontaktirati ako imamo pitanja. Sami ste odgovorni za to da su podaci točni. Bez imena možete prijaviti putem vanjskog kanala za prijavljivanje (vidjeti u nastavku).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Na ovom portalu prijavljujete anonimno. Nema polja za ime i podatke za kontakt. Navedete li ih ipak, primjerice u poruci? Onda ih upotrebljavamo samo za razjašnjavanje pitanja. Inače Vas možemo kontaktirati samo putem portala.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Ime i podaci za kontakt su dobrovoljni, primjerice adresa e-pošte ili broj telefona. S tim podacima možemo Vas izravno kontaktirati ako imamo pitanja. Sami ste odgovorni za to da su točni. Bez njih Vas možemo kontaktirati samo putem portala.</p>
<?php
}
?>
<p>Nakon slanja dobit ćete pristupni kôd. Vidjet ćete ga samo jednom. S njim u bilo kojem trenutku možete pročitati svoju prijavu, zajedno s našim pitanjima i odgovorima. Dok prijava nije zaključena, možete pisati timu. Pristupni kôd možete u bilo kojem trenutku poništiti. Izgubite li ga, a niste naveli podatke za kontakt? Onda Vam ne možemo odgovoriti.</p>
<p>Bojite li se da bi Vam prijava kod nas mogla naštetiti? Onda se možete obratiti i vanjskom kanalu za prijavljivanje, ondje i bez imena: <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">saveznom vanjskom kanalu za prijavljivanje pri Saveznom uredu za pravosuđe (mrežna stranica na njemačkom)</a>, za financijske usluge <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">službi za prijave BaFin-a (mrežna stranica na njemačkom)</a>, za kartelne povrede <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Saveznom uredu za kartele (mrežna stranica na njemačkom)</a>, za povrede zaštite podataka <a href="#aufsicht">nadzornom tijelu (vidjeti u nastavku)</a>.</p>
</details>

<details class="dse"><summary><h3>Obrada</h3></summary>
<p>Vašu prijavu isprva čita samo tim službe za unutarnje prijavljivanje. Kod novih prijava i poruka portal timu šalje e-poruku. U njoj su samo ID prijave<?=setting("Thema_in_Mail") !== "nein" ? " i tema" : ""?>, ali ne i sadržaj.</p>
<p><?=privacy_text("speicher")?> Tim ih upotrebljava za obradu prijave. Druge osobne podatke obrađuje samo ako je to za to potrebno.</p>
<p>Pri razjašnjavanju tim može pohraniti i dokumentaciju, primjerice dokumente, fotografije ili e-poruke. Neke nam šaljete Vi, druge tim pronađe sam. Ta dokumentacija nije u portalu, nego u zaštićenoj pohrani službe za prijave. Za nju vrijedi isto: pristup ima samo tim, a brišemo je zajedno s prijavom.</p>
</details>

<details class="dse"><summary><h3>Otkrivanje podataka</h3></summary>
<p>Podatke o Vama dajemo dalje samo</p>
<ul>
<li>ako je to potrebno za obradu,</li>
<li>ako ste za to dali izričitu privolu ili</li>
<li>ako ste namjerno ili iz krajnje nepažnje prijavili nešto netočno.</li>
</ul>
<p>Prije toga ćemo Vas obavijestiti, osim ako bi to ugrozilo istragu ili sudski postupak. Osobe na koje se Vaša prijava odnosi ne saznaju tko je prijavio. Vaša zaštita ima prednost pred njihovim pravom na informacije i pristup.</p>
<p>Jeste li navedeni u nekoj prijavi? Onda ćemo Vas obavijestiti o Vašim podacima čim to više ne ugrožava razjašnjavanje.</p>
</details>

<details class="dse"><summary><h3>Razdoblje pohrane</h3></summary>
<p>Dok prijavu moramo čuvati, tim može vidjeti sve podatke o njoj. S pristupnim kôdom vidite ih i Vi. Tim briše prijavu 3 godine nakon zaključenja. Kasnije samo ako to zahtijeva zakon. <?=privacy_text("sicherung")?></p>
</details>

<h2>Vaša prava i kontakt</h2>

<p>Imate ova prava: pristup svojim podacima, ispravak, brisanje, ograničenje obrade i pritužbu nadzornom tijelu. Ta prava mogu biti ograničena. To vrijedi ako je potrebno radi zaštite prijavitelja i osoba na koje se prijava odnosi ili kako se ne bi ugrozilo razjašnjavanje. Podatke možemo izbrisati tek nakon isteka zakonskog roka čuvanja.</p>

<p>Voditelj obrade Vaših osobnih podataka jest:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Je li nešto nejasno ili imate pitanja o svojim podacima? Onda nam pišite: <?=mail_verbergen(setting("DS-Mail"))?>. Te e-poruke stižu našem timu za zaštitu podataka. Njemu pripada i službenik za zaštitu podataka. Brzo ćemo Vam se javiti.</p>

<p id="aufsicht">Pritužbu možete podnijeti i izravno <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">nadzornom tijelu</a> (mrežna stranica na njemačkom). Za nas je nadležan</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Neslužbeni prijevod – mjerodavna je <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">njemačka verzija</a>.</p>
