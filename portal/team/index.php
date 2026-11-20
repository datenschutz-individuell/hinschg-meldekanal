<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Show nothing without login – not only via the empty passwd role. Before header.php
// so the 401 is surely set before any output.
include_once(__DIR__."/../common/helper.php");
if (portal_anmeldung_aktiv()) {
    include_once(__DIR__."/../common/login.php");
    anmeldung_erzwingen();
}
if (!get_auth_user()) {
    http_response_code(401);
    exit("Anmeldung erforderlich.");
}
if ($_SERVER['REQUEST_METHOD'] === "POST" && !gleiche_herkunft()) {
    http_response_code(403);
    exit("Anfrage von fremder Seite abgelehnt.");
}
include_once(__DIR__."/../common/header.php");
include_once(__DIR__."/../common/case.php");

// MAIN

if (portal_anmeldung_aktiv()) {
    freigaben_bearbeiten();
    $b = benutzer_laden(get_auth_user());
    if ($b && faktoren($b, "passwort") === ["mail"]) echo "<div class='fristen-info'><a href='" . url("/team/konto/") . "'>" . t("konto_nur_mail") . "</a></div>";
}

if (isset($_GET['case_id'])) {
    // If a report ID is given, show that report

    if (!display_case(clean_token($_GET['case_id']), true)) {
        echo warnkasten(t("team_fehler_laden"));
    }

}
else {
    // Otherwise list all available reports

    if (!isset($_GET["loeschen"])) echo "<h1>" . t("team_titel") . "</h1>\n";

    // First build a data structure holding all reports to be shown
    $all_cases = get_all_cases($unlesbar);
    $unlesbar = array_filter($unlesbar, "is_current_user_authorized_for_case");
    if ($unlesbar) echo warnkasten(t("faelle_unlesbar", implode(", ", array_map("htmlspecialchars", $unlesbar))));
    $user_cases = [];
    $heute = heute();
    $paar = function_exists("paar_der_sitzung") ? paar_der_sitzung() : null;   // once, not per case
    for ($i = 0; $i < count($all_cases); ++$i) {
        $case = $all_cases[$i];
        $case["case_id"] = $case["id"];
        if (!is_current_user_authorized_for_case($case["case_id"])) continue;
        if (ist_verschluesselt($case)) {   // only the title, not every message
            $k = $paar ? viewer_key($case, true, null, $paar) : null;
            $case["title"] = ($k ? sym_entschluesseln($k, $case["title"]) : null) ?? t("krypt_titel_fehlt");
        }
        $case["start_date"] = $case["conversation"][0]["date"];
        $case["start_day"] = substr($case["start_date"],0,10);
        $case["last_entry_meldende_person_date"] = "-";
        $case["last_entry_team_date"] = "-";
        $case["last_entry_team_person"] = "";
        for ($j = 0; $j < count($case["conversation"]); ++$j) {
            if ($case["conversation"][$j]["person"] == MELDER) {
                $case["last_entry_date"] = substr($case["conversation"][$j]["date"],0,10);
                $case["last_entry_person"] = t("person_meldende");
            } else {
                $case["last_entry_date"] = substr($case["conversation"][$j]["date"],0,10);
                $case["last_entry_person"] = get_name_of_person($case["conversation"][$j]["person"], false);
            }
        }
        $klasse = [STATUS_ZU => "abgeschlossen", "offen" => "offen"][$case["status"]] ?? "anderer";
        $case["state_html"] = "<span class='font-status-$klasse'>" . status_text($case["status"]) . "</span>";
        if (illustriert()) {   // badge instead of coloured text; next deadline per § 17 HinSchG
            $klasse = [STATUS_ZU => "fertig", "offen" => "offen"][$case["status"]] ?? "anders";
            $case["state_html"] = "<span class='plakette plakette-$klasse'>" . strip_tags($case["state_html"]) . "</span>";
            // Next team deadline as in the deadline watcher; deletion deadlines and errors are the operator's business.
            $f = $case["status"] !== STATUS_ZU ? fristen($case) : [];
            if (is_array($f) && $f) {
                [$name, $ende, , $falls] = $f[0];
                $tage = (int)$heute->diff($ende)->format("%r%a");
                // First reply may already be the feedback (watcher: „vielleicht“) – then never red
                $ampel = $heute > $ende && !$falls ? "rot" : ($tage <= 14 ? "gelb" : "gruen");
                $label = t($name === FRIST_EINGANG ? "team_frist_eingang" : "team_frist_rueck");
                $case["frist_html"] = "<span class='frist frist-$ampel'>$label " . ($heute > $ende ? t("team_ueberfaellig", -$tage) : t("team_faellig", $ende->format("d.m.Y")))
                    . ($falls ? " – " . t("team_frist_falls", $falls->format("d.m.Y")) : "") . "</span>";
            }
        }
        $user_cases[] = $case;
    }

    // Then sort them by the report's creation date
    usort($user_cases, function ($case1, $case2) {
        return -1 * ($case1['start_date'] <=> $case2['start_date']);
    });

    $antraege = loeschantraege();
    if (isset($_GET["loeschen"])) {
        sammel_loeschen($user_cases, $antraege);
    } else {
        if (empty($user_cases)) { echo "<p>" . t("team_keine") . "</p>\n"; }
        // Order: open cases with their bulk button, then those with a deletion request (collapsed), then the deleted ones
        $beantragt = array_filter($user_cases, fn($c) => isset($antraege[$c["case_id"]]));
        $offen = array_diff_key($user_cases, $beantragt);
        foreach ($offen as $case) meldung_zeile($case, null);
        // Requests by others await this user's confirmation (four eyes): highlighted, the bulk button steps back
        $zustimmen = count(array_filter($beantragt, fn($c) => $antraege[$c["case_id"]]["von"] !== get_auth_user()));
        if ($offen) echo sammel_knopf("sammel_knopf", $zustimmen > 0);
        if ($zustimmen) echo sammel_knopf("bestaetigen_knopf", false, $zustimmen);
        if ($beantragt) {
            echo "<details class='loeschprotokoll'><summary>" . t("antraege_liste", count($beantragt)) . "</summary>";
            foreach ($beantragt as $case) meldung_zeile($case, $antraege[$case["case_id"]]);
            echo "</details>";
        }
        echo loeschprotokoll_html();
    }
}


