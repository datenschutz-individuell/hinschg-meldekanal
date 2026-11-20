<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

    <h1>Intern&nbsp;whistleblowerenhed<?=$titelzusatz?></h1>
    <p>Her kan du indberette lovovertrædelser. Det gælder overtrædelser begået af medarbejdere i <?=$bezug ?: $firma?> eller på virksomhedens vegne. Whistleblowerenheden gennemgår din indberetning. Hvis det er nødvendigt, kontakter den dig. Portalen er ikke til uenigheder mellem medarbejdere eller til reklamationer.</p>
<?php if (illustriert()) { ?>
    <div class="schritte">
        <div class="schritt"><?=symbol("melden")?><b><?=t("schritt1_titel")?></b><span><?=zeilen(t("schritt1_text"))?></span></div>
        <div class="schritt"><?=symbol("zugangscode")?><b><?=t("schritt2_titel")?></b><span><?=zeilen(t("schritt2_text"))?></span></div>
        <div class="schritt"><?=symbol("antwort")?><b><?=t("schritt3_titel")?></b><span><?=zeilen(t("schritt3_text"))?></span></div>
    </div>
<?php } ?>


    <h2>1. Kend dine rettigheder og pligter</h2>
    <p>Også den, der indberetter, har pligter. Skriv for eksempel kun det, vi har brug for til at undersøge sagen. I den tyske whistleblowerlov (<a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">HinSchG, engelsk oversættelse</a>) står der, hvilke overtrædelser du kan indberette her. Der står også, hvordan du og de nævnte personer er beskyttet. <?=t("start_anonym")?></p>

    <h2>2. Internt eller eksternt?</h2>
    <p>Din indberetning behandles af teamet i den interne whistleblowerenhed. Det er i øjeblikket <?=setting("Team_da") ?: str_replace(["Herr ", "Frau ", " und "], ["hr. ", "fru ", " og "], setting("Team"))?>. Frygter du negative følger, hvis du indberetter her? Så brug venligst en ekstern whistleblowerordning, for eksempel <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">den tyske forbundsstats eksterne whistleblowerordning hos Bundesamt für Justiz (hjemmeside på tysk)</a>. <?=setting_nur_l("Persoenlich") ?: t("start_persoenlich")?></p>

    <h2>3. Indberet <span class="font-normal">eller</span> læs og suppler</h2>
    <div class="partner-button-container start-knoepfe">
        <form action="<?=url("/melden/")?>"><button type="submit"><?=symbol("melden")?>Ny indberetning</button></form>
        <form action="<?=url("/bearbeiten/")?>"><button type="submit"><?=symbol("antwort")?>Åbn min indberetning</button></form>
    </div><div class="neue-zeile"></div>

<?=setting_l("Zusatz")?>
