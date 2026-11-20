<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Adatkezelési tájékoztató</h1>

<p>Itt jogszabálysértéseket jelenthet be. Olyan jogsértésekről van szó, amelyeket a(z) <?=$firma?> munkavállalói vagy a nevében eljáró személyek követtek el. A bejelentési hely megvizsgálja bejelentését. Ha szükséges, további belső vagy külső szakértőket kérdez meg. Vagy továbbítja a jogsértést az illetékes hatósághoz.</p>

<p>A portál a német bejelentővédelmi törvény (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, angol nyelvű fordítás</a>) szerinti bejelentési csatorna. Itt elmagyarázzuk, hogyan kezeljük személyes adatait. Ezek azok az adatok, amelyeket a portálon megad, és azok, amelyek a bejelentése miatt keletkeznek.</p>

<p>Ezeket az adatokat azért kezeljük, mert a HinSchG erre kötelez bennünket: bejelentési helyet kell működtetnünk, és kezelnünk kell a bejelentéseket. A különösen védett adatokat, például az egészségügyi adatokat, csak akkor kezeljük, ha ehhez szükséges. Adatokat csak akkor adhatunk tovább, ha ez a törvény ezt megengedi.</p>

<h2>Mi történik az adataival</h2>

<details class="dse"><summary><h3>A portál felkeresése</h3></summary>
<p><?=operation_sentence()?> A webszerver nem tárol IP-címeket. Naplója csak hibakeresésre szolgál. Legkésőbb 2 hónap után töröljük. <?=privacy_text("statistik")?></p>
<p>A portál nem használ nyomkövetőket és harmadik felek szolgáltatásait. Aki bejelentést tesz, nem kap sütiket.<?php if (portal_anmeldung_aktiv()) { ?> Csak amikor a csapat bejelentkezik, akkor állít be a portál két, technikailag szükséges sütit a bejelentkezéshez. Ezek a kijelentkezéskor vagy a böngésző bezárásakor megszűnnek.<?php } ?> Az Ön által megadott adatokat csak arra használjuk, hogy bejelentését a HinSchG szerint kezeljük.</p>
</details>

<details class="dse"><summary><h3>Bejelentés és hozzáférési kód</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Ez a portál csak névvel és elérhetőséggel ellátott bejelentéseket fogad, például e-mail-címmel vagy telefonszámmal. Így kérdés esetén elérjük Önt. Ön felel azért, hogy az adatok helyesek legyenek. Név nélkül külső bejelentési csatornán tehet bejelentést (lásd alább).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Ezen a portálon névtelenül tesz bejelentést. Nincsenek mezők a név és az elérhetőség számára. Mégis megadja ezeket, például egy üzenetben? Akkor csak a kérdések tisztázására használjuk őket. Egyébként csak a portálon keresztül tudjuk elérni Önt.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>A név és az elérhetőség megadása önkéntes, például e-mail-cím vagy telefonszám. Ezekkel az adatokkal kérdés esetén közvetlenül elérjük Önt. Ön felel azért, hogy helyesek legyenek. Nélkülük csak a portálon keresztül tudjuk elérni Önt.</p>
<?php
}
?>
<p>Az elküldés után kap egy hozzáférési kódot. Csak egyszer látja. Ezzel bármikor elolvashatja bejelentését, a kérdéseinkkel és válaszainkkal együtt. Amíg az ügy nincs lezárva, írhat a csapatnak. A hozzáférési kódot bármikor érvénytelenítheti. Elveszett a kód, és nem adott meg elérhetőséget? Akkor nem tudunk válaszolni Önnek.</p>
<p>Attól tart, hogy a nálunk tett bejelentés miatt hátrány érheti? Akkor külső bejelentési csatornához is fordulhat, ott név nélkül is: a <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">Szövetségi Igazságügyi Hivatalnál működő szövetségi külső bejelentési csatornához (német nyelvű weboldal)</a>, pénzügyi szolgáltatások esetén a <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">BaFin bejelentési pontjához (német nyelvű weboldal)</a>, kartelljogi jogsértések esetén a <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Szövetségi Kartellhivatalhoz (német nyelvű weboldal)</a>, adatvédelmi jogsértések esetén a <a href="#aufsicht">felügyeleti hatósághoz (lásd alább)</a>.</p>
</details>

