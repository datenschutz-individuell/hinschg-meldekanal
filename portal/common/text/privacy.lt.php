<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Privatumo pranešimas</h1>

<p>Čia galite pranešti apie įstatymų pažeidimus. Kalbama apie pažeidimus, kuriuos padarė bendrovės <?=$firma?> darbuotojai arba kurie padaryti jos vardu. Pranešimų padalinys patikrina Jūsų pranešimą. Jei reikia, jis kreipiasi į kitus vidaus ar išorės specialistus. Arba jis perduoda pažeidimą kompetentingai institucijai.</p>

<p>Šis portalas yra pranešimų teikimo kanalas pagal Vokietijos pranešėjų apsaugos įstatymą (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, vertimas į anglų kalbą</a>). Čia paaiškiname, kaip tvarkome Jūsų asmens duomenis. Tai duomenys, kuriuos įvedate portale, ir duomenys, kurie atsiranda dėl Jūsų pranešimo.</p>

<p>Šiuos duomenis tvarkome, nes to reikalauja HinSchG: privalome turėti pranešimų padalinį ir nagrinėti pranešimus. Ypač saugomus duomenis, pavyzdžiui, apie sveikatą, tvarkome tik tada, kai tai tam būtina. Perduoti duomenis galime tik tada, kai tai leidžia šis įstatymas.</p>

<h2>Kas vyksta su Jūsų duomenimis</h2>

<details class="dse"><summary><h3>Apsilankymas portale</h3></summary>
<p><?=operation_sentence()?> Žiniatinklio serveris nesaugo IP adresų. Jo žurnalas naudojamas tik klaidoms ieškoti. Jį ištriname ne vėliau kaip po 2 mėnesių. <?=privacy_text("statistik")?></p>
<p>Portale nenaudojamos sekimo priemonės ir trečiųjų šalių paslaugos. Pranešantys asmenys negauna slapukų.<?php if (portal_anmeldung_aktiv()) { ?> Tik komandai prisijungiant portalas nustato du techniškai būtinus prisijungimo slapukus. Jie nustoja galioti atsijungus arba uždarius naršyklę.<?php } ?> Jūsų įvestus duomenis naudojame tik Jūsų pranešimui nagrinėti pagal HinSchG.</p>
</details>

<details class="dse"><summary><h3>Pranešimas ir prieigos kodas</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Šis portalas priima tik pranešimus su vardu, pavarde ir kontaktiniais duomenimis, pavyzdžiui, el. pašto adresu arba telefono numeriu. Taip galime su Jumis susisiekti, jei kyla klausimų. Patys atsakote už tai, kad duomenys būtų teisingi. Be vardo pranešti galite išorės pranešimų teikimo kanalu (žr. toliau).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Šiame portale pranešate anonimiškai. Laukų vardui ir kontaktiniams duomenims nėra. Vis dėlto juos nurodote, pavyzdžiui, žinutėje? Tada naudosime juos tik klausimams išsiaiškinti. Kitaip su Jumis galime susisiekti tik per portalą.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Vardą, pavardę ir kontaktinius duomenis, pavyzdžiui, el. pašto adresą arba telefono numerį, nurodyti neprivaloma. Su šiais duomenimis, kilus klausimų, galime su Jumis susisiekti tiesiogiai. Patys atsakote už tai, kad jie būtų teisingi. Be jų su Jumis galime susisiekti tik per portalą.</p>
<?php
}
?>
<p>Pateikę pranešimą, gausite prieigos kodą. Jį matysite tik vieną kartą. Su juo bet kada galite skaityti savo pranešimą kartu su mūsų klausimais ir atsakymais. Kol pranešimas neužbaigtas, galite rašyti komandai. Prieigos kodo galiojimą galite bet kada panaikinti. Praradote kodą ir nenurodėte kontaktinių duomenų? Tada nebegalėsime Jums atsakyti.</p>
<p>Bijote neigiamų pasekmių, jei pranešite mums? Tada galite kreiptis ir į išorės pranešimų teikimo kanalą, ten ir be vardo: į <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">federalinį išorės pranešimų teikimo kanalą prie Federalinės teisingumo tarnybos (svetainė vokiečių kalba)</a>, dėl finansinių paslaugų – į <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">BaFin pranešimų teikimo kanalą (svetainė vokiečių kalba)</a>, dėl kartelių pažeidimų – į <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Federalinę kartelių tarnybą (svetainė vokiečių kalba)</a>, dėl duomenų apsaugos pažeidimų – į <a href="#aufsicht">priežiūros instituciją (žr. toliau)</a>.</p>
</details>

