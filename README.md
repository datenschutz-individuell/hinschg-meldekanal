# Minimales Meldeportal nach Hinweisgeberschutzgesetz

Ein schlankes Hinweisgebersystem in PHP: Jede Organisation erhält ein eigenes Portal als interne Meldestelle nach dem Hinweisgeberschutzgesetz (HinSchG). Ein Betreiber – etwa ein externer Datenschutzbeauftragter – stellt die Portale für mehrere Organisationen bereit und schaltet die Zugänge der Meldestellen-Teams frei.

Was das Portal kann:

- Meldungen anonym oder mit Kontaktdaten, je nach Einstellung des Portals.
- Ein Nachrichtenverlauf zwischen meldender Person und Team; die meldende Person greift mit einem Token darauf zu, ohne Konto.
- Erinnerungen an die gesetzlichen Fristen und die Löschung nach drei Jahren, mit Veto-Möglichkeit für das Team.
- Gestaltung in den Farben, der Schrift und mit dem Logo der jeweiligen Organisation, hell und dunkel, barrierearm nach WCAG 2.1 AA.
- Mehrsprachige Oberfläche; die Sprache folgt dem Browser, ohne Cookie.
- Schutz vor automatisierten Meldungen ohne CAPTCHA und ohne gespeicherte IP-Adressen.

Ohne Datenbank: Einstellungen und Meldungen liegen als JSON-Dateien im Portalordner.


## Installation

### Voraussetzungen

- **PHP 8.4 oder neuer** mit `mbstring`, `json`, `sodium` und `openssl` (`sodium` für Argon2id, `openssl` für Verschlüsselung und Passkeys, Verfahren in [`SECURITY.md`](SECURITY.md); unter Arch Linux `sodium` als Paket `php-sodium` nachinstallieren und in `/etc/php/conf.d/` einschalten). Das Install-Skript prüft die Module. Ältere Versionen scheitern an `mb_ucfirst`, `match` und dem Rückgabetyp `never`. Mails verschickt PHP über `mail()`, also über ein sendmail-kompatibles Programm.
- **Ein Webserver mit PHP-FPM**, der DocumentRoot jedes Portals zeigt auf `<kunde>/portal`. Erprobt ist nginx; die nötigen Bausteine liegen in `scripts/` (siehe unten).
- **systemd** für den täglichen Fristen-Wächter; ohne systemd genügt ein täglicher cron-Aufruf.
- Zum Einspielen: `bash`, `rsync`, `sudo` und `python3`.

### Schritte

1. Das Repository auf den Server holen und einen Ordner für die Portale anlegen, im Folgenden `<wurzel>`, z. B. `/srv/hinweise`.
2. `scripts/settings.example.json` als `<wurzel>/settings.json` ablegen und ausfüllen (Betreiber-Einstellungen, siehe unten).
3. `scripts/systemd/hinweise-deadlines.env.example` als `/etc/hinweise-deadlines.env` ablegen und ausfüllen, die Units aus `scripts/systemd/` installieren und den Timer aktivieren.
4. `scripts/install-example.sh <wurzel> <portal>` aufrufen; der Portalname besteht aus Kleinbuchstaben, Ziffern und Bindestrichen (nicht am Anfang oder Ende). Das Skript spielt den Code nach `<wurzel>/_core` ein, prüft alle vorhandenen Portale und legt das neue aus `portal/template` an. Weicht der Server ab (Webserver-Benutzer, nginx-Ordner, Name des PHP-FPM-Dienstes), stehen die Variablen dafür im Kopf von `scripts/portal-functions.sh`.
5. Den Webserver für das Portal einrichten – Vorlage: `scripts/nginx-portal.example.conf` –, dazu Zertifikat und DNS-Eintrag. Die Vorlage schreibt weder Zugriffs- noch Fehlerprotokoll, weil nginx in beide die IP-Adresse schreibt und die Datenschutzhinweise zusagen, dass keine gespeichert wird. Das gilt ebenso für das globale `error_log` in `nginx.conf`. Damit PHP-Fehler nicht mit verschwinden, braucht der PHP-FPM-Pool ein eigenes Protokoll (`php_admin_value[error_log]`).
6. Im neuen Portal `settings.json` ausfüllen und ein Logo bauen (siehe „Logo“). Danach `install-example.sh <wurzel>` ohne Portalnamen erneut aufrufen; es prüft und verlinkt alle Portale.

**Beispielportal:** `install-example.sh <wurzel> eposs` legt statt eines leeren Portals die erfundene EPOSS GmbH aus `example/eposs/` an – mit Logo, Farben und ausgefüllten Einstellungen. So lässt sich das Portal ausprobieren, bevor die erste echte Organisation eingerichtet ist.

Neue Versionen spielt derselbe Aufruf ohne Portalnamen ein. Er überschreibt nur den gemeinsamen Code in `_core`, nie die Dateien der Portale.

