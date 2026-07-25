# LoxBerry-Plugin: Saugroboter (Valetudo)

Bindet einen Saugroboter mit der cloudfreien Firmware **Valetudo** an Loxone an —
mit **einer** Abfrage statt vier und einer sauberen **Statuszahl** statt
zusammengesuchter Textbruchstücke. Steuerbefehle laufen als einfache GET-Aufrufe,
die Loxone direkt als virtuellen Ausgang senden kann (Valetudo verlangt sonst
PUT mit JSON-Rumpf).

Kompatibel mit LoxBerry 3.x und **LoxBerry 4** (reines PHP, PHP 7.4 und 8.x).

## Funktionen

- **Ein Endpunkt** fasst Status, Statistik (aktuell und gesamt) und
  Verbrauchsmaterial zusammen — statt vier virtuellen Eingängen im 10-Sekunden-Takt
  genügt einer alle 30 Sekunden
- **Statuszahl** für Loxone: 0 Ladestation, 1 bereit, 2 reinigt, 3 pausiert,
  4 fährt zur Station, 5 fährt, 9 Fehler
- Batterie, Ladezustand, Fehlercode und Fehlertext
- Letzte Reinigung (m²/Minuten) und Gesamtwerte (m², Stunden, Anzahl)
- **Verbrauchsmaterial** in Reststunden (Filter, Haupt-/Seitenbürste, Sensoren)
  mit frei wählbarer Warnschwelle → `MATWARN`
- **Steuerung** per GET: `start`, `stop`, `pause`, `home`, `locate`,
  Raumreinigung (`segments`), Saugstärke (`fan`), gespeicherte Position (`goto`)
- **Meldungen**: Reinigung fertig (mit Fläche und Dauer), Störung, Wartung fällig
  — als Ansage (TTS) und/oder Push über Loxone
- Bis zu **2 Roboter**, MQTT, JSON (inkl. Raumliste), Protokoll mit Rotation
- Reiter: Einstellungen, Einbindung in Loxone (mit kompletter Baustein-Liste),
  Test (inkl. Segment-IDs und ungefährlichem „Piepsen"-Test), Protokoll

## Endpunkte

| Aufruf | Zweck |
|---|---|
| `/plugins/saugrobo/robo.php` | Loxone-Zeile `ROBO;OK=..;CODE=..;BATT=..;FILTER=..;MATWARN=..;…` |
| `/plugins/saugrobo/robo.php?debug=1` | Klartext inkl. Raumliste |
| `/plugins/saugrobo/robo.php?json=1` | kompletter Zustand als JSON |
| `/plugins/saugrobo/robo.php?cmd=start` | Reinigung starten (auch `stop`, `pause`, `home`, `locate`) |
| `/plugins/saugrobo/robo.php?cmd=segments&p=1,4` | nur bestimmte Räume reinigen |
| `/plugins/saugrobo/robo.php?cmd=fan&p=max` | Saugstärke setzen |

## Voraussetzung

Auf dem Roboter läuft **Valetudo** (Version mit `/api/v2`). Es werden keine
Cloud-Dienste und keine Zugangsdaten benötigt — die Kommunikation bleibt im
eigenen Netz.

## Datenschutz

Es sind **keine persönlichen Daten** im Plugin enthalten. Adresse und
Einstellungen liegen ausschließlich lokal (`config/plugins/saugrobo/robo.json`).

## Lizenz

MIT — siehe [LICENSE](LICENSE).
