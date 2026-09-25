<?php

declare(strict_types=1);

/**
 * Beispiel 1 — DWZ-ausgewertete Turniere suchen.
 *
 * Zeigt den einfachsten Fall: ein Abruf mit Filtern, dann die Liste ausgeben.
 * Neben `data` liefert nu einen Block `metadata` mit der Gesamtzahl der Treffer
 * und einen Block `links` mit fertigen Adressen zum Blättern.
 *
 * Aufruf:
 *   php beispiel-1-turniere.php
 *   php beispiel-1-turniere.php 2026-01-01 2026-12-31 C
 *
 * Scope: dsb_tournament
 */

$clients = require __DIR__.'/start.php';

$von = $argv[1] ?? date('Y').'-01-01';
$bis = $argv[2] ?? date('Y-m-d');
$vkz = $argv[3] ?? '';

try {
	$antwort = $clients['turniere']->get('/dwz/tournaments', array(
		// Nur fertig ausgewertete Turniere. Weitere Werte: UNRATED, PENDING
		'ratingState' => 'RATED',
		'fromDate'    => $von,
		'toDate'      => $bis,

		// Verbands- oder Vereinskennzeichen; nu sucht nach dem Anfang, „C"
		// trifft also alles in Württemberg
		'vkz'         => $vkz,
		'limit'       => 15,
	));
} catch (DsbOAuth2Exception $e) {
	echo 'Fehler: '.$e->getMessage()."\n";

	exit(1);
}

$gesamt = (int) ($antwort['metadata']['totalCount'] ?? 0);

printf("Turniere vom %s bis %s%s: %d Treffer, hier die ersten %d\n\n", $von, $bis, '' !== $vkz ? ' (VKZ '.$vkz.'…)' : '', $gesamt, count($antwort['data']));

foreach ($antwort['data'] as $turnier) {
	printf(
		"%s  %s %-7s %2d Runden  %s\n",
		(string) ($turnier['enddate'] ?? '??'),
		spalte((string) ($turnier['label'] ?? ''), 52),
		(string) ($turnier['vkz'] ?? ''),
		(int) ($turnier['rounds'] ?? 0),
		(string) ($turnier['uuid'] ?? '')
	);
}

echo "\nMit der UUID aus der letzten Spalte geht es in Beispiel 2 weiter.\n";
