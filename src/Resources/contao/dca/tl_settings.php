<?php

/**
 * palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{wertungsportal_legend:hide},wertungsportal_karteisperre_gaeste,wertungsportal_passive_ausblenden,wertungsportal_geburtsjahr_ausblenden,wertungsportal_geschlecht_ausblenden,wertungsportal_historie,wertungsportal_elobase_url,wertungsportal_seite_spieler,wertungsportal_seite_turnier,wertungsportal_seite_verein,wertungsportal_seite_verband,wertungsportal_apiBasisURL,wertungsportal_tokenURL,wertungsportal_clientID,wertungsportal_clientSecret,wertungsportal_scopeListe,wertungsportal_crontoken,wertungsportal_api_aus,wertungsportal_api_timeout,wertungsportal_cache,wertungsportal_cachezeit_spieler,wertungsportal_cachezeit_vereine,wertungsportal_cachezeit_verbaende,wertungsportal_cachezeit_turniersuche,wertungsportal_cachezeit_turnierdaten,wertungsportal_cachezeit_turnierdaten_alt,wertungsportal_zugriffslog,wertungsportal_debuglog,wertungsportal_playerDefaultImage,wertungsportal_playerImageSize,wertungsportal_clubDefaultImage,wertungsportal_clubImageSize';

/**
 * fields
 */

// Karteikarte für Gäste sperren
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_karteisperre_gaeste'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_karteisperre_gaeste'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50'
	)
);

// Anzeige passive Mitglieder ausblenden
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_passive_ausblenden'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_passive_ausblenden'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50'
	)
);

// Anzeige des Geburtsjahres ausblenden
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_geburtsjahr_ausblenden'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_geburtsjahr_ausblenden'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50'
	)
);

// Anzeige des Geschlechts ausblenden
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_geschlecht_ausblenden'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_geschlecht_ausblenden'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50'
	)
);

// Historie EloBase in der Karteikarte anzeigen
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_historie'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_historie'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50'
	)
);

// URL der alten EloBase-Datenbank (altdwz)
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase_url'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_url'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false,
		'tl_class'            => 'w50',
	),
);

// Seite für das Spieler-Modul
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_seite_spieler'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_spieler'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'foreignKey'              => 'tl_page.title',
	'eval'                    => array
	(
		'mandatory'           => true,
		'fieldType'           => 'radio',
		'tl_class'            => 'w50 clr'
	),
	'sql'                     => "int(10) unsigned NOT NULL default 0",
	'relation'                => array
	(
		'type'                => 'hasOne',
		'load'                => 'lazy'
	)
); 

// Seite für das Turnier-Modul
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_seite_turnier'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_turnier'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'foreignKey'              => 'tl_page.title',
	'eval'                    => array
	(
		'mandatory'           => true,
		'fieldType'           => 'radio',
		'tl_class'            => 'w50'
	),
	'sql'                     => "int(10) unsigned NOT NULL default 0",
	'relation'                => array
	(
		'type'                => 'hasOne',
		'load'                => 'lazy'
	)
); 

// Seite für das Verein-Modul
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_seite_verein'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_verein'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'foreignKey'              => 'tl_page.title',
	'eval'                    => array
	(
		'mandatory'           => true,
		'fieldType'           => 'radio',
		'tl_class'            => 'w50'
	),
	'sql'                     => "int(10) unsigned NOT NULL default 0",
	'relation'                => array
	(
		'type'                => 'hasOne',
		'load'                => 'lazy'
	)
); 

// Seite für das Verband-Modul
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_seite_verband'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_seite_verband'],
	'exclude'                 => true,
	'inputType'               => 'pageTree',
	'foreignKey'              => 'tl_page.title',
	'eval'                    => array
	(
		'mandatory'           => true,
		'fieldType'           => 'radio',
		'tl_class'            => 'w50'
	),
	'sql'                     => "int(10) unsigned NOT NULL default 0",
	'relation'                => array
	(
		'type'                => 'hasOne',
		'load'                => 'lazy'
	)
); 

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_apiBasisURL'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_apiBasisURL'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false, 
		'tl_class'            => 'w50', 
	),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_tokenURL'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_tokenURL'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false, 
		'tl_class'            => 'w50', 
	),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_clientID'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clientID'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false, 
		'tl_class'            => 'w50', 
	),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_clientSecret'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clientSecret'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false, 
		'tl_class'            => 'w50', 
	),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_scopeListe'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_scopeListe'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false, 
		'tl_class'            => 'w50', 
	),
);

