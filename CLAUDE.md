# contao-wertungsportal-bundle — Projektkontext für Claude

Contao-4.13-Bundle (`schachbulle/contao-wertungsportal-bundle`), das das DWZ-Wertungsportal
des Deutschen Schachbunds (nu/liga.nu-API) in Contao integriert: Frontend-Suchen (Spieler,
Verein, Verband, Turnier) und lokale Spiegel-Tabellen mit Backend-Verwaltung.
Nachfolger des alten contao-dewis-bundles; Migrationsstatus siehe TODO.md, Historie siehe CHANGELOG.md.

## REST-Schnittstelle

Dokumentation unter https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resources.html
(DSBPersonREST, DSBTournamentREST und DWZListeREST sind für das Wertungsportal relevant)

## Neue Funktionen 20.07.2026 (Upload + Livetest offen)

- **Blacklist**: tl_wertungsportal_persons hat blocked/grund/melder (analog tl_dwz_spi).
  Zentrale Prüfung `Helper::getBlacklist($nuIds)` (Bulk, Request-Cache) / `Helper::istGeblockt($id)`.
  Listen (Spielersuche, Vereinsliste, Verbandsrangliste) lassen gesperrte Zeilen weg;
  Karteikarte/eigenes Scoresheet zeigen neutrale Meldung („nicht verfügbar");
  Turniertabellen behalten die Zeile ohne Personenbezug (Name „gesperrt", keine Links).
- **Turnierergebnisse**: Rundenzahl = max(round) aus den Matches (nu-Metadaten falsch);
  Ergebnisse je Runde werden angehängt statt überschrieben (mehrere Partien je Runde),
  Template rendert alle Einträge einer Rundenzelle.
- **Altdaten-Übernahmen** (`Classes/AltdatenImport.php`, Ergebnis-Template be_wp_altdaten):
  WP | Vereine → „Altdaten übernehmen" (key=importDwzVer): altname/Logo/Homepage/Info aus
  tl_dwz_ver per VKZ; WP | Personen → „Bilder übernehmen" (key=importPhotos): Bild aus
  tl_dwz_spi per externeNr=dewisID. Beide füllen nur LEERE Zielfelder (idempotent).
  Frontend: Verein.php liest die neuen Club-Felder mit Fallback tl_dwz_ver; die
  Karteikarte nutzt das Personenbild mit Fallback tl_dwz_spi über die externeNr.
- **Karteikarte**: Verbandszugehörigkeiten je Mitgliedschaft (`Helper::getVerbandskette()`,
  Kette über clubs.federation aufwärts, DSB hart als Wurzel wie in der Verbandsnavigation);
  DWZ/Leistungs-Diagramm als serverseitiges SVG (`Karteikarte::erstelleDiagramm()`,
  kein Chart.js — der tote contaodewis-Verweis wurde aus dem Template entfernt).
- **CSV-Import**: tstamp der importierten Datensätze = Datum aus dem Dateinamen
  (JJJJMMTTHHIISS, Fallback Jetzt-Zeit); Importdaten überschreiben Bestand IMMER
  (kein tstamp-Vergleich). Regeln in TODO.md dokumentiert.
- Deployment: neue DB-Felder in persons (addImage, singleSRC, blocked, grund, melder)
  und clubs (altname, addImage, singleSRC, info, homepage) → contao:migrate/Install-Tool!
  Die beiden Übernahme-Operationen müssen einmalig manuell ausgeführt werden.

## Neue Funktionen 20.07.2026, Runde 2 (Upload + Livetest offen)

- **Diagramm-Ausbau** (`Karteikarte::erstelleDiagramm($punkte, $breite = 700)`):
  Leistungslücken (unter 5 Partien keine Leistung von nu) werden geschätzt —
  primär aus Turnierdaten (Gegnerschnitt + 800 × Score-Anteil − 400), Rest linear
  interpoliert; geschätzte Punkte hohl + Tooltip „(geschätzt)". Jahreslabels 45°
  gedreht. Bei >50 Turnieren: Karteikarte zeigt die letzten 50, Komplettdiagramm
  (Breite = 14px/Turnier, Legende links) im eigenen Overlay (Link darunter;
  CSS-Klassen dwz-diagramm-overlay* in public/css/default.css → assets:install).
- **WP | FIDE-Elo** (tl_wertungsportal_elo, BE_MOD wp-elo, WertungsportalEloModel):
  Feldbestand wie tl_dwz_elo (surname/prename/intent aus FIDE-„name" per Komma
  gesplittet), Indizes nur fideid/surname/published. Globale Operation
  „XML-Import" (key=importElo, `Classes/EloImport.php`, Template
  be_wp_eloimport): Chunk-Upload, Zip wird per Magic-Bytes erkannt und streamend
  entpackt, dann schrittweiser Import per Byte-Offset (Puffer-Parser auf
  '<player>'-Blöcke — exakt '<player>' matchen, sonst greift '<playerslist>'!),
  2000 Spieler/Schritt, Upsert per fideid (nur Änderungen schreiben, keine
  Löschung), elodate/tstamp = vom Client einmalig erzeugter Lauf-Timestamp.
  elodate ist vom Änderungsvergleich AUSGENOMMEN (wandert nur bei echten
  Änderungen mit), sonst würde jeder Folgelauf alle Zeilen updaten.
  Mit der echten players_list_xml.zip lokal verifiziert (20.07.2026): Eintrag
  players_list_xml_foa.xml, 835 MB XML, 1.887.095 Spieler in 945 Schritten
  fehlerfrei geparst (0 Skips/Duplikate/Kürzungen, Ø 0,03 s/Schritt Parsing).
  Zip-Upload empfohlen (~50 MB statt 835 MB); system/tmp braucht temporär ~900 MB.
- **FIDE-Anreicherung** liest jetzt tl_wertungsportal_elo (Helper::
  getFIDEDatenLokal/getFIDEDatenListe, API::getFIDE, Wertungsportal_Converter.php).
  WICHTIG: Ohne einmaligen XML-Import ist die neue Tabelle leer → Elo/Titel
  verschwinden aus den Frontend-Ausgaben, bis der Import gelaufen ist.
- **tl_dwz-DCAs entfernt** (9 dca- + 8 de-Sprachdateien) + BE_MOD dwz-*-Einträge:
  Das contao-dewis-bundle läuft übergangsweise PARALLEL und verwaltet die
  tl_dwz_*-Tabellen selbst. DwzSpiModel/DwzVerModel bleiben registriert
  (Altdaten-Übernahmen, Foto-/Logo-Fallbacks lesen die Tabellen weiter).
  Tote Helper::Blacklist() entfernt. ACHTUNG Deployment: die gelöschten Dateien
  müssen auf dem Server separat entfernt werden (Liste in TODO.md)!
- Deployment Runde 2: contao:migrate (tl_wertungsportal_elo) + assets:install.

## Struktur und Konventionen

- Namespace: `Schachbulle\ContaoWertungsportalBundle`, PSR-4 auf `src/` (composer.json).
  Models liegen in `src/Models` (Plural!), Frontend-Module in `src/Classes`, API/Logik in `src/Helper`.
- Die Module in `src/Classes` sind dünn (Parameter, API-Aufrufe, Fehler-Guards, Bilder,
  Template-Zuweisung); die Datenaufbereitung liegt in Formatter-Klassen in `src/Helper`
  (Konstruktor nimmt API-Result(s), `compile()` befüllt `$daten`, Zugriff per magischem
  `__get`): Spielersuche, Karteikarte, Vereinssuche, Vereinsliste, Verbandsnavigation,
  Verbandsrangliste, Turnierformular, Turniersuche, Scoresheet, Turnierergebnisse,
  Turnierauswertung.
- Die toten Archiv-Kopien `OAuth2Client_v1.php`/`_v2.php` wurden am 17.07.2026 lokal UND
  auf dem Server gelöscht. Aktiv ist nur `src/Helper/OAuth2Client.php`. Merkregel fürs
  Deployment: Das manuelle Ordner-Kopieren entfernt keine Dateien — gelöschte Dateien
  müssen auf dem Server separat entfernt werden.
- Contao-Ressourcen unter `src/Resources/contao/` (dca, languages/de + languages/en, templates, config).
- Sprachdateien: Deutsch ist Hauptsprache, Englisch als Fallback immer mitpflegen.
- Codestil Bestandscode: Tabs, Klammern auf eigener Zeile, FQCN statt use-Imports, `array()`-Syntax.
  Neuere Dateien (Models, neue DCAs) nutzen kurze Array-Syntax und 4 Leerzeichen — jeweils an die Datei anpassen.
- DCA-Konventionen für die `tl_wertungsportal_*`-Tabellen:
  - Immer Felder `id`, `tstamp`, `published` (toggle) und Buttons edit, copy, delete, toggle, show.
  - Parent-Tabellen mit Kindtabelle: Operationen `edit` (href `table=<kindtabelle>`) + `editheader`
    (`act=edit`, Werkzeug-Icon, steht an ERSTER Stelle) — NICHT die neuere `children`-Operation
    (kein children.svg in dieser 4.13-Installation).
  - Elternansichten (MODE_PARENT): `headerFields`, `disableGrouping => true` (keine Zwischenüberschriften),
    `child_record_callback` mit grauem Zusatztext in `<span class="wp-meta">`.
  - Operations-Icons (16x16) in `src/Resources/public/images/`: icon_vereine.png (Mitgliedschaften),
    history.png (Turnierhistorie), chart.png (Hochstufungen), rating2.png (DWZ-Auswertung), games.png (Partien).
  - Unveröffentlichte Datensätze werden per CSS `:has()` in `public/css/backend.css` rot dargestellt
    (reagiert live auf den Ajax-Toggler; Selektoren pro Backend-Modul do=wp-clubs/wp-persons/wp-tournaments).

## Tabellen und Models

| Tabelle | Model | Inhalt / Schlüssel |
|---|---|---|
| tl_wertungsportal_clubs | WertungsportalClubsModel | Vereine, Upsert per clubVkz |
| tl_wertungsportal_persons | WertungsportalPersonsModel | Personen, Upsert per uuid, Fallback nuLigaPersonId |
| tl_wertungsportal_persons_memberships | WertungsportalPersonsMembershipsModel | Kind von persons, Sync per pid+vkz, legt Vereine mit an |
| tl_wertungsportal_persons_tournaments | WertungsportalPersonsTournamentsModel | Turnierhistorie, Sync per pid+tournamentUuid, upsertEntry() |
| tl_wertungsportal_persons_upgrades | WertungsportalPersonsUpgradesModel | DWZ-Hochstufungen, Sync per pid+referenceDate+name |
| tl_wertungsportal_tournaments | WertungsportalTournamentsModel | Turniere zentral, Upsert per uuid |
| tl_wertungsportal_tournaments_evaluation | WertungsportalTournamentsEvaluationModel | DWZ-Auswertung (Spieler-DTOs), upsertPlayer() ist zentrale Drehscheibe |
| tl_wertungsportal_tournaments_matches | WertungsportalTournamentsMatchesModel | Partien, redundanzfrei nur white/blackPlayerUuid |

Registrierung (TL_MODELS, BE_MOD wp-clubs/wp-persons/wp-tournaments) in `src/Resources/contao/config/config.php`.

## Sync-Architektur (API.php)

Jeder API-Case in `Helper/API.php::getAPI()` ruft nach `callApiWithRefresh()` einen Abgleich auf:

- Spielerliste, Karteikarte, Vereinsliste, Verbandsliste → `syncPersons()` (Personen + Mitgliedschaften; Mitgliedschaften legen Vereine per VKZ mit an)
- Vereinsname, Verbaende → `syncClubs()`
- Karteikarte_Turniere (`/dwz/persons/{id}/history`) → `syncPersonHistory()` (Person, Mitgliedschaften, Turnierhistorie, Turniere, Hochstufungen)
- Turnierinfo, Turnierliste → `syncTournaments()`
- Turnierauswertung → `syncTournamentEvaluation()` (mit Löschung nicht mehr gemeldeter Spieler)
- Turnierergebnisse → `syncTournamentMatches()` (KEINE Löschung — paginiert)
- Spielberichtsbogen → `syncScoresheet()` (Upsert-only; keine eigene Tabelle)

`EvaluationModel::upsertPlayer()` aktualisiert dabei automatisch auch tl_wertungsportal_persons
(per nuLigaPersonId, nur Identitätsfelder) und die Turnierhistorie der Person.

WICHTIG: `autoQuery()` cached (24 h, `wertungsportal_cache`). Bei Cache-Treffer läuft KEIN Sync.
Fehlgeschlagene Abfragen (HTTP != 200) werden nicht gecached.

## Örtlicher Datenbestand und Zugriffs-Log (ab 1.8.0)

`Helper/Lokal.php` beantwortet alle zwölf API-Funktionen aus den Spiegeltabellen
— dritte Stufe nach dem Zwischenspeicher (Aufruf in `API::notdaten()`).
Reihenfolge: gültiger Cache → abgelaufener Cache → `Lokal::abfrage()` → Meldung.

- Jede Methode baut die Antwort **in der Feldform der Schnittstelle** (die
  Spaltennamen der Spiegeltabellen entsprechen ihr 1:1). Die Formatter im
  Frontend bleiben dadurch unberührt.
- Partien liegen redundanzfrei (nur Spieler-UUIDs); `partien()` führt sie mit
  der Auswertungstabelle zusammen. Fehlt ein Spieler dort, greift der in der
  Partie gespeicherte Name; eine Partie ohne Gegner bleibt `null`.
- **Laufzeitfalle Rangliste:** Mitgliedschaft per `EXISTS` (kein JOIN mit
  DISTINCT) und `ORDER BY p.rating DESC` OHNE zweiten Sortierschlüssel, sonst
  ist der Index (published, rating) für die Ordnung nutzlos und MySQL sortiert
  95.000 Zeilen nach (972 ms statt 72 ms). Gleichstand wird in PHP
  nachsortiert. Die Spalte `index` darf NICHT in einen DCA-Index — reserviertes
  MySQL-Wort.
- Reihenfolge der Platzhalter beachten: Personen-Bedingungen stehen vor dem
  EXISTS-Teil, das Vergleichsdatum der Mitgliedschaftsprüfung wird deshalb
  ANGEHÄNGT, nicht vorangestellt.
- Kein eigener Zwischenspeicher für örtliche Antworten (gemessen 0,2–72 ms;
  Begründung in TODO.md).

`Helper/Zugriffslog.php` schreibt bei eingeschalteter Einstellung
`wertungsportal_zugriffslog` je Abfrage eine CSV-Zeile nach `var/logs`
(eine Datei je Tag). Die Zeitnahme sitzt in `API::autoQuery` (Gesamtdauer) und
`OAuth2Client::callApiWithRefresh` (reiner Aufruf, statischer Zähler
`aufrufe()` unterscheidet „war bei der Schnittstelle" von „kam aus dem
Cache"). Enthält die IP — datenschutzrelevant.

## Notbetrieb ohne Schnittstelle (ab 1.7.0)

Zwei Einstellungen steuern das Verhalten bei nicht verfügbarer Schnittstelle:
`wertungsportal_api_aus` (Schalter) und `wertungsportal_api_timeout` (Sekunden,
Standard `API::TIMEOUT_STANDARD` = 30, gelesen über `API::timeout()`).

`autoQuery()` greift in beiden Fällen über `notdaten()` auf den Zwischenspeicher
zurück — **ohne Ablaufprüfung** (`Cache::isCached($key, true)` /
`retrieve($key, false, true)`, Helper-Bundle ab 1.8.10). Regeln:

- **Nur HTTP-Code 0** (keine Antwort) löst den Notbetrieb aus. Inhaltliche
  Fehler der Schnittstelle (404, 500 …) bleiben Fehler.
- Nach einem gescheiterten Abruf bekommt der Eintrag eine **Notfrist**
  (`API::NOTFRIST` = 300 s), sonst wartet jeder Seitenaufruf erneut die volle
  Zeit. Der ursprüngliche Speicherzeitpunkt wandert als `notstand` im Payload
  mit, damit der Hinweis das echte Alter nennt — auch wenn der Eintrag durch
  die Notfrist wieder „gültig" aussieht und über den normalen Weg (`ausCache`)
  gelesen wird.
- Bei abgeschalteter Schnittstelle wird **nicht** umdatiert (es gibt keine
  Wartezeit abzufedern).
- Ohne Notreserve kommt eine Fehlerantwort mit `keine_livedaten => true`;
  dann wird bewusst NICHTS in `$notdaten` vermerkt, sonst stünde die Meldung
  doppelt auf der Seite (Hinweis + Fehler-Slot).
- `eraseExpired()` darf in `autoQuery()` nicht wieder eingebaut werden — es
  löscht genau die Notreserve.
- Ausgabe: `Helper::cacheHinweis()` (Notbetrieb hat Vorrang vor dem
  Cache-Hinweis) und `Helper::apiFehler()` für den Fehler-Slot der Module.

## Suchaliase (ab 1.6.0)

Vier Aliasfelder tragen die umlautunabhängige Suche: `persons.firstnameAlias`/
`lastnameAlias`, `clubs.clubNameAlias`, `tournaments.labelAlias`. Erzeugt werden
sie von `Helper::alias()` über den Slug-Generator mit deutschem Sprachraum
(`contao.slug.generator`, NICHT `contao.slug` — der stellt rein numerischen
Werten ein „id-" voran und zöge ohne Optionen die Einstellungen einer Seite
heran). Regeln:

- **Symmetrie ist Pflicht:** Suchbegriff UND gespeicherter Wert laufen durch
  dieselbe Funktion. Wer nur eine Seite umwandelt, bekommt keine Treffer.
- Ein Text ohne verwertbare Zeichen (der nu-Turniername „-") ergibt den
  Platzhalter `'-'`, ein leerer Text `''`. Suchen brechen bei beiden ab, statt
  den ganzen Bestand auszugeben.
- **Pflege:** Zuordnung Quellfeld → Aliasfeld steht als Konstante
  `ALIAS_FELDER` im jeweiligen Model; `ApiSyncTrait::aliasFelder()` (Bulk-Wege,
  IMMER nach `diffApiFields`, nie davor) und `applyApiFields()` (Einzel-Upserts)
  führen sie mit. `fehlendeAliase()` zieht fehlende Aliase des Altbestands beim
  nächsten Abgleich nach. Neu erzeugt wird ein Alias nur bei geändertem Namen —
  sonst liefe die Erzeugung (~0,08 ms) bei jedem Abgleich für jeden Datensatz.
- **Bestand:** `Migration/AliasMigration.php` füllt beim `contao:migrate`
  blockweise nach (20 s Zeitbudget, resümierbar; 95.000 Personen ≈ 15 s).
  Wer INSERT-Spaltenlisten in den Models anfasst, muss die Aliasspalten
  mitführen — sie stehen dort als zusätzliche Positionen im Werte-Tupel.

## Fallstricke / Besonderheiten

- **Identifier**: `nuLigaPersonId` identifiziert eine Person systemweit. Die `playerUuid` in
  Turnier-DTOs gilt NUR innerhalb des jeweiligen Turniers — nie als Personen-UUID speichern!
  Personen-`uuid` (aus /dwz/dwzliste/persons) ist eine dritte, eigene Kennung.
- **`index`** (Spalte in tl_wertungsportal_persons) ist ein reserviertes MySQL-Wort:
  kein sorting/filter im DCA, keine eigenen Finder wie findByIndex() bauen. Model-save() ist sicher.
- **birthyear** in tl_wertungsportal_persons ist varchar(10): Jahr (JJJJ) ODER volles Datum.
  API-Syncs nutzen `applyBirthyear()` und überschreiben ein manuell gepflegtes Datum nicht,
  solange das Jahr übereinstimmt.
- **Optionale API-Felder**: Die nu-API lässt Felder einfach weg (z. B. wins, lastCalculated bei nie
  berechneten Turnieren). Für Spieler-DTOs `Helper::PlayerDefaults()` verwenden, für Datumsfelder
  `Helper::ApiDatum()` — NIE direkt `\DateTime::createFromFormat(...)->format(...)` auf API-Werte.
- **API-Fehler im Frontend**: Turnier.php gibt API-Fehler über `templateFehler()` +
  `$this->fehler` in den Templates aus (kein Redirect auf die 404-Seite).
- Frontend-Links der Module sind roh per sprintf gebaut — im Contao-Vorschaumodus fehlt ihnen
  das `preview.php`-Präfix (im veröffentlichten Zustand egal).
- Nach Änderungen an `src/Resources/public/` auf dem Server `contao:assets:install` nötig,
  nach DCA-/SQL-Änderungen `contao:migrate` bzw. Install-Tool.

## Testumgebung

- Backend: https://development.schachbund.org/contao (Login hat der User; Module unter „Wertungsportal": WP | Vereine, WP | Personen, WP | Turniere)
- Frontend-Vorschau: https://development.schachbund.org/preview.php/wertungsportal.html
  (Links Spieler/Verein/Turnier/Verband; Aliase spieler-2, verein-2, turnier, verband-2)
- Die nu-Testumgebung hat wenig Turnierdaten (praktisch nur „Test GER",
  uuid 381efcec-11f4-4fb5-b2d5-051bfcdbaf07, ohne Auswertung/lastCalculated) —
  gut zum Testen der Fehlerpfade, schlecht für Auswertungs-/Partien-Syncs mit echten Daten.
- Deployment: Der User kopiert den Ordner manuell auf den Server; Claude kann per
  Chrome-Erweiterung im eingeloggten Browser testen, aber nichts deployen.

## Download-Skripte (Hoster-Cronjob)

`src/Resources/public/Wertungsportal_Download.php` und `Wertungsportal_Converter.php` sind
eigenständige Einstiegsskripte (alter Stil: TL_MODE + system/initialize.php), die der Hoster
per Cronjob über Curl aufruft — unabhängig voneinander, vom User erfolgreich getestet.
Download archiviert alle 20 LV-Zips datiert nach `files/wertungsportal/`; Converter lädt LV-0,
reichert spieler.csv mit FIDE-Daten aus tl_dwz_elo an (Elo/Titel/Land ersetzt, Namens-/
Geschlechts-/Geburtsjahr-Abweichungen nur geloggt), packt je Verband CSV-Zips ins Jahresarchiv,
kopiert aktuelle Fassungen nach `export/csv/` und pflegt die Dbafs. Nur CSV, keine SQL-Variante.
Die README.txt in den Verbands-Zips wird per `writeReadme()` angepasst (Überschrift
"Landesverband: X - Name" aus verbaende.csv + Spieler-/Vereinszahlen des Verbands);
Vorsicht: Die nu-Dateien sind windows-1252-kodiert, die Anpassung arbeitet bewusst
byte-basiert ohne Encoding-Konvertierung, CRLF-Zeilenenden bleiben erhalten.
Beide Skripte sind per Token geschützt: Aufruf nur mit `?key=<wertungsportal_crontoken>`
(Contao-Einstellungen, Bereich Wertungsportal); ohne konfigurierten Token gesperrt (403).
Verifiziert am 17.07.2026: ohne/mit falschem Key HTTP 403, mit Key liefen beide Skripte
komplett durch (der nu-Server lieferte dabei zeitweise abgeschnittene Zips — Anlass für die
Download-Absicherung). Beide Skripte laden seitdem über `Helper::DownloadDatei()`:
prüft Curl-Fehler, HTTP-Status 200 und Zip-Konsistenz (CHECKCONS), wiederholt bis zu 3-mal
(5 s Pause) und löscht defekte Dateien statt sie zu archivieren. Das Download-Skript meldet
Fehlschläge im Abschlusstext ("Fertig mit x Fehlschlägen"), der Converter bricht bei
endgültigem Fehlschlag mit FEHLER-Meldung ab. TLS-Verifikation ist weiterhin deaktiviert
(Bestandsverhalten beibehalten).

## Personen-Import (Vereinsmitglieder-CSV)

Globale Operation „CSV-Import" unter WP | Personen (`key=importPersons`, Controller
`Classes/PersonenImport.php`, Template `be_wp_personenimport.html5`). Ablauf komplett in
AJAX-Schritten gegen Timeouts: Chunk-Upload (2-MB-Blöcke) in `system/tmp/wp-personenimport-
{userId}.csv`, dann Import in Paketen à 1000 Zeilen — der Client reicht den Byte-Offset
weiter, jeder Schritt öffnet die Datei neu, liest die Kopfzeile (Spaltenzuordnung über
NAMEN, reihenfolgeunabhängig; BOM-tolerant) und macht per fseek weiter. Bulk-Upsert über
`PersonsModel::importCsvRows()` (Schlüssel nuLigaPersonId = CSV-Spalte InterneNr; nur bei
Änderungen schreiben; published nur beim Anlegen). Quelldatei-Analyse (17.07.2026):
99.529 Zeilen / 95.819 eindeutige Personen, ExterneNr 1:1 zur InterneNr, UTF-8, Semikolon.
Der Import übernimmt je Zeile auch die Vereinsmitgliedschaft in die Kindtabelle
(`MembershipsModel::importCsvRows`): Match über **VKZ + Mitgliedsnummer** (Verbund-Index;
Analyse: 99.310 eindeutige Paare, KEINE Kollisionen), Update nur bei Änderungen inkl.
pid-Umverknüpfung, Insert sonst, keine Löschung; Vereine werden per `ClubsModel::syncList`
mit angelegt (2.168 Vereine in der Datei). Spielgenehmigung → licenceState-Mapping:
Aktiv/Passiv/Sondermitgliedschaft/ohne Spielgenehmigung → ACTIVE/PASSIVE/SONDER/OHNE
(SONDER/OHNE sind neue Optionen); SpielgenehmigungVon/Bis → neue Felder.
Bewusste Entscheidungen: Geschlecht m/w/d/unbekannt → MALE/FEMALE/DIVERSE/'';
Geburtsdatum voll nach birthyear (API-Sync überschreibt es dank applyBirthyear-Jahresregel
nicht); Mehrfachzeilen einer Person sind laut Analyse identisch, die erste gewinnt;
218 Zeilen ohne VereinNr/Mitgliedernummer ergeben nur Personen ohne Mitgliedschafts-Import.
ACHTUNG möglicher Ping-Pong: Die CSV nennt den Verband anders als die nu-API
(z. B. "LVA Schleswig-Holstein" vs. "SVB Schleswig-Holstein") — API-Sync und CSV-Import
überschreiben federationName daher wechselseitig (je 1 Update pro Lauf, unkritisch).
`externeNr` entspricht größtenteils der alten dewisID (tl_dwz_spi) — Matching ist
geplanter Folgeschritt (Stand 18.07.2026): Zweck ist zunächst das SPIELERBILD aus
tl_dwz_spi — die Karteikarte sucht heute per `DwzSpiModel::findOneBy('dewisID', NuId)`,
also mit der NU-Nummer gegen die alte DeWIS-Nummer → kann praktisch nie treffen; künftig
stattdessen über die externeNr der Person matchen. Später evtl. Zusammenführung beider
Tabellen — dafür braucht tl_dwz_spi aber erst Echtdaten (aktuell nicht vorhanden).
WICHTIG: Die AJAX-POSTs des Imports dürfen KEINEN X-Requested-With-Header tragen —
Contaos eigenes Ajax-System fängt solche Requests sonst mit "Missing Ajax action" ab,
bevor der key-Callback läuft (im Livetest gefunden und im Template gefixt).
Livetest 18.07.2026 (Endpunkte mit der echten 26,5-MB-Datei über die Backend-Session
getrieben; das Datei-Upload-Tool des Browsers ließ nur <10 MB zu, daher per JS-Fetch):
Lauf 1 in 85 s / 100 Schritte — 94.101 Personen neu, 1.718 aktualisiert (die vorhandenen
API-Personen, z. B. Aagaard ID 67 mit erhaltener uuid/DWZ), 97.627 Mitgliedschaften neu,
1.683 aktualisiert (VKZ+Mitgliedsnummer-Match auf die API-Datensätze, z. B. ID 74 mit
neuem spielgenehmigungVon); Lauf 2 (Idempotenz) in 64 s — 0 Writes, alles unverändert.
Backend geprüft: Suche nach Externer Nummer, neue Filter (DIVERSE/Verstorben/Nation),
volle Adress-/Datenschutzfelder am Datensatz. Die Import-UI selbst (Dateifeld + Template-JS)
sollte der User einmal mit der echten Datei gegentesten.

## Offene Punkte (Stand 17.07.2026, nach Sync-Optimierung)

0. **[ERLEDIGT + live verifiziert 17.07.2026] Refactoring der Frontend-Module**:
   Datenaufbereitung der vier Module in eigene Formatter-Klassen ausgelagert (7 neue Dateien
   in src/Helper, Module von 346/472/292/296 auf 208/335/141/188 Zeilen geschrumpft).
   Nebenbei: Blacklist-Totabfragen entfernt, log_message hinter wertungsportal_debuglog,
   Vereinssuche-Validierung nur bei Eingabe, Karteikarte mit API-Fehler-Guard, Turnier lädt
   die Verbandsliste nur noch für das Formular. Retest aller Ansichten bestanden:
   Spielersuche (2 Treffer inkl. GM), PKZ-Guard, Karteikarte (73 Zeilen + Foto),
   Vereins-Formular (kein Fehlhinweis mehr), Vereinssuche, Vereinsliste Rang+Alpha mit
   Umschalt-Links, Verbandsformular inkl. Navigation, Verbandsrangliste (Carlsen-Zeile
   identisch; nu-Timeout-Fehlerpfad sauber), Turnierformular (196 Verbands-Optionen),
   Turniersuche, Auswertung, Ergebnisse (Kreuztabelle), Scoresheet (Summen identisch),
   Fehlerpfad "No evaluation found".

1. **[BEHOBEN + live verifiziert] Spielersuche „Nachname, Vorname"**: „Müller, Karsten" → jetzt
   2 Treffer inkl. GM (vorher 0), mit und ohne Leerzeichen; Einzelname („Müller" → 686) und
   Formulareingabe („Faber, Lutz" → 1) unverändert korrekt. Fix: Analyse auf Rohstring (Komma
   bleibt erhalten), erst DANACH Nachname/Vorname einzeln sluggen.
2. **[BEHOBEN, Upload+Livetest offen] 500er bei PKZ-/ZPS-Suche**: Numerische Eingabe im
   Spieler-Suchfeld (z. B. „12345") → HTTP 500 „Undefined variable $param" (Spieler.php:117,
   Altfehler): Für typ=pkz/zps wurde nie ein $param gebaut, autoQuery crashte. Fix umgesetzt:
   Guard `isset($param)` + Hinweis über den fehler-Slot des Suchformulars („Suche nach
   Mitglieds-/ZPS-Nummern wird nicht unterstützt"). Nach Upload testen: spieler-2.html?search=12345
   → Hinweis statt Fehlerseite.
3. **[BEHOBEN + live verifiziert] Per-Datensatz-Syncs batched („Fund 2")**: Personen-,
   Mitgliedschafts-, Historien-, Auswertungs- und Partien-Syncs laufen als Bulk
   (neue Methoden: `PersonsModel::syncList`/`syncFromPlayerDtos`,
   `MembershipsModel::syncForPersons`, `PersonsTournamentsModel::syncEntries`,
   `EvaluationModel::syncPlayers`; `MatchesModel::syncForTournament` umgebaut, API.php reicht
   die Turnier-UUID durch). Messwerte nach Upload (17.07.2026): Turnierergebnisse Fordopen
   2789 → **59** Queries (DB 860 → 45 ms), Vereinsliste Hamburger SK 7521 → **457** Queries
   (DB 2794 → 66 ms), Auswertung 115, Scoresheet 59, Spielersuche 55. Inhalte identisch
   (Kreuztabelle, Rangliste, Karteikarte 73 Zeilen); Backend-Integrität geprüft (K. Müller:
   beide Mitgliedschaften korrekt). `upsertPlayer`/`upsertByVkz`/`upsertEntry` bleiben für
   Einzelfälle erhalten (Karteikarten-Historie läuft weiter per Datensatz — 211 Queries, ok).
4. **[BEHOBEN + live verifiziert] FIDE-Anreicherung gebündelt**: Neue
   `Helper::getFIDEDatenListe()` (eine IN-Abfrage auf tl_dwz_elo, gechunkt à 500) ersetzt die
   Einzel-SELECTs je Spieler in `setFIDEDaten` (Spieler-/Vereinsliste), `Verband.php`
   (Verbandsrangliste, vorher API::getFIDE je Spieler) und `Turnierauswertung.php`.
   `getFIDEDatenLokal`/`API::getFIDE` bleiben für Einzelabrufe (Karteikarte) erhalten.
   Messwerte (17.07.2026): Vereinsliste Hamburger SK 457 → **61** Queries (Gesamtbilanz seit
   Beginn: 7521 → 61), Turnierauswertung Fordopen 115 → **58**, Verbandsrangliste DSB Top 10
   Steady-State 58, Spielersuche 54. FIDE-Spalten (Elo/Titel) überall unverändert korrekt
   (Sarin/Carlsen/K. Müller gegengeprüft). ~55 Queries sind der Contao-Seiten-Grundstock.
   Rest-Hinweis: Ladezeit großer Listen ist der nu-API-Abruf selbst (heute auch mal 30-s-Timeout
   der nu-API beobachtet — Seite zeigt sauber die cURL-Fehlermeldung, Retry klappt); mit
   aktivem wertungsportal_cache (Produktion) nur beim Erstaufruf.
3. **[BEHOBEN + live verifiziert 17.07.2026] Vorschaumodus-Links**: Modul-Links und
   Formular-Actions werden über neue Helper-Methoden `get*seiteUrl()` gebaut (Router-generierte
   URL ohne .html-Suffix, im Vorschaumodus automatisch mit preview.php-Präfix, pro Request
   gecacht). Verifiziert: Ergebnisse→Auswertung-Klick, Spielername→Karteikarte und
   Scoresheet-Links behalten preview.php; Spieler- und Turniersuche-Submits bleiben in der
   Vorschau (Turniersuche 4409 Treffer); Normalmodus-Regression ok (Alias-Links, Klick und
   Formular-Submit funktionieren unverändert). Die Alias-Getter `get*seite()` bleiben BEWUSST
   für die URL-Fragment-Vergleiche und die relativen Location-Redirects in API.php/Verein.php
   (relative Location-Header funktionieren in beiden Modi — NICHT auf die Url-Variante
   umstellen, sonst doppeltes preview.php-Präfix!).
   Erledigt außerdem: Punkt „Erg. 0/" aus früherem Test war Testdaten-Artefakt (nie
   berechnetes Test GER); mit echten Auswertungsdaten rendert alles korrekt.

## Bereits verifiziert (Browsertests 17.07.2026)

Backend-Module inkl. Icons/Reihenfolge, Rotfärbung beim Toggler (Liste + Elternansicht, live in
beide Richtungen), Mitgliedschafts-Elternansicht (Kopfdaten, keine Zwischenüberschriften),
Syncs für Spielerliste, Karteikarte(+Historie), Verbändeliste und Vereinsliste (65 Spieler
SK Ladenburg → Personentabelle), Karteikarten-Fix (wins), Fehlerausgabe statt 404-Umleitung
bei Turnierauswertung.

Retest nach PlayerDefaults-Upload (17.07.2026): Turnierergebnisse-Seite rendert vollständig
(9 Spieler, Rundenspalten, kein 500er), Turnierauswertung zeigt die API-Fehlermeldung
("No evaluation found"), Scoresheet funktioniert, leere Turniersuche über 19 Monate liefert
178 Treffer ohne Fehlerseite (~3,3 s, 5184 Queries — vor der Sync-Optimierung).

Eingabe-Testrunde Frontend + Backend (17.07.2026, Chrome, eingeloggt als Webmaster):
- Spielersuche "Faber" → 10 Treffer; Karteikarte (Lutz Faber) rendert vollständig.
- Vereinssuche "Zwickau" → 1 Verband + 2 Vereine; Eingabevalidierung greift
  ("Zwickau<script>" wird abgewiesen, kein Redirect/XSS).
- Verbandsliste: Top 10 männlich (10 m), Top 10 weiblich (10 w), Altersfilter 0–18 —
  Filter korrekt angewandt (Geschlecht-Radio-Werte sind m/f, NICHT m/w).
- Turniersuche Stichwort "Senioren" → 3 Treffer (case-insensitiv).
- Backend WP|Personen: Suche per FIDE-ID → 1 Treffer; Publish-Toggle färbt Zeile live rot
  und zurück; Mitgliedschafts-Elternansicht zeigt Kopfdaten + Verein.
- Backend WP|Turniere: Liste durch die Frontend-Suchen gefüllt (bestätigt Batch-Insert-Sync
  mit Echtdaten end-to-end); Auswertungsstatus-Filter NOT_RATED → 15 Treffer.
- Keine Bundle-Fehler gefunden. Nebenbeobachtungen: das FE-Suchformular verliert beim ersten
  Submit direkt nach Seitenladung gelegentlich die Eingabe (Automations-Timing, kein Bug, 2.
  Versuch klappt stets); ein nu-Turnier hat leeren Namen "-" (Datenqualität nu); Contao-
  Filter im BE bleiben in der Session aktiv, Reset nur über das "Filter zurücksetzen"-Icon
  (nicht über "Zurücksetzen"-Button) — Contao-Standardverhalten.

Testrunde nach Such-Fix-Upload (17.07.2026): „Müller, Karsten" (mit/ohne Leerzeichen) → 2 Treffer
inkl. GM; Regression „Müller" → 686; Formulareingabe „Faber, Lutz" → 1 Treffer. Klickstrecken:
Karteikarte GM K. Müller (2 Mitgliedschaften, aktiv+passiv, korrekt), Verein Hamburger SK
(765 Mitglieder, rendert, aber langsam — siehe Offene Punkte #3), Vereinssuche „Bremer" → 4,
Bremer SG rendert, Verbandsliste Hamburg Top 25 (Carlsen #1), Turniersuche mit Verbandsfilter
zps=400 → 98 Treffer, Mannschaftsturnier ohne Auswertung zeigt saubere API-Fehlermeldung.
Dabei gefunden: 500er bei numerischer Suche (Offene Punkte #2, gefixt).

Lasttest mit vollen Echtdaten (17.07.2026, nu-Produktivschnittstelle in den Bundle-Einstellungen):
- Spielersuche „Müller" → 686 Treffer, rendert vollständig.
- Große Turniersuche (leer, 01/2025–12/2026) → **7218 Turniere**, rendert ohne Timeout/Fehlerseite
  (früher der kritische Fall). Symfony-Profiler: nur **68 DB-Queries** (Batch-syncList greift!),
  ~1,7 s. ACHTUNG Fehldeutung vermeiden: die „~14800" in der Debug-Toolbar sind DEPRECATION-Logs
  (Framework-Rauschen, 0 Errors, in Produktion aus), NICHT Queries.
- Klickstrecke (Echtdaten): Turniersuche „Fordopen" → Turnierauswertung (77 Spieler, echte
  DWZ-alt/neu/Leistung) → Karteikarte Jonas Schwibbert (**72 Turniereinträge 2010–2026**, kein
  500er) → Scoresheet 30. Fordopen 2025 (7 Runden, Summen) → Turnierergebnisse (Kreuztabelle
  82 Spieler). Alle Seiten korrekt. Einziger Wermutstropfen: Turnierergebnisse = 2789 Queries
  (Matches-Sync nicht batched, siehe Offene Punkte #2).

Sync-Optimierung nach Upload verifiziert (17.07.2026, Symfony-Profiler): Alle API-Abgleiche
schreiben nur noch bei tatsächlichen Änderungen (`ApiSyncTrait::applyApiFields`), syncClubs/
syncTournaments laufen über Bulk-Methoden (`syncList` mit Batch-INSERT). published wird nur
noch beim Anlegen gesetzt (manuell deaktivierte Datensätze bleiben deaktiviert). Messwerte
vorher → nachher: Turniersuche-Formular 4826 → 53 Queries (3,1 → 0,6 s), leere 19-Monats-Suche
5184 → 54 Queries (3,3 → 0,85 s, 178 Treffer vollständig), Verbandsseite 53 Queries,
Turnierergebnisse 139 Queries, Karteikarte (inkl. Historie-Sync) 80 Queries. Alle Seiten
inhaltlich unverändert korrekt (Verbändeliste inkl. Bugfix-Verbände, Karteikarte mit
Mitgliedschaft/FIDE/Historie).
