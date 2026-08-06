# Referenzumgebung

Stand: 06.08.2026

## Bekannter Ist-Stand

| Bereich | Stand | Bewertung |
|---|---|---|
| Bereitstellung | selbst gehostet, Docker, Traefik | für die App transparent |
| Nextcloud | 33.0.2 (interne Version 33.0.2.2) | Referenzversion; installiert und ohne Wartungsmodus |
| PHP | noch zu erfassen | App-Mindestversion 8.1 |
| Datenbank | noch zu erfassen | vor M2 für Migrationstests erforderlich |
| Primärspeicher | noch zu erfassen | vor dem M0-Livetest erforderlich |
| Externe Speicher | noch zu erfassen | nicht blockierend für das MVP |
| Bereitstellungsstatus | `maintenance: false`, `needsDbUpgrade: false` | bereit für den M1-Installationstest |

Die Referenzinstanz läuft im offiziellen Docker-Image `nextcloud:33.0.2-apache`; der Containername lautet `nextcloud`.

## Kompatibilitätsstrategie

- `info.xml`: Nextcloud 32 bis 34
- öffentliche Supportzusage für die erste stabile Version: Nextcloud 32 bis 34
- niedrigste PHP-Syntax und Composer-Plattform: PHP 8.1
- statische Analyse gegen die älteste unterstützte OCP-API
- CI-Matrix gegen alle freigegebenen Nextcloud-Hauptversionen

## M0-Ergebnis

Der statische API-Spike ist abgeschlossen:

1. `OCP\Files\Events\Node\NodeCreatedEvent` ist eine öffentliche, typisierte API und seit Nextcloud 20 verfügbar.
2. Listener werden über `IRegistrationContext::registerEventListener()` registriert und erst beim passenden Ereignis aufgelöst.
3. Das Event liefert über `getNode()` den neu angelegten Node nach der Erstellung.
4. Dateien werden zuverlässig über `OCP\Files\File` von Ordnern unterschieden.
5. `Node::getId()` und `Node::getStorage()->getId()` stehen in der gesamten Kompatibilitätsmatrix öffentlich zur Verfügung.
6. Für das Matching genügt die Elternkette des neuen Nodes; kein Verzeichnisinhalt muss gelesen werden.

## Noch ausstehender Livetest

Auf der Zielinstanz werden vor M2 einmalig erfasst beziehungsweise geprüft:

- aktuelle Nextcloud-, PHP- und Datenbankversion
- Primärspeicher und relevante externe Speicher
- genau ein `NodeCreatedEvent` pro fertiger Datei bei Browser-, WebDAV- und Desktop-Upload
- Verhalten bei Chunked Uploads
- Storage- und File-ID für eigene und geteilte Ordner
- Verfügbarkeit des angemeldeten Benutzers im Eventkontext

Diese Punkte benötigen keine laufende Diagnosefunktion und erzeugen nach Abschluss keine Hintergrundlast.
