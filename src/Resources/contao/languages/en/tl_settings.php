<?php

/**
 * Englische Beschriftungen der Wertungsportal-Einstellungen.
 *
 * Bisher nur für die Insert-Tags übersetzt. Alle übrigen Felder des Moduls
 * gibt es ausschließlich auf Deutsch (languages/de/tl_settings.php); Contao
 * lädt Englisch immer zuerst und legt die gewählte Sprache darüber, eine
 * deutsche Oberfläche bleibt also unverändert.
 */

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_inserttags_legend'] = 'Insert tags';

$GLOBALS['TL_LANG']['tl_settings']['insert_verein_replaces'] = array('Replacements in club names', 'Used by the insert tag verein. The rows are applied from top to bottom, case-insensitively and only at word boundaries: Schachverein matches "Schachverein Tempo" but not "Schachvereinigung". Shortening happens afterwards. See docs/insert-tags.md.');
$GLOBALS['TL_LANG']['tl_settings']['insert_verein_search'] = array('Search for', 'Enter a leading or trailing space as +');
$GLOBALS['TL_LANG']['tl_settings']['insert_verein_replace'] = array('Replace with', 'Enter a leading or trailing space as +');
