<?php

/*
 * English language file for the maintenance module (Contao 4.13):
 * purge job for clearing the rating portal cache
 */

$GLOBALS['TL_LANG']['tl_maintenance_jobs']['wertungsportal'] = array('Purge rating portal cache', 'Deletes the cache of the rating portal queries (runs automatically after each FIDE Elo import). The cache can be disabled in the back end settings.');

// Append the cache size per store to the label
$GLOBALS['TL_LANG']['tl_maintenance_jobs']['wertungsportal'][0] .= \Schachbulle\ContaoWertungsportalBundle\Helper\API::calcCache();
