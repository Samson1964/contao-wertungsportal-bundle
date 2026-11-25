<?php 

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2014 Leo Feyer
 *
 */


/**
 * Back end modules
 */
$GLOBALS['TL_LANG']['tl_module']['options_legend'] = 'Einstellungen';

$GLOBALS['TL_LANG']['tl_module']['wertungsportal_spieler'] = array('Spielersuche','Suche nach Spielern aktivieren');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_verein'] = array('Vereinssuche','Suche nach Vereinen aktivieren');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_verband'] = array('Verbandssuche','Suche nach Verbänden aktivieren');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_turnier'] = array('Turniersuche','Suche nach Turnieren aktivieren');

/**
 * Navigation (ab 1.50.0)
 */
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_navigation_legend'] = 'Navigation';
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_ohneNavigation'] = array('Navigation ausblenden', 'Die Linkleiste „Spieler | Vereine | Verbände | Turniere“ nicht ausgeben, etwa wenn sie als eigenes Inhaltselement über dem Modul steht.');

/**
 * Bestenliste
 */
$GLOBALS['TL_LANG']['tl_module']['bestenliste_legend'] = 'Bestenliste';
$GLOBALS['TL_LANG']['tl_module']['dwz_topcount'] = array('Anzahl', 'Anzahl Spieler festlegen');
$GLOBALS['TL_LANG']['tl_module']['dwz_gender'] = array('Geschlecht', 'Geschlecht für die Liste festlegen. Standard sind alle Spieler.');

$GLOBALS['TL_LANG']['tl_module']['dwz_gender_options'] = array
(
	'm' => 'Alle Spieler',
	'f' => 'Nur Frauen',
);

/**
 * DWZ-Archiv-Suche (ab 1.61.0)
 */
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_legend'] = 'DWZ-Archiv';
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_verband'] = array('Verband', 'Fester Verband, etwa auf der Unterseite eines Landesverbands. „Auswahl anzeigen“ läßt den Besucher wählen.');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_auswahl'] = 'Auswahl anzeigen';
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_nachbarn'] = array('Nachbardateien', 'So viele weitere Tage davor und danach zusätzlich anzeigen (0 bis 10).');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_formate'] = array('Formate', 'Angebotene Formate. Ohne Auswahl werden alle angeboten.');
$GLOBALS['TL_LANG']['tl_module']['wertungsportal_dwzarchiv_neueste'] = array('Neueste Dateien anzeigen', 'Unter dem Ergebnis die jüngsten Dateien je Format ausgeben.');
