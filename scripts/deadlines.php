#!/usr/bin/env php
<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Deadline watcher of the whistleblowing portals (daily via systemd timer). Deadlines:
//   Acknowledgement      7 days after receipt                       § 17 Abs. 1 Nr. 1 HinSchG
//   Feedback             3 months after acknowledgement;            § 17 Abs. 2 HinSchG
//                        without one, 3 months and 7 days after receipt
//   Deletion             3 years after closing                      § 11 Abs. 5 HinSchG
// A report counts as acknowledged with the team's first entry (message or status). The first team message
// may already be the feedback – the script cannot judge that, so it reminds with "maybe"; feedback is certain
// with the second team message or a closing. Every status except "geschlossen" counts as open (portals may
// define their own). Once the token is invalidated, both team deadlines lapse.
// Deadline end per §§ 187 f. BGB (capped at month end).
//
// Deletion: the operator deletes. 30 days before, the script announces it to the team (veto by reply until the
// deadline); it falls due no earlier than 30 days after the announcement actually sent (first run, timer outage).
// After that it reminds only the operator's own team members (addresses @FRISTEN_INTERN) weekly until deleted;
// without FRISTEN_INTERN, or if none is in the team, the reminder goes to FRISTEN_AN.
//
// Aufruf:
//   deadlines.php [--mail] [--kunde KUNDE] [WURZEL]
//     --kunde      nur dieses Portal, z. B. je Portal als dessen PHP-Pool-Benutzer (systemd-Vorlage, README)
//     ohne --mail  Bericht auf stdout. Exit 0 = nichts, 1 = Fristen, 2 = Fehler.
//     --mail       Mails je Frist beim Erreichen einer Stufe, nach Ablauf wöchentlich – nur an die für die
//                  Meldung Berechtigten aus <kunde>/passwd, außer wer „keine“ Benachrichtigung will. An
//                  $FRISTEN_AN (Betreiber, zugleich Reply-To) geht nur, was niemanden erreicht, und FEHLER.
//   deadlines.php --loeschen KUNDE ID [WURZEL]         löschen (nur abgeschlossen und Frist abgelaufen) und dem
//                                                    Team die Löschbestätigung schicken
//   deadlines.php --aufschub KUNDE ID TT.MM.JJJJ [WURZEL]  Veto: Löschung frühestens nach diesem Tag
// Stand, Ankündigungen und Aufschübe in $STATE_DIRECTORY, sonst mit KUNDE in /var/lib/hinweise-deadlines/<kunde>,
// falls vorhanden, sonst im Verzeichnis von $FRISTEN_STAND, sonst in /var/lib/hinweise-deadlines.
// FRISTEN_BEFEHL: Aufruf dieses Skripts in den Mails an den Betreiber (Standard hinweise-deadlines; %k = Portal). Fehlende
// Variablen liest das Skript aus /etc/hinweise-deadlines.env (KEY=VALUE, auch EnvironmentFile der Unit).
// WURZEL: Verzeichnis mit den Portalen, sonst $HINWEISE_WURZEL; je Portal <kunde>/cases/*.json. Absenderdomain
// wie im Portal: "Domain" in <kunde>/portal/settings.json, sonst <kunde>.<Domain-Endung> aus WURZEL/settings.json.
// Inhalte von Meldungen gehen nie in Bericht oder Mail – nur Portal, Meldungs-ID und Daten.

// Deadline calculation is shared with the team page. Installed, it lives root-owned next to the script (copied
// together with it, README), not in _core, which the web server can write.
require is_file(__DIR__ . "/../portal/common/deadlines.php") ? __DIR__ . "/../portal/common/deadlines.php" : "/usr/local/lib/hinweise-deadlines/deadlines.php";

const VORLAUF = 2;                 // days before a team deadline ends
const WIEDERHOLUNG = 7 * 86400;    // after expiry
const UMGEBUNG = "/etc/hinweise-deadlines.env";
const NICHT_WIEDERHOLT = ["läuft ab", "angekündigt"];
const TOLERANZ = 12 * 3600;        // timer jitter and DST: don't push the repeat to day 8

