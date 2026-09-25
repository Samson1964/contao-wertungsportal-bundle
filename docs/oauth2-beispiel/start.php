<?php

declare(strict_types=1);

/**
 * Gemeinsamer Anfang aller Beispiele: Klasse einbinden, Zugangsdaten lesen,
 * Clients bereitstellen.
 *
 * Wird von den Beispieldateien mit `require` eingebunden und liefert ein Array
 * mit zwei Einträgen:
 *
 *   `turniere`  Client für /dwz/tournaments und /dwz/persons
 *   `liste`     Client für /dwz/dwzliste — oder null, wenn dafür keine
 *               Zugangsdaten eingetragen sind. Dann werden diese Endpunkte
 *               ohne Anmeldung abgerufen, was zur Zeit noch möglich ist.
 *
 * Deckt EINE Kennung beide Bereiche ab, entsteht auch nur EIN Client: Er
 * fordert beide Scopes in einer Anfrage an, und beide Einträge zeigen auf ihn.
 * Zwei getrennte Token-Familien derselben Kennung würden sich gegenseitig die
 * Refresh-Token entwerten, und das Kontingent gilt je Kennung.
 *
 * In einer eigenen Anwendung braucht man diese Datei nicht; dort entsteht der
 * Client an einer Stelle, die zum eigenen Aufbau paßt.
 */

require __DIR__.'/DsbOAuth2Client.php';

// Im Browser als Text ausgeben statt als HTML
if ('cli' !== PHP_SAPI) {
	header('Content-Type: text/plain; charset=utf-8');
}

$konfigdatei = __DIR__.'/zugangsdaten.php';

if (!is_file($konfigdatei)) {
	echo "Es fehlen die Zugangsdaten.\n\n"
		."Bitte zugangsdaten.beispiel.php nach zugangsdaten.php kopieren und dort\n"
		."Client-ID und Client Secret eintragen.\n";

	exit(1);
}

$konfig = require $konfigdatei;

$verzeichnis = (string) ($konfig['tokenverzeichnis'] ?? __DIR__.'/token');

if (!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0770, true) && !is_dir($verzeichnis)) {
	echo 'Das Verzeichnis für die Token läßt sich nicht anlegen: '.$verzeichnis."\n";

	exit(1);
}

/**
 * Schneidet einen Text auf eine Spaltenbreite zu und füllt ihn auf.
 *
 * `printf('%-20s', …)` zählt **Bytes**, nicht Zeichen. Bei Namen wie „Keßler"
 * oder „de Voogt, Jürgen" verrutscht die Spalte dadurch um je ein Zeichen —
 * die Antworten von nu sind UTF-8, und dort belegt ein Umlaut zwei Bytes.
 * Deshalb wird hier mit den mb_-Funktionen gearbeitet.
 *
 * Das gilt genauso für Zeichen wie „½" im Ergebnis oder den Gedankenstrich
 * „—", der hier für „kein Wert" steht.
 *
 * @param string $text   Beliebiger Text, auch mit Umlauten
 * @param int    $breite Gewünschte Breite in Zeichen
 * @param bool   $rechts Rechtsbündig auffüllen — für Zahlenspalten
 *
 * @return string Genau $breite Zeichen lang
 */
function spalte(string $text, int $breite, bool $rechts = false): string
{
	$text = mb_substr($text, 0, $breite);
	$fuellung = str_repeat(' ', max(0, $breite - mb_strlen($text)));

	return $rechts ? $fuellung.$text : $text.$fuellung;
}

/**
 * Baut einen Client für einen Bereich der Schnittstelle.
 *
 * Die Tokendatei bekommt ihren Namen aus Kennung und Scope. Damit hat jede
 * Kombination ihre eigene Ablage, und zwei Kennungen kommen sich nicht ins
 * Gehege.
 *
 * @param string $kennung   Client-ID; ist sie leer, kommt null zurück
 * @param string $geheimnis Client Secret
 * @param string $scope     Angeforderter Scope, mehrere durch Leerzeichen getrennt
 * @param string $ablage    Verzeichnis für die Tokendatei
 *
 * @return DsbOAuth2Client|null null, wenn keine Zugangsdaten vorliegen
 */
function dsb_client(string $kennung, string $geheimnis, string $scope, string $ablage): ?DsbOAuth2Client
{
	global $konfig;

	if ('' === trim($kennung) || '' === trim($geheimnis)) {
		return null;
	}

	$einstellungen = array(
		'tokendatei' => $ablage.'/dsb-token-'.substr(sha1(trim($kennung).'|'.$scope), 0, 12).'.json',
	);

	// Abweichende Adressen nur, wenn sie in den Zugangsdaten stehen — etwa für
	// eine Testumgebung. Ohne Eintrag gelten die Adressen aus der Klasse
	foreach (array('apiUrl', 'tokenUrl') as $feld) {
		if ('' !== (string) ($konfig[$feld] ?? '')) {
			$einstellungen[$feld] = (string) $konfig[$feld];
		}
	}

	return new DsbOAuth2Client($kennung, $geheimnis, $scope, $einstellungen);
}

$kennung = (string) ($konfig['clientId'] ?? '');
$listeKennung = (string) ($konfig['listeClientId'] ?? '');

// Eine Kennung für beides: dann beide Scopes in einer Anfrage. Sie gelingt
// nur, wenn der Kennung wirklich beide zugeteilt sind — sonst lehnt nu die
// ganze Anfrage ab, und auch die Turniere stünden still
$gemeinsam = '' !== $listeKennung && $listeKennung === $kennung;

$turniere = dsb_client(
	$kennung,
	(string) ($konfig['clientSecret'] ?? ''),
	$gemeinsam ? DsbOAuth2Client::SCOPE_TURNIERE.' '.DsbOAuth2Client::SCOPE_DWZLISTE : DsbOAuth2Client::SCOPE_TURNIERE,
	$verzeichnis
);

if (null === $turniere) {
	echo "In zugangsdaten.php fehlen clientId und clientSecret.\n";

	exit(1);
}

$liste = $gemeinsam
	? $turniere
	: dsb_client($listeKennung, (string) ($konfig['listeClientSecret'] ?? ''), DsbOAuth2Client::SCOPE_DWZLISTE, $verzeichnis);

return array('turniere' => $turniere, 'liste' => $liste);
