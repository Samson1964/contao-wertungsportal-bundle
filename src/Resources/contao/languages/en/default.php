<?php

/**
 * Frontend texts of the DWZ archive search (since 1.61.0).
 *
 * `gruppen` also labels the formats in tl_module. In `frueher`/`spaeter`,
 * %d is the number of days; the keys ending in 1 are used for exactly one day.
 */
$GLOBALS['TL_LANG']['MSC']['wp_dwzarchiv'] = array
(
	'datum'     => 'Date',
	'verband'   => 'Federation',
	'format'    => 'Format',
	'alle'      => 'all formats',
	'suchen'    => 'Show files',
	'ergebnis'  => 'Files for %s',
	'keine'     => 'There are no files for this federation.',
	'datei'     => 'File',
	'tag'       => 'As of',
	'abstand'   => 'Distance',
	'groesse'   => 'Size',
	'quelle'    => 'Source',
	'genau'     => 'exact',
	'frueher1'  => '1 day earlier',
	'frueher'   => '%d days earlier',
	'spaeter1'  => '1 day later',
	'spaeter'   => '%d days later',
	'neueste'   => 'Newest files',
	'gruppen'   => array
	(
		'csv'    => 'CSV',
		'dos'    => 'DOS',
		'sql'    => 'SQL',
		'sc7'    => 'Swiss-Chess up to 7',
		'sc8'    => 'Swiss-Chess 8 and 9',
		'sc10'   => 'Swiss-Chess 10.0 to 10.02',
		'sc1003' => 'Swiss-Chess 10.03 and later',
	),
	'quellen'   => array
	(
		'wertungsportal' => 'Rating portal',
		'dewis'          => 'DeWIS',
	),
);
