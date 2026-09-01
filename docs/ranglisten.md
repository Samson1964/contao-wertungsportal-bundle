# Ranglisten

Fertige, benannte Ranglisten für Deutschland — nach Altersklasse und
Geschlecht, mit geteilten Platzziffern, ohne Doppelte, mit Verein und
Verbandskürzel.

Die Klasse `Schachbulle\ContaoWertungsportalBundle\Helper\Ranglisten` ist ab
**Version 1.34.0** verfügbar.

## Wozu

Das Bundle `contao-topwertungszahlen-bundle` hat seine zehn Ranglisten bisher
selbst über die alte DeWIS-SOAP-Schnittstelle beschafft. Für jeden Spieler der
Trefferliste rief es zusätzlich `tournamentCardForId()` auf — nur, um dessen
Nation zu erfahren. Bei 1000 abgefragten Spielern je Liste und zwanzig Listen
waren das zehntausende Einzelabrufe je Lauf.

Mit dem Abschalten von DeWIS fällt dieser Weg weg. Statt die ganze Beschaffung
dort neu zu bauen, holt sich das Bundle die fertige Liste hier ab. Die Kenntnis
über Schnittstelle, Notbetrieb, Sperrliste, Mitgliedschaftsauswahl und
Verbandskürzel bleibt damit an einer Stelle.

In der `composer.json` des aufrufenden Bundles:

```json
"require": {
    "schachbulle/contao-wertungsportal-bundle": "^1.34"
}
```

## Die drei Methoden

| Methode | Liefert |
| --- | --- |
| `Ranglisten::dwz(array $params = [])` | Rangliste nach DWZ |
| `Ranglisten::elo(array $params = [])` | Rangliste nach FIDE-Elo |
| `Ranglisten::listentypen()` | Die zehn benannten Standardlisten mit ihren Parametern |

Alle drei sind statisch und brauchen keine Instanz.

## Parameter

Alle Parameter sind freiwillig; was fehlt, kommt aus den Vorgaben.

| Parameter | Vorgabe | Bedeutung |
| --- | --- | --- |
| `limit` | `50` | Anzahl der Plätze, höchstens 500 |
| `geschlecht` | `''` | `'MALE'`, `'FEMALE'`, `'DIVERSE'` oder leer für alle |
| `alter_von` | `0` | Mindestalter in Jahren, 0 = keine Grenze |
| `alter_bis` | `0` | Höchstalter in Jahren, 0 = keine Grenze |
| `nation` | `'GER'` | Nation, leer = alle Nationen |
| `vkz` | `''` | Vereinskennziffer als Präfix, leer = ganzer DSB |
| `nur_aktive` | `true` | Nur Spieler mit aktiver Spielgenehmigung |

Unbekannte Schlüssel werden verworfen. Das ist Absicht: Ein Tippfehler soll
nicht unbemerkt einen zweiten Cache-Eintrag für dieselbe Liste erzeugen.

### Das Alter wird nach Jahrgang gerechnet

Nicht nach Geburtstag. Wer im laufenden Jahr 20 wird, gilt das ganze Jahr über
als U20 — so sind die Altersklassen des DSB ausgeschrieben, und so rechnet auch
die Schnittstelle. `alter_bis => 20` heißt deshalb „Jahrgang `aktuelles Jahr −
20` und jünger".

Der VKZ-Präfix funktioniert wie an der Schnittstelle: `'3'` ist ganz Berlin,
`'30'` ein Bezirk, `'30012'` ein einzelner Verein.

## Rückgabe

```php
[
    'stand'  => 1756377600,   // Zeitstempel, auf den sich die Daten beziehen
    'quelle' => 'api',        // 'api' oder 'lokal'
    'liste'  => [ ... ],      // die Zeilen, siehe unten
]
```

`liste` ist immer ein Array — findet sich nichts, ist es leer, nie `null`.

Jede Zeile:

| Feld | Typ | Inhalt |
| --- | --- | --- |
| `platz` | int | Platzziffer, bei Gleichstand geteilt |
| `rang` | int | Identisch mit `platz` |
| `pkz` | string | `nuLigaPersonId` der Person |
| `vorname` | string | |
| `nachname` | string | |
| `titel` | string | Nationaler Titel aus der Mitgliederdatei |
| `geschlecht` | string | `MALE`, `FEMALE`, `DIVERSE` oder leer |
| `geburtsjahr` | int | 0 wenn unbekannt |
| `dwz` | int | Wertungszahl |
| `dwz_index` | int | Wertungsindex (Anzahl der Auswertungen) |
| `dwz_formatiert` | string | Etwa `1834-42` |
| `fide_id` | int | 0 wenn keine vorhanden |
| `elo` | int | FIDE-Elo aus `tl_wertungsportal_elo` |
| `elo_partien` | int | Gewertete Partien |
| `fide_titel` | string | `GM`, `IM`, `FM` … |
| `fide_titel_w` | string | `WGM`, `WIM`, `WFM` … |
| `nation` | string | Nation aus der Mitgliederdatei |
| `vkz` | string | Kennziffer der gewählten Mitgliedschaft |
| `verein` | string | Name des Vereins |
| `verbandskuerzel` | string | `BER`, `BAY`, `NRW` … |

