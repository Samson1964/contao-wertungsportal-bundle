<?php
/**
 * Avatar for Contao Open Source CMS
 *
 * Copyright (C) 2013 Kirsten Roschanski
 * Copyright (C) 2013 Tristan Lins <http://bit3.de>
 *
 * @package    wertungsportal
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 */

/**
 * Add palette to tl_module
 */

$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_spielersuche'] = '{title_legend},name,headline,type;{config_legend},wertungsportal_searchfield;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_spieler'] = '{title_legend},name,headline,type;{wertungsportal_navigation_legend},wertungsportal_ohneNavigation;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_verein'] = '{title_legend},name,headline,type;{wertungsportal_navigation_legend},wertungsportal_ohneNavigation;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_verband'] = '{title_legend},name,headline,type;{wertungsportal_navigation_legend},wertungsportal_ohneNavigation;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_turnier'] = '{title_legend},name,headline,type;{wertungsportal_navigation_legend},wertungsportal_ohneNavigation;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_bestenliste'] = '{title_legend},name,headline,type;{bestenliste_legend},dwz_topcount,dwz_gender;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_token'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
// Wertungsreferenten als Tabelle (ab 1.52.0): Überschrift aus dem Modul, keine Linkleiste
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_referententabelle'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
// Wertungsreferenten als Baum (ab 1.53.0), wie die Tabelle
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_referentenbaum'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';


$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_searchfield'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_searchfield'],
	'inputType'                          => 'checkbox',
	'eval'                               => array
	(
		'tl_class'                       => 'w50',
		'isBoolean'                      => true,
	),
	'sql'                                => "char(1) NOT NULL default ''",
);

// Welcher Parameter enthält den Suchstring?
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_searchkey'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_searchkey'],
	'exclude'                            => true,
	'default'                            => 'q',
	'inputType'                          => 'text',
	'eval'                               => array
	(
		'mandatory'                      => false,
		'maxlength'                      => 16,
		'tl_class'                       => 'w50'
	),
	'sql'                                => "varchar(16) NOT NULL default 'q'"
);

// Suche in Spieler aktivieren/deaktivieren
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_spieler'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_spieler'],
	'inputType'                          => 'checkbox',
	'eval'                               => array
	(
		'tl_class'                       => 'w50 clr',
		'isBoolean'                      => true,
	),
	'sql'                                => "char(1) NOT NULL default ''",
);

// Suche in Verein aktivieren/deaktivieren
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_verein'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_verein'],
	'inputType'                          => 'checkbox',
	'eval'                               => array
	(
		'tl_class'                       => 'w50',
		'isBoolean'                      => true,
	),
	'sql'                                => "char(1) NOT NULL default ''",
);

// Suche in Verband aktivieren/deaktivieren
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_verband'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_verband'],
	'inputType'                          => 'checkbox',
	'eval'                               => array
	(
		'tl_class'                       => 'w50',
		'isBoolean'                      => true,
	),
	'sql'                                => "char(1) NOT NULL default ''",
);

// Suche in Turnier aktivieren/deaktivieren
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_turnier'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_turnier'],
	'inputType'                          => 'checkbox',
	'eval'                               => array
	(
		'tl_class'                       => 'w50',
		'isBoolean'                      => true,
	),
	'sql'                                => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['dwz_topcount'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['dwz_topcount'],
	'default'                            => 30,
	'exclude'                            => true,
	'inputType'                          => 'text',
	'eval'                               => array('tl_class'=>'w50', 'rgxp'=>'digit', 'maxlength'=>6),
	'sql'                                => "varchar(6) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['dwz_gender'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['dwz_gender'],
	'exclude'                            => true,
	'default'                            => 'm',
	'inputType'                          => 'select',
	'options'                            => &$GLOBALS['TL_LANG']['tl_module']['dwz_gender_options'],
	'eval'                               => array('tl_class'=>'w50'),
	'sql'                                => "char(1) NOT NULL default 'm'"
);

// Linkleiste „Spieler | Vereine | Verbände | Turniere" nicht ausgeben (ab 1.50.0) —
// etwa wenn sie als eigenes Inhaltselement über dem Modul steht, damit dazwischen
// etwas anderes Platz findet. Bis zum contao:migrate fehlt die Spalte; das Modul
// liest dann null und zeigt die Leiste wie bisher
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_ohneNavigation'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_ohneNavigation'],
	'exclude'                            => true,
	'inputType'                          => 'checkbox',
	'eval'                               => array
	(
		'tl_class'                       => 'w50',
		'isBoolean'                      => true,
	),
	'sql'                                => "char(1) NOT NULL default ''",
);

/**
 * DWZ-Archiv-Suche (ab 1.61.0, Fragment-Controller DwzArchivSucheController).
 * Ein Hinweistext über dem Formular steht als Text-Element im Artikel.
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_dwzarchiv'] = '{title_legend},name,headline,type;{wertungsportal_dwzarchiv_legend},wertungsportal_dwzarchiv_verband,wertungsportal_dwzarchiv_nachbarn,wertungsportal_dwzarchiv_formate,wertungsportal_dwzarchiv_neueste;{protected_legend:hide},protected;{expert_legend:hide},cssID';

// Fester Verband (Unterseiten der Landesverbände) oder leer = Auswahl im
// Formular. Die Namen kommen aus API::landesverbaende(), siehe
// DwzArchivSucheController::verbandsnamen()
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_dwzarchiv_verband'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_verband'],
	'exclude'                            => true,
	'inputType'                          => 'select',
	'options'                            => \Schachbulle\ContaoWertungsportalBundle\Controller\FrontendModule\DwzArchivSucheController::verbandsnamen(),
	'eval'                               => array
	(
		// Codes 0–9 sind in PHP Ganzzahlschlüssel; die Schlüssel sind trotzdem die Werte
		'isAssociative'                  => true,
		'includeBlankOption'             => true,
		'blankOptionLabel'               => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_auswahl'],
		'tl_class'                       => 'w50',
	),
	'sql'                                => "varchar(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_dwzarchiv_nachbarn'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_nachbarn'],
	'exclude'                            => true,
	'inputType'                          => 'text',
	'eval'                               => array('rgxp' => 'natural', 'maxlength' => 2, 'tl_class' => 'w50'),
	'sql'                                => "smallint(5) unsigned NOT NULL default 0",
);

// Angebotene Formatgruppen; keine Auswahl = alle
$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_dwzarchiv_formate'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_formate'],
	'exclude'                            => true,
	'inputType'                          => 'checkbox',
	'options'                            => \Schachbulle\ContaoWertungsportalBundle\DwzArchiv\Archivindex::GRUPPEN,
	'reference'                          => &$GLOBALS['TL_LANG']['MSC']['wp_dwzarchiv']['gruppen'],
	'eval'                               => array('multiple' => true, 'tl_class' => 'clr'),
	'sql'                                => 'blob NULL',
);

$GLOBALS['TL_DCA']['tl_module']['fields']['wertungsportal_dwzarchiv_neueste'] = array
(
	'label'                              => &$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_neueste'],
	'exclude'                            => true,
	'inputType'                          => 'checkbox',
	'eval'                               => array('tl_class' => 'clr', 'isBoolean' => true),
	'sql'                                => "char(1) NOT NULL default ''",
);
