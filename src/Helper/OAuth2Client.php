<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * OAuth2 Client Credentials Flow mit Refresh-Token-Unterstützung
 * Spec: https://www.oauth.com/oauth2-servers/access-tokens/client-credentials/
 *
 * **Zwei Zugänge, zwei Kennungen.** nu vergibt für die Turnier- und
 * Personenabfragen (`/dwz/tournaments`, `/dwz/persons`) eine Kennung und für
 * die DWZ-Liste (`/dwz/dwzliste`, dazu die Zip-Downloads) eine zweite — die
 * DWZ-Liste war bis September 2026 frei abrufbar. Jeder Zugang hat eigene
 * Zugangsdaten, eine eigene Tokendatei, eine eigene Wartezeit nach einem
 * Fehlschlag und ein eigenes Tokenprotokoll: Das Kontingent von nu („Too much
 * access tokens") gilt je Kennung, und ein Engpass der einen darf die andere
 * nicht mitreißen. Welcher Zugang zu einer Adresse gehört, entscheidet
 * zugangFuer(); gepflegt werden die Daten unter Wertungsportal → Einstellungen.
 * Doku: docs/zugang.md
 */

class OAuth2Client
{
	/**
	 * Zugang für `/dwz/tournaments` und `/dwz/persons` — die ursprüngliche
	 * Kennung des Bundles.
	 */
	const ZUGANG_TURNIERE = 'turniere';

	/**
	 * Zugang für `/dwz/dwzliste` (Spielersuche, Karteikarte, Vereins- und
	 * Verbandslisten, Zip-Downloads der DWZ-Liste).
	 */
	const ZUGANG_DWZLISTE = 'dwzliste';

	/**
	 * Einstellungen (tl_settings) je Zugang. Basis- und Token-Adresse teilen
	 * sich beide: Die Kennungen stammen aus demselben Portal von nu.
	 */
	const EINSTELLUNGEN = array
	(
		self::ZUGANG_TURNIERE => array
		(
			'clientId'     => 'wertungsportal_clientID',
			'clientSecret' => 'wertungsportal_clientSecret',
			'scope'        => 'wertungsportal_scopeListe',
		),
		self::ZUGANG_DWZLISTE => array
		(
			'clientId'     => 'wertungsportal_dwzliste_clientID',
			'clientSecret' => 'wertungsportal_dwzliste_clientSecret',
			'scope'        => 'wertungsportal_dwzliste_scope',
		),
	);

	/**
	 * Basisadresse der Produktivschnittstelle. Nur Rückfall für die
	 * Zip-Downloads, falls in den Einstellungen keine Basisadresse steht —
	 * die Downloads liefen früher ganz ohne Einstellungen.
	 */
	const BASIS_PRODUKTIV = 'https://schachde-apps.liga.nu/dsbwertungsportal/rs';

	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public string $zugang; // self::ZUGANG_TURNIERE oder self::ZUGANG_DWZLISTE
	public string $apiBaseUrl;
	public string $clientId;
	public string $clientSecret;
	public string $tokenEndpoint;
	public string $scope;
	public string $cacheFile;
	public int $timeout; // Wartezeit je Aufruf in Sekunden

	/**
	 * Den Antworttext der Schnittstelle unverändert mitliefern.
	 *
	 * Ist der Schalter gesetzt, trägt die Antwort von callApi() zusätzlich den
	 * Schlüssel `roh` mit genau den Bytes, die nu geschickt hat. `body` ist
	 * dagegen schon dekodiert — daraus lässt sich der Originaltext nicht
	 * zurückgewinnen (Schreibweise von Zahlen und Sonderzeichen, Leerraum).
	 *
	 * Gebraucht wird das nur für den Rohdaten-Download im Backend
	 * (`Helper\Rohabfrage`). Deshalb ist es ausdrücklich einzuschalten: Bei
	 * einer großen Antwort hielte sonst jeder Abruf zwei Fassungen im Speicher.
	 */
	public bool $rohantwort = false;

	/**
	 * Dauer des letzten Schnittstellenaufrufs in Millisekunden und die dabei
	 * gerufene Adresse — Grundlage des Zugriffs-Logs (Helper\Zugriffslog).
	 * Statisch, weil das Log in API::autoQuery geschrieben wird und dort keine
	 * Client-Instanz mehr vorliegt.
	 */
	protected static float $dauer = 0.0;
	protected static string $letzteUrl = '';

	/**
	 * Zahl der Schnittstellenaufrufe dieses Seitenaufrufs. Daran erkennt das
	 * Log, ob eine Abfrage überhaupt bei der Schnittstelle war — sonst wäre
	 * bei einem Treffer im Zwischenspeicher die Dauer eines FRÜHEREN Aufrufs
	 * im Protokoll gelandet.
	 */
	protected static int $aufrufe = 0;

	/**
	 * Wartezeit nach einem gescheiterten Tokenabruf, in Sekunden.
	 *
	 * Ohne sie wird aus einem abgelehnten Tokenabruf sofort der nächste: Da ein
	 * Fehlschlag nichts hinterlegt, fragt jeder folgende Aufruf von vorn an.
	 * Bei einem Kontingentfehler („Too much access tokens") füttert das genau
	 * die Ursache und die Anlage kommt aus dem Zustand nicht mehr heraus.
	 */
	const TOKENSPERRE = 300;

	/**
	 * cURL-Fehlernummern, bei denen ein zweiter Versuch sinnvoll ist.
	 *
	 * Alle bezeichnen einen Abriss der VERBINDUNG, nicht eine Antwort des
	 * Servers — beim nächsten Versuch geht es meist durch:
	 *
	 * 16 CURLE_HTTP2            Fehler im HTTP/2-Rahmenwerk
	 * 18 CURLE_PARTIAL_FILE     Antwort brach mittendrin ab
	 * 52 CURLE_GOT_NOTHING      gar keine Antwort erhalten
	 * 55 CURLE_SEND_ERROR       Senden gescheitert
	 * 56 CURLE_RECV_ERROR       Empfangen gescheitert
	 * 92 CURLE_HTTP2_STREAM     einzelner HTTP/2-Strom abgebrochen (CANCEL)
	 *
	 * Bewusst NICHT dabei: 28 (Zeitüberschreitung) — dort ist die Wartezeit
	 * schon voll verbraucht; und 6/7 (Namensauflösung, Verbindungsaufbau) —
	 * die deuten auf eine echte Störung, kein Zucken einer Leitung.
	 */
	const FEHLER_WIEDERHOLBAR = array(16, 18, 52, 55, 56, 92);

	/**
	 * Tokendaten für die Dauer dieses Prozesses, je Tokendatei (= je Zugang).
	 *
	 * Zweite Verteidigungslinie neben der Datei: Läßt sich die Datei nicht
	 * schreiben — verschiedene Benutzer für Web und Kommandozeile, ein eigenes
	 * /tmp je Dienst, ein Aufräumer dazwischen —, bliebe sonst jeder einzelne
	 * Abruf ohne hinterlegtes Token und holte sich ein eigenes. Bei einem
	 * Vorladelauf sind das Hunderte in Minuten.
	 *
	 * Seit 1.46.0 nach Tokendatei geschlüsselt: Mit nur EINEM Speicher bekäme
	 * ein Abruf der DWZ-Liste das Token der Turnierkennung — und nu wiese ihn ab.
	 *
	 * @var array<string,array> Tokendatei => Tokendaten
	 */
	protected static array $tokenSpeicher = array();

	/**
	 * Zeitpunkt, bis zu dem nach einem Fehlschlag nicht erneut angefragt wird,
	 * samt der Meldung von damals — je Tokendatei, damit ein Engpass der einen
	 * Kennung die andere nicht mitsperrt. Gilt für diesen Prozess; für die
	 * folgenden steht dasselbe in der Tokendatei.
	 *
	 * @var array<string,int>    Tokendatei => Zeitpunkt
	 * @var array<string,string> Tokendatei => Meldung
	 */
	protected static array $gesperrtBis = array();
	protected static array $sperrgrund = array();

	/**
	 * Prüft, ob die Zugangsdaten eines Zugangs gepflegt sind.
	 *
	 * Ohne Basisadresse, Token-Adresse, Kennung und Geheimnis ist kein
	 * angemeldeter Abruf möglich. Statisch, damit die Frage beantwortet werden
	 * kann, bevor überhaupt eine Instanz entsteht.
	 *
	 * Ohne Angabe wird der Turnierzugang geprüft — so wie vor 1.46.0, als es
	 * nur ihn gab. An ihm hängt, ob das Bundle die Schnittstelle überhaupt
	 * anspricht (API::autoQuery, Vorlader, Rohdaten).
	 *
	 * @param string $zugang self::ZUGANG_TURNIERE oder self::ZUGANG_DWZLISTE
	 *
	 * @return bool true, wenn alle vier Angaben vorliegen
	 */
	public static function eingerichtet(string $zugang = self::ZUGANG_TURNIERE): bool
	{
		$felder = self::EINSTELLUNGEN[$zugang] ?? self::EINSTELLUNGEN[self::ZUGANG_TURNIERE];

		foreach (array('wertungsportal_apiBasisURL', 'wertungsportal_tokenURL', $felder['clientId'], $felder['clientSecret']) as $strEinstellung)
		{
			if (trim((string) ($GLOBALS['TL_CONFIG'][$strEinstellung] ?? '')) === '') return false;
		}

		return true;
	}

	/**
	 * Ermittelt, mit welchem Zugang eine Adresse der Schnittstelle abgerufen
	 * wird.
	 *
	 * - `/dwz/tournaments`, `/dwz/persons`: Turnierzugang.
	 * - `/dwz/dwzliste`: DWZ-Liste — aber nur, wenn deren Zugangsdaten gepflegt
	 *   sind. Sonst null, also ohne Anmeldung, so wie bis 1.45.1: Bis nu die
	 *   Anmeldung scharf schaltet, läuft eine Installation ohne die neuen Daten
	 *   unverändert weiter. Trägt die DWZ-Liste dieselbe Client-ID wie der
	 *   Turnierzugang, gilt der Turnierzugang — ein gemeinsames Token statt
	 *   zweier Token-Familien derselben Kennung, die das Kontingent doppelt
	 *   belasteten.
	 * - alles andere: null.
	 *
	 * @param string $apiUrl Vollständige Adresse oder Pfad der Schnittstelle
	 *
	 * @return string|null Zugang, null = ohne Anmeldung abrufen
	 */
	public static function zugangFuer(string $apiUrl): ?string
	{
		foreach (array('/dwz/persons', '/dwz/tournaments') as $pfad)
		{
			if (strpos($apiUrl, $pfad) !== false) return self::ZUGANG_TURNIERE;
		}

		if (strpos($apiUrl, '/dwz/dwzliste') === false) return null;

		if (!self::eingerichtet(self::ZUGANG_DWZLISTE)) return null;

		$liste = trim((string) ($GLOBALS['TL_CONFIG'][self::EINSTELLUNGEN[self::ZUGANG_DWZLISTE]['clientId']] ?? ''));
		$turniere = trim((string) ($GLOBALS['TL_CONFIG'][self::EINSTELLUNGEN[self::ZUGANG_TURNIERE]['clientId']] ?? ''));

		return $liste === $turniere ? self::ZUGANG_TURNIERE : self::ZUGANG_DWZLISTE;
	}

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────

	/**
	 * Liest die Zugangsdaten eines Zugangs aus den Einstellungen.
	 *
	 * @param string $zugang self::ZUGANG_TURNIERE (Vorgabe, wie vor 1.46.0)
	 *                       oder self::ZUGANG_DWZLISTE; Unbekanntes gilt als
	 *                       Turnierzugang
	 */
	public function __construct(string $zugang = self::ZUGANG_TURNIERE)
	{
		$this->zugang = isset(self::EINSTELLUNGEN[$zugang]) ? $zugang : self::ZUGANG_TURNIERE;
		$felder = self::EINSTELLUNGEN[$this->zugang];

		// Die Einstellungen werden ausdrücklich in Zeichenketten gewandelt:
		// Solange sie im Backend nicht gepflegt sind, liefert TL_CONFIG null,
		// und die getypten Eigenschaften quittieren das mit einem TypeError —
		// also einem 500er auf jeder Seite, die das Bundle einbindet.
		// Das Geheimnis kommt aus dem Backend mit „#" als Entität zurück
		$this->apiBaseUrl    = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'] ?? '');
		$this->clientId      = trim((string) ($GLOBALS['TL_CONFIG'][$felder['clientId']] ?? ''));
		$this->clientSecret  = str_replace('&#35;', '#', trim((string) ($GLOBALS['TL_CONFIG'][$felder['clientSecret']] ?? '')));
		$this->tokenEndpoint = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_tokenURL'] ?? '');
		$this->scope         = trim((string) ($GLOBALS['TL_CONFIG'][$felder['scope']] ?? ''));
		$this->cacheFile     = self::tokendatei($this->zugang);
		$this->timeout       = \Schachbulle\ContaoWertungsportalBundle\Helper\API::timeout();

		// Das Geheimnis gehört auch ins Fehlerprotokoll nicht hinein — bis
		// 1.45.1 stand es dort im Klartext, sobald das Debug-Log lief
		$log = 'OAuth2Client ('.$this->zugang.') initialisiert mit folgenden Werten:'."\n";
		$log .= 'apiBaseUrl = '.$this->apiBaseUrl."\n";
		$log .= 'clientId = '.$this->clientId."\n";
		$log .= 'clientSecret = '.($this->clientSecret === '' ? '(leer)' : '(gesetzt, '.strlen($this->clientSecret).' Zeichen)')."\n";
		$log .= 'tokenEndpoint = '.$this->tokenEndpoint."\n";
		$log .= 'scope = '.$this->scope;
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
	}

	// ─────────────────────────────────────────────
	//  Cache lesen / schreiben / löschen
	// ─────────────────────────────────────────────

	/**
	 * Liefert den Pfad der Tokendatei.
	 *
	 * Sie liegt in `system/tmp` des Projekts und NICHT mehr in
	 * `sys_get_temp_dir()`. Der Grund ist eine echte Störung vom 11.08.2026:
	 * Das Systemverzeichnis gehört nicht der Anwendung. Webserver und
	 * Kommandozeile laufen dort je nach Hoster unter verschiedenen Benutzern
	 * oder sogar in getrennten Namensräumen (systemd `PrivateTmp`), und ein
	 * Aufräumer kann jederzeit dazwischenfahren. Kann die Datei nicht
	 * geschrieben werden, holt sich JEDER Abruf ein eigenes Token — bei einem
	 * Vorladelauf Hunderte, bis die Schnittstelle mit „Too much access tokens"
	 * abweist. `system/tmp` gehört der Anwendung und wird von beiden Wegen
	 * gleich gesehen.
	 *
	 * Ist der Projektpfad nicht zu ermitteln (eigenständige Download-Skripte
	 * ohne Container), bleibt das Systemverzeichnis als Ausweg.
	 *
	 * Je Zugang eine eigene Datei. Der Turnierzugang behält den Namen von vor
	 * 1.46.0: Mit einem neuen Namen fände die Anlage ihr Token nach dem
	 * Einspielen nicht wieder und holte eine neue Token-Familie.
	 *
	 * @param string $zugang self::ZUGANG_TURNIERE oder self::ZUGANG_DWZLISTE
	 *
	 * @return string Vollständiger Pfad zur Tokendatei
	 */
	public static function tokendatei(string $zugang = self::ZUGANG_TURNIERE): string
	{
		$zusatz = $zugang === self::ZUGANG_TURNIERE ? '' : '-'.preg_replace('/[^a-z]/', '', $zugang);
		$wurzel = '';

		try
		{
			$container = \Contao\System::getContainer();
			if($container && $container->hasParameter('kernel.project_dir')) $wurzel = (string) $container->getParameter('kernel.project_dir');
		}
		catch(\Throwable $e)
		{
			$wurzel = '';
		}

		if($wurzel === '' && \defined('TL_ROOT')) $wurzel = TL_ROOT;

		if($wurzel !== '')
		{
			$verzeichnis = $wurzel.'/system/tmp';

			if(is_dir($verzeichnis) || @mkdir($verzeichnis, 0775, true))
			{
				if(is_writable($verzeichnis)) return $verzeichnis.'/wertungsportal-token'.$zusatz.'.json';
			}
		}

		return sys_get_temp_dir().'/oauth2_token_cache'.str_replace('-', '_', $zusatz).'.json';
	}

	/**
	 * Liest die hinterlegten Tokendaten.
	 *
	 * Zuerst aus dem Prozessspeicher, dann aus der Datei — in dieser
	 * Reihenfolge, damit ein Lauf auch dann mit einem Token auskommt, wenn die
	 * Datei nicht beschreibbar ist.
	 *
	 * @return array Leeres Array, wenn nichts hinterlegt oder lesbar ist
	 */
	public function readCache(): array
	{
		if(!empty(self::$tokenSpeicher[$this->cacheFile])) return self::$tokenSpeicher[$this->cacheFile];

		if(!file_exists($this->cacheFile))
		{
			return [];
		}

		$inhalt = @file_get_contents($this->cacheFile);

		if($inhalt === false) return [];

		$daten = json_decode($inhalt, true) ?? [];

		// Was aus der Datei kommt, gilt auch für diesen Prozess
		if(!empty($daten)) self::$tokenSpeicher[$this->cacheFile] = $daten;

		return $daten;
	}

	/**
	 * Legt Tokendaten ab — immer im Prozessspeicher, zusätzlich in der Datei.
	 *
	 * Schlägt das Schreiben fehl, wird das EINMAL im Systemprotokoll vermerkt.
	 * Lautlos darf es nicht bleiben: Genau dieser Fall führt dazu, dass jeder
	 * Abruf ein neues Token anfordert und das Kontingent der Schnittstelle
	 * aufbraucht.
	 *
	 * @param  array $data Tokendaten (access_token, refresh_token, expires_at …)
	 * @return void
	 */
	public function writeCache(array $data): void
	{
		self::$tokenSpeicher[$this->cacheFile] = $data;

		if(@file_put_contents($this->cacheFile, json_encode($data)) === false)
		{
			static $gemeldet = array();

			if(empty($gemeldet[$this->cacheFile]))
			{
				$gemeldet[$this->cacheFile] = true;
				$this->protokolliere('Die Tokendatei '.$this->cacheFile.' läßt sich nicht schreiben. Innerhalb eines Aufrufs hilft der Zwischenspeicher im Arbeitsspeicher, aber jeder neue Seitenaufruf und jeder Cronlauf fordert ein eigenes Zugangstoken an — die Schnittstelle weist das irgendwann mit "Too much access tokens" ab. Bitte Schreibrechte prüfen.');
			}
		}
	}

	/**
	 * Vergisst das Token dieses Zugangs — im Prozessspeicher und in der Datei.
	 *
	 * Der andere Zugang bleibt unberührt: Ein abgewiesenes Token der
	 * DWZ-Liste sagt nichts über das der Turniere.
	 *
	 * @return void
	 */
	public function clearCache(): void
	{
		unset(self::$tokenSpeicher[$this->cacheFile]);

		if(file_exists($this->cacheFile))
		{
			@unlink($this->cacheFile);
			$log = "🗑️ Token-Cache gelöscht.\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		}
	}

	/**
	 * Vermerkt einen gescheiterten Tokenabruf und verhängt die Wartezeit.
	 *
	 * Der Vermerk geht in den Prozessspeicher UND in die Tokendatei, damit auch
	 * der nächste Seitenaufruf und der nächste Cronlauf ihn sehen. Die Datei
	 * enthält dann kein Token, sondern nur die Sperre — `readCache` liefert
	 * keinen `access_token`, der Ablauf bleibt also derselbe.
	 *
	 * @param  string $meldung Fehlertext der Schnittstelle, für das Protokoll
	 * @return void
	 */
	protected function sperreSetzen(string $meldung): void
	{
		self::$gesperrtBis[$this->cacheFile] = time() + self::TOKENSPERRE;
		self::$sperrgrund[$this->cacheFile] = $meldung;

		@file_put_contents($this->cacheFile, json_encode(array(
			'gesperrt_bis' => self::$gesperrtBis[$this->cacheFile],
			'sperrgrund'   => $meldung,
		)));

		$this->protokolliere('Zugangstoken '.$this->bezeichnung().' nicht zu bekommen — '.$meldung.'. Weitere Versuche werden für '.self::TOKENSPERRE.' Sekunden ausgesetzt.');
	}

	/**
	 * Benennt den Zugang für Protokollzeilen und Meldungen.
	 *
	 * @return string Etwa „(DWZ-Liste)"
	 */
	public function bezeichnung(): string
	{
		return $this->zugang === self::ZUGANG_DWZLISTE ? '(DWZ-Liste)' : '(Turniere und Personen)';
	}

	/**
	 * Schreibt eine Zeile ins Systemprotokoll.
	 *
	 * Eigene Methode, weil `TL_ERROR` in den eigenständigen Download-Skripten
	 * nicht zwingend definiert ist und ein klemmendes Protokoll den Abruf nicht
	 * zusätzlich stören darf.
	 *
	 * @param  string $meldung Text ohne Präfix
	 * @return void
	 */
	protected function protokolliere(string $meldung): void
	{
		try
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsportal: '.$meldung, __METHOD__, 'ERROR');
		}
		catch(\Throwable $e)
		{
			// Beiwerk
		}
	}

	/**
	 * Meldet, ob zurzeit eine Wartezeit nach einem Fehlschlag läuft.
	 *
	 * Prüft den Prozessspeicher und die Tokendatei. Ist die Wartezeit
	 * abgelaufen, gilt sie als aufgehoben.
	 *
	 * @return array|null Fehlerantwort im Format der Schnittstelle,
	 *                    null wenn keine Sperre läuft
	 */
	protected function sperre(): ?array
	{
		$bis = self::$gesperrtBis[$this->cacheFile] ?? 0;
		$grund = self::$sperrgrund[$this->cacheFile] ?? '';

		if($bis === 0 && file_exists($this->cacheFile))
		{
			$daten = json_decode((string) @file_get_contents($this->cacheFile), true) ?? [];
			$bis = (int) ($daten['gesperrt_bis'] ?? 0);
			$grund = (string) ($daten['sperrgrund'] ?? '');
		}

		if($bis <= time()) return null;

		return array(
			'error'         => true,
			'error_message' => $grund !== '' ? $grund : 'Zugangstoken nicht verfügbar',
			'http_code'     => 403,
			'tokenfehler'   => true,
		);
	}

	// ─────────────────────────────────────────────
	//  Token-Request (flexibel für beide Grant-Typen)
	//  Gibt bei Fehler ein Array mit 'error' => true zurück,
	//  statt eine RuntimeException zu werfen.
	// ─────────────────────────────────────────────
	public function requestToken(array $postFields): array
	{
		$ch = curl_init($this->tokenEndpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query($postFields),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/x-www-form-urlencoded',
				'Accept: application/json',
			],
			// Wartezeit aus den Einstellungen; CONNECTTIMEOUT zusätzlich, damit
			// ein nicht erreichbarer Server nicht erst die volle Zeit ausschöpft
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_POSTREDIR      => 3,
		]);

		$response     = curl_exec($ch);
		$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$curlError    = curl_error($ch);
		curl_close($ch);

		if($effectiveUrl !== $this->tokenEndpoint)
		{
			$log = "ℹ️ Weitergeleitet zu: $effectiveUrl\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		}

		if($curlError)
		{
			return ['error' => true, 'error_message' => "cURL-Fehler: $curlError", 'http_code' => 0];
		}

		$data = json_decode($response, true);

		if($httpCode !== 200 || empty($data['access_token']))
		{
			$errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unbekannter Fehler';

			self::buchen($this->zugang, $postFields['grant_type'] ?? '?', 'abgelehnt', 'HTTP '.$httpCode.': '.$errorMsg);

			// Beim Turnierzugang lautet die Meldung wie vor 1.46.0 — nach ihr
			// wird in Protokollen gesucht (docs/vorladen.md)
			$wofuer = $this->zugang === self::ZUGANG_DWZLISTE ? ' für die DWZ-Liste' : '';

			return ['error' => true, 'error_message' => "Token-Anfrage$wofuer fehlgeschlagen (HTTP $httpCode): $errorMsg", 'http_code' => $httpCode];
		}

		self::buchen($this->zugang, $postFields['grant_type'] ?? '?', 'ausgestellt', 'gültig '.($data['expires_in'] ?? '?').' s');

		return array_merge(['error' => false], $data);
	}

	/**
	 * Schreibt jede Tokenanfrage in eine Monatsdatei unter `var/logs`.
	 *
	 * Die Schnittstelle von nu gibt je Kennung nur eine begrenzte Zahl Token
	 * aus und antwortet danach mit „Too much access tokens" — ohne zu sagen,
	 * wo die Grenze liegt oder wieviele gerade offen sind. Ohne eigene
	 * Aufzeichnung läßt sich also weder beurteilen, ob die Anlage zu oft
	 * anfragt, noch der Gegenseite eine Zahl nennen.
	 *
	 * Die Datei ist bewußt klein: eine Zeile je Anfrage, nicht je Abruf. Bei
	 * fünf Minuten Lebensdauer sind das im ungünstigen Fall ein paar hundert
	 * Zeilen am Tag.
	 *
	 * Je Zugang eine eigene Datei: Das Kontingent gilt je Kennung, und so
	 * bleibt jede Datei für sich die Zahl, die man nu nennen kann.
	 *
	 * @param  string $zugang   Zugang, für den angefragt wurde
	 * @param  string $art      grant_type der Anfrage (client_credentials, refresh_token)
	 * @param  string $ergebnis 'ausgestellt' oder 'abgelehnt'
	 * @param  string $hinweis  Gültigkeitsdauer bzw. Fehlertext
	 * @return void
	 */
	protected static function buchen(string $zugang, string $art, string $ergebnis, string $hinweis): void
	{
		try
		{
			$datei = self::tokenprotokoll($zugang);

			if($datei === '') return;

			$neu = !is_file($datei);
			$zeile = date('Y-m-d H:i:s').';'.$art.';'.$ergebnis.';'.str_replace(array(';', "\n", "\r"), ' ', $hinweis).';'.(\PHP_SAPI === 'cli' ? 'cli' : 'web')."\n";

			if($neu) $zeile = "Zeitpunkt;Art;Ergebnis;Hinweis;Herkunft\n".$zeile;

			@file_put_contents($datei, $zeile, FILE_APPEND | LOCK_EX);
		}
		catch(\Throwable $e)
		{
			// Ein klemmendes Protokoll darf keinen Abruf verhindern
		}
	}

	/**
	 * Liefert den Pfad der Monatsdatei für die Tokenanfragen eines Zugangs.
	 *
	 * Der Turnierzugang behält den Namen von vor 1.46.0
	 * (`wertungsportal-token-JJJJ-MM.log`), die DWZ-Liste schreibt nach
	 * `wertungsportal-token-dwzliste-JJJJ-MM.log`.
	 *
	 * @param string $zugang self::ZUGANG_TURNIERE oder self::ZUGANG_DWZLISTE
	 *
	 * @return string Vollständiger Pfad, '' wenn kein Verzeichnis nutzbar ist
	 */
	public static function tokenprotokoll(string $zugang = self::ZUGANG_TURNIERE): string
	{
		$zusatz = $zugang === self::ZUGANG_TURNIERE ? '' : preg_replace('/[^a-z]/', '', $zugang).'-';
		$wurzel = '';

		try
		{
			$container = \Contao\System::getContainer();
			if($container && $container->hasParameter('kernel.project_dir')) $wurzel = (string) $container->getParameter('kernel.project_dir');
		}
		catch(\Throwable $e)
		{
			$wurzel = '';
		}

		if($wurzel === '' && \defined('TL_ROOT')) $wurzel = TL_ROOT;
		if($wurzel === '') return '';

		$verzeichnis = $wurzel.'/var/logs';

		if(!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0775, true)) return '';
		if(!is_writable($verzeichnis)) return '';

		return $verzeichnis.'/wertungsportal-token-'.$zusatz.date('Y-m').'.log';
	}

	// ─────────────────────────────────────────────
	//  Neuen Token via Client Credentials holen
	// ─────────────────────────────────────────────
	public function fetchNewToken(): array
	{
		$log = "🔑 Hole neuen Access Token (client_credentials) ...\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		$felder = array
		(
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
		);

		// Ohne eingetragenen Scope wird keiner angefordert (RFC 6749 §3.3: der
		// Server nimmt dann den der Kennung zugedachten). Bis 1.45.1 ging
		// auch ein leerer mit hinaus — für die DWZ-Liste hat nu keinen genannt
		if($this->scope !== '') $felder['scope'] = $this->scope;

		$tokenData = $this->requestToken($felder);

		$log = "Neuer Access-Token:\n".print_r($tokenData, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		if($tokenData['error'])
		{
			return $tokenData;
		}

		$this->saveTokenToCache($tokenData);
		$log = "✅ Neuer Token erhalten (gültig für {$tokenData['expires_in']} Sekunden).\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		return $tokenData;
	}

	// ─────────────────────────────────────────────
	//  Bestehenden Token via Refresh-Token erneuern
	// ─────────────────────────────────────────────
	public function refreshToken(string $refreshToken): array
	{
		$log = "🔄 Erneuere Access Token via Refresh-Token ...\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		$tokenData = $this->requestToken([
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
		]);

		$log = "Neuer Refresh-Token:\n".print_r($tokenData, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		if($tokenData['error'])
		{
			return $tokenData;
		}

		$this->saveTokenToCache($tokenData);
		$log = "✅ Token erneuert (gültig für {$tokenData['expires_in']} Sekunden).\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		return $tokenData;
	}

	// ─────────────────────────────────────────────
	//  Token-Daten im Cache speichern
	// ─────────────────────────────────────────────
	public function saveTokenToCache(array $tokenData): void
	{
		$expiresIn = $tokenData['expires_in'] ?? 3600;

		// **Ein fehlendes Refresh-Token in der Antwort heißt nicht, dass es
		// keins mehr gibt.** Nach RFC 6749 §6 KANN der Server bei einer
		// Erneuerung ein neues ausstellen — muss aber nicht; dann gilt das
		// bisherige weiter. Wer es hier trotzdem auf null setzt, hat beim
		// nächsten Mal keines mehr und muss über `client_credentials` gehen —
		// und das erzeugt eine neue Token-Familie. Bei fünf Minuten
		// Lebensdauer wäre das alle zehn Minuten eine, und genau daran läuft
		// das Kontingent von nu voll („Too much access tokens").
		$refresh = $tokenData['refresh_token'] ?? null;

		if($refresh === null || $refresh === '')
		{
			$bisher = $this->readCache();
			$refresh = $bisher['refresh_token'] ?? null;
		}

		$this->writeCache([
			'access_token'  => $tokenData['access_token'],
			'refresh_token' => $refresh,
			'expires_at'    => time() + $expiresIn,
		]);
		$log = "Token gespeichert:\n".print_r($tokenData, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
	}

	// ─────────────────────────────────────────────
	//  Gültigen Access Token liefern
	//  Gibt bei Fehler ein Array mit 'error' => true zurück.
	//  Reihenfolge:
	//    1. Gecachter Token noch gültig   → direkt verwenden
	//    2. Refresh-Token vorhanden       → Token erneuern
	//    3. Kein Cache                    → neuen Token holen
	// ─────────────────────────────────────────────
	public function getValidToken(): array
	{
		// 0. Läuft nach einem Fehlschlag noch die Wartezeit? Dann gar nicht
		//    erst anfragen — sonst wird aus einem abgelehnten Tokenabruf sofort
		//    der nächste, und bei einem Kontingentfehler füttert das die Ursache
		$sperre = $this->sperre();

		if($sperre !== null)
		{
			$log = "⛔ Tokenabruf ausgesetzt: ".$sperre['error_message']."\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

			return $sperre;
		}

		$cache = $this->readCache();

		// 1. Access Token noch gültig? (30 Sekunden Puffer)
		if(!empty($cache['access_token']) && isset($cache['expires_at']) && time() < ($cache['expires_at'] - 30))
		{
			$log = "ℹ️ Verwende gecachten Access Token.\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
			$log = "Gecachter Access-Token:\n".print_r($cache, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
			return ['error' => false, 'access_token' => $cache['access_token']];
		}

		// 2. Erneuern — aber immer nur EINER auf einmal.
		//
		// Der Fund vom 13.08.2026 aus dem Tokenprotokoll des Livesystems:
		// Läuft das Token ab, während mehrere Seitenaufrufe gleichzeitig
		// arbeiten, erneuern sie ALLE. nu verbraucht ein Refresh-Token beim
		// Einlösen (Rotation), also bekommt der erste ein neues und die
		// übrigen „HTTP 400: Refresh Token already used or invalid" — und
		// weichen auf `client_credentials` aus. **Jedes Mal entsteht dabei
		// eine neue Token-Familie.** Im Protokoll standen an einer Sekunde
		// bis zu drei solcher Ausweichvorgänge; über den Tag summierte sich
		// das, bis nu mit „Too much access tokens" abwies.
		//
		// Die Dateisperre serialisiert das: Wer sie hat, erneuert; die
		// anderen warten kurz und finden danach das frische Token vor,
		// ohne selbst anzufragen.
		return $this->erneuereMitSperre();
	}

	/**
	 * Erneuert das Token unter einer Dateisperre und gibt es zurück.
	 *
	 * Nach dem Erhalt der Sperre wird **erneut nachgesehen**: Ein anderer
	 * Prozess kann in der Zwischenzeit erneuert haben, dann ist gar keine
	 * Anfrage mehr nötig. Ohne diese zweite Prüfung brächte die Sperre nichts —
	 * die Wartenden würden der Reihe nach doch alle anfragen.
	 *
	 * Läßt sich die Sperre nicht setzen (kein `flock`, keine Schreibrechte),
	 * wird ohne sie gearbeitet. Lieber ein möglicher Wettlauf als gar kein
	 * Token.
	 *
	 * @return array Tokendaten oder Fehlerantwort
	 */
	protected function erneuereMitSperre(): array
	{
		$sperrdatei = @fopen($this->cacheFile.'.lock', 'c');

		if($sperrdatei === false || !@flock($sperrdatei, LOCK_EX))
		{
			if($sperrdatei !== false) @fclose($sperrdatei);

			return $this->erneuere($this->readCache());
		}

		try
		{
			// Zweite Prüfung — und zwar aus der DATEI, nicht aus dem
			// Prozessspeicher: Der kennt nur den Stand von vorhin
			unset(self::$tokenSpeicher[$this->cacheFile]);
			$cache = $this->readCache();

			if(!empty($cache['access_token']) && isset($cache['expires_at']) && time() < ($cache['expires_at'] - 30))
			{
				$log = "ℹ️ Ein anderer Vorgang hat inzwischen erneuert.\n";
				if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

				return ['error' => false, 'access_token' => $cache['access_token']];
			}

			return $this->erneuere($cache);
		}
		finally
		{
			@flock($sperrdatei, LOCK_UN);
			@fclose($sperrdatei);
		}
	}

	/**
	 * Holt ein frisches Token: erst über das Refresh-Token, sonst neu.
	 *
	 * @param  array $cache Bisher hinterlegte Tokendaten
	 * @return array        Tokendaten oder Fehlerantwort mit `tokenfehler`
	 */
	protected function erneuere(array $cache): array
	{
		// Refresh-Token vorhanden → erneuern statt neu anfordern
		if(!empty($cache['refresh_token']))
		{
			$tokenData = $this->refreshToken($cache['refresh_token']);
			if(!$tokenData['error'])
			{
				return $tokenData;
			}
			// Refresh-Token ungültig → Cache leeren und neu starten
			$log = "⚠️ Refresh fehlgeschlagen ({$tokenData['error_message']}), hole neuen Token ...\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
			$this->clearCache();
		}

		// Komplett neuen Token holen
		$tokenData = $this->fetchNewToken();

		// Auch das gescheitert: Wartezeit verhängen und den Grund festhalten.
		// Eine ausgefallene Verbindung (HTTP-Code 0) wird ausgenommen — die
		// belastet die Schnittstelle nicht und darf die Anlage nicht für fünf
		// Minuten lahmlegen, wenn das Netz nur kurz stockte
		if(!empty($tokenData['error']))
		{
			$tokenData['tokenfehler'] = true;

			if(0 !== (int) ($tokenData['http_code'] ?? 0))
			{
				$this->sperreSetzen((string) ($tokenData['error_message'] ?? ''));
			}
		}

		return $tokenData;
	}

	/**
	 * Baut die Adresse eines Zip-Downloads der DWZ-Liste.
	 *
	 * Die Basis kommt aus den Einstellungen — wie bei allen übrigen Abrufen.
	 * Bis 1.45.1 stand die Produktivadresse fest in Downloader und Converter;
	 * mit Anmeldung ginge das nicht mehr gut: Ein Token der Demo-Umgebung taugt
	 * nicht für die Produktivschnittstelle. Ohne eingetragene Basis bleibt die
	 * Produktivadresse, damit die Downloads wie bisher auch ohne gepflegte
	 * Zugangsdaten laufen.
	 *
	 * @param string $datei Dateiname, etwa `LV-0-dwzliste.zip`
	 *
	 * @return string Vollständige Adresse
	 */
	public static function downloadAdresse(string $datei): string
	{
		$basis = rtrim(trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'] ?? '')), '/');

		if($basis === '') $basis = self::BASIS_PRODUKTIV;

		return $basis.'/dwz/dwzliste/download/'.rawurlencode($datei);
	}

	/**
	 * Lädt eine Datei der Schnittstelle herunter und prüft das Ergebnis:
	 * cURL-Fehler, HTTP-Status 200 und (wahlweise) ein gültiges Zip-Archiv.
	 *
	 * Bis 1.45.1 stand diese Funktion als `Helper::DownloadDatei()` im Helper
	 * und lud ohne Anmeldung. Seit nu auch die Zip-Dateien der DWZ-Liste
	 * schützt, braucht der Download das Token der DWZ-Liste — deshalb wohnt er
	 * jetzt neben der Tokenbehandlung.
	 *
	 * - Das Token wird **je Versuch** erfragt: Zwischen zwei Versuchen liegen
	 *   Pausen, und ein Lauf über zwanzig Dateien dauert länger als ein Token
	 *   lebt. Solange es gilt, kostet das keine Anfrage bei nu.
	 * - Ist kein Token zu bekommen, wird ohne versucht — solange nu die
	 *   DWZ-Liste noch frei ausliefert, klappt der Download dann trotzdem.
	 * - Bei **HTTP 401** wird nicht wiederholt: Mit demselben Token scheitert
	 *   jeder weitere Versuch genauso, und ein neues kostet Kontingent.
	 * - Die Gegenstelle wird geprüft (`CURLOPT_SSL_VERIFYPEER`). Bis 1.45.1 war
	 *   die Prüfung abgeschaltet — mit einem Token in der Anfrage darf sie das
	 *   nicht sein. Die übrigen Abrufe prüfen seit jeher.
	 *
	 * Bei Fehlschlag wird der Download nach einer kurzen Pause wiederholt,
	 * unvollständige Dateien werden gelöscht statt liegen gelassen (der
	 * nu-Server liefert gelegentlich abgeschnittene Zips).
	 *
	 * @param string $url      Quell-URL
	 * @param string $ziel     Zielpfad im Dateisystem
	 * @param int    $versuche Maximale Anzahl Versuche
	 * @param bool   $zipCheck Datei nach dem Download als Zip-Archiv prüfen
	 *
	 * @return array array('success' => bool, 'error' => string, 'versuche' => int)
	 */
	public static function herunterladen(string $url, string $ziel, int $versuche = 3, bool $zipCheck = true): array
	{
		$fehler = '';

		for($versuch = 1; $versuch <= $versuche; $versuch++)
		{
			if($versuch > 1) sleep(5); // Kurze Pause vor der Wiederholung

			$tokenfehler = '';
			$kopf = self::anmeldekopf($url, $tokenfehler);

			$fp = @fopen($ziel, 'w');

			if($fp === false)
			{
				return array('success' => false, 'error' => 'Zieldatei kann nicht geschrieben werden: '.$ziel, 'versuche' => $versuch);
			}

			$ch = curl_init($url);
			curl_setopt_array($ch, array
			(
				CURLOPT_FILE           => $fp,
				CURLOPT_HTTPHEADER     => $kopf,
				CURLOPT_TIMEOUT        => 3600,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			));
			curl_exec($ch);
			$curlFehler = curl_errno($ch) ? curl_error($ch) : '';
			$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			curl_close($ch);
			fclose($fp);

			if($curlFehler)
			{
				$fehler = 'Curl-Fehler: '.$curlFehler;
			}
			elseif($httpCode === 401)
			{
				@unlink($ziel);

				if($tokenfehler !== '') $grund = $tokenfehler;
				elseif($kopf) $grund = 'nu hat das Zugangstoken abgewiesen';
				else $grund = 'nu verlangt eine Anmeldung, für die DWZ-Liste sind aber keine Zugangsdaten eingetragen (Wertungsportal → Einstellungen → Zugang zur DWZ-Liste)';

				return array('success' => false, 'error' => 'HTTP-Status 401 — '.$grund, 'versuche' => $versuch);
			}
			elseif($httpCode != 200)
			{
				$fehler = 'HTTP-Status '.$httpCode;
			}
			elseif($zipCheck)
			{
				// Zip-Konsistenzprüfung erkennt auch abgeschnittene Downloads
				$zip = new \ZipArchive();
				$res = $zip->open($ziel, \ZipArchive::CHECKCONS);

				if($res === true)
				{
					$zip->close();

					return array('success' => true, 'error' => '', 'versuche' => $versuch);
				}

				$fehler = 'Zip-Archiv defekt oder unvollständig (Code '.$res.')';
			}
			else
			{
				return array('success' => true, 'error' => '', 'versuche' => $versuch);
			}

			@unlink($ziel); // Defekte Datei nicht liegen lassen
		}

		return array('success' => false, 'error' => $fehler, 'versuche' => $versuche);
	}

	/**
	 * Liefert die Kopfzeile mit dem Zugangstoken für eine Adresse.
	 *
	 * @param string $url          Adresse der Schnittstelle
	 * @param string $tokenfehler  Erhält die Meldung, wenn ein Token nötig
	 *                             wäre, aber keines zu bekommen ist
	 *
	 * @return string[] `Authorization: Bearer …` oder leer (ohne Anmeldung)
	 */
	protected static function anmeldekopf(string $url, string &$tokenfehler): array
	{
		$zugang = self::zugangFuer($url);

		if($zugang === null) return array();

		$token = (new self($zugang))->getValidToken();

		if(!empty($token['error']))
		{
			$tokenfehler = (string) ($token['error_message'] ?? 'Zugangstoken nicht verfügbar');

			return array();
		}

		return array('Authorization: Bearer '.$token['access_token']);
	}

	// ─────────────────────────────────────────────
	//  API-Aufruf mit Bearer Token
	// ─────────────────────────────────────────────
	public function callApi(?string $accessToken, string $apiUrl, string $method = 'GET', ?array $body = null, int $wiederholung = 0): array
	{
		$ch = curl_init($apiUrl);

		$headers = [
			'Accept: application/json',
		];
		
		if($accessToken !== null) 
		{
			$headers[] = 'Authorization: Bearer ' . $accessToken;
		}

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			// Wartezeit aus den Einstellungen (Voreinstellung 30 Sekunden).
			// CONNECTTIMEOUT zusätzlich: Antwortet der Server gar nicht mehr,
			// steht der Seitenaufbau sonst die volle Zeit still
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
			CURLOPT_SSL_VERIFYPEER => true,
		];

		if(strtoupper($method) === 'POST')
		{
			$options[CURLOPT_POST]       = true;
			$options[CURLOPT_POSTFIELDS] = json_encode($body ?? []);
			$headers[]                   = 'Content-Type: application/json';
			$options[CURLOPT_HTTPHEADER] = $headers;
		}

		curl_setopt_array($ch, $options);

		$response  = curl_exec($ch);
		$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		$curlNr    = curl_errno($ch);
		curl_close($ch);

		// Einmal nachfassen, wenn die VERBINDUNG unterwegs abgerissen ist.
		//
		// Beobachtet auf dem Livesystem: „HTTP/2 stream 1 was not closed
		// cleanly: CANCEL (err 8)" — rund jeder 200. Abruf eines Vorladelaufs.
		// Der Server bricht dabei einen einzelnen Strom der gemeinsam genutzten
		// HTTP/2-Verbindung ab; der nächste Versuch geht in aller Regel durch.
		//
		// Ausdrücklich NICHT wiederholt wird bei Zeitüberschreitung (28): Dort
		// wurde die Wartezeit bereits voll ausgeschöpft, ein zweiter Versuch
		// verdoppelte sie nur. Ebenso wenig bei inhaltlichen Fehlern — die
		// haben keinen cURL-Fehler und kommen hier gar nicht an
		if($curlError && $wiederholung < 1 && \in_array($curlNr, self::FEHLER_WIEDERHOLBAR, true))
		{
			usleep(200000); // 0,2 s, damit der Server Luft holen kann

			return $this->callApi($accessToken, $apiUrl, $method, $body, $wiederholung + 1);
		}

		if($curlError)
		{
			return ['error' => true, 'error_message' => "cURL-Fehler beim API-Aufruf: $curlError", 'http_code' => 0];
		}

		$antwort = [
			'error'     => false,
			'http_code' => $httpCode,
			'body'      => json_decode($response, true) ?? $response,
		];

		// Originaltext nur auf Anforderung mitgeben, siehe $rohantwort
		if($this->rohantwort)
		{
			$antwort['roh'] = (string) $response;
		}

		return $antwort;
	}

	// ─────────────────────────────────────────────
	//  API-Aufruf mit automatischem Token-Refresh
	//  bei HTTP 401 (Token abgelaufen / ungültig).
	//  Gibt immer ein Array zurück – niemals eine Exception.
	//  Fehlerfall: ['error' => true, 'error_message' => '...', 'http_code' => ...]
	//  Erfolg:     ['error' => false, 'http_code' => ..., 'body' => ...]
	// ─────────────────────────────────────────────
	public function callApiWithRefresh(string $apiUrl, string $method = 'GET', ?array $body = null): array
	{
		// Nur Zeitnahme und Weitergabe: Die eigentliche Arbeit steckt in
		// callApiIntern. Getrennt, weil die Methode mehrere Rückgabepunkte hat
		// (Token-Fehler, 401-Wiederholung …) und die Messung sonst an jedem
		// einzelnen davon stehen müsste
		$start = microtime(true);
		$result = $this->callApiIntern($apiUrl, $method, $body);

		self::$dauer = (microtime(true) - $start) * 1000;
		self::$letzteUrl = $apiUrl;
		self::$aufrufe++;

		return $result;
	}

	/**
	 * Zahl der Schnittstellenaufrufe dieses Seitenaufrufs.
	 */
	public static function aufrufe(): int
	{
		return self::$aufrufe;
	}

	/**
	 * Dauer des letzten Aufrufs in Millisekunden (0, wenn noch keiner lief).
	 */
	public static function dauer(): float
	{
		return self::$dauer;
	}

	/**
	 * Zuletzt aufgerufene Adresse der Schnittstelle.
	 */
	public static function letzteUrl(): string
	{
		return self::$letzteUrl;
	}

	/**
	 * Der eigentliche Aufruf samt Token-Behandlung (siehe callApiWithRefresh).
	 *
	 * Welche Kennung gilt, entscheidet zugangFuer(). Das Token holt eine
	 * Instanz DIESES Zugangs (`$tokenClient`), der Abruf selbst läuft über die
	 * aufrufende Instanz — so greifen deren Schalter wie `rohantwort`.
	 *
	 * Für die DWZ-Liste gilt eine Übergangsregel: Ist kein Token zu bekommen
	 * (keine Zugangsdaten, falscher Scope, erschöpftes Kontingent), wird sie
	 * ohne abgerufen — solange nu sie noch frei ausliefert, merkt davon kein
	 * Besucher etwas. Erst ein 401 macht daraus einen Tokenfehler (siehe
	 * ohneAnmeldung()), und der schickt die Besucher wie jeder Tokenfehler in
	 * den Notbetrieb statt auf eine Fehlerseite.
	 *
	 * @param string     $apiUrl Vollständige Adresse
	 * @param string     $method GET oder POST
	 * @param array|null $body   Rumpf eines POST
	 *
	 * @return array Antwort wie callApi(); ein Tokenfehler trägt 'tokenfehler' => true,
	 *               eine DWZ-Liste, die trotz Zugangsdaten ohne Token kam,
	 *               'ohne_anmeldung' => Grund
	 */
	protected function callApiIntern(string $apiUrl, string $method = 'GET', ?array $body = null): array
	{
		$log = 'API-Aufruf: '.$apiUrl."\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		$zugang = self::zugangFuer($apiUrl);
		$dwzliste = strpos($apiUrl, '/dwz/dwzliste') !== false;

		// Ohne Anmeldung: frei abrufbare Adressen — und die DWZ-Liste, solange
		// für sie keine Zugangsdaten eingetragen sind
		if($zugang === null)
		{
			$log = "ℹ️ Aufruf ohne Token (".$apiUrl.").\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
			$result = $this->callApi(null, $apiUrl, $method, $body);
			$log = "Answer REST-API:\n".print_r($result, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

			return $dwzliste ? self::ohneAnmeldung($result, '') : $result;
		}

		$tokenClient = $zugang === $this->zugang ? $this : new self($zugang);

		$log = "ℹ️ Geschützter Endpunkt (".$apiUrl.") – Aufruf mit Token ".$tokenClient->bezeichnung().".\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		$tokenResult = $tokenClient->getValidToken();
		if($tokenResult['error'])
		{
			$log = "Fehler bei Token-Resultat:\n".print_r($tokenResult, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

			// Übergangsregel der DWZ-Liste, siehe oben
			if($dwzliste)
			{
				$grund = (string) ($tokenResult['error_message'] ?? 'Zugangstoken nicht verfügbar');
				$antwort = self::ohneAnmeldung($this->callApi(null, $apiUrl, $method, $body), $grund);

				// Kam die Antwort ohne Token, wird das vermerkt: Sonst sähe
				// `wertungsportal:token --pruefen` ein HTTP 200 und hielte
				// falsche Zugangsdaten für richtig
				if(empty($antwort['error'])) $antwort['ohne_anmeldung'] = $grund;

				return $antwort;
			}

			return $tokenResult;
		}

		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll("Request REST-API: ".$apiUrl, 'wertungsportal_oauth2client.log');
		$result = $this->callApi($tokenResult['access_token'], $apiUrl, $method, $body);
		$log = "Answer REST-API:\n".print_r($result, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

		if($result['error'])
		{
			return $result;
		}

		// Bei 401: Token per Refresh-Token erneuern und einmal wiederholen
		if($result['http_code'] === 401)
		{
			$log = "⚠️ HTTP 401 – Token wird erneuert ...\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');

			// Einmal je Aufruf und Zugang ins Systemprotokoll, auch ohne
			// Debug-Log: Diese Erneuerung ist die unauffälligste Art, das
			// Tokenkontingent zu verbrauchen. Ein 401 mitten in einem Lauf,
			// dessen Token noch gültig war, deutet auf eine Drosselung der
			// Gegenseite hin — und die Antwort des Bundles darauf ist
			// ausgerechnet ein NEUES Token. Ohne diese Zeile ist das von außen
			// nicht zu erkennen
			static $gemeldet401 = array();

			if(empty($gemeldet401[$zugang]))
			{
				$gemeldet401[$zugang] = true;
				$tokenClient->protokolliere('Die Schnittstelle hat einen Abruf mit HTTP 401 abgewiesen, obwohl ein Token '.$tokenClient->bezeichnung().' vorlag ('.$apiUrl.'). Das Token wird erneuert — das kostet ein weiteres aus dem Kontingent.');
			}

			$cache = $tokenClient->readCache();
			$tokenClient->clearCache();

			if(!empty($cache['refresh_token']))
			{
				$tokenData = $tokenClient->refreshToken($cache['refresh_token']);
			}
			else
			{
				$tokenData = $tokenClient->fetchNewToken();
			}

			if($tokenData['error'])
			{
				$tokenData['tokenfehler'] = true;

				if(0 !== (int) ($tokenData['http_code'] ?? 0))
				{
					$tokenClient->sperreSetzen((string) ($tokenData['error_message'] ?? ''));
				}

				return $tokenData;
			}

			$result = $this->callApi($tokenData['access_token'], $apiUrl, $method, $body);
			$log = "Answer REST-API:\n".print_r($result, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		}

		if($result['error'])
		{
			$log = "❌ Fehler: " . $result['error_message'] . "\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		}
		else
		{
			$log = "📦 API-Antwort (HTTP {$result['http_code']}):\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
			$log = json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		}

		return $result;
	}

	/**
	 * Wertet einen Abruf der DWZ-Liste aus, der ohne Token lief.
	 *
	 * Antwortet nu darauf mit **HTTP 401**, verlangt die DWZ-Liste eine
	 * Anmeldung, die dieser Abruf nicht hatte. Daraus wird ein Tokenfehler:
	 * API::autoQuery() schickt die Besucher dann in den Notbetrieb
	 * (Zwischenspeicher, örtlicher Bestand) und schreibt den Grund ins
	 * Systemprotokoll. Bis 1.45.1 hätte derselbe 401 als gewöhnliche
	 * Fehlerantwort eine Fehlermeldung auf der Seite erzeugt — genau das wäre
	 * geschehen, sobald nu die Anmeldung scharf schaltet.
	 *
	 * Den Antworttext von nu (`roh`) reicht die Methode weiter, falls er
	 * angefordert war: Im Rohdaten-Modul ist er die genaueste Auskunft.
	 *
	 * @param array  $result      Antwort von callApi()
	 * @param string $tokenfehler Grund, warum kein Token vorlag; leer, wenn für
	 *                            die DWZ-Liste gar keine Zugangsdaten
	 *                            eingetragen sind
	 *
	 * @return array Die Antwort unverändert, bei HTTP 401 ein Tokenfehler
	 */
	protected static function ohneAnmeldung(array $result, string $tokenfehler): array
	{
		if(!empty($result['error']) || 401 !== (int) ($result['http_code'] ?? 0)) return $result;

		$meldung = $tokenfehler !== ''
			? 'Die DWZ-Liste verlangt eine Anmeldung, das Zugangstoken dafür war aber nicht zu bekommen: '.$tokenfehler
			: 'Die DWZ-Liste verlangt eine Anmeldung (HTTP 401), in den Einstellungen des Wertungsportals sind dafür aber keine Zugangsdaten eingetragen (Zugang zur DWZ-Liste).';

		$antwort = array
		(
			'error'         => true,
			'error_message' => $meldung,
			'http_code'     => 401,
			'tokenfehler'   => true,
		);

		if(isset($result['roh'])) $antwort['roh'] = $result['roh'];

		return $antwort;
	}
}

// ─────────────────────────────────────────────
//  Hauptprogramm
// ─────────────────────────────────────────────
// $client = new OAuth2Client();
//
// $result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs');
//
// if ($result['error']) {
//     echo "\n❌ Fehler: " . $result['error_message'] . "\n";
// } else {
//     echo "\n📦 API-Antwort (HTTP {$result['http_code']}):\n";
//     echo json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
// }
