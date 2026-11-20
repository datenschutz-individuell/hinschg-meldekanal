<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// check-fonts.php – which font does the customer website use, and may the portal serve it itself?
// Only freely licensed fonts are adopted (Google Fonts: SIL OFL/Apache, self-hosting allowed). Licensed services
// (Adobe Fonts/Typekit, Fonts.com, Font Awesome Kits, MyFonts …) count page views – the portal probably has no
// licence for them: notice, adopt nothing. Self-hosted files of unknown licence: ask.
//
// Usage: php check-fonts.php <webseite> [<portal-ordner>]
//   without folder report only; with folder it downloads free fonts (woff2) there and writes fonts.css,
//   which style.css includes via @import "fonts.css"; (sets --schrift and --schrift-ueberschrift).
// Exit 0 adopted/nothing needed, 1 question needed (licence unclear/paid), 2 fetch failed.

const UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36";
const PFLICHTIG = '~use\.typekit\.net|p\.typekit\.net|fonts\.adobe\.com|fast\.fonts\.net|fonts\.com/|kit\.fontawesome\.com|'
    . 'cloud\.typography\.com|myfonts\.net|webfonts\.fontslive|monotype~i';

function hol(string $url): ?string {
    $c = stream_context_create(["http" => ["header" => "User-Agent: " . UA, "timeout" => 15, "follow_location" => 1]]);
    $t = @file_get_contents($url, false, $c);
    if ($t === false) { sleep(2); $t = @file_get_contents($url, false, $c); }   // Google occasionally doesn't answer
    return $t === false ? null : $t;
}

function absolut(string $url, string $basis): string {
    if (preg_match('~^https?://~', $url)) return $url;
    if (str_starts_with($url, "//")) return "https:$url";
    $p = parse_url($basis);
    $wurzel = "{$p['scheme']}://{$p['host']}";
    return str_starts_with($url, "/") ? $wurzel . $url : preg_replace('~/[^/]*$~', '/', $basis) . $url;
}

if ($argc < 2) { fwrite(STDERR, "Aufruf: php check-fonts.php <webseite> [<portal-ordner>]\n"); exit(2); }
$seite = $argv[1]; $ziel = $argv[2] ?? null;
$html = hol($seite);
if ($html === null) { echo "Webseite nicht abrufbar: $seite\n"; exit(2); }

// Collect stylesheets including @import – every rel=stylesheet counts, even without .css (e.g. /style.php)
$css = implode("\n", preg_match_all('~<style[^>]*>(.*?)</style>~si', $html, $m) ? $m[1] : []);
$quellen = [];
if (preg_match_all('~<link\b[^>]*>~i', $html, $m)) foreach ($m[0] as $tag)
    if (preg_match('~rel=["\']?(?:pre)?(?:stylesheet|load)~i', $tag) || preg_match('~\.css|fonts\.googleapis|typekit|fonts\.net|fontawesome~i', $tag))
        if (preg_match('~href=["\']([^"\']+)["\']~i', $tag, $h)) $quellen[] = $h[1];
$alle = [];
foreach (array_unique($quellen) as $u) {
    $u = absolut(html_entity_decode($u), $seite); $alle[] = $u;
    if (preg_match(PFLICHTIG, $u) || str_contains($u, "fonts.googleapis.com") || preg_match('~\.(woff2?|ttf|otf|png|jpe?g|svg|ico|js)(\?|$)~i', $u)) continue;
    $t = hol($u) ?? ""; $css .= "\n" . $t;
    if (preg_match_all('~@import\s+(?:url\()?["\']?([^"\')\s;]+)~i', $t, $im)) foreach ($im[1] as $i) {
        $alle[] = $iu = absolut($i, $u);
        if (!preg_match(PFLICHTIG, $iu) && !str_contains($iu, "fonts.googleapis.com")) $css .= "\n" . (hol($iu) ?? "");
    }
}
$alle = array_merge($alle, preg_match_all('~<script[^>]+src=["\']([^"\']+)["\']~i', $html, $m) ? $m[1] : []);
$css = preg_replace('~/\*.*?\*/~s', '', $css);

