#!/usr/bin/env php
<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Emergency key of the encrypted reports (portal/common/crypto.php). The private key stays offline; it is read
// from stdin and never written on the server.
//
// Usage:
//   emergency.php --neu FILE                  create a key pair: private to FILE, public on stdout (header and
//                                             base64, belongs in <root>/emergency.pub; NEW_PUB below is the base64 line)
//   emergency.php CUSTOMER ADDRESS < FILE     wrap all reports of CUSTOMER (portal folder) for the approved member
//                                             ADDRESS, e.g. when nobody has access any more
//   emergency.php --freigeben CUSTOMER ADDRESS < FILE
//                                             approve waiting access ADDRESS when nobody in the team can, then wrap
//   emergency.php --umschluesseln ROOT NEW_PUB < FILE
//                                             new emergency key or scheme, as root for all portals under ROOT (the
//                                             folder of emergency.pub), each as its owner: every member with a key
//                                             gets a new pair (taken over at the next login), every report is wrapped
//                                             for the new keys. Then install NEW_PUB as emergency.pub and run again
//                                             with the OLD key for reports from the meantime; repeatable. With a
//                                             portal folder instead of ROOT only that portal. A trailing --pruefen
//                                             only checks and changes nothing (dry run).
// The portal code is loaded only from next to this script, never from the portal folder: its pool user could
// replace it there and read the emergency key. Hence run a root-owned copy of scripts/ and portal/:
//   ssh server 'd=$(sudo mktemp -d) && sudo cp -r TEMPLATE/scripts TEMPLATE/portal "$d"/ && sudo chmod -R a+rX "$d" \
//     && sudo -u hinweise-kunde php "$d"/scripts/emergency.php /srv/hinweise/kunde a@example.org; sudo rm -rf "$d"' < key.txt
// Rekey of all portals the same way, but as root (it switches to each portal's owner):
//   ... && sudo php "$d"/scripts/emergency.php --umschluesseln /srv/hinweise NEW_PUB; ...' < old-key.txt

// --- rekey of all portals (root mode) ---

/** One run of this script for one portal, as its owner when called as root. Exit code of the run. */
function portal_lauf(string $portal, bool $nur_pruefen, string $schluessel, string $neu_b64): int {
    $befehl = [PHP_BINARY, __FILE__, "--umschluesseln", $portal, $neu_b64, ...($nur_pruefen ? ["--pruefen"] : [])];
    if (posix_geteuid() === 0) {
        $wer = posix_getpwuid(fileowner($portal))["name"] ?? "";
        if ($wer === "" || $wer === "root") { fwrite(STDERR, "kein Portalbenutzer als Eigentümer\n"); return 2; }
        array_unshift($befehl, "sudo", "-u", $wer);
    }
    $p = proc_open($befehl, [0 => ["pipe", "r"], 1 => STDOUT, 2 => STDERR], $rohre);
    if (!$p) { fwrite(STDERR, "nicht gestartet\n"); return 1; }
    fwrite($rohre[0], $schluessel); fclose($rohre[0]);
    return proc_close($p);
}

/** All portals one after the other; the highest exit code (proc_close's -1, unknown, counts as 1). */
function portale_laufen(array $portale, bool $nur_pruefen, string $schluessel, string $neu_b64): int {
    $rc = 0;
    foreach ($portale as $portal) {
        echo basename($portal) . ": ";
        if (($r = portal_lauf($portal, $nur_pruefen, $schluessel, $neu_b64)) !== 0) $rc = max($rc, $r > 0 ? $r : 1);
    }
    return $rc;
}

/** Each portal runs as its owner (pool user), who alone may write its files. A first pass only checks every
 *  portal, so a wrong key or a foreign case found then stops the run before any portal changes; a failure in the
 *  second pass leaves the portals before it done, a repeated run finishes the rest. */
function portale_umschluesseln(string $ort, bool $wurzel_modus, string $neu_b64, bool $pruefen): int {
    $schluessel = stream_get_contents(STDIN);
    $ort = rtrim($ort, "/");
    $portale = $wurzel_modus ? portale($ort) : portale(dirname($ort), basename($ort));
    if (!$portale) { fwrite(STDERR, "Keine Portale unter $ort\n"); return 2; }
    $rc = portale_laufen($portale, true, $schluessel, $neu_b64);
    if ($rc) fwrite(STDERR, "Nichts geändert.\n");
    if ($rc || $pruefen) return $rc;
    return portale_laufen($portale, false, $schluessel, $neu_b64);
}