### Betreiber-Einstellungen

`<wurzel>/settings.json` gilt für alle Portale. Setzt ein Portal in seiner eigenen `settings.json` dasselbe Feld, gilt dessen Wert; was niemand setzt, kommt aus `portal/common/defaults.json`. Die Datei liegt außerhalb jedes DocumentRoot und ist daher nicht abrufbar.

| Feld | Bedeutung |
|---|---|
| `Domain-Endung` | Ein Portal ohne eigenes Feld `Domain` ist unter `<ordner>.<Domain-Endung>` erreichbar; daraus entstehen Absender und Links der Mails. |
| `Admin-Mail` | Diese Adresse erfährt von jeder neuen Registrierung. |
| `Freischaltung` | Text, der in dieser Mail vor dem Portalordner steht, etwa der Befehl zum Freischalten. |
| `Betreiber`, `Hoster`, `Serverstandort`, `Sicherung_Monate` | Angaben für die Datenschutzhinweise, siehe „Datenschutzhinweise“. |
| `Sicherheit` | Adresse einer Seite zum Melden von Sicherheitslücken, etwa `/security` (die Seite liefert der Webserver, nicht das Portal); leer: kein Link. Je Sprache als `Sicherheit_xx`, z. B. `/security#en`. |
| `Aufsicht` | Zuständige Datenschutz-Aufsichtsbehörde für die Datenschutzhinweise: je Sprache ein Eintrag mit `Name`, `Link`, `Anschrift` (Zeilen), `Mail`, `Telefon`; `de` ist Pflicht und gilt für Sprachen ohne eigenen Eintrag. Ein Portal mit anderer zuständiger Behörde setzt `Aufsicht` selbst. |

Das Install-Skript bricht ab, solange `Domain-Endung`, `Admin-Mail` oder `Aufsicht` fehlen oder ungültig sind (Umlaut-Domains als Punycode), die eigene `Domain` eines Portals keine gültige Absenderdomain ist, der Webserver-Benutzer die Datei nicht lesen kann oder `HINWEISE_WURZEL` in `/etc/hinweise-deadlines.env` fehlt oder nicht auf `<wurzel>` zeigt (steht es mehrfach darin, gilt wie bei systemd der letzte Eintrag). Die Units laufen als Benutzer `http`; heißt der Webserver-Benutzer anders, `User=` und `Group=` anpassen.

### Andere Webserver (Caddy, Apache …)

Formulare, Spamschutz, Löschung und Fristen-Wächter erledigt PHP selbst. Vier Aufgaben übernimmt bisher nginx; ein anderer Webserver muss sie nachbilden:

1. **Anmeldung für `/team`:** nur ohne `"Anmeldung": "portal"` (siehe „Anmeldung und Verschlüsselung“): HTTP-Basic-Auth gegen `<kunde>/passwd` (bcrypt, `$2y$`) – bei Caddy `basic_auth`, bei Apache `AuthType Basic` mit `AuthUserFile`. PHP liest den Namen aus `PHP_AUTH_USER` und antwortet ohne Anmeldung selbst mit 401.
2. **Sprach-Präfix:** `/<xx>` per 301 auf `/<xx>/` umleiten und `/<xx>/pfad` intern auf `/pfad` umschreiben, ohne `REQUEST_URI` zu ändern (`hinweise-language.conf`). Daran erkennt PHP die Sprache. Vorher: `/sitemap.xml` auf `sitemap.php` (`/<xx>/sitemap.xml` per 301 dorthin) und alles außer den bekannten Seiten, Skripten und Kundendateien als 404, auch unter `/team` und mit Präfix (Whitelist in `hinweise-allow.conf`, einmal in den http-Kontext).
3. **Drosselung je Absender:** POST auf `/melden`, `/registrieren` und `/passwort` drei Mal sofort, danach einmal je 30 Sekunden, auf `/team` (Anmeldung und Bearbeitung) 21 Mal sofort, danach einmal je 2 Sekunden, IPv6 je /64; sonst Antwort 429 mit `common/too-many.html` (`hinweise-throttle*.conf`). Caddy braucht dafür ein Zusatzmodul wie `caddy-ratelimit`. Auch ohne Drosselung bleiben Zeit-Token, Köderfeld und Obergrenze wirksam.
4. **Fehlerseiten und Sperren:** 400/401/403/404 auf `common/error.php?code=…`, 50x auf `common/error-50x.html` (`hinweise-errors.conf`). `*.json` und `common/vendor/` darf der Webserver nie ausliefern.


## Aufbau

### Ein Code für alle Portale