// Entry to bulk deletion, full width with icon; the confirm variant preselects the cases awaiting confirmation.
function sammel_knopf(string $text, bool $zweitrangig, int $anzahl = 0): string {
    return "<form class='sammel-start' method='get' action=''><input type='hidden' name='loeschen' value='1'>"
        . ($anzahl ? "<input type='hidden' name='bestaetigen' value='1'>" : "")
        . "<button class='volle-breite" . ($zweitrangig ? " zweitrangig" : "") . "' type='submit'>" . symbol("papierkorb") . t($text, $anzahl) . "</button></form>";
}

// One case in the team list.
function meldung_zeile(array $case, ?array $antrag): void {
    $id = $case["case_id"];
    ?>

        <div class="meldung-container">
            <div>
                <span><?=$case['start_day']?> – <?=$id?> – <?=$case['title']?></span>
            </div>
            <div>
                <?=$case["state_html"]?>, <?=$case["last_entry_person"]?>, <?=$case["last_entry_date"]?><?=isset($case["frist_html"]) ? "<br>" . $case["frist_html"] : ""?><?=$antrag ? "<br><span class='frist frist-rot'>" . t("antrag_liste", antrag_text($antrag)[0]) . "</span>" : ""?>
            </div>
            <div class="aktionen">
                <a class="symbolknopf" href="?case_id=<?=$id?>" title="<?=t("team_bearbeiten")?>" aria-label="<?=t("team_bearbeiten")?>"><?=symbol("stift")?></a>
            </div>
        </div>

    <?php
}


