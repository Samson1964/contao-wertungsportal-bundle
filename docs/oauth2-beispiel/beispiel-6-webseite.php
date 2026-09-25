<?php

declare(strict_types=1);

/**
 * Beispiel 6 — eine kleine Webseite mit Spielersuche.
 *
 * Die anderen Beispiele laufen auf der Kommandozeile. Dieses hier zeigt, wie
 * daraus eine Seite wird, die Besucher benutzen können — mit den drei Dingen,
 * die dabei wirklich zählen:
 *
 * 1. **Ein Zwischenspeicher.** Ohne ihn löst jeder Besucher einen Abruf bei nu
 *    aus. Bei zehn Besuchern in der Minute sind das zehn Abrufe für dieselbe
 *    Antwort. Hier liegt jede Antwort eine Stunde lang als Datei bereit.
 * 2. **Saubere Ausgabe.** Jeder Wert, der aus der Schnittstelle kommt, geht
 *    durch `htmlspecialchars()`. Sonst kann ein Name mit spitzen Klammern die
 *    Seite zerlegen.
 * 3. **Fehler abfangen.** Ist die Schnittstelle gerade nicht erreichbar, soll
 *    die Seite das sagen und nicht mit einer weißen Seite abbrechen.
 *
 * Aufruf: Die Datei auf einen Webserver legen und im Browser öffnen. Zum
 * Ausprobieren genügt der eingebaute Server von PHP — im Verzeichnis dieser
 * Datei:
 *
 *     php -S localhost:8000
 *
 * Dann http://localhost:8000/beispiel-6-webseite.php aufrufen.
 *
 * Scope: dwz_liste (fällt auf den Turnier-Client zurück, solange die DWZ-Liste
 * ohne Anmeldung erreichbar ist)
 */

$clients = require __DIR__.'/start.php';

$client = $clients['liste'] ?? $clients['turniere'];

/**
 * Holt eine Antwort — aus dem Zwischenspeicher, sonst von der Schnittstelle.
 *
 * Der Zwischenspeicher ist bewußt so einfach wie möglich: eine Datei je
 * Abfrage, benannt nach einem Fingerabdruck der Parameter. Wer schon einen
 * richtigen Zwischenspeicher hat (Redis, Memcached, den seines Rahmenwerks),
 * nimmt natürlich den.
 *
 * Wichtig ist nur das Prinzip: **Nicht bei jedem Seitenaufruf abrufen.** Die
 * DWZ ändert sich nicht im Minutentakt; eine Stunde alte Antwort ist für eine
 * Vereinsseite völlig in Ordnung.
 *
 * @param DsbOAuth2Client $client    Der eingerichtete Client
 * @param string          $pfad      Pfad des Endpunkts
 * @param array<string,mixed> $parameter Abfrageparameter
 * @param int             $dauer     Haltbarkeit in Sekunden (Vorgabe: eine Stunde)
 *
 * @return array<string,mixed> Die Antwort von nu
 *
 * @throws DsbOAuth2Exception wenn nichts im Zwischenspeicher liegt und der
 *                            Abruf scheitert
 */
function mitZwischenspeicher(DsbOAuth2Client $client, string $pfad, array $parameter = array(), int $dauer = 3600): array
{
	$verzeichnis = __DIR__.'/zwischenspeicher';

	if (!is_dir($verzeichnis)) {
		@mkdir($verzeichnis, 0770, true);
	}

	$datei = $verzeichnis.'/'.sha1($pfad.'?'.http_build_query($parameter)).'.json';

	if (is_file($datei) && filemtime($datei) > time() - $dauer) {
		$inhalt = json_decode((string) file_get_contents($datei), true);

		if (is_array($inhalt)) {
			return $inhalt;
		}
	}

	$antwort = $client->get($pfad, $parameter);

	@file_put_contents($datei, json_encode($antwort));

	return $antwort;
}

/**
 * Gibt einen Wert aus, der aus der Schnittstelle kommt — HTML-sicher.
 *
 * @param mixed $wert Beliebiger Wert; null und Zahlen sind erlaubt
 *
 * @return string Für die Ausgabe im HTML vorbereitet
 */
function h($wert): string
{
	return htmlspecialchars((string) $wert, ENT_QUOTES, 'UTF-8');
}

$suche = trim((string) ($_GET['nachname'] ?? ''));
$treffer = array();
$fehler = '';
$ausSpeicher = false;

if ('' !== $suche) {
	try {
		$antwort = mitZwischenspeicher($client, '/dwz/dwzliste/persons', array('lastname' => $suche, 'limit' => 25));
		$treffer = $antwort['data'] ?? array();
	} catch (DsbOAuth2Exception $e) {
		// Dem Besucher sagt man, daß es gerade nicht geht — die Einzelheiten
		// gehören ins Protokoll, nicht auf die Seite
		error_log('DWZ-Abruf gescheitert: '.$e->getMessage());
		$fehler = 'Die Daten sind gerade nicht abrufbar. Bitte später noch einmal versuchen.';
	}
}

?><!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Spieler suchen</title>
	<style>
		body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 52rem; padding: 0 1rem; line-height: 1.5; }
		table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; }
		th, td { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid #ddd; }
		th { background: #f3f3f3; }
		td.zahl { text-align: right; font-variant-numeric: tabular-nums; }
		.fehler { background: #fdf0f0; border-left: 4px solid #c33; padding: .8rem 1rem; }
		form { display: flex; gap: .5rem; }
		input { flex: 1; padding: .5rem; font-size: 1rem; }
		button { padding: .5rem 1.2rem; font-size: 1rem; }
	</style>
</head>
<body>

<h1>Spieler suchen</h1>

<form method="get">
	<input type="text" name="nachname" value="<?= h($suche) ?>" placeholder="Nachname" autofocus>
	<button type="submit">Suchen</button>
</form>

<?php if ('' !== $fehler): ?>
	<p class="fehler"><?= h($fehler) ?></p>
<?php elseif ('' !== $suche && !$treffer): ?>
	<p>Zu „<?= h($suche) ?>" wurde niemand gefunden.</p>
<?php elseif ($treffer): ?>
	<table>
		<tr>
			<th>Name</th>
			<th>Jahrgang</th>
			<th>Verein</th>
			<th class="zahl">DWZ</th>
		</tr>
		<?php foreach ($treffer as $person): ?>
			<tr>
				<td><?= h(($person['lastname'] ?? '').', '.($person['firstname'] ?? '')) ?></td>
				<td><?= h($person['birthyear'] ?? '') ?></td>
				<td><?= h($person['memberships'][0]['clubName'] ?? '') ?></td>

				<?php // Ohne DWZ fehlt das Feld ganz — es steht nicht auf 0 ?>
				<td class="zahl"><?= isset($person['rating']) ? h($person['rating']) : '—' ?></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<p><small>Angaben aus dem DWZ-System des Deutschen Schachbunds, höchstens eine Stunde alt.</small></p>
<?php endif; ?>

</body>
</html>
