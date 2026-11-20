<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Whistleblowing&nbsp;Portal<?=$titelzusatz?></h1>
    <p>Here you can report breaches of the law. This covers breaches by employees of <?=$bezug ?: $firma?> or by anyone acting on its behalf. The reporting office will review your report. If necessary, it will contact you. This portal is not for disputes between employees or for complaints.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Know your rights and duties</h2>
    <p>People who report also have duties. For example, write only what we need to look into the matter. The German Whistleblower Protection Act (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, English translation</a>) says which breaches you can report here. It also says how you and the people you name are protected. <?=t("start_anonym")?></p>

    <h2>2. Internal or external?</h2>
    <p>Your report is handled by the team of the internal reporting office. At present, this is <?=setting("Team_en") ?: str_replace(["Herr ", "Frau ", " und "], ["Mr ", "Ms ", " and "], setting("Team"))?>. Are you worried that reporting here could harm you? Then please use an external reporting office, for example the <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">Federal external reporting office at the Federal Office of Justice (website in German)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Report <span class="font-normal">or</span> read and add to your report</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>New report</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Open my report</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
