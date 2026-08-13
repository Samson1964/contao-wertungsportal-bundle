# Unmögliche Werte der Schnittstelle protokollieren

Manche Zahlen, die nu liefert, kann es nach dem Regelwerk nicht geben. Sie
werden trotzdem übernommen — der Betrieb soll nicht an einem einzelnen
Datensatz hängenbleiben —, aber **mit Spieler und Turnier festgehalten**, damit
sich daraus eine Fehlermeldung an nu schreiben läßt.

## Der Anlass

Am 13.08.2026 brach der nächtliche Vorlader ab:

```
SQLSTATE[22003]: Numeric value out of range: 1264
Out of range value for column 'tournamentPerformance' at row 10
```

nu hatte eine **negative Turnierleistung** geliefert. Nach der Wertungsordnung
des DSB kann die Leistung nicht unter null fallen — der Wert ist also ein
Fehler auf der Gegenseite. Übrig blieb aber nur eine SQL-Meldung ohne Namen:
Welcher Spieler, welches Turnier, wie kam die Zahl zustande? Nichts davon war
zu erkennen, und damit war der Befund auch nicht meldbar.

## Was geprüft wird

Alle Ganzzahlfelder der Spieler-Datensätze, die **nicht negativ sein können**:

| Feld | Bedeutung |
|---|---|
| `tournamentPerformance` | Turnierleistung |
| `ratingOld` / `ratingNew` | DWZ vor und nach dem Turnier |
| `indexOld` / `indexNew` | Index vor und nach dem Turnier |
| `averageRatingCompetitors` | Gegnerschnitt |
| `numberOfGames` | Zahl der Partien |
| `birthyear` | Geburtsjahr |

Die Liste steht als `Helper\Auffaellig::FELDER`; ein weiteres Feld ist eine
Zeile.

Geprüft wird an den zwei Stellen, an denen Spieler-Datensätze ankommen: beim
Abgleich der **Turnierauswertung** und beim Abgleich der **Turnierhistorie**.
Beide Wege führen bei einer Turnierauswertung durch denselben Datensatz —
doppelt aufgeschrieben wird er nicht.

## Wo es landet

Eine Datei je Monat unter `var/logs`:

```
var/logs/wertungsportal-auffaellig-2026-08.log
```

Semikolongetrennt, mit Kopfzeile — läßt sich also unverändert in eine Tabelle
laden und an nu weiterreichen:

| Spalte | Inhalt |
|---|---|
| Zeitpunkt | wann es auffiel |
| Quelle | Turnierauswertung oder Turnierhistorie |
| Turnier-UUID | das Turnier bei nu |
| Person | nu-Nummer, z. B. `NU4093214` |
| Name | Nachname, Vorname |
| VKZ | Vereinskennziffer |
| Feld | deutscher Name und Feldname der Schnittstelle |
| Wert | die beanstandete Zahl |
| Partien, Gegnerschnitt, Punkte | woraus sich die Leistung errechnet |
| DWZ alt, DWZ neu | zur Einordnung |

Die letzten fünf Spalten sind kein Beiwerk: **Ohne sie kann die Gegenseite den
Fall nicht nachrechnen** und müßte die Daten selbst zusammensuchen.

Warum eine eigene Datei und nicht das Systemprotokoll? Dort ginge der Befund
zwischen den Cron-Zeilen unter, und zum Weiterreichen taugt er so nicht. Damit
die Datei trotzdem nicht unbemerkt liegenbleibt, geht **eine Zusammenfassung**
ins Systemprotokoll:

```
Wertungsportal: 3 unmögliche Werte von der Schnittstelle erhalten (negative
Zahlen, wo es keine geben kann). Einzelheiten samt Spieler und Turnier in
wertungsportal-auffaellig-2026-08.log — geeignet als Fehlermeldung an nu.
```

Findet sich nichts, wird auch nichts geschrieben — weder Datei noch
Protokollzeile.

## Was mit dem Wert passiert

Er wird **übernommen**. Die Spalte `tournamentPerformance` ist seit Fassung
1.26.2 vorzeichenbehaftet (`int(11)`), damit ein solcher Wert den Abgleich
nicht mehr zum Stehen bringt. Richtig ist er deswegen nicht — deshalb die
Aufzeichnung.

Im Frontend erscheint er, wie er kommt. Ihn stillschweigend auf 0 zu setzen
wäre schlechter: Dann wäre die Ausgabe falsch, ohne dass es jemandem auffiele.

## Grenzen

* Erfaßt werden nur Datensätze, die durch einen **Abgleich** laufen. Der
  Spielberichtsbogen wird nur angezeigt und nicht gespiegelt — dort steht der
  Wert also in der Ausgabe, aber nicht in der Datei.
* Geprüft wird auf **negativ**, nicht auf „unplausibel hoch". Wo die Grenze
  nach oben liegt, gibt das Regelwerk nicht her.
* Die Datei wächst unbegrenzt. Bei den erwarteten Mengen ist das kein Thema;
  sollte sie doch groß werden, ist das selbst schon der Befund.