// Several cases at once: tick cases, one shared reason for those still in their retention period; review page,
// then execute. Without a request, deletion is requested (reason "test": deleted at once); another person's request is
// confirmed (four eyes).
// Each step rechecks the choice on the server.
function sammel_loeschen(array $user_cases, array $antraege): void {
    $abbrechen = "<form method='get' action=''><div class='partner-button-container'><button class='zweitrangig' type='submit'>" . symbol("zurueck") . t("sammel_abbrechen") . "</button></div></form>";
    $alle = array_column($user_cases, null, "case_id");
    $ich = get_auth_user();
    // Group per case: bestaetigen | frei | grund | (gesperrt | eigener: not selectable)
    $gruppe = function (string $id) use ($alle, $antraege, $ich): string {
        [$art] = loeschbarkeit($alle[$id]);
        $a = $antraege[$id] ?? null;
        return $art === "gesperrt" ? "gesperrt" : (!$a ? $art : ($a["von"] === $ich ? "eigener" : "bestaetigen"));
    };
    $erlaubt = fn(string $g) => ["bestaetigen" => ["bestaetigen"], "frei" => ["frist"], "grund" => LOESCHGRUENDE][$g] ?? [];
    // Selection page posts a checkbox (1) per case plus one shared reason; review page posts the resolved choice.
    $grund = (string)($_POST["grund"] ?? "");
    $wahl = []; $ohne_grund = false;
    foreach ((array)($_POST["wahl"] ?? []) as $id => $w) {
        if (!is_string($id) || !is_string($w) || !isset($alle[$id])) continue;
        $g = $gruppe($id);
        if ($w === "1") $w = ["bestaetigen" => "bestaetigen", "frei" => "frist", "grund" => $grund][$g] ?? "";
        if (in_array($w, $erlaubt($g), true)) $wahl[$id] = $w; elseif ($g === "grund") $ohne_grund = true;
    }
    $schritt = isset($_POST["sammel_ausfuehren"]) ? "ausfuehren" : (isset($_POST["sammel_pruefen"]) ? "pruefen" : "auswahl");
    if ($schritt !== "auswahl" && ($ohne_grund || !$wahl)) { echo warnkasten(t($ohne_grund ? "sammel_grund_fehlt" : "sammel_keine")); $schritt = "auswahl"; }

    if ($schritt === "ausfuehren") {
        $geloescht = 0; $fehler = $antrag_fehler = false; $neu = $tests = [];
        foreach ($wahl as $id => $w) {
            if ($w === "bestaetigen") { if (loeschen_bestaetigen($id)) $geloescht++; else $fehler = true; }
            elseif ($w === "test") { if (test_sofort_loeschen($id, false)) { $geloescht++; $tests[] = $id; } else $fehler = true; }
            elseif (loeschen_beantragen($id, $w, false)) $neu[$id] = $w;
            else $antrag_fehler = true;
        }
        if ($neu) antrag_mail($neu);   // one mail per recipient for the whole run
        if ($tests) test_mail($tests);
        $beantragt = count($neu);
        if ($fehler) echo warnkasten(t("loeschen_fehler"));
        if ($antrag_fehler) echo warnkasten(t("antrag_fehler"));
        echo "<p>" . t("sammel_erledigt", $geloescht, $beantragt) . "</p><p><a href='" . url("/team") . "'>" . t("team_titel") . "</a></p>";
        return;
    }
    echo "<h1>" . t("sammel_titel") . "</h1><form method='post' action='?loeschen=1'>";
    if ($schritt === "pruefen") {
        echo "<p>" . t("sammel_frage") . "</p><ul class='sammelliste pruefliste'>";
        foreach ($wahl as $id => $w) {
            $text = $w === "bestaetigen" ? t("sammel_bestaetigen", antrag_text($antraege[$id])[0]) : t($w === "test" ? "sammel_sofort" : "sammel_beantragen", t("loeschgrund_$w"));
            echo "<li><span class='sammeltitel'>{$alle[$id]['title']}</span><span class='sammelmeta'>$id – $text</span>"
                . "<input type='hidden' name='wahl[$id]' value='$w'></li>";
        }
        echo "</ul>" . knopfpaar("<button name='sammel_ausfuehren' type='submit'>" . symbol("papierkorb")
            . t(array_intersect(["bestaetigen", "test"], $wahl) ? "loeschen_endgueltig" : "loeschen_beantragen") . "</button>", zurueck_in_formular("sammel_abbrechen", url("/team/"))) . "</form>";
    } else {
        $gruppen = ["bestaetigen" => [], "frei" => [], "grund" => []]; $gesperrt = $eigene = 0;
        foreach ($user_cases as $case) {
            $g = $gruppe($case["case_id"]);
            if ($g === "gesperrt") $gesperrt++; elseif ($g === "eigener") $eigene++; else $gruppen[$g][] = $case;
        }
        // Nothing selectable: say why instead of an empty list with a "Next" button
        $ausgelassen = ($gesperrt ? "<li>" . t("sammel_gesperrt", $gesperrt) . "</li>" : "") . ($eigene ? "<li>" . t("sammel_eigene", $eigene) . "</li>" : "");
        if (!array_filter($gruppen)) {
            echo "<p>" . t("sammel_nichts") . "</p>" . ($ausgelassen ? "<ul>$ausgelassen</ul>" : "") . "</form>" . $abbrechen;
            return;
        }
        echo "<p>" . t("sammel_text") . ($gruppen["grund"] ? " " . t("loeschen_test_ausnahme") : "") . "</p>";
        foreach ($gruppen as $g => $faelle) {
            if (!$faelle) continue;
            echo "<h2 class='sammelgruppe'>" . t("sammel_gruppe_$g") . "</h2>";
            if ($g === "grund") {
                $optionen = "<option value=''>" . t("sammel_grund_waehlen") . "</option>";
                foreach (LOESCHGRUENDE as $w) $optionen .= "<option value='$w'" . ($grund === $w ? " selected" : "") . ">" . t("loeschgrund_$w") . "</option>";
                echo "<p><label for='grund'>" . t("sammel_grund") . "</label> <select id='grund' name='grund'>$optionen</select></p>";
            }
            echo "<ul class='sammelliste'>";
            if (count($faelle) > 1) echo "<li class='sammelzeile'><input type='checkbox' id='alle-$g' class='alle'><label for='alle-$g'>" . t("sammel_alle") . "</label></li>";
            foreach ($faelle as $case) {
                $id = $case["case_id"];
                $meta = "{$case['start_day']} · $id" . ($g === "bestaetigen" ? " · " . t("antrag_liste", antrag_text($antraege[$id])[0]) . " (" . antrag_text($antraege[$id])[2] . ")" : "");
                // On first load, expired ones are preselected (via the confirm button: those awaiting confirmation); after an error, keep what was ticked
                $an = $_POST ? isset($_POST["wahl"][$id]) : $g === (isset($_GET["bestaetigen"]) ? "bestaetigen" : "frei");
                echo "<li class='sammelzeile'><input type='checkbox' id='w-$id' name='wahl[$id]' value='1'" . ($an ? " checked" : "") . ">"
                    . "<label for='w-$id'>{$case['title']} <span class='sammelmeta'>($meta)</span></label></li>";
            }
            echo "</ul>";
        }
        // „Mark all“ per group; ticking a case by hand keeps the group box in step
        echo "<script>document.querySelectorAll('.sammelliste').forEach(ul => { const alle = ul.querySelector('.alle'); if (!alle) return;
            const faelle = [...ul.querySelectorAll('input[name^=wahl]')], sync = () => alle.checked = faelle.every(f => f.checked);
            alle.onchange = () => faelle.forEach(f => f.checked = alle.checked); faelle.forEach(f => f.onchange = sync); sync(); });</script>";
        if ($ausgelassen) echo "<div class='sammelmeta'>" . t("sammel_nicht_hier") . "<ul>$ausgelassen</ul></div>";
        echo knopfpaar("<button name='sammel_pruefen' type='submit'>" . symbol("weiter") . t("sammel_weiter") . "</button>", zurueck_in_formular("sammel_abbrechen", url("/team/"))) . "</form>";
    }
}

