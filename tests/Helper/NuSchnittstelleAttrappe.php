<?php

/**
 * Nachbau der Schnittstelle von nu für die Prüfungen in OAuth2ClientTest —
 * gestartet als Router des eingebauten PHP-Servers (`php -S … NuSchnittstelleAttrappe.php`).
 *
 * Er spielt genau das nach, was für die Anmeldung zählt:
 *
 * - `POST /auth/token`: gibt Token aus (client_credentials und refresh_token),
 *   prüft Kennung, Geheimnis und — falls für die Kennung vorgegeben — den
 *   Scope. Jede Ausgabe erhält eine laufende Nummer, damit die Prüfungen
 *   sehen, welches Token wofür benutzt wurde.
 * - `GET /rs/dwz/…`: prüft das mitgeschickte Token gegen die Kennungen, die
 *   für den jeweiligen Bereich zugelassen sind. Die DWZ-Liste kann „offen"
 *   sein (so war es bis September 2026) — dann geht es auch ohne Token.
 * - `GET /rs/dwz/dwzliste/download/…`: liefert ein kleines, gültiges Zip.
 *
 * Gesteuert wird er über `konfig.json` in dem Verzeichnis, das die
 * Umgebungsvariable NU_ATTRAPPE nennt; jede Anfrage landet als JSON-Zeile in
 * `anfragen.jsonl` daneben. Die echten Zugangsdaten von nu werden für die
 * Prüfungen nie gebraucht.
 *
 * Keine Klasse und kein Test: PHPUnit sammelt nur Dateien auf `Test.php`.
 */

$verzeichnis = (string) getenv('NU_ATTRAPPE');
$konfig = json_decode((string) @file_get_contents($verzeichnis.'/konfig.json'), true) ?: array();
$pfad = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$protokoll = static function (array $eintrag) use ($verzeichnis): void {
	file_put_contents($verzeichnis.'/anfragen.jsonl', json_encode($eintrag)."\n", FILE_APPEND | LOCK_EX);
};

$antwort = static function (int $status, array $daten): bool {
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($daten);

	return true;
};

// Laufende Nummer für ausgegebene Token
$nummer = static function () use ($verzeichnis): int {
	$datei = $verzeichnis.'/zaehler.txt';
	$n = (int) @file_get_contents($datei) + 1;
	file_put_contents($datei, (string) $n);

	return $n;
};

// ─────────────── Token-Endpunkt ───────────────
if ('/auth/token' === $pfad) {
	$felder = $_POST;
	$kennung = (string) ($felder['client_id'] ?? '');
	$art = (string) ($felder['grant_type'] ?? '');

	$protokoll(array(
		'art'    => 'token',
		'grant'  => $art,
		'client' => $kennung,
		'scope'  => array_key_exists('scope', $felder) ? (string) $felder['scope'] : null,
	));

	$daten = $konfig['kennungen'][$kennung] ?? null;

	if (null === $daten || ($daten['geheim'] ?? '') !== (string) ($felder['client_secret'] ?? '')) {
		return $antwort(401, array('error' => 'invalid_client', 'error_description' => 'Bad client credentials'));
	}

	if ('client_credentials' === $art && isset($daten['scope']) && ($felder['scope'] ?? null) !== $daten['scope']) {
		return $antwort(403, array('error' => 'access_denied', 'error_description' => 'Wrong or no scope(s) provided'));
	}

	if ('refresh_token' === $art && 0 !== strpos((string) ($felder['refresh_token'] ?? ''), $kennung.'-erneuerung-')) {
		return $antwort(400, array('error' => 'invalid_grant', 'error_description' => 'Refresh Token already used or invalid'));
	}

	$n = $nummer();

	return $antwort(200, array(
		'access_token'  => $kennung.'-zugang-'.$n,
		'refresh_token' => $kennung.'-erneuerung-'.$n,
		'expires_in'    => 300,
		'token_type'    => 'bearer',
	));
}

// ─────────────── Abrufe ───────────────
$kopf = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ('' === $kopf && function_exists('getallheaders')) {
	foreach (getallheaders() as $name => $wert) {
		if (0 === strcasecmp($name, 'Authorization')) {
			$kopf = (string) $wert;
		}
	}
}

$token = preg_match('/^Bearer (.+)$/', $kopf, $treffer) ? $treffer[1] : '';
$kennung = '' !== $token ? (string) preg_replace('/-zugang-\d+$/', '', $token) : '';

$protokoll(array('art' => 'abruf', 'pfad' => $pfad, 'token' => $token));

$bereich = 0 === strpos($pfad, '/rs/dwz/dwzliste') ? 'dwzliste' : (0 === strpos($pfad, '/rs/dwz/') ? 'turniere' : '');

if ('' === $bereich) {
	return $antwort(404, array('error' => 'not found'));
}

$zugelassen = in_array($kennung, $konfig['zugelassen'][$bereich] ?? array(), true)
	&& !in_array($token, $konfig['widerrufen'] ?? array(), true);
$offen = 'dwzliste' === $bereich && !empty($konfig['dwzliste_offen']) && '' === $token;

if (!$zugelassen && !$offen) {
	return $antwort(401, array('message' => 'HTTP 401 Unauthorized'));
}

if (0 === strpos($pfad, '/rs/dwz/dwzliste/download/')) {
	$zip = $verzeichnis.'/liefer.zip';
	$archiv = new ZipArchive();
	$archiv->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	$archiv->addFromString('spieler.csv', "ID,ZPS\r\nNU1,10614\r\n");
	$archiv->close();

	header('Content-Type: application/zip');
	readfile($zip);

	return true;
}

return $antwort(200, array('data' => array(array('bereich' => $bereich)), 'metadata' => array('totalCount' => 1)));
