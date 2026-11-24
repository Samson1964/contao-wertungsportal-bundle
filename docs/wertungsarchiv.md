# Wertungsarchiv (ab 2.0.0, im Bau)

Das Wertungsarchiv führt die drei Bestände der DWZ-Geschichte zusammen:

| Archiv | Kennung | Zeitraum | Turniere | Personen | Karteizeilen |
| --- | --- | --- | --- | --- | --- |
| ELOBASE (ZDB) | `elobase` | 1978–2013 | 100.722 | 301.097 | 4.879.493 |
| DeWIS | `dewis` | 2010–2026 | 66.685 | 540.153 | 3.021.788 |
| Wertungsportal (nu) | `wp` | 2010–2026 | 68.467 | 1.020.695 | 2.986.693 |

Die Daten bereitet eine eigene Arbeitssitzung auf (Ordner `Umbau auf DWZ-Archiv/` im
Arbeitsverzeichnis, nicht im Repository: Personendaten). Das Bundle liest nur ihre
**Übergabe** — je Archiv ein Ordner mit Listen (`*.jsonl`), einer Prüfdatei
(`uebergabe.json`) und den Turnieren als JSON-Dateien.

## Aufbau

- **Eigene Datenbank.** Das Archiv liegt nicht in der Datenbank von Contao, sondern in einer
  zweiten (Festlegung 17.11.2026). Contao weiß davon nichts: Die Tabellen stehen nicht im DCA,
  `contao:migrate` legt sie weder an noch löscht es sie. Angelegt werden sie mit
  `wertungsportal:archiv-schema`.
- **Turniere als Dateien.** In die Datenbank kommt, wonach gesucht oder verknüpft wird:
  Turnierliste, Personen, Karteizeilen, Brücke. Die Turniere selbst (Teilnehmer, Partien,
  Mannschaften) bleiben JSON-Dateien im Ordner der Übergabe. Der Ordner liegt **außerhalb**
  von `files/` und `public/` — sonst läse die Dateiverwaltung 235.000 Dateien ein, und die
  Personendaten wären per Adresse abrufbar. Ausgeliefert wird nur über das Bundle.
- **Nichts geht verloren.** Jedes Feld einer Liste kommt in eine Spalte oder unverändert in
  die Spalte `extra` (JSON). Es wird nichts umgerechnet (Grundsatz Frank Binding, 17.11.2026).
- **Abgeschaltet, solange nicht eingerichtet.** Ohne `WERTUNGSARCHIV_DATABASE_URL` arbeitet
  das Bundle wie in 1.x.

| Tabelle | Inhalt | Schlüssel |
| --- | --- | --- |
| `archiv_turnier` | Turnierliste aller Archive: Code, Name, Ende, Verband, Zahlen, JSON-Pfad, Kennzeichen, Verweis auf die Dublette im älteren Archiv | `archiv` + `quell_id` |
| `archiv_person` | Personen je Archiv: `Z:<PKZ>`, `D:<DeWIS-ID>`, `N:<NU-ID>`, `W:<Turnier>-<Nr>` | `kennung` |
| `archiv_kante` | Brücke zwischen den Archiven, vom jüngeren aufs ältere: `pkz` (DeWIS → ELOBASE), `dewis_ersatz` (DeWIS-Ersatzperson → ELOBASE), `turniere`/`externenr` (Portal → DeWIS), `dublette` | `von` + `nach` + `quelle` |
| `archiv_gesamtperson` | Gesamtpersonen: zusammenhängende Archivpersonen über bestätigte Kanten; Nummer = kleinste Personen-ID der Gruppe, Name/Geburtsjahr zuerst aus dem Portal, NU-ID, Prüfvermerke | `id` |
| `archiv_kartei` | Karteizeilen (Person × Turnier) mit DWZ vorher/nachher, Punkten, Partien, We | `id`; je Archiv ersetzt |
| `archiv_info` | Schemafassung, Stand des letzten Imports je Archiv | `schluessel` |

## Entscheidungen (Frank Binding, 18.11.2026)

- Zweite Datenbank auf demselben Server, Zugang in `.env.local`.
- **Alle Kanten der Übergabe gelten als bestätigt.** Der Status bleibt je Kante gespeichert,
  damit sich eine falsche Zusammenführung zurücknehmen lässt; ein erneuter Import ändert ihn
  nicht.