Der Inhalt von `portal/` (ohne `template`) liegt auf dem Server einmal als `<wurzel>/_core`. Jedes Portal `<wurzel>/<kunde>/portal` enthält nur seine eigenen Dateien – `settings.json`, `logo.svg`, gegebenenfalls `style.css` und Schriften – und Symlinks auf `_core` (`common`, `index.php`, `datenschutz.php`, `melden`, `bearbeiten`, `registrieren`, `passwort`, `team`). Neben `portal/` liegen die Meldungen (`cases/`) und die Zugänge (`passwd`).

Weil der Code über Symlinks eingebunden ist, zeigt `__DIR__` immer auf `_core`. Alles Portaleigene findet der Code deshalb über `DOCUMENT_ROOT`: `PORTAL_DIR` und `KUNDE_DIR` in `helper.php`.

Nach dem Einspielen muss PHP-FPM neu laden, sonst liefert opcache eingebundene Vorlagen weiter in der alten Fassung aus. Das Install-Skript erledigt das.

### Dateien in `portal/`

- `index.php`, `datenschutz.php` und die Ordner `melden`, `bearbeiten`, `registrieren` und `team` sind die Seiten des Portals. Jede `index.php` bindet zuerst den Kopf ein, verarbeitet dann abgeschickte Formulare und zeigt sonst das leere Formular; am Ende folgt die Fußzeile.
- `common/` enthält alles Gemeinsame, unter anderem:
  - `helper.php` – Einstellungen, Mailversand und viele kleine Hilfsfunktionen,
  - `case.php` – Darstellung und Verarbeitung des Nachrichtenverlaufs,
  - `header.php`, `footer.php` – Kopf und Fußzeile jeder Seite,
  - `style.php` – die Grundgestaltung als CSS,
  - `js.php` – Zeichenzähler und Absenden mit Strg+Eingabe,
  - `defaults.json` – alle Einstellungen mit ihren Standardwerten,
  - `lang/` und `text/` – Oberflächentexte, Startseite und Datenschutzhinweise je Sprache.
- `template/` enthält die Dateien, mit denen ein neues Portal beginnt.

Die Daten aus den JSON-Dateien bleiben bisher Arrays. Wächst das Projekt, lohnen sich eigene Klassen für Meldungen und Einstellungen.

### Sprachen

Zu einer Sprache gehören `common/lang/xx.php` (Oberflächentexte mit denselben Schlüsseln wie `de.php`) sowie `common/text/index.xx.php` und `common/text/privacy.xx.php` (Startseite und Datenschutzhinweise). Deutsch ist Standard und springt für fehlende Schlüssel ein. Mails und gespeicherte Meldungen bleiben deutsch.

Welche Sprachen ein Portal anbietet, steht in seiner `settings.json` unter `Sprachen`. Sprachen unter `Sprachen_in_Pruefung` sind nur über ihr Pfad-Präfix erreichbar, damit die Organisation die Übersetzung gegenlesen kann; freigegeben wird durch Verschieben nach `Sprachen`. Neue Portale beginnen nur mit Deutsch. Einzelne Felder lassen sich je Sprache als `<Feld>_xx` überschreiben, z. B. `Impressum_en`.

Die Sprache ergibt sich ohne Cookie und ohne Parameter: aus einem Pfad-Präfix `/xx/`, sonst aus der Browser-Sprache (`Accept-Language`), sonst Deutsch. Übersetzungen sind Gefälligkeitsübersetzungen; maßgeblich ist die deutsche Fassung, und die Datenschutzhinweise sagen das gleich zu Beginn.

**Stand der Übersetzungen:** Alle Sprachen außer Deutsch sind maschinell übersetzt. Die englischen Texte sind überflogen, aber nicht systematisch vollständig geprüft; alle anderen Sprachen hat niemand gegengelesen. Vor der Freigabe einer Sprache für ein Portal sollte sie jemand mit dieser Muttersprache gegenlesen. Hinweise und Korrekturen sind willkommen. Bekannte Fragen: Ungarisch lässt „Passkey“ unübersetzt und beugt es (üblich wären auch „azonosítókulcs“ oder „hozzáférési kulcs“); Niederländisch „ruzie“ und „aanvinken“ sind eventuell zu umgangssprachlich; Tschechisch, Litauisch und Lettisch verwenden je zwei Wörter für „Meldestelle“.

### Meldungen

Jede Meldung ist eine Datei `cases/<Meldungs-ID>.json`; PHP legt den Ordner selbst an. Im Code heißt eine Meldung durchgehend „case“, ihre ID `case_id`. Welche Felder eine Meldung hat, zeigt am besten eine gespeicherte Datei oder `portal/melden/index.php`, wo sie angelegt wird. Zeilenenden werden beim Speichern zu `\n`.

Die meldende Person erhält einen Token aus 24 Buchstaben und Ziffern: die nicht geheime Meldungs-ID (8 Zeichen) und ein geheimes Secret (16 Zeichen). Damit öffnet sie den Nachrichtenverlauf.

