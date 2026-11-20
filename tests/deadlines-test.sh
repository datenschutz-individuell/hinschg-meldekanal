#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Tests for scripts/deadlines.php with generated reports (no real data). Usage: tests/deadlines-test.sh
set -u
cd "$(dirname "$0")/.."; S=scripts/deadlines.php; T=$(mktemp -d); trap 'rm -rf "$T"' EXIT; f=0
ok() { if eval "$2"; then echo "ok   $1"; else echo "FEHL $1"; f=1; fi; }
d() { date -d "$1" "+%Y-%m-%d %H:%M:%S"; }
m() { mkdir -p "$T/$1/cases" "$T/$1/portal"; printf '%s' "$3" > "$T/$1/cases/$2.json"; }
ein() { printf '{"person":"meldende_person","date":"%s","text":"%s"}' "$(d "$1")" "${2:-GEHEIMTEXT4711}"; }
st() { printf '{"person":"t@example.org","date":"%s","status":"%s"}' "$(d "$1")" "$2"; }
tx() { printf '{"person":"t@example.org","date":"%s","text":"x"}' "$(d "$1")"; }
fx() { php -r 'require "'$S'"; $s=new DateTimeImmutable($argv[1]); echo fristende($s,(int)$argv[2],(int)($argv[3]??0))->format("Y-m-d");' "$@"; }
export FRISTEN_STAND="$T/stand"
ok "7 Tage nach Eingang" '[ "$(fx 2026-01-01 0 7)" = 2026-01-08 ]'
ok "3 Monate, Monatsende gekappt" '[ "$(fx 2025-11-30 3)" = 2026-02-28 ]'
ok "3 Jahre ab 29.02." '[ "$(fx 2024-02-29 36)" = 2027-02-28 ]'
ok "3 Monate und 7 Tage" '[ "$(fx 2026-01-31 3 7)" = 2026-05-07 ]'
# tree "gut"
m gut/k1 AAAAAAAA "{\"status\":\"unbearbeitet\",\"conversation\":[$(ein '-6 days')]}"
m gut/k1 BBBBBBBB "{\"status\":\"unbearbeitet\",\"conversation\":[$(ein '-10 days')]}"
m gut/k1 CCCCCCCC "{\"status\":\"offen\",\"conversation\":[$(ein '-4 months'),$(st '-4 months' offen),$(tx '-4 months')]}"
m gut/k1 DDDDDDDD "{\"status\":\"offen\",\"conversation\":[$(ein '-4 months'),$(st '-4 months' offen),$(tx '-4 months'),$(tx '-1 month')]}"
m gut/k1 OOOOOOOO "{\"status\":\"offen\",\"conversation\":[$(ein '-4 months'),$(st '-4 months' offen)]}"
m gut/k1 PPPPPPPP "{\"status\":\"offen\",\"conversation\":[$(ein '-4 months'),$(st '-4 months' offen),$(st '-3 months' geschlossen),$(st '-2 months' offen)]}"
m gut/k1 MMMMMMMM "{\"status\":\"offen\",\"conversation\":[$(ein '-4 months'),$(st '-4 months' offen),$(tx '-4 months +5 minutes')]}"
m gut/k1 NNNNNNNN "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-5 years'),$(st '-4 years' geschlossen)]}"
m gut/k1 QQQQQQQQ "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-4 years'),$(st '-3 years +10 days' geschlossen)]}"
m gut/k1 SSSSSSSS "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-6 years'),$(st '-5 years' geschlossen)]}"
m gut/k1 TTTTTTTT "{\"status\":\"in Prüfung\",\"conversation\":[$(ein '-10 days')]}"
m gut/k2 EEEEEEEE "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-5 years'),$(st '-4 years' geschlossen)]}"
m gut/k2 FFFFFFFF "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-4 years'),$(st '-3 years +1 day' geschlossen)]}"
m gut/k2 GGGGGGGG "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-1 year'),$(st '-1 year' geschlossen)]}"
m gut/k2 HHHHHHHH "{\"status\":\"offen\",\"conversation\":[$(ein '-1 year'),{\"person\":\"meldende_person\",\"date\":\"$(d '-1 year')\",\"action\":\"invalidate_token\"}]}"
printf '{"k1/NNNNNNNN":"%s","k2/EEEEEEEE":"%s"}' "$(date -d '-40 days' +%F)" "$(date -d '-40 days' +%F)" > "$T/announcements.json"
php $S "$T/gut" >/dev/null; rc=$?
ok "Exit 1 bei Fristen" '[ $rc = 1 ]'
printf '# Zeit\tEmpfänger\tBetreff\n2026-01-05 10:00:00\ta@example.org\tx\n' > "$T/gut/k2/mail-failures.log"
out=$(php $S "$T/gut"); rc=$?
ok "Exit 2 bei FEHLER (nicht versandte Mail)" '[ $rc = 2 ]'
ok "nicht versandte Mail gemeldet" 'grep -q "^FEHLER .*k2.*1 Mail(s) nicht versandt, zuletzt 2026-01-05 10:00" <<<"$out"'
ok "Bestätigung läuft ab (Tag 6 von 7)" 'grep -q "^läuft ab .*AAAAAAAA *Eingangsbestätigung" <<<"$out"'
ok "Bestätigung abgelaufen" 'grep -q "^ABGELAUFEN .*BBBBBBBB *Eingangsbestätigung" <<<"$out"'
ok "erste Antwort ist keine Rückmeldung" 'grep -q "^ABGELAUFEN .*CCCCCCCC *Rückmeldung" <<<"$out"'
ok "Antwort mit Text: nur „falls“" 'grep -q "CCCCCCCC.*nur falls die Antwort vom" <<<"$out"'
ok "nur Statuswechsel: sicher" 'grep "OOOOOOOO" <<<"$out" | grep -qv "nur falls"'
ok "Abschluss zählt auch nach Wiederöffnen" '! grep -q PPPPPPPP <<<"$out"'
ok "spätere Antwort ist Rückmeldung" '! grep -q DDDDDDDD <<<"$out"'
ok "Status, dann erste Nachricht: nur „falls“" 'grep -q "MMMMMMMM.*nur falls" <<<"$out"'
ok "eigener Status gilt als offen" 'grep -q "^ABGELAUFEN .*TTTTTTTT *Eingangsbestätigung" <<<"$out"'
ok "ohne Ankündigung erst ankündigen" 'grep -q "^angekündigt .*SSSSSSSS *Löschung nach dem $(date -d "+30 days" +%d.%m.%Y)" <<<"$out"'
ok "Löschung abgelaufen" 'grep -q "^ABGELAUFEN .*EEEEEEEE *Löschung" <<<"$out"'
ok "Löschung 30 Tage vorher angekündigt" 'grep -q "^angekündigt .*FFFFFFFF *Löschung nach dem" <<<"$out"'
ok "junge geschlossene nicht" '! grep -q GGGGGGGG <<<"$out"'
ok "invalidierter Token: keine Team-Frist" '! grep -q HHHHHHHH <<<"$out"'
ok "kein Meldungstext" '! grep -q GEHEIMTEXT4711 <<<"$out"'
# tree "kaputt"
m kaputt/k3 IIIIIIII "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-1 year')]}"
m kaputt/k3 JJJJJJJJ 'kein json'
m kaputt/k3 KKKKKKKK '{"status":"abgeschlossen","conversation":[]}'
m kaputt/k3 LLLLLLLL '{"status":"geschlossen","conversation":[{"date":"01.01.2019 10:00","status":"geschlossen"}]}'
printf x > "$T/kaputt/k3/cases/_"
m kaputt/k3 '$(id).json' '{}'; mkdir -p "$T/kaputt/k\$(id)/cases" "$T/kaputt/k\$(id)/portal"
out=$(php $S "$T/kaputt"); rc=$?
ok "Exit 2 bei FEHLER" '[ $rc = 2 ]'
ok "ohne Abschlusseintrag" 'grep -q "FEHLER .*IIIIIIII.*ohne Abschlusseintrag" <<<"$out"'
ok "kaputtes JSON" 'grep -q "FEHLER .*JJJJJJJJ.*nicht lesbar" <<<"$out"'
ok "ohne Einträge" 'grep -q "FEHLER .*KKKKKKKK.*ohne Einträge" <<<"$out"'
ok "fremdes Datumsformat" 'grep -q "FEHLER .*LLLLLLLL.*Datum unlesbar" <<<"$out"'
ok "keine Shell-Zeichen aus Namen" '! grep -q "\$(id)" <<<"$out" && grep -q "Portalverzeichnis mit ungültigem Namen" <<<"$out"'
ok "Datei mit ungültigem Namen" 'grep -q "FEHLER .*Datei mit ungültigem Namen" <<<"$out"'
mkdir -p "$T/leer/k/cases" "$T/leer/k/portal" "$T/leer/_alt/cases"; php $S "$T/leer" >/dev/null; ok "Exit 0 ohne Treffer, \"_*\" ist kein Portal" '[ $? = 0 ]'
mkdir -p "$T/falsch"; out=$(php $S "$T/falsch"); rc=$?; ok "Wurzel ohne Portale: Exit 2" '[ $rc = 2 ] && grep -q "keine Portale" <<<"$out"'
php $S "$T/fehlt" 2>/dev/null; ok "Exit 2 ohne Verzeichnis" '[ $? = 2 ]'
# Set but empty: the script then does not read HINWEISE_WURZEL from /etc/hinweise-deadlines.env of an installed host
HINWEISE_WURZEL= php $S 2>/dev/null; ok "Exit 2 ohne Wurzel" '[ $? = 2 ]'
HINWEISE_WURZEL= php $S --loeschen k1 NNNNNNNN 2>&1 | grep -q "Verzeichnis fehlt"; ok "--loeschen ohne Wurzel: klare Meldung" '[ $? = 0 ]'
mkdir -p "$T/dom/k3"; printf '{"Domain-Endung":"Betreiber.Example"}' > "$T/dom/settings.json"
ok "Wurzel aus HINWEISE_WURZEL" 'HINWEISE_WURZEL="$T/dom" php $S 2>&1 | grep -q "unter $T/dom gefunden"'
printf '{"Sicherung_Monate":12}' > "$T/dom/settings.json"; mkdir -p "$T/dom/k4/portal"; printf '{"Hoster":"H"}' > "$T/dom/k4/portal/settings.json"
ok "Sicherung wie in den Datenschutzhinweisen" 'php -r "require \"$S\"; echo sicherung(\"$T/dom/k3\"),\"|\",sicherung(\"$T/dom/k4\");" | grep -qx "noch bis zu 12 Monate enthalten|bis zum Ablauf der Sicherungen enthalten"'
printf '{"Domain-Endung":"Betreiber.Example"}' > "$T/dom/settings.json"
mkdir -p "$T/dom/k6/portal" "$T/dom/k7/portal"; printf '{"Domain":"https://a.example"}' > "$T/dom/k6/portal/settings.json"; printf '{"Domain":["a.example"]}' > "$T/dom/k7/portal/settings.json"
ok "ungültige Domain zählt als keine, Nicht-String bricht nicht ab und wird keine andere" '[ "$(php -r "require \"$S\"; echo domain(\"$T/dom/k6\"),\"|\",domain(\"$T/dom/k7\");")" = "|" ]'
ok "Domain aus Betreiber-Einstellungen" '[ "$(php -r "require \"$S\"; echo domain(\"$T/dom/k3\");")" = k3.betreiber.example ]'
# --mail with a stub: one file per call, recipient in its name
printf '#!/bin/sh\nn=$(ls "%s/post" | wc -l); eval an=\\${$#}; { echo "ARGS: $*"; cat; } > "%s/post/$n-$an"\n' "$T" "$T" > "$T/sm"; chmod +x "$T/sm"; mkdir "$T/post"
printf '# Kommentar\nt@example.org:h:Team:Nachrichten\nstill@example.org:h:Team:keine\nintern@betreiber.example:h:Team:Nachrichten\nnur@example.org:h:BBBBBBBB,XXXXXXXX:Fälle\nfremd@example.org:h:ZZZZZZZZ:Fälle\n' > "$T/gut/k1/passwd"
printf 'still@example.org:h:Team:keine\n' > "$T/gut/k2/passwd"
mkdir -p "$T/gut/k1/portal"; printf '{"Domain":"Meldestelle.Example.com"}' > "$T/gut/k1/portal/settings.json"
printf '{"Domain-Endung":"betreiber.example"}' > "$T/gut/settings.json"   # k2 has no domain of its own
export FRISTEN_SENDMAIL="$T/sm" FRISTEN_AN="dsb@example.org" FRISTEN_INTERN="betreiber.example"
n() { ls "$T/post" | grep -c -- "-$1$"; }
php $S --mail "$T/gut"; rc=$?
ok "Exit 0 nach Versand" '[ $rc = 0 ]'
ok "Team erhält Mail" '[ $(n t@example.org) = 1 ]'
ok "nur Berechtigte für ihre Meldung" '[ $(n nur@example.org) = 1 ] && grep -q BBBBBBBB "$T"/post/*-nur@example.org && ! grep -q AAAAAAAA "$T"/post/*-nur@example.org'
ok "Unberechtigte nicht" '[ $(n fremd@example.org) = 0 ]'
ok "Mail trennt sicher und vielleicht" 'sed -n "/^Noch nicht erledigt/,/^\$/p" "$T"/post/*-t@example.org | grep -q OOOOOOOO && sed -n "/^Vielleicht schon erledigt/,/^\$/p" "$T"/post/*-t@example.org | grep -q CCCCCCCC'
ok "„keine“ erhält nichts" '[ $(n still@example.org) = 0 ]'
ok "Ankündigung an alle, mit Veto und Reply-To" 'grep -q "QQQQQQQQ *Löschung nach dem" "$T"/post/*-t@example.org && grep -q "Veto" "$T"/post/*-t@example.org && grep -q "^Reply-To: dsb@example.org" "$T"/post/*-t@example.org && grep -q QQQQQQQQ "$T"/post/*-intern@betreiber.example'
ok "fällige Löschung nur an Betreiber-intern, mit Befehl" 'grep -q "hinweise-deadlines --loeschen k1 NNNNNNNN" "$T"/post/*-intern@betreiber.example && ! grep -q NNNNNNNN "$T"/post/*-t@example.org && ! grep -q NNNNNNNN "$T"/post/*-dsb@example.org'
ok "versandte Ankündigung vermerkt" 'grep -q "\"k1/SSSSSSSS\": \"$(date +%F)\"" "$T/announcements.json" && grep -q "k1/QQQQQQQQ" "$T/announcements.json"'
ok "fällige Löschung ohne interne Empfänger: an den Betreiber, mit Befehl" 'grep -q -- "hinweise-deadlines --loeschen k2 EEEEEEEE" "$T"/post/*-dsb@example.org'
ok "Betreiber sonst nur, was niemanden erreicht" '[ $(n dsb@example.org) = 2 ] && grep -q FFFFFFFF "$T"/post/*-dsb@example.org && ! grep -q AAAAAAAA "$T"/post/*-dsb@example.org'
ok "Absender, Envelope und MIME" 'grep -q "^From: no-reply@meldestelle.example.com" "$T"/post/*-t@example.org && grep -q "^ARGS: -i -f no-reply@meldestelle.example.com -- t@example.org" "$T"/post/*-t@example.org && grep -q "^MIME-Version: 1.0" "$T"/post/*-dsb@example.org'
ok "kein Meldungstext in Mails" '! grep -rq GEHEIMTEXT4711 "$T/post"'
a=$(ls "$T/post" | wc -l)
php $S --mail "$T/gut"; ok "zweiter Lauf: nichts" '[ $(ls "$T/post" | wc -l) = $a ]'
php -r '$s=json_decode(file_get_contents($argv[1]),true); foreach($s as &$v) $v["zeit"]-=8*86400; file_put_contents($argv[1],json_encode($s));' "$T/stand"
php $S --mail "$T/gut"; neu_t() { ls "$T/post" | grep -- "-t@example.org$" | sort -n | tail -1; }
ok "nach einer Woche: nur Abgelaufene wiederholt" '[ $(ls "$T/post" | wc -l) -gt $a ] && grep -q BBBBBBBB "$T/post/$(neu_t)" && ! grep -q AAAAAAAA "$T/post/$(neu_t)"'
a=$(ls "$T/post" | wc -l); rm "$T/stand"
FRISTEN_SENDMAIL=/bin/false php $S --mail "$T/gut" 2>/dev/null; rc=$?
ok "Sendefehler: Exit 2, nichts als erledigt vermerkt" '[ $rc = 2 ] && [ "$(cat "$T/stand")" = "[]" ]'
# Only the operator mail fails: team mails must not be resent on the next run
rm "$T/stand"; printf '#!/bin/sh\neval an=\\${$#}; [ "$an" = dsb@example.org ] && exit 1; n=$(ls "%s/post" | wc -l); cat > "%s/post/$n-$an"\n' "$T" "$T" > "$T/sm2"; chmod +x "$T/sm2"
FRISTEN_SENDMAIL="$T/sm2" php $S --mail "$T/gut" 2>/dev/null; rc=$?; a=$(ls "$T/post" | wc -l)
FRISTEN_SENDMAIL="$T/sm2" php $S --mail "$T/gut" 2>/dev/null
ok "Betreiber-Fehler: Exit 2, Team nicht doppelt" '[ $rc = 2 ] && [ $(ls "$T/post" | wc -l) = $a ]'
FRISTEN_AN="x@y.z; touch $T/boese" php $S --mail "$T/gut" 2>/dev/null; ok "ungültiger Empfänger abgelehnt" '[ $? = 2 ] && [ ! -e "$T/boese" ]'
# --loeschen / --aufschub
rm -rf "$T/post"/*
php $S --loeschen k1 QQQQQQQQ "$T/gut" 2>/dev/null; ok "Löschen vor Fristende verweigert" '[ $? = 2 ] && [ -e "$T/gut/k1/cases/QQQQQQQQ.json" ]'
php $S --loeschen k1 CCCCCCCC "$T/gut" 2>/dev/null; ok "offene Meldung nicht löschbar" '[ $? = 2 ] && [ -e "$T/gut/k1/cases/CCCCCCCC.json" ]'
php $S --loeschen k1 "../k2/cases/EEEEEEEE" "$T/gut" 2>/dev/null; ok "Pfad als ID abgelehnt" '[ $? = 2 ] && [ -e "$T/gut/k2/cases/EEEEEEEE.json" ]'
php $S --loeschen k2 EEEEEEEE "$T/gut" 2>/dev/null; ok "ohne Empfänger für Bestätigung nicht löschen" '[ $? = 2 ] && [ -e "$T/gut/k2/cases/EEEEEEEE.json" ]'
php $S --loeschen k1 SSSSSSSS "$T/gut" 2>/dev/null; ok "frisch angekündigt: nicht löschen" '[ $? = 2 ] && [ -e "$T/gut/k1/cases/SSSSSSSS.json" ]'
php $S --aufschub k1 ZZZZZZZZ "$(date -d '+60 days' +%d.%m.%Y)" "$T/gut" 2>/dev/null; ok "Aufschub für fehlende Meldung abgelehnt" '[ $? = 2 ]'
cp "$T/announcements.json" "$T/ank.bak"; printf '{"k1/NNNN' > "$T/postponements.json"
php $S --loeschen k1 NNNNNNNN "$T/gut" 2>/dev/null; ok "kaputte postponements.json: nicht löschen" '[ $? = 2 ] && [ -e "$T/gut/k1/cases/NNNNNNNN.json" ]'
rm "$T/postponements.json"
php $S --aufschub k1 NNNNNNNN "$(date -d '+60 days' +%d.%m.%Y)" "$T/gut" >/dev/null; out=$(php $S "$T/gut")
ok "Aufschub: nicht mehr fällig" '! grep -q "NNNNNNNN" <<<"$out"'
php $S --loeschen k1 NNNNNNNN "$T/gut" 2>/dev/null; ok "Löschen während Aufschub verweigert" '[ $? = 2 ] && [ -e "$T/gut/k1/cases/NNNNNNNN.json" ]'
printf '{}' > "$T/postponements.json"
out=$(php $S --loeschen k1 NNNNNNNN "$T/gut"); rc=$?
ok "abgelaufene Meldung gelöscht" '[ $rc = 0 ] && [ ! -e "$T/gut/k1/cases/NNNNNNNN.json" ] && grep -q "gelöscht: k1/NNNNNNNN" <<<"$out"'
ok "Löschung protokolliert (Kopfzeile, Grund frist)" 'head -1 "$T/gut/k1/deletions.log" | grep -q "^# Eingang" && grep -qP "\tNNNNNNNN\t$(TZ=Europe/Berlin date +%Y-%m-%d)\tfrist$" "$T/gut/k1/deletions.log"'
ok "Löschbestätigung ans Team" 'grep -q "Löschbestätigung" "$T"/post/*-t@example.org && grep -q "Löschbestätigung" "$T"/post/*-intern@betreiber.example && [ $(ls "$T/post" | grep -c still@) = 0 ]'
# No sender domain (neither Domain nor Domain-Endung): the confirmation could not be sent, so nothing is deleted
m nd/k5 UUUUUUUU "{\"status\":\"geschlossen\",\"conversation\":[$(ein '-5 years'),$(st '-4 years' geschlossen)]}"
printf 't@example.org:h:Team:Nachrichten\n' > "$T/nd/k5/passwd"
php -r '$a=json_decode(file_get_contents($argv[1]),true); $a["k5/UUUUUUUU"]=$argv[2]; file_put_contents($argv[1],json_encode($a));' "$T/announcements.json" "$(date -d '-40 days' +%F)"
aus=$(php $S --loeschen k5 UUUUUUUU "$T/nd" 2>&1); ok "ohne Absenderdomain nicht löschen" '[ $? = 2 ] && grep -q "keine gültige Absenderdomain" <<<"$aus" && [ -e "$T/nd/k5/cases/UUUUUUUU.json" ]'
# --kunde: one portal per run (per-portal PHP pools); %k in FRISTEN_BEFEHL names the portal
out=$(php $S --kunde k2 "$T/gut"); ok "--kunde: nur dieses Portal" 'grep -q EEEEEEEE <<<"$out" && ! grep -q " k1 " <<<"$out"'
php $S --kunde "../gut" "$T/gut" 2>/dev/null; ok "--kunde: Pfad abgelehnt" '[ $? = 2 ]'
rm -rf "$T/post"/* "$T/stand"; FRISTEN_BEFEHL="sudo -u hinweise-%k hinweise-deadlines" php $S --mail --kunde k2 "$T/gut" 2>/dev/null
ok "--kunde --mail: Befehl mit Pool-Benutzer, kein anderes Portal" 'grep -q "sudo -u hinweise-k2 hinweise-deadlines --loeschen k2 EEEEEEEE" "$T"/post/*-dsb@example.org && ! grep -rq "k1" "$T/post"'
printf '{"k1/X/Y/Z":{"stufe":"a","zeit":1}}' > "$T/stand"; php $S --mail --kunde k2 "$T/gut" >/dev/null 2>&1
ok "--kunde: Stand anderer Portale bleibt" 'grep -q "\"k1.*Z\"" "$T/stand" && grep -q "\"k2" "$T/stand"'
ok "STATE_DIRECTORY vor FRISTEN_STAND (Units je Portal teilen die Umgebungsdatei)" '[ "$(STATE_DIRECTORY="$T/sd" php -r "require \"$S\"; echo standdatei();")" = "$T/sd/state.json" ]'
exit $f
