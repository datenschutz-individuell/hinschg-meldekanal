<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/../common/helper.php");
include_once(__DIR__."/../common/crypto.php");


function load_case_data($case_id): Array {
    $expected_case_id_length = CASE_ID_LENGTH;
    $case_id_length = strlen($case_id);
    if($case_id_length != $expected_case_id_length) {
        echo "<p>".t("id_laenge", $expected_case_id_length, htmlspecialchars($case_id), $case_id_length)."</p>";
        return [];
    }

    $case_file_path = get_case_file_path($case_id);
    $m = load_json_from_file($case_file_path, "Meldung existiert nicht: " . $case_id);
    if ($m) { $m["status"] ??= ""; $m["id"] = $case_id; }   // the file name is the ID
    return $m;
}


/** Changes a case on its current file content: $aendern gets the case and returns the new one, or null to leave it
 *  unchanged. Returns the case as stored, or null if it is gone, unreadable or could not be written. */
function fall_aendern(string $case_id, callable $aendern): ?array {
    return unter_fallsperre(function () use ($case_id, $aendern) {
        $m = load_case_data($case_id);
        if (!$m) return null;
        $neu = $aendern($m);
        if ($neu === null) return $m;
        return fall_schreiben($case_id, $neu) ? $neu : null;
    });
}


/** Sets the status with a line in the conversation; false if it could not be saved. */
function update_case_status_if_changed($case_id, &$case_data, $status): bool {
    if ($status == $case_data["status"]) return true;
    $geaendert = $verfallen = $gerufen = false;
    $neu = null;
    // A pending deletion request was based on the old status and lapses with the change. Request lock first, case
    // lock second (as in loeschen_bestaetigen), so no request can be filed in between.
    $aendern = function () use ($case_id, $status, &$geaendert) {
        return fall_aendern($case_id, function ($m) use ($status, &$geaendert) {
            if ($m["status"] === $status) return null;   // someone else set it meanwhile
            $m["status"] = $status;
            $m["conversation"][] = ["person" => get_auth_user(), "date" => german_date(), "status" => $status];
            $geaendert = true;
            return $m;
        });
    };
    loeschantraege(function ($a) use ($case_id, $aendern, &$geaendert, &$verfallen, &$neu, &$gerufen) {
        $gerufen = true;
        $neu = $aendern();
        if ($neu && $geaendert && isset($a[$case_id])) { unset($a[$case_id]); $verfallen = true; }
        return $a;
    });
    if (!$gerufen) $neu = $aendern();   // request file broken: still change the status, there is no request to lapse
    if (!$neu) return false;
    $case_data = $neu;
    if ($verfallen) team_benachrichtigen([$case_id], "Löschantrag verfallen", fn() => "Der Antrag, Meldung $case_id zu löschen, gilt nicht mehr. Der Status hat sich geändert.");
    if ($geaendert) inform_stakeholders($case_data);
    return true;
}


// Team deletes a case: after the retention period (§ 11 Abs. 5 HinSchG) with reason "frist",
// before that only what is no real report (reason from LOESCHGRUENDE); never while in progress ("offen"). Four
// eyes: one person requests (<kunde>/deletion-requests.json), another confirms; reason "test" one person alone, the
// others get a mail. Each deletion is logged without content in <kunde>/deletions.log (append only): received, ID,
// deletion date, reason – no names: the procedure ensures that two people agreed or were told.
const LOESCHANTRAEGE = "deletion-requests.json";
const LOESCHGRUENDE = ["spam", "test", "doppelt", "leer"];   // text per reason: loeschgrund_<grund>

/** Whether and how a case may be deleted: ["gesperrt"|"frei"|"grund", end of retention or null]. */
function loeschbarkeit(array $m): array {
    if (!in_array($m["status"] ?? "", ["unbearbeitet", STATUS_ZU], true)) return ["gesperrt", null];
    $bis = aufbewahrung_bis($m);
    return [$bis && heute() > $bis ? "frei" : "grund", $bis];
}

/** Valid reason from the form, or null. */
function loeschgrund_aus_formular(): ?string {
    return in_array($_POST["loeschgrund"] ?? "", LOESCHGRUENDE, true) ? $_POST["loeschgrund"] : null;
}

