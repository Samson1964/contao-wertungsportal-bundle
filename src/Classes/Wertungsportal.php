<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Wertungsportal
{

	public $API_BASE_URL;
	public $CLIENT_ID;
	public $CLIENT_SECRET;
	public $TOKEN_ENDPOINT;
	public $SCOPE;
	public $CACHE_FILE;
	
	function __construct()
	{
		$this->API_BASE_URL = $GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'];
		$this->CLIENT_ID = $GLOBALS['TL_CONFIG']['wertungsportal_clientID'];
		$this->CLIENT_SECRET = $GLOBALS['TL_CONFIG']['wertungsportal_clientSecret'];
		$this->TOKEN_ENDPOINT = $GLOBALS['TL_CONFIG']['wertungsportal_tokenURL'];
		$this->SCOPE = $GLOBALS['TL_CONFIG']['wertungsportal_scopeListe'];
		$this->CACHE_FILE = sys_get_temp_dir() . '/oauth2_wertungsportal_token_cache.json';
	}

	// ─────────────────────────────────────────────
	//  Cache lesen / schreiben / löschen
	// ─────────────────────────────────────────────
	function readCache(): array
	{
		if(!file_exists($this->CACHE_FILE))
		{
			return [];
		}
		return json_decode(file_get_contents($this->CACHE_FILE), true) ?? [];
	}
	
	function writeCache(array $data): void
	{
		file_put_contents($this->CACHE_FILE, json_encode($data));
	}
	
	function clearCache(): void
	{
		if(file_exists($this->CACHE_FILE)) 
		{
			unlink($this->CACHE_FILE);
			echo "🗑️  Token-Cache gelöscht.\n";
		}
	}
	
	// ─────────────────────────────────────────────
	//  Token-Request (flexibel für beide Grant-Typen)
	// ─────────────────────────────────────────────
	function requestToken(array $postFields): array
	{
		$ch = curl_init($this->TOKEN_ENDPOINT);
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
		
		if($effectiveUrl !== $this->TOKEN_ENDPOINT) 
		{
			echo "ℹ️  Weitergeleitet zu: $effectiveUrl\n";
		}
		
		if($curlError) 
		{
			throw new RuntimeException("cURL-Fehler: $curlError");
		}
		
		$data = json_decode($response, true);
		
		if($httpCode !== 200 || empty($data['access_token'])) 
		{
			$errorMsg = $data['error_description'] ?? $data['error'] ?? 'Unbekannter Fehler';
			throw new RuntimeException("Token-Anfrage fehlgeschlagen (HTTP $httpCode): $errorMsg");
		}
		
		return $data;
	}
	
	// ─────────────────────────────────────────────
	//  Neuen Token via Client Credentials holen
	// ─────────────────────────────────────────────
	function fetchNewToken(): array
	{
		echo "🔑 Hole neuen Access Token (client_credentials) ...\n";
		
		$tokenData = $this->requestToken([
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->CLIENT_ID,
			'client_secret' => $this->CLIENT_SECRET,
			'scope'         => $this->SCOPE,
		]);
		
		$this->saveTokenToCache($tokenData);
		echo "✅ Neuer Token erhalten (gültig für {$tokenData['expires_in']} Sekunden).\n";
		return $tokenData;
	}
	
	// ─────────────────────────────────────────────
	//  Bestehenden Token via Refresh-Token erneuern
	// ─────────────────────────────────────────────
	function refreshToken(string $refreshToken): array
	{
		echo "🔄 Erneuere Access Token via Refresh-Token ...\n";
		
		$tokenData = $this->requestToken([
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id'     => $this->CLIENT_ID,
			'client_secret' => $this->CLIENT_SECRET,
		]);
		
		$this->saveTokenToCache($tokenData);
		echo "✅ Token erneuert (gültig für {$tokenData['expires_in']} Sekunden).\n";
		return $tokenData;
	}
	
	// ─────────────────────────────────────────────
	//  Token-Daten im Cache speichern
	// ─────────────────────────────────────────────
	function saveTokenToCache(array $tokenData): void
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
	//  Reihenfolge:
	//    1. Gecachter Token noch gültig   → direkt verwenden
	//    2. Refresh-Token vorhanden       → Token erneuern
	//    3. Kein Cache                    → neuen Token holen
	// ─────────────────────────────────────────────
	function getValidToken(): string
	{
		$cache = $this->readCache();
		
		// 1. Access Token noch gültig? (30 Sekunden Puffer)
		if(!empty($cache['access_token']) && isset($cache['expires_at']) && time() < ($cache['expires_at'] - 30)) 
		{
			echo "ℹ️  Verwende gecachten Access Token.\n";
			return $cache['access_token'];
		}
		
		// 2. Refresh-Token vorhanden → erneuern statt neu anfordern
		if(!empty($cache['refresh_token'])) 
		{
			try 
			{
				$tokenData = $this->refreshToken($cache['refresh_token']);
				return $tokenData['access_token'];
			} 
			catch (RuntimeException $e) 
			{
				// Refresh-Token ungültig → Cache leeren und neu starten
				echo "⚠️  Refresh fehlgeschlagen ({$e->getMessage()}), hole neuen Token ...\n";
				$this->clearCache();
			}
		}
		
		// 3. Komplett neuen Token holen
		$tokenData = $this->fetchNewToken();
		return $tokenData['access_token'];
	}
	
	// ─────────────────────────────────────────────
	//  API-Aufruf mit Bearer Token
	// ─────────────────────────────────────────────
	function callApi(string $accessToken, string $apiUrl, string $method = 'GET', ?array $body = null): array
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
			throw new RuntimeException("cURL-Fehler beim API-Aufruf: $curlError");
		}
		
		return [
			'http_code' => $httpCode,
			'body'      => json_decode($response, true) ?? $response,
		];
	}
	
	// ─────────────────────────────────────────────
	//  API-Aufruf mit automatischem Token-Refresh
	//  bei HTTP 401 (Token abgelaufen / ungültig)
	// ─────────────────────────────────────────────
	function callApiWithRefresh(string $apiUrl, string $method = 'GET', ?array $body = null): array
	{
		$apiUrl = $this->API_BASE_URL.$apiUrl; // API-Basisadresse hinzufügen zum Funktionsuafruf
		
		$token  = $this->getValidToken();
		$result = $this->callApi($token, $apiUrl, $method, $body);
		
		// Bei 401: Token per Refresh-Token erneuern und einmal wiederholen
		if($result['http_code'] === 401) 
		{
			echo "⚠️  HTTP 401 – Token wird erneuert ...\n";
			$cache = $this->readCache();
			$this->clearCache();
			
			if(!empty($cache['refresh_token'])) 
			{
				$tokenData = $this->refreshToken($cache['refresh_token']);
				$token     = $tokenData['access_token'];
			} 
			else 
			{
				$tokenData = $this->fetchNewToken();
				$token     = $tokenData['access_token'];
			}
			
			$result = $this->callApi($token, $apiUrl, $method, $body);
		}
		
		return $result;
	}

}