const SYSTEM = '~^(system-ui|ui-sans-serif|ui-serif|-apple-system|blinkmacsystemfont|sans-serif|serif|arial|helvetica|helvetica neue|verdana|georgia|tahoma|segoe ui|inherit)$~i';
// free = real Google family (lives under /s/<familie>/; for unknown names Google returns a substitute /l/font?kit=)
function google(string $f): ?string {
    $url = "https://fonts.googleapis.com/css2?family=" . urlencode($f) . ":wght@400;700&display=swap";
    for ($i = 0; $i < 3; $i++) {   // Google occasionally answers empty; a substitute (/l/) is a real answer though
        $g = hol($url);
        if ($g !== null && preg_match('~fonts\.gstatic\.com/s/~', $g)) return $g;
        if ($g !== null && str_contains($g, "@font-face")) return null;
        sleep(2);
    }
    return null;
}
// First family of a font-family value, var() resolved, spelling normalised
function familie(string $wert, string $css): string {
    for ($i = 0; $i < 5 && preg_match('~var\(\s*(--[\w-]+)~', $wert, $vm); $i++)
        $wert = preg_match('~' . preg_quote($vm[1]) . '\s*:\s*([^;}]+)~', $css, $wm) ? $wm[1] : "";
    $f = trim(explode(",", $wert)[0] ?? "", " \"'");
    $f = preg_replace('~(?<=[a-z])(?=[A-Z])~', ' ', $f);   // "OpenSans" → "Open Sans"
    return ["Source Sans Pro" => "Source Sans 3", "Source Serif Pro" => "Source Serif 4"][$f] ?? $f;
}
function angabe(string $selektor, string $css): string {
    return preg_match('~(?:^|[}\s,])(?:' . $selektor . ')\s*(?:,[^{]*)?\{[^}]*font-family\s*:\s*([^;}]+)~i', $css, $m) ? $m[1] : "";
}
$text = familie(angabe('body|html', $css), $css);
if (!$text && preg_match_all('~font-family\s*:\s*([^;}]+)~i', $css, $m)) {
    $z = array_count_values(array_map('trim', $m[1])); arsort($z); $text = familie((string)array_key_first($z), $css);
}
$ueber = familie(angabe('h1|h2|h1\s*,\s*h2', $css), $css);
if (!$ueber || preg_match('~^(inherit|initial|unset)$~i', $ueber)) $ueber = $text;
// Better than reading CSS: measure in the browser what is actually shown (measure-website.mjs, needs node + chromium)
$messung = null;
if (trim((string)shell_exec("command -v node chromium 2>/dev/null | wc -l")) === "2") {
    $mess = fn($u) => json_decode((string)shell_exec("timeout 60 node " . escapeshellarg(__DIR__ . "/measure-website.mjs") . " " . escapeshellarg($u) . " 2>/dev/null"), true);
    // Measure body text on a text page: home pages often consist of hero texts (e.g. 18–25px there, 13px in the imprint)
    $textseite = preg_match('~<a\b[^>]*href=["\']([^"\'#]*impressum[^"\'#]*)["\']~i', $html, $im) ? absolut(html_entity_decode($im[1]), $seite) : null;
    $messung = $textseite ? $mess($textseite) : null;
    if (is_array($messung) && !empty($messung["text"])) echo "(Größen gemessen auf $textseite)\n";
    else $messung = $mess($seite);
    if (is_array($messung) && !empty($messung["text"])) {
        $text = familie($messung["text"]["familie"], "");
        $h = $messung["h1"] ?? $messung["h2"] ?? null;
        $ueber = $h ? familie($h["familie"], "") : $text;
        echo "(gemessen im Browser)\n";
    } else $messung = null;
}
// Resolve alias families ("norm", "fett") via the file name in @font-face: share-regular.woff2 → Share
function echt(string $f, string $css): string {
    if (!preg_match('~@font-face\s*\{[^}]*font-family\s*:\s*["\']?' . preg_quote($f, '~') . '["\']?\s*;[^}]*url\(\s*["\']?([^"\')]+)~i', $css, $m)) return $f;
    $n = preg_replace('~\.(woff2?|ttf|otf|eot)$~i', '', basename(parse_url($m[1], PHP_URL_PATH) ?? ""));
    $n = preg_replace('~[-_ ]?(regular|bold|italic|bolditalic|light|medium|semibold|black|thin|normal|\d{3}|webfont|latin|ext|v\d+)+$~i', '', $n);
    $n = ucwords(trim(preg_replace('~[-_]+~', ' ', preg_replace('~[-_ ]?(regular|bold|italic|light|medium|semibold)\b~i', '', $n))));
    return $n ?: $f;
}
foreach (['text', 'ueber'] as $r) if ($$r && !preg_match(SYSTEM, $$r) && google($$r) === null) { $e = echt($$r, $css); if ($e !== $$r && google($e) !== null) { echo "„{$$r}“ ist ein Alias für „{$e}“\n"; $$r = $e; } }
echo "Textschrift: " . ($text ?: "(keine gefunden)") . "; Überschriften: " . ($ueber ?: "(keine gefunden)") . "\n";