/** Reads a JSON file, e.g. a case; the portal replaces case files atomically, so an unreadable one is really broken. */
function lesen(string $datei): ?array {
    $m = json_decode((string)@file_get_contents($datei), true);
    return is_array($m) ? $m : null;
}

/** Portal this run is limited to (--kunde, --loeschen, --aufschub); null = all. */
function nur_portal(?string $kunde = null): ?string {
    static $p = null;
    return $p = $kunde ?? $p;
}

/** Per-portal state before FRISTEN_STAND: per-portal units and manual calls (--kunde) share one environment
 *  file, a FRISTEN_STAND there must neither merge their states nor hide the timer's from manual calls. */
function standdatei(): string {
    if ($d = getenv("STATE_DIRECTORY")) return "$d/state.json";
    // Per-portal pools can't share one state folder; it stays shared where the subfolder doesn't exist.
    $d = "/var/lib/hinweise-deadlines";
    if (($k = nur_portal()) && is_dir("$d/$k")) return "$d/$k/state.json";
    return getenv("FRISTEN_STAND") ?: "$d/state.json";
}

function zustand_verzeichnis(): string {
    return dirname(standdatei());
}

/** State file ["kunde/id" => "Y-m-d"]; missing: []; broken: null – then delete nothing. */
function zustand(string $name): ?array {
    $datei = zustand_verzeichnis() . "/$name.json";
    if (!file_exists($datei)) return [];
    $d = json_decode((string)@file_get_contents($datei), true);
    return is_array($d) ? $d : null;
}

function zustand_schreiben(string $name, array $daten): bool {
    return atomar_schreiben(zustand_verzeichnis() . "/$name.json", json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ".$name.");
}

/** Manual calls get the timer's environment from its file; what is already set stays. */
function umgebung(): void {
    $datei = umgebungsdatei_lesen((string)@file_get_contents(UMGEBUNG));
    foreach (["FRISTEN_AN", "FRISTEN_INTERN", "FRISTEN_BEFEHL", "HINWEISE_WURZEL"] as $name)
        if (isset($datei[$name]) && getenv($name) === false) putenv("$name=$datei[$name]");
}

/** Hits: [stufe, portal, id, frist, an, text, sortierdatum, falls]
 *  stufe ∈ läuft ab, angekündigt, ABGELAUFEN, FEHLER;  an ∈ team, intern, betreiber. */
