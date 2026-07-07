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
| FIDE-Elo                                 | ☑                                     |
| FIDE-ID (verlinkt)                       | ☑                                     |
| FIDE-Titel                               | ☑                                     |
| FIDE-Nation (verlinkt)                   | ?  Verlinkung fehlt noch               |
| Historie (Verlinkung zu EloBase)         | ❌ (Integration geplant)               |
| Zuständiger Wertungsreferent (Kontakt)   | ❌ (keine Daten von nu)                |
| Spielerfoto                              | ☑ (Zuordnung MIVIS-ID fehlt!)         |
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
| Diagramm DWZ und Leistung                | ❌ (vorerst nicht geplant)             |
| Ranglistenplatzierungen                  | ❌ (keine Daten von nu)                |
| Verbandszugehörigkeiten                  | ❌ (geplant)                           |

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
| Spielerliste:Elo                         | ☑                                     |
| Spielerliste:FIDE-Titel                  | ☑                                     |
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
| Ergebniskopf:Auswerter                   |x ☑                                     |
| Ergebniskopf:Top-x                       | ☑                                     |
| Ergebniskopf:Alter von/bis               |x ☑                                     |
| Ergebniskopf:Alle/nur Deutsche           |x ☑                                     |
| Trefferliste:Platz                       | ☑                                     |
| Trefferliste:Spielername (verlinkt)      | ☑                                     |
| Trefferliste:Letzte Auswertung           | ☑                                     |
| Trefferliste:DWZ mit Index               | ☑                                     |
| Trefferliste:Elo                         |x ☑                                     |
| Trefferliste:FIDE-Titel                  |x ☑                                     |
| Trefferliste:FIDE-Nation                 |x ☑                                     |
| Trefferliste:Status                      |x ☑                                     |
| Trefferliste:Verein                      |x ☑                                     |
| Verbandszugehörigkeit                    |x ☑                                     |

## Downloads CSV/SQL

Noch nicht implementiert.

## Besonderheiten

Für Spieler werden unterschiedliche UUID geführt: Sie unterscheiden sich bei Spielerdatensätzen und in Turnieren.
Einmal gibt es eine {nuLigaPersonId} und in Turnieren eine {playerUuid}.