// Font sizes of body text and h1–h3, converted to the portal's rem (base 16px). The site may shift the base
// (html { font-size:62.5% }) – then its rem count differently. clamp()/calc()/vw: not adoptable → default.
function groesse(string $sel, string $css): string {
    // last rule wins (cascade) – the first are often resets; only rules whose selector is exactly this element
    return preg_match_all('~(?:^|[}])\s*(?:' . $sel . ')\s*\{[^}]*font-size\s*:\s*([^;}]+)~i', $css, $m) ? trim(end($m[1])) : "";
}
function in_px(string $w, float $basis, float $eltern): ?float {
    $w = strtolower(trim(preg_replace('~\s*!important~', '', $w)));
    $wort = ["xx-small" => 9, "x-small" => 10, "small" => 13, "medium" => 16, "large" => 18, "x-large" => 24, "xx-large" => 32];
    if (isset($wort[$w])) return (float)$wort[$w];
    if (!preg_match('~^([\d.]+)(px|rem|em|%|pt)$~', $w, $m)) return null;
    return match ($m[2]) { "px" => (float)$m[1], "pt" => $m[1] * 4 / 3, "rem" => $m[1] * $basis, "em" => $m[1] * $eltern, "%" => $m[1] / 100 * $eltern };
}
$basis = in_px(groesse('html', $css) ?: "16px", 16, 16) ?? 16;
$koerper = in_px(groesse('body', $css), $basis, $basis);
$groessen = [];
foreach (["schriftgroesse" => [$koerper, 0.85, 1.3], "groesse-h1" => [in_px(groesse('h1', $css), $basis, $koerper ?? $basis), 1.3, 3],
          "groesse-h2" => [in_px(groesse('h2', $css), $basis, $koerper ?? $basis), 1, 2.4], "groesse-h3" => [in_px(groesse('h3', $css), $basis, $koerper ?? $basis), 0.9, 2]] as $n => [$px, $min, $max]) {
    if ($px === null) continue;
    $rem = round($px / 16, 3);
    if ($rem < $min || $rem > $max) { if (!$messung) echo "Größe $n = {$rem}rem außerhalb {$min}–{$max}rem – nicht übernommen\n"; continue; }
    $groessen[$n] = $rem;
}

$pflichtig = array_values(array_unique(array_filter($alle, fn($u) => preg_match(PFLICHTIG, $u))));
foreach ($pflichtig as $u) echo "LIZENZPFLICHTIG (zählt Aufrufe, für das Portal vermutlich keine Lizenz): $u\n";

$frei = []; $frage = false;
foreach (array_unique(array_filter([$text, $ueber])) as $f) {
    if (preg_match(SYSTEM, $f)) { echo "„{$f}“: Systemschrift – nichts zu übernehmen.\n"; continue; }
    $g = google($f);
    if ($g === null) { echo "FRAGE: „{$f}“ ist nicht als freie Schrift (Google Fonts) erkennbar – Lizenz beim Kunden erfragen; ohne Nachweis bleibt die Systemschrift.\n"; $frage = true; continue; }
    echo "FREI: „{$f}“ (Google Fonts, SIL OFL/Apache) – darf selbst ausgeliefert werden.\n"; $frei[$f] = $g;
}
if ($messung) {   // measured sizes, clamped for the form portal; headings clearly larger than the text
    $t = min(max($messung["text"]["px"], 14), 18);
    $px = fn($e) => !empty($messung[$e]["px"]) ? $messung[$e]["px"] : null;
    $h2 = min(max($px("h2") ?? 0, 1.3 * $t), 24);
    $h3 = min(max($px("h3") ?? 0, 1.15 * $t), 20);
    $h1 = min(max($px("h1") ?? 0, 24, 1.5 * $t, 1.2 * $h2), 36);
    $breite = null;
    if (!empty($messung["faktoren"]["zeichen"])) {   // ~75 characters per line in this font; heading "3. …" must fit on one line
        // factors of the font the portal will show: free → the site's, else system font
        $f = $messung["faktoren"]; $tsys = !isset($frei[$text]); $hsys = !isset($frei[$ueber]);
        $breite = 75 * ($tsys ? $f["zeichen_system"] : $f["zeichen"]) * $t;
        $passt = $breite / ($hsys ? $f["ueberschrift_system"] : $f["ueberschrift"]) * 0.97;
        if ($h2 > $passt) { $h2 = max($passt, 1.2 * $t); $h1 = min(max($px("h1") ?? 0, 24, 1.5 * $t, 1.2 * $h2), 36); }
    }
    $h3 = min($h3, 0.9 * $h2);
    $groessen = ["schriftgroesse" => $t, "groesse-h1" => $h1, "groesse-h2" => $h2, "groesse-h3" => $h3];
    $groessen = array_map(fn($v) => round($v / 16, 3), $groessen);
    if ($breite) $groessen["seitenbreite"] = round(($breite + 40) / 16, 2);   // + padding 2 × 1.25rem
}
echo "Größen (rem): " . ($groessen ? implode(", ", array_map(fn($k, $v) => "$k $v", array_keys($groessen), $groessen)) : "keine übernehmbar – Standard") . "\n";
if ($ziel === null) { echo "Übernehmen: php check-fonts.php $seite <portal-ordner>\n"; exit($frage || $pflichtig ? 1 : 0); }