`platz` und `rang` tragen denselben Wert. Beide Felder gibt es, weil die
Auswertungstabelle des Topwertungszahlen-Bundles die Spalte `rank` führt, die
Anzeige aber vom „Platz" spricht — so muß niemand umbenennen.

## Vollständiges Beispiel

Die fünfzig besten deutschen Spielerinnen unter 20 Jahren:

```php
use Schachbulle\ContaoWertungsportalBundle\Helper\Ranglisten;

$ergebnis = Ranglisten::dwz([
    'limit'      => 50,
    'alter_bis'  => 20,
    'geschlecht' => 'FEMALE',
]);

echo 'Stand: '.date('d.m.Y', $ergebnis['stand']);
echo $ergebnis['quelle'] === 'lokal' ? ' (aus dem örtlichen Bestand)' : '';

foreach ($ergebnis['liste'] as $zeile)
{
    printf(
        "%2d. %-4s %s, %s (%s) %s — %s%s\n",
        $zeile['platz'],
        $zeile['fide_titel_w'] ?: $zeile['fide_titel'],
        $zeile['nachname'],
        $zeile['vorname'],
        $zeile['dwz_formatiert'],
        $zeile['geburtsjahr'],
        $zeile['verein'],
        $zeile['verbandskuerzel'] ? ' ['.$zeile['verbandskuerzel'].']' : ''
    );
}
```

Ausgabe (gekürzt):

```
 1.      Roebers, Eline (2398-60) 2006 — Hamburger SK von 1830 [HAM]
 2.      Manko, Mariia (2237-45) 2007 — SC 1957 Bad Königshofen [BAY]
 3.      Kuznecova, Marija (2213-26) 2010 — TSV Schott Mainz [RLP]
```

### Alle zehn Standardlisten in einem Rutsch

```php
foreach (Ranglisten::listentypen() as $schluessel => $typ)
{
    $liste = Ranglisten::dwz(array_merge($typ['params'], ['limit' => 50]));

    echo $typ['name'].': '.count($liste['liste'])." Plätze\n";
    // in eigene Tabelle schreiben, Datei erzeugen …
}
```

`listentypen()` liefert:

| Schlüssel | Name | Parameter |
| --- | --- | --- |
| `alle` | Alle Spieler | — |
| `w` | Frauen | `geschlecht: FEMALE` |
| `u20` | U20 | `alter_bis: 20` |
| `u20w` | U20 weiblich | `alter_bis: 20`, `geschlecht: FEMALE` |
| `50+` | Senioren 50+ | `alter_von: 50` |
| `50w+` | Seniorinnen 50+ | `alter_von: 50`, `geschlecht: FEMALE` |
| `65+` | Senioren 65+ | `alter_von: 65` |
| `65w+` | Seniorinnen 65+ | `alter_von: 65`, `geschlecht: FEMALE` |
| `75+` | Senioren 75+ | `alter_von: 75` |
| `75w+` | Seniorinnen 75+ | `alter_von: 75`, `geschlecht: FEMALE` |

Die Schlüssel entsprechen denen des Topwertungszahlen-Bundles ohne dessen
Präfix `dwz_` beziehungsweise `elo_`, damit die vorhandenen Auswertungen und
Archivdaten weiterpassen.

## Regeln, die das Ergebnis bestimmen

### Geteilte Platzziffern

Bei gleicher Wertungszahl teilen sich die Spieler den Platz, und der nächste
überspringt die verbrauchten Ziffern:

```
1. Adler    2900
2. Berger   2850
2. Conrad   2850
4. Iber     2800
```

### Reihenfolge bei Gleichstand

Erster Schlüssel ist die Wertungszahl. Danach entscheidet bei der DWZ der
höhere Wertungsindex (mehr ausgewertete Turniere), bei Elo die höhere
Partienzahl; zuletzt Nachname und Vorname.

Sortiert wird in PHP, nicht in SQL. Ein zweiter Sortierschlüssel in der Abfrage
macht den Index `(published, rating)` für die Ordnung nutzlos — MySQL sortiert
dann 95.000 Zeilen nach, gemessen 972 ms statt 40 ms.

### Keine Doppelten

