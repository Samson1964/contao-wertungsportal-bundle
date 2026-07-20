# Wertungsportal-Anbindung

## Version 1.0.0 (2026-07-20)

* Fix: Personen-Import: Die AJAX-Aufrufe dürfen KEINEN X-Requested-With-Header senden, sonst fängt Contaos eigenes Ajax-System die Anfrage mit "Missing Ajax action" ab, bevor der key-Callback läuft (im Livetest gefunden, Template korrigiert)
* Add: Personen-Import aus der Vereinsmitglieder-CSV als globale Operation unter WP | Personen (key=importPersons). Upload und Import laufen in AJAX-Schritten (Chunk-Upload à 2 MB nach system/tmp, Import in Paketen à 1000 Zeilen per Byte-Offset), damit auch ~100.000 Zeilen ohne Timeout durchlaufen; Fortschrittsanzeige und Ergebnisstatistik im Backend-Template be_wp_personenimport. Spaltenzuordnung erfolgt über die Kopfzeile (Pflichtspalten InterneNr/ExterneNr/Nachname/Vorname), Personen werden per Bulk-Upsert über die InterneNr (nuLigaPersonId) angelegt bzw. nur bei Änderungen aktualisiert (WertungsportalPersonsModel::importCsvRows)
* Add: Der Personen-Import übernimmt auch die Vereinsmitgliedschaften in die Kindtabelle tl_wertungsportal_persons_memberships – Match über VKZ + Mitgliedsnummer (neuer Verbund-Index; laut Datei-Analyse kollisionsfrei), bestehende Datensätze werden nur bei Änderungen aktualisiert (inkl. pid-Verknüpfung zur Person), neue per Batch-INSERT angelegt, nichts wird gelöscht; die Vereine der Mitgliedschaften werden über ClubsModel::syncList mit angelegt. Neue Felder spielgenehmigungVon/spielgenehmigungBis; Spielgenehmigung wird auf den Lizenzstatus gemappt (Aktiv/Passiv/Sondermitgliedschaft/ohne Spielgenehmigung → ACTIVE/PASSIVE/SONDER/OHNE, neue Optionen inkl. Sprachdateien)
* Add: 29 neue Felder in tl_wertungsportal_persons für den CSV-Import, u. a. externeNr (entspricht größtenteils der alten dewisID aus tl_dwz_spi, mit Index für das spätere Matching), Anrede/Titel/Geburtsname/-ort, Verstorben(-Am), Nation/FIDE-Nation, Geschlecht im Spielbetrieb, Adresse und Kontaktdaten sowie Datenschutzfelder; neue Indizes auf nuLigaPersonId und externeNr — DB-Update erforderlich (contao:migrate/Install-Tool)
* Umfangreiche Programmierungen außer bei den Downloads, Caching und lokaler Sicherung
* Change: Korrekturen Datensatzanzeige mit Hilfe von Claude
* Add: tl_dwz_spiver.bearbeiter (kommt aus MIVIS)
* Add: Tabelle tl_wertungsportal_clubs (Felder aus /dwz/dwzliste/clubs) mit DCA und deutscher Sprachdatei, mit Claude erstellt
* Add: Model WertungsportalClubsModel mit Findern (findByVkz, findPublished, findActive, searchByName) und Upsert-Funktion, mit Claude erstellt
* Add: Backend-Modul wp-clubs zur Verwaltung der Vereine
* Add: API::syncClubs() – Abfragen von /dwz/dwzliste/clubs (Vereinsname, Verbaende) gleichen die Rückgabe automatisch mit tl_wertungsportal_clubs ab
* Change: Reihenfolge der Backend-Module geändert (wp-clubs vor den tl_dwz-Modulen)
* Add: Sortierung in tl_wertungsportal_clubs nach clubVkz (Standard) und clubName, jeweils alphabetisch aufsteigend
* Add: Unveröffentlichte Vereine werden in der Backendliste rot dargestellt (auch direkt beim Betätigen des Togglers)
* Add: Tabellen tl_wertungsportal_persons und tl_wertungsportal_persons_memberships (Felder aus /dwz/dwzliste/persons inkl. memberships) mit DCA und deutschen Sprachdateien, mit Claude erstellt
* Add: Models WertungsportalPersonsModel und WertungsportalPersonsMembershipsModel mit Findern und Upsert-/Sync-Funktionen, mit Claude erstellt
* Add: Backend-Modul wp-persons zur Verwaltung der Personen und ihrer Mitgliedschaften
* Add: API::syncPersons() – Abfragen von /dwz/dwzliste/persons (Spielerliste, Karteikarte, Verbandsliste) gleichen die Rückgabe automatisch mit tl_wertungsportal_persons und der Kindtabelle ab
* Add: Unveröffentlichte Personen werden in der Backendliste rot dargestellt
* Add: Englische Sprachdateien (Fallback) für tl_wertungsportal_clubs, tl_wertungsportal_persons, tl_wertungsportal_persons_memberships und die neuen Backend-Module
* Add: Label für Backend-Modul wp-persons in der deutschen modules.php
* Fix: Operationen in tl_wertungsportal_persons: edit öffnet die Mitgliedschaften, editheader bearbeitet die Person
* Add: Unveröffentlichte Mitgliedschaften werden in der Elternansicht rot dargestellt (auch direkt beim Betätigen des Togglers)
* Change: Zwischenüberschriften in der Mitgliedschaftsliste deaktiviert (disableGrouping), Kopfdaten der Person bleiben erhalten
* Add: Tabelle tl_wertungsportal_tournaments (Felder aus entries.tournament von /dwz/persons/{id}/history) mit DCA, Sprachdateien (de/en) und Backend-Modul wp-tournaments, mit Claude erstellt
* Add: Kindtabellen tl_wertungsportal_persons_tournaments (Turnierhistorie, Felder aus entries.player plus tournamentUuid als redundanzfreie Referenz) und tl_wertungsportal_persons_upgrades (Felder aus upgrades) mit DCAs und Sprachdateien (de/en), mit Claude erstellt
* Add: Models WertungsportalTournamentsModel, WertungsportalPersonsTournamentsModel und WertungsportalPersonsUpgradesModel mit Upsert-/Sync-Funktionen, mit Claude erstellt
* Add: Operationen in tl_wertungsportal_persons zum Öffnen der Turnierhistorie und der Hochstufungen (Bundle-Icons icon_turniere.png und rating.png)
* Add: API::syncPersonHistory() – die Abfrage Karteikarte_Turniere (/dwz/persons/{id}/history) gleicht Person, Mitgliedschaften, Turnierhistorie, Turniere und Hochstufungen automatisch ab
* Add: Unveröffentlichte Turniere werden in der Backendliste rot dargestellt
* Add: tl_wertungsportal_tournaments um playerCount und matchCount ergänzt (Felder aus /dwz/tournaments/{uuid})
* Add: Kindtabellen tl_wertungsportal_tournaments_evaluation (DWZ-Auswertung, Felder aus evaluation.players) und tl_wertungsportal_tournaments_matches (Partien, Felder aus matches.data; Spieler redundanzfrei über whitePlayerUuid/blackPlayerUuid referenziert) mit DCAs und Sprachdateien (de/en), mit Claude erstellt
* Add: Models WertungsportalTournamentsEvaluationModel und WertungsportalTournamentsMatchesModel mit Upsert-/Sync-Funktionen, mit Claude erstellt
* Add: Operationen in tl_wertungsportal_tournaments zum Öffnen der Auswertung (edit) und der Partien (matches), editheader bearbeitet das Turnier
* Add: API-Abgleich für Turnierinfo, Turnierliste (syncTournaments), Turnierauswertung (syncTournamentEvaluation), Turnierergebnisse (syncTournamentMatches) und Spielberichtsbogen (syncScoresheet – aktualisiert Turnier, Partien und Spielerdaten, keine eigene Tabelle)
* Add: Unveröffentlichte Auswertungseinträge und Partien werden in der Elternansicht rot dargestellt
* Change: Turnierliste: Werkzeugsymbol (Turnier bearbeiten) an erster Stelle, Auswertungs-Button mit Rating-Icon statt Bleistift
* Add: Anzeige der Spielernamen (nicht editierbar) unter den UUID-Feldern im Bearbeitungsformular von tl_wertungsportal_tournaments_matches
* Add: Einlesen von evaluation, matches und scoresheet aktualisiert auch tl_wertungsportal_persons (Referenz nuLigaPersonId, nur Identitätsfelder)
* Fix: upsertByUuid in WertungsportalPersonsModel mit Fallback auf nuLigaPersonId, um Duplikate bei zuvor ohne UUID angelegten Personen zu vermeiden
* Add: Turnierauswertung, Turnierergebnisse und Spielberichtsbogen aktualisieren auch die Turnierhistorie tl_wertungsportal_persons_tournaments (upsertEntry aus syncForPerson extrahiert)
* Fix: Abruf Vereinsliste synchronisiert jetzt Personen (syncPersons) – Vereine werden dabei über die Mitgliedschaften in tl_wertungsportal_clubs abgelegt (VKZ und Vereinsname, gilt für alle Personen-Abfragen)
* Change: Personenliste: Werkzeugsymbol (Person bearbeiten) an erster Stelle, Mitgliedschaften-Button mit Vereins-Icon statt Bleistift
* Change: Neue Icons für die Operationen: history.png (Turnierhistorie), chart.png (DWZ-Hochstufungen), rating2.png (DWZ-Auswertung), games.png (Partien) – keine Doppelbelegungen mehr
* Change: Neue Operations-Icons auf 16x16 Pixel verkleinert
* Change: tl_wertungsportal_persons.birthyear von int auf varchar(10) umgestellt – erlaubt Geburtsjahr (JJJJ) oder vollständiges Geburtsdatum; API-Abgleich überschreibt ein manuell gepflegtes Datum nicht, solange das Jahr übereinstimmt
* Fix: Spieler.php: fehlende Absicherung der optionalen API-Felder wins und numberOfGames in der Karteikarte (500er bei Historien-Einträgen ohne diese Felder)
* Change: Turnier.php: API-Fehler (z. B. "No evaluation found") werden bei Turnierauswertung, Turnierergebnissen und Spielberichtsbogen im Template ausgegeben statt auf die 404-Seite umzuleiten (neue Methode templateFehler, Templates um Fehlerausgabe ergänzt)
* Fix: Fehlertolerante Datumsformatierung Helper::ApiDatum() – fehlendes enddate/lastCalculated (z. B. bei nie berechneten Turnieren) führte in Turnierergebnisse, Turnierauswertung, Scoresheet und Turniersuche zu einem 500er
* Fix: Helper::PlayerDefaults() füllt fehlende Felder der Spieler-DTOs auf – ersetzt die unvollständigen array_key_exists-Blöcke in Turnierergebnisse, Scoresheet und Turnierauswertung (500er bei fehlendem wins u. a.)
* Add: CLAUDE.md mit Projektkontext, Sync-Architektur, Fallstricken und offenen Punkten
* Change: Sync-Performance: Alle API-Abgleiche schreiben nur noch bei tatsächlichen Änderungen (neuer ApiSyncTrait::applyApiFields in allen Wertungsportal-Models) – unveränderte Datensätze erzeugen keine UPDATE-Queries mehr
* Change: Sync-Performance: syncClubs und syncTournaments nutzen neue Bulk-Methoden (WertungsportalClubsModel::syncList, WertungsportalTournamentsModel::syncList) – Bestand wird mit einer Abfrage geladen, neue Datensätze werden blockweise per Batch-INSERT angelegt (statt SELECT+UPDATE je Datensatz, vorher ~4800 Queries pro Seitenaufruf bei ~2400 Vereinen)
* Change: Der Veröffentlichungsstatus wird beim API-Abgleich nur noch beim Anlegen gesetzt – manuell deaktivierte Datensätze werden durch einen Sync nicht mehr wieder veröffentlicht
* Add: Token-Schutz für die Cron-Download-Skripte Wertungsportal_Download.php und Wertungsportal_Converter.php – Aufruf nur noch mit ?key=SCHLÜSSEL (neue Einstellung wertungsportal_crontoken, ohne Eintrag sind die Skripte gesperrt)
* Add: Download-Absicherung für die Cron-Skripte – neue Helper::DownloadDatei() prüft Curl-Fehler, HTTP-Status und Zip-Konsistenz, wiederholt fehlgeschlagene Downloads bis zu 3-mal (5 s Pause) und löscht defekte Dateien statt sie zu archivieren (der nu-Server lieferte am 17.07.2026 zeitweise abgeschnittene Zips); Download-Skript meldet Fehlschläge im Abschlusstext, Converter bricht bei endgültigem Fehlschlag mit FEHLER-Meldung ab
* Change: Sync-Performance Stufe 2 – auch Personen-, Mitgliedschafts-, Turnierhistorien-, Auswertungs- und Partien-Syncs arbeiten jetzt als Bulk-Verarbeitung (Bestand per IN-Liste laden, Batch-INSERTs, Updates nur bei Änderungen, gesammelte DELETEs): neue Methoden WertungsportalPersonsModel::syncList()/syncFromPlayerDtos(), WertungsportalPersonsMembershipsModel::syncForPersons(), WertungsportalPersonsTournamentsModel::syncEntries(), WertungsportalTournamentsEvaluationModel::syncPlayers(); MatchesModel::syncForTournament komplett auf Bulk umgestellt. Betroffene Seiten: Vereins-/Verbands-/Spielerlisten (vorher ~7500 Queries bei 765 Mitgliedern) und Turnierergebnisse/Auswertung/Scoresheet (vorher ~2800 Queries bei 266 Partien)
* Fix: Modul-Links und Suchformular-Actions verlassen den Contao-Vorschaumodus nicht mehr – neue Helper-Methoden getSpielerseiteUrl/getTurnierseiteUrl/getVereinseiteUrl/getVerbandseiteUrl liefern die per Router generierte Seiten-URL (im Vorschaumodus inkl. preview.php-Präfix) statt des nackten Alias; alle Link-Bauer in den Formatter-Klassen, Helper::Spielername, der Listenlink in Verein.php und die vier Formular-Actions in den Templates umgestellt. Die Alias-Getter bleiben für die URL-Fragment-Vergleiche und Location-Redirects in API.php erhalten
* Change: Tote Archiv-Kopien src/Helper/OAuth2Client_v1.php und _v2.php gelöscht (deklarierten dieselbe Klasse wie die aktive OAuth2Client.php, v2 mit Parse-Fehler, beide mit hartkodierten Demo-Zugangsdaten) – beim Deployment auch auf dem Server entfernen
* Change: Frontend-Module restrukturiert – die Datenaufbereitung der vier Module in src/Classes ist nach dem Muster von Turniersuche/Scoresheet in eigene Helper-Klassen ausgelagert: Spielersuche, Karteikarte (Spieler.php), Vereinssuche, Vereinsliste (Verein.php), Verbandsnavigation, Verbandsrangliste (Verband.php), Turnierformular (Turnier.php). Die Module enthalten nur noch Parameterverarbeitung, API-Aufrufe, Fehlerbehandlung, Bilder und Template-Zuweisungen
* Fix: Vereinssuche zeigte den Hinweis „Der Suchbegriff darf nur Buchstaben…" schon beim leeren Suchformular – Validierung greift jetzt nur noch bei tatsächlicher Eingabe
* Fix: Karteikarte mit API-Fehler (z. B. nu-Timeout) führte zu einem PHP-Fehler – jetzt saubere Fehlermeldung im Template wie bei den Turnierseiten
* Change: Turnier.php protokolliert die GET-Parameter nur noch bei aktiviertem Debug-Log (wertungsportal_debuglog) statt bei jedem Seitenaufruf; die Verbands-/Vereinsliste wird nur noch für das Suchformular geladen statt bei allen Turnier-Ansichten
* Change: Ungenutzte Blacklist-Abfragen in Spieler.php, Verband.php und Turnier.php entfernt (je 1 überflüssige DB-Query pro Seitenaufruf) sowie weiterer toter Code (ungenutzte $gesperrt/$mitglied/$dewis-Variablen)
* Change: FIDE-Anreicherung gebündelt – neue Helper::getFIDEDatenListe() lädt die FIDE-Daten (Elo/Titel/Nation) aller Spieler einer Liste mit einer IN-Abfrage aus tl_dwz_elo statt je Spieler einzeln; umgestellt in setFIDEDaten (Spieler-/Vereinsliste), Verband.php (Verbandsrangliste) und Turnierauswertung.php (vorher z. B. ~450 Einzelabfragen bei der Vereinsliste des Hamburger SK)
* Fix: Spielersuche mit Mitglieds- oder ZPS-Nummer führte zu einem 500er (Undefined variable $param in Spieler.php) – numerische bzw. ZPS-Eingaben zeigen jetzt einen Hinweis im Suchformular statt abzustürzen
* Fix: Spielersuche „Nachname, Vorname" lieferte 0 Treffer, obwohl der Spieler existierte (z. B. „Müller, Karsten"). Ursache: Spieler.php sluggte den Suchstring (contao.slug) VOR der Komma-Trennung, wodurch das Komma zu „-" wurde und die Nachname/Vorname-Trennung fehlschlug. Jetzt wird zuerst getrennt und erst danach werden Nachname und Vorname einzeln geslugt; Helper::checkSearchstringPlayer belegt seine Rückgabefelder vor (keine undefinierten Variablen im Komma-/pkz-/zps-Zweig)
* Fix: Wertungsportal_Converter.php: Die Verbands-Zips (LV-1 bis LV-M) enthielten immer die README.txt aus Gesamtdeutschland – neue Methode writeReadme() passt Überschrift (Landesverband: X - Verbandsname aus verbaende.csv) und Spieler-/Vereinszahlen an den jeweiligen Verband an; die LV-0-README bleibt unverändert

## Version 0.0.3 (2026-07-07)

* Change: Klasse Wertungsportal durch OAuth2Client ersetzt, mit Claude Code korrigiert
* Add: Klassen für Spieler, Verein, Turnier und Verband
* Add: Helper-Klasse aus DeWIS modifiziert übernommen
* Add: API-Klasse
* Add: Cachefunktion
* Add: Abhängigkeit schachbulle/contao-helper-bundle
* Add: Weitere Einstellungen wie Spieler- und Vereinsbild
* Add: Übernahme Tabellen mit Prefix tl_dwz
* Change: OAuth2Client mit Claude geändert um RuntimeException zu umgehen
* Add: tl_dwz_spi.nuLigaPersonId
* Umfangreiche Programmierungen außer bei den Downloads, Caching und lokaler Sicherung

## Version 0.0.2 (2026-06-01)

* Add: System-Einstellungen
* Add: Klasse Wertungsportal für den Zugriff auf die neue API
* Add: api.php -> öffentlicher Aufruf der API für Testzwecke

## Version 0.0.1 (2026-05-29)

* Erste Alphaversion
