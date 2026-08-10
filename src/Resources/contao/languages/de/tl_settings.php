<?php

/**
 * Legende
 */
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_legend']       = 'Wertungsportal';

/**
 * Felder
 */

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_karteisperre_gaeste'] = array('Karteikarten für Gäste sperren','Nichtangemeldeten Besuchern den Zugriff auf die Karteikarten verweigern.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_passive_ausblenden'] = array('Passiv-Mitgliedschaften ausblenden','Passiv gemeldete Spieler ausblenden');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_geburtsjahr_ausblenden'] = array('Geburtsjahr ausblenden','Geburtsjahr der Spieler ausblenden');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_geschlecht_ausblenden'] = array('Geschlecht ausblenden','Geschlecht der Spieler ausblenden');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_historie'] = array('Historie EloBase anzeigen','In der Karteikarte einen Link zur alten EloBase-Karteikarte (altdwz) anzeigen');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_url'] = array('EloBase-URL','URL der alten Datenbank inklusive zps-Parameter; ohne Eintrag wird http://altdwz.schachbund.net/db/spieler.html?zps= verwendet');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_spieler'] = array('Spielerseite wählen','Seite, auf der das Spieler-Modul eingebunden ist');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_turnier'] = array('Turnierseite wählen','Seite, auf der das Turnier-Modul eingebunden ist');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_verein'] = array('Vereinseite wählen','Seite, auf der das Verein-Modul eingebunden ist');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_verband'] = array('Verbandseite wählen','Seite, auf der das Verband-Modul eingebunden ist');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_apiBasisURL'] = array('API-Basisadresse', 'URL der API-Basis (ohne endenden Slash)');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_tokenURL'] = array('Token-Adresse', 'URL des Token-Endpoints (ohne endenden Slash)');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clientID'] = array('Client-ID', 'Client-Identifikationsnummer');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clientSecret'] = array('Client Secret', 'Geheimes Passwort');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_scopeListe'] = array('Scope (Bereiche)', 'Bereiche eintragen, für die Zugriff gegeben werden soll.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_crontoken'] = array('Cron-Token', 'Geheimer Schlüssel für die Download-Skripte (Aufruf mit ?key=SCHLÜSSEL). Ohne Eintrag sind die Skripte gesperrt.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_aus'] = array('Live-Abruf abschalten', 'Es wird keine Verbindung zur Schnittstelle mehr aufgebaut; ausgeliefert wird nur noch, was im Zwischenspeicher liegt — auch wenn dessen Gültigkeit abgelaufen ist. Die Ausgaben weisen darauf hin. Für Wartungsfenster und Störungen bei nu.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_timeout'] = array('Wartezeit der Schnittstelle', 'Nach dieser Zeit ohne Antwort wird der Abruf abgebrochen und auf den Zwischenspeicher zurückgegriffen. Ohne Auswahl gilt 30 Sekunden.');

// Auswahlwerte der Wartezeit (Schlüssel = Sekunden)
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_timeouts'] = array
(
	'5'  => '5 Sekunden',
	'10' => '10 Sekunden',
	'15' => '15 Sekunden',
	'20' => '20 Sekunden',
	'30' => '30 Sekunden',
	'45' => '45 Sekunden',
	'60' => '60 Sekunden',
);

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache'] = array('Cache aktivieren','Cache für das Wertungsportal aktivieren');

