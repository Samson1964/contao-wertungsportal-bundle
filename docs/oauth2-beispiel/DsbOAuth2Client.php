<?php

declare(strict_types=1);

/**
 * OAuth2-Zugriff auf das DSB-Wertungsportal (DWZ-System) — eigenständige
 * Beispielklasse für Umsysteme.
 *
 * Eine einzelne Datei, ohne Composer, ohne Rahmenwerk. Gebraucht werden nur
 * die PHP-Erweiterungen **curl** und **json** (ab PHP 7.4). Für die Prüfung
 * heruntergeladener Zip-Dateien zusätzlich **zip**.
 *
 * Sie deckt genau das ab, was die Schnittstellenanleitung von nu
 * („OAuth2-Zugriff auf das DSB-Wertungsportal (DWZ-System)", Stand September
 * 2026) verlangt — einschließlich der Dinge, die man erst im Dauerbetrieb
 * merkt:
 *
 * - **Ein Access Token, dann nur noch Erneuerungen.** Je Client-ID sind
 *   derzeit nur **fünf neue Token in 30 Minuten** erlaubt. Wer bei jedem
 *   Aufruf ein neues holt, steht nach wenigen Minuten vor einem HTTP 403.
 * - **Das Token wird in einer Datei hinterlegt**, nicht nur im Arbeitsspeicher:
 *   Ein Webaufruf endet nach Sekunden, das Token gilt fünf Minuten.
 * - **Nur das jüngste Refresh-Token ist einlösbar.** Erneuern zwei Vorgänge
 *   gleichzeitig, bekommt der zweite „Refresh Token already used or invalid"
 *   und müsste ein neues Token anfordern — genau das, was das Kontingent
 *   auffrißt. Deshalb läuft jede Erneuerung unter einer Dateisperre.
 * - **Nach HTTP 401 einmal erneuern und den Abruf wiederholen.**
 * - **Nach einem abgewiesenen Tokenabruf wird gewartet.** nu antwortet mit
 *   einer Sammelmeldung für drei Ursachen („1. No accesses for this client-id
 *   or 2. Too much access tokens … or 3. Wrong or no scope(s) provided"); die
 *   Ursache ist nicht zu erkennen, also gilt die vorsichtigste Annahme.
 * - **Der Scope geht in jede Tokenanfrage**, auch in die Erneuerung.
 *
 * Kurzfassung der Benutzung:
 *
 *     require __DIR__.'/DsbOAuth2Client.php';
 *
 *     $client = new DsbOAuth2Client(CLIENT_ID, CLIENT_SECRET, DsbOAuth2Client::SCOPE_TURNIERE);
 *     $antwort = $client->get('/dwz/tournaments', array('ratingState' => 'RATED', 'limit' => 10));
 *
 *     foreach ($antwort['data'] as $turnier) {
 *         echo $turnier['enddate'], '  ', $turnier['label'], "\n";
 *     }
 *
 * Zugangsdaten gibt es beim Deutschen Schachbund; sie gehören **nicht** in den
 * Quelltext (siehe zugangsdaten.beispiel.php).
 *
 * Diese Datei darf frei verwendet und angepaßt werden.
 */

/**
 * Fehler beim Zugriff auf die Schnittstelle.
 *
 * Trägt neben der Meldung den HTTP-Status und den Antworttext von nu — beides
 * hilft beim Nachsehen, was wirklich zurückkam.
 */
class DsbOAuth2Exception extends RuntimeException
{
	/**
	 * @var int HTTP-Status der Antwort; 0, wenn gar keine Verbindung zustande kam
	 */
	private $httpStatus;

	/**
	 * @var string Antworttext von nu, unverändert
	 */
	private $antworttext;

	/**
	 * Legt den Fehler an.
	 *
	 * @param string $meldung     Beschreibung in Klartext
	 * @param int    $httpStatus  HTTP-Status, 0 bei einem Verbindungsfehler
	 * @param string $antworttext Antwort von nu, soweit vorhanden
	 */
	public function __construct(string $meldung, int $httpStatus = 0, string $antworttext = '')
	{
		parent::__construct($meldung, $httpStatus);

		$this->httpStatus = $httpStatus;
		$this->antworttext = $antworttext;
	}

	/**
	 * Liefert den HTTP-Status der Antwort.
	 *
	 * @return int 0, wenn die Anfrage den Server nicht erreicht hat
	 */
	public function httpStatus(): int
	{
		return $this->httpStatus;
	}

	/**
	 * Liefert den unveränderten Antworttext von nu.
	 *
	 * @return string Leer, wenn keine Antwort vorlag
	 */
	public function antworttext(): string
	{
		return $this->antworttext;
	}
}

/**
 * Client für die REST-Schnittstelle des DSB-Wertungsportals.
 */