// --- one portal ---

/** Private key from stdin ("#" lines are a header). */
function schluessel_lesen(): string {
    return implode("\n", array_filter(array_map("trim", file("php://stdin")), fn($z) => $z !== "" && $z[0] !== "#")) . "\n";
}

/** Rekey: the old key fits the installed one, or the new one is installed already (run for the meantime).
 *  Otherwise ($neu null) the key must be the installed one. Exits on mismatch. */
function schluessel_pruefen(?string $pub_alt, ?string $pub_notfall, ?string $neu): void {
    if (!$pub_alt) $text = "Kein privater Schlüssel auf stdin";
    elseif ($neu !== null && $pub_alt === $neu) $text = "Bitte den bisherigen privaten Notfallschlüssel angeben, nicht den neuen";
    elseif (!$pub_notfall || ($pub_alt !== $pub_notfall && $neu !== $pub_notfall)) $text = "Schlüssel passt nicht zu " . dirname(KUNDE_DIR) . "/" . NOTFALL_DATEI;
    else return;
    fwrite(STDERR, "$text\n");
    exit(2);
}

/** Kind of a case for the rekey. "alt": the given key opens its emergency wrapping; "neu": done before
 *  (notfall_fp); "fremd": wrapped for a third key. Without emergency wrapping: "ohne" if a member may read it
 *  (after the new pairs nobody could), "verwaist" if none may (a wrapping for a member outside its role is lost). */
function fall_art(array $c, string $paar, string $fp_neu, array $alle): string {
    if (!ist_verschluesselt($c)) return "klar";
    if (!isset($c["keys"]["notfall"])) {
        foreach (array_keys(empfaenger($c["id"], $alle)) as $fp) if (isset($c["keys"]["u:$fp"])) return "ohne";
        return "verwaist";
    }
    if (($c["notfall_fp"] ?? null) === $fp_neu) return "neu";
    return entsiegeln($paar, $c["keys"]["notfall"]) ? "alt" : "fremd";
}

/** Judges the kinds once before any change: null to go on, else the exit code. The given key must be proven
 *  (installed, or opens a case), so a wrong one changes nothing. */
function einordnung_bewerten(array $arten, bool $installiert, bool $pruefen): ?int {
    if ($verwaist = array_keys($arten, "verwaist", true)) fwrite(STDERR, "Ohne Notfallverpackung und für kein berechtigtes Mitglied verpackt – danach nur noch mit dem Zugangscode der meldenden Person lesbar: " . implode(" ", $verwaist) . "\n");
    $stopp = array_filter([
        "Nicht für diesen Schlüssel verpackt" => array_keys($arten, "fremd", true),
        // a member login adds the emergency wrapping; after the new pairs nobody could
        "Ohne Notfallverpackung – die nächste Anmeldung eines Mitglieds, das sie lesen kann, ergänzt sie" => array_keys($arten, "ohne", true),
    ]);
    foreach ($stopp as $grund => $ids) fwrite(STDERR, "$grund: " . implode(" ", $ids) . "\n");
    if ($stopp) { fwrite(STDERR, "Nichts geändert.\n"); return 2; }
    if (!$installiert && !in_array("alt", $arten, true)) { echo "nichts umzuschlüsseln\n"; return 0; }
    if ($pruefen) { echo count(array_keys($arten, "alt", true)) . " Meldungen bereit\n"; return 0; }
    return null;
}

/** New pair sealed to each member's old public key, so the next normal login takes it over (auth.php
 *  paar_aus_mk). User locks are held until the end: no login can undo the rekey meanwhile. [address => new pub] */
function mitglieder_umschluesseln(string $fp_neu): array {
    $gesetzt = [];
    foreach (array_keys(alle_benutzer()) as $adr) {
        benutzer_sperren($adr);
        $b = benutzer_laden($adr);
        if (empty($b["pub"]) || ($b["rekey"] ?? "") === $fp_neu) continue;
        $p = schluesselpaar_neu();
        $b["sk_neu"][] = versiegeln(base64_decode($b["pub"]), $p);
        $b["pub"] = base64_encode(oeffentlich($p));
        $b["rekey"] = $fp_neu;
        // Stop before the cases: a repeated run then wraps them for all new pairs, this one included
        if (!benutzer_speichern($b)) { fwrite(STDERR, "$adr: nicht gespeichert – Ursache beheben und erneut aufrufen\n"); exit(1); }
        $gesetzt[$adr] = $b["pub"];
    }
    return $gesetzt;
}