function pruefen(string $wurzel, DateTimeImmutable $heute, array $aufschuebe = [], array $ankuendigungen = []): array {
    $ordner = portale($wurzel, nur_portal());
    if (!$ordner) return [["FEHLER", nur_portal() ?? "-", "-", "", "betreiber", "keine Portale mit cases/ unter $wurzel gefunden", $heute, null]];
    $t = [];
    foreach ($ordner as $portal => $dir) {
        $cases = "$dir/cases";
        $portal = (string)$portal;   // numeric names come back as int keys
        // Portal and ID end up in mails, also in a command line to copy: allow only harmless characters.
        if (!preg_match('/^[a-z0-9-]+$/', $portal)) { $t[] = ["FEHLER", "?", "-", "", "betreiber", "Portalverzeichnis mit ungültigem Namen unter $wurzel", $heute, null]; continue; }
        // Mails the portal could not send (send_mail): reported until the operator removes the file
        $mf = array_filter(@file("$dir/" . MAILFEHLER, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], fn($z) => $z[0] !== "#");
        if ($mf) $t[] = ["FEHLER", $portal, "-", "", "betreiber", count($mf) . " Mail(s) nicht versandt, zuletzt " . substr(end($mf), 0, 16) . " – Liste in " . MAILFEHLER . ", danach löschen", $heute, null];
        $dateien = glob("$cases/*");
        if ($dateien === false || !is_readable($cases)) { $t[] = ["FEHLER", $portal, "-", "", "betreiber", "cases/ nicht lesbar", $heute, null]; continue; }
        foreach ($dateien as $datei) {
            $id = basename($datei, ".json");
            if (!preg_match('/^[A-Za-z0-9]{8}\.json$/', basename($datei))) { $t[] = ["FEHLER", $portal, "?", "", "betreiber", "Datei mit ungültigem Namen in cases/", $heute, null]; continue; }
            $m = lesen($datei);
            $f = $m === null ? "nicht lesbar" : fristen($m, $aufschuebe["$portal/$id"] ?? null, $ankuendigungen["$portal/$id"] ?? null, $heute);
            if (is_string($f)) { $t[] = ["FEHLER", $portal, $id, "", "betreiber", $f, $heute, null]; continue; }
            foreach ($f as [$frist, $ende, $bezug, $falls]) {
                $loeschung = $frist === FRIST_LOESCHUNG;
                if ($heute > $ende) $stufe = "ABGELAUFEN";
                elseif ($heute >= $ende->modify("-" . ($loeschung ? ANKUENDIGUNG : VORLAUF) . " days")) $stufe = $loeschung ? "angekündigt" : "läuft ab";
                else continue;
                $text = $loeschung
                    ? ($stufe === "ABGELAUFEN" ? "Löschung fällig seit " . $ende->modify("+1 day")->format("d.m.Y")
                        : "Löschung nach dem " . $ende->format("d.m.Y") . ", Veto bis dahin möglich")
                    : "$frist bis " . $ende->format("d.m.Y");
                $text .= " ($bezug)" . ($falls ? " – nur falls die Antwort vom " . $falls->format("d.m.Y") . " noch keine Folgemaßnahmen nannte" : "");
                $an = $loeschung && $stufe === "ABGELAUFEN" ? "intern" : "team";
                $t[] = [$stufe, $portal, $id, $frist, $an, $text, $ende, $falls];
            }
        }
    }
    usort($t, fn($a, $b) => [$a[1], $a[6], $a[2]] <=> [$b[1], $b[6], $b[2]]);
    return $t;
}

function zeilen(array $treffer, bool $mit_portal = true): string {
    return implode("\n", array_map(fn($x) => sprintf("%-11s %s%-10s %s", $x[0], $mit_portal ? sprintf("%-18s ", $x[1]) : "", $x[2], $x[5]), $treffer)) . "\n";
}

const HINWEIS = [
    FRIST_EINGANG => "Eingangsbestätigung: Bestätigen Sie der meldenden Person, dass die Meldung angekommen ist (§ 17 Abs. 1 Nr. 1 HinSchG).",
    FRIST_RUECK => "Rückmeldung: Schreiben Sie der meldenden Person, was Sie unternehmen oder planen, und warum (§ 17 Abs. 2 HinSchG).",
];

function bericht(array $treffer): string {
    return $treffer ? "Hinweisportale – Fristen:\n\n" . zeilen($treffer) : "";
}

/** Addresses to notify per report from <kunde>/passwd (as in the portal: adresse:hash:rolle:benachrichtigung). */
function team(string $kunde, string $id): array {
    $an = [];
    foreach (passwd_lesen("$kunde/passwd", fn($t) => fwrite(STDERR, "$t – " . basename($kunde) . "\n")) as $f) {
        if ($f[3] === "keine" || !filter_var($f[0], FILTER_VALIDATE_EMAIL)) continue;
        $rolle = $f[2];
        if ($rolle === "Team" || $rolle === "DEV" || in_array($id, array_map(fn($r) => preg_replace("/[^a-zA-Z0-9]+/", "", $r), explode(",", $rolle)), true))
            $an[] = $f[0];
    }
    return $an;
}

function intern(array $adressen): array {
    if (!getenv("FRISTEN_INTERN")) return [];
    $domain = "@" . strtolower(getenv("FRISTEN_INTERN"));
    return array_values(array_filter($adressen, fn($a) => str_ends_with(strtolower($a), $domain)));
}

