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
	public function __construct(
		string $apiBaseUrl    = $GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'],
		string $clientId      = $GLOBALS['TL_CONFIG']['wertungsportal_clientID'],
		string $clientSecret  = $GLOBALS['TL_CONFIG']['wertungsportal_clientSecret'],
		string $tokenEndpoint = $GLOBALS['TL_CONFIG']['wertungsportal_tokenURL'],
		string $scope         = $GLOBALS['TL_CONFIG']['wertungsportal_scopeListe'],
		string $cacheFile     = ''
	) {
		$this->apiBaseUrl    = 'https://schachde-appsdemo.liga.nu/dsbwertungsportal/rs'; //$apiBaseUrl;
		$this->clientId      = '2c4c3c61-ac9b-4095-b008-a78b122b1c35'; //$clientId;
		$this->clientSecret  = 'Kolibrifalter#4287'; //$clientSecret;
		$this->tokenEndpoint = 'https://schachde-portaldemo.liga.nu/rs/auth/token'; //$tokenEndpoint;
		$this->scope         = 'dwz_liste dwz_tournament'; //$scope;
		$this->cacheFile     = $cacheFile !== '' ? $cacheFile : sys_get_temp_dir() . '/oauth2_token_cache.json';
	}

	// ─────────────────────────────────────────────
	//  Hilfsmethode: einheitlichen Fehler-Array erzeugen
	// ─────────────────────────────────────────────
	private function errorResult(string $message, int $httpCode = 0): array
	{
		return [
			'success'   => false,
			'http_code' => $httpCode,
			'error'     => $message,
			'body'      => null,
		];
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
		}
	}

	// ─────────────────────────────────────────────
	//  Token-Request (flexibel für beide Grant-Typen)
	//  Gibt bei Fehler null zurück; $error wird per
	//  Referenz mit der Fehlermeldung befüllt.
	// ─────────────────────────────────────────────
	public function requestToken(array $postFields, string &$error = ''): ?array
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

		$response  = curl_exec($ch);
		$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if($curlError)
		{
			$error = "cURL-Fehler: $curlError";
			return null;
		}

		$data = json_decode($response, true);

		if($httpCode !== 200 || empty($data['access_token']))
		{
			$error = $data['error_description'] ?? $data['error'] ?? 'Unbekannter Fehler';
			$error = "Token-Anfrage fehlgeschlagen (HTTP $httpCode): $error";
			return null;
		}

		return $data;
	}

	// ─────────────────────────────────────────────
	//  Neuen Token via Client Credentials holen
	//  Gibt bei Fehler null zurück; $error wird befüllt.
	// ─────────────────────────────────────────────
	public function fetchNewToken(string &$error = ''): ?array
	{
		$tokenData = $this->requestToken([
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
			'scope'         => $this->scope,
		], $error);

		if($tokenData === null)
		{
			return null;
		}

		$this->saveTokenToCache($tokenData);
		return $tokenData;
	}

	// ─────────────────────────────────────────────
	//  Bestehenden Token via Refresh-Token erneuern
	//  Gibt bei Fehler null zurück; $error wird befüllt.
	// ─────────────────────────────────────────────
	public function refreshToken(string $refreshToken, string &$error = ''): ?array
	{
		$tokenData = $this->requestToken([
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
		], $error);

		if($tokenData === null)
		{
			return null;
		}

		$this->saveTokenToCache($tokenData);
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
	}

	// ─────────────────────────────────────────────
	//  Gültigen Access Token liefern
	//  Gibt bei Fehler null zurück; $error wird befüllt.
	//  Reihenfolge:
	//    1. Gecachter Token noch gültig   → direkt verwenden
	//    2. Refresh-Token vorhanden       → Token erneuern
	//    3. Kein Cache / Refresh ungültig → neuen Token holen
	// ─────────────────────────────────────────────
	public function getValidToken(string &$error = ''): ?string
	{
		$cache = $this->readCache();

		// 1. Access Token noch gültig? (30 Sekunden Puffer)
		if(!empty($cache['access_token']) && isset($cache['expires_at']) && time() < ($cache['expires_at'] - 30))
		{
			return $cache['access_token'];
		}

		// 2. Refresh-Token vorhanden → erneuern statt neu anfordern
		if(!empty($cache['refresh_token']))
		{
			$tokenData = $this->refreshToken($cache['refresh_token'], $error);

			if($tokenData !== null)
			{
				return $tokenData['access_token'];
			}

			// Refresh-Token ungültig (z. B. "already used or invalid") → Cache leeren und neu starten
			$this->clearCache();
			$error = ''; // Fehler zurücksetzen, nächster Versuch folgt
		}

		// 3. Komplett neuen Token holen
		$tokenData = $this->fetchNewToken($error);

		if($tokenData === null)
		{
			return null;
		}

		return $tokenData['access_token'];
	}

	// ─────────────────────────────────────────────
	//  API-Aufruf mit Bearer Token
	//  Gibt bei cURL-Fehler ein Fehler-Array zurück
	//  (http_code = 0, error gesetzt).
	// ─────────────────────────────────────────────
	public function callApi(string $accessToken, string $apiUrl, string $method = 'GET', ?array $body = null): array
	{
		$ch = curl_init($apiUrl);

		$headers = [
			'Authorization: Bearer ' . $accessToken,
			'Accept: application/json',
		];

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
			return $this->errorResult("cURL-Fehler beim API-Aufruf: $curlError");
		}

		return [
			'success'   => ($httpCode >= 200 && $httpCode < 300),
			'http_code' => $httpCode,
			'error'     => null,
			'body'      => json_decode($response, true) ?? $response,
		];
	}

	// ─────────────────────────────────────────────
	//  API-Aufruf mit automatischem Token-Refresh
	//  bei HTTP 401 (Token abgelaufen / ungültig).
	//  Wirft keine Exception – Fehler werden immer
	//  als Array zurückgegeben:
	//    [
	//      'success'   => false,
	//      'http_code' => <int>,
	//      'error'     => '<Fehlermeldung>',
	//      'body'      => null | <API-Antwort>,
	//    ]
	// ─────────────────────────────────────────────
	public function callApiWithRefresh(string $apiUrl, string $method = 'GET', ?array $body = null): array
	{
		// Token beschaffen
		$error = '';
		$token = $this->getValidToken($error);

		if($token === null)
		{
			return $this->errorResult("Token konnte nicht beschafft werden: $error");
		}

		$result = $this->callApi($token, $apiUrl, $method, $body);

		// Bei 401: Cache leeren, einmal neu versuchen
		if($result['http_code'] === 401)
		{
			$cache = $this->readCache();
			$this->clearCache();

			if(!empty($cache['refresh_token']))
			{
				$tokenData = $this->refreshToken($cache['refresh_token'], $error);

				if($tokenData === null)
				{
					// Refresh gescheitert (z. B. "Refresh Token already used or invalid")
					// → neuen Token via Client Credentials holen
					$tokenData = $this->fetchNewToken($error);

					if($tokenData === null)
					{
						return $this->errorResult("Token-Erneuerung nach HTTP 401 fehlgeschlagen: $error", 401);
					}
				}

				$token = $tokenData['access_token'];
			}
			else
			{
				$tokenData = $this->fetchNewToken($error);

				if($tokenData === null)
				{
					return $this->errorResult("Token-Neubeschaffung nach HTTP 401 fehlgeschlagen: $error", 401);
				}

				$token = $tokenData['access_token'];
			}

			$result = $this->callApi($token, $apiUrl, $method, $body);
		}

		return $result;
	}
}
