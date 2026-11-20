#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Tests for scripts/logo-light-dark.py (no browser): build, check, raster images, recoloring.
set -u
cd "$(dirname "$0")/.." || exit 2
G="python3 -I scripts/logo-light-dark.py"
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
ok=0; fehler=0
pruef() { if eval "$2"; then ok=$((ok+1)); else echo "FEHLER: $1"; fehler=$((fehler+1)); fi; }
xml() { python3 -I -c 'import sys,xml.dom.minidom; xml.dom.minidom.parse(sys.argv[1])' "$1" 2>/dev/null; }

cat > "$T/h.svg" <<'EOF'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" viewBox="0 0 200 50"><defs><linearGradient id="g"><stop offset="0" stop-color="#000"/></linearGradient></defs><rect id="r" inkscape:label="x" width="200" height="50" fill="url(#g)"/></svg>
EOF
cat > "$T/d.svg" <<'EOF'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 100"><defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient></defs><rect id="r" width="400" height="100" fill="url(#g)"/><use href="#r"/></svg>
EOF

# 1. Two SVG versions
$G "$T/h.svg" "$T/d.svg" -o "$T/a.svg" 2>"$T/err"
pruef "Paar: gültiges XML (Namensraum inkscape übernommen)" 'xml "$T/a.svg"'
pruef "Paar: --pruefen erkennt die Umstellung" '$G --pruefen "$T/a.svg" >/dev/null'
pruef "Paar: helle und dunkle Fassung enthalten" 'grep -q "class=\"__hd-h\"" "$T/a.svg" && grep -q "class=\"__hd-d\"" "$T/a.svg"'
pruef "Paar: ids der dunklen Fassung umbenannt, Verweise mit" 'grep -q "id=\"dunkel-g\"" "$T/a.svg" && grep -q "url(#dunkel-g)" "$T/a.svg" && grep -q "href=\"#dunkel-r\"" "$T/a.svg" && grep -q "url(#g)" "$T/a.svg"'
pruef "Paar: gleiches Seitenverhältnis, kein Hinweis" '! grep -q Seitenverhältnis "$T/err"'

# Dark version's classes/styles kept apart, root attributes inherited, commas in viewBox, stroke-width
printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 2" width="250" height="50" fill="none"><style>.cls-1{stroke:#000}</style><path class="cls-1 x" d="M0 0"/></svg>' > "$T/ih.svg"
printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0,10,2"><style>.cls-1{stroke:#fff}</style><path class="cls-1" d="M0 0"/></svg>' > "$T/id.svg"
$G "$T/ih.svg" "$T/id.svg" -o "$T/i.svg" 2>"$T/err"
pruef "Paar: <style> je Fassung eingegrenzt" 'grep -q "\.__hd-d \.cls-1{stroke:#fff}" "$T/i.svg" && grep -q "\.__hd-h \.cls-1{stroke:#000}" "$T/i.svg"'
printf '<svg xmlns="http://www.w3.org/2000/svg" class="logo" viewBox="0 0 1 1"><style>path,#a{fill:#fff}</style><path id="a" d="M0 0"/></svg>' > "$T/ty.svg"
$G "$T/h.svg" "$T/ty.svg" -o "$T/ty2.svg"
pruef "Paar: Typ- und #id-Selektoren eingegrenzt, class der Wurzel kein Doppel" 'grep -q "\.__hd-d path,\.__hd-d #dunkel-a{fill:#fff}" "$T/ty2.svg" && xml "$T/ty2.svg"'
pruef "Paar: fill der Wurzel aufs innere svg, width/height außen" 'grep -q "class=\"__hd-h\" viewBox=\"0 0 10 2\" fill=\"none\"" "$T/i.svg" && grep -q "^<svg [^>]*width=\"250\" height=\"50\">" "$T/i.svg" && xml "$T/i.svg"'
printf '<svg xmlns="http://www.w3.org/2000/svg" stroke-width="2" width="24" height="12"><path d="M0 0"/></svg>' > "$T/sw.svg"
$G "$T/sw.svg" "$T/sw.svg" -o "$T/sw2.svg" 2>/dev/null
pruef "Paar: stroke-width nicht als width gelesen" 'grep -q "viewBox=\"0 0 24 12\"" "$T/sw2.svg"'
printf '<svg xmlns="http://www.w3.org/2000/svg" xmlns:x="&ns_x;" viewBox="0 0 1 1"><path d="M0 0"/></svg>' > "$T/ent.svg"
pruef "Ungültiges XML im Ergebnis → Fehler, keine Datei" '! $G "$T/ent.svg" "$T/d.svg" -o "$T/ent2.svg" 2>"$T/err" && grep -q "kein gültiges XML" "$T/err" && [ ! -e "$T/ent2.svg" ]'
printf '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="svg2" class="logo" viewBox="0 0 1 1"><style>svg{fill:red}.logo path,#svg2 rect{fill:red}@keyframes k{from{opacity:0}to{opacity:1}}</style><path id='"'r'"' d="M0 0"/><use xlink:href='"'#r'"'/></svg>' > "$T/w.svg"
$G "$T/h.svg" "$T/w.svg" -o "$T/w2.svg"
pruef "Paar: Wurzel-Selektoren, @keyframes, ids in einfachen Quotes" 'grep -q "<style>\.__hd-d{fill:red}\.__hd-d path,\.__hd-d rect{fill:red}@keyframes k{from{opacity:0}to{opacity:1}}" "$T/w2.svg" && grep -q "id='"'dunkel-r'"'" "$T/w2.svg" && grep -q "href='"'#dunkel-r'"'" "$T/w2.svg" && xml "$T/w2.svg"'
printf '<svg xmlns="http://www.w3.org/2000/svg"><style><![CDATA[@media (prefers-color-scheme: dark){path{fill:#fff}}]]></style></svg>' > "$T/cd.svg"
pruef "--pruefen: Stil in CDATA erkannt" '$G --pruefen "$T/cd.svg" >/dev/null'

pruef "Ausgabe = Eingabe → Fehler, Eingabe bleibt" 'cp "$T/h.svg" "$T/same.svg"; ! $G "$T/same.svg" "$T/d.svg" -o "$T/same.svg" 2>/dev/null && cmp -s "$T/h.svg" "$T/same.svg"'

# 2. Differing aspect ratio is reported
sed 's/0 0 400 100/0 0 400 300/' "$T/d.svg" > "$T/d2.svg"
$G "$T/h.svg" "$T/d2.svg" -o "$T/b.svg" 2>"$T/err"
pruef "Seitenverhältnis: Hinweis" 'grep -q Seitenverhältnis "$T/err"'

# 3. Check rejects a plain SVG
pruef "--pruefen: gewöhnliches SVG → Exit 1" '! $G --pruefen "$T/h.svg" >/dev/null'

# 4. Recoloring a single SVG
printf '<svg xmlns="http://www.w3.org/2000/svg" width="879" height="224"><path fill="#0D71B4" d="M0 0h10v10z"/></svg>' > "$T/k.svg"
$G "$T/k.svg" --farbe '#0d71b4=#37a6f1' -o "$T/c.svg"
pruef "Farbe: viewBox aus width/height" 'grep -q "viewBox=\"0 0 879 224\"" "$T/c.svg"'
pruef "Farbe: Regel trifft jede Schreibweise" 'grep -q "\[fill=\"#0d71b4\" i\]{fill:#37a6f1}" "$T/c.svg"'
pruef "Farbe: ALT kommt nicht vor → Fehler" '! $G "$T/k.svg" --farbe "#fff=#000" -o "$T/x.svg" 2>/dev/null'
pruef "Farbe: --pruefen ok, XML gültig" '$G --pruefen "$T/c.svg" >/dev/null && xml "$T/c.svg"'
printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path style="fill:#000" d="M0 0"/><path fill="#000" d="M0 0"/></svg>' > "$T/s.svg"
$G "$T/s.svg" --farbe '#000=#fff' -o "$T/s2.svg" 2>"$T/err"
pruef "Farbe: Hinweis bei style=\"fill:…\"" 'grep -q "style=" "$T/err"'
printf '<?xml version="1.0"?>\n<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><stop stop-color="#0D71B4"/></svg>' > "$T/v.svg"
$G "$T/v.svg" --farbe '#0d71b4=#37a6f1' -o "$T/v2.svg"
pruef "Farbe: stop-color umgefärbt, Prolog bleibt" 'grep -q "\[stop-color=\"#0d71b4\" i\]{stop-color:#37a6f1}" "$T/v2.svg" && head -1 "$T/v2.svg" | grep -q "^<?xml" && xml "$T/v2.svg"'

$G "$T/k.svg" --gleich -o "$T/g.svg"
pruef "--gleich: unverändert, aber als geprüft markiert" '$G --pruefen "$T/g.svg" >/dev/null && grep -q "fill=\"#0D71B4\"" "$T/g.svg" && xml "$T/g.svg"'

# 5. Raster images: real PNG, WebP/JPEG only with a valid header (dimensions)
python3 -I - "$T" <<'EOF'
import sys, zlib, struct
t = sys.argv[1]
def png(w, h):
    roh = b"".join(b"\0" + b"\xff\xff\xff\xff" * w for _ in range(h))
    ch = lambda k, d: struct.pack(">I", len(d)) + k + d + struct.pack(">I", zlib.crc32(k + d))
    return b"\x89PNG\r\n\x1a\n" + ch(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 6, 0, 0, 0)) + ch(b"IDAT", zlib.compress(roh)) + ch(b"IEND", b"")
