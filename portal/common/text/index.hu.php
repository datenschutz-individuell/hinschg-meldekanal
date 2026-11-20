<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Belső&nbsp;bejelentési&nbsp;csatorna<?=$titelzusatz?></h1>
    <p>Itt jogszabálysértéseket jelenthet be. Olyan jogsértésekről van szó, amelyeket a(z) <?=$bezug ?: $firma?> munkavállalói vagy a nevében eljáró személyek követtek el. A bejelentési hely megvizsgálja bejelentését. Ha szükséges, felveszi Önnel a kapcsolatot. Munkavállalók közötti vitákra vagy reklamációkra ez a portál nem szolgál.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Ismerje meg jogait és kötelezettségeit</h2>
    <p>Annak is vannak kötelezettségei, aki bejelentést tesz. Például csak azt írja le, amire a vizsgálathoz szükségünk van. A német bejelentővédelmi törvényben (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, angol nyelvű fordítás</a>) áll, mely jogsértéseket jelentheti be itt. Ott az is áll, hogyan védi a törvény Önt és a megnevezett személyeket. <?=t("start_anonym")?></p>

    <h2>2. Belső vagy külső bejelentés?</h2>
    <p>Bejelentését a belső bejelentési hely csapata kezeli. Ők jelenleg: <?=setting("Team_hu") ?: str_replace(["Herr ", "Frau ", " und "], ["", "", " és "], setting("Team"))?>. Attól tart, hogy az itt tett bejelentés miatt hátrány érheti? Akkor kérjük, vegyen igénybe külső bejelentési csatornát, például a <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">Szövetségi Igazságügyi Hivatalnál működő szövetségi külső bejelentési csatornát (német nyelvű weboldal)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Bejelentés <span class="font-normal">vagy</span> olvasás és kiegészítés</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Új bejelentés</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Saját bejelentés megnyitása</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