Löschen im Portal braucht zwei Personen: Eine beantragt (`deletion-requests.json`), eine zweite bestätigt. Ausnahme ist der Grund „Test“: Dann löscht eine Person allein, solange kein Antrag besteht, und das übrige Team erhält eine Mail darüber. Jede Löschung wird vorher in `deletions.log` vermerkt; das Portal hängt dort nur an und blendet nichts aus.

### Team-Zugänge

`<kunde>/passwd` hat je Person eine Zeile `adresse:bcrypt-Hash:Rolle:Benachrichtigung`. Die Rolle ist `Team`, `DEV` oder eine Liste von Meldungs-IDs, die Benachrichtigung `Nachrichten`, `Fälle` oder `keine`. Eine mit `#` auskommentierte Zeile sperrt die Person vorübergehend.

Wer sich unter `/registrieren` anmeldet, landet zunächst in einer eigenen Datei `<kunde>/JJJJ-MM-TT_HH-MM-SS.passwd`; die `Admin-Mail` aus den Betreiber-Einstellungen erfährt davon. Zum Freischalten die Zeile prüfen, an `passwd` anhängen (bei bekannter Adresse nur den Hash ersetzen) und die Registrierungsdatei löschen – als Benutzer des Webservers, damit Eigentümer und Rechte stimmen, und nach einer Sicherung von `passwd`.

### Anmeldung und Verschlüsselung

Mit `"Anmeldung": "portal"` in der `settings.json` eines Portals meldet sich das Team in PHP an statt per HTTP-Basic-Auth; im Webserver entfällt dann `auth_basic` für `/team`. Meldungen werden dann verschlüsselt gespeichert. Neue Portale bekommen die Einstellung aus `portal/template`; vor dem ersten Portal muss deshalb der Notfallschlüssel angelegt sein.

- **Anmeldung:** Passwort mit zweitem Faktor – Authenticator-App (TOTP), Sicherheitsschlüssel (WebAuthn) oder ein Code per Mail als Rückfall, die zuletzt genutzte Methode vorausgewählt – oder ein Passkey, nach dem ein zweiter Faktor nur auf Wunsch folgt. Einstellungen unter `/team/konto/`. Fehlversuche verlängern die Wartezeit je Zugang, ohne IP.
- **Zugänge:** `/registrieren` mit Bestätigungslink per Mail, `/passwort` für ein vergessenes Passwort. Beides wartet danach auf die Freigabe durch ein Mitglied mit Rolle `Team` oder `DEV` in `/team/`; so öffnet ein übernommenes Postfach allein keine Meldung. `passwd` bleibt die Liste der Zugänge, weitere Angaben je Person stehen in `<kunde>/users/<id>.json`.
- **Verschlüsselung** (`common/crypto.php`): Je Meldung ein eigener Datenschlüssel für Titel und Nachrichtentexte. Lesbar bleiben ID, Status, Daten und wer schrieb – das braucht der Fristen-Wächter. Das Thema steht nur verschlüsselt in der ersten Nachricht; die Benachrichtigung nennt es, außer bei `"Thema_in_Mail": "nein"`, ohne es zu speichern. Vom Datenschlüssel liegt je eine verschlüsselte Kopie vor für die meldende Person (aus dem Token, der selbst nicht mehr gespeichert wird), für jedes freigegebene Mitglied (an dessen Schlüsselpaar, das mit Passwort oder Passkey geschützt ist) und für den Notfallschlüssel des Betreibers. Passkeys müssen dafür die Erweiterung PRF beherrschen; ohne sie taugen sie nur als zweiter Faktor.
- **Notfallschlüssel:** `php scripts/emergency.php --neu DATEI` erzeugt ihn; der öffentliche Teil gehört nach `<wurzel>/emergency.pub`, der private offline. Ohne `emergency.pub` bricht das Install-Skript ab, und ein Portal mit PHP-Anmeldung nimmt keine Meldungen an. Haben alle Mitglieder ihren Zugang verloren, legt `emergency.php KUNDE ADRESSE < DATEI` die Schlüsselkopien für ein neu freigegebenes Mitglied an. Für einen neuen Notfallschlüssel oder ein neues Verfahren schlüsselt `emergency.php --umschluesseln WURZEL NEUE_PUB < DATEI` (als root; mit `--pruefen` am Ende nur Probelauf) Mitglieder und Meldungen aller Portale um, ohne dass sich jemand neu anmelden muss (siehe SECURITY.md).
- **Umstellung:** Bestehende Meldungen verschlüsselt die erste Anmeldung eines berechtigten Mitglieds. Wer sich danach zum ersten Mal anmeldet, liest sie, sobald sich ein anderes Mitglied angemeldet hat.
- **Verfahren und bewusst hingenommene Risiken:** [`SECURITY.md`](SECURITY.md).

