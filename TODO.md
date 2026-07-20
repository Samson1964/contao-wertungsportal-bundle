# Migrationsstatus Wertungsportal-Bundle

Die nachfolgenden Tabellen enthalten den Stand der Umsetzung der alten DeWIS-Funktionen vom contao-dewis-bundle im neuen contao-wertungsportal-bundle.

## Spielersuche

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Suchformular                             | ☑                                     |
| Suchergebnis                             | ☑                                     |
| Suchergebnis:Anzahl Treffer              | ☑                                     |
| Suchergebnis:Name verlinkt (Karteikarte) | ☑                                     |
| Suchergebnis:Letzte Auswertung           | ☑                                     |
| Suchergebnis:DWZ                         | ☑                                     |
| Suchergebnis:Elo                         | ☑                                     |
| Suchergebnis:FIDE-Titel                  | ☑ (in DeWIS nicht vorhanden)          |
| Suchergebnis:Verein verlinkt             | ☑                                     |

## Karteikarte (Spieler)

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Name                                     | ☑                                     |
| Geschlecht (ausblendbar)                 | ☑                                     |
| Geburtsjahr (ausblendbar)                | ☑                                     |
| Verein (verlinkt)                        | ☑                                     |
| ZPS-Nr. (Mitgliedsnummer)                | ☑                                     |
| Status                                   | ☑                                     |
| FIDE-Elo                                 | ☑ (keine Daten von nu)                |
| FIDE-ID (verlinkt)                       | ☑                                     |
| FIDE-Titel                               | ☑ (keine Daten von nu)                |
| FIDE-Nation (verlinkt)                   | ?  Verlinkung fehlt noch               |
| Historie (Verlinkung zu EloBase)         | ❌ (Integration geplant)               |
| Zuständiger Wertungsreferent (Kontakt)   | ❌ (keine Daten von nu)                |
| Spielerfoto                              | ☑ (eigenes Bild in WP-Personen, Fallback tl_dwz_spi per externeNr) |
| Karteikarte                              | ☑                                     |
| Karteikarte:Nr.                          | ☑                                     |
| Karteikarte:Jahr                         | ☑                                     |
| Karteikarte:Turnier (Scoresheet verlinkt)| ☑                                     |
| Karteikarte:Punkte                       | ☑                                     |
| Karteikarte:Partien                      | ☑                                     |
| Karteikarte:We (Erwartungswert)          | ☑                                     |
| Karteikarte:E(ntwicklungskoeffizient)    | ☑                                     |
| Karteikarte:Gegner                       | ☑                                     |
| Karteikarte:Leistung                     | ☑                                     |
| Karteikarte:Aktuelle DWZ                 | ☑                                     |
| Diagramm DWZ und Leistung                | ☑ (serverseitiges SVG, ohne Chart.js) |
| Ranglistenplatzierungen                  | ❌ (keine Daten von nu)                |
| Verbandszugehörigkeiten                  | ☑ (Kette aus tl_wertungsportal_clubs) |

## Vereinssuche

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Suchformular                             | ☑                                     |
| Suchergebnis                             | ☑                                     |
| Suchergebnis:Anzahl Treffer Verbände     | ☑                                     |
| Suchergebnis:Name Verband (verlinkt)     | ☑                                     |
| Suchergebnis:ZPS Verband                 | ☑                                     |
| Suchergebnis:Anzahl Treffer Vereine      | ☑                                     |
| Suchergebnis:Name Verein (verlinkt)      | ☑                                     |
| Suchergebnis:ZPS Verein                  | ☑                                     |

## Vereinsliste

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Verlinkung Alphaliste/Rangliste          | ☑                                     |
| Vereinsname                              | ☑                                     |
| Vereinsname (alternativ)                 | ☑                                     |
| Vereinslogo                              | ☑                                     |
| Homepage                                 | ☑                                     |
| Über den Verein                          | ☑                                     |
| Spielerliste:Platz/Nr.                   | ☑                                     |
| Spielerliste:Mitgliedsnummer             | ☑                                     |
| Spielerliste:Status                      | ☑                                     |
| Spielerliste:Name                        | ☑                                     |
| Spielerliste:Geschlecht (ausblendbar)    | ☑                                     |
| Spielerliste:Letzte Auswertung           | ☑                                     |
| Spielerliste:DWZ                         | ☑                                     |
| Spielerliste:Elo                         | ☑ (keine Daten von nu)                |
| Spielerliste:FIDE-Titel                  | ☑ (keine Daten von nu)                |
| Verbandszugehörigkeiten                  | ❌ (geplant)                           |
| Zuständiger Wertungsreferent (Kontakt)   | ❌ (keine Daten von nu)                |