- **Ausgabe zeigt die jüngste Quelle** (Wertungsportal vor DeWIS vor ELOBASE), Namen kommen
  über die Brücke dazu.
- **DeWIS-Ersatzpersonen (19.11.2026, Rückfrage R1):** 61 ELOBASE-Personen zeigen über die
  Tabelle `pkz` auf DeWIS-IDs, die DeWIS beim Zusammenlegen gelöscht hat. Die Aufbereitung führt sie
  in `elobase/dewis_ersatz.jsonl`; wo sie eine eindeutige Ersatz-ID gefunden hat (`erg_ersatz`, 37),
  legt `archiv-import elobase` mit den Personen die Kante DeWIS-Ersatz → ELOBASE an (Quelle
  `dewis_ersatz`, im Backend einzeln ablehnbar). Bei 16 zeigen dadurch zwei ELOBASE-Personen auf
  dieselbe DeWIS-Person — gewollt, das sind Tippfehler-Dubletten der ZDB. Schwache Kandidaten
  (`erg_kandidaten`) werden nicht übernommen. Führt eine neue Liste eine Ersatzkante nicht mehr, wird
  sie „entfallen“; der DeWIS-Import lässt diese Kanten unberührt.
- **Monatlich ein neuer Abzug** aus dem Backend von nu; die Aufbereitung läuft erneut, dann
  der Import. Ein Hashname überschreibt nie einen Klarnamen.
- Die Dateien kommen **per FTP** in einen Ordner außerhalb von `files/`, übernommen wird über
  die Konsole.
- Turniere vor der ersten Mitgliedschaft (`vor_erster_anmeldung = 1`) werden **gekennzeichnet**
  angezeigt, nicht ausgeblendet.

## Einrichten

1. Leere Datenbank anlegen (utf8mb4) und in `.env.local` eintragen:

```text
WERTUNGSARCHIV_DATABASE_URL="mysql://benutzer:passwort@localhost:3306/wertungsarchiv"
WERTUNGSARCHIV_PFAD="/pfad/zum/projekt/wertungsarchiv"
```

2. Den Inhalt von `Aufbereitung/ausgabe/` (die Ordner `elobase`, `dewis`, `wp`) nach
   `WERTUNGSARCHIV_PFAD` übertragen — außerhalb von `files/` und `public/`.
3. Tabellen anlegen:

```bash
vendor/bin/contao-console wertungsportal:archiv-schema --force
```

4. Archive übernehmen, ältestes zuerst:

```bash
vendor/bin/contao-console wertungsportal:archiv-import elobase
```

```bash
vendor/bin/contao-console wertungsportal:archiv-import dewis
```

```bash
vendor/bin/contao-console wertungsportal:archiv-import wp
```

5. Gesamtpersonen bilden (nach jedem Import und nach jeder abgelehnten Kante):

```bash
vendor/bin/contao-console wertungsportal:archiv-personen
```

6. `contao:migrate` für die neue Spalte `tl_member.wertungsarchiv`, dann beim eigenen
   Mitglied **Zugriff auf das Wertungsarchiv** anhaken (Mitglieder → Bearbeiten → Wertungsarchiv).

Der Import prüft vorher das Prüfergebnis der Aufbereitung und die SHA-256 jeder Liste
(`--ohne-pruefsumme` schaltet Letzteres ab), übernimmt Turniere, Personen samt Kanten und
Kartei und darf beliebig oft laufen: Turniere und Personen werden aktualisiert (ihre IDs
bleiben), die Kartei des Archivs wird ersetzt. `--nur=kartei` übernimmt einzelne Teile.

## Wer das Archiv sieht

Niemand, solange es erprobt wird (Frank Binding, 18.11.2026) — außer angemeldeten Mitgliedern,
bei denen in `tl_member` der Schalter **Zugriff auf das Wertungsarchiv** gesetzt ist. Jede
Stelle der Ausgabe fragt `Wertungsarchiv\Zugriff::erlaubt()`; ohne eingerichtete Datenbank ist
die Antwort immer nein.

## Gesamtpersonen (Etappe 2)

`wertungsportal:archiv-personen` verbindet die Personen der drei Archive über die bestätigten
Kanten (Union-Find) und baut `archiv_gesamtperson` jedes Mal neu — eine abgelehnte Kante löst
die Gruppe beim nächsten Lauf wieder auf. Anonyme Portalzeilen (`W:`) ohne Kante bekommen keine
Gesamtperson.

