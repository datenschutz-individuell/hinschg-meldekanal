<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/../common/header.php");
include_once(__DIR__."/../common/spam-protection.php");
include_once(__DIR__."/../common/auth.php");


function process_form_input(): bool {
    // Same rules server-side as in the form – the browser can be bypassed. The topic must come from the
    // list (it also appears in the notification mail to the team); name/contact only if the portal provides for them.
    $feld = fn($n) => is_string($_POST[$n] ?? null) ? trim(standardise_newlines($_POST[$n])) : null;
    $beschreibung = $feld("case_description");
    if ($beschreibung === null || $beschreibung === "") return false;
    $recht = $feld("case_Recht"); $titel = $feld("case_title");
    $level = setting("Level");
    $mit_person = $level == "optional" || $level == "erforderlich";
    $person = $mit_person ? ($feld("case_person") ?? "") : ""; $kontakt = $mit_person ? ($feld("case_contact") ?? "") : "";
    $gueltig = in_array($recht, (array)setting("Recht"), true)
        && $titel !== null && mb_strlen($titel) >= 10 && mb_strlen($titel) <= 40
        && mb_strlen($beschreibung) <= 2000 && mb_strlen($person) <= 40 && mb_strlen($kontakt) <= 60
        && ($level != "erforderlich" || (mb_strlen($person) >= 7 && mb_strlen($kontakt) >= 7));
    if (!$gueltig) {
        echo warnkasten(t("spam_abgelehnt"));
        return false;
    }

    $text  = "Thema: ".htmlspecialchars($recht)."\n\n".htmlspecialchars($beschreibung);
    if ($person !== "" || $kontakt !== "") {
        $text .= "\n";
        $text .= $person !== "" ? "\nMeldende Person: " . htmlspecialchars($person) : "";
        $text .= $kontakt !== "" ? "\nKontaktdaten: " . htmlspecialchars($kontakt) : "";
    }

    $title = htmlspecialchars($titel);

    $abgelehnt = spam_formular_pruefen();
    if ($abgelehnt) {
        echo warnkasten(t("spam_abgelehnt"));   // one text for every reason
        return false;
    }
    spam_sperre(true);

    // Same form sent again (page reloaded)? The time token alone is not unique (same second, back button), so
    // keyed with the content: the stored text may be encrypted, and a plain hash would confirm a known report.
    $all_cases = get_all_cases();
    $formular = hash_hmac("sha256", implode("\0", [(string)($_POST["formzeit"] ?? ""), $title, $text]), spam_schluessel());
    foreach ($all_cases as $case) if (hash_equals((string)($case["form"] ?? ""), $formular)) {
        echo warnkasten(t("neu_doppelt"));
        return false;
    }

    $abgelehnt = spam_obergrenze($all_cases);
    if ($abgelehnt) {
        echo warnkasten(t($abgelehnt, htmlspecialchars(setting("Firma")), "#impressum"));
        return false;
    }

    // Create the new report
    $token = generate_random_string(24);
    $case_id = get_case_id_from_token($token);
    $case_data = [
        "id" => $case_id,   // for inform_stakeholders; not stored (the file name is the ID)
        "token" => $token,
        "title" => $title,
        "status" => "unbearbeitet",
        "form" => $formular,
        "conversation" => [[
            "person" => "meldende_person",
            "date" => german_date(),
            "text" => $text,
        ]]
    ];
    // PHP login: encrypted right away for all members with a key, plus emergency key (without it header.php
    // stops the page earlier). Without members with a key the case stays plain until one logs in
    // (auth.php verpackungen_ergaenzen), else nobody could read it.
    if (portal_anmeldung_aktiv() && ($an = empfaenger($case_id)) && ($notfall = notfall_schluessel(dirname(KUNDE_DIR)))) {
        $case_data = fall_verschluesseln($case_data, random_bytes(SCHLUESSEL_LAENGE), substr($token, CASE_ID_LENGTH), $an, $notfall);
    }
    unter_fallsperre(fn() => fall_schreiben($case_id, $case_data, true)) or die("Kritischer Fehler! Datei kann nicht geschrieben werden!");
    spam_sperre(false);
    inform_stakeholders($case_data, setting("Thema_in_Mail") !== "nein" ? htmlspecialchars($recht) : "");

    ?>

    <h1><?=t("gespeichert_titel")?></h1>
    <?php if (illustriert()) echo '<div class="fristen-info">' . t("gespeichert_fristen") . '</div>'; ?>

    <div class="zugangskarte"><?=symbol("zugangscode")?><div>
    <h2><?=t("gespeichert_zugriff")?></h2>
    <p><?=t("gespeichert_token")?></p>
    <?= code_kasten($token, "-") ?>
    <p><?=t("gespeichert_warnung")?></p>

    <?php // The button copying the case to the clipboard is shown only with JS enabled.?>
    <p class="knopfreihe"><script>
        // Success shown in the button itself: swap text, colour green (success/background colour pair is checked)
        function CopyTokenToClipboard(k) {
            navigator.clipboard.writeText("<?= implode("-", str_split($token, 4)) ?>").then(() => {
                k.textContent = <?=json_encode(t("token_kopiert"))?>; k.classList.add("knopf-erfolg");
            },() => {
                document.getElementById("token_copy_response").innerHTML = "<span class='font-fehler'>" + <?=json_encode(t("token_nicht_kopiert"))?> + "</span>";
            });
        }
        document.write('<button type="button" onclick="CopyTokenToClipboard(this)">' + <?=json_encode(symbol("kopieren") . t("token_kopieren"))?> + '</button>');
        <?php if (illustriert()) { ?>document.write('<button type="button" onclick="window.print()">' + <?=json_encode(symbol("drucker") . t("token_drucken"))?> + '</button>');<?php } ?>
    </script></p>
    <p id="token_copy_response"></p>
    </div></div>

    <h2><?=t("gespeichert_komm")?></h2>
    <p><?=t(illustriert() ? "gespeichert_komm_kurz" : "gespeichert_komm_text")?></p>

    <div class="partner-button-container">
        <form method="post" action="<?=url("/bearbeiten/")?>">
            <input type="hidden" name="token" value="<?=$token?>">
            <button type="submit"><?=symbol("antwort")?><?=t("einsehen_knopf")?></button>
        </form>
    </div>

    <?php
    return true;
}