class DsbOAuth2Client
{
	/**
	 * Endpunkt für Access- und Refresh-Token-Anfragen.
	 */
	const TOKEN_URL = 'https://schachde-portal.liga.nu/rs/auth/token';

	/**
	 * Basisadresse der Schnittstelle. Alle Pfade sind relativ dazu.
	 */
	const API_URL = 'https://schachde-apps.liga.nu/dsbwertungsportal/rs';

	/**
	 * Scope für Turniere und Personen: `/dwz/tournaments/…`, `/dwz/persons/…`.
	 */
	const SCOPE_TURNIERE = 'dsb_tournament';

	/**
	 * Scope für die DWZ-Liste: `/dwz/dwzliste/…` samt Zip-Downloads.
	 *
	 * Diese Endpunkte sind derzeit noch ohne Anmeldung erreichbar; nu hat
	 * angekündigt, das umzustellen, und empfiehlt, schon jetzt ein Token
	 * mitzusenden. Dafür ist eine eigene Freischaltung nötig — eine Kennung
	 * bekommt nur dann ein Token, wenn ihr **alle** angefragten Scopes
	 * zugeteilt sind. Deckt eine Kennung beides ab, können beide Scopes in
	 * einer Anfrage stehen: `SCOPE_TURNIERE.' '.SCOPE_DWZLISTE`.
	 */
	const SCOPE_DWZLISTE = 'dwz_liste';

	/**
	 * Angenommene Lebensdauer eines Tokens in Sekunden, wenn die Antwort keine
	 * nennt. Fünf Minuten — so steht es in der Anleitung.
	 */
	const TOKEN_LEBENSDAUER = 300;

	/**
	 * Sicherheitsabstand in Sekunden: So lange vor dem Ablauf wird schon
	 * erneuert. Sonst läuft das Token womöglich während der Anfrage ab.
	 */
	const PUFFER = 30;

	/**
	 * Wartezeit in Sekunden nach einem abgewiesenen Tokenabruf.
	 */
	const SPERRE_NORMAL = 300;

	/**
	 * Wartezeit in Sekunden nach einem Tokenabruf, den nu mit **HTTP 403**
	 * abgewiesen hat.
	 *
	 * Hinter diesem Status steckt unter anderem das erschöpfte Kontingent, und
	 * das zählt in einem Fenster von 30 Minuten. Früher wieder anzufragen
	 * verbrennt nur die verbliebenen Versuche.
	 */
	const SPERRE_KONTINGENT = 1800;

	/**
	 * cURL-Fehlernummern, bei denen ein zweiter Versuch sinnvoll ist: Alle
	 * bezeichnen einen Abriß der Verbindung, nicht eine Antwort des Servers.
	 * Beobachtet im Dauerbetrieb: „HTTP/2 stream 1 was not closed cleanly:
	 * CANCEL" bei etwa jedem 200. Abruf.
	 */
	const WIEDERHOLBAR = array(16, 18, 52, 55, 56, 92);

	/**
	 * @var string Client-ID der Freischaltung
	 */
	private $clientId;

	/**
	 * @var string Client Secret (das bei der Freischaltung gesetzte Passwort)
	 */
	private $clientSecret;

	/**
	 * @var string Angeforderter Scope, mehrere durch Leerzeichen getrennt
	 */
	private $scope;

	/**
	 * @var string Adresse des Token-Endpunkts
	 */
	private $tokenUrl;

	/**
	 * @var string Basisadresse der Schnittstelle, ohne Schrägstrich am Ende
	 */
	private $apiUrl;

	/**
	 * @var string Datei, in der Token und Wartezeiten liegen
	 */
	private $tokendatei;

	/**
	 * @var int Wartezeit einer Anfrage in Sekunden
	 */
	private $timeout;

	/**
	 * @var array<string,mixed> Tokendaten dieses Prozesses — zweite Ebene neben der Datei
	 */
	private $speicher = array();

	/**
	 * @var bool Ist die Tokendatei benutzbar? Einmal im Konstruktor geprüft.
	 *           Wenn nicht, arbeitet die Klasse nur im Arbeitsspeicher weiter
	 *           — und faßt die Datei gar nicht erst an, statt bei jedem
	 *           Zugriff eine Warnung auszulösen
	 */
	private $ablageNutzbar = true;