Lauf 18.11.2026 mit der vollen Übergabe (7 Minuten): 1.251.222 Kanten, **542.204
Gesamtpersonen**, 68.519 `W:`-Zeilen ohne Kante. Prüfvermerk nur `mehrere_dewis` (56 — die
bekannten Dubletten in DeWIS). Die größten Gruppen (bis 176 Personen) sind je eine
DeWIS-Person mit ihren anonymen Portalzeilen, keine Fehlverkettung.

| Archive | Gesamtpersonen |
| --- | --- |
| ELOBASE + DeWIS | 207.096 |
| DeWIS + Portal | 183.991 |
| alle drei | 93.920 |
| nur DeWIS | 55.090 |
| nur Portal | 2.026 |
| nur ELOBASE | 81 (20 ohne PKZ-Zuordnung, 61 mit DeWIS-ID, die der DeWIS-Übergabe fehlt) |

## Backend: Wertungsportal → Wertungsarchiv

| Ansicht | Inhalt |
| --- | --- |
| Überblick | Turniere, Personen und Karteizeilen je Archiv mit Stand der Übergabe und des Imports; Kanten nach Quelle und Status; Zahl der Gesamtpersonen |
| Suche | Namensanfang (auch „Mueller,Klaus“ wie in ELOBASE — gesucht wird mit und ohne Leerzeichen nach dem Komma), NU-Nummer, Kennung einer Archivperson (`Z:`, `D:`, `N:`, `W:`) oder Nummer der Gesamtperson (`#123`) |
| Gesamtperson | ihre Archivpersonen mit Karteizeilen und Zeitraum, alle Kanten — auch abgelehnte, die aus der Gruppe hinausführen |
| Prüffälle | Gesamtpersonen mit Prüfvermerk, abgelehnte Kanten |

Jede Kante lässt sich **ablehnen** oder **wieder bestätigen**. Das ändert nur ihren Status (mit
Zeitpunkt, Eintrag im Systemprotokoll mit Benutzername); die Gruppe trennt sich beim nächsten
Lauf von `wertungsportal:archiv-personen`. Ein erneuter Import ändert den Status nicht.

Geprüft 18.11.2026: Kante NU4005028 → D:10000004 abgelehnt, Gesamtpersonen neu gebildet — die
Portalperson stand allein, DeWIS- und ELOBASE-Person blieben verbunden; wieder bestätigt, neu
gebildet — alles wie vorher.

## Ausgabe (Etappe 3)

Alles nur mit Zugriff (siehe „Wer das Archiv sieht“). Für alle anderen bleiben Karteikarte,
Suche und Turnierseiten, wie sie waren.

