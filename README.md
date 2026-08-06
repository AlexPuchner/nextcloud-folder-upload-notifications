# Folder Upload Notifications for Nextcloud

Eine schlanke Nextcloud-App, mit der Benutzer einzelne Ordner abonnieren und bei neuen Dateien über das vorhandene Nextcloud-Benachrichtigungssystem informiert werden können.

> Projektstatus: öffentliche Alpha-Vorbereitung für `0.4.0-alpha.4`; Kernfunktionen auf Nextcloud 33 live getestet

## Zielbild

- Ordner direkt in der Dateien-App abonnieren und wieder abbestellen
- Push und E-Mail pro Ordnerabonnement getrennt konfigurierbar
- mehrere Dateien desselben Uploaders im selben Zielordner zu einer Meldung bündeln
- Push über Nextcloud-Glocke und vorhandene mobile Push-Kanäle
- E-Mail über den zentral konfigurierten Nextcloud-Mailer
- Unterstützung eigener und geteilter Ordner
- rekursive Überwachung optional pro Abonnement
- eigene Uploads standardmäßig ausblenden
- stabile Abonnements auch nach Umbenennen oder Verschieben eines Ordners
- kein Ordner-Scan und kein dauerhaft laufender Prozess

Die App reagiert auf Nextclouds `NodeCreatedEvent`. Erst wenn Nextcloud tatsächlich eine Datei anlegt, wird der Listener ausgeführt. Die Zuordnung zu Abonnements erfolgt zuerst über interne Datei-/Speicher-IDs und indexierte Datenbankabfragen. Bei Freigabe-Mounts wird die Datei zusätzlich nur für die aktuell berechtigten Empfänger in deren sichtbarem Dateibaum aufgelöst, damit auch abonnierte virtuelle Sammelordner korrekt funktionieren. Passende Ereignisse werden pro Empfänger, Uploader und Zielordner für zwei Minuten in einer kleinen Datenbankzeile gesammelt. Anschließend versendet ein einmaliger Nextcloud-Hintergrundjob genau eine Meldung; Dateien und Ordnerinhalte werden dabei nicht gescannt. Der Job wird nach zwei Minuten freigegeben und läuft beim nächsten Aufruf des konfigurierten Nextcloud-Hintergrundjob-Systems; bei einem fünfminütigen Cron-Intervall kann die Zustellung deshalb entsprechend später eintreffen.

## Planung

Der vollständige Projektauftrag mit Anforderungen, Architektur, Datenmodell, Meilensteinen, Risiken und Abnahmekriterien befindet sich in [docs/PROJEKTAUFTRAG.md](docs/PROJEKTAUFTRAG.md).

## Vorläufige technische Eckdaten

| Bereich | Festlegung |
|---|---|
| Anzeigename | Folder Upload Notifications |
| Repository | `nextcloud-folder-upload-notifications` |
| vorgesehene App-ID | `folder_upload_notifications` |
| Backend | PHP, Nextcloud App Framework und OCP-APIs |
| Frontend | TypeScript/Vue mit offiziellen Nextcloud-Komponenten |
| Benachrichtigungen | Nextcloud Notifications API |
| App-Kompatibilität | Nextcloud 32 bis 34 |
| öffentlich unterstützte Versionen | Nextcloud 32 bis 34 |
| Referenzinstanz | Nextcloud 33.0.2 (interne Version 33.0.2.2), Docker-Apache-Image |
| vorgesehene Lizenz | AGPL-3.0-or-later |

## Nicht Bestandteil der Architektur

- periodische Verzeichnisabfragen
- direkte Überwachung des Nextcloud-Datenverzeichnisses mit `inotify`
- Lesen oder Analysieren von Dateiinhalten
- externe Cloud-, Analyse- oder Telemetriedienste

## Entwicklungsstand

