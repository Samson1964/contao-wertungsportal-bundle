<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Holt die unveränderte Antwort einer Schnittstellenfunktion — für den
 * Rohdaten-Download im Backend.
 *
 * Anlass war eine Anfrage vom September 2026: Gebraucht wurden die Auswertung
 * des 22. Rostocker Schach-Opens und der Spielberichtsbogen eines Teilnehmers,
 * und zwar genau so, wie die nu-Schnittstelle sie ausliefert. Das Frontend
 * zeigt davon nur eine aufbereitete Fassung, und der Zwischenspeicher hält die
 * bereits dekodierte Antwort — den Originaltext gab es nirgends.
 *
 * Bewusst anders als der gewöhnliche Abruf über `API::autoQuery()`:
 *
 * * **kein Zwischenspeicher** — gefragt ist, was nu JETZT liefert, nicht ein
 *   Eintrag von gestern;
 * * **kein Abgleich** mit den Spiegeltabellen — ein Diagnosewerkzeug soll
 *   nichts verändern, und ein scheiternder Abgleich darf den Download nicht
 *   verhindern;
 * * **keine Nachbesserung** — `API::BugfixVerbaende()` ergänzt beim
 *   Vereinsnamen und bei den Verbänden fehlende Einträge; die Rohdaten
 *   enthalten nur, was nu geschickt hat;
 * * **keine Zählung** in der Abrufstatistik, die die Besucher abbilden soll.
 *
 * Die Adressen baut `API::adresse()` — dieselbe Stelle, die auch das Frontend
 * benutzt. Diese Klasse kennt nur die Eingaben, ihre Prüfung und das Ergebnis.
 * Sie kommt ohne Contao aus; die Bedienoberfläche ist `Classes\Rohdaten`.
 */
class Rohabfrage
{
	/**
	 * Die abrufbaren Funktionen: Name wie in `API::getAPI()` => Bezeichnung,
	 * Eingabefelder, Pflichtfelder und „mindestens eines von".
	 *
	 * Die Reihenfolge ist die der Auswahlliste; die Turnierfunktionen stehen
	 * vorn, weil nach ihnen am häufigsten gefragt wird. Eine Prüfung in
	 * `tests/Helper/RohabfrageTest.php` stellt sicher, dass hier genau die
	 * Funktionen stehen, die `API::endpunkte()` kennt.
	 */
	const FUNKTIONEN = array
	(
		'Turnierauswertung' => array
		(
			'titel'   => 'Turnierauswertung',
			'felder'  => array('turnier'),
			'pflicht' => array('turnier'),
		),
		'Spielberichtsbogen' => array
		(
			'titel'   => 'Spielberichtsbogen eines Teilnehmers',
			'felder'  => array('turnier', 'spieleruuid'),
			'pflicht' => array('turnier', 'spieleruuid'),
		),
		'Turnierergebnisse' => array
		(
			'titel'   => 'Turnierergebnisse (Partien)',
			'felder'  => array('turnier'),
			'pflicht' => array('turnier'),
		),
		'Turnierinfo' => array
		(
			'titel'   => 'Turnier-Kopfdaten',
			'felder'  => array('turnier'),
			'pflicht' => array('turnier'),
		),
		'Turnierliste' => array
		(
			'titel'   => 'Turniersuche',
			'felder'  => array('suche', 'von', 'bis', 'zps'),
			'pflicht' => array(),
		),
		'Karteikarte' => array
		(
			'titel'   => 'Karteikarte eines Spielers',
			'felder'  => array('nuid'),
			'pflicht' => array('nuid'),
		),
		'Karteikarte_Turniere' => array
		(
			'titel'   => 'Turnierhistorie eines Spielers',
			'felder'  => array('nuid'),
			'pflicht' => array('nuid'),
		),
		'Spielerliste' => array
		(
			'titel'   => 'Spielersuche',
			'felder'  => array('nachname', 'vorname'),
			'pflicht' => array(),
			// Ohne jeden Namen käme die ganze DWZ-Liste zurück
			'eines'   => array('nachname', 'vorname'),
		),
		'Vereinsliste' => array
		(
			'titel'   => 'Mitgliederliste eines Vereins',
			'felder'  => array('zps'),
			'pflicht' => array('zps'),
		),
		'Verbandsliste' => array
		(
			'titel'   => 'Rangliste eines Verbands',
			'felder'  => array('zps', 'limit', 'geschlecht', 'alter_von', 'alter_bis'),
			'pflicht' => array(),
			// Ohne VKZ ist es ganz Deutschland — dann wenigstens mit Höchstzahl
			'eines'   => array('zps', 'limit'),
		),
		'Vereinsname' => array
		(
			'titel'   => 'Vereinsdaten',
			'felder'  => array('zps'),
			'pflicht' => array('zps'),
		),
		'Verbaende' => array
		(
			'titel'   => 'Alle Vereine und Verbände',
			'felder'  => array(),
			'pflicht' => array(),
		),
	);

