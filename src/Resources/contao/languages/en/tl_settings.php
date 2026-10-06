<?php

/**
 * Englische Beschriftungen der Wertungsportal-Einstellungen.
 *
 * Bisher nur für die Insert-Tags und die Reklamationen übersetzt. Alle übrigen Felder des Moduls
 * gibt es ausschließlich auf Deutsch (languages/de/tl_settings.php); Contao
 * lädt Englisch immer zuerst und legt die gewählte Sprache darüber, eine
 * deutsche Oberfläche bleibt also unverändert.
 */

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_inserttags_legend'] = 'Insert tags';

$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_reklamation_legend'] = 'Complaints';
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_reklamation_email'] = array('E-mail address of the DSB administrator', 'Receives a blind copy of every complaint and is the recipient where a view names no rating officer. Without an address the link "Reklamation" does not appear.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_reklamation_name'] = array('Name of the DSB administrator', 'Used in the salutation and recipient line. Defaults to the sender name.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_reklamation_absender'] = array('Sender address for complaints', 'Complaint e-mails are sent from this address. If empty, the sender address under "E-mail sending" is used.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_reklamation_absendername'] = array('Sender name for complaints', 'Name shown as the sender of complaint e-mails. If empty, the sender name under "E-mail sending" is used.');
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_mitglieder_legend'] = 'Member group';
$GLOBALS['TL_LANG']['tl_settings']['wertungsportal_mitgliedergruppe'] = array('Member group for DSB members', 'Assigned to every member whose e-mail address occurs in the player records; removed from all others. Runs monthly or manually with "wertungsportal:mitgliedergruppe".');

$GLOBALS['TL_LANG']['tl_settings']['insert_verein_replaces'] = array('Replacements in club names', 'Used by the insert tag verein. The rows are applied from top to bottom, case-insensitively and only at word boundaries: Schachverein matches "Schachverein Tempo" but not "Schachvereinigung". Shortening happens afterwards. See docs/insert-tags.md.');
$GLOBALS['TL_LANG']['tl_settings']['insert_verein_search'] = array('Search for', 'Enter a leading or trailing space as +');
$GLOBALS['TL_LANG']['tl_settings']['insert_verein_replace'] = array('Replace with', 'Enter a leading or trailing space as +');