open(f"{t}/h.png", "wb").write(png(40, 10))
open(f"{t}/d.webp", "wb").write(b"RIFF" + struct.pack("<I", 22) + b"WEBPVP8X" + struct.pack("<I", 10) + b"\0" * 4 + (79).to_bytes(3, "little") + (19).to_bytes(3, "little"))
open(f"{t}/d.jpg", "wb").write(b"\xff\xd8\xff\xe0\x00\x04\x00\x00\xff\xc0\x00\x11\x08" + struct.pack(">HH", 20, 80) + b"\x03")
EOF
$G "$T/h.png" "$T/d.webp" -o "$T/r.svg"
pruef "Raster: PNG und WebP eingebettet, Maße" 'grep -q "viewBox=\"0 0 40 10\"" "$T/r.svg" && grep -q "data:image/png;base64" "$T/r.svg" && grep -q "viewBox=\"0 0 80 20\"" "$T/r.svg" && grep -q "data:image/webp;base64" "$T/r.svg"'
pruef "Raster: XML gültig, --pruefen ok" 'xml "$T/r.svg" && $G --pruefen "$T/r.svg" >/dev/null'
$G "$T/h.png" "$T/d.jpg" -o "$T/j.svg"
pruef "Raster: JPEG-Maße" 'grep -q "viewBox=\"0 0 80 20\"" "$T/j.svg" && grep -q "data:image/jpeg" "$T/j.svg"'

