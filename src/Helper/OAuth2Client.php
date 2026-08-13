<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * OAuth2 Client Credentials Flow mit Refresh-Token-Unterstützung
 * Spec: https://www.oauth.com/oauth2-servers/access-tokens/client-credentials/
 */

class OAuth2Client
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public string $apiBaseUrl;
	public string $clientId;
	public string $clientSecret;
	public string $tokenEndpoint;
	public string $scope;
	public string $cacheFile;
	public int $timeout; // Wartezeit je Aufruf in Sekunden

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
	 * Tokendaten für die Dauer dieses Prozesses.
	 *
	 * Zweite Verteidigungslinie neben der Datei: Läßt sich die Datei nicht
	 * schreiben — verschiedene Benutzer für Web und Kommandozeile, ein eigenes
	 * /tmp je Dienst, ein Aufräumer dazwischen —, bliebe sonst jeder einzelne
	 * Abruf ohne hinterlegtes Token und holte sich ein eigenes. Bei einem
	 * Vorladelauf sind das Hunderte in Minuten.
	 */
	protected static array $tokenSpeicher = array();

	/**
	 * Zeitpunkt, bis zu dem nach einem Fehlschlag nicht erneut angefragt wird,
	 * samt der Meldung von damals. Gilt für diesen Prozess; für die folgenden
	 * steht dasselbe in der Tokendatei.
	 */
	protected static int $gesperrtBis = 0;
	protected static string $sperrgrund = '';

	/**
	 * Prüft, ob die Zugangsdaten der Schnittstelle gepflegt sind.
	 *
	 * Ohne Basisadresse, Kennung, Geheimnis und Token-Adresse ist kein Abruf
	 * möglich. Statisch, damit die Frage beantwortet werden kann, bevor
	 * überhaupt eine Instanz entsteht.
	 *
	 * @return bool true, wenn alle vier Angaben vorliegen
	 */
	public static function eingerichtet(): bool
	{
		foreach (array('wertungsportal_apiBasisURL', 'wertungsportal_clientID', 'wertungsportal_clientSecret', 'wertungsportal_tokenURL') as $strEinstellung)
		{
			if (trim((string) ($GLOBALS['TL_CONFIG'][$strEinstellung] ?? '')) === '') return false;
		}

		return true;
	}

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct()
	{
		// Die Einstellungen werden ausdrücklich in Zeichenketten gewandelt:
		// Solange sie im Backend nicht gepflegt sind, liefert TL_CONFIG null,
		// und die getypten Eigenschaften quittieren das mit einem TypeError —
		// also einem 500er auf jeder Seite, die das Bundle einbindet
		$this->apiBaseUrl    = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'] ?? '');
		$this->clientId      = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_clientID'] ?? '');
		$this->clientSecret  = str_replace('&#35;', '#', (string) ($GLOBALS['TL_CONFIG']['wertungsportal_clientSecret'] ?? ''));
		$this->tokenEndpoint = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_tokenURL'] ?? '');
		$this->scope         = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_scopeListe'] ?? '');
		$this->cacheFile     = self::tokendatei();
		$this->timeout       = \Schachbulle\ContaoWertungsportalBundle\Helper\API::timeout();

		$log = 'OAuth2Client initialisiert mit folgenden Werten:'."\n";
		$log .= 'apiBaseUrl = '.$this->apiBaseUrl."\n";
		$log .= 'clientId = '.$this->clientId."\n";
		$log .= 'clientSecret = '.rawurldecode($this->clientSecret)."\n";
		$log .= 'tokenEndpoint = '.$this->tokenEndpoint."\n";
		$log .= 'scope = '.$this->scope;
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
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
	 * @return string Vollständiger Pfad zur Tokendatei
	 */
	public static function tokendatei(): string
	{
		$wurzel = '';

		try
		{
			$container = \System::getContainer();
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
				if(is_writable($verzeichnis)) return $verzeichnis.'/wertungsportal-token.json';
			}
		}

		return sys_get_temp_dir().'/oauth2_token_cache.json';
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
		if(!empty(self::$tokenSpeicher)) return self::$tokenSpeicher;

		if(!file_exists($this->cacheFile))
		{
			return [];
		}

		$inhalt = @file_get_contents($this->cacheFile);

		if($inhalt === false) return [];

		$daten = json_decode($inhalt, true) ?? [];

		// Was aus der Datei kommt, gilt auch für diesen Prozess
		if(!empty($daten)) self::$tokenSpeicher = $daten;

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
		self::$tokenSpeicher = $data;

		if(@file_put_contents($this->cacheFile, json_encode($data)) === false)
		{
			static $gemeldet = false;

			if(!$gemeldet)
			{
				$gemeldet = true;
				$this->protokolliere('Die Tokendatei '.$this->cacheFile.' läßt sich nicht schreiben. Innerhalb eines Aufrufs hilft der Zwischenspeicher im Arbeitsspeicher, aber jeder neue Seitenaufruf und jeder Cronlauf fordert ein eigenes Zugangstoken an — die Schnittstelle weist das irgendwann mit "Too much access tokens" ab. Bitte Schreibrechte prüfen.');
			}
		}
	}

	public function clearCache(): void
	{
		self::$tokenSpeicher = array();

		if(file_exists($this->cacheFile))
		{
			@unlink($this->cacheFile);
			$log = "🗑️ Token-Cache gelöscht.\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
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
		self::$gesperrtBis = time() + self::TOKENSPERRE;
		self::$sperrgrund = $meldung;

		@file_put_contents($this->cacheFile, json_encode(array(
			'gesperrt_bis' => self::$gesperrtBis,
			'sperrgrund'   => $meldung,
		)));

		$this->protokolliere('Zugangstoken nicht zu bekommen — '.$meldung.'. Weitere Versuche werden für '.self::TOKENSPERRE.' Sekunden ausgesetzt.');
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
			\System::log('Wertungsportal: '.$meldung, __METHOD__, \defined('TL_ERROR') ? TL_ERROR : 'ERROR');
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
		$bis = self::$gesperrtBis;
		$grund = self::$sperrgrund;

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
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		}

		if($curlError)
		{
			return ['error' => true, 'error_message' => "cURL-Fehler: $curlError", 'http_code' => 0];
		}

		$data = json_decode($response, true);

		if($httpCode !== 200 || empty($data['access_token']))
		{
			$errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unbekannter Fehler';
			return ['error' => true, 'error_message' => "Token-Anfrage fehlgeschlagen (HTTP $httpCode): $errorMsg", 'http_code' => $httpCode];
		}

		return array_merge(['error' => false], $data);
	}

	// ─────────────────────────────────────────────
	//  Neuen Token via Client Credentials holen
	// ─────────────────────────────────────────────
	public function fetchNewToken(): array
	{
		$log = "🔑 Hole neuen Access Token (client_credentials) ...\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

		$tokenData = $this->requestToken([
		    'grant_type'    => 'client_credentials',
		    'client_id'     => $this->clientId,
		    'client_secret' => $this->clientSecret,
		    'scope'         => $this->scope,
		]);

		$log = "Neuer Access-Token:\n".print_r($tokenData, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

		if($tokenData['error'])
		{
			return $tokenData;
		}

		$this->saveTokenToCache($tokenData);
		$log = "✅ Neuer Token erhalten (gültig für {$tokenData['expires_in']} Sekunden).\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		return $tokenData;
	}

	// ─────────────────────────────────────────────
	//  Bestehenden Token via Refresh-Token erneuern
	// ─────────────────────────────────────────────
	public function refreshToken(string $refreshToken): array
	{
		$log = "🔄 Erneuere Access Token via Refresh-Token ...\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

		$tokenData = $this->requestToken([
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
		]);

		$log = "Neuer Refresh-Token:\n".print_r($tokenData, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

		if($tokenData['error'])
		{
			return $tokenData;
		}

		$this->saveTokenToCache($tokenData);
		$log = "✅ Token erneuert (gültig für {$tokenData['expires_in']} Sekunden).\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		return $tokenData;
	}

	// ─────────────────────────────────────────────
	//  Token-Daten im Cache speichern
	// ─────────────────────────────────────────────
	public function saveTokenToCache(array $tokenData): void
	{
		$expiresIn = $tokenData['expires_in'] ?? 3600;
		$this->writeCache([
			'access_token'  => $tokenData['access_token'],
			'refresh_token' => $tokenData['refresh_token'] ?? null,
			'expires_at'    => time() + $expiresIn,
		]);
		$log = "Token gespeichert:\n".print_r($tokenData, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
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
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

			return $sperre;
		}

		$cache = $this->readCache();

		// 1. Access Token noch gültig? (30 Sekunden Puffer)
		if(!empty($cache['access_token']) && isset($cache['expires_at']) && time() < ($cache['expires_at'] - 30))
		{
			$log = "ℹ️ Verwende gecachten Access Token.\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			$log = "Gecachter Access-Token:\n".print_r($cache, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			return ['error' => false, 'access_token' => $cache['access_token']];
		}

		// 2. Refresh-Token vorhanden → erneuern statt neu anfordern
		if(!empty($cache['refresh_token']))
		{
			$tokenData = $this->refreshToken($cache['refresh_token']);
			if(!$tokenData['error'])
			{
				return $tokenData;
			}
			// Refresh-Token ungültig → Cache leeren und neu starten
			$log = "⚠️ Refresh fehlgeschlagen ({$tokenData['error_message']}), hole neuen Token ...\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			$this->clearCache();
		}

		// 3. Komplett neuen Token holen
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

	// ─────────────────────────────────────────────
	//  Prüft, ob ein Endpunkt ein Token benötigt.
	//  Nicht öffentlich sind /dwz/persons und /dwz/tournaments –
	//  beide verlangen einen Access Token. Alle anderen
	//  Schnittstellen (z. B. /dwz/dwzliste) sind öffentlich.
	// ─────────────────────────────────────────────
	public function requiresToken(string $apiUrl): bool
	{
		$geschuetztePfade = ['/dwz/persons', '/dwz/tournaments'];

		foreach ($geschuetztePfade as $pfad) {
			if (strpos($apiUrl, $pfad) !== false) {
				return true;
			}
		}

		return false;
	}

	// ─────────────────────────────────────────────
	//  API-Aufruf mit Bearer Token
	// ─────────────────────────────────────────────
	public function callApi(?string $accessToken, string $apiUrl, string $method = 'GET', ?array $body = null): array
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
		curl_close($ch);

		if($curlError)
		{
			return ['error' => true, 'error_message' => "cURL-Fehler beim API-Aufruf: $curlError", 'http_code' => 0];
		}

		return [
			'error'     => false,
			'http_code' => $httpCode,
			'body'      => json_decode($response, true) ?? $response,
		];
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
	 */
	protected function callApiIntern(string $apiUrl, string $method = 'GET', ?array $body = null): array
	{
		$log = 'API-Aufruf: '.$apiUrl."\n";
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

		// Öffentliche Schnittstellen (alles außer /dwz/persons)
		// werden ohne Token aufgerufen.
		if(!$this->requiresToken($apiUrl))
		{
			$log = "ℹ️ Öffentlicher Endpunkt (".$apiUrl.") – Aufruf ohne Token.\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			$result = $this->callApi(null, $apiUrl, $method, $body);
			$log = "Answer REST-API:\n".print_r($result, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			return $result;
		}
		else
		{
			$log = "ℹ️ Geschützter Endpunkt (".$apiUrl.") – Aufruf mit Token.\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		}

		$tokenResult = $this->getValidToken();
		if($tokenResult['error'])
		{
			$log = "Fehler bei Token-Resultat:\n".print_r($tokenResult, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			return $tokenResult;
		}

		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message("Request REST-API: ".$apiUrl, 'wertungsportal_oauth2client.log');
		$result = $this->callApi($tokenResult['access_token'], $apiUrl, $method, $body);
		$log = "Answer REST-API:\n".print_r($result, true);
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

		if($result['error'])
		{
			return $result;
		}

		// Bei 401: Token per Refresh-Token erneuern und einmal wiederholen
		if($result['http_code'] === 401)
		{
			$log = "⚠️ HTTP 401 – Token wird erneuert ...\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');

			// Einmal je Aufruf ins Systemprotokoll, auch ohne Debug-Log: Diese
			// Erneuerung ist die unauffälligste Art, das Tokenkontingent zu
			// verbrauchen. Ein 401 mitten in einem Lauf, dessen Token noch
			// gültig war, deutet auf eine Drosselung der Gegenseite hin — und
			// die Antwort des Bundles darauf ist ausgerechnet ein NEUES Token.
			// Ohne diese Zeile ist das von außen nicht zu erkennen
			static $gemeldet401 = false;

			if(!$gemeldet401)
			{
				$gemeldet401 = true;
				$this->protokolliere('Die Schnittstelle hat einen Abruf mit HTTP 401 abgewiesen, obwohl ein Token vorlag ('.$apiUrl.'). Das Token wird erneuert — das kostet ein weiteres aus dem Kontingent.');
			}

			$cache = $this->readCache();
			$this->clearCache();

			if(!empty($cache['refresh_token']))
			{
				$tokenData = $this->refreshToken($cache['refresh_token']);
			}
			else
			{
				$tokenData = $this->fetchNewToken();
			}

			if($tokenData['error'])
			{
				$tokenData['tokenfehler'] = true;

				if(0 !== (int) ($tokenData['http_code'] ?? 0))
				{
					$this->sperreSetzen((string) ($tokenData['error_message'] ?? ''));
				}

				return $tokenData;
			}

			$result = $this->callApi($tokenData['access_token'], $apiUrl, $method, $body);
			$log = "Answer REST-API:\n".print_r($result, true);
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		}

		if($result['error'])
		{
			$log = "❌ Fehler: " . $result['error_message'] . "\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		}
		else
		{
			$log = "📦 API-Antwort (HTTP {$result['http_code']}):\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
			$log = json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) log_message($log, 'wertungsportal_oauth2client.log');
		}

		return $result;
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
