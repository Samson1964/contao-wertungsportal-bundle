<?php

/*
 * Deutsche Sprachdatei für die Systemwartung (Contao 4.13):
 * Purge-Job zum Leeren des Wertungsportal-Caches
 */

$GLOBALS['TL_LANG']['tl_maintenance_jobs']['wertungsportal'] = array('Wertungsportal-Cache leeren', 'Löscht den Cache der Wertungsportal-Abfragen (läuft automatisch nach jedem FIDE-Elo-Import). Der Cache kann in den Backend-Einstellungen deaktiviert werden.');

// Cache-Größe je Speicher an das Label anhängen
$GLOBALS['TL_LANG']['tl_maintenance_jobs']['wertungsportal'][0] .= \Schachbulle\ContaoWertungsportalBundle\Helper\API::calcCache();