- [x] Projektauftrag und Anforderungen
- [x] M0: API-Spike für `NodeCreatedEvent` und stabile Ordneridentität
- [x] M1: installierbares App-Grundgerüst; Livetest auf Nextcloud 33 bestanden
- [x] M2: Abonnement-Backend und CRUD-API; Livetest auf Nextcloud 33 bestanden
- [x] M3: Event- und Matching-Kern; Fremd- und Eigen-Upload-Filter live getestet
- [x] M4: native Benachrichtigungen; Glockenmeldung auf Nextcloud 33 live getestet
- [x] M5: persönliche Einstellungsseite; Ordnerauswahl, Optionen und Löschen live getestet
- [x] M5.1: abonnierte virtuelle Share-Sammelordner; Livetest auf Nextcloud 33 bestanden
- [x] M5.2: Push und E-Mail pro Abonnement; Livetest auf Nextcloud 33 bestanden
- [ ] M5.3: Sammelmeldungen für Mehrfach-Uploads; Implementierung bereit für den Livetest

## Bedienung

Nach der Installation findet jeder Benutzer seine Ordnerabonnements unter **Profilbild → Persönliche Einstellungen → Ordner-Upload-Benachrichtigungen**. Dort lassen sich Ordner über den nativen Nextcloud-Dateidialog hinzufügen, Push und E-Mail je Abo auswählen, Unterordner und eigene Uploads konfigurieren sowie Abos wieder entfernen. Für E-Mail-Benachrichtigungen müssen der zentrale Mailversand eingerichtet und im Benutzerprofil eine E-Mail-Adresse hinterlegt sein.

## Installation

### Nextcloud App Store

Die Alpha-Version wird nur auf Nextcloud-Instanzen angeboten, deren Update-Kanal Vorabversionen zulässt. Nach der Veröffentlichung kann sie in der App-Verwaltung gesucht und installiert werden. Unterstützt werden Nextcloud 32 bis 34 sowie PostgreSQL, MariaDB/MySQL und SQLite.

### Manuelle Installation

Das Release-Archiv muss als Ordner `folder_upload_notifications` unterhalb eines Nextcloud-App-Verzeichnisses entpackt werden. Anschließend lässt sich die App in der App-Verwaltung oder mit `occ app:enable folder_upload_notifications` aktivieren. Für zuverlässige Sammelbenachrichtigungen sollte Nextclouds Hintergrundjob-Modus auf Cron eingestellt sein.

## Datenschutz und Sicherheit

Die App verarbeitet ausschließlich Nextcloud-interne Benutzer-, Ordner- und Datei-IDs sowie die für eine Benachrichtigung notwendigen Anzeigenamen und Pfade. Dateiinhalte werden weder gelesen noch analysiert. Es gibt keine Telemetrie, Werbung oder externen Analyse-/Cloud-Dienste. Sicherheitsprobleme bitte nicht öffentlich melden; der verantwortungsvolle Meldeweg ist in [SECURITY.md](SECURITY.md) beschrieben.

## Entwicklung und Releases

Entwicklungssetup, Qualitätsprüfungen und der App-Store-Releaseprozess sind in [CONTRIBUTING.md](CONTRIBUTING.md) und [docs/RELEASING.md](docs/RELEASING.md) dokumentiert. Änderungen werden in [CHANGELOG.md](CHANGELOG.md) festgehalten.

Die Architekturentscheidungen und die erfasste Referenzumgebung liegen unter [`docs/adr/`](docs/adr/) und in [`docs/REFERENCE_ENVIRONMENT.md`](docs/REFERENCE_ENVIRONMENT.md). Die OCS-Endpunkte sind in [`docs/API.md`](docs/API.md) dokumentiert.

## Quellenbasis

- [Nextcloud Event-System](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html)
- [Nextcloud Filesystem API](https://docs.nextcloud.com/server/latest/developer_manual/basics/storage/filesystem.html)
- [Nextcloud Notifications](https://github.com/nextcloud/notifications/blob/master/docs/notification-workflow.md)
- [Offizielles Nextcloud App Template](https://github.com/nextcloud/app_template)
