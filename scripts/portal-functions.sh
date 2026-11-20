#!/bin/bash
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
# Shared by deploy-hinweise.sh and deploy-other.sh: code lives once in
# $www_folder/_core; each portal holds only customer files and symlinks to it.
# Expects $www_project_template (repo copy), $www_folder (portals) and ensured()/fail_exit() from the calling script;
# server layout below can be preset there.
WEBUSER=${WEBUSER:-http}                          # web server user: group of the portals; _core belongs to root
NGINX_DIR=${NGINX_DIR:-/etc/nginx}                # snippets included by every portal config
NGINX_ZONE_DIR=${NGINX_ZONE_DIR:-/etc/nginx/conf.d}  # http context (rate-limit zone)
PHP_FPM_DIENST=${PHP_FPM_DIENST:-php-fpm}         # systemd unit of PHP-FPM
# Optional: command (run via sudo) that records the paths written by core_einspielen as the new
# integrity baseline, e.g. "/path/to/tool record"; skipped if empty or not executable.
INTEGRITY_RECORD=${INTEGRITY_RECORD:-}

CORE_TEILE="common index.php datenschutz.php sitemap.php melden bearbeiten registrieren passwort team"

# Deploy repo code to _core, plus the nginx parts of all portals (language prefix, rate limiting);
# reload php-fpm, otherwise opcache serves stale included templates.
function core_einspielen() {
  # root-owned and read-only for everyone else: a write hole in any site running as $WEBUSER must not alter portal code
  ensured sudo rsync -a --delete --exclude template --exclude ALT --delete-excluded --chown=root:root --chmod=D755,F644 "$www_project_template/portal/" "$www_folder/_core/"
  ensured sudo install -m 644 "$www_project_template/scripts/hinweise-language.conf" "$www_project_template/scripts/hinweise-throttle.conf" "$www_project_template/scripts/hinweise-errors.conf" "$NGINX_DIR/"
  ensured sudo install -m 644 "$www_project_template/scripts/hinweise-throttle-zone.conf" "$www_project_template/scripts/hinweise-allow.conf" "$NGINX_ZONE_DIR/"
  # Deadline watcher with the same deadline logic as the team page; root-owned since it may also run as root
  ensured sudo install -D -m 644 -o root -g root "$www_project_template/portal/common/deadlines.php" /usr/local/lib/hinweise-deadlines/deadlines.php
  ensured sudo install -m 755 -o root -g root "$www_project_template/scripts/deadlines.php" /usr/local/bin/hinweise-deadlines
  ensured sudo nginx -t
  ensured sudo systemctl reload nginx "$PHP_FPM_DIENST"
  integritaet_festhalten "$www_folder/_core" "$NGINX_DIR"/hinweise-{language,throttle,errors}.conf \
    "$NGINX_ZONE_DIR"/hinweise-{throttle-zone,allow}.conf /usr/local/lib/hinweise-deadlines /usr/local/bin/hinweise-deadlines
}

