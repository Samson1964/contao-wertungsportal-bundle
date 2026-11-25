# DWZ-Archiv-Suche (ab 1.61.0)

Frontend-Modul **„DWZ-Archiv-Suche“** (Typ `wertungsportal_dwzarchiv`, Gruppe „Wertungsportal“).
Es ersetzt die Seiten mit Tausenden Download-Links in Akkordeons
(`dwz-archiv-downloads-dsb.html`, `…-lv-<slug>.html`). Der Besucher wählt Datum, Verband und
Format. Angezeigt werden die Dateien zu genau diesem Tag. Gibt es keine, kommen die nächsten
davor und danach, jeweils mit dem Abstand in Tagen.

Das Modul liest die Ordner direkt. Neue Dateien und neue Jahresordner erscheinen ohne Pflege, es
braucht keine Download-Elemente mehr. Es läuft unter Contao 4.13 und 5.7 mit demselben Code.

## Einrichten

1. Bundle einspielen, dann `contao:migrate` ausführen: vier neue Spalten in `tl_module`, sonst
   nichts. Außerdem `assets:install` (neue `dwzarchiv.css`) und `cache:clear` (neue Dienste,
   neue Route, neue Twig-Vorlage).
2. Unter *Themes → Module* ein Modul „DWZ-Archiv-Suche“ anlegen:
   - **Verband:** leer lassen („Auswahl anzeigen“), damit der Besucher wählt. Auf den Unterseiten
     der Landesverbände den Verband fest einstellen; dann verschwindet die Auswahl.
   - **Nachbardateien:** so viele Tage davor und danach zeigen (0 bis 10, Vorgabe 0). Bei 1 und
     ohne Datei am gesuchten Tag: genau ein Tag davor und einer danach.
   - **Formate:** welche Formatgruppen angeboten werden. Ohne Auswahl werden alle angeboten.
   - **Neueste Dateien anzeigen:** unter dem Ergebnis die jüngsten Dateien je Format.
3. Einen Hinweistext über dem Formular als Text-Element in den Artikel setzen.
4. Prüfen (siehe unten): `wertungsportal:dwz-archiv`.

Ein Durchlauf der Dateiverwaltung („Synchronisieren“) ist **nicht** nötig. Das Modul liest das
Dateisystem und löst keine Synchronisierung aus.

## Woher die Dateien kommen

Durchsucht werden zwei Basisordner, Vorgabe im Parameter `wertungsportal.dwz_archiv.basisordner`
(`src/Resources/config/services.yml`):

| Quelle | Ordner | Bestand |
| --- | --- | --- |
| `wertungsportal` | `files/wertungsportal/downloads` | ab 24.07.2026, täglich; legen Converter und Swiss-Chess-Befehl an |
| `dewis` | `files/dewis` | DSB 2016 bis 04.07.2026, Landesverbände ab 08.05.2018; abgeschlossen |

Gelesen werden nur die Jahresordner `<JJJJ>/<ordner>/`. `export/`, `raw/` und `tmp/` des Converters
bleiben außen vor. Ein Ordner `lv<code>` gehört zum Landesverband, jeder andere (csv, dos, sql,
swiss, swiss8, swiss10, swiss1003) zum DSB.

Die Ordner lassen sich in der `config/config.yaml` der Installation umstellen:

```yaml
parameters:
    wertungsportal.dwz_archiv.basisordner:
        wertungsportal: files/wertungsportal/downloads
        dewis: files/dewis
```

Die Reihenfolge gilt als Vorrang, falls derselbe Dateiname in beiden Quellen liegt.

### Dateinamen

Jeder Name trägt sein Datum als `JJJJMMTT`. Muster (`Archivindex::MUSTER`):

```
LV-<code>-<csv|dos|sql>[_v2]_JJJJMMTT.zip
dsb-<ws7|ws822|ws833|swiss|swiss10|swiss1003>_JJJJMMTT.zip    (immer Verband 0)
```

Dateien, die nicht passen, werden übergangen und protokolliert. Ebenso übergangen wird eine
Datei, die im Ordner eines anderen Verbands liegt. Sonst stünde sie doppelt in der Ausgabe.

### Verbände

