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

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct()
	{
		$this->apiBaseUrl    = $GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'];
		$this->clientId      = $GLOBALS['TL_CONFIG']['wertungsportal_clientID'];
		$this->clientSecret  = str_replace('&#35;', '#', $GLOBALS['TL_CONFIG']['wertungsportal_clientSecret']);
		$this->tokenEndpoint = $GLOBALS['TL_CONFIG']['wertungsportal_tokenURL'];
		$this->scope         = $GLOBALS['TL_CONFIG']['wertungsportal_scopeListe'];
		$this->cacheFile     = sys_get_temp_dir() . '/oauth2_token_cache.json';

		$log = 'OAuth2Client initialisiert mit folgenden Werten:'."\n";
		$log .= 'apiBaseUrl = '.$this->apiBaseUrl."\n";
		$log .= 'clientId = '.$this->clientId."\n";
		$log .= 'clientSecret = '.rawurldecode($this->clientSecret)."\n";
		$log .= 'tokenEndpoint = '.$this->tokenEndpoint."\n";
		$log .= 'scope = '.$this->scope;
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
	}

	// ─────────────────────────────────────────────
	//  Cache lesen / schreiben / löschen
	// ─────────────────────────────────────────────
	public function readCache(): array
	{
		if(!file_exists($this->cacheFile))
		{
			return [];
		}
		return json_decode(file_get_contents($this->cacheFile), true) ?? [];
	}

	public function writeCache(array $data): void
	{
		file_put_contents($this->cacheFile, json_encode($data));
	}

	public function clearCache(): void
	{
		if(file_exists($this->cacheFile))
		{
			unlink($this->cacheFile);
			$log = "🗑️ Token-Cache gelöscht.\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
		}
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
			CURLOPT_TIMEOUT        => 15,
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
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
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
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');

		$tokenData = $this->requestToken([
		    'grant_type'    => 'client_credentials',
		    'client_id'     => $this->clientId,
		    'client_secret' => $this->clientSecret,
		    'scope'         => $this->scope,
		]);

		$log = "Neuer Access-Token:\n".print_r($tokenData, true);
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');

		if($tokenData['error'])
		{
			return $tokenData;
		}

		$this->saveTokenToCache($tokenData);
		$log = "✅ Neuer Token erhalten (gültig für {$tokenData['expires_in']} Sekunden).\n";
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
		return $tokenData;
	}

	// ─────────────────────────────────────────────
	//  Bestehenden Token via Refresh-Token erneuern
	// ─────────────────────────────────────────────
	public function refreshToken(string $refreshToken): array
	{
		$log = "🔄 Erneuere Access Token via Refresh-Token ...\n";
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');

		$tokenData = $this->requestToken([
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
		]);

		$log = "Neuer Refresh-Token:\n".print_r($tokenData, true);
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');

		if($tokenData['error'])
		{
			return $tokenData;
		}

		$this->saveTokenToCache($tokenData);
		$log = "✅ Token erneuert (gültig für {$tokenData['expires_in']} Sekunden).\n";
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
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
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
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
		$cache = $this->readCache();

		// 1. Access Token noch gültig? (30 Sekunden Puffer)
		if(!empty($cache['access_token']) && isset($cache['expires_at']) && time() < ($cache['expires_at'] - 30))
		{
			$log = "ℹ️ Verwende gecachten Access Token.\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
			$log = "Gecachter Access-Token:\n".print_r($cache, true);
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
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
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
			$this->clearCache();
		}

		// 3. Komplett neuen Token holen
		return $this->fetchNewToken();
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
			CURLOPT_TIMEOUT        => 30,
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
		$log = 'API-Aufruf: '.$apiUrl."\n";
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');

		// Öffentliche Schnittstellen (alles außer /dwz/persons)
		// werden ohne Token aufgerufen.
		if(!$this->requiresToken($apiUrl))
		{
			$log = "ℹ️ Öffentlicher Endpunkt (".$apiUrl.") – Aufruf ohne Token.\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
			$result = $this->callApi(null, $apiUrl, $method, $body);
			$log = "Answer REST-API:\n".print_r($result, true);
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
			return $result;
		}
		else
		{
			$log = "ℹ️ Geschützter Endpunkt (".$apiUrl.") – Aufruf mit Token.\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
		}

		$tokenResult = $this->getValidToken();
		if($tokenResult['error'])
		{
			$log = "Fehler bei Token-Resultat:\n".print_r($tokenResult, true);
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
			return $tokenResult;
		}

		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message("Request REST-API: ".$apiUrl, 'wertungsportal_oauth2client.log');
		$result = $this->callApi($tokenResult['access_token'], $apiUrl, $method, $body);
		$log = "Answer REST-API:\n".print_r($result, true);
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');

		if($result['error'])
		{
			return $result;
		}

		// Bei 401: Token per Refresh-Token erneuern und einmal wiederholen
		if($result['http_code'] === 401)
		{
			$log = "⚠️ HTTP 401 – Token wird erneuert ...\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
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
				return $tokenData;
			}

			$result = $this->callApi($tokenData['access_token'], $apiUrl, $method, $body);
			$log = "Answer REST-API:\n".print_r($result, true);
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
		}

		if($result['error'])
		{
			$log = "❌ Fehler: " . $result['error_message'] . "\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
		}
		else
		{
			$log = "📦 API-Antwort (HTTP {$result['http_code']}):\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
			$log = json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
			if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal_oauth2client.log');
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