| Wo | Was |
| --- | --- |
| Karteikarte `spieler/NU…` | Unter den Turnieren von nu die **älteren Turniere** aus ELOBASE und DeWIS, neueste zuerst — nur solche, die keine jüngere Quelle führt (eine DeWIS-Zeile entfällt, wenn die Person im selben Portalturnier unter ihrer NU-Nummer steht — siehe „DeWIS oder Portal“ unten; eine ELOBASE-Zeile, wenn die Person im selben DeWIS-Turnier steht — siehe „ELOBASE oder DeWIS“). Die „Datenübernahme nach DeWIS“ zeigt nu selbst. Erste Spalte: Archivsymbol mit „ZDB“ oder „DeWIS“, linksbündig (Langname im Tooltip). ELOBASE-Sätze aus einem **älteren Abzug** der ZDB tragen den Abzug dahinter („ZDB 8/1998“, `kartei.quelle`); „ZDB“ allein ist der letzte Stand. Besuchte Turnierlinks erscheinen violett (`table.wp-kartei a:visited`). |
| Karteikarte `spieler/G<Nummer>` | Für Spieler **ohne NU-Nummer**, die nur noch im Archiv stehen: Name, Geburtsjahr, Archive, die ganze Kartei einschließlich der Portalzeilen aus dem Archiv. Mit NU-Nummer leitet die Adresse auf die normale Karteikarte weiter. |
| Spielersuche | Zweite Trefferliste „Aus dem Wertungsarchiv (heute nicht mehr gemeldet)“ — Gesamtpersonen ohne NU-Nummer, gesucht in allen Schreibweisen („Müller, Klaus“, „Müller,Klaus“, „Mueller,Klaus“). |
| Turnierseite `turniere/A<Nummer>` | Ein Turnier aus dem Archiv: Kopf, Teilnehmer mit Verein, DWZ vorher/nachher, Punkten, Partien, We, E, Gegnerschnitt, Leistung; Teilnehmer mit NU-Nummer verlinkt. Rekonstruktionen und Abweichungen von der Kartei sind vermerkt. |
| Turnierseite `turniere/A<Nummer>/Ergebnisse` | Partien: **Kreuztabelle**, wenn jeder gegen jeden gespielt hat (bis 30 Spieler, mindestens 90 % aller Paare — an den Partien entschieden, weil DeWIS als Rundenzahl oft nur 5 führt), sonst **Rundenansicht** mit Ergebnis und Gegner je Runde. Farben aus `partien.farbe` (Rückfrage R3, w/s) mit denselben Klassen wie die Ergebnisse von nu: Rundenansicht `div.white`/`div.black`, Kreuztabelle `span.result.kreuz-w`/`kreuz-s`, Tooltip „mit Weiß/Schwarz“. ELOBASE führt keine Farbe (immer null) — dort sagt der Hinweis „Diese Turnierdatei führt keine Farben“. Ein leeres Feld heißt, die Turnierdatei nennt kein Ergebnis. „Erg.“ zählt wie in der Auswertung nur gewertete Partien. |
| DWZ-Diagramm (Karteikarte NU… und G<Nummer>) | Die Archivwerte stehen vor den Werten von nu auf **grauem Grund** (Legende „Wertungsarchiv“). Die DWZ-Linie läuft durch; Leistung nur, wo das Archiv eine Turnierleistung führt (DeWIS, Portal) — für Archivturniere wird **nichts geschätzt**, weder aus Gegnerschnitt und Punkten noch durch Überbrücken (`archiv` = true am Punkt, `Darstellung::diagrammpunkt()`). Ab 50 Punkten wie bisher die letzten 50 plus Gesamtdiagramm. Die G-Karteikarte ist ganz grau, sie hat nur Archivwerte. |
| Turnierseite `turniere/<ELOBASE-Code>` | Alte Verweise aus der Zeit vor DeWIS. DeWIS-Codes (auch die 2011–2013 in beiden Archiven) leiten wie seit 1.58.0 auf die jüngere Fassung bei nu. |

**Turniere vor der ersten Mitgliedschaft** (`vor_erster_anmeldung = 1`) stehen grau da, mit der Marke
„vor 1. Anmeldung“ hinter dem Turniernamen (Frank Binding, 18.11.2026: kennzeichnen, nicht ausblenden;
bis 20.11.2026 eine gestrichelte Linie links von „Nr.“, die wie ein Darstellungsfehler aussah).

**DeWIS oder Portal — je Zeile entschieden (24.11.2026):** nu hat den DeWIS-Bestand übernommen, führt
Spieler ohne NU-Nummer aber nur anonymisiert: DWZ vorher eingeklammert, ohne Index, ohne neue DWZ
(`W:`-Personen; in der Übergabe 879.014 Zeilen, bei 287.450 davon hat DeWIS die neue DWZ). Wer nach
Jahren Pause wieder Mitglied wird, bekommt eine neue NU-Nummer, deren Karteikarte bei nu diese Turniere
nicht kennt. Deshalb entfällt eine DeWIS-Zeile nur, wenn **dieselbe Person** im Portalturnier eine
brauchbare Zeile hat:

- Karteikarte `spieler/NU…`: eine Zeile ihrer `N:`-Person — nur die zeigt nu live.
- Karteikarte `spieler/G<Nummer>`: eine Portalzeile **mit** neuer DWZ. Eine Portalzeile ohne neue DWZ
  weicht dort der DeWIS-Zeile derselben Person; die Quelle heißt dann „DeWIS“.

Bis zum 24.11.2026 galt die Regel je Turnier: Hatte das Portal das Turnier, entfiel jede DeWIS-Zeile.
Gemessen an einer Stichprobe von 300 Archiv-Karteikarten: Sämtliche Portalzeilen (durchweg ohne neue
DWZ) stehen jetzt als DeWIS-Zeilen da, 37 Karteikarten bekamen 1–2 Zeilen zurück, die vorher ganz
fehlten. Von 131 Karteikarten mit NU-Nummer bekam eine eine DeWIS-Zeile zurück, die übrigen blieben
gleich. Rechenzeit je Karteikarte etwa 20 ms.

