<?php

declare(strict_types=1);

/**
 * Beispiel 4 — die DWZ-Liste seitenweise durchgehen.
 *
 * Listen können sehr lang werden. nu empfiehlt deshalb ausdrücklich, in
 * Abschnitten abzurufen: `limit` sagt, wieviele Datensätze kommen sollen,
 * `offset`, ab welchem. Die Gesamtzahl steht in `metadata.totalCount`.
 *
 * `alleSeiten()` nimmt einem das ab und liefert die Datensätze einzeln. Ein
 * Lauf über viele Seiten dauert länger, als ein Token lebt — das macht nichts,
 * zwischendurch wird über das Refresh-Token erneuert.
 *
 * Aufruf:
 *   php beispiel-4-dwzliste.php 40023          (ein Verein)
 *   php beispiel-4-dwzliste.php 400            (ein Verband: VKZ-Anfang genügt)
 *
 * Scope: dwz_liste
 */

$clients = require __DIR__.'/start.php';

$vkz = $argv[1] ?? '40023';
$client = $clients['liste'] ?? $clients['turniere'];

if (null === ($clients['liste'] ?? null)) {
	echo "Hinweis: keine eigene Freischaltung für die DWZ-Liste eingetragen —\n"
		."der Abruf läuft mit dem Token für Turniere.\n\n";
}

try {
	// ── Wer gehört zu dieser VKZ? ─────────────────────────────────────
	$vereine = $client->get('/dwz/dwzliste/clubs', array('vkz' => $vkz));

	foreach (array_slice($vereine['data'] ?? array(), 0, 5) as $verein) {
		printf(
			"%-7s %s Verband %s\n",
			(string) ($verein['clubVkz'] ?? ''),
			spalte((string) ($verein['clubName'] ?? ''), 42),
			(string) ($verein['parentFederation'] ?? $verein['federation'] ?? '')
		);
	}

	echo "\n";

	// ── Die Mitglieder, seitenweise ───────────────────────────────────
	$anzahl = 0;
	$summe = 0;
	$ohneDwz = 0;

	echo "Mitglieder mit DWZ (die ersten 20 von allen abgerufenen)\n";
	echo str_repeat('-', 74)."\n";

	foreach ($client->alleSeiten('/dwz/dwzliste/persons', array('vkz' => $vkz), 100) as $person) {
		++$anzahl;

		$dwz = (int) ($person['rating'] ?? 0);

		if ($dwz > 0) {
			$summe += $dwz;
		} else {
			++$ohneDwz;
		}

		if ($anzahl <= 20) {
			printf(
				"%-12s %s %s  %s\n",
				(string) ($person['nuLigaPersonId'] ?? ''),
				spalte((string) ($person['lastname'] ?? '').', '.(string) ($person['firstname'] ?? ''), 34),
				spalte($dwz > 0 ? (string) $dwz : '—', 4, true),
				(string) ($person['memberships'][0]['clubName'] ?? '')
			);
		}

		// Sicherheitsnetz für dieses Beispiel: Ein ganzer Landesverband hat
		// Zehntausende Einträge. In einer echten Anwendung läßt man die
		// Schleife durchlaufen — oder nimmt gleich die Zip-Datei, siehe
		// Beispiel 5
		if ($anzahl >= 500) {
			echo "… (bei 500 Einträgen abgebrochen)\n";

			break;
		}
	}

	printf(
		"\n%d Spieler abgerufen, davon %d ohne DWZ. Durchschnitt der übrigen: %d\n",
		$anzahl,
		$ohneDwz,
		$anzahl > $ohneDwz ? (int) round($summe / ($anzahl - $ohneDwz)) : 0
	);
} catch (DsbOAuth2Exception $e) {
	echo 'Fehler: '.$e->getMessage()."\n";

	exit(1);
}
