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

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_freigabe'] = array('Neue Schlüssel erst nach Freigabe', 'Über das Registrierungsformular angeforderte Schlüssel werden unveröffentlicht angelegt und liefern erst Daten, nachdem sie unter „WP | Zugangsschlüssel" freigeschaltet wurden. Die Bestätigungsmail weist darauf hin. Ohne diesen Haken bekommt jeder, der eine Vereinskennziffer kennt, sofort Zugriff auf die Mitgliederliste dieses Vereins.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_sperren'] = array('Gesperrte IP-Adressen', 'Anfragen von diesen Adressen an die Vereinslisten-Schnittstelle werden abgewiesen — eine Adresse je Zeile, Zeilen mit # am Anfang sind Kommentare. Für den Fall, daß jemand die Schnittstelle ohne gültigen Schlüssel belagert. Einzelne Zugangsschlüssel sperrt man dagegen am Datensatz unter „WP | Zugangsschlüssel".');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_zugriffslog'] = array('Zugriffs-Log', 'Schreibt je Abfrage eine Zeile nach var/logs (eine Datei je Tag, Semikolon-getrennt): Dauer, Quelle, Funktion, Trefferzahl, IP-Adresse, Browser und Seite. Zum Beobachten der Laufzeiten. ACHTUNG: Die IP-Adresse ist ein personenbezogenes Datum — dauerhafter Betrieb gehört in die Datenschutzerklärung, und die Dateien sollten regelmäßig gelöscht werden.');

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_debuglog'] = array('Debug-Log', 'Debug-Log aktivieren');
