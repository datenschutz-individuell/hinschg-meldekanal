<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Shared by the portal and the deadline watcher (scripts/deadlines.php), so both report the same HinSchG due
// dates (rules in the header of scripts/deadlines.php) and treat case files the same: deletion log, temp files.

const ANKUENDIGUNG = 30;           // days before deletion
const ZONE = "Europe/Berlin";
const STATUS_ZU = "geschlossen";
// Deletion log per portal, written by the portal and by the deadline watcher; header line on creation
const LOESCHPROTOKOLL = "deletions.log";
const LOESCHPROTOKOLL_KOPF = "# Eingang\tMeldungs-ID\tLöschung\tGrund\n";
// Mails the portal could not hand to sendmail; the deadline watcher reports them to the operator until the file is removed
const MAILFEHLER = "mail-failures.log";
const MAILFEHLER_KOPF = "# Zeit\tEmpfänger\tBetreff\n";
const MELDER = "meldende_person";
const FRIST_EINGANG = "Eingangsbestätigung";   // names double as mail text keys in the watcher
const FRIST_RUECK = "Rückmeldung";
const FRIST_LOESCHUNG = "Löschung";

/** Settings of one portal, the same for its pages and the watcher: defaults < operator settings for all portals
 *  (<wurzel>/settings.json) < the portal's own. "Domain" falls back to <ordner>.<Domain-Endung>; an inherited
 *  "Sicherung_Monate" does not apply to a portal at a hoster (only its own value does). */
function einstellungen_zusammenfuehren(array $defaults, array $betreiber, array $eigen, string $ordner): array {
    $s = array_merge($defaults, $betreiber, $eigen);
    $endung = is_string($s["Domain-Endung"] ?? null) ? $s["Domain-Endung"] : "";
    $domain = $s["Domain"] ?? "";
    // A broken value (not a string) must neither abort pages or watcher nor silently become another domain:
    // it stays empty, which both report as an invalid sender domain
    if (!is_string($domain)) $domain = "";
    elseif ($domain === "") $domain = $ordner . ($endung !== "" ? ".$endung" : "");
    $s["Domain"] = $domain;
    $s["Domain-Endung"] = $endung;
    if (!isset($eigen["Sicherung_Monate"]) && ($s["Hoster"] ?? "") !== "") $s["Sicherung_Monate"] = 0;
    return $s;
}

/** A domain mails can be sent from: no-reply@<domain> must be a valid address (IDN only as Punycode). */
function absenderdomain_gueltig(string $domain): bool {
    return (bool)filter_var("no-reply@$domain", FILTER_VALIDATE_EMAIL);
}

/** Where to look when the sender domain is invalid; $wurzel is the folder of all portals. */
function absenderdomain_hinweis(string $wurzel): string {
    return "\"Domain\" im Portal oder \"Domain-Endung\" in $wurzel/settings.json prüfen (Umlaut-Domains als Punycode)";
}

/** NAME=value lines of the watcher's environment file (optional blanks and quotes); as with systemd, the last entry wins. */
function umgebungsdatei_lesen(string $text): array {
    preg_match_all('/^[ \t]*([A-Z_]+)[ \t]*=[ \t]*"?([^"\n]*?)"?[ \t]*$/m', $text, $m);
    return array_combine($m[1], $m[2]);
}

function datum(string $wert): ?DateTimeImmutable {
    $d = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $wert, new DateTimeZone(ZONE));
    $e = DateTimeImmutable::getLastErrors();
    return ($d === false || ($e && ($e["warning_count"] || $e["error_count"]))) ? null : $d;
}

function heute(): DateTimeImmutable {
    return new DateTimeImmutable("today", new DateTimeZone(ZONE));
}

/** Last day of the deadline (§ 188 BGB): months capped at month end, then days. */
function fristende(DateTimeImmutable $start, int $monate, int $tage = 0): DateTimeImmutable {
    $erster = $start->setTime(0, 0)->modify("first day of this month")->modify("+$monate months");
    $tag = min((int)$start->format("j"), (int)$erster->format("t"));
    return $erster->setDate((int)$erster->format("Y"), (int)$erster->format("n"), $tag)->modify("+$tage days");
}

function tag(?string $ymd): ?DateTimeImmutable {
    return $ymd ? (DateTimeImmutable::createFromFormat("!Y-m-d", $ymd, new DateTimeZone(ZONE)) ?: null) : null;
}

/** Deadlines of a report: [[deadline, last day, reference, falls]] or FEHLER text.
 *  falls: date of the first team reply if it may already be the feedback (the script does not check its content). */