	/**
	 * Richtet den Client ein.
	 *
	 * Es wird noch nichts abgerufen und kein Token geholt; das geschieht beim
	 * ersten `get()`.
	 *
	 * Die **Tokendatei** ist der wichtigste Punkt der Einrichtung: Sie muß für
	 * den Benutzer schreibbar sein, unter dem PHP läuft — und für alle Wege,
	 * die die Schnittstelle benutzen. Laufen Webserver und Kommandozeile unter
	 * verschiedenen Benutzern, brauchen beide Zugriff auf dieselbe Datei, sonst
	 * holt sich jeder Weg ein eigenes Token. Das Systemverzeichnis für
	 * temporäre Dateien ist dafür ein schlechter Ort: Es gehört nicht der
	 * Anwendung, ein Aufräumer kann jederzeit dazwischenfahren, und manche
	 * Dienste bekommen sogar ein eigenes zu sehen (systemd `PrivateTmp`).
	 * Besser ein eigenes Verzeichnis im Projekt.
	 *
	 * Je Client-ID gehört eine eigene Tokendatei dazu. Zwei Kennungen, die sich
	 * eine Datei teilen, überschreiben sich gegenseitig die Token.
	 *
	 * @param string $clientId     Client-ID aus der Freischaltungs-E-Mail
	 * @param string $clientSecret Bei der Freischaltung gesetztes Passwort
	 * @param string $scope        Angeforderter Scope, z. B. self::SCOPE_TURNIERE.
	 *                             Mehrere durch Leerzeichen getrennt; die Anfrage
	 *                             gelingt nur, wenn alle freigeschaltet sind
	 * @param array<string,mixed> $einstellungen Wahlweise abweichende Werte:
	 *                             `tokendatei` (Vorgabe: dsb-token-<Kennung>.json
	 *                             neben dieser Datei), `timeout` (Vorgabe 30 s),
	 *                             `tokenUrl` und `apiUrl` (für Testumgebungen)
	 *
	 * @throws DsbOAuth2Exception wenn Kennung oder Geheimnis fehlen
	 */
	public function __construct(string $clientId, string $clientSecret, string $scope = self::SCOPE_TURNIERE, array $einstellungen = array())
	{
		$this->clientId = trim($clientId);
		$this->clientSecret = trim($clientSecret);
		$this->scope = trim($scope);

		if ('' === $this->clientId || '' === $this->clientSecret) {
			throw new DsbOAuth2Exception('Client-ID und Client Secret müssen angegeben werden.');
		}

		$this->tokenUrl = (string) ($einstellungen['tokenUrl'] ?? self::TOKEN_URL);
		$this->apiUrl = rtrim((string) ($einstellungen['apiUrl'] ?? self::API_URL), '/');
		$this->timeout = (int) ($einstellungen['timeout'] ?? 30);

		// Der Dateiname enthält einen Fingerabdruck der Kennung, damit zwei
		// Kennungen nicht versehentlich dieselbe Datei benutzen. Die Kennung
		// selbst steht nicht im Namen — Dateinamen landen schnell in
		// Protokollen und Sicherungen
		$this->tokendatei = (string) ($einstellungen['tokendatei'] ?? __DIR__.'/dsb-token-'.substr(sha1($this->clientId), 0, 12).'.json');
		$this->ablageNutzbar = $this->ablagePruefen();
	}

	/**
	 * Stellt fest, ob die Tokendatei benutzbar ist, und legt ihr Verzeichnis
	 * bei Bedarf an.
	 *
	 * Einmal beim Einrichten statt bei jedem Zugriff: Sonst liefe jeder
	 * Schreibversuch in eine Warnung, die nur mit `@` verdeckt wäre — in
	 * einem Rahmenwerk mit eigenem Fehlerhandler stünde sie trotzdem im
	 * Protokoll.
	 *
	 * Ist die Ablage nicht nutzbar, bricht nichts ab: Die Klasse arbeitet mit
	 * dem Token im Arbeitsspeicher weiter. Das reicht für einen Seitenaufruf
	 * — aber jeder weitere holt ein eigenes Token, und davon sind nur fünf je
	 * 30 Minuten erlaubt. Deshalb die deutliche Warnung.
	 *
	 * @return bool true, wenn Token und Sperrdatei geschrieben werden können
	 */
	private function ablagePruefen(): bool
	{
		$verzeichnis = \dirname($this->tokendatei);

		// Bis zum ersten vorhandenen Teil des Weges hochgehen und dort nachsehen,
		// ob überhaupt angelegt werden kann. Ohne diese Vorprüfung löst ein
		// aussichtsloses mkdir() eine Warnung aus — hier zwar mit @ verdeckt,
		// aber ein Rahmenwerk mit eigenem Fehlerhandler protokolliert sie doch
		$vorhanden = $verzeichnis;

		while (!file_exists($vorhanden) && \dirname($vorhanden) !== $vorhanden) {
			$vorhanden = \dirname($vorhanden);
		}

		if (!is_dir($verzeichnis) && (!is_dir($vorhanden) || !is_writable($vorhanden) || !@mkdir($verzeichnis, 0770, true)) && !is_dir($verzeichnis)) {
			trigger_error(
				'Das Verzeichnis für die Tokendatei läßt sich nicht anlegen: '.$verzeichnis.'. Ohne hinterlegtes '
				.'Token fordert jeder Aufruf ein eigenes an — die Schnittstelle weist das bald mit '
				.'„Too much access tokens" ab.',
				E_USER_WARNING
			);

			return false;
		}

		// Bei einer vorhandenen Datei zählt sie selbst, sonst das Verzeichnis
		if (is_file($this->tokendatei) ? !is_writable($this->tokendatei) : !is_writable($verzeichnis)) {
			trigger_error(
				'Die Tokendatei '.$this->tokendatei.' läßt sich nicht schreiben. Bitte die Schreibrechte '
				.'prüfen — sie müssen für ALLE Wege gelten, die die Schnittstelle benutzen (Webserver und '
				.'Cronjob). Sonst holt sich jeder Aufruf ein eigenes Zugangstoken.',
				E_USER_WARNING
			);

			return false;
		}

		return true;
	}