**ELOBASE oder DeWIS — ebenfalls je Zeile (24.11.2026):** Beide Archive überschneiden sich nur bei den
Turnieren mit Ende Januar 2011 bis Juli 2013 (12.078 Turniercodes). DeWIS hat sie am 27./28.07.2013 aus
ELOBASE übernommen (Auswertungsdatum in DeWIS; Meldung „DeWIS geht nach einem Reset wieder an den Start“
vom 12.08.2013) und rechnet erst seit dem 13.08.2013 selbst aus; davor steht in DeWIS nur die
„Datenübernahme nach DeWIS“ zum 31.12.2010. Vergleich der ELOBASE-Zeilen dieser Turniere mit DeWIS:

| Befund | Zeilen |
| --- | --- |
| gleiche DWZ und gleicher Index | 404.600 |
| abweichend (fast nur 2013 — DeWIS hat nachgerechnet; es gilt DeWIS) | 21.014 |
| DeWIS-Zeile bei derselben Gesamtperson über eine andere Archivperson | 27 |
| DeWIS führt die Person in diesem Turnier gar nicht (andere oder zweite DeWIS-Person) | 298 bei 91 Gesamtpersonen |

Die letzten 298 Zeilen fielen mit der Regel je Turnier ganz von der Karteikarte (Beispiel: eine
DWZ-Kette 1301-17 → 1325-19 mit fehlendem Turnier dazwischen). Jetzt entfällt eine ELOBASE-Zeile nur,
wenn dieselbe Gesamtperson im DeWIS-Turnier eine Zeile hat.

**Doppelt erfasste ELOBASE-Turniere (Rückfrage R6, 20.11.2026):** Die Aufbereitung kennzeichnet die
ältere Fassung desselben Turniers unter anderem Code mit `dublette` und nennt in `erg_dublette_von`
(im Bundle `extra`) den Code der gültigen ZDB-Fassung. Solche Turniere erscheinen in keiner
Karteikarte (`Archiv::altkartei()`, über das Kennzeichen — der Verweis steht an der Dublette, nicht an
der gültigen Fassung wie bei DeWIS → ELOBASE). Ihre Turnierseite bleibt erreichbar und verweist auf die
gültige Fassung.

Die **Archivturnier-Seite** nennt bei ELOBASE die Herkunft des Turnierkopfs (`extra.kopf_quelle`):
„ZDB, letzter Stand“, „älterer ZDB-Abzug 7/1997“ oder „nur Turnierdatei“.

Einheiten werden nur für die Anzeige umgerechnet (`Wertungsarchiv\Darstellung`): Halbpunkte → „4½“,
We · 1000 → „7,215“. ELOBASE führt in der Kartei die **Erfolgszahl**, nicht die Turnierleistung — sie
erscheint nicht in der Spalte „Lstg.“.

**E, Gegnerschnitt und Leistung (19.11.2026):** DeWIS und Portal führen alle drei in der Kartei
(E in `extra.e_koeffizient`). Die ELOBASE-Kartei führt keinen davon; die Turnierdatei hat Gegnerschnitt
und Turnierleistung im gemeinsamen Teil, E nur roh (`efaktor`). Die Aufbereitung soll sie je Karteisatz als
`erg_e_koeffizient`, `erg_gegnerschnitt`, `erg_turnierleistung` ergänzen (Rückfrage R5); das Bundle
liest diese Namen bereits, danach `archiv-import elobase --nur=kartei`. In der Archivturnier-Ansicht
stehen die Spalten „E“ und „Gegn.“; „E“ bleibt leer, bis die Turnierdateien `e_koeffizient` im
gemeinsamen Teil führen (R5). Rekonstruierte Turniere (ohne Turnierdatei) haben weder Verein noch
Gegnerschnitt oder Leistung.

Ein Fehler der Archivdatenbank kippt keine Seite: Er kommt ins
Systemprotokoll, die Seite erscheint ohne Archiv.

## Reklamationen (ab 20.11.2026)

