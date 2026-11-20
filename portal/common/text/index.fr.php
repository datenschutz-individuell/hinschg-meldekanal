<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Service&nbsp;de&nbsp;signalement&nbsp;interne<?=$titelzusatz?></h1>
    <p>Ici, vous pouvez signaler des violations de la loi. Il s'agit de violations commises par des membres du personnel de <?=$bezug ?: $firma?> ou en son nom. Le service de signalement examine votre signalement. Si nécessaire, il vous contacte. Ce portail n'est pas fait pour les conflits entre membres du personnel ni pour les réclamations.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Informez-vous</h2>
    <p>Les personnes qui signalent ont aussi des obligations. Par exemple, n'écrivez que ce dont nous avons besoin pour l'examen. La loi allemande sur la protection des lanceurs d'alerte (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, traduction anglaise</a>) indique quelles violations vous pouvez signaler ici. Elle indique aussi comment vous et les personnes mentionnées êtes protégés. <?=t("start_anonym")?></p>

    <h2>2. Interne ou externe ?</h2>
    <p>Votre signalement est traité par l'équipe du service de signalement interne. Il s'agit actuellement de <?=setting("Team_fr") ?: str_replace(["Herr ", "Frau ", " und "], ["M. ", "Mme ", " et "], setting("Team"))?>. Vous craignez un préjudice si vous signalez ici ? Utilisez alors un service de signalement externe, par exemple le <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">service de signalement externe de la Fédération auprès de l'Office fédéral de la justice (site en allemand)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Signaler <span class="font-normal">ou</span> lire et compléter</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Nouveau signalement</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Ouvrir mon signalement</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