# 6. Wrong invocations
pruef "Aufruf: DUNKEL und --farbe zugleich → Fehler" '! $G "$T/h.svg" "$T/d.svg" --farbe "#000=#fff" -o "$T/x.svg" 2>/dev/null'
pruef "Aufruf: unbekanntes Format → Fehler" 'echo hallo > "$T/x.txt"; ! $G "$T/x.txt" "$T/d.svg" -o "$T/x.svg" 2>/dev/null'

# 8. Edge cases: abort with a distinct exit code instead of silent misrendering; the script itself straightens rotated JPEGs
printf '<svg xmlns="http://www.w3.org/2000/svg" xmlns:a="urn:eins" viewBox="0 0 1 1"><path d="M0 0"/></svg>' > "$T/n1.svg"
printf '<svg xmlns="http://www.w3.org/2000/svg" xmlns:a="urn:zwei" viewBox="0 0 1 1"><path d="M0 0"/></svg>' > "$T/n2.svg"
$G "$T/n1.svg" "$T/n2.svg" -o "$T/n.svg" 2>/dev/null; rc=$?
pruef "Sonderfall: Namensraum-Kollision → Exit 3" '[ $rc -eq 3 ] && [ ! -e "$T/n.svg" ]'
printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><style>@keyframes k{from{opacity:0}}</style><path d="M0 0"/></svg>' > "$T/k.svg"
$G "$T/k.svg" "$T/k.svg" -o "$T/k2.svg" 2>/dev/null; rc=$?
pruef "Sonderfall: gleichnamige @keyframes → Exit 4" '[ $rc -eq 4 ]'
python3 -I -c 'import sys,struct; d=open(sys.argv[1],"rb").read(); tiff=b"II*\0"+struct.pack("<I",8)+struct.pack("<H",1)+struct.pack("<HHIHH",0x0112,3,1,6,0)+b"\0\0\0\0"; app=b"Exif\0\0"+tiff; open(sys.argv[2],"wb").write(d[:2]+b"\xff\xe1"+struct.pack(">H",len(app)+2)+app+d[2:])' "$T/d.jpg" "$T/rot.jpg"
$G "$T/h.png" "$T/rot.jpg" -o "$T/rot.svg" 2>"$T/err"; rc=$?
pruef "JPEG mit EXIF-Drehung 6: selbst gedreht, Maße getauscht, EXIF auf 1" '[ $rc -eq 0 ] && grep -q "viewBox=\"0 0 20 80\"" "$T/rot.svg" && grep -q "transform=\"matrix(0 1 -1 0 20 0)\"" "$T/rot.svg" && python3 -I -c "import sys,re,base64; d=base64.b64decode(re.findall(r\"data:image/jpeg;base64,([^\\\"]+)\", open(sys.argv[1]).read())[0]); i=d.find(b\"\\x12\\x01\\x03\\x00\"); sys.exit(d[i+8:i+10]!=b\"\\x01\\x00\")" "$T/rot.svg"'

# 7. The repo template meets the requirement
pruef "Vorlage: portal/template/logo.svg stellt sich um, keine logo-dark.*" '$G --pruefen portal/template/logo.svg >/dev/null && ! ls portal/template/logo-dark.* 2>/dev/null'

echo "$ok ok, $fehler Fehler"
[ "$fehler" -eq 0 ]