## Scoresheet

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Kopf:Turniername                         | ☑                                     |
| Kopf:Turniercode                         | ☑                                     |
| Kopf:Auswerter (Kontaktdaten)            | ☑ (nur Name und E-Mail)               |
| Kopf:Turnierende                         | ☑                                     |
| Kopf:Datum Erstberechnung                | ❌ (keine Daten von nu)                |
| Kopf:Datum Letzte Berechnung             | ☑                                     |
| Kopf:Anzahl Spieler                      | ☑                                     |
| Kopf:Anzahl Runden                       | ☑ (falscher Wert von nu)              |
| Kopf:Anzahl Partien                      | ☑                                     |
| Kopf:Link Turnierauswertung              | ☑                                     |
| Kopf:Link Turnierergebnisse              | ☑                                     |
| Spieler:Name                             | ☑                                     |
| Spieler:DWZ                              | ☑                                     |
| Scoresheet:Runde                         | ☑                                     |
| Scoresheet:Gegner (Karteikarte verlinkt) | ☑                                     |
| Scoresheet:Link Gegner-Scoresheet        | ☑                                     |
| Scoresheet:DWZ                           | ☑                                     |
| Scoresheet:Ergebnis mit SW-Markierung    | ☑                                     |
| Scoresheet:We (Erwartungswert)           | ☑                                     |
| Scoresheet:Summe Erwartungswert          | ☑                                     |

## Turnierauswertung

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Kopf:Turniername                         | ☑                                     |
| Kopf:Turniercode                         | ☑                                     |
| Kopf:Auswerter (Kontaktdaten)            | ☑ (nur Name und E-Mail)               |
| Kopf:Turnierende                         | ☑                                     |
| Kopf:Datum Erstberechnung                | ❌ (keine Daten von nu)                |
| Kopf:Datum Letzte Berechnung             | ☑                                     |
| Kopf:Anzahl Spieler                      | ☑                                     |
| Kopf:Anzahl Runden                       | ☑ (falscher Wert von nu)              |
| Kopf:Anzahl Partien                      | ☑                                     |
| Kopf:Link Turnierergebnisse              | ☑                                     |
| Spieler:Nr.                              | ☑                                     |
| Spieler:Name (Karteikarte verlinkt)      | ☑                                     |
| Spieler:Link Scoresheet                  | ☑                                     |
| Spieler:ZPS (Link Verein)                | ☑                                     |
| Spieler:DWZ alt                          | ☑                                     |
| Spieler:Ergebnis (Punkte/Partien)        | ☑                                     |
| Spieler:We (Erwartungswert)              | ☑                                     |
| Spieler:E(ntwicklungskoeffizient)        | ☑                                     |
| Spieler:Leistung                         | ☑                                     |
| Spieler:Niveau (Gegner)                  | ☑                                     |
| Spieler:DWZ neu                          | ☑                                     |
| Spieler:DWZ ±                            | ☑                                     |

## Turnierergebnisse

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Kopf:Turniername                         | ☑                                     |
| Kopf:Turniercode                         | ☑                                     |
| Kopf:Auswerter (Kontaktdaten)            | ❌ (keine Daten von nu)                |
| Kopf:Turnierende                         | ☑                                     |
| Kopf:Datum Erstberechnung                | ❌ (keine Daten von nu)                |
| Kopf:Datum Letzte Berechnung             | ☑                                     |
| Kopf:Anzahl Spieler                      | ☑                                     |
| Kopf:Anzahl Runden                       | ☑ (falscher Wert von nu)              |
| Kopf:Anzahl Partien                      | ☑                                     |
| Kopf:Link Turnierauswertung              | ☑                                     |
| Spieler:Nr.                              | ☑                                     |
| Spieler:Name (Karteikarte verlinkt)      | ☑                                     |
| Spieler:Link Scoresheet                  | ☑                                     |
| Spieler:Ergebnis (Punkte/Partien)        | ☑                                     |
| Spieler:Rundenspalten mit Erg./Gegner    | ☑                                     |

