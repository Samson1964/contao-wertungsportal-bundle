<?php

/**
 * palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'wertungsportal_switchedOff';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'wertungsportal_cache';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'wertungsportal_elobase';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{wertungsportal_legend:hide},wertungsportal_switchedOff,wertungsportal_karteisperre_gaeste,wertungsportal_passive_ausblenden,wertungsportal_geburtsjahr_ausblenden,wertungsportal_geschlecht_ausblenden,wertungsportal_seite_spieler,wertungsportal_seite_turnier,wertungsportal_seite_verein,wertungsportal_seite_verband,wertungsportal_cache,wertungsportal_elobase,wertungsportal_playerDefaultImage,wertungsportal_playerImageSize,wertungsportal_clubDefaultImage,wertungsportal_clubImageSize,wertungsportal_eloLocal,wertungsportal_adminName,wertungsportal_adminMail,wertungsportal_apiSubject';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['wertungsportal_switchedOff'] = 'wertungsportal_switchedOffText';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['wertungsportal_cache'] = 'wertungsportal_cache_default,wertungsportal_cache_verband,wertungsportal_cache_referent';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['wertungsportal_elobase'] = 'wertungsportal_elobase_host,wertungsportal_elobase_db,wertungsportal_elobase_user,wertungsportal_elobase_pass,wertungsportal_elobase_url';

/**
 * fields
 */

// DeWIS-Abfrage ausschalten
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_switchedOff'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_switchedOff'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
		'submitOnChange'      => true
	)
);

// Text im Frontend bei aktiver Abschaltung
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_switchedOffText'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_switchedOffText'],
	'inputType'               => 'textarea',
	'eval'                    => array
	(
		'tl_class'            => 'long',
		'rte'                 => 'tinyMCE',
		'helpwizard'          => true
	),
	'explanation'             => 'insertTags',
);

// Cache ein- oder ausschalten
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cache'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'clr',
		'submitOnChange'      => true
	)
);

// Anzahl Stunden, die der Standardcache gültig ist
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cache_default'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache_default'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
		'rgxp'                => 'natural'
	)
);

// Anzahl Stunden, die der Verbandcache gültig ist
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cache_verband'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache_verband'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50 clr',
		'rgxp'                => 'natural'
	)
);

// Anzahl Stunden, die der Referentcache gültig ist
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_cache_referent'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_cache_referent'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
		'allowHtml'           => true
	)
);

// Karteikarte für Gäste sperren
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_karteisperre_gaeste'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_karteisperre_gaeste'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'w50 clr'
	)
);

// Karteikarte für Gäste sperren
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

// Alte Elobase-Datenbank aktivieren
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase'],
	'inputType'               => 'checkbox',
	'eval'                    => array
	(
		'tl_class'            => 'clr',
		'submitOnChange'      => true
	)
);

// Alte Elobase-Datenbank Host
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase_host'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_host'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
	)
);

// Alte Elobase-Datenbank Datenbank
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase_db'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_db'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
	)
);

// Alte Elobase-Datenbank Benutzer
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase_user'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_user'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
	)
);

// Alte Elobase-Datenbank Passwort
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase_pass'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_pass'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'w50',
	)
);

// Alte Elobase-Datenbank URL
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_elobase_url'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_elobase_url'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'tl_class'            => 'long clr',
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
		'tl_class'            => 'w50'
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

// Globaler E-Mail-Absendername
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_adminName'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_adminName'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => true, 
		'tl_class'            => 'w50 clr', 
	),
);

// Globale E-Mail-Absenderadresse
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_adminMail'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_adminMail'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'rgxp'                => 'email', 
		'mandatory'           => true, 
		'tl_class'            => 'w50', 
	),
);

// Betreff für API-Mails
$GLOBALS['TL_DCA']['tl_settings']['fields']['wertungsportal_apiSubject'] = array
(
	'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_apiSubject'],
	'inputType'               => 'text',
	'eval'                    => array
	(
		'mandatory'           => false, 
		'tl_class'            => 'clr long', 
	),
);