	/**
	 * Die Eingabefelder: Schlüssel => Bezeichnung, Prüfart, Hilfetext und bei
	 * Bedarf der Name, unter dem `API::adresse()` den Wert erwartet (`api`).
	 *
	 * Spieler-UUID und NU-Nummer landen beide im Parameter `id`, sind aber zwei
	 * verschiedene Dinge — deshalb zwei Felder. Wer sie verwechselt, bekommt
	 * von nu nur ein 404.
	 */
	const PARAMETER = array
	(
		'turnier' => array
		(
			'titel'    => 'Turnier-UUID',
			'art'      => 'uuid',
			'hinweis'  => 'Die UUID des Turniers, wie sie in der Adresse der Turnierseite steht. Groß- und Kleinschreibung werden unverändert übernommen.',
			'beispiel' => '3fb73b0d-85cd-421c-b441-d85c44207115',
		),
		'spieleruuid' => array
		(
			'titel'    => 'Spieler-UUID im Turnier',
			'art'      => 'uuid',
			'api'      => 'id',
			'hinweis'  => 'Das Feld <code>playerUuid</code> des Teilnehmers aus der Turnierauswertung — nicht die NU-Nummer und nicht die Personen-UUID aus der DWZ-Liste. Sie gilt nur innerhalb dieses einen Turniers.',
			'beispiel' => '019f75bb-20f8-7bd8-8c1f-80cc107e0047',
		),
		'nuid' => array
		(
			'titel'    => 'NU-Nummer',
			'art'      => 'nuid',
			'api'      => 'id',
			'hinweis'  => 'Die Kennung des Spielers im Wertungsportal.',
			'beispiel' => 'NU4338664',
		),
		'nachname' => array
		(
			'titel'    => 'Nachname',
			'art'      => 'text',
			'hinweis'  => 'Nachname oder sein Anfang.',
		),
		'vorname' => array
		(
			'titel'    => 'Vorname',
			'art'      => 'text',
			'hinweis'  => 'Vorname oder sein Anfang.',
		),
		'suche' => array
		(
			'titel'    => 'Turniername enthält',
			'art'      => 'text',
			'hinweis'  => 'Ein Teil des Turniernamens.',
		),
		'von' => array
		(
			'titel'    => 'Zeitraum ab',
			'art'      => 'datum',
			'hinweis'  => 'Erster Tag des Suchzeitraums.',
		),
		'bis' => array
		(
			'titel'    => 'Zeitraum bis',
			'art'      => 'datum',
			'hinweis'  => 'Letzter Tag des Suchzeitraums.',
		),
		'zps' => array
		(
			'titel'    => 'VKZ',
			'art'      => 'vkz',
			'hinweis'  => 'Vereinskennziffer, fünfstellig für einen Verein. Bei Verbandsliste und Turniersuche genügt der Anfang (4 für Hamburg).',
			'beispiel' => '40039',
		),
		'limit' => array
		(
			'titel'    => 'Höchstzahl',
			'art'      => 'zahl',
			'min'      => 1,
			'max'      => 100000,
			'hinweis'  => 'So viele Spieler höchstens. Ohne VKZ unbedingt angeben.',
		),
		'geschlecht' => array
		(
			'titel'    => 'Geschlecht',
			'art'      => 'auswahl',
			'optionen' => array('' => 'alle', 'MALE' => 'männlich', 'FEMALE' => 'weiblich'),
			'hinweis'  => 'Filter der Schnittstelle.',
		),
		'alter_von' => array
		(
			'titel'    => 'Alter ab',
			'art'      => 'zahl',
			'min'      => 1,
			'max'      => 140,
			'hinweis'  => 'Leer lassen für keine Untergrenze.',
		),
		'alter_bis' => array
		(
			'titel'    => 'Alter bis',
			'art'      => 'zahl',
			'min'      => 1,
			'max'      => 140,
			'hinweis'  => 'Leer lassen für keine Obergrenze. U20 heißt 20, nicht 19.',
		),
	);

