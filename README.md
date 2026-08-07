# Folder Upload Notifications for Nextcloud

Eine schlanke Nextcloud-App, mit der Benutzer Ordner abonnieren und sich über neue Dateien per Push, E-Mail oder über beide Wege benachrichtigen lassen können.

> Status: `0.4.0-alpha.5` wird für die erste öffentliche Alpha im Nextcloud App Store vorbereitet.

## Funktionen

- eigene und geteilte Ordner abonnieren
- Unterordner optional einbeziehen
- eigene Uploads optional melden
- Push und E-Mail pro Abonnement getrennt auswählen
- mehrere Uploads zu einer Sammelmeldung bündeln
- Abonnements bleiben beim Umbenennen oder Verschieben eines Ordners erhalten
- keine Ordner-Scans, Dateiinhaltsanalyse oder Telemetrie

## Voraussetzungen

- Nextcloud 32 bis 34
- für E-Mails: eingerichteter Nextcloud-Mailversand und eine E-Mail-Adresse im Benutzerprofil
- für zuverlässige Sammelmeldungen: Nextcloud-Hintergrundjobs im Cron-Modus

## Installation

### Nextcloud App Store

Vorabversionen werden nur Instanzen angeboten, deren Update-Kanal Alpha-/Beta-Versionen zulässt. Nach der Veröffentlichung kann die App dort gesucht und installiert werden.

### Manuell

Das Release-Archiv als Ordner `folder_upload_notifications` in ein Nextcloud-App-Verzeichnis entpacken und anschließend über die App-Verwaltung oder mit folgendem Befehl aktivieren:

```bash
php occ app:enable folder_upload_notifications
```

## Bedienung

Die Verwaltung befindet sich unter **Profilbild → Persönliche Einstellungen → Ordner-Upload-Benachrichtigungen**. Dort können Benutzer einen Ordner auswählen, Push und E-Mail festlegen, Unterordner sowie eigene Uploads konfigurieren und Abonnements wieder entfernen.

## Sammelmeldungen

Mehrere Dateien desselben Uploaders im selben Zielordner werden pro Empfänger zwei Minuten lang gesammelt und anschließend als eine Meldung versendet. Der Versand erfolgt beim nächsten Nextcloud-Hintergrundjob. Bei einem Cron-Intervall von fünf Minuten erscheint eine Meldung daher üblicherweise etwa zwei bis sieben Minuten nach dem ersten Upload.

## Datenschutz und Sicherheit

Die App verarbeitet nur die für Abonnements und Benachrichtigungen notwendigen Nextcloud-internen IDs, Anzeigenamen und Pfade. Dateiinhalte werden nicht gelesen. Es gibt keine Werbung, Telemetrie oder externen Analyse- und Cloud-Dienste.

Sicherheitsprobleme bitte gemäß [SECURITY.md](SECURITY.md) vertraulich melden.

## Für Entwickler

Die [experimentelle OCS-API](docs/API.md) kann während der Alpha noch geändert werden. Hinweise zur Mitarbeit und zum Releaseprozess stehen in [CONTRIBUTING.md](CONTRIBUTING.md) und [docs/RELEASING.md](docs/RELEASING.md). Versionsänderungen werden im [CHANGELOG.md](CHANGELOG.md) dokumentiert.
