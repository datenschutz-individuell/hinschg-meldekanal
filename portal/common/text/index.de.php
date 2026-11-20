<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Interne&nbsp;Meldestelle<?=$titelzusatz?></h1>
    <p>Hier können Sie Verstöße gegen Gesetze melden. Es geht um Verstöße von Beschäftigten oder im Namen der <?=$bezug ?: $firma?>. Die Meldestelle prüft Ihre Meldung. Wenn nötig, meldet sie sich bei Ihnen. Für Streit unter Beschäftigten oder für Reklamationen ist dieses Portal nicht da.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Informieren Sie sich</h2>
    <p>Auch wer meldet, hat Pflichten. Schreiben Sie zum Beispiel nur, was wir zum Prüfen brauchen. Im <a href="https://www.gesetze-im-internet.de/hinschg/index.html" target="extern" rel="noopener noreferrer">Hinweisgeberschutzgesetz</a> steht, welche Verstöße Sie hier melden können. Dort steht auch, wie Sie und die genannten Personen geschützt sind. <?=t("start_anonym")?></p>

    <h2>2. Intern oder extern?</h2>
    <p>Ihre Meldung bearbeitet das Team der internen Meldestelle. Das sind zurzeit <?=setting_l("Team")?>. Sie fürchten Nachteile, wenn Sie hier melden? Dann nutzen Sie bitte eine externe Meldestelle, zum Beispiel die <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">externe Meldestelle des Bundes beim Bundesamt für Justiz</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Melden <span class="font-normal">oder</span> lesen und ergänzen</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Neue Meldung</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Meine Meldung öffnen</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