## Turniersuche

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Suchformular:Suchbegriff (Turniername)   | ☑                                     |
| Suchformular:Landesverband               | ☑                                     |
| Suchformular:Von-Monat                   | ☑                                     |
| Suchformular:Von-Jahr                    | ☑                                     |
| Suchformular:Bis-Monat                   | ☑                                     |
| Suchformular:Bis-Jahr                    | ☑                                     |
| Suchformular:Letzte x Monate             | ☑                                     |
| Suchformular:Landesverband               | ☑                                     |
| Suchformular:Turniername                 | ☑                                     |
| Suchformular:Landesverband               | ☑                                     |
| Ergebniskopf:Suchbegriff                 | ☑                                     |
| Ergebniskopf:Verband                     | ☑                                     |
| Ergebniskopf:Von Monat/Jahr              | ☑                                     |
| Ergebniskopf:Bis Monat/Jahr              | ☑                                     |
| Ergebniskopf:Anzahl Treffer              | ☑                                     |
| Trefferliste:Turniercode                 | ☑                                     |
| Trefferliste:Turniername (verlinkt)      | ☑                                     |
| Trefferliste:Region (VKZ 3 Stellen)      | ☑ (gab es nicht in DeWIS, jetzt nötig)|
| Trefferliste:Turnierende                 | ☑                                     |
| Trefferliste:Spieler                     | ❌ (keine Daten von nu)                |
| Trefferliste:Auswerter                   | ❌ (nur bei einigen Turnieren vorh.)   |

## Verbandssuche/Verbandsliste

| Funktion                                 | Umgesetzt?                             |
|------------------------------------------|----------------------------------------|
| Suchformular:Rangliste (Top-x)           | ☑                                     |
| Suchformular:Geschlecht                  | ☑                                     |
| Suchformular:Nur Deutsche                | ☑                                     |
| Suchformular:Alter von                   | ☑                                     |
| Suchformular:Alter bis                   | ☑                                     |
| Ergebniskopf:Verband                     | ☑ (separate Abfrage nötig)            |
| Ergebniskopf:Auswerter                   | ❌ (keine Daten von nu)                |
| Ergebniskopf:Top-x                       | ☑                                     |
| Ergebniskopf:Alter von/bis               | ☑                                     |
| Ergebniskopf:Alle/nur Deutsche           | ☑                                     |
| Trefferliste:Platz                       | ☑                                     |
| Trefferliste:Spielername (verlinkt)      | ☑                                     |
| Trefferliste:Letzte Auswertung           | ☑                                     |
| Trefferliste:DWZ mit Index               | ☑                                     |
| Trefferliste:Elo                         | ☑ (keine Daten von nu)                |
| Trefferliste:FIDE-Titel                  | ☑ (keine Daten von nu)                |
| Trefferliste:FIDE-Nation                 | ☑ (keine Daten von nu)                |
| Trefferliste:Status                      | ☑ (unklare Daten von nu)              |
| Trefferliste:Verein                      | ☑ (unklare Daten von nu)              |
| Verbandszugehörigkeit                    | ❌ (unvollständige Daten von nu)       |

### Unklare Daten von nu

Es werden von nu alle Mitgliedschaften geliefert, nicht nur die des angeforderten Verbandes.

### Unvollständige Daten von nu

Von den Verbänden auf oberster Ebene sind nur 1, 5, F, L und M da. Ursache lt. Andreas Filmann: Verbände sind in nu nicht möglich. Es muß alles als Verein angelegt werden.
Bugfix DSB-Website: Array manuell ergänzen

## Downloads CSV/SQL

Umgesetzt über zwei eigenständige Skripte in `src/Resources/public/`, die unabhängig
voneinander über einen Cronjob des Hosters per Curl-Aufruf laufen (in Tests erfolgreich):

- **Wertungsportal_Download.php**: Lädt alle 20 DWZ-Listen-Zips (LV-0 bis LV-M) vom
  nu-Server und legt sie datiert unter `files/wertungsportal/` ab
  (`LV-x-dwzliste_JJJJMMTT_wertungsportal-version.zip`).