	/**
	 * Höchstlänge freier Texteingaben (Namen, Turniername).
	 */
	const TEXTLAENGE = 100;

	/**
	 * Länge des Ausschnitts, der bei einer Fehlerantwort auf der Seite
	 * erscheint. Eine Fehlerseite des Servers kann ein ganzes HTML-Dokument
	 * sein; mehr als der Anfang hilft dort niemandem.
	 */
	const AUSZUG = 4000;

	/**
	 * Prüft und bereinigt die Eingaben für eine Funktion.
	 *
	 * Geprüft werden nur die Felder, die die Funktion auch benutzt — ein
	 * zurückgebliebener Wert aus einer vorher gewählten Funktion stört nicht.
	 * Bereinigt heißt: Leerzeichen am Rand weg, NU-Nummer und VKZ in
	 * Großschrift, führende Nullen bei Zahlen weg. Eine UUID bleibt, wie sie
	 * eingegeben wurde; ob nu Großbuchstaben annimmt, ist nicht belegt, und
	 * eine stillschweigende Umschrift ließe sich im Fehlerfall nicht
	 * nachvollziehen.
	 *
	 * @param string $funktion Schlüssel aus FUNKTIONEN
	 * @param array  $eingabe  Feld => eingegebener Text
	 *
	 * @return array ['werte' => Feld => bereinigter Wert (leer = nicht
	 *               angegeben), 'fehler' => Liste verständlicher Meldungen]
	 */
	public static function eingabe(string $funktion, array $eingabe): array
	{
		if (!isset(self::FUNKTIONEN[$funktion]))
		{
			return array('werte' => array(), 'fehler' => array('Unbekannte Funktion: '.$funktion));
		}

		$regeln = self::FUNKTIONEN[$funktion];
		$werte = array();
		$fehler = array();

		foreach ($regeln['felder'] as $feld)
		{
			$parameter = self::PARAMETER[$feld];
			$wert = trim((string) ($eingabe[$feld] ?? ''));
			$werte[$feld] = '';

			if ($wert === '')
			{
				if (in_array($feld, $regeln['pflicht'], true))
				{
					$fehler[] = 'Bitte „'.$parameter['titel'].'" angeben.';
				}

				continue;
			}

			$pruefung = self::pruefeWert($parameter, $wert);
			$werte[$feld] = $pruefung['wert'];

			if ($pruefung['fehler'] !== '')
			{
				$fehler[] = '„'.$parameter['titel'].'": '.$pruefung['fehler'];
			}
		}

		if (!empty($regeln['eines']))
		{
			$gefuellt = array_filter($regeln['eines'], static function ($feld) use ($werte) {
				return ($werte[$feld] ?? '') !== '';
			});

			if (!count($gefuellt))
			{
				$titel = array_map(static function ($feld) {
					return '„'.self::PARAMETER[$feld]['titel'].'"';
				}, $regeln['eines']);

				$fehler[] = 'Bitte mindestens eines angeben: '.implode(' oder ', $titel).'.';
			}
		}

		return array('werte' => $werte, 'fehler' => $fehler);
	}

	/**
	 * Prüft einen einzelnen, nicht leeren Wert nach der Art seines Feldes.
	 *
	 * @param array  $parameter Eintrag aus PARAMETER
	 * @param string $wert      Eingabe ohne Randleerzeichen
	 *
	 * @return array ['wert' => bereinigter Wert, 'fehler' => Meldung oder leer]
	 */
	protected static function pruefeWert(array $parameter, string $wert): array
	{
		switch ($parameter['art'])
		{
			case 'uuid':
				if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $wert))
				{
					return array('wert' => $wert, 'fehler' => 'keine gültige UUID (Form: '.$parameter['beispiel'].').');
				}

				return array('wert' => $wert, 'fehler' => '');

			case 'nuid':
				$wert = strtoupper($wert);

				if (!preg_match('/^NU[0-9]{1,12}$/', $wert))
				{
					return array('wert' => $wert, 'fehler' => 'keine gültige NU-Nummer (Form: NU4338664).');
				}

				return array('wert' => $wert, 'fehler' => '');

