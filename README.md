# Bridge Wertungsportal -> DeWIS

Ersetzt die SOAP-Schnittstelle von DeWIS und kommuniziert mit der REST-Schnittstelle des Wertungsportals.

## Dokumentation

* [Vereinslisten-Schnittstelle](docs/vereinslisten-api.md) — Mitgliederlisten als
  JSON für Vereinswebsites: Abruf, Zugangsschlüssel, Verwaltung und Sperren
* [Zwischenspeicher gezielt leeren](docs/zwischenspeicher.md) — Backend-Modul, das
  die Cache-Einträge eines einzelnen Turniers, Spielers oder Vereins löscht
* [Turnierdaten nachts vorladen](docs/vorladen.md) — Cronjob, der Turnierdaten und
  Karteikarten in den Zwischenspeicher holt, damit nicht der erste Besucher wartet
* [Massenabfragen bremsen](docs/besucherbremse.md) — Höchstabrufe je Minute, Stunde und
  Tag; gebremste Besucher stehen mit Adresse, Browserkennung und Mitglied im Protokoll
* [Unmögliche Werte protokollieren](docs/auffaellige-werte.md) — Zahlen, die es nach dem
  Regelwerk nicht geben kann, mit Spieler und Turnier festhalten und an nu melden

**Frank Binding**