Wer das Archiv sehen darf, findet hinter jedem Archiveintrag der Karteikarte (NU… und G…) eine kleine
Fahne. Sie öffnet einen Dialog mit Gründen zum Ankreuzen und einer Bemerkung; unter der Tabelle steht
der Knopf **„Turnier fehlt?“** mit Feldern für Turnier, Zeitraum, Ort und Ergebnis. Gründe
(`Archivreklamation::GRUENDE`, die Schlüssel stehen in der Datei und bleiben fest):

| Schlüssel | Text im Dialog |
| --- | --- |
| `fremd` | Gehört nicht zu diesem Spieler (ein anderer, etwa ein Namensvetter, hat das Turnier gespielt) |
| `doppelt` | Doppelt in der Kartei (dasselbe Turnier steht noch einmal da, oft mit anderem Code oder Datum) |
| `nie` | Dieses Turnier hat es nicht gegeben |
| `ergebnis` | Ergebnis falsch (Punkte oder Partienzahl) |
| `wertung` | Wertungszahlen falsch (DWZ vorher/nachher, We, Leistung) |
| `datum` | Datum oder Jahr falsch, Turnier steht an der falschen Stelle |
| `name` | Turniername falsch oder nichtssagend |
| `nicht_dwz` | Turnier hätte nicht DWZ-gewertet werden dürfen (z. B. Blitz, Schnellschach, Simultan) |
| `unvollstaendig` | Partien fehlen oder gehören zu einem anderen Turnier |
| `sonstiges` | Etwas anderes (Bemerkung Pflicht) |

**Ablage:** Nichts wird verschickt. Jede Meldung ist eine JSON-Zeile in
`var/wertungsarchiv/reklamationen.jsonl` (Format `wertungsarchiv-reklamation/1`): Zeitpunkt, Gründe,
Bemerkung, Felder, die Karteikarte (NU-Nummer, Gesamtperson, Name), der Eintrag, wie er angezeigt
wurde (Archiv, Kennung, Code, ZDB-Abzug, Satz, Turnier, Ende, Partien, Punkte, DWZ), Mitglied
(Nummer, Name, E-Mail) und `status: offen`. Die Datei wird nur angehängt, nie geändert; sie liegt
außerhalb von `public/` und übersteht Updates und neue Uploads der Archivordner.

**Schlüssel statt Datenbanknummer:** Eine Zeile wird über `archiv|kennung|code|abzug|satz` erkannt
(`Archivreklamation::schluessel()`), weil der Import die Kartei ersetzt und die Nummern dabei
wechseln. „Turnier fehlt“ hängt an der NU-Nummer (bzw. an der ersten Archivperson einer G-Karte).
Reklamierte Zeilen tragen die Marke „reklamiert“ und einen hellen Orangeton; erneut reklamieren geht,
der Dialog zeigt dann die bisherigen Meldungen — mit Datum, Gründen und Text, aber **ohne den
Namen** des Melders (nur „Ihre Meldung“ bei eigenen).

**Sicherheit:** Der Kontext jedes Knopfs ist mit `kernel.secret` signiert (wie bei der Reklamation
der nu-Daten); der Controller (`POST /wertungsportal-api/archiv-reklamation`) nimmt nur Mitglieder mit
Archivzugriff an, prüft Signatur und Eingabe und bremst bei 60 Meldungen je Stunde. Ein Fehler beim
Ausgeben des Dialogs nimmt nur die Knöpfe weg, nie die Archivzeilen.

**Nächtlicher Bericht:** Der Cronjob `Archivreklamationsbericht` (5:15 Uhr) schickt dem Webmaster
(Contao-Einstellung „E-Mail-Adresse des Systemadministrators“, `adminEmail`) eine Textmail mit allen
Meldungen seit dem letzten Bericht. Ohne neue Meldung geht nichts hinaus. Der Stand steht in
`var/wertungsarchiv/reklamationen.jsonl.bericht`; scheitert der Versand, versucht es der nächste Lauf
mit denselben Meldungen. Ohne echten Cronjob beim Hoster löst nur ein Seitenaufruf den Termin aus.