function loeschgrund_auswahl(bool $pflicht, string $label = "loeschen_grund_feld"): string {
    $optionen = "<option value=''>" . t("loeschen_grund_waehlen") . "</option>";
    foreach (LOESCHGRUENDE as $g) $optionen .= "<option value='$g'" . (($_POST["loeschgrund"] ?? "") === $g ? " selected" : "") . ">" . t("loeschgrund_$g") . "</option>";
    return "<label class='loeschgrund'><span>" . t($label) . "</span><select name='loeschgrund'" . ($pflicht ? " required" : "") . ">$optionen</select></label>";
}

/** Reads deletion requests, changes them under a lock: $aendern gets all requests and returns the new ones (null = read only). */
function loeschantraege(?callable $aendern = null): array {
    $h = fopen(KUNDE_DIR . "/" . LOESCHANTRAEGE, "c+");
    if (!$h || !flock($h, $aendern ? LOCK_EX : LOCK_SH)) { error_log("Löschanträge nicht lesbar"); return []; }
    $roh = stream_get_contents($h);
    $a = $roh === "" ? [] : json_decode($roh, true);
    if (!is_array($a)) { error_log("Löschanträge unlesbar: " . KUNDE_DIR); flock($h, LOCK_UN); fclose($h); return []; }
    if ($aendern && ($neu = $aendern($a)) !== $a) { $a = $neu; ftruncate($h, 0); rewind($h); fwrite($h, json_encode($a)); fflush($h); }
    flock($h, LOCK_UN); fclose($h);
    return $a;
}

/** The request as readable text: [name, date, reason text]. */
function antrag_text(array $antrag): array {
    return [get_name_of_person($antrag["von"], false), date("d.m.Y", strtotime($antrag["datum"])), t("loeschgrund_" . $antrag["grund"])];
}

/** Mail to the other authorised users (not to "keine"); never the report's content. */
function team_benachrichtigen(array $case_ids, string $betreff, callable $text): void {
    // One mail per recipient listing the cases they may see; $text gets that list
    $domain = setting("Domain");
    foreach (passwd_eintraege() as $adr => $f) {
        // Deletion needs a second person to act, so only "keine" opts out (not "Fälle", unlike inform_stakeholders)
        if ($adr === strtolower(get_auth_user()) || $f[3] === "keine") continue;
        $ihre = array_values(array_filter($case_ids, fn($id) => is_role_authorized_for_case($f[2], $id)));
        if (!$ihre) continue;
        mail_text($f[0], (count($ihre) === 1 ? "$ihre[0] " : "") . $betreff, $text($ihre) . " Bitte <a href=\"https://$domain/team\">melden Sie sich an</a>.");
    }
}

/** Files a request; false if one exists already, the case is (no longer) deletable for this reason, or on error. */
function loeschen_beantragen(string $case_id, string $grund, bool $mail = true): bool {
    $neu = false;
    loeschantraege(function ($a) use ($case_id, $grund, &$neu) {
        // Current status, not the one shown on the page. Status changes hold the request lock, so it stays valid.
        $art = loeschbarkeit(load_case_data($case_id) ?: ["status" => ""])[0];
        if (isset($a[$case_id]) || $art === "gesperrt" || ($grund === "frist" && $art !== "frei")) return $a;
        $a[$case_id] = ["von" => get_auth_user(), "datum" => german_date(), "grund" => $grund];
        $neu = true;
        return $a;
    });
    if ($neu && $mail) antrag_mail([$case_id => $grund]);
    return $neu;
}

/** Test reports need no second person: deleted at once without a request, logged as usual; refused while a request
 *  exists (that one goes through four eyes). Without $mail the caller sends test_mail() for the whole run. */
function test_sofort_loeschen(string $case_id, bool $mail = true): bool {
    $ok = loeschen_bestaetigen($case_id, true);
    if ($ok && $mail) test_mail([$case_id]);
    return $ok;
}

