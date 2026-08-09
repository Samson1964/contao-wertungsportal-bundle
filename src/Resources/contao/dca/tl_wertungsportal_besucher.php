<?php

/**
 * Zähler der Besucherbremse — eine Zeile je IP-Adresse mit den Ständen der
 * drei laufenden Fenster.
 *
 * Bewusst OHNE Backend-Modul: Die Tabelle ist reine Arbeitsablage, sie enthält
 * keine Vorfälle. Wer wissen will, wer gebremst wurde, schaut in
 * tl_wertungsportal_sperren. Die Zeilen räumt der nächtliche Cronjob weg,
 * sobald eine Adresse einen Tag lang nichts mehr abgerufen hat.
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_besucher'] = array
(
	'config' => array
	(
		'dataContainer'    => 'Table',
		'closed'           => true,
		'notEditable'      => true,
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'ip' => 'unique',
				'tstamp' => 'index',
			)
		)
	),

	'fields' => array
	(
		'id' => array
		(
			'sql' => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
		'ip' => array
		(
			'sql' => "varchar(64) NOT NULL default ''"
		),
		'minuteStart' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
		'minuteAnzahl' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
		'stundeStart' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
		'stundeAnzahl' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
		'tagStart' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
		'tagAnzahl' => array
		(
			'sql' => "int(10) unsigned NOT NULL default 0"
		),
	)
);