	/**
	 * Ruft einen Endpunkt der Schnittstelle ab.
	 *
	 * Um Token, Erneuerung und Wiederholung kümmert sich die Methode selbst.
	 * Zurück kommt die ausgewertete Antwort — bei Listen also ein Array mit
	 * `data`, `metadata` und `links`.
	 *
	 * @param string $pfad      Pfad relativ zur Basisadresse, etwa
	 *                          `/dwz/tournaments` oder `/dwz/persons/NU1234567/history`
	 * @param array<string,mixed> $parameter Abfrageparameter, etwa
	 *                          `array('limit' => 10)`. Leere Werte (null, '')
	 *                          werden weggelassen
	 *
	 * @return array<string,mixed> Die Antwort von nu als Array
	 *
	 * @throws DsbOAuth2Exception bei jedem Status außer 200, bei ungültigem
	 *                            JSON und wenn kein Token zu bekommen ist
	 */
	public function get(string $pfad, array $parameter = array()): array
	{
		$url = $this->adresse($pfad, $parameter);
		$antwort = $this->anfrage($url, $this->zugangstoken());

		// Nach einem 401 genau einmal erneuern und wiederholen. Bleibt es
		// dabei, stimmt etwas anderes nicht — dann wird nicht weiter versucht
		if (401 === $antwort['status']) {
			$antwort = $this->anfrage($url, $this->zugangstoken(true));
		}

		if (200 !== $antwort['status']) {
			throw new DsbOAuth2Exception(
				sprintf('Abruf von %s beantwortet mit HTTP %d.', $pfad, $antwort['status']),
				$antwort['status'],
				$antwort['text']
			);
		}

		$daten = json_decode($antwort['text'], true);

		if (!is_array($daten)) {
			throw new DsbOAuth2Exception('Die Antwort war kein gültiges JSON: '.json_last_error_msg(), 200, $antwort['text']);
		}

		return $daten;
	}

	/**
	 * Durchläuft einen Listen-Endpunkt seitenweise und liefert jeden Datensatz
	 * einzeln.
	 *
	 * Listen können sehr lang werden — die DWZ-Liste eines großen Landesverbands
	 * umfaßt Zehntausende Spieler. nu empfiehlt deshalb ausdrücklich, in
	 * Abschnitten abzurufen. Diese Methode erledigt das: Sie zählt `offset`
	 * hoch, bis `metadata.totalCount` erreicht ist.
	 *
	 * Weil sie ein Generator ist, steht immer nur eine Seite im Speicher:
	 *
	 *     foreach ($client->alleSeiten('/dwz/dwzliste/persons', array('vkz' => 'C0652')) as $spieler) {
	 *         echo $spieler['lastname'], "\n";
	 *     }
	 *
	 * Ein Lauf über viele Seiten dauert länger, als ein Token lebt. Das macht
	 * nichts: Zwischendurch wird über das Refresh-Token erneuert, ohne das
	 * Kontingent zu belasten.
	 *
	 * @param string $pfad      Pfad eines Listen-Endpunkts
	 * @param array<string,mixed> $parameter Abfrageparameter ohne `limit` und `offset`
	 * @param int    $proSeite  Datensätze je Abruf (1 bis 1000)
	 *
	 * @return Generator<int,array<string,mixed>> Liefert die Einträge aus `data` nacheinander
	 *
	 * @throws DsbOAuth2Exception wie get()
	 */
	public function alleSeiten(string $pfad, array $parameter = array(), int $proSeite = 100): Generator
	{
		$proSeite = max(1, min(1000, $proSeite));
		$offset = 0;

		do {
			$antwort = $this->get($pfad, array_merge($parameter, array('limit' => $proSeite, 'offset' => $offset)));
			$zeilen = isset($antwort['data']) && is_array($antwort['data']) ? $antwort['data'] : array();

			foreach ($zeilen as $zeile) {
				yield $zeile;
			}

			$gesamt = (int) ($antwort['metadata']['totalCount'] ?? 0);
			$offset += $proSeite;

			// Abbruch auch dann, wenn eine Seite weniger liefert als
			// angefordert — sonst dreht sich die Schleife bei einem
			// unerwarteten totalCount endlos
		} while (count($zeilen) >= $proSeite && $offset < $gesamt);
	}

