# DWZ-Dateien herunterladen und aufbereiten

Zwei Konsolenbefehle holen die DWZ-Listen vom nu-Server und bauen daraus die
Verbands-Archive:

| Befehl | Was er tut |
| --- | --- |
| `wertungsportal:download` | Lädt die zwanzig Landesverbands-Zips und legt sie datiert unter `files/wertungsportal/downloads/` ab |
| `wertungsportal:converter` | Lädt die Deutschland-Datei, reichert `spieler.csv` mit den FIDE-Daten an, packt je Landesverband ein CSV-Zip ins Jahresarchiv und pflegt die Dbafs |

Beide sind voneinander unabhängig: Der Converter holt sich seine Datei selbst.

**Anmeldung (ab 1.46.0):** Die Zip-Dateien gehören zur DWZ-Liste, die nu per
OAuth2 schützt. Beide Befehle laden deshalb mit dem Token der DWZ-Liste; die
Zugangsdaten stehen unter Wertungsportal → Einstellungen → „Zugang zur
DWZ-Liste". Fehlen sie, laden die Befehle wie bisher ohne Anmeldung — das
klappt, solange nu die Liste frei ausliefert. Verweigert nu eine Datei
(HTTP 401), steht der Grund in der Ausgabe, und der Befehl gibt 1 zurück.
Einzelheiten: [Zugang zur Schnittstelle](zugang.md).

## Der Cronjob muß umgestellt werden

> **Beim Deploy zu erledigen.** Bis 1.35.2 rief der Hoster die beiden Skripte
> über eine URL auf. **Die Skripte gibt es nicht mehr.** Bleibt der alte
> Cron-Eintrag stehen, holt er ab sofort nur noch eine 404-Seite — und niemand
> merkt es, weil ein Cronjob keine Fehlerseite anzeigt.

Bisher:

```
curl "https://www.schachbund.de/bundles/contaowertungsportal/Wertungsportal_Download.php?key=SCHLÜSSEL"
curl "https://www.schachbund.de/bundles/contaowertungsportal/Wertungsportal_Converter.php?key=SCHLÜSSEL"
```

Künftig:

```bash
vendor/bin/contao-console wertungsportal:download
```

```bash
vendor/bin/contao-console wertungsportal:converter
```

Beim Hoster wird dem üblicherweise der PHP-Aufruf vorangestellt, etwa
`php84 /pfad/zur/installation/vendor/bin/contao-console wertungsportal:download`.

**Der Zugangsschlüssel entfällt ersatzlos.** Die Einstellung „Cron-Token" ist
aus den Wertungsportal-Einstellungen verschwunden: Sie hat den öffentlichen
Aufruf der Skripte abgesichert, und den gibt es nicht mehr. Ein Konsolenbefehl
ist von außen nicht erreichbar.

## Warum die Umstellung nötig war

Die beiden Skripte lagen unter `src/Resources/public/` und banden
`system/initialize.php` ein. Diesen Weg gibt es in Contao 5 nicht mehr —
mit ihm wäre das ganze Bundle dort nicht lauffähig gewesen.

Nebenbei fielen zwei stille Fehler weg:

* Die Skripte ermittelten das Wurzelverzeichnis mit
  `substr($_SERVER['DOCUMENT_ROOT'], 0, -3)` — sie schnitten also die drei
  Zeichen von „web" ab. In Contao 5 heißt der Ordner „public", das sind sechs
  Zeichen; die Pfade hätten dort ins Leere gezeigt.
* Auf der Kommandozeile gibt es gar keinen `DOCUMENT_ROOT`. Der Weg über den
  Container (`Helper::projektpfad()`) funktioniert in beiden Fassungen und in
  beiden Betriebsarten.

Die Arbeitsweise selbst ist unverändert geblieben: Die Klassen
`Classes\Downloader` und `Classes\Converter` sind die früheren Skriptklassen,
Zeile für Zeile übernommen. Nur der Einstieg ist neu.

## Rückgabewerte

Beide Befehle geben 0 zurück, wenn alles durchgelaufen ist, und 1, wenn etwas
schiefging — beim Download also, wenn mindestens eine der zwanzig Dateien
fehlt, beim Converter, wenn der Download oder das Entpacken scheiterte.

Ein Cronjob kann daran erkennen, ob er sich melden muß. Vorher war das nicht
möglich: Der Curl-Aufruf lieferte HTTP 200 auch dann, wenn im Text „FEHLER"
stand.

## Fortschritt mitlesen

Beide Befehle geben ihren Fortschritt zeilenweise aus, solange sie laufen:

```
Lade LV-0-dwzliste.zip
Lade LV-1-dwzliste.zip
…
Fertig
```

Bei einem Lauf über zwanzig Dateien ist damit zu sehen, wo er steht.

## Zwei Fassungen je Verband: CSV und DOS

Der Converter legt jedes Verbandsarchiv **zweimal** ab — einmal so, wie nu es
liefert (CSV, windows-1252), und einmal im **alten Format des DeWIS-Servers**:

```
files/wertungsportal/downloads/<Jahr>/
├── csv/LV-0-csv_JJJJMMTT.zip     DSB, CSV
├── dos/LV-0-dos_JJJJMMTT.zip     DSB, DOS
├── lv3/LV-3-csv_JJJJMMTT.zip     Berlin, CSV
└── lv3/LV-3-dos_JJJJMMTT.zip     Berlin, DOS
```

