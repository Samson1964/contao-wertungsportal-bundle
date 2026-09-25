<?php

declare(strict_types=1);

/**
 * Beispiel 3 — einen Spieler suchen und seine DWZ-Geschichte ausgeben.
 *
 * Hier treffen die **beiden Bereiche** der Schnittstelle aufeinander, und das
 * ist der wichtigste Punkt dieses Beispiels:
 *
 *   /dwz/dwzliste/persons          Suche und Stammdaten   → Scope dwz_liste
 *   /dwz/persons/{id}/history      Turnierhistorie        → Scope dsb_tournament
 *
 * Wer nur eine Freischaltung hat, kann den zweiten Teil trotzdem abrufen,
 * solange nu die DWZ-Liste ohne Anmeldung ausliefert. Deshalb fällt dieses
 * Beispiel auf den Turnier-Client zurück, wenn kein Client für die Liste da
 * ist — mit einem Hinweis.
 *
 * Aufruf:
 *   php beispiel-3-spieler.php Gerdts
 *   php beispiel-3-spieler.php Gerdts Martina
 *
 * Scope: dwz_liste und dsb_tournament
 */

$clients = require __DIR__.'/start.php';

$nachname = $argv[1] ?? 'Gerdts';
$vorname = $argv[2] ?? '';

// Ohne eigene Freischaltung für die Liste wird der Turnier-Client benutzt.
// Sein Token trägt den Scope dwz_liste nicht — das geht nur so lange gut, wie
// nu diese Endpunkte frei ausliefert
$listeClient = $clients['liste'] ?? null;

if (null === $listeClient) {
	echo "Hinweis: keine eigene Freischaltung für die DWZ-Liste eingetragen.\n"
		."Die Suche läuft mit dem Token für Turniere — möglich, solange nu die\n"
		."DWZ-Liste ohne Anmeldung ausliefert.\n\n";

	$listeClient = $clients['turniere'];
}

try {
	// ── Suchen ────────────────────────────────────────────────────────
	$treffer = $listeClient->get('/dwz/dwzliste/persons', array(
		'lastname'  => $nachname,
		'firstname' => $vorname,
		'limit'     => 10,
	));

	$gefunden = $treffer['data'] ?? array();

	if (!$gefunden) {
		printf("Zu „%s%s\" wurde niemand gefunden.\n", $nachname, '' !== $vorname ? ', '.$vorname : '');

		exit(0);
	}

	printf("%d Treffer (insgesamt %d)\n\n", count($gefunden), (int) ($treffer['metadata']['totalCount'] ?? 0));

	foreach ($gefunden as $person) {
		// Ohne DWZ fehlen `rating` und `index` GANZ — sie stehen nicht auf 0,
		// sie sind nicht da. Das trifft alle, die noch kein ausgewertetes
		// Turnier gespielt haben, und Nichtmitglieder
		$dwz = isset($person['rating'])
			? $person['rating'].' - '.(string) ($person['index'] ?? '')
			: 'ohne';

		printf(
			"%-12s %s %4s  DWZ %s  %s\n",
			(string) ($person['nuLigaPersonId'] ?? ''),
			spalte((string) ($person['lastname'] ?? '').', '.(string) ($person['firstname'] ?? ''), 30),
			(string) ($person['birthyear'] ?? ''),
			spalte($dwz, 10, true),
			(string) ($person['memberships'][0]['clubName'] ?? '')
		);
	}

	// ── Die Geschichte des ersten Treffers ────────────────────────────
	$nuId = (string) ($gefunden[0]['nuLigaPersonId'] ?? '');
	$historie = $clients['turniere']->get('/dwz/persons/'.$nuId.'/history');

	printf("\nDWZ-Entwicklung von %s (%s)\n", trim((string) ($gefunden[0]['firstname'] ?? '').' '.(string) ($gefunden[0]['lastname'] ?? '')), $nuId);
	echo str_repeat('-', 88)."\n";

	// `entries` sind die Turniere, `upgrades` die Umstufungen. Beides kommt
	// mit dem neuesten Eintrag zuerst
	foreach (array_slice($historie['entries'] ?? array(), 0, 12) as $eintrag) {
		printf(
			"%s  %s %s-%s → %s-%s  %s/%s\n",
			(string) ($eintrag['tournament']['enddate'] ?? '??'),
			spalte((string) ($eintrag['tournament']['label'] ?? ''), 44),
			spalte((string) ($eintrag['player']['ratingOld'] ?? '—'), 4, true),
			spalte((string) ($eintrag['player']['indexOld'] ?? '—'), 3),
			spalte((string) ($eintrag['player']['ratingNew'] ?? '—'), 4, true),
			spalte((string) ($eintrag['player']['indexNew'] ?? '—'), 3),
			(string) ($eintrag['player']['wins'] ?? '—'),
			(string) ($eintrag['player']['numberOfGames'] ?? '—')
		);
	}

	// Umstufungen sind Verwaltungsvorgänge ohne Turnier. **Vorsicht:** Sie
	// können ganz ohne Wertungsangabe kommen — nur Stichtag und Name. Bei
	// einem Spieler ohne DWZ ist die jährliche Umstufung folgenlos, wird aber
	// trotzdem gemeldet. Wer sie ungeprüft anzeigt, bekommt eine leere Zeile
	$umstufungen = array_filter(
		$historie['upgrades'] ?? array(),
		static function (array $up): bool {
			return (int) ($up['ratingNew'] ?? 0) > 0 || (int) ($up['ratingOld'] ?? 0) > 0;
		}
	);

	if ($umstufungen) {
		echo "\nUmstufungen\n";

		foreach ($umstufungen as $up) {
			printf(
				"%s  %s %s-%s → %s-%s\n",
				(string) ($up['referenceDate'] ?? '??'),
				spalte((string) ($up['name'] ?? ''), 44),
				spalte((string) ($up['ratingOld'] ?? '—'), 4, true),
				spalte((string) ($up['indexOld'] ?? '—'), 3),
				spalte((string) ($up['ratingNew'] ?? '—'), 4, true),
				spalte((string) ($up['indexNew'] ?? '—'), 3)
			);
		}
	}
} catch (DsbOAuth2Exception $e) {
	echo 'Fehler: '.$e->getMessage()."\n";

	exit(1);
}