### Schutz vor automatisierten Meldungen

Das Portal kommt ohne CAPTCHA aus und speichert keine IP-Adressen; die Anonymität meldender Personen bleibt gewahrt.

- **Drosselung im Webserver:** drei Meldungen sofort, danach eine je 30 Sekunden, IPv6 je /64, sonst 429 mit `common/too-many.html`. `scripts/hinweise-throttle-zone.conf` und `scripts/hinweise-allow.conf` gehören einmal in den http-Kontext, `scripts/hinweise-throttle.conf` in jedes Portal. Die IP liegt dabei nur im Arbeitsspeicher; `limit_req_log_level warn` hält sie aus dem Fehlerprotokoll.
- **Zeit-Token** (`common/spam-protection.php`): ein signierter Zeitstempel im Formular, gültig frühestens 5 Sekunden und spätestens 24 Stunden nach dem Aufruf. Den Schlüssel `<kunde>/.form-key` legt PHP selbst an.
- **Köderfeld:** ein verstecktes Feld `kontrollfeld`, das nur Bots ausfüllen.
- **Obergrenze:** höchstens drei neue Meldungen je Portal in 15 Minuten. Wer darüber liegt, erfährt das und wird gebeten, es später erneut zu versuchen oder sich über das Impressum an die Organisation zu wenden. Zählen und Anlegen laufen unter einer Sperre (`<kunde>/.report-lock`).
- Abgelehnte Eingaben bleiben im Formular stehen.

Kommen Angriffe verteilt oder füllen sie die Obergrenze wiederholt, wäre der nächste Schritt ein selbst gehostetes Proof-of-Work wie ALTCHA – barrierefrei und ohne Drittanbieter. Ein Bild-CAPTCHA schlösse sehbehinderte Menschen aus.


## Gestaltung

Kundenspezifisch sind nur `settings.json`, Logo, Farben und Schrift. Die Texte von Startseite und Datenschutzhinweisen kommen aus `common/text/` und werden über die Felder `Team`, `Anschrift`, `DS-Mail`, `Bezug`, `Titelzusatz`, `Zusatz` und `Persoenlich` angepasst. `Persoenlich` ersetzt den Satz der Startseite, wie man die Meldestelle persönlich oder per Video spricht (§ 16 Abs. 3 HinSchG), etwa durch eine Telefonnummer. Für andere Sprachen gilt nur `Persoenlich_<Sprache>`, sonst der übersetzte Standardsatz. Alle Felder mit ihren Standardwerten stehen in `common/defaults.json`.

### Logo

Jedes Portal hat genau eine `portal/logo.svg`, die sich mit eingebettetem `@media (prefers-color-scheme: dark)` selbst auf den dunklen Modus umstellt. Andere Formate liest der Code nicht. Das Install-Skript bricht ab, wenn das Logo eines Portals fehlt oder sich nicht umstellt.

`scripts/logo-light-dark.py` baut ein solches Logo:

- `logo-light-dark.py hell.svg dunkel.svg` vereint zwei Fassungen (SVG, PNG, WebP oder JPEG, auch gemischt) in einer Datei. Rasterbilder werden eingebettet, ids und Klassen der dunklen Fassung umbenannt.
- `logo-light-dark.py logo.svg --farbe '#0d71b4=#37a6f1'` färbt eine SVG im dunklen Modus um; die Farben müssen als Attribut stehen, nicht in `style=`.
- `logo-light-dark.py logo.svg --gleich` markiert ein Logo, das in beiden Modi wirkt, als geprüft.
- `logo-light-dark.py --pruefen logo.svg` endet mit 0, wenn sich die Datei umstellt.

In Sonderfällen bricht das Skript ab, statt ein falsches Logo zu bauen: mit 3 bei einer Namensraum-Kollision (gleiches `xmlns:`-Präfix für verschiedene URIs), mit 4 bei gleichnamigen `@keyframes` in beiden Fassungen. JPEGs mit EXIF-Drehung richtet es selbst aus.

Angezeigt wird das Logo höchstens 220 × 56 px groß. Die Content-Security-Policy erlaubt dafür `style-src 'unsafe-inline'` und `img-src data:`.

### Farben

Ohne eigene Angaben gilt die Grundgestaltung aus `common/style.php`; im dunklen Modus tönt sie `Farbton` (0–360) aus `settings.json`. Eigene Farben stehen als CSS-Variablen in `portal/style.css` – alle Namen nennt die Vorlage `portal/template/style.css` –, je einmal für hell (`:root`) und dunkel (`@media (prefers-color-scheme: dark) { :root { … } }`). Ein `:root` ohne `@media` gilt in beiden Modi; wer nur hell setzt, ändert also auch dunkel.

