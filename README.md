# Bridge Wertungsportal -> DeWIS

Ersetzt die SOAP-Schnittstelle von DeWIS und kommuniziert mit der REST-Schnittstelle des Wertungsportals.

## Dokumentation

* [Vereinslisten-Schnittstelle](docs/vereinslisten-api.md) — Mitgliederlisten als
  JSON für Vereinswebsites: Abruf, Zugangsschlüssel, Verwaltung und Sperren
* [Zwischenspeicher gezielt leeren](docs/zwischenspeicher.md) — Backend-Modul, das
  die Cache-Einträge eines einzelnen Turniers, Spielers oder Vereins löscht
* [Turnierdaten täglich vorladen](docs/vorladen.md) — Cronjob, der die Turniere der
  letzten 30 Tage in den Zwischenspeicher holt, damit nicht der erste Besucher wartet
* [Massenabfragen bremsen](docs/besucherbremse.md) — Höchstabrufe je Minute, Stunde und
  Tag; gebremste Besucher stehen mit Adresse, Browserkennung und Mitglied im Protokoll

**Frank Binding**
