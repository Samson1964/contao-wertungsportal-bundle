<?php

/*
 * English language file for tl_module (Contao 4.13)
 */

$GLOBALS['TL_LANG']['tl_module']['options_legend'] = 'Settings';

$GLOBALS['TL_LANG']['tl_module']['wertungsportal_spieler'] = array('Player search','Enable searching for players');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_verein'] = array('Club search','Enable searching for clubs');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_verband'] = array('Federation search','Enable searching for federations');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_turnier'] = array('Tournament search','Enable searching for tournaments');

/**
 * Navigation (since 1.50.0)
 */
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_navigation_legend'] = 'Navigation';
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_ohneNavigation'] = array('Hide navigation', 'Do not output the link bar "Players | Clubs | Federations | Tournaments", e.g. when it is placed above the module as a separate content element.');

/**
 * Best list
 */
$GLOBALS['TL_LANG']['tl_module']['bestenliste_legend'] = 'Best list';
$GLOBALS['TL_LANG']['tl_module']['dwz_topcount'] = array('Number', 'Number of players to show');
$GLOBALS['TL_LANG']['tl_module']['dwz_gender'] = array('Gender', 'Gender filter for the list. Default is all players.');

$GLOBALS['TL_LANG']['tl_module']['dwz_gender_options'] = array
(
	'm' => 'All players',
	'f' => 'Women only',
);

/**
 * DWZ archive search (since 1.61.0)
 */
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_legend'] = 'DWZ archive';
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_verband'] = array('Federation', 'Fixed federation, e.g. on the page of a state federation. "Show selection" lets the visitor choose.');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_auswahl'] = 'Show selection';
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_nachbarn'] = array('Neighbouring files', 'Additionally show this many days before and after (0 to 10).');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_formate'] = array('Formats', 'Formats offered. Without a selection, all are offered.');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_neueste'] = array('Show newest files', 'Below the result, list the newest files of each format.');
