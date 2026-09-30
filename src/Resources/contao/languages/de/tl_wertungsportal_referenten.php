<?php

/**
 * Beschriftungen der Tabelle tl_wertungsportal_referenten
 *
 * Seit 1.52.0 ohne Telefon, Straße, PLZ und Ort: Diese Angaben kommen nur noch
 * aus der zugeordneten Adresse der Adressverwaltung.
 */

$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['adresse']   = array('Adresse aus der Adressverwaltung', 'Aus der Adresse kommen Name, Anschrift, Telefonnummern und E-Mail-Adressen — bei jeder Ausgabe frisch und nur, soweit sie dort als öffentlich markiert sind. Mit gewählter Adresse darf der Nachname leer bleiben. Ohne Adresse erscheinen nur Name und Funktions-E-Mail.');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['nachname']  = array('Nachname', 'Nachname des Referenten');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['vorname']   = array('Vorname', 'Vorname des Referenten');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['nuId']      = array('nu-ID', 'Kennung der Person im Wertungsportal, z. B. NU4093214');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['email']     = array('Funktions-E-Mail', 'E-Mail-Adresse des Wertungsreferats, z. B. dwz@verband.de. Sie steht in den Ausgaben vor den E-Mail-Adressen der Adresse und empfängt die Reklamationen. Ohne sie gehen Reklamationen an die erste E-Mail-Adresse der Adresse.');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['verbaende'] = array('Zuständig für', 'Verbände, für die dieser Referent zuständig ist. Die Liste kommt aus dem Vereinsbestand: Verband ist, wessen Kennziffer auf 00 endet — also Landesverbände ebenso wie Bezirke. Ist die Liste leer, wurde der Vereinsbestand noch nicht abgeglichen.');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['published'] = array('Veröffentlichen', 'Nur veröffentlichte Referenten erscheinen in Ausgaben');

/**
 * Legenden
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['person_legend']    = 'Person';
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['kontakt_legend']   = 'Funktions-E-Mail';
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['verband_legend']   = 'Zuständigkeit';
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['published_legend'] = 'Veröffentlichung';
