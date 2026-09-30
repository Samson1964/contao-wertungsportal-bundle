<?php

/**
 * Sprachdatei für tl_wertungsportal_reklamationen (Backend-Modul „Reklamationen")
 */

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bearbeitung_legend'] = 'Bearbeitung';

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['datum'] = array('Gesendet am', 'Zeitpunkt, zu dem das Mitglied die Reklamation abgeschickt hat.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberId'] = array('Mitglied (ID)', 'Kennung des Mitglieds in tl_member.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberName'] = array('Absender', 'Name des Mitglieds zum Zeitpunkt des Absendens.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberEmail'] = array('Antwortadresse', 'E-Mail-Adresse des Mitglieds; an sie gehen Antworten.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerName'] = array('Empfänger', 'An wen die Reklamation ging.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerEmail'] = array('Empfängeradresse', 'E-Mail-Adresse(n) der Empfänger.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerArt'] = array('Empfänger ist', 'Wertungsreferent oder — wenn keiner genannt war — der DSB-Admin.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bcc'] = array('Blindkopie an', 'Adresse der Blindkopie; leer, wenn der Admin selbst Empfänger war.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bereich'] = array('Ansicht', 'Seite, von der aus die Reklamation abgeschickt wurde.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['url'] = array('Adresse der Seite', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['turnierUuid'] = array('Turniercode', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['turnierName'] = array('Turnier', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['spielerId'] = array('NU-Nummer', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['spielerName'] = array('Spieler', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['betreff'] = array('Betreff', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['text'] = array('Text', 'Nachricht, wie sie verschickt wurde, samt Fußzeile.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['gesendet'] = array('Verschickt', 'Ob der Mailversand geklappt hat.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['fehler'] = array('Fehler beim Versand', 'Meldung des Mailversands, wenn er gescheitert ist.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['erledigt'] = array('Erledigt', 'Die Reklamation ist bearbeitet.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['notiz'] = array('Notiz', 'Vermerk zur Bearbeitung. Er bleibt im Backend und geht an niemanden.');

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['arten'] = array
(
	'referent' => 'Wertungsreferent',
	'admin'    => 'DSB-Admin',
);

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bereiche'] = array
(
	'turnierauswertung'  => 'Turnierauswertung',
	'turnierergebnisse'  => 'Ergebnisliste',
	'spielberichtsbogen' => 'Spielberichtsbogen',
	'karteikarte'        => 'DWZ-Karteikarte',
	'verein'             => 'DWZ-Liste eines Vereins',
	'rangliste'          => 'Rangliste eines Verbandes',
	'spielersuche'       => 'Spielersuche',
	'vereinssuche'       => 'Vereinssuche',
	'turniersuche'       => 'Turniersuche',
);

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['edit'] = 'Reklamation ID %s bearbeiten';
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['delete'] = 'Reklamation ID %s löschen';
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['show'] = 'Details der Reklamation ID %s';
