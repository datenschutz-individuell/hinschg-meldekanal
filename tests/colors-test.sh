#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Tests for scripts/check-colors.php (no browser): base design, light/dark cascade, suggestions, error cases.
set -u
cd "$(dirname "$0")/.." || exit 2
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
ok=0; fehler=0
pruef() { if eval "$2"; then ok=$((ok+1)); else echo "FEHLER: $1"; fehler=$((fehler+1)); fi; }
F() { php scripts/check-colors.php portal/common/style.php "$T/s.json" "$@" >"$T/aus" 2>&1; echo $? >"$T/rc"; }
rc() { [ "$(cat "$T/rc")" = "$1" ]; }
echo '{"Farbton":"220"}' > "$T/s.json"

F; pruef "Grundgestaltung ohne Kunden-CSS besteht" 'rc 0'
F portal/template/style.css; pruef "Vorlage style.css (nur Kommentar) besteht" 'rc 0'
for h in 0 60 120 180 240 300; do echo "{\"Farbton\":\"$h\"}" > "$T/s.json"; F
    pruef "Grundgestaltung mit Farbton $h besteht" 'rc 0'; done
echo '{}' > "$T/s.json"

# White text on brand orange
printf ':root { --farbe-chat-meldende-titel:#ff9934; --farbe-chat-meldende-titel-text:#fff; }\n' > "$T/a.css"; F "$T/a.css"
pruef "weiß auf Orange fällt durch" 'rc 1 && grep -q "Kopfzeile meldende Person 2.1" "$T/aus"'
pruef "... mit Vorschlag dunkle Schrift" 'grep -q "Vorschlag: --farbe-chat-meldende-titel-text:#222" "$T/aus"'

# Cascade: customer :root without @media also applies in dark mode, so it overrides style.php's dark values
printf ':root { --farbe-hintergrund:#fff; --farbe-text-leise:#777; }\n' > "$T/k.css"; F "$T/k.css"
pruef "Kunden-:root wirkt in beiden Modi" 'rc 1 && grep -q "^hell: leiser Text" "$T/aus" && grep -q "^dunkel: leiser Text" "$T/aus"'
printf ':root { --farbe-text-leise:#666; }\n@media (prefers-color-scheme: dark) { :root { --farbe-text-leise:#aaa; } }\n' > "$T/k.css"; F "$T/k.css"
pruef "dunkler Kundenblock gilt nur dunkel" 'rc 0'

# Notations: rgb, hsl with commas, short hex, var with fallback
printf '@media (prefers-color-scheme: light) { :root { --farbe-akzent:rgb(20, 40, 90); --farbe-akzent-text:hsl(0, 0%%, 100%%); --farbe-link:#13a; --farbe-chat-team-titel-text:var(--gibts-nicht, #111); } }\n' > "$T/w.css"; F "$T/w.css"
pruef "rgb(), hsl() mit Kommas, Kurz-Hex, var-Ersatz; light-Block nur hell" 'rc 0'
printf ':root{--farbe-text-leise:#bbb}' > "$T/o.css"; F "$T/o.css"
pruef "letzte Deklaration ohne Semikolon zählt" 'rc 1 && grep -q "leiser Text" "$T/aus"'
printf 'html { --farbe-text-leise:#bbb; }' > "$T/o.css"; F "$T/o.css"
pruef "Selektor html wie :root" 'rc 1'
printf '@media print { :root { --farbe-text:#fff; } }\n' > "$T/d.css"; F "$T/d.css"
pruef "@media print bleibt draußen" 'rc 0'

# Focus ring and outline icon buttons follow the button colour unless --farbe-akzent-linie is set
printf ':root { --farbe-akzent:#ff9934; --farbe-akzent-text:#222; }\n' > "$T/l.css"; F "$T/l.css"
pruef "helle Knopffarbe: Fokusrahmen fällt durch, mit Vorschlag Link-Farbe" 'rc 1 && grep -q "Fokusrahmen und Symbolknöpfe 1.97.*Vorschlag: --farbe-akzent-linie:var(--farbe-link)" "$T/aus"'
printf ':root { --farbe-akzent:#ff9934; --farbe-akzent-text:#222; --farbe-akzent-linie:#a65100; }\n@media (prefers-color-scheme: dark) { :root { --farbe-akzent-linie:#ff9934; } }\n' > "$T/l.css"; F "$T/l.css"
pruef "... eigene --farbe-akzent-linie behebt es" 'rc 0'

printf ':root { --farbe-text:oklch(0.3 0.1 200); }\n' > "$T/u.css"; F "$T/u.css"
pruef "unbekannte Schreibweise: nicht prüfbar, Exit 2" 'rc 2 && grep -q "nicht prüfbar" "$T/aus"'
printf ':root { --farbe-text:var(--farbe-x); --farbe-x:var(--farbe-text); }\n' > "$T/s2.css"; F "$T/s2.css"
pruef "Variablen-Schleife: Exit 2" 'rc 2'

php scripts/check-colors.php portal/common/style.php "$T/gibtsnicht.json" >/dev/null 2>&1
pruef "unlesbare Eingabe: Exit 2, nie bestanden" '[ $? -eq 2 ]'
: > "$T/leer.php"; php scripts/check-colors.php "$T/leer.php" "$T/s.json" >/dev/null 2>&1
pruef "leere style.php: Exit 2" '[ $? -eq 2 ]'

echo "$ok ok, $fehler Fehler"
[ "$fehler" -eq 0 ]