Codes wie im Downloader: `0` = DSB gesamt, `1`–`9` und `A`–`H` die Landesverbände, `L` der
Blindenschachbund (DBSB), `M` die Schwalbe. Die Namen der Landesverbände kommen aus
`API::landesverbaende()`, die übrigen drei aus `DwzArchivSucheController::verbandsnamen()`. Die
Auswahl im Formular zeigt nur Verbände, für die es Ordner gibt.

### Formatgruppen

Festgelegt von Frank am 25.11.2026:

| Gruppe | Dateien |
| --- | --- |
| CSV | `LV-…-csv` (mit `_v2`) |
| DOS | `LV-…-dos` (mit `_v2`) |
| SQL | `LV-…-sql` (mit `_v2`, nur DeWIS) |
| Swiss-Chess bis 7 | `dsb-ws7` (DeWIS) |
| Swiss-Chess 8 und 9 | `dsb-ws822`, `dsb-ws833` (DeWIS), `dsb-swiss` (Wertungsportal, „ältere Fassungen“) |
| Swiss-Chess 10.0 bis 10.02 | `dsb-swiss10` |
| Swiss-Chess ab 10.03 | `dsb-swiss1003` (Namen in windows-1252, siehe `docs/swiss-chess.md`) |

Mehrere Dateien am selben Tag (`ws822` und `ws833`, mit und ohne `_v2`) stehen gemeinsam als
Treffer da.

## Suche

- Genauer Tag vorhanden → diese Datei(en), Abstand „genau“.
- Sonst der nächste Tag davor und der nächste danach, immer beide, auch bei gleichem Abstand.
  Vor dem ersten Bestand fehlt „davor“, nach dem letzten fehlt „danach“.
- Nachbardateien = n Tage je Seite. Beim genauen Treffer kommen sie zum Tag hinzu. Ohne Treffer
  zählt der nächste Tag mit: n Tage davor und n danach, mindestens je einer (bis 1.61.0 kamen dort
  n + 1, weil der nächste Tag nicht mitzählte).
- DeWIS und Wertungsportal stehen in einer Liste. Am 15.07.2026 findet die Suche deshalb DeWIS
  vom 04.07. und Wertungsportal vom 24.07. Die Herkunft steht im Tooltip des Dateinamens (bis
  1.61.0 als eigene Spalte „Quelle“).
- Das Datum wird auf den Bestand des Verbands begrenzt (`min`/`max` des Datumsfelds). Sonst wiese
  der Browser das Formular morgens ab, bevor die Tagesdatei da ist.
- Alle Werte kommen per GET (`?datum=JJJJ-MM-TT&verband=3&format=csv`), Ergebnisse sind
  verlinkbar. Ungültige Werte fallen auf heute, DSB und „alle Formate“ zurück.

## Download

`GET /wertungsportal-api/dwz-archiv/<quelle>/<JJJJ>/<ordner>/<datei>.zip`
(`Controller\DwzArchivDownloadController`, Route `wertungsportal_dwzarchiv_download`).

Contaos `?file=` liefert nur Dateien aus, die in einem Download-Element derselben Seite stehen.
Genau diese Elemente fallen weg. Der eigene Weg hängt außerdem nicht davon ab, ob die Ordner in
der Dateiverwaltung öffentlich sind und ob der Web-Ordner `web/` oder `public/` heißt.

`Archivindex::aufloesen()` läßt nur `<quelle>/<JJJJ>/<ordner>/<datei>` mit bekannter Quelle und
einem Namen nach dem Muster zu. Leere Glieder, `.`, `..`, Rückstriche und Nullbytes werden
abgewiesen. Der echte Pfad (`realpath`, Symlinks aufgelöst) muß im Basisordner liegen. Alles
andere ergibt 404. Die Antwort trägt `Content-Disposition: attachment`, ETag und Last-Modified.

Geprüft in beiden Testinstallationen über `$kernel->handle()`: `..`, kodiertes `%2e%2e`, fehlende
Datei, fremde Endung und `export/` ergeben 404.

## Zwischenspeicher

Der Index entsteht je Verband und liegt im Pool `cache.app`. Der Schlüssel lautet
`wertungsportal_dwz_archiv_<verband>_<fingerabdruck>`. Der Fingerabdruck stammt aus den
Änderungszeiten der Ordner des Verbands. Kommt eine Datei hinzu, ändert sich die Zeit ihres
Ordners, und der nächste Aufruf baut neu. Ein neuer Jahresordner kommt ohnehin in die
Ordnerliste. Zusätzlich verfällt jeder Eintrag nach einer Stunde. Converter und Swiss-Chess-Befehl
müssen den Cache deshalb nicht löschen (Franks Entscheidung, 25.11.2026).