function fristen(array $m, ?string $aufschub = null, ?string $angekuendigt = null, ?DateTimeImmutable $heute = null): array|string {
    $status = $m["status"] ?? "";
    if (!is_string($status) || $status === "") return "ohne Status";
    $eingang = $bestaetigt = $abschluss = $antwort = null; $zurueck = $invalidiert = false; $texte = 0;
    foreach ($m["conversation"] ?? [] as $i => $e) {
        $roh = (string)($e["date"] ?? "");
        $d = datum($roh);
        if (!$d) return "Datum unlesbar: " . substr($roh, 0, 30);
        $team = ($e["person"] ?? MELDER) !== MELDER;
        if ($i === 0) $eingang = $d;
        elseif ($team) {
            $bestaetigt ??= $d;
            if (isset($e["text"]) && ++$texte === 1) $antwort = $d;
            elseif (isset($e["text"])) $zurueck = true;
        }
        if (($e["action"] ?? "") === "invalidate_token") $invalidiert = true;
        // Closing counts as feedback, even if the report was reopened afterwards.
        if (($e["status"] ?? "") === STATUS_ZU) { $abschluss = $d; $zurueck = true; }
    }
    if (!$eingang) return "ohne Einträge";
    if ($status === STATUS_ZU) {
        if ($abschluss === null) return "geschlossen, aber ohne Abschlusseintrag";
        $ende = fristende($abschluss, 36);
        $bezug = "abgeschlossen " . $abschluss->format("d.m.Y");
        $a = tag($aufschub);
        if ($a && $a > $ende) { $ende = $a; $bezug .= ", nach Veto aufbewahrt"; }
        // Without a sent notice, treat it as sent today: delete 30 days later at the earliest.
        $ank = tag($angekuendigt) ?? ($heute ?? heute());
        $frueh = $ank->modify("+" . ANKUENDIGUNG . " days");
        if ($frueh > $ende) $ende = $frueh;
        return [[FRIST_LOESCHUNG, $ende, $bezug, null]];
    }
    if ($invalidiert) return [];
    $f = [];
    if (!$bestaetigt) $f[] = [FRIST_EINGANG, fristende($eingang, 0, 7), "Eingang " . $eingang->format("d.m.Y"), null];
    if (!$zurueck) $f[] = $bestaetigt
        ? [FRIST_RUECK, fristende($bestaetigt, 3), "bestätigt " . $bestaetigt->format("d.m.Y"), $antwort]
        : [FRIST_RUECK, fristende($eingang, 3, 7), "Eingang " . $eingang->format("d.m.Y"), null];
    return $f;
}

/** Last retention day of a closed case (3 years after the last closing, § 11 Abs. 5
 *  HinSchG); null while it is not closed or a date is unreadable. */
function aufbewahrung_bis(array $m): ?DateTimeImmutable {
    if (($m["status"] ?? "") !== STATUS_ZU) return null;
    $abschluss = null;
    foreach ($m["conversation"] ?? [] as $e) if (($e["status"] ?? "") === STATUS_ZU) $abschluss = datum((string)($e["date"] ?? ""));
    return $abschluss ? fristende($abschluss, 36) : null;
}


// Shared by portal and deadline watcher, so both write the same log format and find the same temp files.
const FALL_TEMP = ".case.";   // prefix of temp files in cases/; the dot hides them from case lists

/** Writes a file atomically (temp file in the same folder + rename), so readers never see half a file. $praefix names
 *  the temp file (a dot hides it from listings); $modus e.g. 0600 for files holding secrets. */
function atomar_schreiben(string $datei, string $inhalt, string $praefix, ?int $modus = null): bool {
    $tmp = tempnam(dirname($datei), $praefix);
    if ($tmp && file_put_contents($tmp, $inhalt) === strlen($inhalt) && ($modus === null || chmod($tmp, $modus)) && rename($tmp, $datei)) return true;
    if ($tmp) @unlink($tmp);
    return false;
}

/** Logs a deletion in <kunde>/deletions.log, header line on creation. Log before deleting: rather one entry too
 *  many than a deletion without trace. */
function loeschung_protokollieren(string $kunde_dir, array $m, string $id, string $grund): bool {
    $log = "$kunde_dir/" . LOESCHPROTOKOLL;
    $zeile = implode("\t", [substr((string)($m["conversation"][0]["date"] ?? ""), 0, 10), $id, heute()->format("Y-m-d"), $grund]) . "\n";
    return file_put_contents($log, (file_exists($log) ? "" : LOESCHPROTOKOLL_KOPF) . $zeile, FILE_APPEND | LOCK_EX) !== false;
}

/** Portal folders under $wurzel: [name => folder]. One rule for deadline watcher and emergency script: top-level,
 *  not "_*" or hidden, with portal/ (as the install script) and cases/. With $nur only that one, without listing
 *  $wurzel (a pool user may only traverse it). */
function portale(string $wurzel, ?string $nur = null): array {
    $p = [];
    foreach ($nur !== null ? [$nur] : (scandir($wurzel) ?: []) as $n)
        if ($n !== "" && $n[0] !== "_" && $n[0] !== "." && is_dir("$wurzel/$n/portal") && is_dir("$wurzel/$n/cases")) $p[$n] = "$wurzel/$n";
    return $p;
}

/** Under the case lock: any temp file found is a leftover of a killed writer and holds a full case, which would
 *  outlive its deletion. */
function temp_reste_entfernen(string $kunde_dir): void {
    foreach (glob("$kunde_dir/cases/" . FALL_TEMP . "*") ?: [] as $alt) @unlink($alt);
}


/** Active lines of a passwd file: [lowercase address => [address, hash, role, notification]]. Same rule for portal
 *  and deadline watcher. Malformed lines are skipped, an address listed twice is dropped (its role is ambiguous);
 *  $melden gets a note for each, without the line itself (it holds the password hash). */
function passwd_lesen(string $datei, ?callable $melden = null): array {
    $e = $doppelt = [];
    foreach (explode("\n", (string)@file_get_contents($datei)) as $nr => $z) {
        $z = trim($z);
        if ($z === "" || str_starts_with($z, "#")) continue;
        $f = explode(":", $z);
        if (count($f) < 4) { if ($melden) $melden("passwd Zeile " . ($nr + 1) . ": falsches Format"); continue; }
        $a = strtolower($f[0]);
        if (isset($e[$a])) $doppelt[$a] = true;
        $e[$a] = $f;
    }
    foreach (array_keys($doppelt) as $a) { if ($melden) $melden("passwd: $a mehrfach eingetragen, ignoriert"); unset($e[$a]); }
    return $e;
}
