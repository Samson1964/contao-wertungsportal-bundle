<?php

/**
 * Language file for tl_wertungsportal_reklamationen (back end module "Complaints")
 */

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bearbeitung_legend'] = 'Processing';

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['datum'] = array('Sent on', 'Time the member submitted the complaint.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberId'] = array('Member (ID)', 'ID of the member in tl_member.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberName'] = array('Sender', 'Name of the member at the time of sending.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberEmail'] = array('Reply address', 'E-mail address of the member; replies go there.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerName'] = array('Recipient', 'Who received the complaint.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerEmail'] = array('Recipient address', 'E-mail address(es) of the recipients.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerArt'] = array('Recipient is', 'Rating officer or — if none was given — the DSB administrator.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bcc'] = array('Blind copy to', 'Blind copy address; empty if the administrator was the recipient.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bereich'] = array('View', 'Page the complaint was sent from.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['url'] = array('Page address', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['turnierUuid'] = array('Tournament code', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['turnierName'] = array('Tournament', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['spielerId'] = array('NU number', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['spielerName'] = array('Player', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['betreff'] = array('Subject', '');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['text'] = array('Text', 'Message as sent, including the footer.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['gesendet'] = array('Sent', 'Whether sending the e-mail succeeded.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['fehler'] = array('Sending error', 'Message of the mailer if sending failed.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['erledigt'] = array('Done', 'The complaint has been dealt with.');
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['notiz'] = array('Note', 'Processing note. It stays in the back end and is sent to no one.');

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['arten'] = array
(
	'referent' => 'Rating officer',
	'admin'    => 'DSB administrator',
);

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bereiche'] = array
(
	'turnierauswertung'  => 'Tournament evaluation',
	'turnierergebnisse'  => 'Results',
	'spielberichtsbogen' => 'Score sheet',
	'karteikarte'        => 'Rating card',
	'verein'             => 'Club rating list',
	'rangliste'          => 'Federation ranking',
	'spielersuche'       => 'Player search',
	'vereinssuche'       => 'Club search',
	'turniersuche'       => 'Tournament search',
);

$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['edit'] = 'Edit complaint ID %s';
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['delete'] = 'Delete complaint ID %s';
$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['show'] = 'Details of complaint ID %s';
