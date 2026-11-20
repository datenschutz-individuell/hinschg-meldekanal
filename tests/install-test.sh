#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Tests for scripts/install-example.sh in a throwaway folder; sudo, install, nginx and systemctl are stubs,
# so nothing on the system changes. Usage: tests/install-test.sh
cd "$(dirname "$0")/.."; T=$(mktemp -d); trap 'rm -rf "$T"' EXIT; f=0
ok() { if eval "$2"; then echo "ok   $1"; else echo "FEHL $1"; f=1; fi; }
mkdir -p "$T/bin" "$T/.versteckt/_w"; W="$T/.versteckt/_w"   # hidden parent and "_" name: must not hide the portals
printf '#!/bin/sh\n[ "$1" = -u ] && shift 2\nexec "$@"\n' > "$T/bin/sudo"; for s in install nginx systemctl; do printf '#!/bin/sh\nexit 0\n' > "$T/bin/$s"; done; chmod +x "$T"/bin/*
export PATH="$T/bin:$PATH" WEBUSER=$(id -un) DEADLINES_ENV="$T/env"
lauf() { bash scripts/install-example.sh "$W" "$@" </dev/null >"$T/aus" 2>&1; }
lauf eposs; ok "ohne Betreiber-Einstellungen: Abbruch" '[ $? != 0 ] && grep -q "settings.json: fehlt" "$T/aus" && [ ! -e "$W/_core" ]'
printf '{"Domain-Endung":"meldestelle.example.org","Admin-Mail":"a@example.org","Aufsicht":{"de":{"Name":"Behörde"}}}' > "$W/settings.json"
lauf eposs; ok "ohne HINWEISE_WURZEL: Abbruch" '[ $? != 0 ] && grep -q "HINWEISE_WURZEL fehlt" "$T/aus"'
echo "HINWEISE_WURZEL=$T/anderswo" > "$T/env"
lauf eposs; ok "HINWEISE_WURZEL zeigt woandershin: Abbruch" '[ $? != 0 ] && grep -q "zeigt nicht auf" "$T/aus"'
lauf Firma_X; ok "ungültiger Portalname abgelehnt" '[ $? != 0 ] && grep -q "nur Kleinbuchstaben" "$T/aus" && [ ! -e "$W/Firma_X" ]'
lauf firma-; ok "Bindestrich am Rand abgelehnt" '[ $? != 0 ] && grep -q "nur Kleinbuchstaben" "$T/aus"'
# In a UTF-8 locale a range like a-z would match umlauts; without that locale the test proves nothing
if locale -a 2>/dev/null | grep -qi '^de_DE\.utf-\?8$'; then
  LC_ALL=de_DE.UTF-8 lauf müller; ok "Umlaut im Portalnamen abgelehnt, auch in deutscher Locale" '[ $? != 0 ] && grep -q "nur Kleinbuchstaben" "$T/aus"'
else echo "übersprungen: Umlaut im Portalnamen (Locale de_DE.UTF-8 fehlt)"; fi
echo "HINWEISE_WURZEL=$W" > "$T/env"
lauf eposs; ok "Beispielportal angelegt" '[ $? = 0 ] && grep -q "EPOSS GmbH" "$W/eposs/portal/settings.json" && [ -f "$W/eposs/portal/logo.svg" ] && [ -f "$W/eposs/passwd" ]'
ok "Code in _core, Portal verlinkt" '[ -f "$W/_core/common/helper.php" ] && [ ! -e "$W/_core/template" ] && [ "$(readlink "$W/eposs/portal/common")" = ../../_core/common ]'
printf '  HINWEISE_WURZEL = "%s"\n' "$W" > "$T/env"
lauf; ok "Umgebungsdatei mit Anführungszeichen und Leerraum gelesen" '[ $? = 0 ] && grep -q "1 Portal(e) aktualisiert" "$T/aus" && ! grep -q HINWEISE_WURZEL "$T/aus"'
printf 'HINWEISE_WURZEL=%s\nHINWEISE_WURZEL=/anderswo\n' "$W" > "$T/env"
lauf; ok "HINWEISE_WURZEL doppelt: der letzte Eintrag gilt, wie bei systemd" '[ $? != 0 ] && grep -q "zeigt nicht auf" "$T/aus"'
echo "HINWEISE_WURZEL=$W" > "$T/env"
printf '{"Domain-Endung":".example.org","Admin-Mail":"a@example.org","Aufsicht":{"de":{"Name":"Behörde"}}}' > "$W/settings.json"
lauf; ok "ungültige Domain-Endung: Abbruch" '[ $? != 0 ] && grep -q "ungültig: Domain-Endung" "$T/aus"'
printf '{"Domain-Endung":"meldestelle.example.org","Admin-Mail":"a@example.org","Aufsicht":{"de":{"Name":"Behörde"}},"Domain":"alle.example.org"}' > "$W/settings.json"
lauf; ok "Domain in den Betreiber-Einstellungen: Abbruch" '[ $? != 0 ] && grep -q "gälte für alle Portale" "$T/aus"'
printf '{"Domain-Endung":"meldestelle.example.org","Admin-Mail":"admin","Aufsicht":{"de":{"Name":"Behörde"}}}' > "$W/settings.json"
lauf; ok "ungültige Admin-Mail: Abbruch" '[ $? != 0 ] && grep -q "ungültig: Admin-Mail" "$T/aus"'
printf '{"Domain-Endung":"meldestelle.example.org","Admin-Mail":"a@example.org"}' > "$W/settings.json"
lauf; ok "ohne Aufsichtsbehörde: Abbruch" '[ $? != 0 ] && grep -q "ungültig: Aufsicht" "$T/aus"'
printf '{"Domain-Endung":"meldestelle.example.org","Admin-Mail":"a@example.org","Aufsicht":{"de":{"Name":"Behörde"}}}' > "$W/settings.json"
lauf; ok "zweiter Lauf aktualisiert das bestehende Portal" '[ $? = 0 ] && grep -q "1 Portal(e) aktualisiert, 0 neu" "$T/aus"'
lauf eposs; ok "vorhandenes Portal nicht überschrieben" '[ $? != 0 ] && grep -q "gibt es schon" "$T/aus"'
lauf neu; ok "Vorlage mit PHP-Anmeldung ohne emergency.pub: Abbruch, kein Portal" '[ $? != 0 ] && grep -q "emergency.pub fehlt" "$T/aus" && [ ! -e "$W/neu" ]'
echo x > "$W/emergency.pub"
lauf neu; ok "emergency.pub kein Schlüssel: Abbruch" '[ $? != 0 ] && grep -q "kein öffentlicher Schlüssel" "$T/aus" && [ ! -e "$W/neu" ]'
openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:P-256 2>/dev/null | openssl pkey -pubout -outform DER | base64 -w0 > "$W/emergency.pub"
lauf neu; ok "emergency.pub auf anderer Kurve: Abbruch" '[ $? != 0 ] && grep -q "auf brainpoolP384r1" "$T/aus" && [ ! -e "$W/neu" ]'
openssl genpkey -algorithm EC -pkeyopt ec_paramgen_curve:brainpoolP384r1 2>/dev/null | openssl pkey -pubout -outform DER | base64 -w0 > "$W/emergency.pub"   # format of emergency.php --neu
lauf neu; ok "mit emergency.pub: Portal aus der Vorlage mit PHP-Anmeldung" '[ $? = 0 ] && grep -q "\"Anmeldung\": \"portal\"" "$W/neu/portal/settings.json"'
rm -rf "$W/neu" "$W/emergency.pub"
sed -i 's|^{|{"Domain":"https://firma.example",|' "$W/eposs/portal/settings.json"
lauf; ok "eigene Domain des Portals ungültig: Abbruch" '[ $? != 0 ] && grep -q "fehlt: gültige Domain" "$T/aus"'
printf '{"Firma":"x"}' > "$W/eposs/portal/settings.json"
lauf; ok "Pflichtfelder fehlen: Abbruch vor dem Einspielen" '[ $? != 0 ] && grep -q "fehlt: Team" "$T/aus"'
exit $f
