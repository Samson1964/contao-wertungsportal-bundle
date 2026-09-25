<?php

declare(strict_types=1);

/**
 * Beispiel 2 — ein Turnier vollständig: Kopfdaten, DWZ-Auswertung, Partien.
 *
 * Zeigt mehrere Abrufe hintereinander mit demselben Token. Genau dafür ist die
 * Tokendatei da: Der zweite und dritte Abruf holen kein neues Token, sondern
 * nehmen das vorhandene.
 *
 * Aufruf:
 *   php beispiel-2-turnier.php                 (nimmt das jüngste ausgewertete Turnier)
 *   php beispiel-2-turnier.php <turnier-uuid>
 *
 * Scope: dsb_tournament
 */

$clients = require __DIR__.'/start.php';
$client = $clients['turniere'];

/**
 * Übersetzt einen Ergebniscode der Schnittstelle in die übliche Schreibweise.
 *
 * Die Codes mit PLUS oder MINUS bezeichnen kampflose Partien; sie fließen
 * nicht in die DWZ-Berechnung ein.
 *
 * @param string $code Ergebniscode, etwa `WHITE_WINS`
 *
 * @return string Ergebnis als Text; unbekannte Codes bleiben stehen
 */
function ergebnis(string $code): string
{
	$tabelle = array(
		'WHITE_WINS'  => '1:0',
		'BLACK_WINS'  => '0:1',
		'REMIS'       => '½:½',
		'PLUS_MINUS'  => '+:-',
		'MINUS_PLUS'  => '-:+',
		'MINUS_MINUS' => '-:-',
		'ZERO_MINUS'  => '0:-',
		'MINUS_ZERO'  => '-:0',
		'ZERO_HALF'   => '0:½',
		'HALF_ZERO'   => '½:0',
	);

	return $tabelle[$code] ?? $code;
}

/**
 * Setzt Vor- und Nachnamen eines Spielerblocks zusammen.
 *
 * @param array<string,mixed>|null $spieler Block `whitePlayer` oder `blackPlayer`
 *
 * @return string „Nachname, Vorname"; „—" wenn kein Spieler angegeben ist
 */
function name(?array $spieler): string
{
	if (!$spieler) {
		return '—';
	}

	return trim((string) ($spieler['lastname'] ?? '').', '.(string) ($spieler['firstname'] ?? ''), ', ');
}

try {
	$uuid = (string) ($argv[1] ?? '');

	// Ohne Angabe das jüngste ausgewertete Turnier suchen
	if ('' === $uuid) {
		$liste = $client->get('/dwz/tournaments', array('ratingState' => 'RATED', 'limit' => 1));
		$uuid = (string) ($liste['data'][0]['uuid'] ?? '');

		if ('' === $uuid) {
			echo "Es wurde kein ausgewertetes Turnier gefunden.\n";

			exit(1);
		}
	}

	// ── Kopfdaten ─────────────────────────────────────────────────────
	$turnier = $client->get('/dwz/tournaments/'.$uuid);
	$titel = (string) ($turnier['label'] ?? '');

	printf("%s\n%s\n", $titel, str_repeat('=', mb_strlen($titel)));
	printf("Ort:        %s\n", (string) ($turnier['location'] ?? '—'));
	printf("Zeitraum:   %s bis %s\n", (string) ($turnier['startdate'] ?? '?'), (string) ($turnier['enddate'] ?? '?'));
	printf("VKZ:        %s\n", (string) ($turnier['vkz'] ?? '—'));
	printf("Runden:     %d\n", (int) ($turnier['rounds'] ?? 0));
	printf("Teilnehmer: %d in %d Partien\n\n", (int) ($turnier['playerCount'] ?? 0), (int) ($turnier['matchCount'] ?? 0));

	// ── Die DWZ-Auswertung ────────────────────────────────────────────
	// Antwort: { "tournament": {…}, "players": [ … ] }
	$auswertung = $client->get('/dwz/tournaments/'.$uuid.'/evaluation');
	$spieler = $auswertung['players'] ?? array();

	echo "DWZ-Auswertung, die ersten zehn Teilnehmer\n";
	echo str_repeat('-', 92)."\n";
	printf("%s %s %s %s %s %s %s\n", spalte('Name', 28), spalte('Verein', 24), spalte('DWZ alt', 10, true), spalte('Pkt', 5, true), spalte('Part', 5, true), spalte('We', 7, true), spalte('DWZ neu', 10, true));

	foreach (array_slice($spieler, 0, 10) as $eintrag) {
		printf(
			"%s %s %s %s %s %s %s\n",
			spalte(name($eintrag), 28),
			spalte((string) ($eintrag['clubName'] ?? ''), 24),
			spalte((string) ($eintrag['ratingOldDisplayString'] ?? '—'), 10, true),
			spalte((string) ($eintrag['wins'] ?? '—'), 5, true),
			spalte((string) ($eintrag['numberOfGames'] ?? '—'), 5, true),
			spalte((string) ($eintrag['winsExpectedDisplayString'] ?? '—'), 7, true),

			// Nichtmitglieder („Textuelle", member = false) bekommen keine neue
			// DWZ. Ihre Eingangswertung steht in ratingOldDisplayString, eine
			// errechnete Zahl auch mal in Klammern in ratingNewDisplayString
			spalte((string) ($eintrag['ratingNewDisplayString'] ?? '—'), 10, true)
		);
	}

	// ── Die Partien einer Runde ───────────────────────────────────────
	// Antwort: { "data": [ { "round", "result", "expected",
	//            "whitePlayer": {…}, "blackPlayer": {…} } ], "metadata": {…} }
	$partien = $client->get('/dwz/tournaments/'.$uuid.'/matches', array('round' => 1));

	printf("\nPartien der 1. Runde: %d insgesamt, hier die ersten fünf\n", (int) ($partien['metadata']['totalCount'] ?? count($partien['data'] ?? array())));

	foreach (array_slice($partien['data'] ?? array(), 0, 5) as $partie) {
		printf(
			"  %s – %s %s   Erwartung Weiß %.3f\n",
			spalte(name($partie['whitePlayer'] ?? null), 28),
			spalte(name($partie['blackPlayer'] ?? null), 28),
			spalte(ergebnis((string) ($partie['result'] ?? '')), 5),

			// `expected` ist IMMER die Gewinnerwartung von Weiß;
			// für Schwarz ist es 1 − expected
			(float) ($partie['expected'] ?? 0)
		);
	}
} catch (DsbOAuth2Exception $e) {
	echo 'Fehler: '.$e->getMessage()."\n";

	exit(1);
}
