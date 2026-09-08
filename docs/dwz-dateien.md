# DWZ-Dateien herunterladen und aufbereiten

Zwei Konsolenbefehle holen die DWZ-Listen vom nu-Server und bauen daraus die
Verbands-Archive:

| Befehl | Was er tut |
| --- | --- |
| `wertungsportal:download` | Lädt die zwanzig Landesverbands-Zips und legt sie datiert unter `files/wertungsportal/downloads/` ab |
| `wertungsportal:converter` | Lädt die Deutschland-Datei, reichert `spieler.csv` mit den FIDE-Daten an, packt je Landesverband ein CSV-Zip ins Jahresarchiv und pflegt die Dbafs |

Beide sind voneinander unabhängig: Der Converter holt sich seine Datei selbst.

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

Der Converter legt jedes Verbandsarchiv **zweimal** ab — einmal in der
Kodierung, die nu liefert (windows-1252), und einmal in der DOS-Codepage 850:

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

**Inhalt und Spaltenaufbau sind identisch.** Der einzige Unterschied ist der
Zeichensatz: Ältere Schachprogramme unter DOS lesen die Dateien direkt ein und
erwarten dort die Codepage 850. In der Kodierung der nu-Dateien stünde bei
ihnen statt „Müller" ein „MĂźller". Auch die Dateinamen im Archiv bleiben
gleich (`spieler.csv`, `vereine.csv`, `verbaende.csv`, `README.txt`); sie sind
ohnehin schon 8.3-tauglich.

Umgewandelt wird mit `iconv` und dem Zusatz `//TRANSLIT`: Für ein Zeichen, das
die Codepage 850 nicht kennt, schreibt es eine lesbare Entsprechung statt eines
Fragezeichens.

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