- **Wertungsportal_Converter.php**: Lädt LV-0-dwzliste.zip (Gesamtdeutschland), entpackt
  spieler/vereine/verbaende.csv, reichert die Spieler mit FIDE-Daten aus tl_dwz_elo an
  (Elo, Titel, Land werden ersetzt; Abweichungen bei Name/Geschlecht/Geburtsjahr werden
  nur geloggt), packt daraus je Verband neue CSV-Zips ins Jahresarchiv
  (`files/wertungsportal/{Jahr}/lv{x}/`), kopiert die aktuellen Fassungen nach
  `files/wertungsportal/export/csv/LV-x-csv.zip` und trägt sie in die Dateiverwaltung
  (Dbafs) ein.

Eine SQL-Variante ist in den Skripten nicht enthalten (nur CSV).

Beide Skripte sind per Token geschützt: Der Cron-Aufruf braucht `?key=SCHLÜSSEL`,
der Schlüssel wird in den Contao-Einstellungen (Bereich Wertungsportal, Feld Cron-Token)
gepflegt. Ohne konfigurierten Token antworten die Skripte mit 403.

## Besonderheiten

Für Spieler werden unterschiedliche UUID geführt: Sie unterscheiden sich bei Spielerdatensätzen und in Turnieren.
Einmal gibt es eine {nuLigaPersonId} und in Turnieren eine {playerUuid}.

## Import-Regeln Vereine__Vereinsmitglieder__JJJJMMTTHHIISS (festgelegt 20.07.2026)

1. **tstamp aus Dateinamen**: Datum und Uhrzeit aus dem Dateinamen (JJJJMMTTHHIISS)
   werden als tstamp aller in diesem Lauf angelegten/aktualisierten Datensätze gesetzt
   (Personen und Mitgliedschaften). Enthält der Dateiname kein gültiges Datum,
   wird die aktuelle Zeit verwendet.
2. **Importpriorität**: Die Importdaten haben höhere Priorität und überschreiben
   abweichende Bestandsdaten IMMER — auch wenn der vorhandene tstamp jünger ist
   als das Dateidatum. Es findet kein tstamp-Vergleich statt.
3. Unverändert gelten weiterhin: Schlüssel InterneNr (Personen) bzw.
   VKZ+Mitgliedsnummer (Mitgliedschaften), Schreiben nur bei tatsächlichen
   Änderungen, published nur beim Anlegen, keine Löschungen.

## Erledigt am 20.07.2026 (Upload + Livetest offen)

* ☑ Diagramm DWZ und Leistung in Spielerkarteikarte (serverseitiges SVG im
  Karteikarte-Helper; toter Chart.js-Verweis auf das alte contaodewis-Bundle
  aus dem Template entfernt)
* ☑ Verbandszugehörigkeiten in Spielerkarteikarte (Kette DSB → … → Verband je
  Mitgliedschaft über tl_wertungsportal_clubs / Helper::getVerbandskette;
  nur so vollständig, wie die synchronisierten Vereins-/Verbandsdaten es hergeben)
* ☑ Turnierergebnisse: Rundenzahl wird aus den Matches gelesen (max. Rundennummer,
  Fallback Metadaten wenn keine Partien)
* ☑ Turnierergebnisse: Mehrere Ergebnisse je Runde (Append statt Überschreiben,
  Template rendert alle Einträge einer Rundenzelle)
* ☑ Personen: Blacklist-Felder blocked/grund/melder analog tl_dwz_spi
  (Vorhandene Blacklist-Einträge manuell übernehmen — Aufgabe User!)
* ☑ Blacklist-Personen werden in keiner Ausgabe mehr angezeigt:
  Spielersuche/Vereinsliste/Verbandsrangliste lassen die Zeile weg; Karteikarte
  und eigener Spielberichtsbogen zeigen eine neutrale Meldung; in
  Turnierauswertung/Turnierergebnissen/Scoresheet-Gegnerliste bleibt die Zeile
  wegen der Querbezüge erhalten, aber ohne Personenbezug (Name „gesperrt",
  keine Links, keine Vereins-/FIDE-Daten)
* ☑ WP | Vereine: Felder altname/Logo/Homepage/Info + globale Operation
  „Altdaten übernehmen" (key=importDwzVer, Match per VKZ, füllt nur leere
  Zielfelder — einmal ausführen!); Frontend liest neue Tabelle mit Fallback tl_dwz_ver