	/**
	 * Lädt eine Datei der DWZ-Liste herunter, etwa die Zip-Datei eines
	 * Landesverbands.
	 *
	 * Die Dateinamen nennt der Endpunkt `/dwz/dwzliste/download`. Geschrieben
	 * wird erst in eine Datei daneben und am Ende umbenannt: Bricht der
	 * Download ab, bleibt so keine halbe Datei am Zielort liegen.
	 *
	 * @param string $dateiname Name der Datei bei nu, etwa `LV-0-dwzliste.zip`
	 * @param string $ziel      Vollständiger Pfad, unter dem gespeichert wird
	 * @param bool   $zipPruefen Das Ergebnis als Zip-Archiv gegenprüfen. Erkennt
	 *                          auch abgeschnittene Downloads; braucht die
	 *                          PHP-Erweiterung `zip`
	 *
	 * @return int Größe der geschriebenen Datei in Bytes
	 *
	 * @throws DsbOAuth2Exception bei jedem Status außer 200, bei einem
	 *                            Verbindungsfehler und bei einem defekten Archiv
	 */
	public function herunterladen(string $dateiname, string $ziel, bool $zipPruefen = true): int
	{
		$url = $this->apiUrl.'/dwz/dwzliste/download/'.rawurlencode($dateiname);
		$vorlaeufig = $ziel.'.teil';

		foreach (array(false, true) as $zweiterVersuch) {
			$griff = @fopen($vorlaeufig, 'w');

			if (false === $griff) {
				throw new DsbOAuth2Exception('Die Zieldatei läßt sich nicht schreiben: '.$vorlaeufig);
			}

			$verbindung = curl_init($url);
			curl_setopt_array($verbindung, array(
				CURLOPT_FILE           => $griff,
				CURLOPT_HTTPHEADER     => array('Authorization: Bearer '.$this->zugangstoken($zweiterVersuch)),
				CURLOPT_TIMEOUT        => 3600,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			));
			curl_exec($verbindung);
			$fehler = curl_error($verbindung);
			$status = (int) curl_getinfo($verbindung, CURLINFO_RESPONSE_CODE);
			curl_close($verbindung);
			fclose($griff);

			if ('' !== $fehler) {
				@unlink($vorlaeufig);

				throw new DsbOAuth2Exception('Verbindungsfehler beim Download: '.$fehler);
			}

			// Ein 401 kann heißen, daß das Token widerrufen wurde. Einmal
			// erneuern und wiederholen — beim zweiten Durchlauf holt
			// zugangstoken(true) ein frisches
			if (401 === $status && !$zweiterVersuch) {
				@unlink($vorlaeufig);

				continue;
			}

			if (200 !== $status) {
				@unlink($vorlaeufig);

				throw new DsbOAuth2Exception(sprintf('Download von %s beantwortet mit HTTP %d.', $dateiname, $status), $status);
			}

			if ($zipPruefen && class_exists('ZipArchive')) {
				$archiv = new ZipArchive();
				$ergebnis = $archiv->open($vorlaeufig, ZipArchive::CHECKCONS);

				if (true !== $ergebnis) {
					@unlink($vorlaeufig);

					throw new DsbOAuth2Exception('Das heruntergeladene Archiv ist unvollständig oder defekt (Code '.$ergebnis.').');
				}

				$archiv->close();
			}

			if (!@rename($vorlaeufig, $ziel)) {
				@unlink($vorlaeufig);

				throw new DsbOAuth2Exception('Die fertige Datei läßt sich nicht nach '.$ziel.' verschieben.');
			}

			return (int) filesize($ziel);
		}

		// Hierher kommt der Ablauf nicht: Der zweite Schleifendurchlauf endet
		// immer mit einer Rückgabe oder einer Ausnahme. Die Zeile steht nur,
		// damit die Methode in jedem Fall etwas zurückgibt
		throw new DsbOAuth2Exception('Der Download wurde auch mit erneuertem Token mit HTTP 401 abgewiesen.', 401);
	}

