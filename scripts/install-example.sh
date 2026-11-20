#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Example installation (README, section Installation): copies the code to <wurzel>/_core, checks and relinks all
# portals and creates new ones from portal/template. Web server config, certificates and DNS stay with the operator.
#   install-example.sh <wurzel> [<neues-portal> …]     "eposs" creates the fictitious example company from example/
# Server layout: preset WEBUSER, NGINX_DIR, NGINX_ZONE_DIR, PHP_FPM_DIENST, INTEGRITY_RECORD (defaults in portal-functions.sh).
set -u
www_folder=${1:?Aufruf: install-example.sh <wurzel> [<neues-portal> …]}; shift
www_project_template=$(cd "$(dirname "$0")/.." && pwd)
fail_exit() { printf '%s\n' "$@"; echo "Abgebrochen."; exit 1; }
ensured() { "$@" || fail_exit "FEHLER: $* fehlgeschlagen"; }
source "$(dirname "$0")/portal-functions.sh"

for p in "$@"; do
  portalname_pruefen "$p"
  sudo [ -e "$www_folder/$p" ] && fail_exit "$www_folder/$p gibt es schon"
done
# Portal folders only: not _core, nothing hidden – judged by the folder's own name, not by the path of <wurzel>
mapfile -t bestehende < <(sudo find "$www_folder" -mindepth 1 -maxdepth 1 -type d ! -name '_*' ! -name '.*' -exec test -d '{}/portal' \; -print | sort)
portale_vorpruefen "${bestehende[@]}"
core_einspielen
for b in "${bestehende[@]}"; do portal_verlinken "$b"; done
for p in "$@"; do
  example="$www_project_template/example/$p/portal"   # e.g. "eposs": complete example portal with logo and colours
  if [ -d "$example" ]; then portal_anlegen "$www_folder/$p" "$p" "$example"; else portal_anlegen "$www_folder/$p" "$p"; fi
  echo "angelegt: $www_folder/$p – settings.json ausfüllen, logo.svg bauen (scripts/logo-light-dark.py), Webserver einrichten"
done
echo "fertig: ${#bestehende[@]} Portal(e) aktualisiert, $# neu"