<details class="dse"><summary><h3>Nagrinėjimas</h3></summary>
<p>Iš pradžių Jūsų pranešimą skaito tik vidaus pranešimų padalinio komanda. Gavęs naują pranešimą ar žinutę, portalas komandai siunčia el. laišką. Jame nurodomas tik pranešimo ID<?=setting("Thema_in_Mail") !== "nein" ? " ir tema" : ""?>, bet ne turinys.</p>
<p><?=privacy_text("speicher")?> Komanda šiuos duomenis naudoja pranešimui nagrinėti. Kitus asmens duomenis ji tvarko tik tada, kai tai tam būtina.</p>
<p>Aiškindamasi aplinkybes, komanda gali saugoti ir medžiagą, pavyzdžiui, dokumentus, nuotraukas ar el. laiškus. Kai ką atsiunčiate Jūs, kitką komanda randa pati. Ši medžiaga saugoma ne portale, o apsaugotoje pranešimų padalinio saugykloje. Jai galioja tas pats: prieigą turi tik komanda, o ištriname ją kartu su pranešimu.</p>
</details>

<details class="dse"><summary><h3>Perdavimas</h3></summary>
<p>Informaciją apie Jūsų asmenį perduodame tik:</p>
<ul>
<li>jei tai būtina pranešimui nagrinėti,</li>
<li>jei aiškiai sutikote, arba</li>
<li>jei tyčia ar dėl didelio neatsargumo pranešėte neteisingą informaciją.</li>
</ul>
<p>Prieš tai Jums pranešame, nebent tai keltų pavojų tyrimui ar teismo procesui. Asmenys, apie kuriuos kalbama Jūsų pranešime, nesužino, kas pranešė. Jūsų apsauga svarbesnė už jų teisę gauti informaciją ir susipažinti su duomenimis.</p>
<p>Esate paminėti pranešime? Tada informuosime Jus apie Jūsų duomenis, kai tai nebekels pavojaus aplinkybių išaiškinimui.</p>
</details>

<details class="dse"><summary><h3>Saugojimo laikotarpis</h3></summary>
<p>Kol pranešimą privalome saugoti, komanda gali matyti visą su juo susijusią informaciją. Su prieigos kodu ją matote ir Jūs. Komanda ištrina pranešimą praėjus 3 metams nuo jo užbaigimo. Ilgiau – tik jei to reikalauja įstatymas. <?=privacy_text("sicherung")?></p>
</details>

<h2>Jūsų teisės ir kontaktai</h2>

<p>Turite šias teises: susipažinti su savo duomenimis, juos ištaisyti, ištrinti, apriboti jų tvarkymą ir pateikti skundą priežiūros institucijai. Šios teisės gali būti apribotos. Taip yra, kai to reikia pranešantiems ir susijusiems asmenims apsaugoti arba kad nekiltų pavojus aplinkybių išaiškinimui. Ištrinti duomenis galime tik pasibaigus įstatyme nustatytam saugojimo terminui.</p>

<p>Už Jūsų asmens duomenų tvarkymą atsakingas duomenų valdytojas yra:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Kas nors neaišku arba turite klausimų apie savo duomenis? Tada rašykite mums: <?=mail_verbergen(setting("DS-Mail"))?>. Šie el. laiškai patenka mūsų duomenų apsaugos komandai. Jai priklauso ir duomenų apsaugos pareigūnas. Greitai Jums atsakysime.</p>

<p id="aufsicht">Taip pat galite tiesiogiai pateikti skundą <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">duomenų apsaugos priežiūros institucijai</a> (svetainė vokiečių kalba). Mus prižiūrinti institucija yra</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Neoficialus vertimas – privalomas <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">tekstas vokiečių kalba</a>.</p>