	/**
	 * Liefert ein gültiges Zugangstoken.
	 *
	 * Meist kommt es aus der Tokendatei. Ist es abgelaufen — oder wird mit
	 * `$erneuern` ausdrücklich ein frisches verlangt —, wird es über das
	 * Refresh-Token erneuert; nur wenn auch das nicht geht, wird ein neues
	 * angefordert.
	 *
	 * Die Methode ist öffentlich, damit auch eigene Aufrufe (etwa mit einem
	 * anderen HTTP-Werkzeug) dasselbe Token benutzen können, statt sich ein
	 * zweites zu holen.
	 *
	 * @param bool $erneuern Das hinterlegte Token gilt als verbraucht, auch
	 *                       wenn es rechnerisch noch läuft — nach einem 401
	 *
	 * @return string Das Zugangstoken, ohne den Zusatz „Bearer"
	 *
	 * @throws DsbOAuth2Exception wenn kein Token zu bekommen ist oder gerade
	 *                            eine Wartezeit läuft
	 */
	public function zugangstoken(bool $erneuern = false): string
	{
		$stand = $this->cacheLesen();

		if (!$erneuern && $this->nochGueltig($stand)) {
			return (string) $stand['access_token'];
		}

		$verbraucht = $erneuern && isset($stand['access_token']) ? (string) $stand['access_token'] : '';

		return $this->unterSperre(function () use ($verbraucht) {
			// Unter der Sperre noch einmal aus der DATEI nachsehen: Ein anderer
			// Vorgang kann inzwischen erneuert haben. Dann ist dessen Token ein
			// anderes als das verbrauchte, und es gilt
			$stand = $this->cacheLesen(true);

			if ($this->nochGueltig($stand) && (string) $stand['access_token'] !== $verbraucht) {
				return (string) $stand['access_token'];
			}

			$this->wartezeitPruefen($stand);

			if (!empty($stand['refresh_token'])) {
				try {
					return $this->tokenAnfordern(array(
						'grant_type'    => 'refresh_token',
						'refresh_token' => (string) $stand['refresh_token'],
					));
				} catch (DsbOAuth2Exception $e) {
					// Das Refresh-Token war ungültig — zum Beispiel, weil ein
					// anderer Vorgang es eingelöst hat oder nu es widerrufen
					// hat. Dann bleibt nur ein neues Token
				}
			}

			return $this->tokenAnfordern(array('grant_type' => 'client_credentials'));
		});
	}

	/**
	 * Baut die vollständige Adresse eines Abrufs.
	 *
	 * @param string $pfad      Pfad relativ zur Basisadresse, mit oder ohne
	 *                          führenden Schrägstrich
	 * @param array<string,mixed> $parameter Abfrageparameter; null und '' entfallen
	 *
	 * @return string Vollständige Adresse samt Abfragezeichenkette
	 */
	private function adresse(string $pfad, array $parameter): string
	{
		$url = $this->apiUrl.'/'.ltrim($pfad, '/');

		$parameter = array_filter($parameter, static function ($wert): bool {
			return null !== $wert && '' !== $wert;
		});

		return $parameter ? $url.'?'.http_build_query($parameter) : $url;
	}

	/**
	 * Führt einen GET-Abruf mit Token aus.
	 *
	 * Bricht die Verbindung unterwegs ab, wird einmal nachgefaßt — das kommt
	 * im Dauerbetrieb vor und geht beim zweiten Versuch fast immer durch.
	 * Inhaltliche Fehler (jeder HTTP-Status) werden NICHT wiederholt, sondern
	 * weitergereicht.
	 *
	 * @param string $url          Vollständige Adresse
	 * @param string $token        Zugangstoken ohne „Bearer"
	 * @param bool   $wiederholung Interner Schalter; beim zweiten Versuch true
	 *
	 * @return array{status:int,text:string} Status und Antwortkörper
	 *
	 * @throws DsbOAuth2Exception bei einem Verbindungsfehler
	 */
	private function anfrage(string $url, string $token, bool $wiederholung = false): array
	{
		$verbindung = curl_init($url);
		curl_setopt_array($verbindung, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => array(
				'Accept: application/json',
				'Authorization: Bearer '.$token,
			),
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		));

		$text = curl_exec($verbindung);
		$status = (int) curl_getinfo($verbindung, CURLINFO_RESPONSE_CODE);
		$fehlernummer = curl_errno($verbindung);
		$fehler = curl_error($verbindung);
		curl_close($verbindung);

		if ($fehlernummer && !$wiederholung && in_array($fehlernummer, self::WIEDERHOLBAR, true)) {
			usleep(200000);

			return $this->anfrage($url, $token, true);
		}

		if ($fehlernummer) {
			throw new DsbOAuth2Exception('Verbindungsfehler: '.$fehler);
		}

