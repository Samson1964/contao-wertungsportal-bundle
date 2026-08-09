<?php

/**
 * Beschriftungen der Tabelle tl_wertungsportal_sperren
 */

$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['zeitpunkt']  = array('Zeitpunkt', 'Wann die Sperre ausgelöst wurde');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['datum']      = array('Tag', 'Tag der Sperre');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['ip']         = array('IP-Adresse', 'Adresse des Besuchers, wie Contao sie speichert (ggf. gekürzt)');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['useragent']  = array('Browserkennung', 'Kennung, mit der sich der Aufrufer gemeldet hat');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['memberId']   = array('Mitglieds-ID', 'Kennung des angemeldeten Mitglieds (tl_member.id), 0 = nicht angemeldet');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['memberName'] = array('Mitglied', 'Anmeldename des Mitglieds');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['grund']      = array('Überschrittenes Fenster', 'Welche der drei Grenzen gerissen wurde');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['anzahl']     = array('Abrufe', 'Abrufe im überschrittenen Fenster');
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['grenze']     = array('Grenze', 'Eingestellter Höchstwert');

// Auswahlwerte des Feldes grund
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['gruende'] = array
(
	'minute' => 'je Minute',
	'stunde' => 'je Stunde',
	'tag'    => 'je Tag',
);

// Globale Operation
$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['aufraeumen'] = array('Alte Einträge löschen', 'Einträge löschen, die älter als 90 Tage sind');