* ☑ WP | Personen: Bildfelder + globale Operation „Bilder übernehmen"
  (key=importPhotos, Match externeNr = dewisID, nur Personen ohne eigenes Bild —
  einmal ausführen, sobald tl_dwz_spi Echtdaten hat); Karteikarte nutzt das
  Personenbild mit Fallback tl_dwz_spi über die externeNr (der alte
  dewisID=nu-ID-Match konnte nie treffen)
* ☑ Import-Regeln umgesetzt (siehe Abschnitt oben)

Deployment-Hinweise: Neue DB-Felder (persons: addImage/singleSRC/blocked/grund/melder;
clubs: altname/addImage/singleSRC/info/homepage) → nach dem Upload contao:migrate
bzw. Install-Tool ausführen.

## Erledigt am 20.07.2026, Runde 2 (Upload + Livetest offen)

* ☑ Diagramm: Leistungslücken werden geschätzt (Turniere unter 5 Partien:
  Gegnerschnitt + 800 × Score-Anteil − 400; verbleibende Lücken linear
  interpoliert) — die Leistungskurve läuft ohne Unterbrechungen durch,
  geschätzte Werte als hohle Punkte mit „(geschätzt)" im Tooltip
* ☑ Diagramm: Jahreszahlen um 45° gedreht (keine Überschneidungen mehr)
* ☑ Diagramm: Bei mehr als 50 Turnieren zeigt die Karteikarte nur die letzten 50;
  das Komplettdiagramm (breiter, horizontal scrollbar) öffnet sich über den Link
  „Diagramm mit allen Turnieren anzeigen" in einem Overlay (eigene Lightbox,
  CSS in default.css → contao:assets:install!)
* ☑ Neues Backend-Modul WP | FIDE-Elo (tl_wertungsportal_elo) mit den Feldern
  von tl_dwz_elo (plus Eingabemasken; bewusst weniger Indizes — nur fideid/
  surname/published, die Anreicherung fragt nur per fideid ab)
* ☑ Globale Operation „XML-Import" (key=importElo, Classes/EloImport.php):
  FIDE-Ratingliste als XML oder Zip (wird serverseitig entpackt), Chunk-Upload
  in 2-MB-Blöcken + schrittweiser Import per Byte-Offset (Puffer-Parser,
  2000 Spieler je Schritt). Überschreiben = Upsert per fideid (Schreiben nur
  bei Änderungen, keine Löschung); elodate = Startzeit des Importlaufs,
  wandert aber nur bei tatsächlich geänderten Datensätzen mit (sonst würde
  jeder Folgelauf alle 1,9 Mio. Zeilen schreiben).
  Mit der ECHTEN Datei players_list_xml.zip lokal getestet (20.07.2026):
  Zip erkannt, Eintrag players_list_xml_foa.xml (835 MB) in 1,5 s entpackt,
  alle 1.887.095 Spieler in 945 Schritten fehlerfrei geparst (0 übersprungen,
  0 doppelte FIDE-IDs, keine Feldkürzungen, Ø 0,03 s Parsing je Schritt).
  Empfehlung: das ZIP direkt hochladen (~50 MB statt 835 MB Upload) —
  Anwender muss NICHT vorher entpacken. Der Server braucht temporär
  ~900 MB Platz in system/tmp (Zip + entpackte XML).
* ☑ FIDE-Anreicherung liest jetzt tl_wertungsportal_elo statt tl_dwz_elo
  (Helper::getFIDEDatenLokal/getFIDEDatenListe, API::getFIDE,
  Wertungsportal_Converter.php)
* ☑ Alle tl_dwz-DCA- und Sprachdateien entfernt + DeWIS-Backend-Module
  (dwz-spieler/-vereine/-turniere/-bearbeiter) aus config.php — neue Strategie:
  Das contao-dewis-bundle läuft einige Tage parallel und verwaltet die
  tl_dwz_*-Tabellen selbst. Die DwzSpi-/DwzVer-Models bleiben für die
  Altdaten-Übernahmen und Foto-/Logo-Fallbacks registriert.
  Tote Helper::Blacklist() (las tl_dwz_spi) entfernt.
* ☑ Die gewünschten Kopier-Operationen für Spielerbild (WP | Personen →
  „Bilder übernehmen") und Vereinsdaten (WP | Vereine → „Altdaten übernehmen")
  existieren bereits seit Runde 1 (Classes/AltdatenImport.php)