/** Tells the others about deletions without a second person, so misuse cannot go unnoticed. */
function test_mail(array $ids): void {
    $wer = get_name_of_person(get_auth_user(), false);
    team_benachrichtigen($ids, "Test-Meldung gelöscht", fn($ihre) => "$wer hat " . (count($ihre) === 1 ? "Meldung $ihre[0]" : count($ihre) . " Meldungen (" . implode(", ", $ihre) . ")")
        . " als Test gelöscht, ohne zweite Person. Das Löschprotokoll nennt sie mit dem Grund Test.");
}

/** Lines of the deletion log the logged-in person may see: [received, ID, deleted, reason]. */
function loeschprotokoll_zeilen(): array {
    $alle = [];
    foreach (@file(KUNDE_DIR . "/" . LOESCHPROTOKOLL, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $z) {
        $f = explode("\t", $z);
        if (!str_starts_with($z, "#") && count($f) >= 4 && is_current_user_authorized_for_case($f[1])) $alle[] = $f;
    }
    return $alle;
}

/** Mail about new deletion requests [ID => reason], one per recipient. */
function antrag_mail(array $antraege): void {
    $wer = get_name_of_person(get_auth_user(), false);
    team_benachrichtigen(array_keys($antraege), "Löschung beantragt", fn($ids) => count($ids) === 1
        ? "$wer möchte Meldung $ids[0] löschen (" . t("loeschgrund_" . $antraege[$ids[0]]) . "). Eine zweite Person muss zustimmen oder ablehnen."
        : "$wer möchte " . count($ids) . " Meldungen löschen: " . implode(", ", array_map(fn($id) => "$id (" . t("loeschgrund_" . $antraege[$id]) . ")", $ids))
          . ". Eine zweite Person muss zustimmen oder ablehnen.");
}

/** Removes a pending request; true if there was one. */
function antrag_entfernen(string $case_id): bool {
    $da = false;
    loeschantraege(function ($a) use ($case_id, &$da) { $da = isset($a[$case_id]); unset($a[$case_id]); return $a; });
    return $da;
}

/** Confirms another person's request and deletes; $test: no request, reason "test", only while none exists.
 *  Log first, then delete: better one entry too many than a deletion without a trace. */
function loeschen_bestaetigen(string $case_id, bool $test = false): bool {
    $ok = false;
    // Under the request lock, so two people confirming at once delete and log only once; under the case lock
    // (always taken second), so no change of status slips in between check and deletion
    loeschantraege(function ($a) use ($case_id, $test, &$ok) { return unter_fallsperre(function () use ($a, $case_id, $test, &$ok) {
        $antrag = $test ? (isset($a[$case_id]) ? null : ["von" => "", "grund" => "test"]) : $a[$case_id] ?? null;
        $m = load_case_data($case_id);
        if (!$m) { if (!file_exists(get_case_file_path($case_id))) unset($a[$case_id]); return $a; }   // case gone: drop orphaned request
        $art = loeschbarkeit($m)[0];
        if (!$antrag || (!$test && $antrag["von"] === get_auth_user()) || $art === "gesperrt" || ($antrag["grund"] === "frist" && $art !== "frei")) return $a;
        if (!loeschung_protokollieren(KUNDE_DIR, $m, $case_id, $antrag["grund"]) || !unlink(get_case_file_path($case_id))) return $a;
        temp_reste_entfernen(KUNDE_DIR);
        unset($a[$case_id]);
        $ok = true;
        return $a;
    }) ?? $a; });
    return $ok;
}

function zurueck_knopf(string $text = "loeschen_zurueck"): string {
    return "<form class='zurueck' method='post' action=''><button class='zweitrangig' type='submit'>" . symbol("zurueck") . t($text) . "</button></form>";
}

/** Deletion section at the bottom of the case: set apart, the reason is chosen here; the next page
 *  has only the red button. Empty while the case is in progress. */
function loeschbereich_html(string $case_id, array $case_data): string {
    [$art, $bis] = loeschbarkeit($case_data);
    if ($art === "gesperrt") return "";
    $antrag = loeschantraege()[$case_id] ?? null;
    $h = "<section class='loeschbereich' id='loeschen'><h2>" . t("loeschen_knopf") . "</h2><form method='post' action='#loeschen'>";
    if ($antrag && $antrag["von"] === get_auth_user()) {
        [, $datum, $grundtext] = antrag_text($antrag);
        $h .= "<p>" . t("antrag_eigener", $datum, $grundtext) . "</p><div class='partner-button-container'>"
            . "<button name='antrag_zurueckziehen' type='submit'>" . symbol("zurueck") . t("antrag_zurueckziehen") . "</button></div>";
    } elseif ($antrag) {
        [$name, $datum, $grundtext] = antrag_text($antrag);
        $h .= "<p>" . t("antrag_fremd", $name, $datum, $grundtext) . "</p><div class='partner-button-container'>"
            . "<button name='vorgang_loeschen' type='submit'>" . symbol("papierkorb") . t("antrag_bestaetigen") . " …</button>"
            . "<button name='antrag_ablehnen' type='submit'>" . symbol("ablehnen") . t("antrag_ablehnen") . "</button></div>";
    } else {
        $h .= "<p>" . ($art === "frei" ? t("loeschen_frei", $bis->format("d.m.Y")) : ($bis ? t("loeschen_frist_laeuft", $bis->format("d.m.Y")) : t("loeschen_offen")))
            . " " . t("antrag_hinweis") . ($art === "frei" ? "" : " " . t("loeschen_test_ausnahme")) . "</p>" . ($art === "frei" ? "" : loeschgrund_auswahl(true))
            . "<div class='partner-button-container'><button name='vorgang_loeschen' type='submit'>" . symbol("papierkorb") . t("loeschen_beantragen") . " …</button></div>";
    }
    return $h . "</form></section>";
}

/** Deletion actions; null = no page of its own, show the case as usual (possibly with a warning). */
function vorgang_loeschen(string $case_id, array $case_data): ?bool {
    [$art] = loeschbarkeit($case_data);
    $antrag = loeschantraege()[$case_id] ?? null;
    $eigener = $antrag && $antrag["von"] === get_auth_user();
    $zur_liste = "<p><a href='" . url("/team") . "'>" . t("team_titel") . "</a></p>";
    $kopf = "<p class='hinweis-titel'>" . t("meldungs_id", $case_id) . "</p><h1>" . $case_data["title"] . "</h1>";

    if ($antrag && !$eigener && isset($_POST["loeschen_bestaetigen"])) {
        if (!loeschen_bestaetigen($case_id)) { echo warnkasten(t("loeschen_fehler")); return true; }
        echo "<p>" . t("loeschen_erledigt", $case_id) . "</p>$zur_liste";
        return true;
    }
    if ($antrag && (isset($_POST["antrag_ablehnen"]) && !$eigener || isset($_POST["antrag_zurueckziehen"]) && $eigener)) {
        antrag_entfernen($case_id);
        if (!$eigener) team_benachrichtigen([$case_id], "Löschantrag abgelehnt", fn() => get_name_of_person(get_auth_user(), false)
            . " hat abgelehnt, Meldung $case_id zu löschen. Die Meldung bleibt erhalten.");
        echo warnkasten(t($eigener ? "antrag_zurueckgezogen" : "antrag_abgelehnt"));
        return null;
    }
    if ($art === "gesperrt") { echo warnkasten(t("loeschen_gesperrt")); return null; }
    if ($antrag) {
        if ($eigener) return null;
        // Confirmation page: only the red button
        [$name, $datum, $grundtext] = antrag_text($antrag);
        echo $kopf . "<div class='loeschhinweis'><p>" . t("loeschen_ablauf_bestaetigen", $name, $datum, $grundtext) . "</p></div>"
            . knopfpaar("<form method='post' action=''><button class='gefahr' name='loeschen_bestaetigen' type='submit'>"
            . symbol("papierkorb") . t("loeschen_endgueltig") . "</button></form>", zurueck_knopf());
        return true;
    }
    $grund = $art === "frei" ? "frist" : loeschgrund_aus_formular();
    if (!$grund) { echo warnkasten(t("loeschen_grund_fehlt")); return null; }
    if (isset($_POST["loeschen_beantragen"]) && $grund === "test") {
        if (!test_sofort_loeschen($case_id)) { echo warnkasten(t("loeschen_fehler")); return null; }   // back to the case: shows a request filed meanwhile
        echo "<p>" . t("loeschen_erledigt", $case_id) . "</p>$zur_liste";
        return true;
    }
    if (isset($_POST["loeschen_beantragen"])) {
        echo loeschen_beantragen($case_id, $grund) ? "<p>" . t("antrag_gestellt", $case_id) . "</p>$zur_liste" : warnkasten(t("antrag_fehler"));
        return true;
    }
    echo $kopf . "<div class='loeschhinweis'><p>" . ($grund === "test" ? t("loeschen_ablauf_test") : t("loeschen_ablauf_antrag", t("loeschgrund_$grund"))) . "</p></div>"
        . knopfpaar("<form method='post' action=''><input type='hidden' name='loeschgrund' value='$grund'>"
        . "<button class='gefahr' name='loeschen_beantragen' type='submit'>" . symbol("papierkorb") . ($grund === "test" ? t("loeschen_endgueltig") : t("loeschen_beantragen_mit_grund", t("loeschgrund_$grund"))) . "</button></form>", zurueck_knopf());
    return true;
}


// Deletion log for the team overview; only lines for cases the logged-in person is authorised for.
function loeschprotokoll_html(): string {
    $zeilen = [];
    foreach (loeschprotokoll_zeilen() as $f) {
        $zeilen[] = "<li>" . htmlspecialchars(t("loeschen_protokoll_zeile", date("d.m.Y", strtotime($f[0])), $f[1], date("d.m.Y", strtotime($f[2])), t("loeschgrund_" . $f[3]))) . "</li>";
    }
    if (!$zeilen) return "";
    return "<details class='loeschprotokoll'><summary>" . t("loeschen_protokoll", count($zeilen)) . "</summary><ul>"
        . implode("", array_reverse($zeilen)) . "</ul></details>";
}


/** Data key of the viewer: for the team from the session, for the reporting person from the token secret.
 *  $paar: the session's key pair if already at hand (team list: fetched once, not per case). */
function viewer_key(array $m, bool $as_team, ?string $token_geheim, ?string $paar = null): ?string {
    if (!ist_verschluesselt($m)) return null;
    if (!$as_team) return $token_geheim !== null ? fallschluessel_token($m, $token_geheim) : null;
    $paar ??= function_exists("paar_der_sitzung") ? paar_der_sitzung() : null;
    return $paar ? fallschluessel_mitglied($m, $paar) : null;
}

function display_case($case_id, $as_team, ?string $token_geheim = null): bool
{
    if (!$as_team && isset($_POST['invalidate_token'])) {
        ?>
        <p><?=t("invalid_frage1")?></p>
        <p><?=t("invalid_frage2")?></p>
        <form action="#chat-input" method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_POST['token'] ?? '');?>">
            <div class="partner-button-container knopfpaar">
                <button name="confirmed_invalidate_token" type="submit"><?=symbol("schloss")?><?=t("invalid_endgueltig")?></button>
                <button class="zweitrangig zurueck" type="submit"><?=symbol("zurueck")?><?=t("invalid_zurueck")?></button>
            </div>
        </form>
        <?php
        return true;
    }

    $case_data = load_case_data($case_id);
    if (empty($case_data)) return false;

    // Reporter only.
    if (!$as_team && isset($_POST['confirmed_invalidate_token'])) {
        // New token: same case_id, new secret
        $case_data = fall_aendern($case_id, function ($m) {
            // Encrypted: drop the reporter's wrapping and verifier; plain: a new secret nobody knows
            if (ist_verschluesselt($m)) unset($m["keys"]["melder"], $m["verifier"]);
            else $m["token"] = get_case_id_from_token($m["token"]) . generate_random_string(16);
            $m["conversation"][] = ["person" => "meldende_person", "date" => german_date(), "action" => "invalidate_token"];
            return $m;
        });
        if (!$case_data) { echo warnkasten(t("speichern_fehler")); return true; }
        inform_stakeholders($case_data);

        echo "<p>".t("invalid_erledigt")."</p>";
        return true;
    }

    if ($as_team && !is_current_user_authorized_for_case($case_id)) {
        echo "<p>".t("keine_berechtigung")."</p>";
        return false;
    }

    $k = viewer_key($case_data, $as_team, $token_geheim);
    if (ist_verschluesselt($case_data) && !$k) {
        echo "<p class='hinweis-titel'>" . t("meldungs_id", $case_id) . "</p>" . warnkasten(t($as_team ? "krypt_nicht_freigegeben" : "token_secret"));
        return true;
    }

    $loeschaktionen = ["vorgang_loeschen", "loeschen_beantragen", "loeschen_bestaetigen", "antrag_ablehnen", "antrag_zurueckziehen"];
    if ($as_team && array_intersect($loeschaktionen, array_keys($_POST)) && ($r = vorgang_loeschen($case_id, fall_klartext($case_data, $k))) !== null) return $r;

    $posted_status = htmlspecialchars($_POST['case_status'] ?? "");

    // First team action opens the case; checked first so that line always leads the conversation.
    if ($as_team && $posted_status && ($posted_status == "offen" || $case_data["status"] == "unbearbeitet")
        && !update_case_status_if_changed($case_id, $case_data, "offen")) {
        // No further status or team message without the opening line (acknowledgement deadline hangs on it)
        echo warnkasten(t("speichern_fehler"));
        if (trim($_POST['added_text'] ?? "")) echo "<p class='chat-text'>" . standardise_newlines(htmlspecialchars($_POST['added_text'])) . "</p>";
        $posted_status = "";
        $_POST['added_text'] = "";
    }

    if (isset($_POST['added_text']) && trim($_POST['added_text'])) {
        $added_text = standardise_newlines(htmlspecialchars($_POST['added_text']));
        $person = $as_team ? get_auth_user() : "meldende_person";
        $doppelt = $zu = $ohne_schluessel = false;
        $neu = fall_aendern($case_id, function ($m) use ($person, $added_text, &$k, $as_team, $token_geheim, &$doppelt, &$zu, &$ohne_schluessel) {
            if ($m["status"] === STATUS_ZU) { $zu = true; return null; }   // the form hides the field, the server must refuse too
            // Encrypted since it was read (first member login wraps plain cases): fetch the key again
            if (ist_verschluesselt($m) && !$k && !($k = viewer_key($m, $as_team, $token_geheim))) { $ohne_schluessel = true; return null; }
            // Same text as the last message by the same person: most likely a resent form
            $m["conversation"] ??= [];
            $last = end($m["conversation"]) ?: [];
            $last_text = isset($last["text"]) && ist_verschluesselt($m) ? sym_entschluesseln($k, $last["text"]) : ($last["text"] ?? "");
            if ($person == ($last["person"] ?? null) && $added_text == $last_text) { $doppelt = true; return null; }
            $m["conversation"][] = ["person" => $person, "date" => german_date(), "text" => text_speichern($m, $k, $added_text)];
            return $m;
        });
        if ($neu) $case_data = $neu;   // also when nothing was saved: show the current state
        if ($zu) {
            echo warnkasten(t("nachricht_zu")) . "<p class='chat-text'>$added_text</p>";
            $posted_status = "";   // a status change in the same form must not reopen the case behind the refusal
        }
        elseif ($doppelt) echo "<p>".t("nachricht_doppelt")."</p>";
        elseif (!$neu || $ohne_schluessel) echo warnkasten(t("speichern_fehler")) . "<p class='chat-text'>$added_text</p>";
        else inform_stakeholders($case_data);
    }

    // Manual status change by the team ("offen" is handled above); in_array also rejects forged values.
    if ($as_team && $posted_status != "offen" && in_array($posted_status, setting("Stati")) ) {
        if (!update_case_status_if_changed($case_id, $case_data, $posted_status)) echo warnkasten(t("speichern_fehler"));
    }

    // Encrypted meanwhile (first member login wraps plain cases): fetch the key for the current state
    if (ist_verschluesselt($case_data) && !$k && !($k = viewer_key($case_data, $as_team, $token_geheim))) {
        echo "<p class='hinweis-titel'>" . t("meldungs_id", $case_id) . "</p>" . warnkasten(t($as_team ? "krypt_nicht_freigegeben" : "token_secret"));
        return true;
    }
    $case_data = fall_klartext($case_data, $k);
    print("<p class='hinweis-titel'>".t("meldungs_id", $case_id)."</p>");
    print("<h1>".$case_data["title"]."</h1>");

    echo "<div class='chat-system chat-grenze'><span>".t("fall_start", substr($case_data["conversation"][0]["date"] ?? "", 0, 16))."</span></div>";
    for($i = 0; $i < count($case_data["conversation"]); ++$i) {
        $chat_person      = $case_data["conversation"][$i]["person"];
        $chat_person_name = get_name_of_person($chat_person, !$as_team);
        $chat_date        = substr($case_data["conversation"][$i]["date"],0,16);
        $chat_text        = $case_data["conversation"][$i]["text"] ?? "";
        $chat_status      = $case_data["conversation"][$i]["status"] ?? "";
        $chat_action      = $case_data["conversation"][$i]["action"] ?? "";

        // If chat_status is also set (invalid), the message wins
        if ($chat_text) {
            $css_class = $chat_person === "meldende_person" ? "chat-meldende-person" : "chat-team";
            ?>

            <div class="<?=$css_class?>">
                <div>
                    <span><?=$chat_date?> - <?=$chat_person_name?></span>
                </div>
                <div>
                    <p class="chat-text"><?=$chat_text?></p>
                </div>
            </div>

            <?php
        }
        elseif ($chat_status) {
            echo "<div class='chat-system'><span>".t("status_zeile", status_text($chat_status), $chat_person_name, $chat_date)."</span></div>";
        }
        elseif ($chat_person == "meldende_person" && $chat_action == "invalidate_token") {
            echo "<div class='chat-system'><span>".t("invalid_verlauf", $chat_date)."</span></div>";
        }
        else {
            echo "<div class='chat-system'><span>".t("eintrag_fehler")."</span></div>";
        }
    }
    echo "<div class='chat-system chat-grenze'><span>".t("fall_ende", german_date("Y-m-d H:i"))."</span></div>";

    ?>
    <a id="chat-input"></a><br class="neue-zeile">

    <form action="#chat-input" method="post">

        <input type="hidden" name="token" value="<?= htmlspecialchars($_POST['token'] ?? '');?>">

        <?php
        if ($case_data["status"] != "geschlossen") {
        ?>

            <label>
                <textarea class="volle-breite abstand platz-fuer-hinweis" rows="10" name="added_text" aria-label="<?=t("ergaenzung_platz")?>" placeholder="<?=t("ergaenzung_platz")?>" maxlength="2000" <?=remaining_signs_hint()?>></textarea>
            </label>

        <?php
        }

        if ($as_team) {
            ?>

            <label class="label-rechts">
                <select class="volle-breite abstand" name="case_status" required aria-label="<?=t("status_feld")?>">

                    <?php
                    $stati = setting("Stati");
                    for($i = 0; $i < count($stati); ++$i) {
                        $status = $stati[$i];
                        $html_attributes = $status == $case_data["status"] ? "selected='selected'" : "";
                        echo "<option value='$status' $html_attributes>" . status_text($status) . "</option>";
                    }
                    ?>

                </select>
            </label>

            <?php
        }

        if ($as_team || $case_data["status"] != "geschlossen") {
            echo "<button type='submit' class='volle-breite abstand'>".symbol($as_team ? "firma" : "senden").t("nachricht_absenden")."</button>";
        }
        if (!$as_team) {
            echo "<button class='volle-breite abstand' name='invalidate_token' type='submit'>".symbol("schloss").t("invalid_knopf")."</button>";
        }
        ?>

    </form>

    <?php
    if ($as_team) {
        echo loeschbereich_html($case_id, $case_data);
        echo "<br class='neue-zeile'><br>";
        echo "<a href='" . url("/team") . "'><span>" . t("team_titel") . "</span></a>";
    }
    elseif ($case_data["status"] == "geschlossen") {
        echo "<br class='neue-zeile'><br>";
        echo "<p>".t("abgeschlossen")."</p>";
    }

    return true;
}
