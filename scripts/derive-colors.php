<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// derive-colors.php – computes the text, status and bar colours of a portal's message thread from its
// party colours (--farbe-partei-meldende/-team in style.css) and prints them as CSS to append to style.css.
// Usage: php derive-colors.php <style.php> <settings.json> <style.css> >> style.css   (then check-colors.php)
// Rules: README, section Farben.
// Per mode: header font = party colour mixed as little toward black/white as possible, message text = grey as close
// to the background as possible – both just ≥ 4.6:1. Prints CSS variables.
require __DIR__ . "/check-colors.php";
[$basis, $einst, $kunde] = [file_get_contents($argv[1]), json_decode(file_get_contents($argv[2]), true) ?: [], file_get_contents($argv[3])];
$farbton = is_numeric($einst["Farbton"] ?? "") ? (int)$einst["Farbton"] % 360 : 220;
$basis = str_replace('<?=$farbton?>', (string)$farbton, preg_replace('/^<\?php.*?\?>/s', '', $basis));
$alle = array_merge(deklarationen($basis), deklarationen($kunde));
$hex = fn($c) => sprintf("#%02x%02x%02x", ...array_map(fn($x) => max(0, min(255, round($x))), $c));
foreach (["hell", "dunkel"] as $modus) {
    $v = []; foreach ($alle as [$n, $w, $m]) if ($m === "" || $m === $modus) $v[$n] = $w;
    $ziel = $modus === "hell" ? [0, 0, 0] : [255, 255, 255];
    $z = [];
    foreach (["meldende", "team"] as $p) {
        $partei = farbe(einsetzen($v["partei-$p"], $v));
        $titel = farbe(einsetzen($v["chat-$p-titel"], $v)); $flaeche = farbe(einsetzen($v["chat-$p"], $v));
        for ($a = 1.0; $a >= 0; $a -= 0.01) { $c = array_map(fn($x, $y) => $x * $a + $y * (1 - $a), $partei, $ziel); if (kontrast($c, $titel) >= 4.6) break; }
        $c = array_map(fn($x, $y) => ($x + 2 * $y) / 3, $c, $ziel); $z[] = "--farbe-chat-$p-titel-text:" . $hex($c);  // two thirds toward black/white
        $g0 = $modus === "hell" ? 255 : 0; $schritt = $modus === "hell" ? -1 : 1;
        for ($g = $g0; $g >= 0 && $g <= 255; $g += $schritt) if (kontrast([$g, $g, $g], $flaeche) >= 4.6) break;
        $g = ($g + 2 * ($modus === "hell" ? 0 : 255)) / 3; $z[] = "--farbe-chat-$p-text:" . $hex([$g, $g, $g]);
    }
    $hg = farbe(einsetzen($v["hintergrund"], $v));
    for ($g = $modus === "hell" ? 255 : 0; $g >= 0 && $g <= 255; $g += $modus === "hell" ? -1 : 1) if (kontrast([$g, $g, $g], $hg) >= 4.6) break;
    $z[] = "--farbe-chat-status:" . $hex([$g, $g, $g]);   // as little contrast as allowed
    foreach (["meldende", "team"] as $p) {   // bar must stand out from the page (≥ 2:1), else shift toward text
        // opposite direction if the background itself is near black/white (else endless loop)
        foreach ($modus === "hell" ? [[0,0,0], [255,255,255]] : [[255,255,255], [0,0,0]] as $richtung) {
            $b = farbe(einsetzen($v["partei-$p"], $v));
            for ($i = 0; $i < 100 && kontrast($b, $hg) < 2; $i++) $b = array_map(fn($x, $y) => $x * 0.9 + $y * 0.1, $b, $richtung);
            if (kontrast($b, $hg) >= 2) break;
        }
        if (kontrast($b, $hg) < 2) fwrite(STDERR, "Balken $p ($modus) hebt sich nicht ab (< 2:1): Hintergrund ändern\n");
        $z[] = "--farbe-chat-$p-balken:" . $hex($b);
    }
    $inhalt = implode("; ", $z);
    echo $modus === "hell" ? "@media (prefers-color-scheme: light) { :root { $inhalt } }\n" : "@media (prefers-color-scheme: dark) { :root { $inhalt } }\n";
}
