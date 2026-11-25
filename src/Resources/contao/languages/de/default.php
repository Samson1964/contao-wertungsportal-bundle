<?php

/**
 * Texte der DWZ-Archiv-Suche im Frontend (ab 1.61.0).
 *
 * `gruppen` dient zugleich als Beschriftung der Formate in tl_module.
 * In `frueher`/`spaeter` steht %d für die Zahl der Tage; die Fassungen mit
 * der Endung 1 gelten für genau einen Tag.
 */
$GLOBALS['TL_LANG']['MSC']['wp_dwzarchiv'] = array
(
	'datum'     => 'Datum',
	'verband'   => 'Verband',
	'format'    => 'Format',
	'alle'      => 'alle Formate',
	'suchen'    => 'Dateien anzeigen',
	'ergebnis'  => 'Dateien zum %s',
	'keine'     => 'Für diesen Verband gibt es keine Dateien.',
	'datei'     => 'Datei',
	'tag'       => 'Stand',
	'abstand'   => 'Abstand',
	'groesse'   => 'Größe',
	'quelle'    => 'Quelle',
	'genau'     => 'genau',
	'frueher1'  => '1 Tag früher',
	'frueher'   => '%d Tage früher',
	'spaeter1'  => '1 Tag später',
	'spaeter'   => '%d Tage später',
	'neueste'   => 'Neueste Dateien',
	'gruppen'   => array
	(
		'csv'    => 'CSV',
		'dos'    => 'DOS',
		'sql'    => 'SQL',
		'sc7'    => 'Swiss-Chess bis 7',
		'sc8'    => 'Swiss-Chess 8 und 9',
		'sc10'   => 'Swiss-Chess 10.0 bis 10.02',
		'sc1003' => 'Swiss-Chess ab 10.03',
	),
	'quellen'   => array
	(
		'wertungsportal' => 'Wertungsportal',
		'dewis'          => 'DeWIS',
	),
);
