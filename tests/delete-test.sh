#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Tests for deleting in the portal (loeschen_bestaetigen in portal/common/case.php) with generated reports.
# Usage: tests/delete-test.sh
set -u
cd "$(dirname "$0")/.."; T=$(mktemp -d); trap 'rm -rf "$T"' EXIT; f=0
ok() { if eval "$2"; then echo "ok   $1"; else echo "FEHL $1"; f=1; fi; }
K="$T/kunde"; mkdir -p "$K/portal" "$K/cases"
printf '{"Debug":"b@example.org"}' > "$K/portal/settings.json"   # Debug = logged-in user without Basic auth
fall() { printf '{"status":"%s","conversation":[{"person":"meldende_person","date":"2026-01-02 10:00:00","text":"x"}]}' "$2" > "$K/cases/$1.json"; }
antrag() { printf '{"AAAAAAAA":{"von":"%s","datum":"2026-01-03 10:00:00","grund":"test"},"BBBBBBBB":{"von":"%s","datum":"2026-01-03 10:00:00","grund":"spam"},"CCCCCCCC":{"von":"a@example.org","datum":"2026-01-03 10:00:00","grund":"test"}}' "$1" "$2" > "$K/deletion-requests.json"; }
best() { DOCUMENT_ROOT="$K/portal" php -r '$_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT"); require "portal/common/case.php"; echo loeschen_bestaetigen($argv[1]) ? "ja" : "nein";' "$1" 2>/dev/null; }
L="$K/deletions.log"; heute=$(TZ=Europe/Berlin date +%Y-%m-%d)
fall AAAAAAAA unbearbeitet; fall BBBBBBBB unbearbeitet; fall CCCCCCCC offen
antrag a@example.org b@example.org
ok "Bestätigung durch zweite Person löscht" '[ "$(best AAAAAAAA)" = ja ] && [ ! -e "$K/cases/AAAAAAAA.json" ]'
ok "Löschung protokolliert (Kopfzeile, Grund)" 'head -1 "$L" | grep -q "^# Eingang" && grep -qP "^2026-01-02\tAAAAAAAA\t$heute\ttest$" "$L"'
ok "Antrag danach entfernt" '! grep -q AAAAAAAA "$K/deletion-requests.json"'
ok "eigener Antrag: nicht löschen, nicht protokollieren" '[ "$(best BBBBBBBB)" = nein ] && [ -e "$K/cases/BBBBBBBB.json" ] && ! grep -q BBBBBBBB "$L"'
ok "offene Meldung: nicht löschen, nicht protokollieren" '[ "$(best CCCCCCCC)" = nein ] && [ -e "$K/cases/CCCCCCCC.json" ] && ! grep -q CCCCCCCC "$L"'
printf "b@example.org:x:Team:keine\n" > "$K/passwd"; fall AAAAAAAA unbearbeitet; fall DDDDDDDD unbearbeitet; antrag b@example.org a@example.org
ok "eigener Test-Antrag: braucht eine zweite Person" '[ "$(best AAAAAAAA)" = nein ] && [ -e "$K/cases/AAAAAAAA.json" ]'
ts() { DOCUMENT_ROOT="$K/portal" php -r '$_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT"); require "portal/common/case.php"; echo test_sofort_loeschen($argv[1], false) ? "ja" : "nein";' "$1" 2>/dev/null; }
fall BBBBBBBB unbearbeitet
ok "Test sofort: nicht bei fremdem Antrag" '[ "$(ts BBBBBBBB)" = nein ] && [ -e "$K/cases/BBBBBBBB.json" ]'
ok "Test sofort: nicht bei offener Meldung" '[ "$(ts CCCCCCCC)" = nein ] && [ -e "$K/cases/CCCCCCCC.json" ]'
ok "Test sofort: nicht bei offenem Antrag" '[ "$(DOCUMENT_ROOT="$K/portal" php -r '"'"'$_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT"); require "portal/common/case.php"; echo test_sofort_loeschen("AAAAAAAA", false) ? "ja" : "nein";'"'"' 2>/dev/null)" = nein ] && [ -e "$K/cases/AAAAAAAA.json" ]'
ok "Test sofort: ohne Antrag in einem Schritt" '[ "$(DOCUMENT_ROOT="$K/portal" php -r '"'"'$_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT"); require "portal/common/case.php"; echo test_sofort_loeschen("DDDDDDDD") ? "ja" : "nein";'"'"' 2>/dev/null)" = ja ] && grep -qP "\tDDDDDDDD\t$heute\ttest$" "$L" && ! grep -q DDDDDDDD "$K/deletion-requests.json"'
ok "Liste zeigt Test-Löschungen" 'DOCUMENT_ROOT="$K/portal" php -r '"'"'$_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT"); require "portal/common/case.php"; echo implode(",", array_column(loeschprotokoll_zeilen(), 1));'"'"' 2>/dev/null | grep -q DDDDDDDD'
exit $f