Die Flächen des Nachrichtenverlaufs und der Rand der Eingabefelder werden aus den übrigen Farben gemischt. Wer sie anders braucht, setzt `--farbe-chat-meldende`, `--farbe-chat-meldende-titel`, `--farbe-chat-meldende-titel-text`, `--farbe-chat-team`, `--farbe-chat-team-titel`, `--farbe-chat-team-titel-text`, `--farbe-chat-rand` oder `--farbe-feld-rand` selbst – etwa eine dunkle Kopfzeilenschrift `#222` auf hellem Markenorange, wo weiße Schrift nur einen Kontrast von 2,1:1 erreichte.

Fokusrahmen, aktives Eingabefeld und Symbolknöpfe zeichnet `--farbe-akzent-linie`, standardmäßig die Knopffarbe. Sie braucht einen Kontrast von 3:1 zu Seite, Fläche und Meldungskasten (WCAG 1.4.11). Knopffarben verfehlen das oft; dann eine eigene setzen, meist `var(--farbe-link)`.

**Nachrichtenverlauf:** Jede Seite des Gesprächs hat eine Parteifarbe, `--farbe-partei-meldende` und `--farbe-partei-team`. So entstehen die Farben für ein neues Portal:

1. Markenfarben von der Webseite der Organisation übernehmen, sonst aus dem Logo. Bei zwei Markenfarben erhält jede Partei eine, bei einer die meldende Person; das Team bekommt dann `#808080`.
2. Sind beide Seiten schlecht zu unterscheiden, etwa weil die Markenfarbe dem dunklen Seitenhintergrund gleicht: hell die Logofarbe, dunkel eine Kopfzeilenfarbe im Farbton des Hintergrunds, ungefähr so hell wie die graue Kopfzeile des Teams.
3. Flächen mischt `style.php`: Kopfzeile 28 % Parteifarbe (hell, zu Weiß) bzw. 40 % (dunkel, zu `#1c1c1c`), Meldungshintergrund 10 % bzw. 16 %.
4. Schriftfarben, Statuszeile und Seitenbalken berechnet `php scripts/derive-colors.php portal/common/style.php <portal>/settings.json <portal>/style.css >> <portal>/style.css`. Vor einem erneuten Aufruf den zuvor angehängten Block entfernen.
5. Danach die Kontraste prüfen:

       php scripts/check-colors.php portal/common/style.php <portal>/settings.json [<portal>/style.css]

`check-colors.php` prüft Schrift (mindestens 4,5:1), Feldrand und `--farbe-akzent-linie` (mindestens 3:1) in hell und dunkel, ohne Browser. Es endet mit 0, wenn alles erfüllt ist, mit 1 bei zu schwachen Stellen – jeweils mit Wert und, bei Schrift auf farbiger Fläche, einem Vorschlag `#222` oder `#fff` – und mit 2, wenn es eine Farbe nicht lesen kann. Verstanden werden `#hex`, `rgb()`, `hsl()`, `color-mix(in srgb, …)` und `var()`, keine anderen Farbräume und außer white und black keine Farbnamen. Das Install-Skript prüft so jedes Portal vor dem Einspielen und bricht bei Mängeln ab.

`example/eposs/portal/style.css` zeigt das Ergebnis für eine Organisation mit einer Markenfarbe.

### Schrift

`php scripts/check-fonts.php <webseite> [<portal>/portal]` ermittelt Text- und Überschriftenschrift einer Webseite und übernimmt sie nur, wenn sie frei lizenziert ist (Google Fonts unter SIL OFL oder Apache). Mit Portalordner lädt es die lateinischen woff2-Dateien ins Portal und schreibt `fonts.css`; `style.css` beginnt dann mit `@import "fonts.css";`. Lizenzpflichtige Dienste wie Adobe Fonts, Fonts.com oder MyFonts zählen Seitenaufrufe; dafür fehlt meist eine Lizenz, das Skript nennt sie und übernimmt nichts. Bei Schriften unbekannter Lizenz bei der Organisation nachfragen und bis dahin die Systemschrift nehmen. Die Content-Security-Policy erlaubt nur Schriften aus dem Portal selbst.

Wo `node` und `chromium` laufen, misst `scripts/measure-website.mjs` im Browser, was die Webseite tatsächlich anzeigt: die häufigste Schrift der Absätze im Hauptinhalt und die Überschriften h1 bis h3. Das ist verlässlicher, als Stylesheets zu lesen, das nur als Rückfall dient. Übernommen werden auch die Größen, begrenzt auf Werte, die zu einem Formular passen: Fließtext 14–18 px (gemessen auf der Impressumsseite, weil Startseiten oft nur große Blickfänge zeigen); h2 mindestens 1,3-mal Fließtext und höchstens 24 px, h3 mindestens 1,15-mal Fließtext und höchstens 20 px, h1 zwischen 24 und 36 px, mindestens 1,5-mal Fließtext und 1,2-mal h2. Die Seite ist rund 80 Zeichen je Zeile breit, in jeder Schrift (Obergrenze nach WCAG 1.4.8): gemessen wird die Breite für 75 Zeichen, das Stylesheet gibt 16/15 dazu.

