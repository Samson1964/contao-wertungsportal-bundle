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
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_spieler'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_verein'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_verband'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';
$GLOBALS['TL_DCA']['tl_module']['palettes']['wertungsportal_turnier'] = '{title_legend},name,headline,type;{protected_legend:hide},protected;{expert_legend:hide},cssID,align';


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