// Waiting accesses (registration, password reset): a full member approves or refuses. Approval wraps all reports
// both may see for the new key. Refusal deletes a registration and locks a reset access.
function freigaben_bearbeiten(): void {
    if (!darf_freigeben(get_auth_user())) return;
    $wartend = array_filter(alle_benutzer(), fn($b) => ($b["status"] ?? "") === "wartet");
    $adr = (string)($_POST["freigeben"] ?? $_POST["ablehnen"] ?? "");
    if ($adr !== "" && isset($wartend[strtolower($adr)])) {
        $b = $wartend[strtolower($adr)];
        $neu = isset($b["pw"]);   // registration: not yet in passwd
        if (isset($_POST["freigeben"])) {
            // Without the approver's key pair the reports could not be wrapped for the new member: approve nothing
            if (!($paar = paar_der_sitzung())) { echo warnkasten(t("wa_anmelden")); return; }
            // null: not saved, or approved by someone else meanwhile (then wrap anyway, but send no second mail)
            $frisch = zugang_freigeben($b["email"]);
            if (!$frisch && (benutzer_laden($b["email"])["status"] ?? "") !== "aktiv") { echo warnkasten(t("speichern_fehler")); return; }
            verpackungen_ergaenzen($paar);
            if ($frisch) freigabe_mail($frisch);
            echo bestaetigung(t("freigabe_erledigt", htmlspecialchars($b["email"])));
        } else {
            if ($neu) benutzer_loeschen($b["email"]);
            else { $b["status"] = "gesperrt"; if (!passwd_sperren($b["email"]) || !benutzer_speichern($b)) { echo warnkasten(t("speichern_fehler")); return; } }
            echo "<p>" . t("freigabe_abgelehnt", htmlspecialchars($b["email"])) . "</p>";
        }
        unset($wartend[strtolower($adr)]);
    }
    if (!$wartend) return;
    echo "<section class='loeschbereich'><h2>" . t("freigabe_titel") . "</h2><p>" . t("freigabe_text") . "</p><ul class='sammelliste'>";
    foreach ($wartend as $b) {
        $e = htmlspecialchars($b["email"], ENT_QUOTES);
        echo "<li><span class='sammeltitel'>$e</span><span class='sammelmeta'>" . t(isset($b["pw"]) ? "freigabe_neu" : "freigabe_reset") . "</span>"
            . "<form method='post' action=''><div class='partner-button-container'><button name='freigeben' value='$e'>" . symbol("weiter") . t("freigabe_knopf") . "</button>"
            . "<button class='zweitrangig' name='ablehnen' value='$e'>" . symbol("ablehnen") . t("freigabe_ablehnen") . "</button></div></form></li>";
    }
    echo "</ul></section>";
}

// Account line at the end, so the reports come first
if (portal_anmeldung_aktiv()) echo kontozeile(url("/team/konto/"), t("konto_titel"));

include_once(__DIR__."/../common/footer.php");