			case 'vkz':
				$wert = strtoupper($wert);

				if (!preg_match('/^[0-9A-Z]{1,5}$/', $wert))
				{
					return array('wert' => $wert, 'fehler' => 'keine gültige Vereinskennziffer (ein bis fünf Ziffern oder Buchstaben, etwa 40039 oder C0109).');
				}

				return array('wert' => $wert, 'fehler' => '');

			case 'datum':
				if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $wert, $teile) || !checkdate((int) $teile[2], (int) $teile[3], (int) $teile[1]))
				{
					return array('wert' => $wert, 'fehler' => 'kein gültiges Datum (Form: JJJJ-MM-TT).');
				}

				return array('wert' => $wert, 'fehler' => '');

			case 'zahl':
				$min = (int) ($parameter['min'] ?? 0);
				$max = (int) ($parameter['max'] ?? 999999);

				if (!preg_match('/^[0-9]{1,7}$/', $wert) || (int) $wert < $min || (int) $wert > $max)
				{
					return array('wert' => $wert, 'fehler' => 'eine ganze Zahl von '.$min.' bis '.$max.' erwartet.');
				}

				return array('wert' => (string) (int) $wert, 'fehler' => '');

			case 'auswahl':
				if (!array_key_exists($wert, $parameter['optionen']))
				{
					return array('wert' => $wert, 'fehler' => 'unbekannter Wert.');
				}

				return array('wert' => $wert, 'fehler' => '');
		}

		// Freier Text
		if (!mb_check_encoding($wert, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $wert))
		{
			return array('wert' => $wert, 'fehler' => 'enthält unzulässige Zeichen.');
		}

		if (mb_strlen($wert, 'UTF-8') > self::TEXTLAENGE)
		{
			return array('wert' => $wert, 'fehler' => 'höchstens '.self::TEXTLAENGE.' Zeichen.');
		}

		return array('wert' => $wert, 'fehler' => '');
	}

	/**
	 * Wandelt die geprüften Werte in die Parameter von `API::adresse()` um.
	 *
	 * Leere Werte fallen weg — `adresse()` behandelt einen fehlenden Parameter
	 * genau wie einen leeren.
	 *
	 * @param string $funktion Schlüssel aus FUNKTIONEN
	 * @param array  $werte    Ergebnis `werte` aus eingabe()
	 *
	 * @return array Parameter samt `funktion`
	 */
	public static function parameter(string $funktion, array $werte): array
	{
		$parameter = array('funktion' => $funktion);

		foreach ($werte as $feld => $wert)
		{
			if ($wert === '' || !isset(self::PARAMETER[$feld])) continue;

			$parameter[self::PARAMETER[$feld]['api'] ?? $feld] = $wert;
		}

		return $parameter;
	}

	/**
	 * Nennt den Grund, aus dem gerade gar nicht abgerufen werden darf.
	 *
	 * Dieselben beiden Bedingungen wie in `API::autoQuery()`: Ist die
	 * Schnittstelle in den Einstellungen abgeschaltet, gilt das auch hier —
	 * sonst ließe sich ein bewusster Stopp, etwa während nu das
	 * Tokenkontingent drosselt, über das Backend aushebeln.
	 *
	 * @return string Meldung, oder leer wenn nichts im Weg steht
	 */
	public static function hindernis(): string
	{
		if (!empty($GLOBALS['TL_CONFIG']['wertungsportal_api_aus']))
		{
			return 'Die Schnittstelle ist in den Einstellungen des Wertungsportals abgeschaltet. Solange das so ist, wird auch hier nichts abgerufen.';
		}

		if (!OAuth2Client::eingerichtet())
		{
			return 'In den Einstellungen des Wertungsportals fehlen Zugangsdaten der Schnittstelle (Basisadresse, Token-Adresse, Client-ID oder Client-Secret).';
		}

		return '';
	}

	/**
	 * Ruft die Schnittstelle auf und gibt die Antwort unverändert zurück.
	 *
	 * Der Abruf läuft über `OAuth2Client::callApiWithRefresh()` und damit über
	 * dieselbe Tokenbehandlung wie das Frontend: dasselbe zwischengespeicherte
	 * Token, dieselbe Wartezeit nach einem gescheiterten Tokenabruf. Ein Klick
	 * hier kostet also kein zusätzliches Token aus dem Kontingent, solange das
	 * gespeicherte noch gilt.
	 *
	 * @param array $parameter Ergebnis von parameter()
	 *
	 * @return array ['url' => aufgerufene Adresse, 'http' => Status (0 = keine
	 *               Antwort), 'fehler' => Meldung des Clients oder leer,
	 *               'roh' => Antworttext, 'dauer' => Millisekunden]
	 */
	public static function abrufen(array $parameter): array
	{
		$adresse = API::adresse($parameter);

		if ($adresse === null)
		{
			return array('url' => '', 'http' => 0, 'fehler' => 'Unbekannte Funktion.', 'roh' => '', 'dauer' => 0.0);
		}

		$client = new OAuth2Client();
		$client->rohantwort = true;

		$url = $client->apiBaseUrl.$adresse;
		$beginn = microtime(true);
		$antwort = $client->callApiWithRefresh($url);

		return array
		(
			'url'    => $url,
			'http'   => (int) ($antwort['http_code'] ?? 0),
			'fehler' => !empty($antwort['error']) ? (string) ($antwort['error_message'] ?? 'Unbekannter Fehler') : '',
			'roh'    => isset($antwort['roh']) ? (string) $antwort['roh'] : '',
			'dauer'  => (microtime(true) - $beginn) * 1000,
		);
	}

	/**
	 * Entscheidet, ob die Antwort eingerückt ausgegeben wird.
	 *
	 * Vorgabe ist seit 1.45.0 „eingerückt": Die Dateien werden fast immer zum
	 * Lesen und Weitergeben geholt, die unveränderten Bytes braucht nur, wer
	 * die Antwort mit der von nu vergleichen will. Ein Kontrollkästchen
	 * kennt aber keinen Wert für „abgewählt" — der Browser schickt es dann
	 * gar nicht mit. Ob der Haken fehlt, weil er entfernt wurde, oder weil das
	 * Formular noch nie abgeschickt wurde, verrät deshalb nur `$abgeschickt`.
	 * Das Formular schickt sich auch beim Wechsel der Funktion ab; ein dort
	 * entfernter Haken bleibt so entfernt.
	 *
	 * @param bool  $abgeschickt true, wenn das Rohdaten-Formular abgeschickt
	 *                           wurde (FORM_SUBMIT stimmt)
	 * @param mixed $wert        Wert des Kästchens aus der Anfrage, '1' = angehakt
	 *
	 * @return bool true = eingerückt ausgeben
	 */
	public static function lesbar(bool $abgeschickt, $wert): bool
	{
		return $abgeschickt ? $wert === '1' : true;
	}

	/**
	 * Bereitet den Antworttext für die Datei auf.
	 *
	 * Ohne `$lesbar` kommt der Text Byte für Byte so zurück, wie nu ihn
	 * geschickt hat — nur diese Fassung ist mit der Originalantwort identisch.
	 *
	 * Mit `$lesbar` wird eingerückt. Dekodiert wird dabei in Objekte statt in
	 * Arrays: Sonst würde aus einem leeren Objekt `{}` beim Zurückschreiben ein
	 * leeres Array `[]`, und die Datei behauptete eine andere Struktur als die
	 * Antwort. Aus demselben Grund bleiben `1.0` eine Kommazahl und sehr große
	 * Ganzzahlen unverfälscht. Ist der Text kein gültiges JSON, kommt er
	 * unverändert zurück.
	 *
	 * @param string $roh    Antworttext
	 * @param bool   $lesbar Eingerückt ausgeben
	 *
	 * @return string Dateiinhalt
	 */
	public static function inhalt(string $roh, bool $lesbar): string
	{
		if (!$lesbar || $roh === '') return $roh;

		$daten = json_decode($roh, false, 512, JSON_BIGINT_AS_STRING);

		if (json_last_error() !== JSON_ERROR_NONE) return $roh;

		$text = json_encode($daten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

		return $text === false ? $roh : $text."\n";
	}

	/**
	 * Bildet den Dateinamen des Downloads.
	 *
	 * Aus Funktion, den angegebenen Werten und dem Abrufzeitpunkt — so lässt
	 * sich eine weitergereichte Datei auch Wochen später noch zuordnen, und
	 * zwei Abrufe desselben Turniers überschreiben sich nicht. Umlaute werden
	 * umschrieben, alles außer Buchstaben, Ziffern, Punkt, Binde- und
	 * Unterstrich wird zum Bindestrich; der Name taugt damit auf jedem
	 * Betriebssystem und im Content-Disposition-Kopf ohne Kodierung.
	 *
	 * @param string $funktion Schlüssel aus FUNKTIONEN
	 * @param array  $werte    Ergebnis `werte` aus eingabe()
	 * @param int    $zeit     Zeitpunkt des Abrufs
	 *
	 * @return string Dateiname mit Endung .json
	 */
	public static function dateiname(string $funktion, array $werte, int $zeit): string
	{
		$teile = array('nu', $funktion);

		foreach ($werte as $wert)
		{
			if ($wert !== '') $teile[] = $wert;
		}

		$teile[] = date('Ymd-Hi', $zeit);

		$name = strtr(implode('_', $teile), array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss'));
		$name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
		$name = trim(substr($name, 0, 150), '-_.');

		return $name.'.json';
	}

	/**
	 * Fasst die angegebenen Werte für die Protokollzeile zusammen.
	 *
	 * @param array $werte Ergebnis `werte` aus eingabe()
	 *
	 * @return string Etwa `turnier=3fb7…, spieleruuid=019f…`, oder `ohne Angaben`
	 */
	public static function beschreibung(array $werte): string
	{
		$teile = array();

		foreach ($werte as $feld => $wert)
		{
			if ($wert !== '') $teile[] = $feld.'='.$wert;
		}

		return count($teile) ? implode(', ', $teile) : 'ohne Angaben';
	}

	/**
	 * Gibt zu einer gescheiterten Antwort einen gezielten Hinweis.
	 *
	 * Die Fälle stammen aus dem Betrieb: Das Tokenkontingent der Client-ID ist
	 * endlich (siehe `docs/vorladen.md`), der Spielberichtsbogen will eine
	 * andere Kennung als man zuerst vermutet, und über die Schreibweise von
	 * UUIDs ist nichts Verlässliches bekannt.
	 *
	 * @param string $funktion Schlüssel aus FUNKTIONEN
	 * @param array  $ergebnis Ergebnis von abrufen()
	 * @param array  $werte    Ergebnis `werte` aus eingabe()
	 *
	 * @return string Hinweis, oder leer
	 */
	public static function hinweis(string $funktion, array $ergebnis, array $werte): string
	{
		$http = (int) ($ergebnis['http'] ?? 0);
		$hinweise = array();

		if ($http === 0)
		{
			$hinweise[] = 'Die Schnittstelle hat gar nicht geantwortet — Verbindung gestört oder Wartezeit überschritten. Die Meldung oben nennt die Ursache.';
		}
		elseif ($http === 401 || $http === 403)
		{
			$hinweise[] = 'nu hat den Zugriff abgewiesen. Häufigste Ursache ist ein erschöpftes Tokenkontingent der Client-ID. Dann einige Minuten warten und nicht wiederholt klicken — jeder neue Versuch verlängert die Sperre.';
		}
		elseif ($http === 404)
		{
			if ($funktion === 'Spielberichtsbogen')
			{
				$hinweise[] = 'Der Spielberichtsbogen braucht die Spieler-UUID aus der Turnierauswertung (Feld playerUuid). Die NU-Nummer und die Personen-UUID aus der DWZ-Liste passen nicht. Am sichersten erst die Turnierauswertung herunterladen und die UUID dort ablesen.';
			}

			foreach (array('turnier', 'spieleruuid') as $feld)
			{
				if (preg_match('/[A-F]/', (string) ($werte[$feld] ?? '')))
				{
					$hinweise[] = 'Die '.self::PARAMETER[$feld]['titel'].' enthält Großbuchstaben. nu liefert UUIDs sonst in Kleinschreibung — einen Versuch klein geschrieben ist es wert.';
				}
			}

			if (!count($hinweise))
			{
				$hinweise[] = 'Diesen Datensatz kennt nu nicht. Die Kennung prüfen.';
			}
		}
		elseif ($http === 400)
		{
			$hinweise[] = 'nu hat die Anfrage als fehlerhaft abgewiesen. Die Meldung im Antworttext unten nennt meist das beanstandete Feld.';
		}
		elseif ($http >= 500)
		{
			$hinweise[] = 'Ein Fehler auf der Seite von nu. Später erneut versuchen; hält er an, die Adresse und den Antworttext an nu weitergeben.';
		}

		return implode(' ', $hinweise);
	}
}