Je Verband statt ein Eintrag für alles: Der ganze Bestand hat über 100.000 Dateien. Ein einzelner
Eintrag müßte bei jedem Seitenaufruf vollständig entpackt werden.

Gemessen am 25.11.2026 mit einem künstlichen Bestand von 133.364 Dateien im echten Aufbau, unter
Windows/NTFS (Linux bei Hetzner ist schneller):

| | |
| --- | --- |
| Aufbau ohne Cache, DSB (15.409 Dateien) | 0,9 s |
| Aufbau ohne Cache, ein Landesverband (6.208 Dateien) | 0,4 s |
| Seitenaufruf mit Cache | 6–17 ms |
| Cache-Eintrag DSB / Landesverband | 3,1 MB / 1,3 MB |

## Prüfen auf dem Server

```
vendor/bin/contao-console wertungsportal:dwz-archiv
```

Der Befehl zeigt je Verband und Gruppe die Zahl der Dateien, den ersten und letzten Tag, die
größte Lücke in Tagen und die Zahl je Jahr. Darunter stehen die Dateien, die nicht ins Muster
passen. Nebenbei füllt er den Cache.

Vor dem Ersetzen der alten Seiten Stichproben gegen die Akkordeons vergleichen: Lücken (Wertungsportal
14., 27., 29.08.2026), Jahreswechsel und den Übergang DeWIS → Wertungsportal (04.07. bis
24.07.2026).

## Bauweise

- `DwzArchiv\Archivindex`: Lesen, Zerlegen, Suchen, Auflösen. Ohne Contao prüfbar
  (`tests/DwzArchiv/ArchivindexTest.php`, Prüfordner mit leeren Zip-Dateien). Die Ordner liest
  `scandir()`, nicht Symfony Finder: Die Tests des Bundles laufen ohne Composer-Autoloader.
- `Controller\FrontendModule\DwzArchivSucheController`: Fragment-Controller, registriert über das
  Tag `contao.frontend_module` in `services.yml`. Die übrigen Module des Bundles stehen noch als
  alte `Module`-Klassen in `$GLOBALS['FE_MOD']`. Diesen Weg gibt es in Contao 5 nicht mehr.
  `#[AsFrontendModule]` wirkte hier nicht, weil das Bundle ohne Autoconfigure arbeitet und PHP 7.4
  Attribute überliest. `getResponse()` hat keinen Parametertyp (4.13: `Template`, 5:
  `FragmentTemplate`).
- Vorlage `src/Resources/views/dwz_archiv_suche.html.twig` (`@ContaoWertungsportal/…`), gerendert
  mit `render()`. Labels an allen Feldern, Ergebnisbereich mit `aria-live`.
- **Gestaltung komplett in `public/css/dwzarchiv.css`** (ab 1.61.1). 1.61.0 setzte auf Bootstrap-
  Klassen; das Theme auf schachbund.de lädt aber kein Bootstrap — das Datumsfeld blieb im
  Browserstil, die Auswahllisten bekamen die grauen 60-px-Felder des Themes. Jede Regel beginnt mit
  `.wp-dwzarchiv`, damit sie die allgemeinen Theme-Regeln für `select`, `table`, `th`, `td`
  überstimmt (das Theme setzt u. a. `table { display: block }`). Farben als Variablen
  `--wp-dwzarchiv-*` am Modul, ein anderes Theme stellt sie dort um.
- **Eine Tabelle für alle Formate:** je Format ein `tbody` mit Kopfzeile, je Tag eine Zeile;
  mehrere Dateien desselben Tags teilen sich Stand und Abstand (`rowspan`). Der genaue Treffer ist
  hinterlegt. Unter 576 px rücken Abstand und Größe als kleine Zeile unter Stand bzw. Dateiname
  (`.wp-dwzarchiv-schmal` statt `.wp-dwzarchiv-breit`), sonst scrollte die Tabelle seitwärts.
- Texte in `languages/*/default.php` (`$GLOBALS['TL_LANG']['MSC']['wp_dwzarchiv']`).
- **Twig im prod-Modus:** Eine geänderte Vorlage wirkt erst nach `cache:clear` (oder nach dem
  Löschen von `var/cache/prod/twig`).