/** Settings as the portal sees them (shared rule in portal/common/deadlines.php); only Domain and Sicherung are used. */
function einstellungen(string $kunde): array {
    static $je = [];   // one run reads each portal once
    return $je[$kunde] ??= einstellungen_zusammenfuehren([], lesen(dirname($kunde) . "/settings.json") ?? [], lesen("$kunde/portal/settings.json") ?? [], basename($kunde));
}

/** Sender domain; "" if no-reply@<domain> is no valid address (the test senden() applies): no Domain-Endung, a URL … */
function domain(string $kunde): string {
    $d = strtolower(einstellungen($kunde)["Domain"]);
    return absenderdomain_gueltig($d) ? $d : "";
}

/** Backup sentence of the deletion confirmation, as in the privacy notice. */
function sicherung(string $kunde): string {
    $monate = (int)(einstellungen($kunde)["Sicherung_Monate"] ?? 0);
    return $monate > 0 ? "noch bis zu $monate Monate enthalten" : "bis zum Ablauf der Sicherungen enthalten";
}

/** Envelope sender = From, else the mail server doesn't sign and strict DMARC (adkim=s, aspf=s) rejects. */
function senden(string $an, string $betreff, string $text, string $von = "", string $antwort = ""): bool {
    foreach ([$von, $antwort] as $x) if ($x && !filter_var($x, FILTER_VALIDATE_EMAIL)) return false;
    if (!filter_var($an, FILTER_VALIDATE_EMAIL)) return false;
    $p = popen((getenv("FRISTEN_SENDMAIL") ?: "/usr/sbin/sendmail") . " -i" . ($von ? " -f " . escapeshellarg($von) : "")
        . " -- " . escapeshellarg($an), "w");
    if (!$p) return false;
    fwrite($p, ($von ? "From: $von\n" : "") . ($antwort ? "Reply-To: $antwort\n" : "") . "To: $an\nSubject: "
        . mb_encode_mimeheader($betreff, "UTF-8", "Q")
        . "\nMIME-Version: 1.0\nContent-Type: text/plain; charset=utf-8\nContent-Transfer-Encoding: 8bit\n\n$text");
    return pclose($p) === 0;
}

/** Mail text for one address of a portal; [subject, text]. */
function mailtext(string $domain, array $liste): array {
    $team = array_filter($liste, fn($x) => $x[3] !== FRIST_LOESCHUNG);
    $angekuendigt = array_filter($liste, fn($x) => $x[3] === FRIST_LOESCHUNG && $x[0] === "angekündigt");
    $faellig = array_filter($liste, fn($x) => $x[3] === FRIST_LOESCHUNG && $x[0] === "ABGELAUFEN");
    $teile = [];
    if ($team) {
        $sicher = array_filter($team, fn($x) => !$x[7]);
        $vielleicht = array_filter($team, fn($x) => (bool)$x[7]);
        $h = array_intersect_key(HINWEIS, array_flip(array_column($team, 3)));
        $teile[] = "Fristen nach § 17 HinSchG laufen ab oder sind abgelaufen.\n"
            . ($sicher ? "\nNoch nicht erledigt. Bitte handeln Sie:\n" . zeilen($sicher, false) : "")
            . ($vielleicht ? "\nVielleicht schon erledigt. Bitte prüfen Sie: Nennt Ihre Antwort schon, was Sie unternehmen? Dann ist die Frist"
                . " eingehalten. Diese Erinnerung endet mit der nächsten Nachricht an die meldende Person oder mit dem Abschluss:\n"
                . zeilen($vielleicht, false) : "")
            . "\n" . implode("\n", $h) . "\nAnmeldung: https://$domain/team\nNach Ablauf kommt diese Erinnerung jede Woche.";
    }
    if ($angekuendigt) {
        $teile[] = "Ankündigung der Löschung (§ 11 Abs. 5 HinSchG):\n" . zeilen($angekuendigt, false)
            . "\nAbgeschlossene Meldungen müssen 3 Jahre nach dem Abschluss gelöscht werden. Wir löschen nach dem genannten Tag."
            . " Müssen Sie eine Meldung länger aufbewahren, zum Beispiel für ein laufendes Verfahren? Dann widersprechen Sie bitte"
            . " bis zu diesem Tag (Veto): Antworten Sie einfach auf diese E-Mail. Nach dem Löschen bekommen Sie eine Bestätigung.";
    }
    if ($faellig) {
        // As the operator calls it, e.g. via ssh and sudo as the portal's pool user (%k); one mail = one portal.
        $befehl = str_replace("%k", reset($faellig)[1], getenv("FRISTEN_BEFEHL") ?: "hinweise-deadlines");
        $befehle = implode("", array_map(fn($x) => "  $befehl --loeschen $x[1] $x[2]\n", $faellig));
        $teile[] = "Löschung fällig – nur an den Betreiber (§ 11 Abs. 5 HinSchG):\n" . zeilen($faellig, false)
            . "\nKam bis zum Fristende kein Veto vom Kunden? Dann jetzt löschen. Der Befehl löscht die Meldung und"
            . " schickt dem Team die Bestätigung:\n$befehle"
            . "Bei Veto bis zum vereinbarten Tag aufbewahren:\n"
            . "  $befehl --aufschub <kunde> <id> TT.MM.JJJJ\n"
            . "Diese Erinnerung kommt jede Woche, bis gelöscht oder aufgeschoben ist.";
    }
    $betreff = $team ? "Fristen nach HinSchG" : ($faellig ? "Löschung fällig" : "Ankündigung der Löschung");
    return ["$domain: $betreff", "Hinweisgebersystem $domain\n\n" . implode("\n\n", $teile) . "\n"];
}