// Auswahlwerte der Cachezeiten (Schlüssel = Stunden, -1 = unbegrenzt)
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeiten'] = array
(
	'0'    => 'Kein Cache',
	'1'    => '1 Stunde',
	'6'    => '6 Stunden',
	'12'   => '12 Stunden',
	'24'   => '1 Tag',
	'48'   => '2 Tage',
	'168'  => '1 Woche',
	'720'  => '30 Tage',
	'1440' => '2 Monate',
	'2160' => '3 Monate',
	'2880' => '4 Monate',
	'4320' => '6 Monate',
	'8760' => '1 Jahr',
	'-1'   => 'Unbegrenzt',
);

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_spieler'] = array('Cachezeit Spieler', 'Spielersuche, Karteikarte und Turnierhistorie. Ohne Auswahl gilt 1 Tag.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_vereine'] = array('Cachezeit Vereine', 'Vereinsliste, Vereinsname und Verbandsrangliste. Ohne Auswahl gilt 1 Tag.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_verbaende'] = array('Cachezeit Verbände', 'Liste aller Verbände und Vereine (ändert sich selten, 1 Woche ist üblich). Ohne Auswahl gilt 1 Tag.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_turniersuche'] = array('Cachezeit Turniersuche', 'Turniersuche und Turnier-Kopfdaten. Ohne Auswahl gilt 1 Tag.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_turnierdaten'] = array('Cachezeit Turnierdaten (bis zu 1 Jahr alt)', 'Kopfdaten, Auswertung, Ergebnisse und Spielberichtsbögen von Turnieren, deren Turnierende weniger als ein Jahr zurückliegt. In dieser Zeit sind Nachberechnungen noch zu erwarten. Ohne Auswahl gilt 1 Tag.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_turnierdaten_alt'] = array('Cachezeit Turnierdaten (über 1 Jahr alt)', 'Dieselben Daten für Turniere, deren Turnierende länger als ein Jahr zurückliegt — daran ändert sich in aller Regel nichts mehr, „Unbegrenzt" ist hier vertretbar. Ohne Auswahl gilt dieselbe Zeit wie für jüngere Turniere. ACHTUNG: Sollte nu ein altes Turnier doch noch einmal nachberechnen, bleibt der alte Stand stehen, bis der Cache über die Systemwartung geleert wird.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_playerDefaultImage'] = array('Spielerbild', 'Standardbild für Spieler');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_playerImageSize'] = array('Bildgröße', 'Größe der Spielerbilder');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clubDefaultImage'] = array('Vereinsbild', 'Standardbild/-logo für Vereine');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clubImageSize'] = array('Bildgröße', 'Größe der Vereinsbilder/Logos');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cron_aus'] = array('Nächtliches Vorladen abschalten', 'Ohne diesen Haken holt ein Cronjob nachts zwischen 1 und 3 Uhr alle zehn Minuten Turnierdaten und Karteikarten in den Zwischenspeicher, sofern dort noch nichts liegt — in dieser Reihenfolge: Turnierauswertungen, Turnierergebnisse, Karteikarten, Turnierhistorien, zuletzt die Spielberichtsbögen. Vorgeladen wird der gesamte örtliche Bestand, nicht nur die jüngsten Turniere. Ohne das Vorladen wartet jeweils der erste Besucher einer Seite auf die Schnittstelle. Jeder Lauf endet nach 180 Sekunden und der nächste macht dort weiter; der Lauf um 3 Uhr ist der letzte der Nacht. Erfolge stehen im Systemlog. ACHTUNG PLATZBEDARF: Ein vollständiger Durchlauf dauert mehrere Nächte und legt je Person zwei Einträge an (Karteikarte rund 4 KB, Turnierhistorie rund 32 KB) — bei 95.000 Personen sind das etwa 3,4 GB, dazu die Turnierdaten. Näheres in docs/vorladen.md.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_mail_absender'] = array('Absenderadresse', 'Absender der E-Mails dieses Bundles, zum Beispiel der Schlüssel-E-Mail der Vereinslisten-Schnittstelle. Ohne Eintrag gilt die Adresse des Administrators aus den allgemeinen Einstellungen. Die Adresse muß zur Domain der Website passen, sonst stufen viele Postfächer die Nachricht als Fälschung ein.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_mail_absendername'] = array('Absendername', 'Name, der beim Empfänger als Absender erscheint. Ohne Eintrag der Name der Website.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_mail_token'] = array('Vorlage der Schlüssel-E-Mail', 'HTML-Vorlage für die E-Mail mit dem Zugangsschlüssel. Ohne Auswahl geht die Nachricht als reiner Text hinaus. Eigene Fassungen legt man als Kopie unter templates/ an; der Dateiname muß mit „wp_mail_token" beginnen. Die verfügbaren Platzhalter stehen im Kopf der mitgelieferten Vorlage.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_abrufe_tag'] = array('Erlaubte Abrufe je Tag', 'Höchstzahl der Vereinslisten-Abrufe je Zugangsschlüssel und Tag; gezählt werden nur erfolgreiche Abrufe. Dieselbe Zahl nennt die Schlüssel-E-Mail, Text und Verhalten bleiben also beieinander. Ohne Eintrag gelten 24 (ein Abruf je Stunde), eine 0 hebt die Grenze auf. Unabhängig davon greift eine Bremse von 120 Anfragen je Stunde und IP-Adresse.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_freigabe'] = array('Neue Schlüssel erst nach Freigabe', 'Über das Registrierungsformular angeforderte Schlüssel werden unveröffentlicht angelegt und liefern erst Daten, nachdem sie unter „Zugangsschlüssel" freigeschaltet wurden. Die Bestätigungsmail weist darauf hin. Ohne diesen Haken bekommt jeder, der eine Vereinskennziffer kennt, sofort Zugriff auf die Mitgliederliste dieses Vereins.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_sperren'] = array('Gesperrte IP-Adressen', 'Anfragen von diesen Adressen an die Vereinslisten-Schnittstelle werden abgewiesen — eine Adresse je Zeile, Zeilen mit # am Anfang sind Kommentare. Für den Fall, daß jemand die Schnittstelle ohne gültigen Schlüssel belagert. Einzelne Zugangsschlüssel sperrt man dagegen am Datensatz unter „Zugangsschlüssel".');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_zugriffslog'] = array('Zugriffs-Log', 'Schreibt je Abfrage eine Zeile nach var/logs (eine Datei je Tag, Semikolon-getrennt): Dauer, Quelle, Funktion, Trefferzahl, IP-Adresse, Browser und Seite. Zum Beobachten der Laufzeiten. ACHTUNG: Die IP-Adresse ist ein personenbezogenes Datum — dauerhafter Betrieb gehört in die Datenschutzerklärung, und die Dateien sollten regelmäßig gelöscht werden.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_debuglog'] = array('Debug-Log', 'Debug-Log aktivieren');

// Besucherbremse gegen Massenabfragen
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_limit_minute'] = array('Höchstabrufe je Minute', 'Wie viele Wertungsportal-Seiten ein Besucher (eine IP-Adresse) je Minute abrufen darf. Gezählt wird je Seitenaufruf, nicht je Schnittstellenabfrage. Leer oder 0 schaltet diese Grenze ab.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_limit_stunde'] = array('Höchstabrufe je Stunde', 'Dasselbe für eine Stunde. Ein Mensch kommt selten über einige Dutzend; ein Bot, der einen Verband abklappert, sofort über tausend.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_limit_tag'] = array('Höchstabrufe je Tag', 'Dasselbe für einen Tag. Wer eine Grenze reißt, bekommt bis zum Ende des jeweiligen Zeitfensters einen Hinweis statt Daten; der Vorfall steht mit IP-Adresse, Browserkennung und — falls angemeldet — Mitgliedskennung unter „Sperren".');