/** Data key from the old emergency wrapping, wrapped for the members' current keys and the new emergency key; the
 *  reporting person's wrapping stays (symmetric). Cases are read again: one may have come in meanwhile. [n, errors] */
function faelle_umschluesseln(string $paar, string $neu, string $fp_neu): array {
    $alle = empfaenger_alle();
    $n = $fehler = 0;
    foreach (get_all_cases() as $c) {
        $id = $c["id"];
        // anything else the closure sorts out: done meanwhile, or (foreign key) counted as error below
        if (!ist_verschluesselt($c) || !isset($c["keys"]["notfall"]) || ($c["notfall_fp"] ?? null) === $fp_neu) continue;
        $ok = fall_aendern($id, function ($m) use ($paar, $neu, $fp_neu, $alle, $id) {
            if (($m["notfall_fp"] ?? null) === $fp_neu) return null;   // done meanwhile by a parallel run
            $k = entsiegeln($paar, $m["keys"]["notfall"] ?? null);
            if (!$k) return null;
            $m["crypto"] = KRYPTO;   // a change of the symmetric scheme would re-encrypt title and texts here
            $m["keys"] = array_filter($m["keys"], fn($n) => !str_starts_with($n, "u:"), ARRAY_FILTER_USE_KEY);
            foreach (empfaenger($id, $alle) as $fp => $pub) $m["keys"]["u:$fp"] = versiegeln($pub, $k);
            return notfall_verpacken($m, $neu, $k);
        });
        if (($ok["notfall_fp"] ?? null) === $fp_neu) $n++; else { fwrite(STDERR, "$id: nicht umgeschlüsselt\n"); $fehler++; }
    }
    return [$n, $fehler];
}

/** A login that read its user file before the rekey and saved it afterwards undid it: report. Errors. */
function ueberschriebene_melden(array $gesetzt): int {
    $fehler = 0;
    foreach ($gesetzt as $adr => $pub) if ((benutzer_laden($adr)["pub"] ?? "") !== $pub) {
        fwrite(STDERR, "$adr: Datei während des Laufs geändert – nach Installation der neuen " . NOTFALL_DATEI . ": emergency.php KUNDE $adr < NEUER_SCHLÜSSEL\n");
        $fehler++;
    }
    return $fehler;
}

/** Approval as in /team/ (freigaben_bearbeiten); the emergency key stands in for the approver's. True if this
 *  run approved, so the mail is due; a repeated run or an approval in /team/ meanwhile mails nothing. */
function freigeben(string $adresse): bool {
    $status = benutzer_laden($adresse)["status"] ?? "";
    if ($status === "aktiv") return false;
    if ($status !== "wartet") { fwrite(STDERR, "$adresse: kein wartender Zugang\n"); exit(2); }
    if (zugang_freigeben($adresse)) { echo "$adresse freigegeben\n"; return true; }
    if ((benutzer_laden($adresse)["status"] ?? "") === "aktiv") return false;
    fwrite(STDERR, "$adresse: nicht gespeichert\n"); exit(1);
}

/** Wraps every case the member's role in passwd allows (as at a normal login, empfaenger) for its key. [n, errors] */
function faelle_verpacken(string $paar, string $fp, string $pub, array $alle): array {
    $n = $fehler = 0;
    foreach (get_all_cases() as $c) {
        if (!ist_verschluesselt($c) || isset($c["keys"]["u:$fp"]) || !isset(empfaenger($c["id"], $alle)[$fp])) continue;
        $ok = fall_aendern($c["id"], function ($m) use ($paar, $pub, $fp) {
            $k = entsiegeln($paar, $m["keys"]["notfall"] ?? null);
            if (!$k) return null;
            $m["keys"]["u:$fp"] = versiegeln($pub, $k);
            return $m;
        });
        if (isset($ok["keys"]["u:$fp"])) $n++; else { fwrite(STDERR, $c["id"] . ": nicht verpackt (ohne Notfallverpackung?)\n"); $fehler++; }
    }
    return [$n, $fehler];
}

// --- main ---

$a = array_slice($argv, 1);
$common = __DIR__ . "/../portal/common";