Eine Person kann in mehreren Vereinen gemeldet sein und deshalb mehrfach
geliefert werden. Es gewinnt die zuerst gefundene Zeile, und die trägt bereits
die nach der Mitgliedschaftsregel ausgewählte Vereinsangabe: **Eine aktive
Spielgenehmigung hat Vorrang**, sonst stünde ein Spieler mit seinem Zweitverein
in der Liste.

### Der Nationenfilter läßt leere Werte durch

Das ist die wichtigste Regel — und die, die man beim Nachlesen am ehesten für
einen Fehler hält.

Die Nation steht in `tl_wertungsportal_persons.nation` und stammt
**ausschließlich aus dem Import der Vereinsmitglieder-CSV**; die
nu-Schnittstelle liefert das Feld gar nicht. Wer Leerwerte ausschlösse, bekäme
eine Rangliste, in der jeder Spieler fehlt, dessen Datensatz allein über die
Schnittstelle entstanden ist. Ausgeschlossen wird deshalb nur, wer
nachweislich eine **andere** Nation trägt.

Wie gut der Filter greift, hängt damit unmittelbar davon ab, wie vollständig
das Feld gepflegt ist. Das läßt sich jederzeit nachsehen:

```sql
SELECT nation, COUNT(*) FROM tl_wertungsportal_persons GROUP BY nation ORDER BY 2 DESC;
```

Steht dort überwiegend ein Leerwert, ist der Personen-Import noch nicht
gelaufen — die Liste enthält dann auch Ausländer.

### Es wird mehr angefordert, als gebraucht wird

Die Schnittstelle kennt keinen Nationenfilter. Sie liefert die besten Spieler
des Verbands, und erst danach fallen die Ausländer heraus — für 50 Deutsche
sind also mehr als 50 Zeilen nötig. Angefordert wird deshalb das **Vierfache**
(Konstante `UEBERZUG`), höchstens aber 1000 (`UEBERZUG_MAX`).

Reicht das im Einzelfall nicht, wird **einmal** mit der Obergrenze
nachgefordert. Die Liste ist also auch dann vollständig, wenn der Faktor zu
klein gewählt war; sie kostet dann einen zweiten Abruf. Nachgefordert wird nur,
wenn die Quelle die angeforderte Menge tatsächlich ausgeschöpft hat — sonst ist
der Bestand erschöpft und ein zweiter Abruf reine Verschwendung.

Zum Vergleich: Der alte Weg über DeWIS brauchte für 50 Deutsche 1000
Datensätze, also den Faktor 20. Das lag daran, daß dort gegen die
**FIDE**-Nation geprüft wurde — wer bei der FIDE unter einer anderen Föderation
geführt wird, fiel heraus, auch als DSB-Mitglied.

### Wer nicht in der Liste steht

* Gesperrte Personen (Sperrliste, Feld `blocked`)
* Verstorbene
* Im Backend abgeschaltete Datensätze
* Spieler ohne Wertungszahl
* Bei gesetztem Altersfilter: Spieler ohne bekanntes Geburtsjahr
* Bei `nur_aktive` (Vorgabe): Spieler ohne aktive Spielgenehmigung

Die ersten drei Punkte werden zweimal geprüft — der örtliche Weg filtert sie
schon in der Abfrage, die Schnittstelle kennt diese Merkmale nicht. So liefern
alle Bezugsquellen dasselbe Ergebnis.

## Woher die Daten kommen

### `dwz()` — über die Schnittstelle, mit Notbetrieb

Der Aufruf läuft über `API::autoQuery()` mit der Funktion `Verbandsliste` und
erbt damit die dreistufige Auslieferung des Bundles:

1. gültiger Zwischenspeicher
2. abgelaufener Zwischenspeicher (Notreserve)
3. örtlicher Bestand (`Helper\Lokal`)

**Die Liste kommt also auch dann, wenn nu gerade nicht antwortet.** Das Feld
`quelle` sagt, woher: `'api'` heißt „von der Schnittstelle oder aus deren
Zwischenspeicher", `'lokal'` heißt „aus den Spiegeltabellen". `stand` nennt in
beiden Fällen den Zeitpunkt, auf den sich die Daten beziehen.

Was die Schnittstelle nicht liefert — Nation, nationaler Titel, Sperrvermerk —
kommt in einer Abfrage aus `tl_wertungsportal_persons` dazu. Die FIDE-Daten
(Elo, Partien, beide Titelfelder) kommen aus `tl_wertungsportal_elo`, und zwar
erst **nach** dem Kürzen auf `limit`: Angereichert wird nur, was auch ausgegeben
wird.

### `elo()` — rein örtlich

