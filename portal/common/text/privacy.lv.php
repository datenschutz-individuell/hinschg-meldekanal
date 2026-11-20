<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Privātuma paziņojums</h1>

<p>Šeit Jūs varat ziņot par likumu pārkāpumiem. Runa ir par pārkāpumiem, ko izdarījuši uzņēmuma <?=$firma?> darbinieki vai kas izdarīti uzņēmuma vārdā. Ziņošanas struktūrvienība pārbauda Jūsu ziņojumu. Ja vajadzīgs, tā vēršas pie citiem iekšējiem vai ārējiem speciālistiem. Vai arī tā nodod pārkāpumu kompetentajai iestādei.</p>

<p>Šis portāls ir ziņošanas kanāls saskaņā ar Vācijas Trauksmes cēlēju aizsardzības likumu (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, tulkojums angļu valodā</a>). Šeit mēs paskaidrojam, kā rīkojamies ar Jūsu personas datiem. Tie ir dati, ko ievadāt portālā, un dati, kas rodas Jūsu ziņojuma dēļ.</p>

<p>Mēs apstrādājam šos datus, jo to mums uzliek HinSchG: mums ir jāuztur ziņošanas struktūrvienība un jāizskata ziņojumi. Īpaši aizsargājamus datus, piemēram, par veselību, mēs apstrādājam tikai tad, ja tas šim nolūkam ir vajadzīgs. Nodot datus mēs drīkstam tikai tad, ja to atļauj šis likums.</p>

<h2>Kas notiek ar Jūsu datiem</h2>

<details class="dse"><summary><h3>Portāla apmeklēšana</h3></summary>
<p><?=operation_sentence()?> Tīmekļa serveris nesaglabā IP adreses. Tā žurnāls kalpo tikai kļūdu meklēšanai. Mēs to dzēšam ne vēlāk kā pēc 2 mēnešiem. <?=privacy_text("statistik")?></p>
<p>Portālā netiek izmantoti izsekotāji un trešo personu pakalpojumi. Tie, kas ziņo, nesaņem sīkdatnes.<?php if (portal_anmeldung_aktiv()) { ?> Tikai tad, kad piesakās komanda, portāls iestata divas tehniski nepieciešamas sīkdatnes pieteikšanās vajadzībām. Tās beidz darboties, kad izrakstāties vai aizverat pārlūkprogrammu.<?php } ?> Jūsu ievadīto informāciju mēs izmantojam tikai, lai izskatītu Jūsu ziņojumu saskaņā ar HinSchG.</p>
</details>

<details class="dse"><summary><h3>Ziņošana un piekļuves kods</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Šajā portālā tiek pieņemti tikai ziņojumi ar vārdu, uzvārdu un kontaktinformāciju, piemēram, e-pasta adresi vai tālruņa numuru. Tā mēs varam ar Jums sazināties, ja rodas jautājumi. Jūs paši atbildat par to, lai šī informācija būtu pareiza. Bez vārda Jūs varat ziņot ārējā ziņošanas kanālā (skatīt tālāk).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Šajā portālā Jūs ziņojat anonīmi. Lauku vārdam un kontaktinformācijai nav. Tomēr norādāt tos, piemēram, ziņā? Tad mēs tos izmantosim tikai, lai noskaidrotu jautājumus. Citādi mēs varam sazināties ar Jums tikai ar portāla starpniecību.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Vārda, uzvārda un kontaktinformācijas, piemēram, e-pasta adreses vai tālruņa numura, norādīšana ir brīvprātīga. Ar šo informāciju mēs varam sazināties ar Jums tieši, ja rodas jautājumi. Jūs paši atbildat par to, lai tā būtu pareiza. Bez tās mēs varam sazināties ar Jums tikai ar portāla starpniecību.</p>
<?php
}
?>
<p>Pēc iesniegšanas Jūs saņemsiet piekļuves kodu. To redzēsiet tikai vienreiz. Ar to Jūs jebkurā laikā varat lasīt savu ziņojumu kopā ar mūsu jautājumiem un atbildēm. Kamēr lieta nav slēgta, Jūs varat rakstīt komandai. Piekļuves kodu Jūs jebkurā laikā varat anulēt. Kods ir pazaudēts, un kontaktinformācija nav norādīta? Tad mēs Jums nevaram atbildēt.</p>
<p>Baidāties no nelabvēlīgām sekām, ja ziņosiet mums? Tad Jūs varat vērsties arī ārējā ziņošanas kanālā, tur arī bez vārda: <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">federālajā ārējā ziņošanas kanālā pie Federālā tieslietu biroja (tīmekļvietne vācu valodā)</a>, finanšu pakalpojumu jomā – <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">BaFin ziņošanas kanālā (tīmekļvietne vācu valodā)</a>, karteļa pārkāpumu gadījumā – <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Federālajā karteļu birojā (tīmekļvietne vācu valodā)</a>, datu aizsardzības pārkāpumu gadījumā – <a href="#aufsicht">uzraudzības iestādē (skatīt tālāk)</a>.</p>
</details>