// License text of a Google Fonts family from the official archive (OFL, Apache or Ubuntu Font Licence): the licences
// require a copy of the licence with every copy we serve. Returns [licence folder, file name, text] or null.
function licence(string $family): ?array {
    $slug = preg_replace('~[^a-z0-9]~', '', strtolower($family));
    foreach (["ofl" => "OFL.txt", "apache" => "LICENSE.txt", "ufl" => "UFL.txt"] as $dir => $file) {
        $text = hol("https://raw.githubusercontent.com/google/fonts/main/$dir/$slug/$file");
        if ($text !== null && strlen($text) > 200) return [$dir, $file, $text];
    }
    return null;
}

// Adopt: store woff2 locally, rewrite paths – no request to Google at runtime; Latin subsets only
$aus = "/* Schriften (nur frei lizenzierte, selbst ausgeliefert) und Größen der Kunden-Webseite; erzeugt von check-fonts.php */\n"; $nr = 0;
foreach ($frei as $f => $g) {
    $lic = licence($f);
    if ($lic === null) { echo "FRAGE: Lizenztext für „{$f}“ nicht im Google-Fonts-Archiv gefunden – nichts übernommen.\n"; exit(1); }
    $lizenzdatei = "LICENSE-" . preg_replace('~[^a-z0-9]+~', '-', strtolower($f)) . ".txt";
    if (file_put_contents("$ziel/$lizenzdatei", $lic[2]) === false) { echo "Lizenz nicht schreibbar: $ziel/$lizenzdatei\n"; exit(2); }
    $aus .= "/* $f – licence: " . strtoupper($lic[0]) . ", full text in /$lizenzdatei */\n";
    $g = implode("\n", array_filter(preg_split('~(?=/\* )~', $g), fn($b) => preg_match('~^/\* latin(-ext)? \*/~', $b)));
    $aus .= preg_replace_callback('~url\((https://fonts\.gstatic\.com/[^)]+)\)~', function ($m) use ($ziel, $f, &$nr) {
        $datei = "font-" . preg_replace('~[^a-z0-9]+~', '-', strtolower($f)) . "-" . (++$nr) . ".woff2";
        $inhalt = hol($m[1]);
        if ($inhalt === null || file_put_contents("$ziel/$datei", $inhalt) === false) { echo "Download gescheitert: {$m[1]}\n"; exit(2); }
        return "url(/$datei)";
    }, $g) . "\n";
}
$stapel = 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
$var = fn($f) => isset($frei[$f]) ? "\"$f\", $stapel" : $stapel;
$vars = $frei ? ["schrift" => $var($text), "schrift-ueberschrift" => $var($ueber)] : [];
foreach ($groessen as $n => $rem) $vars[$n] = "{$rem}rem";
$aus .= ":root { " . implode(" ", array_map(fn($k, $v) => "--$k:$v;", array_keys($vars), $vars)) . " }\n";
file_put_contents("$ziel/fonts.css", $aus);
echo "Übernommen: $nr Datei(en) und fonts.css nach $ziel; in style.css ganz oben: @import \"fonts.css\";\n";
exit($frage || $pflichtig ? 1 : 0);
