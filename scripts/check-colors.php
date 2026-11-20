<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// check-colors.php – contrasts of a portal per WCAG 2.1 AA, light and dark, without a browser.
// Resolves the colour variables from common/style.php and the customer style.css itself (hex, rgb(), hsl(), var(),
// color-mix in srgb) and follows the cascade: in dark mode, customer :root without @media overrides the dark
// values from style.php. Other colour notations are reported as uncheckable instead of guessing.
//
// Usage: php check-colors.php <style.php> <settings.json> [<style.css>]   (also via <(sudo cat …))
// Exit 0 all ≥ threshold, 1 too weak (with suggestion), 2 uncheckable/usage error.

// [foreground, background, minimum contrast, place]; 4.5 text, 3 control border (WCAG 1.4.3, 1.4.11)
const PAARE = [
    ["text", "hintergrund", 4.5, "Text"],
    ["text-leise", "hintergrund", 4.5, "leiser Text (Meldungs-ID, Zeichenzähler, Status)"],
    ["link", "hintergrund", 4.5, "Link"],
    ["link-besucht", "hintergrund", 4.5, "besuchter Link"],
    ["ueberschrift", "hintergrund", 4.5, "Überschrift"],
    ["erfolg", "hintergrund", 4.5, "Erfolgsmeldung"],
    ["fehler", "hintergrund", 4.5, "Fehlermeldung"],
    ["akzent-text", "akzent", 4.5, "Knopf"],
    ["text", "flaeche", 4.5, "Eingabefeld"],
    ["text-leise", "flaeche", 4.5, "Platzhalter im Eingabefeld"],
    ["text", "meldung-titel", 4.5, "Kopf der Meldungsliste"],
    ["chat-status", "hintergrund", 4.5, "Statuszeile im Verlauf"],
    ["chat-meldende-text", "chat-meldende", 4.5, "Nachricht meldende Person"],
    ["chat-meldende-titel-text", "chat-meldende-titel", 4.5, "Kopfzeile meldende Person"],
    ["chat-team-text", "chat-team", 4.5, "Nachricht Team"],
    ["chat-team-titel-text", "chat-team-titel", 4.5, "Kopfzeile Team"],
    ["feld-rand", "flaeche", 3, "Rand Eingabefeld"],
    ["feld-rand", "hintergrund", 3, "Rand Eingabefeld zur Seite"],
    ["akzent-linie", "hintergrund", 3, "Fokusrahmen und Symbolknöpfe"],
    ["akzent-linie", "flaeche", 3, "Fokusrahmen und Symbolknöpfe auf Fläche"],
    ["akzent-linie", "meldung", 3, "Bearbeiten-Knopf in der Teamliste"],
];

class NichtPruefbar extends Exception {}

// :root declarations in document order: [[name, wert, modus], …], modus "" = both, else hell/dunkel;
// @media print stays out.
function deklarationen(string $css): array {
    $css = preg_replace('~/\*.*?\*/~s', '', $css);
    $aus = []; $stapel = []; $kopf = ""; $n = strlen($css);
    for ($i = 0; $i < $n; $i++) {
        $c = $css[$i];
        if ($c === "{") { $stapel[] = trim($kopf); $kopf = ""; continue; }
        if ($c !== ";" && $c !== "}") { $kopf .= $c; continue; }
        // declaration ends with ";" or – without semicolon – with "}"
        if ($stapel && preg_match('/^(:root|html)$/', end($stapel))) {
            $modus = array_filter($stapel, fn($k) => preg_match('/prefers-color-scheme:\s*dark/', $k)) ? "dunkel"
                : (array_filter($stapel, fn($k) => preg_match('/prefers-color-scheme:\s*light/', $k)) ? "hell" : "");
            $druck = (bool)array_filter($stapel, fn($k) => preg_match('/@media\s+print/', $k));
            // names without "farbe-": so var(--farbe-text) and var(--farbton) resolve alike
            if (!$druck && preg_match('/^\s*--(?:farbe-)?([\w-]+)\s*:\s*(.+?)\s*$/s', $kopf, $m)) $aus[] = [$m[1], $m[2], $modus];
        }
        $kopf = "";
        if ($c === "}") array_pop($stapel);
    }
    return $aus;
}

function einsetzen(string $wert, array $v, int $tiefe = 0): string {
    if ($tiefe > 20) throw new NichtPruefbar("Variablen-Schleife bei '$wert'");
    return preg_replace_callback('/var\(\s*--(?:farbe-)?([\w-]+)\s*(?:,([^()]*))?\)/', function ($m) use ($v, $tiefe) {
        $name = $m[1];
        if (isset($v[$name])) return einsetzen($v[$name], $v, $tiefe + 1);
        if (isset($m[2]) && trim($m[2]) !== "") return einsetzen(trim($m[2]), $v, $tiefe + 1);
        throw new NichtPruefbar("--farbe-$name nicht gesetzt");
    }, $wert);
}

function argumente(string $s): array {  // split at top-level commas
    $teile = []; $tiefe = 0; $akt = "";
    foreach (str_split($s) as $c) {
        if ($c === "(") $tiefe++;
        if ($c === ")") $tiefe--;
        if ($c === "," && $tiefe === 0) { $teile[] = trim($akt); $akt = ""; } else $akt .= $c;
    }
    $teile[] = trim($akt);
    return $teile;
}