**Abarbeitung (Frank Binding, 20.11.2026): Berichtigung nur über die Aufbereitung.** Die Datei geht
an die Aufbereitung (Rückfrage R6 in `UEBERGABE.md`). Dort werden je Grund Belege gesammelt, eine
Berichtigungsliste als eigene Datei geschrieben, im Probelauf mit Vorher/Nachher je Person geprüft und
nach Franks Freigabe beim Neuaufbau angewendet; danach wie immer `archiv-import` und
`archiv-personen`. Das Bundle hat **keine eigene Ausblendliste** und ändert an den Daten nichts — ein
falscher Eintrag bleibt sichtbar (als „reklamiert“), bis der nächste Neuaufbau ihn berichtigt.
Vorgeschlagen ist eine Rückmeldung der Aufbereitung (`reklamationen_status.jsonl`), damit die
Karteikarte „erledigt“ bzw. „abgelehnt“ zeigen kann; gebaut wird sie erst, wenn das Format steht.

## Monatlicher Abzug des Wertungsportals (Etappe 4)

Entscheidung 7.4 (Frank Binding, 18.11.2026): jeden Monat ein **neuer Abzug** aus dem Backend von
nu, die Aufbereitung (W1–W5) läuft erneut, dann der Import. Das Bundle liest nichts aus den
Spiegeltabellen. Ablauf:

1. Aufbereitung erzeugt `ausgabe/wp/` neu (andere Sitzung, `Aufbereitung/README.md`).
2. Den Ordner per FTP neben den alten legen (`wp.neu`), dann umbenennen: alten `wp` → `wp.alt`,
   `wp.neu` → `wp`. So zeigen die Turnierseiten nie auf halb übertragene Dateien.
3. Import und Gesamtpersonen:

```bash
vendor/bin/contao-console wertungsportal:archiv-import wp
```

```bash
vendor/bin/contao-console wertungsportal:archiv-personen
```

4. Läuft alles, `wp.alt` löschen.

Was der Import dabei tut:

| Was | Wie |
| --- | --- |
| Turniere, Personen | aktualisiert über `turnier` bzw. `person`; IDs und damit die Adressen `turniere/A<Nummer>` bleiben. Nichts wird gelöscht: Was der neue Abzug nicht mehr führt, meldet der Befehl („Nicht mehr in der Übergabe …“). |
| Namen | Ein Name ohne Buchstaben oder ein Hashname des Portals überschreibt keinen vorhandenen Namen (`Importeur::klarname()`, UEBERGABE.md 3). |
| Kartei | Die Kartei des Portals wird ganz ersetzt. Während des Imports (gemessen 13½ Minuten, davon 10 für die Kartei) fehlen auf den Karteikarten die Portalzeilen des Archivs — nachts laufen lassen. |
| Kanten | Jede Kante trägt in `gesehen` den Zeitpunkt des letzten Imports, der sie führte. Kanten des Portals (`N:`/`W:` → `D:`), die der neue Abzug nicht mehr führt, werden **„entfallen“** — sonst hielte eine geänderte Brücke (W:x → D:a, jetzt → D:b) zwei fremde Personen zusammen. Taucht sie wieder auf, gilt sie wieder als bestätigt. Von Hand abgelehnte Kanten bleiben abgelehnt. PKZ-Kanten (ELOBASE ↔ DeWIS) sind abgeschlossen und bleiben unberührt. |

Erst `archiv-personen` macht neue und entfallene Kanten in der Ausgabe wirksam. Im Backend steht eine
entfallene Kante grau und ohne Knopf. Die Spalte `gesehen` kam mit Schemafassung 4 — einmal
`wertungsportal:archiv-schema --force`.

## Stand

- Etappe 1 (Datenbank, Schema, Import): umgesetzt, lokal mit der vollen Übergabe geprüft.
- Etappe 2: Gesamtpersonen, Schalter in tl_member, Backend-Ansicht mit Ablehnen/Bestätigen von Kanten.
- Etappe 3: Karteikarte mit älteren Turnieren, Archiv-Karteikarte G<Nummer>, Archivtreffer in der
  Spielersuche, Archivturniere A<Nummer> und ELOBASE-Codes. Ergebnisse als Kreuztabelle oder
  Rundenansicht. Archivwerte im DWZ-Diagramm. Archivsymbol in der Spalte Nr./Quelle, Spalten E
  und Gegn. im Archivturnier; E/Gegnerschnitt/Leistung für ELOBASE warten auf Rückfrage R5.
- Etappe 4 (monatlicher Abzug des Wertungsportals): Import nachführend (entfallene Kanten, Klarnamen,
  Meldung übriger Zeilen). Offen auf Seiten der Aufbereitung: Rückfrage R4 (W1–W5 auf neuen Abzug).