### Datenschutzhinweise

Wer das Portal betreibt, ergibt sich aus `Betreiber` (leer: die Organisation selbst), `Hoster` (leer: ein eigener Server) und optional `Serverstandort` – üblicherweise aus den Betreiber-Einstellungen. Beispiele: ein externer DSB beim Hoster mit `"Betreiber": "<DSB>", "Hoster": "<Hoster>"`, die Organisation selbst mit `"Betreiber": ""`.

`"Sicherung_Monate": 12` beschreibt eine tägliche Datensicherung, deren Stände so viele Monate zurückreichen. Kommt der Wert aus den Betreiber-Einstellungen, gilt er nur für Portale ohne `Hoster`. Sonst nennen Datenschutzhinweise und Löschbestätigung keine Dauer, sondern sagen, dass gelöschte Meldungen bis zum Ablauf der Sicherungen erhalten bleiben.

### Weitere Gestaltung

- Die illustrierte Gestaltung ist Standard: Strichsymbole (`SYMBOLE` in `helper.php`) in Linkfarbe, drei Schritte, Vertrauenskasten, Zugangskarte, Plaketten und Fristen in der Teamansicht. `"Illustrationen": "nein"` schaltet sie ab.
- Die Fußzeile zeigt links „Portal-Startseite · Webseite · Team“, in der Mitte die Sprachwahl („🌐 Deutsch“, die Weltkugel mit Alternativtext und Tooltip „Sprache“) und rechts „Sicherheit · Datenschutz · Impressum“ (Sicherheit nur mit dem Feld `Sicherheit`). Die Sprachwahl steht in der Mitte, solange beide Seiten Platz haben; sonst rückt sie zur Seite, statt dass eine Seite umbricht.
- Die beiden Buttons der Startseite sind gleich breit: nebeneinander, solange jede Beschriftung samt Symbol in die halbe Breite passt, sonst untereinander (kleines Skript in `js.php`; ohne Skript nebeneinander nach Länge der Beschriftung). Die Texte der drei Kacheln brechen an der mit `|` markierten Stelle der Übersetzung um, aber nur, wenn dann in allen Kacheln jeder Teil in eine Zeile passt; sonst und ohne Skript fließen sie normal.
- Fehlerseiten: `scripts/hinweise-errors.conf` in jede Portal-Konfiguration einbinden. 4xx liefert `common/error.php`, 5xx die statische `common/error-50x.html`.


## Fristen-Wächter

`scripts/deadlines.php` prüft täglich die Fristen jeder Meldung und erinnert per Mail:

| Frist | Ende | Mail |
|---|---|---|
| Eingangsbestätigung (§ 17 Abs. 1 Nr. 1 HinSchG) | 7 Tage nach Eingang | an das Team 2 Tage vorher, nach Ablauf wöchentlich |
| Rückmeldung (§ 17 Abs. 2 HinSchG) | 3 Monate nach der Bestätigung, ohne Bestätigung 3 Monate und 7 Tage nach Eingang | wie oben |
| Löschung (§ 11 Abs. 5 HinSchG) | 3 Jahre nach Abschluss | Ankündigung an das Team 30 Tage vorher, Veto per Antwort bis Fristende; danach wöchentlich an den Betreiber, bis gelöscht ist |