function farbe(string $s): array {
    $s = strtolower(trim($s));
    if ($s === "white") return [255, 255, 255];
    if ($s === "black") return [0, 0, 0];
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $s, $m)) {
        $h = strlen($m[1]) === 3 ? preg_replace('/(.)/', '$1$1', $m[1]) : $m[1];
        return array_map('hexdec', str_split($h, 2));
    }
    if (preg_match('/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)\s*(?:[,\/]\s*1(?:\.0*)?\s*)?\)$/', $s, $m))
        return [(float)$m[1], (float)$m[2], (float)$m[3]];
    if (preg_match('/^hsl\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%\s*\)$/', $s, $m)) {
        [$h, $sa, $l] = [fmod((float)$m[1], 360) / 360, $m[2] / 100, $m[3] / 100];
        $f = function ($n) use ($h, $sa, $l) {
            $k = fmod($n + $h * 12, 12);
            return 255 * ($l - $sa * min($l, 1 - $l) * max(-1, min($k - 3, 9 - $k, 1)));
        };
        return [$f(0), $f(8), $f(4)];
    }
    if (preg_match('/^color-mix\(\s*in\s+srgb\s*,(.*)\)$/s', $s, $m)) {
        $a = argumente($m[1]);
        if (count($a) !== 2) throw new NichtPruefbar("color-mix '$s'");
        $teil = function ($t) { return preg_match('/^(.*\S)\s+([\d.]+)%$/s', $t, $x) ? [$x[1], $x[2] / 100] : [$t, null]; };
        [[$f1, $p1], [$f2, $p2]] = [$teil($a[0]), $teil($a[1])];
        $p1 ??= $p2 !== null ? 1 - $p2 : 0.5;
        [$c1, $c2] = [farbe($f1), farbe($f2)];
        return array_map(fn($x, $y) => $x * $p1 + $y * (1 - $p1), $c1, $c2);
    }
    throw new NichtPruefbar("Farbe '$s' (erlaubt: #hex, rgb(), hsl(), color-mix in srgb, var())");
}

function luminanz(array $c): float {
    $l = array_map(function ($v) { $v /= 255; return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }, $c);
    return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

function kontrast(array $a, array $b): float {
    [$x, $y] = [luminanz($a), luminanz($b)];
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

// MAIN – only when called directly; derive-colors.php includes the functions
if (realpath($argv[0] ?? "") !== __FILE__) return;
if ($argc < 3) { fwrite(STDERR, "Aufruf: php check-colors.php <style.php> <settings.json> [<style.css>]\n"); exit(2); }
// Empty or unreadable input must never pass as "passed" (e.g. /dev/fd pipes)
function lies(string $datei): string {
    $inhalt = @file_get_contents($datei);
    if ($inhalt === false) { fwrite(STDERR, "nicht lesbar: $datei\n"); exit(2); }
    return $inhalt;
}
$basis = lies($argv[1]);
if (!str_contains($basis, "--farbe-text:")) { fwrite(STDERR, "$argv[1]: keine Farbvariablen – falsche Datei?\n"); exit(2); }
$einst = json_decode(lies($argv[2]), true);
if (!is_array($einst)) { fwrite(STDERR, "$argv[2]: kein JSON\n"); exit(2); }
$kunde = $argc > 3 ? lies($argv[3]) : "";
// as style.php: hue from settings.json, else 220
$farbton = is_numeric($einst["Farbton"] ?? "") ? (int)$einst["Farbton"] % 360 : 220;
$basis = str_replace('<?=$farbton?>', (string)$farbton, preg_replace('/^<\?php.*?\?>/s', '', $basis));
$alle = array_merge(deklarationen($basis), deklarationen($kunde));

$schwach = []; $code = 0;
foreach (["hell", "dunkel"] as $modus) {
    $v = [];
    foreach ($alle as [$name, $wert, $m]) if ($m === "" || $m === $modus) $v[$name] = $wert;
    foreach (PAARE as [$vg, $hg, $min, $ort]) {
        if (!isset($v[$vg])) continue;   // e.g. --farbe-ueberschrift is optional
        try {
            [$fv, $fh] = [farbe(einsetzen($v[$vg], $v)), farbe(einsetzen($v[$hg], $v))];
        } catch (NichtPruefbar $e) {
            echo "$modus: $ort nicht prüfbar: {$e->getMessage()}\n"; $code = 2; continue;
        }
        $k = kontrast($fv, $fh);
        if ($k + 1e-9 < $min) {
            // for text on a coloured area suggest the better of the two base tones
            $vorschlag = "";
            if ($vg === "akzent-linie" && kontrast(farbe(einsetzen($v["link"], $v)), $fh) >= $min) {
                $vorschlag = ", Vorschlag: --farbe-akzent-linie:var(--farbe-link)";
            } elseif ($min > 3) {
                $best = kontrast([34, 34, 34], $fh) >= kontrast([255, 255, 255], $fh) ? "#222" : "#fff";
                if (kontrast(farbe($best), $fh) >= $min) $vorschlag = ", Vorschlag: --farbe-$vg:$best";
            }
            $schwach[] = sprintf("%s: %s %.2f:1 statt %s:1 (--farbe-%s auf --farbe-%s)%s", $modus, $ort, $k, $min, $vg, $hg, $vorschlag);
        }
    }
}
foreach ($schwach as $z) echo "$z\n";
exit($code ?: ($schwach ? 1 : 0));