**ACHTUNG Server-Cleanup:** Das manuelle Ordner-Kopieren entfernt keine Dateien —
diese 17 gelöschten Dateien müssen auf dem Server separat entfernt werden:
`src/Resources/contao/dca/`: tl_dwz_bea.php, tl_dwz_elo.php, tl_dwz_fid.php,
tl_dwz_inf.php, tl_dwz_kar.php, tl_dwz_spi.php, tl_dwz_spiver.php, tl_dwz_tur.php,
tl_dwz_ver.php; `src/Resources/contao/languages/de/`: tl_dwz_bea.php, tl_dwz_fid.php,
tl_dwz_inf.php, tl_dwz_kar.php, tl_dwz_spi.php, tl_dwz_spiver.php, tl_dwz_tur.php,
tl_dwz_ver.php

Deployment Runde 2: contao:migrate (neue Tabelle tl_wertungsportal_elo) und
contao:assets:install (default.css) nötig.

## Livetest 20.07.2026 (Chrome, Dev-System, nach Migration durch den User)

Bestanden: WP | FIDE-Elo (Liste, Suchpanel, Edit-Maske); XML-Import über die
Endpunkte (3 Läufe: Neuanlage + Skip ohne FIDE-ID / Idempotenz 0 Writes /
gezielte Änderung = genau 1 Update); FIDE-Anreicherung end-to-end (Keymer
Elo 2750/GM in der Spielersuche aus tl_wertungsportal_elo); Diagramm auf der
Karteikarte (letzte 50, lückenlose Leistungskurve mit 17 hohlen Schätzpunkten,
45°-Jahreslabels, Overlay öffnet/schließt, CSS/Assets geladen);
Verbandszugehörigkeiten-Zeilen je Verein; Turnierergebnisse Fordopen 7 Runden
und Bundesliga 15 Runden (aus Matches); Blacklist end-to-end (Suche filtert,
Karteikarte „nicht verfügbar", Kreuztabelle+Auswertung ohne jeden Namensbezug,
wieder entsperrt); Personen-Import-Endpunkt mit Dateinamen-Parameter;
Vereinsliste (441 Mitglieder) und Scoresheet unverändert korrekt.
Testdaten wieder entfernt; der Keymer-Elo-Datensatz (echte Werte) blieb drin.

Hinweise aus dem Test:
* Verbandsketten zeigen aktuell meist nur den DSB — Zwischenebenen erscheinen
  erst, wenn die lokale Vereinstabelle die Verbände (federation-Kette) kennt.
* Cachesystem gezielt getestet (20.07.2026): Im Dev-System ist
  wertungsportal_cache AUS (jede Seite holt frisch von der nu-API).
  Für den Test kurz aktiviert: Cache-Treffer liefern die beim Cache-Schreiben
  EINGEBACKENE FIDE-Anreicherung (Suche zeigte weiter Elo 2750, obwohl die
  Tabelle schon 2777 hatte) — deshalb gilt in Produktion (Cache an): nach
  einem FIDE-Import den Wertungsportal-Cache leeren. Blacklist-Filterung
  greift dagegen auch bei Cache-Treffern sofort (Filterung zur Renderzeit).
  Einstellung danach wieder deaktiviert, Elo zurückgesetzt.
  Achtung fürs Backend-Skripting: input[name="wertungsportal_cache"] matcht
  zuerst ein Hidden-Feld — die echte Checkbox ist #opt_wertungsportal_cache_0.

## Offene Aufgaben

* Echten FIDE-Import einmal über die Import-Seite ausführen (players_list_xml.zip,
  ~50 MB Upload; danach Cache leeren)
* Vereinsliste: Verbandszugehörigkeiten (bisher nur in der Karteikarte umgesetzt)
* Blacklist: vorhandene Einträge aus tl_dwz_spi manuell in WP | Personen übernehmen
* Übernahme-Operationen einmalig ausführen (WP | Vereine → „Altdaten übernehmen";
  WP | Personen → „Bilder übernehmen", sobald tl_dwz_spi Echtdaten hat)
* FIDE-Elo-Import einmalig mit der echten FIDE-XML ausführen (WP | FIDE-Elo →
  „XML-Import"), sonst bleibt die FIDE-Anreicherung leer (neue Quelle!)
* Karteikarten-Template (wertungsportal_spieler.html5) lädt tablesorter noch aus
  bundles/contaodewis — vor der endgültigen Abschaltung des dewis-Bundles die
  Assets in dieses Bundle übernehmen
