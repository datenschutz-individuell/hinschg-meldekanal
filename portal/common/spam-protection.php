<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Bot protection for the report page (cause: 342 bot reports in 51 s on one portal).
// Stores no IP and nothing about the reporting person – anonymity stays intact.
//   Honeypot:     hidden field only bots fill in
//   Time token:   signed timestamp; submit no earlier than MIN_SEKUNDEN after loading, no later than MAX_SEKUNDEN
//   Cap:          at most OBERGRENZE new reports per portal in FENSTER_SEKUNDEN
// Per-IP throttling is done by nginx (scripts/hinweise-throttle.conf, zone in scripts/hinweise-throttle-zone.conf).

const SPAM_MIN_SEKUNDEN = 5;
const SPAM_MAX_SEKUNDEN = 86400;
const SPAM_OBERGRENZE = 3;
const SPAM_FENSTER_SEKUNDEN = 15 * 60;
const SPAM_HONEYPOT = "kontrollfeld";


function spam_schluessel(): string {
    $datei = KUNDE_DIR."/.form-key";
    // "x" creates only if the file is missing: two concurrent first calls never create two keys.
    if ($f = @fopen($datei, "x")) {
        fwrite($f, random_bytes(32));
        fclose($f);
        @chmod($datei, 0600);
    }
    for ($i = 0; $i < 20; $i++) {
        $s = @file_get_contents($datei);
        if ($s !== false && strlen($s) >= 32) return $s;
        usleep(50000);  // another request is writing
    }
    error_log("Spamschutz: $datei fehlt oder nicht lesbar");
    die("Kritischer Fehler!");
}


// Exclusive until request end: counting and creating must not interleave, else concurrent POSTs
// all slip under the cap.
function spam_sperre(bool $an): void {
    static $sperre = null;
    if (!$an) { if ($sperre) { flock($sperre, LOCK_UN); fclose($sperre); $sperre = null; } return; }
    $sperre = fopen(KUNDE_DIR."/.report-lock", "c");
    if (!$sperre || !flock($sperre, LOCK_EX)) { error_log("Spamschutz: Sperre fehlgeschlagen"); die("Kritischer Fehler!"); }
}


// Hidden fields for the report form.
function spam_felder(): string {
    $zeit = time();
    $token = "$zeit." . hash_hmac("sha256", (string)$zeit, spam_schluessel());
    return "<input type='hidden' name='formzeit' value='$token'>"
        . "<div class='spam-falle' aria-hidden='true'><label>Bitte leer lassen "
        . "<input type='text' name='" . SPAM_HONEYPOT . "' value='' tabindex='-1' autocomplete='off'></label></div>";
}


// Honeypot and time token, without lock and without reading reports; null = OK, else the reason:
// "spam_zu_schnell" (genuine form sent back too fast, the sender may resubmit) or "spam_abgelehnt".
function spam_formular_pruefen(): ?string {
    if (($_POST[SPAM_HONEYPOT] ?? "") !== "") return "spam_abgelehnt";
    [$zeit, $mac] = array_pad(explode(".", (string)($_POST["formzeit"] ?? ""), 2), 2, "");
    if (!ctype_digit($zeit) || !hash_equals(hash_hmac("sha256", $zeit, spam_schluessel()), $mac)) return "spam_abgelehnt";
    $alter = time() - (int)$zeit;
    if ($alter < SPAM_MIN_SEKUNDEN) return "spam_zu_schnell";
    if ($alter > SPAM_MAX_SEKUNDEN) return "spam_abgelehnt";
    return null;
}



// Cap per portal; call only under spam_sperre(true). The numbers also appear in the spam_drossel texts.
function spam_obergrenze(array $alle_meldungen): ?string {
    // Stored as local time (german_date), so compare that way too.
    $grenze = (new DateTime("@" . (time() - SPAM_FENSTER_SEKUNDEN)))->setTimezone(new DateTimeZone("Europe/Berlin"))->format("Y-m-d H:i:s");
    $neue = count(array_filter($alle_meldungen, fn($m) => ($m["conversation"][0]["date"] ?? "") >= $grenze));
    if ($neue >= SPAM_OBERGRENZE) {
        error_log("Spamschutz: Obergrenze erreicht (" . SPAM_OBERGRENZE . " Meldungen in " . SPAM_FENSTER_SEKUNDEN / 60 . " Min.) – " . basename(KUNDE_DIR));
        return "spam_drossel";
    }
    return null;
}


// Value of a rejected form for refilling, so the reporting person loses nothing.
function formwert(string $name): string {
    return is_string($_POST[$name] ?? null) ? htmlspecialchars($_POST[$name], ENT_QUOTES) : "";
}