// MAIN

if (!process_form_input()) {
    ?>

    <h1><?=t("neu_titel")?></h1>
    <?php if (illustriert()) { $stufe = setting("Level") ?: "anonym"; ?>
    <div class="vertrauen"><?=symbol("schutz")?><ul>
        <li><?=t("vertrauen_1")?></li><li><?=t("vertrauen_" . $stufe)?></li><li><?=t("vertrauen_4")?></li><li><?=t("vertrauen_3")?></li>
    </ul></div>
    <?php } ?>
    <form class="label-links" action="" method="post">
        <?=spam_felder()?>
        <fieldset class="gruppe"><legend><?=t("gruppe_sache")?></legend>

        <label>
            <span><?=t("neu_rechtsgebiet")?></span>
            <select name="case_Recht" required>
                <option value=""><?=t("neu_rechtsgebiet_wahl")?></option>

                <?php
                $anwendungsbereiche = setting("Recht");
                for($i = 0; $i < count($anwendungsbereiche); ++$i) {
                    $anwendungsbereich = $anwendungsbereiche[$i];
                    // Value stays German (stored report text for the team); only the display is translated.
                    $gewaehlt = ($_POST['case_Recht'] ?? null) === $anwendungsbereich ? " selected" : "";
                    echo "<option value='".htmlspecialchars($anwendungsbereich, ENT_QUOTES)."'$gewaehlt>".htmlspecialchars(t_wert($anwendungsbereich))."</option>";
                }
                ?>

            </select>
        </label>

        <label>
            <span><?=t("neu_titel_feld")?></span>
            <input type="text" name="case_title" value="<?=formwert("case_title")?>" required placeholder="<?=t("neu_titel_platz")?>" minlength="10" maxlength="40" <?=remaining_signs_hint()?>>
        </label>

        <label class="breit">
            <span><?=t("neu_was")?></span>
            <textarea name="case_description" required placeholder="<?=t("neu_was_platz")?>"
                      rows="12" maxlength="2000" <?=remaining_signs_hint()?>><?=formwert("case_description")?></textarea>
        </label>

        </fieldset>
        <?php
        $anon_level = setting("Level");
        if ($anon_level == "optional" || $anon_level == "erforderlich") {
            $html_attributes = $anon_level == "erforderlich" ? "required minlength='7'" : "";
            ?>

            <fieldset class="gruppe"><legend><?=t("gruppe_person")?></legend>
            <label>
                <span><?=t("neu_name", t_wert($anon_level))?></span>
                <input type="text" name="case_person" value="<?=formwert("case_person")?>" <?=$html_attributes?> placeholder="<?=t("neu_name_platz")?>" maxlength="40" <?=remaining_signs_hint()?>>
            </label>
            <label>
                <span><?=t("neu_kontakt", t_wert($anon_level))?></span>
                <input type="text" name="case_contact" value="<?=formwert("case_contact")?>" <?=$html_attributes?> placeholder="<?=t("neu_kontakt_platz")?>" maxlength="60" <?=remaining_signs_hint()?>>
            </label>
            </fieldset>

            <?php
        }
        ?>

        <button type="submit">
            <?=symbol("senden")?><?=t("neu_absenden")?>
        </button>
        <p class="unter-knopf"><?=t("neu_datenschutz", htmlspecialchars(url("/datenschutz.php")))?></p>

    </form>

    <?php
}

include_once(__DIR__."/../common/footer.php");
