# ADR 0001: Datei-Ereignis und persistente Ordneridentität

- Status: akzeptiert
- Datum: 06.08.2026

## Kontext

Die App muss neue Dateien in abonnierten Ordnern erkennen, ohne Ordner periodisch zu scannen. Abonnements müssen Umbenennungen und Verschiebungen des Zielordners überstehen. Pfade sind deshalb weder als Ereignisquelle noch als persistente Identität geeignet.

## Entscheidung

1. Neue Dateien werden ausschließlich über `OCP\Files\Events\Node\NodeCreatedEvent` verarbeitet.
2. Der Listener verwirft alle Nodes, die keine Instanz von `OCP\Files\File` sind.
3. Ein abonnierter Ordner wird durch `storage_id + folder_file_id` identifiziert.
4. `display_path` darf zusätzlich als UI-Cache gespeichert werden, ist aber nie maßgeblich für das Matching.
5. Beim Upload wird nur vom direkten Elternordner bis zur Mount-/Benutzerwurzel aufgestiegen. Die dabei ermittelten Identitäten werden in einer indexierten Datenbankabfrage mit den Abonnements verglichen.
6. Datei-Inhalte und Verzeichnislisten werden nicht gelesen.

## Begründung

`NodeCreatedEvent` ist eine öffentliche, typisierte Nextcloud-API und wird nach der Erstellung ausgelöst. `Node::getId()` bleibt bei einer normalen Umbenennung oder Verschiebung innerhalb desselben Speichers stabil. Die zusätzliche Storage-ID verhindert Kollisionen und macht die Speichergrenze explizit. Die Arbeitsmenge hängt dadurch nur von der Ordnertiefe und der Zahl passender Abonnements ab, nicht von der Zahl enthaltener Dateien.

## Konsequenzen

- Im Leerlauf gibt es keinerlei App-Arbeit.
- Ein nicht passender Upload erzeugt nur die begrenzte Elternkettenauflösung und eine indexierte Abfrage.
- Ein Verschieben oder Kopieren einer bereits vorhandenen Datei ist im MVP kein Upload und wird nicht über zusätzliche Events gemeldet.
- Direkte Kopien in das Server-Datenverzeichnis außerhalb von Nextcloud werden nicht erkannt.
- Storage-Wechsel und spezielle Share-/External-Storage-Mounts benötigen Integrationstests.
