<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Informace o zpracování osobních údajů</h1>

<p>Zde můžete oznámit porušení zákonů. Jde o porušení ze strany zaměstnanců organizace <?=$firma?> nebo osob jednajících jejím jménem. Tým, který oznámení vyřizuje, vaše oznámení prověří. Pokud je to nutné, požádá o radu další odborníky zevnitř nebo zvenku. Nebo porušení předá příslušnému úřadu.</p>

<p>Portál je kanálem pro oznamování podle německého zákona o ochraně oznamovatelů (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, anglický překlad</a>). Zde vysvětlujeme, jak zacházíme s vašimi osobními údaji. Jde o údaje, které zadáte na portálu, a o údaje, které vzniknou vaším oznámením.</p>

<p>Tyto údaje zpracováváme, protože nám to ukládá HinSchG: musíme provozovat kanál pro oznamování a vyřizovat oznámení. Zvlášť chráněné údaje, například o zdraví, zpracováváme jen tehdy, když je to k tomu nutné. Předávat údaje smíme jen tehdy, když to tento zákon dovoluje.</p>

<h2>Co se děje s vašimi údaji</h2>

<details class="dse"><summary><h3>Návštěva portálu</h3></summary>
<p><?=operation_sentence()?> Webový server neukládá IP adresy. Jeho protokol slouží jen k hledání chyb. Mažeme ho nejpozději po 2 měsících. <?=privacy_text("statistik")?></p>
<p>Portál nepoužívá sledovací nástroje ani služby třetích stran. Kdo oznamuje, nedostává žádné soubory cookie.<?php if (portal_anmeldung_aktiv()) { ?> Jen když se přihlašuje tým, nastaví portál dva technicky nutné soubory cookie pro přihlášení. Zaniknou odhlášením nebo zavřením prohlížeče.<?php } ?> Vaše zadání používáme jen k vyřízení vašeho oznámení podle HinSchG.</p>
</details>

<details class="dse"><summary><h3>Oznámení a přístupový kód</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Tento portál přijímá jen oznámení se jménem a kontaktními údaji, například e-mailovou adresou nebo telefonním číslem. Tak vás zastihneme, když máme otázky. Sami odpovídáte za to, že údaje jsou správné. Bez jména můžete oznámit u externího kanálu pro oznamování (viz níže).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Na tomto portálu oznamujete anonymně. Pole pro jméno a kontaktní údaje tu nejsou. Uvedete je přesto, například ve zprávě? Pak je použijeme jen k vyjasnění otázek. Jinak vás zastihneme jen přes portál.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Jméno a kontaktní údaje jsou dobrovolné, například e-mailová adresa nebo telefonní číslo. S těmito údaji vás při otázkách zastihneme přímo. Sami odpovídáte za to, že jsou správné. Bez nich vás zastihneme jen přes portál.</p>
<?php
}
?>
<p>Po odeslání dostanete přístupový kód („token“). Uvidíte ho jen jednou. S ním můžete své oznámení kdykoli číst, i s našimi otázkami a odpověďmi. Dokud není uzavřeno, můžete týmu psát. Přístupový kód můžete kdykoli zneplatnit. Ztratíte ho a neuvedli jste kontaktní údaje? Pak vám nemůžeme odpovědět.</p>
<p>Obáváte se, že by vám oznámení u nás mohlo uškodit? Pak se můžete obrátit také na externí kanál pro oznamování, tam i bez jména: na <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">spolkový externí kanál pro oznamování při Spolkovém úřadu pro spravedlnost (web v němčině)</a>, v případě finančních služeb na <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">kanál pro oznamování úřadu BaFin (web v němčině)</a>, v případě porušení kartelového práva na <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Spolkový kartelový úřad (web v němčině)</a>, v případě porušení ochrany osobních údajů na <a href="#aufsicht">dozorový úřad (viz níže)</a>.</p>
</details>

<details class="dse"><summary><h3>Vyřizování</h3></summary>
<p>Vaše oznámení čte nejprve jen tým interního kanálu pro oznamování. Při nových oznámeních a zprávách pošle portál týmu e-mail. Je v něm jen ID oznámení<?=setting("Thema_in_Mail") !== "nein" ? " a téma" : ""?>, ne obsah.</p>
<p><?=privacy_text("speicher")?> Tým je používá k vyřízení oznámení. Další osobní údaje zpracovává jen tehdy, když je to k tomu nutné.</p>
<p>Při objasňování může tým ukládat i podklady, například dokumenty, fotografie nebo e-maily. Některé nám pošlete vy, jiné najde tým sám. Tyto podklady nejsou na portálu, ale v chráněném úložišti kanálu pro oznamování. Platí pro ně totéž: přístup má jen tým a mažeme je spolu s oznámením.</p>
</details>

<details class="dse"><summary><h3>Předávání údajů</h3></summary>
<p>Údaje o vaší osobě předáváme jen,</p>
<ul>
<li>když je to nutné k vyřízení oznámení,</li>
<li>když jste k tomu výslovně dali souhlas, nebo</li>
<li>když jste úmyslně nebo z hrubé nedbalosti oznámili nepravdivé informace.</li>
</ul>
<p>Předem vám dáme vědět, pokud by to neohrozilo vyšetřování nebo soudní řízení. Osoby, o které ve vašem oznámení jde, se nedozví, kdo oznámení podal. Vaše ochrana má přednost před jejich právem na informace a na přístup.</p>
<p>Jste v oznámení uvedeni? Pak vás o vašich údajích informujeme, jakmile to už neohrozí objasnění věci.</p>
</details>

<details class="dse"><summary><h3>Doba uložení</h3></summary>
<p>Dokud musíme oznámení uchovávat, vidí tým všechny údaje k němu. S přístupovým kódem je vidíte i vy. Tým oznámení vymaže 3 roky po uzavření. Déle jen tehdy, když to vyžaduje zákon. <?=privacy_text("sicherung")?></p>
</details>

<h2>Vaše práva a kontakt</h2>

<p>Máte tato práva: přístup ke svým údajům, oprava, výmaz, omezení zpracování a stížnost u dozorového úřadu. Tato práva mohou být omezena. Platí to, když je to nutné k ochraně oznamujících a dotčených osob nebo aby nebylo ohroženo objasnění věci. Vymazat můžeme až po skončení zákonné lhůty pro uchovávání.</p>

<p>Správcem odpovědným za zpracování vašich osobních údajů je:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Je něco nejasné, nebo máte otázky ke svým údajům? Pak nám napište: <?=mail_verbergen(setting("DS-Mail"))?>. Tyto e-maily dostává náš tým pro ochranu osobních údajů. Patří k němu i náš pověřenec pro ochranu osobních údajů. Ozveme se vám rychle.</p>

<p id="aufsicht">Máte také právo podat stížnost přímo u <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">některého dozorového úřadu pro ochranu osobních údajů</a> (web v němčině). Naším příslušným dozorovým úřadem je</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Informativní překlad – závazné je <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">německé znění</a>.</p>
