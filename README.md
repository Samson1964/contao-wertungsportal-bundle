# Bridge Wertungsportal -> DeWIS

Ersetzt die SOAP-Schnittstelle von DeWIS und kommuniziert mit der REST-Schnittstelle des Wertungsportals.

## Dokumentation

* [Insert-Tags mit Wertungsdaten](docs/insert-tags.md) — `{{dwz::…}}`, `{{elo::…}}`,
  `{{ftitel::…}}` und `{{verein::…}}` mit der NU-Nummer (früher Helper-Bundle mit DeWIS-ID)
* [Adressen der Frontend-Seiten](docs/frontend-adressen.md) — Karteikarte, Verein, Verband,
  Turnier: Aufbau der Adressen, Suffix, alte Verweise, Unterschiede zwischen Contao 4.13 und 5
* [Zugang zur Schnittstelle: zwei Kennungen](docs/zugang.md) — Anmeldung per OAuth2 für
  Turniere und Personen und (seit 1.46.0) für die DWZ-Liste: Einstellungen, Tokendateien,
  Verhalten vor und nach der Umstellung bei nu, Zip-Downloads
* [Turnierseiten: Nichtmitglieder und Erwartungswerte](docs/turnierseiten.md) — warum
  Nichtmitglieder keine neue DWZ bekommen, woher ihre Eingangswertung stammt und wie der
  Erwartungswert je Partie im Spielberichtsbogen zustande kommt
* [Vereinslisten-Schnittstelle](docs/vereinslisten-api.md) — Mitgliederlisten als
  JSON für Vereinswebsites: Abruf, Zugangsschlüssel, Verwaltung und Sperren
* [Zwischenspeicher gezielt leeren](docs/zwischenspeicher.md) — Backend-Modul, das
  die Cache-Einträge eines einzelnen Turniers, Spielers oder Vereins löscht
* [Rohdaten der Schnittstelle herunterladen](docs/rohdaten.md) — Backend-Modul, das
  die unveränderte Antwort einer Schnittstellenfunktion als JSON-Datei liefert
* [Turnierdaten nachts vorladen](docs/vorladen.md) — Cronjob, der Turnierdaten und
  Karteikarten in den Zwischenspeicher holt, damit nicht der erste Besucher wartet
* [Massenabfragen bremsen](docs/besucherbremse.md) — Höchstabrufe je Minute, Stunde und
  Tag; gebremste Besucher stehen mit Adresse, Browserkennung und Mitglied im Protokoll
* [Unmögliche Werte protokollieren](docs/auffaellige-werte.md) — Zahlen, die es nach dem
  Regelwerk nicht geben kann, mit Spieler und Turnier festhalten und an nu melden
* [Ranglisten](docs/ranglisten.md) — fertige Deutschland-Ranglisten nach DWZ und Elo,
  für andere Bundles abrufbar: Altersklasse, Geschlecht, geteilte Plätze, Verbandskürzel
* [DWZ-Dateien herunterladen und aufbereiten](docs/dwz-dateien.md) — die beiden
  Konsolenbefehle, die die früheren Cron-Skripte ersetzen (Aufruf beim Hoster umstellen!)
* [Hintergrunddateien für Swiss-Chess](docs/swiss-chess.md) — LST und SWX aus der
  DWZ-Liste erzeugen, für Swiss-Chess 10 und für ältere Fassungen

## Voraussetzungen

Contao 4.13 oder 5, PHP 7.4 bis 8.4.

**Frank Binding**