function waechter(string $wurzel): int {
    $betreiber = getenv("FRISTEN_AN");
    if (!$betreiber || !filter_var($betreiber, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "FRISTEN_AN fehlt oder ungültig\n"); return 2; }
    $auf = zustand("postponements"); $ank = zustand("announcements");
    if ($auf === null || $ank === null) { fwrite(STDERR, "postponements.json oder announcements.json kaputt – erst reparieren\n"); return 2; }
    $treffer = pruefen($wurzel, heute(), $auf, $ank);
    $standdatei = standdatei();
    $alt = json_decode((string)@file_get_contents($standdatei), true) ?: [];
    // A hit is due if its stage is new, or if expired and not reported for a week.
    $stand = []; $faellig = [];
    foreach ($treffer as $x) {
        $k = "$x[1]/$x[2]/$x[3]/$x[5]";
        $vorher = $alt[$k] ?? null;
        $neu = !$vorher || $vorher["stufe"] !== $x[0] || (!in_array($x[0], NICHT_WIEDERHOLT, true) && time() - $vorher["zeit"] >= WIEDERHOLUNG - TOLERANZ);
        $stand[$k] = $neu ? ["stufe" => $x[0], "zeit" => time()] : $vorher;
        if ($neu) $faellig[$k] = $x;
    }
    // Limited to one portal with a shared state file: keep the other portals' entries
    if ($p = nur_portal()) foreach ($alt as $k => $v) if (!str_starts_with($k, "$p/")) $stand[$k] ??= $v;
    $je_adresse = [];
    foreach ($faellig as $k => $x) {
        if ($x[4] === "betreiber") continue;
        $an = team("$wurzel/$x[1]", $x[2]);
        // Due deletions: the operator's own team members, else the operator – always with the delete command
        foreach ($x[4] === "intern" ? (intern($an) ?: [$betreiber]) : $an as $a) $je_adresse["$x[1]\t$a"][$k] = $x;
    }
    $erreicht = [];
    foreach ($je_adresse as $schluessel => $liste) {
        [$portal, $a] = explode("\t", $schluessel);
        $domain = domain("$wurzel/$portal");
        if ($domain === "") { fwrite(STDERR, "$portal: keine gültige Absenderdomain – " . absenderdomain_hinweis($wurzel) . "\n"); continue; }
        [$betreff, $text] = mailtext($domain, $liste);
        if (senden($a, $betreff, $text, "no-reply@$domain", $betreiber)) $erreicht += array_fill_keys(array_keys($liste), true);
        else fwrite(STDERR, "Mail an Team $portal fehlgeschlagen\n");
    }
    // To the operator only what reached nobody, plus FEHLER.
    $offen = array_diff_key($faellig, $erreicht);
    $ok = true;
    if ($offen) {
        $text = "Hinweisportale – Fristen ohne zustellbaren Empfänger in <kunde>/passwd bzw. FEHLER:\n\n" . zeilen($offen);
        if (!senden($betreiber, "[server] Hinweisportale: Fristen ohne Empfänger", $text)) {
            fwrite(STDERR, "Mail an FRISTEN_AN fehlgeschlagen\n"); $ok = false;
            // Retry only the undelivered next run; no duplicate team mails.
            foreach (array_keys($offen) as $k) if (isset($alt[$k])) $stand[$k] = $alt[$k]; else unset($stand[$k]);
        }
    }
    // Record the announcement once it reached someone; the 30-day period until deletion starts then.
    $vermerkt = false;
    foreach ($faellig as $k => $x)
        if ($x[3] === FRIST_LOESCHUNG && $x[0] === "angekündigt" && !isset($ank["$x[1]/$x[2]"]) && ($erreicht[$k] ?? $ok && isset($offen[$k]))) {
            $ank["$x[1]/$x[2]"] = heute()->format("Y-m-d"); $vermerkt = true;
        }
    if ($vermerkt && !zustand_schreiben("announcements", $ank)) { fwrite(STDERR, "announcements.json nicht schreibbar\n"); return 2; }
    if (!atomar_schreiben($standdatei, json_encode($stand, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ".state.")) {
        fwrite(STDERR, "Stand nicht schreibbar: $standdatei\n"); return 2;
    }
    return $ok ? 0 : 2;
}

function meldung_pruefen(string $wurzel, string $kunde, string $id): string|array {
    if (!preg_match('/^[a-z0-9-]+$/', $kunde) || !preg_match('/^[A-Za-z0-9]{8}$/', $id)) return "KUNDE oder ID ungültig";
    $datei = "$wurzel/$kunde/cases/$id.json";
    if (!is_file($datei)) return "Meldung fehlt: $datei";
    $m = lesen($datei);
    if ($m === null) return "nicht lesbar: $datei";
    if (($m["status"] ?? "") !== STATUS_ZU) return "nicht abgeschlossen";
    return [$datei, $m];
}

function loeschen(string $wurzel, string $kunde, string $id): int {
    $r = meldung_pruefen($wurzel, $kunde, $id);   // validates $kunde before it becomes part of a path
    if (is_string($r)) { fwrite(STDERR, "$r – nicht löschen\n"); return 2; }
    // The portal's write lock: no message or status change between check and deletion
    if ($fehler = eigner_fehler("$wurzel/$kunde/cases")) { fwrite(STDERR, "$fehler – nicht löschen\n"); return 2; }
    $sperre = @fopen("$wurzel/$kunde/.cases-lock", "c");
    if (!$sperre || !flock($sperre, LOCK_EX)) { fwrite(STDERR, "Sperre $kunde/.cases-lock nicht zu bekommen – nicht löschen\n"); return 2; }
    $r = meldung_pruefen($wurzel, $kunde, $id);
    if (is_string($r)) { fwrite(STDERR, "$r – nicht löschen\n"); return 2; }
    [$datei, $m] = $r;
    $auf = zustand("postponements"); $ank = zustand("announcements");
    if ($auf === null || $ank === null) { fwrite(STDERR, "postponements.json oder announcements.json kaputt – nicht löschen\n"); return 2; }
    if (!isset($ank["$kunde/$id"])) { fwrite(STDERR, "Löschung wurde dem Team noch nicht angekündigt – nicht löschen\n"); return 2; }
    $f = fristen($m, $auf["$kunde/$id"] ?? null, $ank["$kunde/$id"]);
    if (is_string($f)) { fwrite(STDERR, "$f – nicht löschen\n"); return 2; }
    [, $ende, $bezug] = $f[0];
    if (heute() <= $ende) { fwrite(STDERR, "Aufbewahrung läuft noch bis " . $ende->format("d.m.Y") . " – nicht löschen\n"); return 2; }
    $an = team("$wurzel/$kunde", $id);
    if (!$an) { fwrite(STDERR, "keine Empfänger für die Löschbestätigung in $kunde/passwd – nicht löschen\n"); return 2; }
    $domain = domain("$wurzel/$kunde");
    if ($domain === "") { fwrite(STDERR, "keine gültige Absenderdomain für die Löschbestätigung – nicht löschen; " . absenderdomain_hinweis($wurzel) . "\n"); return 2; }
    if (!loeschung_protokollieren("$wurzel/$kunde", $m, $id, "frist")) { fwrite(STDERR, "Löschprotokoll $kunde/" . LOESCHPROTOKOLL . " nicht schreibbar – nicht löschen\n"); return 2; }
    if (!unlink($datei)) { fwrite(STDERR, "Löschen fehlgeschlagen: $datei\n"); return 2; }
    temp_reste_entfernen("$wurzel/$kunde");
    fclose($sperre);   // not held during mail delivery
    unset($auf["$kunde/$id"], $ank["$kunde/$id"]);
    if (!zustand_schreiben("postponements", $auf) || !zustand_schreiben("announcements", $ank))
        fwrite(STDERR, "Zustand nach dem Löschen nicht geschrieben – Einträge für $kunde/$id in postponements.json/announcements.json von Hand entfernen\n");
    $text = "Hinweisgebersystem $domain\n\nLöschbestätigung (§ 11 Abs. 5 HinSchG): Die Meldung $id ($bezug) ist seit dem "
        . heute()->format("d.m.Y") . " gelöscht. In den Sicherungen des Servers ist sie " . sicherung("$wurzel/$kunde")
        . ". Wir stellen sie von dort nicht wieder her.\n";
    $fehl = array_values(array_filter($an, fn($a) => !senden($a, "$domain: Löschbestätigung $id", $text, "no-reply@$domain", getenv("FRISTEN_AN") ?: "")));
    echo "gelöscht: $kunde/$id; Löschbestätigung an " . (count($an) - count($fehl)) . " von " . count($an) . " Adressen\n";
    if (!$fehl) return 0;
    echo "NICHT zugestellt an: " . implode(", ", $fehl) . " – bitte von Hand weiterleiten:\n\n$text";
    return 1;
}

function aufschieben(string $wurzel, string $kunde, string $id, string $bis): int {
    $d = DateTimeImmutable::createFromFormat("!d.m.Y", $bis, new DateTimeZone(ZONE));
    if (!$d || $d->format("d.m.Y") !== $bis || $d <= heute()) { fwrite(STDERR, "Datum TT.MM.JJJJ in der Zukunft erwartet\n"); return 2; }
    $r = meldung_pruefen($wurzel, $kunde, $id);
    if (is_string($r)) { fwrite(STDERR, "$r\n"); return 2; }
    $auf = zustand("postponements");
    if ($auf === null) { fwrite(STDERR, "postponements.json kaputt – erst reparieren\n"); return 2; }
    $f = fristen($r[1], null, "2000-01-01");  // regular end, without the announcement extension
    if (is_string($f)) { fwrite(STDERR, "$f\n"); return 2; }
    if ($d <= $f[0][1]) { fwrite(STDERR, "reguläre Aufbewahrung läuft ohnehin bis " . $f[0][1]->format("d.m.Y") . "\n"); return 2; }
    $auf["$kunde/$id"] = $d->format("Y-m-d");
    if (!zustand_schreiben("postponements", $auf)) { fwrite(STDERR, "postponements.json nicht schreibbar\n"); return 2; }
    echo "Aufschub: $kunde/$id bis $bis\n";
    return 0;
}

/** null if the caller owns $dir, else why not. */
function eigner_fehler(string $dir): ?string {
    if (!is_dir($dir)) return "Verzeichnis fehlt: $dir";
    $eigner = fileowner($dir);
    return $eigner === posix_geteuid() ? null : "als Eigentümer von $dir aufrufen: sudo -u " . (posix_getpwuid($eigner)["name"] ?? $eigner);
}

/** One run at a time (timer and manual call), else postponements or announcements get lost. */
function sperren(): bool {
    static $f;
    $f = @fopen(zustand_verzeichnis() . "/.lock", "c");
    return $f && flock($f, LOCK_EX);
}

function main(array $argv): int {
    umask(0077);  // state names customers and report IDs
    umgebung();
    $a = array_slice($argv, 1);
    if (in_array($a[0] ?? "", ["-h", "--help"], true)) {
        // Help = the usage block in the header, so the two can't diverge.
        $kopf = file(__FILE__, FILE_IGNORE_NEW_LINES);
        $start = array_search("// Aufruf:", $kopf, true);
        for ($i = $start; isset($kopf[$i]) && str_starts_with($kopf[$i], "//"); $i++) echo substr($kopf[$i], 3), "\n";
        return 0;
    }
    // Validated here because the name becomes part of the state path (loeschen() validates again for its own paths)
    if (in_array($a[0] ?? "", ["--loeschen", "--aufschub"], true) && preg_match('/^[a-z0-9-]+$/', $a[1] ?? "")) nur_portal($a[1]);
    if (($i = array_search("--kunde", $a, true)) !== false) {
        if (!preg_match('/^[a-z0-9-]+$/', $a[$i + 1] ?? "")) { fwrite(STDERR, "--kunde: Portalname fehlt oder ungültig\n"); return 2; }
        nur_portal($a[$i + 1]);
        array_splice($a, $i, 2);
    }
    $schreibt = in_array($a[0] ?? "", ["--loeschen", "--aufschub", "--mail"], true) || in_array("--mail", $a, true);
    // Writing as another user (e.g. plain sudo) would leave files the timer and the portal cannot open
    if ($schreibt && ($fehler = eigner_fehler(zustand_verzeichnis()))) { fwrite(STDERR, "$fehler\n"); return 2; }
    if ($schreibt && !sperren()) { fwrite(STDERR, "Sperre in " . zustand_verzeichnis() . " nicht möglich\n"); return 2; }
    // Folder of all portals: from the argument, else from HINWEISE_WURZEL; null (and a message) if it does not exist
    $wurzel = function (?string $arg): ?string {
        $w = rtrim($arg ?? (string)getenv("HINWEISE_WURZEL"), "/");
        if ($w !== "" && is_dir($w)) return $w;
        fwrite(STDERR, "Verzeichnis fehlt: \"$w\" (WURZEL angeben oder HINWEISE_WURZEL in " . UMGEBUNG . " setzen)\n");
        return null;
    };
    if (($a[0] ?? "") === "--loeschen" && count($a) >= 3) return ($w = $wurzel($a[3] ?? null)) === null ? 2 : loeschen($w, $a[1], $a[2]);
    if (($a[0] ?? "") === "--aufschub" && count($a) >= 4) return ($w = $wurzel($a[4] ?? null)) === null ? 2 : aufschieben($w, $a[1], $a[2], $a[3]);
    $mail = in_array("--mail", $a, true);
    $rest = array_values(array_filter($a, fn($x) => $x !== "--mail"));
    if ($rest && str_starts_with($rest[0], "--")) { fwrite(STDERR, "Aufruf: siehe Kopf von " . basename(__FILE__) . "\n"); return 2; }
    if (($w = $wurzel($rest[0] ?? null)) === null) return 2;
    if ($mail) return waechter($w);
    $auf = zustand("postponements"); $ank = zustand("announcements");
    if ($auf === null || $ank === null) { fwrite(STDERR, "postponements.json oder announcements.json kaputt\n"); return 2; }
    $treffer = pruefen($w, heute(), $auf, $ank);
    echo bericht($treffer);
    return in_array("FEHLER", array_column($treffer, 0), true) ? 2 : ($treffer ? 1 : 0);
}

if (PHP_SAPI === "cli" && realpath($argv[0] ?? "") === __FILE__) exit(main($argv));