# Our own deploy changed these files on purpose: record them, so the next integrity check does not flag them.
# Deploy is already done, so a failure only warns.
function integritaet_festhalten() {
  local cmd
  read -ra cmd <<< "$INTEGRITY_RECORD"
  [ ${#cmd[@]} -gt 0 ] && sudo [ -x "${cmd[0]}" ] || return 0
  sudo "${cmd[@]}" "$@" || echo "WARNUNG: Integritätsstand nicht festgehalten (${cmd[*]})" >&2
}

# Runs a PHP script from scripts/ on unprivileged copies: the template folder and the portals are readable by root
# only, and nothing from them is to run as root. Arguments that are absolute paths are copied (PHP on some servers
# cannot open /dev/fd pipes, hence a private temp folder), all others passed as they are; STDIN goes to the script.
function php_aus_kopie() {
  local skript="$www_project_template/scripts/$1" t a i=0 args=() rc; shift
  sudo [ -s "$skript" ] || fail_exit "$skript fehlt"   # otherwise an empty program would exit 0
  t=$(mktemp -d) || fail_exit "mktemp"
  sudo cat "$skript" > "$t/p.php"
  for a in "$@"; do
    if [[ $a == /* ]]; then
      i=$((i+1)); sudo cat "$a" > "$t/$i-${a##*/}" || { rm -rf "$t"; fail_exit "$a nicht lesbar"; }
      a="$t/$i-${a##*/}"
    fi
    args+=("$a")
  done
  php "$t/p.php" "${args[@]}"; rc=$?
  rm -rf "$t"
  return $rc
}

# What scripts/check-settings.php misses in the settings.json or environment file on STDIN (modes: see there).
function einstellungen_pruefen() {
  php_aus_kopie check-settings.php "$www_project_template/portal/common/deadlines.php" "$@" || fail_exit "scripts/check-settings.php fehlgeschlagen"
}

# A portal's own settings, before the shared texts display them.
function pflichtfelder_pruefen() {
  local fehlt
  fehlt=$(sudo cat "$1/portal/settings.json" 2>/dev/null | einstellungen_pruefen portal "$(basename "$1")") || fail_exit "$fehlt"
  [ -z "$fehlt" ] || fail_exit "$1: in settings.json fehlt: $fehlt"
}

# The logo must be ONE logo.svg that switches itself via @media (prefers-color-scheme: dark).
function logo_pruefen() {
  sudo [ -f "$1/portal/logo.svg" ] || fail_exit "$1: portal/logo.svg fehlt (bauen mit scripts/logo-light-dark.py)"
  # Both readable by root only; but do not run the script from the synced template folder as root
  local skript="$www_project_template/scripts/logo-light-dark.py"
  sudo [ -s "$skript" ] || fail_exit "$skript fehlt"   # otherwise an empty program would exit 0
  python3 -I <(sudo cat "$skript") --pruefen <(sudo cat "$1/portal/logo.svg") >/dev/null \
    || fail_exit "$1: Logo bitte neu bauen (scripts/logo-light-dark.py)"
}

# WCAG AA contrast, light and dark (guide: README, section Farben).
function farben_pruefen() {
  local css=()
  sudo [ -f "$1/portal/style.css" ] && css=("$1/portal/style.css")
  php_aus_kopie check-colors.php "$www_project_template/portal/common/style.php" "$1/portal/settings.json" "${css[@]}" \
    || fail_exit "$1: Farben nicht barrierearm oder nicht prüfbar (Werte oben; Anleitung README, Abschnitt Farben)"
}

# Operator settings for all portals (README, section Betreiber-Einstellungen) and the deadline watcher's environment.
# Read as the web server user: a file only root can read would pass here and leave the portals on their defaults.
function betreiber_pruefen() {
  local fehlt wurzel umgebung="${DEADLINES_ENV:-/etc/hinweise-deadlines.env}"
  fehlt=$(sudo -u "$WEBUSER" cat "$www_folder/settings.json" 2>/dev/null | einstellungen_pruefen operator) || fail_exit "$fehlt"
  [ -z "$fehlt" ] || fail_exit "$www_folder/settings.json: fehlt oder ungültig: $fehlt (Vorlage scripts/settings.example.json; Umlaut-Domains als Punycode)"
  # Read by the watcher's own parser. Readable for the web server user because manual calls
  # (sudo -u <webuser> hinweise-deadlines --loeschen …) read the file themselves.
  wurzel=$(sudo -u "$WEBUSER" cat "$umgebung" 2>/dev/null | einstellungen_pruefen env) || fail_exit "$wurzel"
  [ -n "$wurzel" ] && [ "$(realpath -m "$wurzel")" = "$(realpath -m "$www_folder")" ] \
    || fail_exit "$umgebung: HINWEISE_WURZEL fehlt, ist für $WEBUSER nicht lesbar oder zeigt nicht auf $www_folder (Vorlage scripts/systemd/hinweise-deadlines.env.example)"
}

# Before core_einspielen: the new _core applies at once to ALL linked portals, so check them all, plus the update targets.
function portale_vorpruefen() {
  local ordner m
  betreiber_pruefen
  # sodium: Argon2id, openssl: AES-GCM, ECIES and passkeys (README, Voraussetzungen); checked with the CLI, FPM normally loads the same
  for m in mbstring sodium openssl; do php -m | grep -qix "$m" || fail_exit "PHP-Modul $m fehlt (Arch: php-sodium, Debian: in php-common)"; done
  for ordner in $( { printf '%s\n' "$@"; sudo find "$www_folder" -mindepth 3 -maxdepth 3 -path '*/portal/common' -type l -printf '%h\n' | sed 's|/portal$||'; } | sort -u); do
    sudo [ -d "$ordner/portal" ] || fail_exit "$ordner/portal fehlt"
    pflichtfelder_pruefen "$ordner"
    logo_pruefen "$ordner"
    farben_pruefen "$ordner"
    notfallschluessel_pruefen "$ordner/portal"
  done
}

# Point a portal's symlinks to _core; replaces old copies (backed up to ALT/ first).
function portal_verlinken() {
  project_folder="$1"
  ensured sudo mkdir -p "$project_folder/ALT" "$project_folder/cases"
  for teil in $CORE_TEILE; do
    ziel="$project_folder/portal/$teil"
    if sudo [ -e "$ziel" ] && sudo [ ! -L "$ziel" ]; then
      ensured sudo mv "$ziel" "$project_folder/ALT/$teil.vor-core-$(date +%Y%m%d-%H%M%S)"
    fi
    ensured sudo ln -sfn "../../_core/$teil" "$ziel"
  done
  # With a PHP pool per portal (systemd template hinweise-deadlines@) the portal belongs to its pool user; the group
  # stays the web server's, which reads passwd and portal/
  local eigner="$WEBUSER"
  id -u "hinweise-${project_folder##*/}" &>/dev/null && eigner="hinweise-${project_folder##*/}"
  ensured sudo chown -R -h "$eigner:$WEBUSER" "$project_folder"
}

# The folder name becomes part of the domain; the deadline watcher reports other characters as an error. Characters listed
# one by one: a range like a-z also matches umlauts in a UTF-8 locale. Call before anything is created.
function portalname_pruefen() {
  case "$1" in ""|-*|*-|*[!abcdefghijklmnopqrstuvwxyz0123456789-]*) fail_exit "Portalname \"$1\": nur Kleinbuchstaben a–z, Ziffern und Bindestrich (nicht am Rand)";; esac
}

# New portal: customer files from the template (or from a filled-in example such as example/eposs), then symlinks.
# PHP login encrypts; without the emergency key such a portal refuses service. Operator settings may set it for all.
# $1: folder with the portal's settings.json, default the template for new portals.
# Curve of the emergency key: KURVE in crypto.php
function kurve() {
  php -r 'require $argv[1]; echo KURVE;' "$www_project_template/portal/common/crypto.php"
}

function notfallschluessel_pruefen() {
  local ordner="${1:-$www_project_template/portal/template}"
  if sudo cat "$ordner/settings.json" "$www_folder/settings.json" 2>/dev/null \
      | grep -Eq '"Anmeldung"[[:space:]]*:[[:space:]]*"portal"' && ! sudo grep -v '^#' "$www_folder/emergency.pub" 2>/dev/null | base64 -d 2>/dev/null | openssl pkey -pubin -inform DER -noout -text 2>/dev/null | grep -q "ASN1 OID: $(kurve)\$"; then
    fail_exit "$ordner nutzt \"Anmeldung\": \"portal\", aber $www_folder/emergency.pub fehlt oder ist kein öffentlicher Schlüssel auf $(kurve) (scripts/emergency.php --neu)"
  fi
}

function portal_anlegen() {
  project_folder="$1"; name="$2"; vorlage="${3:-$www_project_template/portal/template}"
  portalname_pruefen "$name"
  notfallschluessel_pruefen "$vorlage"
  ensured sudo mkdir -p "$project_folder/portal"
  ensured sudo cp "$vorlage/"* "$project_folder/portal/"
  ensured sudo touch "$project_folder/passwd"
  ensured sudo sed -i "s/FIRMA/$name/" "$project_folder/portal/settings.json"
  pflichtfelder_pruefen "$project_folder"
  portal_verlinken "$project_folder"
}