// Geheimer Schlüssel für die Cron-Download-Skripte
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_crontoken'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_crontoken'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false,
		'tl_class'            => 'w50',
	),
);

// Cache ein- oder ausschalten
// Notschalter: Schaltet jeden Zugriff auf die REST-Schnittstelle ab. Es
// werden nur noch Daten aus dem Zwischenspeicher ausgeliefert, und zwar
// ohne Rücksicht auf deren Ablaufzeit — sonst stünde nach kurzer Zeit
// überhaupt nichts mehr zur Verfügung. Gedacht für angekündigte Wartungen
// von nu und für Störungen, die sich sonst als Wartezeit bemerkbar machen
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_api_aus'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_aus'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50 clr',
	)
);

// Wartezeit, nach der ein Abruf abgebrochen wird. Ohne Begrenzung hängt
// der Seitenaufbau an einer nicht antwortenden Schnittstelle fest
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_api_timeout'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_api_timeout'],
	'inputType'               => 'select',
	'options'                 => array('5', '10', '15', '20', '30', '45', '60'),
	'reference'               => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_timeouts'],
	'eval'                    => array
	(
		'includeBlankOption'  => true,
		'tl_class'            => 'w50',
	)
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cache'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50 clr',
	)
);

// Cachezeiten je Funktionsgruppe (Wert = Stunden; 0 = nicht cachen,
// -1 = unbegrenzt, leer = Standard von 24 Stunden). Die Beschriftungen der
// Optionen stehen in den Sprachdateien (wertungsportal_cachezeiten).
//
// turnierdaten gibt es ZWEIMAL: Daten zu einem einzelnen Turnier ändern sich
// nach der Erstauswertung praktisch nur noch im ersten Jahr. Für ältere
// Turniere gilt deshalb die zweite Einstellung, die bis „unbegrenzt" reichen
// kann (siehe API::cachezeitFuerAntwort)
foreach(array('spieler', 'vereine', 'verbaende', 'turniersuche', 'turnierdaten', 'turnierdaten_alt') as $bereich)
{
	$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cachezeit_'.$bereich] = array
	(
		'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeit_'.$bereich],
		'inputType'               => 'select',
		'options'                 => array('0', '1', '6', '12', '24', '48', '168', '720', '1440', '2160', '2880', '4320', '8760', '-1'),
		'reference'               => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cachezeiten'],
		'eval'                    => array
		(
			'includeBlankOption'  => true,
			'tl_class'            => 'w50',
		),
	);
}

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_playerDefaultImage'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_playerDefaultImage'],
	'inputType'               => 'fileTree',
	'eval'                    => array
	(
		'filesOnly'           => true,
		'fieldType'           => 'radio',
		'tl_class'            => 'w50 clr'
	)
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_playerImageSize'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_playerImageSize'],
	'exclude'                 => true,
	'inputType'               => 'imageSize',
	'reference'               => &$GLOBALS['TL_LANG']['MSC'],
	'eval'                    => array(
		'rgxp'                => 'natural', 
		'includeBlankOption'  => true, 
		'nospace'             => true, 
		'helpwizard'          => true, 
		'tl_class'            => 'w50'
	),
	'options_callback' => static function ()
	{
		return System::getContainer()->get('contao.image.image_sizes')->getOptionsForUser(BackendUser::getInstance());
	},
); 

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_clubDefaultImage'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clubDefaultImage'],
	'inputType'               => 'fileTree',
	'eval'                    => array
	(
		'filesOnly'           => true,
		'fieldType'           => 'radio',
		'tl_class'            => 'w50 clr'
	)
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_clubImageSize'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_clubImageSize'],
	'exclude'                 => true,
	'inputType'               => 'imageSize',
	'reference'               => &$GLOBALS['TL_LANG']['MSC'],
	'eval'                    => array(
		'rgxp'                => 'natural', 
		'includeBlankOption'  => true, 
		'nospace'             => true, 
		'helpwizard'          => true, 
		'tl_class'            => 'w50'
	),
	'options_callback' => static function ()
	{
		return System::getContainer()->get('contao.image.image_sizes')->getOptionsForUser(BackendUser::getInstance());
	},
); 

// Zugriffs-Log: schreibt je Abfrage eine Zeile nach var/logs (eine Datei je
// Tag). ACHTUNG Datenschutz: Die Zeile enthält die IP-Adresse des Besuchers
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_zugriffslog'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_zugriffslog'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50 clr'
	)
);

// Anzeige passive Mitglieder ausblenden
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_debuglog'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_debuglog'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50'
	)
);