Für die FIDE-Wertungen gibt es keine Schnittstelle; sie liegen vollständig in
`tl_wertungsportal_elo` und kommen über den monatlichen XML-Import dorthin.
`quelle` meldet deshalb immer `'lokal'`, und `stand` nennt das Listendatum
(`elodate`) der beteiligten Einträge.

Geführt wird die Liste von der Elo-Tabelle: Nation, Geschlecht und Alter kommen
aus `country`, `sex` und `birthday`; inaktive Spieler (Kennzeichen `i` im Feld
`flag`) bleiben draußen. Die Person wird über die FIDE-ID dazugesucht.

**Findet sich keine Person, bleibt der Eintrag trotzdem in der Liste** — dann
sind `pkz`, `verein`, `verbandskuerzel` und die DWZ-Felder leer. Zwei Parameter
wirken deshalb anders als bei `dwz()`: `vkz` und `nur_aktive` beschreiben eine
Mitgliedschaft, die es ohne Person gar nicht gibt. Sie werden nur auf Einträge
angewandt, zu denen eine Person gefunden wurde.

Ist eine Person vorhanden, gewinnt ihre **Schreibweise des Namens** gegenüber
der FIDE: Dort stehen Umlaute als „ue"/„oe", und Doppelnamen sind zusammen­
gezogen.

> **Ohne einmaligen XML-Import ist `tl_wertungsportal_elo` leer.** `elo()`
> liefert dann eine leere Liste, und in `dwz()` bleiben die Felder `elo`,
> `elo_partien`, `fide_titel` und `fide_titel_w` leer. Der Import läuft im
> Backend unter *WP | FIDE-Elo → XML-Import*.

## Zwischenspeicher

Die fertige Liste wird zwischengespeichert; der Schlüssel entsteht aus **allen**
Parametern, gleichwertige Aufrufe (`'50'` und `50`) treffen denselben Eintrag.

Cachezeit und Ein/Aus-Schalter werden von der Funktion `Verbandsliste`
übernommen (Einstellungen → Wertungsportal): Die Rangliste ist deren
Auswertung und soll nicht länger gelten als ihre Grundlage. Ist der
Zwischenspeicher abgeschaltet, wird weder gelesen noch geschrieben.

Eine **leere** Liste wird bewußt nicht gespeichert. Sie entsteht auch dann, wenn
die Schnittstelle gerade nichts liefert und der örtliche Bestand noch leer ist
— wer sie speicherte, hielte den Ausfall über die ganze Cachezeit fest.

Geleert wird der Speicher zusammen mit allen anderen: *System → Systemwartung →
Wertungsportal-Cache leeren*, und automatisch nach jedem FIDE-Elo-Import (sonst
zeigte eine Rangliste nach dem Import noch die alten Elo-Zahlen). Das
Verzeichnis heißt `wp_Rangliste`.

## Verbandskürzel

Das Kürzel kommt aus der **ersten Stelle** der Vereinskennziffer:

| Stelle | Kürzel | Stelle | Kürzel | Stelle | Kürzel |
| --- | --- | --- | --- | --- | --- |
| 1 | BAD | 8 | RLP | E | MVP |
| 2 | BAY | 9 | SAR | F | SAC |
| 3 | BER | A | SH | G | SAA |
| 4 | HAM | B | BRE | H | THÜ |
| 5 | HES | C | WÜR | L | BSB |
| 6 | NRW | D | BRA | M | SWA |
| 7 | NDS | | | | |

Die Tabelle steht als Konstante `VERBANDSKUERZEL` in der Klasse und wird nicht
aus den Vereinsdaten gelesen: Die Felder `kurzname` und `druckname` sind in
`tl_wertungsportal_clubs` für alle 17 Landesverbände leer, und die
Schnittstelle kennt Verbände überhaupt nur als Vereine.

## Prüfungen

Die Regeln, die ohne Datenbank gelten — geteilte Platzziffern, Altersfilter mit
beiden `birthyear`-Formaten, Entdoppeln, Nationenfilter,
Mitgliedschaftsauswahl, Verbandskürzel, Parameter- und Schlüsselbildung —
stehen als Unit-Tests in `tests/Helper/RanglistenTest.php`:

```bash
php F:\Claude\tools\phpunit9\vendor\bin\phpunit
```

Das Bundle hat bewußt kein eigenes `vendor/`-Verzeichnis; `tests/bootstrap.php`
registriert deshalb einen schlanken PSR-4-Lader statt eines
Composer-Autoloaders.

Alles, was gegen die echten Tabellen läuft, wird über den Prüfstand in der
Contao-4.13-Testinstallation abgedeckt: beide Wege (örtlicher Bestand und eine
über den Zwischenspeicher eingeschleuste Antwort der Schnittstelle), die
Filter, die Sperrliste, der Zwischenspeicher und alle zehn Standardlisten.
