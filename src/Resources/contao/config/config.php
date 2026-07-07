<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * @package   bdf
 * @author    Frank Hoppe
 * @license   GNU/LGPL
 * @copyright Frank Hoppe 2014
 */

/**
 * Backend-Module
 */

$GLOBALS['BE_MOD']['wertungsportal'] = array
(
	'dwz-spieler'    => array
	(
		'tables'         => array
		(
			'tl_dwz_spi', 
			'tl_dwz_spiver',
			'tl_dwz_kar',
			'tl_dwz_inf',
			'tl_dwz_fid',
		),
		'icon'           => 'bundles/contaowertungsportal/images/icon_spieler.png',
	),
	'dwz-vereine'    => array
	(
		'tables'         => array
		(
			'tl_dwz_ver', 
		),
		'icon'           => 'bundles/contaowertungsportal/images/icon_vereine.png',
	),
	'dwz-turniere'    => array
	(
		'tables'         => array
		(
			'tl_dwz_tur', 
		),
		'icon'           => 'bundles/contaowertungsportal/images/icon_turniere.png',
	),
	'dwz-bearbeiter'    => array
	(
		'tables'         => array
		(
			'tl_dwz_bea', 
		),
		'icon'           => 'bundles/contaowertungsportal/images/icon_bearbeiter.png',
	),
);

/**
 * Frontend-Module
 */

$GLOBALS['FE_MOD']['wertungsportal'] = array
(
	'wertungsportal_spieler'         => 'Schachbulle\ContaoWertungsportalBundle\Classes\Spieler',
	'wertungsportal_verein'          => 'Schachbulle\ContaoWertungsportalBundle\Classes\Verein',
	'wertungsportal_verband'         => 'Schachbulle\ContaoWertungsportalBundle\Classes\Verband',
	'wertungsportal_turnier'         => 'Schachbulle\ContaoWertungsportalBundle\Classes\Turnier',
);

// http://de.contaowiki.org/Strukturierte_URLs
$GLOBALS['TL_HOOKS']['getPageIdFromUrl'][] = array('Schachbulle\ContaoWertungsportalBundle\Helper\API', 'getParamsFromUrl');

if(TL_MODE == 'BE') 
{
	$GLOBALS['TL_CSS'][] = 'bundles/contaowertungsportal/css/backend.css';
}

/**
 * -------------------------------------------------------------------------
 * Voreinstellungen Contao-BE System -> Einstellungen
 * -------------------------------------------------------------------------
 */

$GLOBALS['TL_CONFIG']['wertungsportal_cache'] = 1;
$GLOBALS['TL_CONFIG']['wertungsportal_karteisperre_gaeste'] = 0;
$GLOBALS['TL_CONFIG']['wertungsportal_passive_ausblenden'] = 0;
$GLOBALS['TL_CONFIG']['wertungsportal_geburtsjahr_ausblenden'] = 1;
$GLOBALS['TL_CONFIG']['wertungsportal_geschlecht_ausblenden'] = 1;

/**
 * -------------------------------------------------------------------------
 * Models registrieren                                  
 * -------------------------------------------------------------------------
 */

$GLOBALS['TL_MODELS']['tl_dwz_spi'] = \Schachbulle\ContaoWertungsportalBundle\Models\DwzSpiModel::class;
$GLOBALS['TL_MODELS']['tl_dwz_ver'] = \Schachbulle\ContaoWertungsportalBundle\Models\DwzVerModel::class;
