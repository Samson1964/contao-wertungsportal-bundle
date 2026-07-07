<?php

/**
 * palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{wertungsportal_legend:hide},wertungsportal_karteisperre_gaeste,wertungsportal_passive_ausblenden,wertungsportal_geburtsjahr_ausblenden,wertungsportal_geschlecht_ausblenden,wertungsportal_seite_spieler,wertungsportal_seite_turnier,wertungsportal_seite_verein,wertungsportal_seite_verband,wertungsportal_apiBasisURL,wertungsportal_tokenURL,wertungsportal_clientID,wertungsportal_clientSecret,wertungsportal_scopeListe,wertungsportal_cache,wertungsportal_playerDefaultImage,wertungsportal_playerImageSize,wertungsportal_clubDefaultImage,wertungsportal_clubImageSize,wertungsportal_eloLocal';

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

// Cache ein- oder ausschalten
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cache'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50 clr',
	)
);

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

// Elo von lokaler Quelle laden
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_eloLocal'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_eloLocal'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'clr w50',
	)
);

