# Mitwirken

Beiträge sind willkommen: Code, Tests, Texte, Übersetzungen, Fehlerberichte und Ideen. Wer mitwirkt, folgt dem [Verhaltenskodex](CODE_OF_CONDUCT.md).

## Vorher absprechen

Größere Änderungen bitte erst als [Issue](https://github.com/datenschutz-individuell/hinschg-meldekanal/issues) beschreiben, bevor ein Pull Request entsteht – so arbeitet niemand an etwas, das nicht übernommen wird.

## Entwicklung

Aufbau, Installation und Tests stehen in der [README](README.md), Verfahren und Risiken der Verschlüsselung in [SECURITY.md](SECURITY.md).

- **Tests:** Vor jedem Pull Request die Tests zum geänderten Bereich fahren (`tests/*-test.sh`, ohne Abhängigkeiten außer PHP). Die CI fährt alle, dazu `php -l`, Shellcheck und REUSE. Der Browser-Test `tests/e2e/portal-e2e.mjs` braucht ein laufendes Portal und läuft von Hand.
- **Sprache:** Code-Kommentare und Dateinamen englisch. Deutsch bleiben öffentliche URL-Pfade (`/melden/`, `/team/` …), Texte für Menschen (Oberfläche, README, Commit-Texte).
- **Datendateien:** Neue Text- und Protokolldateien beginnen mit einer `#`-Zeile, die die Felder nennt.
- **Lizenzkopf:** Jede neue Datei trägt einen SPDX-Kopf (siehe bestehende Dateien); was keinen tragen kann, deckt [REUSE.toml](REUSE.toml) ab.
- **Fremdbestandteile:** Wer eine Bibliothek aufnimmt, aktualisiert oder entfernt, zieht [sbom.cdx.json](sbom.cdx.json) und [REUSE.toml](REUSE.toml) nach.
- **Commits:** Eine Sache je Commit, keine fixup- oder squash-Commits im Pull Request.

## Lizenz

Das Portal steht unter einer [eigenen Lizenz](LICENSE.md) ([English](LICENSE.en.md)); die Rechte daran liegen beim Urheber, der es auch gegen Entgelt lizenziert. Ein Beitrag ist deshalb ein Geschenk: Wer einen Pull Request stellt, räumt dem Urheber die Rechte nach Ziffer 6 der Lizenz ein. Wer das nicht möchte, sagt es bitte vorher. Wer mitgewirkt hat, steht in [CONTRIBUTORS.md](CONTRIBUTORS.md).

## Sicherheitslücken

Nicht als öffentliches Issue melden, sondern wie in [SECURITY.md](SECURITY.md#sicherheitslücken-melden) beschrieben.