if (($a[0] ?? "") === "--neu" && isset($a[1])) {
    if (file_exists($a[1])) { fwrite(STDERR, "$a[1] existiert schon\n"); exit(2); }
    require "$common/crypto.php";
    $paar = schluesselpaar_neu();
    $inhalt = "# Notfallschlüssel Hinweisportale: privater Schlüssel " . KURVE . " (PEM), erzeugt " . date("Y-m-d") . ". Nur offline aufbewahren.\n" . $paar;
    umask(077);
    if (file_put_contents($a[1], $inhalt) !== strlen($inhalt)) { fwrite(STDERR, "$a[1] nicht geschrieben\n"); exit(2); }
    echo "# Notfallschlüssel Hinweisportale: öffentlicher Schlüssel " . KURVE . " (SubjectPublicKeyInfo DER, base64), erzeugt " . date("Y-m-d") . "\n", base64_encode(oeffentlich($paar)), "\n";
    exit(0);
}
$umschluesseln = ($a[0] ?? "") === "--umschluesseln";
$freigeben = ($a[0] ?? "") === "--freigeben";
if ($umschluesseln || $freigeben) array_shift($a);
$pruefen = $umschluesseln && ($a[2] ?? "") === "--pruefen";   // check only; also the first pass of root mode
if ($pruefen) array_pop($a);
if (count($a) !== 2) { fwrite(STDERR, "Aufruf: emergency.php --neu DATEI | emergency.php [--freigeben] KUNDE ADRESSE < DATEI | emergency.php --umschluesseln WURZEL|KUNDE NEUE_PUB [--pruefen] < DATEI\n"); exit(2); }
[$kunde, $adresse] = $a;
require_once "$common/crypto.php";
require_once "$common/deadlines.php";   // portale(): the same portal rule as the deadline watcher
// Root never writes portal files itself: rekey goes through each portal's owner, anything else is refused
$wurzel_modus = is_file("$kunde/" . NOTFALL_DATEI);
if (posix_geteuid() === 0 && !$umschluesseln) { fwrite(STDERR, "Nicht als root aufrufen, sondern als Benutzer des Portals (sudo -u hinweise-KUNDE)\n"); exit(2); }
if ($umschluesseln && ($wurzel_modus || posix_geteuid() === 0)) exit(portale_umschluesseln($kunde, $wurzel_modus, $adresse, $pruefen));

$_SERVER["DOCUMENT_ROOT"] = rtrim($kunde, "/") . "/portal";
require "$common/helper.php";
require "$common/auth.php";

$paar = schluessel_lesen();
$neu = $umschluesseln ? base64_decode($adresse, true) : null;
if ($umschluesseln && (!$neu || !kurve_passt($neu))) { fwrite(STDERR, "NEUE_PUB ist kein öffentlicher Schlüssel auf " . KURVE . "\n"); exit(2); }
$pub_alt = oeffentlich($paar);
$pub_notfall = notfall_schluessel(dirname(KUNDE_DIR));
schluessel_pruefen($pub_alt, $pub_notfall, $neu ?: null);

if ($umschluesseln) {
    $fp_neu = schluessel_fp($neu);
    $alle = empfaenger_alle();
    $arten = array_map(fn($c) => fall_art($c, $paar, $fp_neu, $alle), array_column(get_all_cases(), null, "id"));
    if (($rc = einordnung_bewerten($arten, $pub_alt === $pub_notfall, $pruefen)) !== null) exit($rc);
    $gesetzt = mitglieder_umschluesseln($fp_neu);
    [$n, $fehler] = faelle_umschluesseln($paar, $neu, $fp_neu);
    $fehler += ueberschriebene_melden($gesetzt);
    echo count($gesetzt) . " Mitglieder, $n Meldungen umgeschlüsselt" . ($fehler ? ", $fehler Fehler" : "") . "\n";
    exit($fehler ? 1 : 0);
}

$frisch = $freigeben && freigeben($adresse);
$b = benutzer_laden($adresse) ?? [];
$alle = empfaenger_alle();
$fp = schluessel_fp(base64_decode((string)($b["pub"] ?? "")));
if (empty($b["pub"]) || !isset($alle[$fp])) {
    if ($frisch) freigabe_mail($b);   // approved all the same; a repeated run would not mail
    fwrite(STDERR, "$adresse: kein freigegebenes Mitglied mit Schlüssel und Eintrag in passwd\n"); exit(2);
}
[$n, $fehler] = faelle_verpacken($paar, $fp, $alle[$fp][0], $alle);
echo "$n Meldungen für $adresse verpackt" . ($fehler ? ", $fehler Fehler" : "") . "\n";
// The approval mail after wrapping, as in /team/; also after errors, a repeated run would not send it
if ($frisch) freigabe_mail($b);
exit($fehler ? 1 : 0);
