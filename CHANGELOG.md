# Änderungshistorie
Ziel: Semantic Versioning nach https://semver.org/

## 0.6 – unveröffentlicht
- Betreiber-Einstellungen für alle Portale (`<wurzel>/settings.json`), Beispiel-Installation `scripts/install-example.sh`, eigene Lizenz: frei für steuerbegünstigte gemeinnützige Körperschaften, sonst Lizenz je Verantwortlichem, keine Weitergabe (`LICENSE.md`, englisch `LICENSE.en.md`; REUSE, SBOM `sbom.cdx.json`)
- Schriftgrößen der Kunden-Webseite (Fließtext, h1–h3) werden übernommen; Schrift und Größen im Browser gemessen (`scripts/measure-website.mjs`)
- Schrift der Kunden-Webseite: `scripts/check-fonts.php` übernimmt freie Schriften (Google Fonts) selbst ausgeliefert und meldet lizenzpflichtige zur Rückfrage
- Nachrichtenverlauf als Chat: Parteifarben je Gesprächsseite (von der Kunden-Webseite), Symbole, Seitenbalken, Einrückung, „Start/Ende der Falldatei“; Schriftfarben per `scripts/derive-colors.php` berechnet
- Barrierearm: Rand der Eingabefelder mit mind. 3:1; `scripts/check-colors.php` prüft die Kontraste jedes Portals (WCAG AA, hell und dunkel), der Deploy bricht bei Verstoß ab; Kopfzeilen des Verlaufs per Variable einfärbbar
- Nachrichtenverlauf: Farben aus den Kundenfarben gemischt (statt immer im Farbton), Kästen bündig mit dem Eingabefeld, Zeichenzähler überdeckt den Knopf nicht mehr
- Fußzeile: Sprachwahl als aufklappbares Menü „🌐 Sprache: Deutsch ▾“ (`<details>`, ohne JavaScript, barrierefrei); Impressum rechts. Neue Portale bieten Englisch erst nach Freigabe durch den Kunden an
- Logo: nur noch eine `logo.svg`, die sich selbst auf den dunklen Modus umstellt; `scripts/logo-light-dark.py` baut sie aus zwei Fassungen (auch PNG/WebP/JPEG) oder färbt um; der Deploy verlangt sie
- Mehrsprachig: Startseite, Datenschutzhinweise und Meldeformular auf Englisch und weiteren Sprachen; Wahl über Browser-Sprache oder `/xx/` im Pfad, ohne Cookie
- Datenschutzhinweise überarbeitet (Speicherdauer, Sicherungen, Benachrichtigungen, Rechte vollständig, Rechtsgrundlagen in Worten, externe Meldestellen); Begriffe des HinSchG
- Sicherheit: Team-Seite ohne Anmeldung gesperrt (nginx-Vorlage und Code); Token-Ungültigmachen nur durch die meldende Person; Ausgaben escaped; Fehlerprotokoll ohne Client-IP (nginx-logfilter)
- Schutz vor Bot-Meldungen ohne CAPTCHA und ohne gespeicherte IP: Drosselung je IP in nginx, Zeit-Token und Honeypot im Formular, höchstens 3 neue Meldungen je Portal in 15 Minuten; abgelehnte Eingaben bleiben im Formular
- Ein Code für alle Portale (`_core`); Kundentexte als Felder in `settings.json`
- Datenschutzhinweise zeigen bei Pflichtangaben (`Level: erforderlich`) den passenden Absatz
- Neue Gestaltung: responsiv, hell/dunkel, Formulare und Nachrichtenverlauf als Karten; Farben je Kunde (style.css, Farbton), Silbentrennung ohne Trennung von Eigennamen
- Mail-Adressen anklickbar, ohne Klartext im Quelltext
- Fristen-Wächter: erinnert das Team an Eingangsbestätigung und Rückmeldung (§ 17 HinSchG), kündigt die Löschung (§ 11 Abs. 5 HinSchG) 30 Tage vorher mit Veto-Möglichkeit an, erinnert den Betreiber danach an die Löschung und verschickt die Löschbestätigung

## 0.5 – 20.11.2023
Initiale Version (Funktion noch unvollständig)