<details class="dse"><summary><h3>Izskatīšana</h3></summary>
<p>Sākumā Jūsu ziņojumu lasa tikai iekšējās ziņošanas struktūrvienības komanda. Par jauniem ziņojumiem un ziņām portāls komandai nosūta e-pastu. Tajā ir tikai ziņojuma ID<?=setting("Thema_in_Mail") !== "nein" ? " un tēma" : ""?>, nevis saturs.</p>
<p><?=privacy_text("speicher")?> Komanda šos datus izmanto, lai izskatītu ziņojumu. Citus personas datus tā apstrādā tikai tad, ja tas šim nolūkam ir vajadzīgs.</p>
<p>Noskaidrojot apstākļus, komanda var saglabāt arī materiālus, piemēram, dokumentus, fotoattēlus vai e-pasta vēstules. Dažus no tiem nosūtāt Jūs, citus komanda atrod pati. Šie materiāli neatrodas portālā, bet gan aizsargātā ziņošanas struktūrvienības krātuvē. Uz tiem attiecas tas pats: piekļuve ir tikai komandai, un mēs tos dzēšam kopā ar ziņojumu.</p>
</details>

<details class="dse"><summary><h3>Nodošana</h3></summary>
<p>Informāciju par Jums mēs nododam tikai,</p>
<ul>
<li>ja tas ir vajadzīgs ziņojuma izskatīšanai,</li>
<li>ja Jūs esat nepārprotami piekrituši, vai</li>
<li>ja Jūs tīši vai rupjas neuzmanības dēļ esat ziņojuši nepatiesu informāciju.</li>
</ul>
<p>Pirms tam mēs Jums to paziņojam, ja vien tas neapdraudētu izmeklēšanu vai tiesvedību. Personas, par kurām ir Jūsu ziņojums, neuzzina, kas ir ziņojis. Jūsu aizsardzība ir svarīgāka par viņu tiesībām uz informāciju un piekļuvi.</p>
<p>Jūs esat minēti ziņojumā? Tad mēs Jūs informēsim par Jūsu datiem, tiklīdz tas vairs neapdraudēs lietas apstākļu noskaidrošanu.</p>
</details>

<details class="dse"><summary><h3>Glabāšanas laikposms</h3></summary>
<p>Kamēr ziņojums mums ir jāglabā, komanda var redzēt visu ar to saistīto informāciju. Ar piekļuves kodu to redzat arī Jūs. Komanda dzēš ziņojumu 3 gadus pēc lietas slēgšanas. Ilgāk – tikai tad, ja to prasa likums. <?=privacy_text("sicherung")?></p>
</details>

<h2>Jūsu tiesības un kontakti</h2>

<p>Jums ir šādas tiesības: piekļūt saviem datiem, tos labot, dzēst, ierobežot to apstrādi un iesniegt sūdzību uzraudzības iestādē. Šīs tiesības var būt ierobežotas. Tā ir tad, ja tas vajadzīgs, lai aizsargātu ziņojošās un iesaistītās personas vai neapdraudētu lietas apstākļu noskaidrošanu. Dzēst datus mēs varam tikai pēc likumā noteiktā glabāšanas termiņa beigām.</p>

<p>Pārzinis, kas atbild par Jūsu personas datu apstrādi, ir:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Kaut kas nav skaidrs, vai Jums ir jautājumi par saviem datiem? Tad rakstiet mums: <?=mail_verbergen(setting("DS-Mail"))?>. Šīs e-pasta vēstules saņem mūsu datu aizsardzības komanda. Tajā ietilpst arī datu aizsardzības speciālists. Mēs Jums ātri atbildēsim.</p>

<p id="aufsicht">Jūs varat arī tieši iesniegt sūdzību <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">datu aizsardzības uzraudzības iestādē</a> (tīmekļvietne vācu valodā). Mūs uzraugošā iestāde ir</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Neoficiāls tulkojums – saistošs ir <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">teksts vācu valodā</a>.</p>