Die jeweils aktuellen Fassungen liegen zusätzlich unter
`export/csv/LV-x-csv.zip` und `export/dos/LV-x-dos.zip` — genau die Aufteilung,
die der frühere DeWIS-Server hatte.

### Das DOS-Format

Vorlage ist `LV-0-dos_20240627.zip` vom DeWIS-Server. Einziger inhaltlicher
Unterschied: Im ersten Feld von `SPIELER.TXT` steht die **nu-ID**
(`NU4005017`) statt der MIVIS/DeWIS-Kennung. Das Archiv enthält vier Dateien:

| Datei | Inhalt |
| --- | --- |
| `SPIELER.TXT` | eine Zeile je Mitgliedschaft, absteigend nach DWZ |
| `VEREINE.TXT` | eine Zeile je Verein |
| `VERBAENDE.TXT` | eine Zeile je Verband |
| `README.TXT` | Verband, Stand, Zahlen und Beschreibung der Felder |

Für alle gilt: **keine Kopfzeile**, Felder durch `|` getrennt, keine
Anführungszeichen, Zeilenende CRLF, Zeichensatz **DOS-Codepage 850**. Die
Reihenfolge der Zeilen ist die der nu-Dateien.

`SPIELER.TXT` hat 14 Felder, zum Beispiel:

```
NU4005017|C0505|1043|A|Muster,Max|M||1963|202611|1802-45|1850|FM|4711|GER
```

| Nr. | Feld | Spalte der spieler.csv |
| --- | --- | --- |
| 1 | nu-ID | `ID` |
| 2 | ZPS-Nummer des Vereins | `ZPS` |
| 3 | Mitgliedsnummer im Verein | `Mitgliedsnummer` |
| 4 | Status (A/P) | `Status` |
| 5 | Name,Vorname | `Name,Vorname` |
| 6 | Geschlecht (M/W) | `Geschlecht` |
| 7 | Spielberechtigung | `Spielberechtigung` — von nu derzeit leer |
| 8 | Geburtsjahr | `Geburtsjahr` |
| 9 | Woche der letzten Auswertung (JJJJWW) | `Letzte Auswertung` |
| 10 | DWZ-Index, ohne DWZ **`0-0`** | `DWZ` und `Index` |
| 11 | FIDE-Elozahl | `FIDE-Elozahl` |
| 12 | FIDE-Titel | `FIDE-Titel` |
| 13 | FIDE-ID | `FIDE-ID` |
| 14 | FIDE-Land | `FIDE-Land` |

`VEREINE.TXT` und `VERBAENDE.TXT` haben je vier Felder: Kennziffer bzw.
Verbandnummer, Landesverband, übergeordneter Verband, Name.

* **VEREINE.TXT führt nur Vereine.** nu listet in der vereine.csv auch die
  Verbände mit einer Kennziffer auf „00" (10000 „Badischer Schachverband
  e.V."); in der Vorlage fehlen sie, hier auch. Keine Mitgliedschaft verweist
  auf eine davon. L0001 und M0001 (Blinden- und Problemschach) bleiben
  stehen — dort sind Mitglieder gemeldet.
* **VERBAENDE.TXT** übernimmt die verbaende.csv unverändert, auch die Wurzel
  `000` und die mehrfach vergebenen Nummern der württembergischen Bezirke und
  Kreise (`C01` bis `C06`).
* **README.TXT** zählt wie die Vorlage die Zeilen von `SPIELER.TXT` und
  `VEREINE.TXT` („96081 Spieler in 2246 Vereinen" bei 96.081 Zeilen).

Die Spalten der CSV werden **über ihre Überschriften** zugeordnet. Fehlt eine,
entsteht für diesen Verband kein DOS-Archiv, und der Lauf meldet die fehlende
Spalte — ein Archiv mit verrutschten Feldern wäre schlimmer.

**Zeichensatz:** Zeichen, die CP850 nicht kennt (`š`, `Š`, `ž`, `’`, `„` …),
werden vor der Wandlung zu ASCII (`s`, `S`, `z`, `'`, `"`). Das ist dasselbe,
was `iconv` mit `//TRANSLIT` auf dem Server geschrieben hat, hängt aber nicht
mehr von dessen Einrichtung ab.

**Bis 1.43.3** war die DOS-Fassung eine Kopie der CSV-Dateien, nur in CP850
gewandelt — mit Kopfzeile, Kommas, Anführungszeichen und 17 Spalten. So hatte
Frank es in 1.37.0 entschieden und am 15.09.2026 zurückgenommen.

Umgesetzt ist das Format in `Classes/DosFormat.php`, geprüft in
`tests/Classes/DosFormatTest.php`.

## Was der Converter voraussetzt

Der Converter ersetzt in `spieler.csv` Elo, Titel und Land durch die Werte aus
`tl_wertungsportal_elo`. **Ist diese Tabelle leer, weil der FIDE-Elo-XML-Import
noch nie gelaufen ist, bleiben die Spalten so, wie nu sie geliefert hat.** Der
Import läuft im Backend unter *WP | FIDE-Elo → XML-Import*.

Abweichungen bei Name, Geschlecht und Geburtsjahr werden nur protokolliert,
nicht überschrieben.

## Vorsicht bei Änderungen

Die Dateien des nu-Servers sind **windows-1252-kodiert**. `writeReadme()`
arbeitet deshalb bewußt byte-basiert ohne Encoding-Umwandlung, und die
CRLF-Zeilenenden bleiben erhalten. Wer dort mit `mb_*`-Funktionen „aufräumt",
zerlegt die Umlaute in den Archiven.