		return array('status' => $status, 'text' => (string) $text);
	}

	/**
	 * Fordert ein Token an und hinterlegt es.
	 *
	 * Gilt für beide Arten: `client_credentials` für das erste Token,
	 * `refresh_token` für jede Erneuerung. Client-ID, Client Secret und Scope
	 * kommen immer mit hinzu — auch beim Erneuern, so verlangt es die
	 * Anleitung. Die Parameter gehen nach RFC 6749 in den **Körper** der
	 * Anfrage, nicht in die Adresse.
	 *
	 * @param array<string,string> $felder `grant_type` und, beim Erneuern, `refresh_token`
	 *
	 * @return string Das neue Zugangstoken
	 *
	 * @throws DsbOAuth2Exception wenn nu die Anfrage abweist. Bei einem echten
	 *                            Fehlschlag (nicht bloß einem verbrauchten
	 *                            Refresh-Token) wird zusätzlich eine Wartezeit
	 *                            hinterlegt
	 */
	private function tokenAnfordern(array $felder): string
	{
		$felder['client_id'] = $this->clientId;
		$felder['client_secret'] = $this->clientSecret;

		if ('' !== $this->scope) {
			$felder['scope'] = $this->scope;
		}

		$verbindung = curl_init($this->tokenUrl);
		curl_setopt_array($verbindung, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query($felder),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/x-www-form-urlencoded',
				'Accept: application/json',
			),
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		));

		$text = (string) curl_exec($verbindung);
		$status = (int) curl_getinfo($verbindung, CURLINFO_RESPONSE_CODE);
		$fehler = curl_error($verbindung);
		curl_close($verbindung);

		if ('' !== $fehler) {
			// Eine gescheiterte Verbindung belastet das Kontingent nicht und
			// zieht deshalb auch keine Wartezeit nach sich
			throw new DsbOAuth2Exception('Verbindungsfehler beim Tokenabruf: '.$fehler);
		}

		$daten = json_decode($text, true);

		if (200 !== $status || empty($daten['access_token'])) {
			$meldung = (string) ($daten['error_description'] ?? $daten['error'] ?? 'unbekannter Fehler');

			// Ein verbrauchtes Refresh-Token ist kein Grund zu warten: Danach
			// wird gleich ein neues Token angefordert, und das ist der
			// vorgesehene Weg
			if ('refresh_token' !== ($felder['grant_type'] ?? '')) {
				$this->wartezeitSetzen($status, $meldung);
			}

			throw new DsbOAuth2Exception(sprintf('Tokenabruf abgewiesen (HTTP %d): %s', $status, $meldung), $status, $text);
		}

		$gueltig = (int) ($daten['expires_in'] ?? self::TOKEN_LEBENSDAUER);

		$this->cacheSchreiben(array(
			'access_token' => (string) $daten['access_token'],

			// **Immer den neuen Refresh-Token merken.** Kommt keiner mit,
			// gilt der bisherige weiter (RFC 6749 §6) — ihn hier zu löschen
			// hieße, beim nächsten Mal ein neues Token anfordern zu müssen
			'refresh_token' => (string) ($daten['refresh_token'] ?? $this->cacheLesen()['refresh_token'] ?? ''),
			'expires_at'    => time() + ($gueltig > 0 ? $gueltig : self::TOKEN_LEBENSDAUER),
		));

		return (string) $daten['access_token'];
	}

	/**
	 * Beurteilt, ob ein hinterlegtes Token noch benutzt werden kann.
	 *
	 * @param array<string,mixed> $stand Inhalt der Tokendatei
	 *
	 * @return bool true, wenn ein Token vorliegt und der Puffer noch nicht
	 *              angebrochen ist
	 */
	private function nochGueltig(array $stand): bool
	{
		return !empty($stand['access_token'])
			&& isset($stand['expires_at'])
			&& time() < ((int) $stand['expires_at'] - self::PUFFER);
	}

	/**
	 * Bricht ab, wenn nach einem abgewiesenen Tokenabruf noch gewartet wird.
	 *
	 * Ohne diese Bremse wird aus einem abgelehnten Tokenabruf sofort der
	 * nächste: Ein Fehlschlag hinterlegt nichts, also fragt jeder folgende
	 * Aufruf von vorn an. Bei einem Kontingentfehler füttert das genau die
	 * Ursache, und die Anlage kommt aus dem Zustand nicht mehr heraus.
	 *
	 * @param array<string,mixed> $stand Inhalt der Tokendatei
	 *
	 * @return void
	 *
	 * @throws DsbOAuth2Exception solange die Wartezeit läuft
	 */
	private function wartezeitPruefen(array $stand): void
	{
		$bis = (int) ($stand['gesperrt_bis'] ?? 0);

		if ($bis <= time()) {
			return;
		}

		throw new DsbOAuth2Exception(sprintf(
			'Der letzte Tokenabruf wurde abgewiesen (%s). Vor dem nächsten Versuch sind noch %d Sekunden zu warten.',
			(string) ($stand['sperrgrund'] ?? 'ohne Angabe'),
			$bis - time()
		));
	}

	/**
	 * Hinterlegt eine Wartezeit nach einem abgewiesenen Tokenabruf.
	 *
	 * Nach HTTP 403 wird deutlich länger gewartet: Diesen Status benutzt nu für
	 * drei Ursachen zugleich — keine Freischaltung, erschöpftes Kontingent,
	 * falscher Scope. Welche vorliegt, ist der Meldung nicht zu entnehmen, und
	 * bei zweien davon hilft schnelles Nachfassen ohnehin nicht.
	 *
	 * @param int    $status  HTTP-Status der abgewiesenen Antwort
	 * @param string $meldung Fehlertext von nu, für die Fehlersuche
	 *
	 * @return void
	 */
	private function wartezeitSetzen(int $status, string $meldung): void
	{
		$this->cacheSchreiben(array(
			'gesperrt_bis' => time() + (403 === $status ? self::SPERRE_KONTINGENT : self::SPERRE_NORMAL),
			'sperrgrund'   => 'HTTP '.$status.': '.$meldung,
		));
	}

	/**
	 * Liest die hinterlegten Tokendaten.
	 *
	 * Zuerst aus dem Prozessspeicher, dann aus der Datei — in dieser
	 * Reihenfolge, damit ein Lauf auch dann mit einem Token auskommt, wenn die
	 * Datei nicht beschreibbar ist.
	 *
	 * @param bool $frisch Den Prozessspeicher übergehen und die Datei lesen.
	 *                     Nötig unter der Sperre: Dort zählt, was ein anderer
	 *                     Vorgang inzwischen geschrieben hat
	 *
	 * @return array<string,mixed> Leeres Array, wenn nichts hinterlegt oder lesbar ist
	 */
	private function cacheLesen(bool $frisch = false): array
	{
		if (!$frisch && $this->speicher) {
			return $this->speicher;
		}

		if (!$this->ablageNutzbar || !is_file($this->tokendatei)) {
			return $this->speicher;
		}

		$inhalt = @file_get_contents($this->tokendatei);
		$daten = false === $inhalt ? null : json_decode($inhalt, true);

		if (is_array($daten)) {
			$this->speicher = $daten;
		}

		return $this->speicher;
	}

	/**
	 * Legt Tokendaten ab — im Prozessspeicher und in der Datei.
	 *
	 * Ist die Ablage nicht nutzbar, bleibt es beim Arbeitsspeicher — gewarnt
	 * wurde dann schon beim Einrichten (siehe ablagePruefen()).
	 *
	 * @param array<string,mixed> $daten Tokendaten oder ein Sperrvermerk
	 *
	 * @return void
	 */
	private function cacheSchreiben(array $daten): void
	{
		$this->speicher = $daten;

		if (!$this->ablageNutzbar) {
			return;
		}

		if (false === @file_put_contents($this->tokendatei, json_encode($daten))) {
			trigger_error('Die Tokendatei '.$this->tokendatei.' konnte nicht geschrieben werden.', E_USER_WARNING);
		}
	}

	/**
	 * Führt eine Aufgabe aus, während kein anderer Vorgang erneuern kann.
	 *
	 * nu verbraucht ein Refresh-Token beim Einlösen. Erneuern zwei Vorgänge
	 * gleichzeitig, bekommt der erste ein neues Token und der zweite die
	 * Meldung „Refresh Token already used or invalid" — er müßte auf
	 * `client_credentials` ausweichen, und davon sind nur fünf in 30 Minuten
	 * erlaubt. Auf einem belebten Server passiert das regelmäßig, sobald ein
	 * Token abläuft.
	 *
	 * Läßt sich die Sperre nicht setzen, wird ohne sie gearbeitet: Lieber ein
	 * möglicher Wettlauf als gar kein Token.
	 *
	 * @param callable $aufgabe Wird genau einmal aufgerufen und liefert das Token
	 *
	 * @return string Das Ergebnis der Aufgabe
	 */
	private function unterSperre(callable $aufgabe): string
	{
		$sperrdatei = $this->ablageNutzbar ? @fopen($this->tokendatei.'.lock', 'c') : false;

		if (false === $sperrdatei || !@flock($sperrdatei, LOCK_EX)) {
			if (false !== $sperrdatei) {
				@fclose($sperrdatei);
			}

			return $aufgabe();
		}

		try {
			return $aufgabe();
		} finally {
			@flock($sperrdatei, LOCK_UN);
			@fclose($sperrdatei);
		}
	}
}