- Mails erhalten alle, die für die Meldung berechtigt sind (`<kunde>/passwd`), außer wer „keine“ Benachrichtigung gewählt hat. Erinnerungen an fällige Löschungen nennen den Befehl zum Löschen (`FRISTEN_BEFEHL`) und gehen nur an Teammitglieder mit einer Adresse der Domain `FRISTEN_INTERN`; gibt es keine, gehen sie an `FRISTEN_AN`. Dorthin geht außerdem, was sonst niemanden erreicht, und jeder Fehler; die Adresse dient zugleich als Antwortadresse.
- Bestätigt ist eine Meldung mit dem ersten Eintrag des Teams, einer Nachricht oder einem Statuswechsel. Die erste Nachricht kann schon die Rückmeldung sein; weil das Skript Inhalte nicht beurteilt, erinnert es dann mit „vielleicht schon erledigt“. Sicher zurückgemeldet ist mit der zweiten Nachricht oder dem Abschluss, auch wenn die Meldung danach wieder geöffnet wurde. Jeder Status außer „geschlossen“ gilt als offen. Hat die meldende Person ihren Token ungültig gemacht, entfallen die Fristen des Teams.
- Gelöscht wird frühestens 30 Tage nach der tatsächlich verschickten Ankündigung. Auch beim ersten Einsatz oder nach einem Ausfall erfährt das Team also vorher davon. Sind `postponements.json` oder `announcements.json` beschädigt, löscht und meldet das Skript nichts.
- Absender ist `no-reply@` der Portal-Domain, auch im Envelope; sonst signiert der Mailserver nicht, und eine strikte DMARC-Richtlinie verwirft die Mail.
- Bericht und Mails nennen nur Portal, Meldungs-ID und Daten, nie Inhalte.
- Löschen: `deadlines.php --loeschen <kunde> <id>` als Benutzer des Webservers. Das geht nur für abgeschlossene, angekündigte Meldungen nach Fristende und nur, wenn es Empfänger für die Löschbestätigung gibt; scheitert der Versand, gibt das Skript den Text zum Weiterleiten aus. Veto: `deadlines.php --aufschub <kunde> <id> TT.MM.JJJJ`. Datensicherungen bleiben unberührt; die Löschbestätigung sagt das.
- Von Hand: `php scripts/deadlines.php [<wurzel>]`, ohne Angabe gilt `HINWEISE_WURZEL`. Ergebnis 0: nichts fällig, 1: Fristen, 2: Fehler.
- Eigener PHP-Pool je Portal: Statt `hinweise-deadlines.service` die Vorlage `hinweise-deadlines@<kunde>` (Service und Timer) einrichten. Sie läuft als Pool-Benutzer `hinweise-<kunde>` mit `--kunde <kunde>` und hält ihren Stand in `/var/lib/hinweise-deadlines/<kunde>`. `--loeschen` und `--aufschub` finden diesen Ordner von selbst. In `FRISTEN_BEFEHL` steht `%k` für das Portal, z. B. `sudo -u hinweise-%k hinweise-deadlines`.
- Einrichten: siehe „Installation“. Skript und Fristberechnung (`portal/common/deadlines.php`, gemeinsam mit der Teamansicht) spielt das Install-Skript mit ein; die Units nach Änderungen von Hand neu kopieren.


## Entwicklung

Tests ohne echte Daten, vor jedem Einspielen des geänderten Bereichs:

- `tests/crypto-test.sh` – Verschlüsselung, Schlüsselkopien, Notfallschlüssel und Umschlüsseln
- `tests/colors-test.sh` – Farbprüfung und Farbableitung
- `tests/deadlines-test.sh` – Fristen-Wächter mit erzeugten Meldungen und einer sendmail-Attrappe
- `tests/delete-test.sh` – Löschen im Portal
- `tests/logo-test.sh` – Logo-Werkzeug
- `tests/install-test.sh` – Install-Skript in einem Wegwerf-Ordner
- `tests/e2e/portal-e2e.mjs` – Browser-Test gegen ein laufendes Portal: Anmeldung, Passkeys, Verschlüsselung (Aufruf im Dateikopf)

Die CI (GitHub Actions) fährt bei jedem Pull Request und auf `main` die Shell-Tests mit PHP 8.4 und 8.5, dazu `php -l`, Shellcheck und die REUSE-Prüfung; Dependabot hält Actions und Testabhängigkeiten aktuell. Wie man beiträgt, steht in [`CONTRIBUTING.md`](CONTRIBUTING.md).

Bewusst hingenommene Grenzen:

- Der Fokusrahmen auf farbigen Flächen (Kopfzeilen und Nachrichten im Verlauf) wird nicht geprüft; er liegt 2 px außerhalb des Elements und damit meist schon auf der Seite.
- Token-Kasten und Zugangskarte behalten die Knopffarbe als Rahmen. Er ist reiner Schmuck; die Aussage trägt der Text.
- Löscht der Fristen-Wächter eine Meldung in derselben Sekunde, in der jemand ihre Löschung beantragt, bleibt der Antrag stehen, bis die Teamansicht ihn bei der nächsten Bestätigung entfernt.
- Ist `deletion-requests.json` kurz nicht zu öffnen, kann ein Statuswechsel ohne die Antragssperre durchgehen; ein gleichzeitiger Antrag wird dann gegen den alten Status geprüft. Die Bestätigung prüft den Status erneut, gelöscht wird also nie zu Unrecht.


## Lizenz

[Eigene Lizenz](LICENSE.md) ([English](LICENSE.en.md)), keine Open-Source-Lizenz: Steuerbegünstigte gemeinnützige Körperschaften nutzen das Portal kostenfrei, alle anderen – Unternehmen, Behörden, Kirchen – mit einer Lizenz je Verantwortlichem; ein Dienstleister braucht keine eigene. Ausprobieren ohne echte Meldungen ist frei, Weitergabe nicht. Fremde Bestandteile (MIT) und ihre Versionen nennt die SBOM [`sbom.cdx.json`](sbom.cdx.json). Wer mitgewirkt hat, steht in [`CONTRIBUTORS.md`](CONTRIBUTORS.md).
