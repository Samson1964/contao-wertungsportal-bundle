<?php

use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsTournamentsModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsUpgradesModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsEvaluationModel;
use Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsMatchesModel;

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
	'wp-clubs'    => array
	(
		'tables'         => array
		(
			'tl_wertungsportal_clubs',
		),
		// Globale Operation: Altdaten-Übernahme aus tl_dwz_ver (key=importDwzVer)
		'importDwzVer'   => array('Schachbulle\ContaoWertungsportalBundle\Classes\AltdatenImport', 'runVereine'),
	),
	'wp-persons'    => array
	(
		'tables'         => array
		(
			'tl_wertungsportal_persons',
			'tl_wertungsportal_persons_memberships',
			'tl_wertungsportal_persons_tournaments',
			'tl_wertungsportal_persons_upgrades',
		),
		// Globale Operation: Personen-Import aus der Vereinsmitglieder-CSV (key=importPersons)
		'importPersons'  => array('Schachbulle\ContaoWertungsportalBundle\Classes\PersonenImport', 'run'),
		// Globale Operation: Spielerbild-Übernahme aus tl_dwz_spi (key=importPhotos)
		'importPhotos'   => array('Schachbulle\ContaoWertungsportalBundle\Classes\AltdatenImport', 'runPersonen'),
	),
	'wp-tournaments'    => array
	(
		'tables'         => array
		(
			'tl_wertungsportal_tournaments',
			'tl_wertungsportal_tournaments_evaluation',
			'tl_wertungsportal_tournaments_matches',
		),
	),
	'wp-elo'    => array
	(
		'tables'         => array
		(
			'tl_wertungsportal_elo',
		),
		// Globale Operation: FIDE-Elo-Import aus der Ratinglisten-XML (key=importElo)
		'importElo'      => array('Schachbulle\ContaoWertungsportalBundle\Classes\EloImport', 'run'),
	),
	// Die DeWIS-Module (tl_dwz_*) verwaltet wieder das contao-dewis-bundle,
	// das für die Übergangszeit parallel installiert ist
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
$GLOBALS['TL_CONFIG']['wertungsportal_debuglog'] = 0;

/**
 * -------------------------------------------------------------------------
 * Models registrieren                                  
 * -------------------------------------------------------------------------
 */

// Die DwzSpi-/DwzVer-Models bleiben registriert: Sie werden für die
// Altdaten-Übernahmen und die Foto-/Logo-Fallbacks weiter gelesen
// (die DCA-Verwaltung der tl_dwz_*-Tabellen liegt beim contao-dewis-bundle)
$GLOBALS['TL_MODELS']['tl_dwz_spi'] = \Schachbulle\ContaoWertungsportalBundle\Models\DwzSpiModel::class;
$GLOBALS['TL_MODELS']['tl_dwz_ver'] = \Schachbulle\ContaoWertungsportalBundle\Models\DwzVerModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_elo'] = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalEloModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_clubs'] = WertungsportalClubsModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_persons'] = WertungsportalPersonsModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_persons_memberships'] = WertungsportalPersonsMembershipsModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_persons_tournaments'] = WertungsportalPersonsTournamentsModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_persons_upgrades'] = WertungsportalPersonsUpgradesModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_tournaments'] = WertungsportalTournamentsModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_tournaments_evaluation'] = WertungsportalTournamentsEvaluationModel::class;
$GLOBALS['TL_MODELS']['tl_wertungsportal_tournaments_matches'] = WertungsportalTournamentsMatchesModel::class;
