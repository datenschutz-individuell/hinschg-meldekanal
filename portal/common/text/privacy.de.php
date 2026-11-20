<?php /*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Datenschutzhinweis</h1>

<p>Hier können Sie Verstöße gegen Gesetze melden. Es geht um Verstöße von Beschäftigten oder im Namen der <?=$firma?>. Die Meldestelle prüft Ihre Meldung. Wenn nötig, fragt sie weitere Fachleute von innen oder außen. Oder sie gibt den Verstoß an die zuständige Behörde weiter.</p>

<p>Das Portal ist ein Meldekanal nach dem <a href="https://www.gesetze-im-internet.de/hinschg/" target="extern" rel="noopener noreferrer">Hinweisgeberschutzgesetz</a> (HinSchG). Hier erklären wir, wie wir mit Ihren personenbezogenen Daten umgehen. Gemeint sind Daten, die Sie im Portal eingeben, und Daten, die durch Ihre Meldung entstehen.</p>

<p>Wir verarbeiten diese Daten, weil uns das HinSchG dazu verpflichtet: Wir müssen eine Meldestelle betreiben und Meldungen bearbeiten. Besonders geschützte Daten, zum Beispiel zur Gesundheit, verarbeiten wir nur, wenn das dafür nötig ist. Weitergeben dürfen wir Daten nur, wenn dieses Gesetz es erlaubt.</p>

<h2>Was mit Ihren Daten geschieht</h2>

<details class="dse"><summary><h3>Besuch des Portals</h3></summary>
<p><?=operation_sentence()?> Der Webserver speichert keine IP-Adressen. Sein Protokoll dient nur zur Fehlersuche. Wir löschen es nach spätestens 2 Monaten. <?=privacy_text("statistik")?></p>
<p>Das Portal nutzt keine Tracker und keine Dienste von Dritten. Wer meldet, bekommt keine Cookies.<?php if (portal_anmeldung_aktiv()) { ?> Nur wenn sich das Team anmeldet, setzt das Portal zwei technisch nötige Cookies für die Anmeldung. Sie enden mit der Abmeldung oder wenn Sie den Browser schließen.<?php } ?> Ihre Eingaben nutzen wir nur, um Ihre Meldung nach HinSchG zu bearbeiten.</p>
</details>

<details class="dse"><summary><h3>Melden und Zugangscode</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Dieses Portal nimmt nur Meldungen mit Namen und Kontaktdaten an, zum Beispiel E-Mail-Adresse oder Telefonnummer. So erreichen wir Sie bei Fragen. Sie sind selbst dafür verantwortlich, dass die Angaben stimmen. Ohne Namen melden können Sie bei einer externen Meldestelle (siehe unten).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>In diesem Portal melden Sie anonym. Es gibt keine Felder für Name und Kontaktdaten. Nennen Sie diese trotzdem, zum Beispiel in einer Nachricht? Dann nutzen wir sie nur, um Fragen zu klären. Sonst erreichen wir Sie nur über das Portal.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Name und Kontaktdaten sind freiwillig, zum Beispiel E-Mail-Adresse oder Telefonnummer. Mit diesen Angaben erreichen wir Sie bei Fragen direkt. Sie sind selbst dafür verantwortlich, dass sie stimmen. Ohne sie erreichen wir Sie nur über das Portal.</p>
<?php
}
?>
<p>Nach dem Absenden bekommen Sie einen Zugangscode. Sie sehen ihn nur einmal. Damit können Sie Ihre Meldung jederzeit lesen, mit unseren Fragen und Antworten. Solange sie nicht abgeschlossen ist, können Sie dem Team schreiben. Sie können den Zugangscode jederzeit ungültig machen. Ist er weg und haben Sie keine Kontaktdaten angegeben? Dann können wir Ihnen nicht antworten.</p>
<p>Sie fürchten Nachteile, wenn Sie bei uns melden? Dann können Sie sich auch an eine externe Meldestelle wenden, dort auch ohne Namen: an die <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">externe Meldestelle des Bundes beim Bundesamt für Justiz</a>, bei Finanzdienstleistungen an die <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">Hinweisgeberstelle der BaFin</a>, bei Kartellverstößen an das <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Bundeskartellamt</a>, bei Datenschutzverstößen an die <a href="#aufsicht">Aufsicht (siehe unten)</a>.</p>
</details>

<details class="dse"><summary><h3>Bearbeitung</h3></summary>
<p>Ihre Meldung liest zuerst nur das Team der internen Meldestelle. Bei neuen Meldungen und Nachrichten schickt das Portal dem Team eine E-Mail. Darin stehen nur die Meldungs-ID<?=setting("Thema_in_Mail") !== "nein" ? " und das Thema" : ""?>, nicht der Inhalt.</p>
<p><?=privacy_text("speicher")?> Das Team nutzt sie, um die Meldung zu bearbeiten. Weitere personenbezogene Daten verarbeitet es nur, wenn das dafür nötig ist.</p>
<p>Bei der Aufklärung kann das Team auch Unterlagen speichern, zum Beispiel Dokumente, Fotos oder E-Mails. Manche schicken Sie uns, andere findet das Team selbst. Diese Unterlagen liegen nicht im Portal, sondern in einer geschützten Ablage der Meldestelle. Für sie gilt dasselbe: Nur das Team hat Zugriff, und wir löschen sie zusammen mit der Meldung.</p>
</details>

<details class="dse"><summary><h3>Weitergabe</h3></summary>
<p>Angaben zu Ihrer Person geben wir nur weiter,</p>
<ul>
<li>wenn es für die Bearbeitung nötig ist,</li>
<li>wenn Sie ausdrücklich zugestimmt haben, oder</li>
<li>wenn Sie absichtlich oder grob fahrlässig Falsches gemeldet haben.</li>
</ul>
<p>Vorher sagen wir Ihnen Bescheid, außer das würde Ermittlungen oder ein Gerichtsverfahren gefährden. Personen, um die es in Ihrer Meldung geht, erfahren nicht, wer gemeldet hat. Ihr Schutz geht vor deren Recht auf Information und Auskunft.</p>
<p>Werden Sie in einer Meldung genannt? Dann informieren wir Sie über Ihre Daten, sobald das die Aufklärung nicht mehr gefährdet.</p>
</details>

<details class="dse"><summary><h3>Speicherdauer</h3></summary>
<p>Solange wir eine Meldung aufbewahren müssen, kann das Team alle Angaben dazu sehen. Mit dem Zugangscode sehen Sie sie auch. Das Team löscht die Meldung 3 Jahre nach dem Abschluss. Länger nur, wenn ein Gesetz es verlangt. <?=privacy_text("sicherung")?></p>
</details>

<h2>Ihre Rechte und Kontakt</h2>

<p>Sie haben diese Rechte: Auskunft über Ihre Daten, Berichtigung, Löschung, Einschränkung der Verarbeitung und Beschwerde bei einer Aufsichtsbehörde. Diese Rechte können eingeschränkt sein. Das gilt, wenn es nötig ist, um meldende und betroffene Personen zu schützen oder die Aufklärung nicht zu gefährden. Löschen können wir erst nach Ende der gesetzlichen Frist zum Aufbewahren.</p>

<p>Verantwortlich für die Verarbeitung Ihrer personenbezogenen Daten ist</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Ist etwas unklar, oder haben Sie Fragen zu Ihren Daten? Dann schreiben Sie uns: <?=mail_verbergen(setting("DS-Mail"))?>. Diese E-Mails gehen an unser Datenschutz-Team. Dazu gehört auch die oder der Datenschutzbeauftragte. Wir melden uns schnell bei Ihnen.</p>

<p id="aufsicht">Sie können sich mit einer Beschwerde auch direkt an <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">eine Datenschutz-Aufsichtsbehörde</a> wenden. Für uns zuständig ist</p>

<?=aufsicht_html()?>
