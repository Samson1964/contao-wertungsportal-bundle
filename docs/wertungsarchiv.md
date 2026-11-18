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

## Stand

- Etappe 1 (Datenbank, Schema, Import): umgesetzt, lokal mit der vollen Übergabe geprüft.
- Etappe 2: Gesamtpersonen gebildet, Schalter in tl_member; Backend-Ansicht der Zuordnungen offen.
- Etappe 3 (Ausgabe: Karteikarte, Turnierseiten, Suche): offen.
- Etappe 4 (monatlicher Abzug des Wertungsportals): offen.
