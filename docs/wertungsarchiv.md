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
| `archiv_kante` | Brücke zwischen den Archiven, vom jüngeren aufs ältere: `pkz` (DeWIS → ELOBASE), `turniere`/`externenr` (Portal → DeWIS), `dublette` | `von` + `nach` + `quelle` |
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
| Karteikarte `spieler/NU…` | Unter den Turnieren von nu die **älteren Turniere** aus ELOBASE und DeWIS, neueste zuerst — nur solche, die keine jüngere Quelle führt (ein DeWIS-Turnier entfällt, wenn das Portal es hat; ein ELOBASE-Turnier, wenn DeWIS es hat). Die „Datenübernahme nach DeWIS“ zeigt nu selbst. Erste Spalte: ZDB oder DeWIS. |
| Karteikarte `spieler/G<Nummer>` | Für Spieler **ohne NU-Nummer**, die nur noch im Archiv stehen: Name, Geburtsjahr, Archive, die ganze Kartei einschließlich der Portalzeilen aus dem Archiv. Mit NU-Nummer leitet die Adresse auf die normale Karteikarte weiter. |
| Spielersuche | Zweite Trefferliste „Aus dem Wertungsarchiv (heute nicht mehr gemeldet)“ — Gesamtpersonen ohne NU-Nummer, gesucht in allen Schreibweisen („Müller, Klaus“, „Müller,Klaus“, „Mueller,Klaus“). |
| Turnierseite `turniere/A<Nummer>` | Ein Turnier aus dem Archiv: Kopf, Teilnehmer mit DWZ vorher/nachher, Punkten, Partien, We, Leistung; Teilnehmer mit NU-Nummer verlinkt. Rekonstruktionen und Abweichungen von der Kartei sind vermerkt. |
| Turnierseite `turniere/A<Nummer>/Ergebnisse` | Partien: **Kreuztabelle**, wenn jeder gegen jeden gespielt hat (bis 30 Spieler, mindestens 90 % aller Paare — an den Partien entschieden, weil DeWIS als Rundenzahl oft nur 5 führt), sonst **Rundenansicht** mit Ergebnis und Gegner je Runde. Ohne Farben: Der gemeinsame Teil führt keine (Rückfrage R3). Ein leeres Feld heißt, die Turnierdatei nennt kein Ergebnis. „Erg.“ zählt wie in der Auswertung nur gewertete Partien. |
| DWZ-Diagramm (Karteikarte NU… und G<Nummer>) | Die Archivwerte stehen vor den Werten von nu auf **grauem Grund** (Legende „Wertungsarchiv“). Die DWZ-Linie läuft durch; Leistung nur, wo das Archiv eine Turnierleistung führt (DeWIS, Portal) — für Archivturniere wird **nichts geschätzt**, weder aus Gegnerschnitt und Punkten noch durch Überbrücken (`archiv` = true am Punkt, `Darstellung::diagrammpunkt()`). Ab 50 Punkten wie bisher die letzten 50 plus Gesamtdiagramm. Die G-Karteikarte ist ganz grau, sie hat nur Archivwerte. |
| Turnierseite `turniere/<ELOBASE-Code>` | Alte Verweise aus der Zeit vor DeWIS. DeWIS-Codes (auch die 2011–2013 in beiden Archiven) leiten wie seit 1.58.0 auf die jüngere Fassung bei nu. |

**Turniere vor der ersten Mitgliedschaft** (`vor_erster_anmeldung = 1`) stehen grau mit gestrichelter
Linie links da, Erklärung im `title` (Frank Binding, 18.11.2026: kennzeichnen, nicht ausblenden).

Einheiten werden nur für die Anzeige umgerechnet (`Wertungsarchiv\Darstellung`): Halbpunkte → „4½“,
We · 1000 → „7,215“. ELOBASE führt in der Kartei die **Erfolgszahl**, nicht die Turnierleistung — sie
erscheint nicht in der Spalte „Lstg.“. Ein Fehler der Archivdatenbank kippt keine Seite: Er kommt ins
Systemprotokoll, die Seite erscheint ohne Archiv.

## Stand

- Etappe 1 (Datenbank, Schema, Import): umgesetzt, lokal mit der vollen Übergabe geprüft.
- Etappe 2: Gesamtpersonen, Schalter in tl_member, Backend-Ansicht mit Ablehnen/Bestätigen von Kanten.
- Etappe 3: Karteikarte mit älteren Turnieren, Archiv-Karteikarte G<Nummer>, Archivtreffer in der
  Spielersuche, Archivturniere A<Nummer> und ELOBASE-Codes. Ergebnisse als Kreuztabelle oder
  Rundenansicht. Archivwerte im DWZ-Diagramm.
- Etappe 4 (monatlicher Abzug des Wertungsportals): offen.
