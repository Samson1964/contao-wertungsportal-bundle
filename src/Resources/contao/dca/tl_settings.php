<?php

/**
 * palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{wertungsportal_legend:hide},wertungsportal_apiBasisURL,wertungsportal_tokenURL,wertungsportal_clientID,wertungsportal_clientSecret,wertungsportal_scopeListe';

/**
 * fields
 */

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