<details class="dse"><summary><h3>Kezelés</h3></summary>
<p>Bejelentését először csak a belső bejelentési hely csapata olvassa. Új bejelentésekről és üzenetekről a portál e-mailt küld a csapatnak. Ebben csak a bejelentés azonosítója<?=setting("Thema_in_Mail") !== "nein" ? " és a téma" : ""?> szerepel, a tartalom nem.</p>
<p><?=privacy_text("speicher")?> A csapat ezeket a bejelentés kezelésére használja. További személyes adatokat csak akkor kezel, ha ehhez szükséges.</p>
<p>A tényállás tisztázása során a csapat iratokat is tárolhat, például dokumentumokat, fényképeket vagy e-maileket. Egyeseket Ön küld nekünk, másokat a csapat maga talál. Ezek az iratok nem a portálon vannak, hanem a bejelentési hely védett tárhelyén. Rájuk ugyanez érvényes: csak a csapat fér hozzájuk, és a bejelentéssel együtt töröljük őket.</p>
</details>

<details class="dse"><summary><h3>Továbbítás</h3></summary>
<p>Az Ön személyére vonatkozó adatokat csak akkor adjuk tovább,</p>
<ul>
<li>ha ez a kezeléshez szükséges,</li>
<li>ha ehhez kifejezetten hozzájárult, vagy</li>
<li>ha szándékosan vagy súlyos gondatlanságból valótlant jelentett be.</li>
</ul>
<p>Előtte szólunk Önnek, kivéve, ha ez veszélyeztetné a nyomozást vagy egy bírósági eljárást. Akikről a bejelentése szól, nem tudják meg, ki tette a bejelentést. Az Ön védelme elsőbbséget élvez az ő tájékoztatáshoz és hozzáféréshez való jogukkal szemben.</p>
<p>Önt megnevezik egy bejelentésben? Akkor tájékoztatjuk adatairól, amint ez már nem veszélyezteti a tényállás tisztázását.</p>
</details>

<details class="dse"><summary><h3>Tárolási idő</h3></summary>
<p>Amíg egy bejelentést meg kell őriznünk, a csapat minden hozzá tartozó adatot láthat. A hozzáférési kóddal Ön is láthatja őket. A csapat a bejelentést a lezárás után 3 évvel törli. Tovább csak akkor őrzi, ha törvény előírja. <?=privacy_text("sicherung")?></p>
</details>

<h2>Az Ön jogai és elérhetőségünk</h2>

<p>Önnek ezek a jogai vannak: hozzáférés az adataihoz, helyesbítés, törlés, az adatkezelés korlátozása és panasz egy felügyeleti hatóságnál. Ezek a jogok korlátozhatók. Ez akkor érvényes, ha ez a bejelentő és az érintett személyek védelméhez vagy a tényállás tisztázásának védelméhez szükséges. Törölni csak a törvényes megőrzési idő lejárta után tudunk.</p>

<p>Személyes adatai kezelésének adatkezelője:</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Valami nem világos, vagy kérdése van az adataival kapcsolatban? Akkor írjon nekünk: <?=mail_verbergen(setting("DS-Mail"))?>. Ezeket az e-maileket adatvédelmi csapatunk kapja meg. Ennek tagja az adatvédelmi tisztviselő is. Gyorsan válaszolunk.</p>

<p id="aufsicht">Panaszt közvetlenül is benyújthat <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">valamely adatvédelmi felügyeleti hatóságnál</a> (német nyelvű weboldal). Ránk nézve illetékes</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Tájékoztató fordítás – a <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">német változat</a> az irányadó.</p>
