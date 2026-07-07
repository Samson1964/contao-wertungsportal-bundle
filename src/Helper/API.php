<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// API-Dokumentation: https://schachde-appsdemo.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html
// API-Dokumentation: https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html

class API
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	protected static $instance = null;
	var $Fragmente;

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct()
	{
		$this->Fragmente = '';
	}

	/**
	 * Return the current object instance (Singleton)
	 * @return BannerCheckHelper
	 */
	public static function getInstance()
	{
		if(self::$instance === null)
		{
			self::$instance = new \Schachbulle\ContaoWertungsportalBundle\Helper\API();
		}

		return self::$instance;
	}

	/*********************************************************
	 * autoQuery
	 * =========
	 * Vollautomatisierte Abfrage des Wertungsportals inkl. Cachenutzung
	 *
	 * @param       Array mit den Parametern
	 * $param = array
	 * (
	 * 	"funktion" => "Spielerliste", // Funktion/Cachename
	 * 	"cachekey" => "Cacheschlüssel", // Name des Datensatzes im Cache
	 * 	"vorname"  => $vorname, // definierbar anhand Wertungsportal-Funktion
	 * );
	 * @return      Array mit den Rückgabewerten
	*/
	public static function autoQuery($params)
	{
		// ======================================================================
		// Wenn Cache aktiviert, dann Daten laden, wenn vorhanden
		// ======================================================================
		if($GLOBALS['TL_CONFIG']['wertungsportal_cache'])
		{
			// Cache initialisieren
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => 'wp_'.$params['funktion'], 'extension' => '.cache'));
			$cache->eraseExpired(); // Cache aufräumen, abgelaufene Schlüssel löschen
			$cachetime = 3600 * 24; // 24 Stunden

			// Cache laden
			if($cache->isCached($params['cachekey']) && !isset($params['nocache']))
			{
				$cache_result = $cache->retrieve($params['cachekey']);
				return $cache_result; // Abfrageergebnis aus Cache zurückgeben
			}
		}

		// Wertungsportal-Abfrage, wenn Cache leer ist
		if(!isset($cache_result))
		{
			$result = self::getAPI($params);
			if($result['http_code'] == '200')
			{
				// FIDE-Daten hinzuladen
				$result = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::setFIDEDaten($result, $params);
				// im Cache speichern
				if($GLOBALS['TL_CONFIG']['wertungsportal_cache'])
				{
					$cache->store($params['cachekey'], $result, $cachetime);
				}
			}
			return $result; // Abfrageergebnis von Schnittstelle zurückgeben
		}

	}

	/*********************************************************
	 * getAPI
	 * =========
	 * Hier erfolgt die eigentliche Abfrage
	 */
	public static function getAPI($params)
	{
		$client = new \Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client();
		$get = '';

		switch($params['funktion'])
		{
			case 'Spielerliste': // Spielerliste einer Suche
				// vorname = Vorname des Spielers, default = leer
				// nachname = Nachname des Spielers
				$get = '';
				$get .=  $params['vorname'] ? 'firstname='.rawurlencode($params['vorname']).'&' : '';
				$get .=  $params['nachname'] ? 'lastname='.rawurlencode($params['nachname']).'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				break;

			case 'Karteikarte': // Karteikarte eines Spielers nach nu-ID
				// id = nu-ID des Spielers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons/'.$params['id']);
				break;

			case 'Karteikarte_Turniere': // Turniere für die Karteikarte eines Spielers
				// id = nu-ID des Spielers
				//echo '/dwz/persons/'.$params['id'].'/history';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/persons/'.$params['id'].'/history');
				break;

			case 'Spielberichtsbogen': // Scoresheet eines Spielers für ein Turnier
				// id = nu-UUID des Spielers
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier'].'/players/'.$params['id'].'/scoresheet');
				break;

			case 'Turnierinfo': // Kopfdaten eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier']);
				break;

			case 'Turnierliste': // Suchergebnisse nach einem Turnier laden
				// suche = Suche nach Turniername
				$params['zps'] = rtrim($params['zps'], '0'); // nu sucht nach Prefix der ZPS
				$get = '';
				$get .=  $params['suche'] ? 'label='.rawurlencode($params['suche']).'&' : '';
				$get .=  $params['von'] ? 'fromDate='.rawurlencode($params['von']).'&' : '';
				$get .=  $params['bis'] ? 'toDate='.rawurlencode($params['bis']).'&' : '';
				$get .=  $params['zps'] ? 'vkz='.rawurlencode($params['zps']).'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments?'.$get);
				break;

			case 'Turnierauswertung': // DWZ-Auswertung eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier'].'/evaluation');
				break;

			case 'Turnierergebnisse': // Ergebnisse eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier'].'/matches');
				break;

			case 'Vereinsliste': // Spielerliste eines Vereins
				// zps = fünfstellig
				$get =  $params['zps'] ? 'vkz='.rawurlencode($params['zps']) : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				break;

			case 'Verbandsliste': // Spielerliste eines Verbands
				// zps = ein- bis fünfstellig
				$get =  $params['zps'] ? 'vkz='.rawurlencode($params['zps']).'&' : '';
				$get .= $params['limit'] ? 'limit='.$params['limit'].'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				break;

			case 'Vereinsname': // Vereinsname anhand der ZPS
				// zps = fünfstellig
				$get =  $params['zps'] ? 'vkz='.rawurlencode($params['zps']) : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs?'.$get);
				break;

			case 'Verbaende': // Verbände einer ZPS-Struktur laden
				// zps = fünfstellig
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs');
				break;

			default:
		}

		// Abfrageergebnis zurückgeben
		// $result = array
		// (
		//   'success'   => true|false,
		//   'http_code' => 200,          // 0 bei cURL-Fehler
		//   'error'     => null|'...',   // Fehlermeldung im Fehlerfall
		//   'body'      => [...],        // API-Antwort oder null
		// )
		return $result;
	}

	/**
	 * Hook-Funktion:
	 * Wertet das URL-Parameter-Array aus und modifiziert es, wenn das Array für DeWIS bestimmt ist
	 *
	 * @return array
	 */
	public static function getParamsFromUrl($arrFragments)
	{
		//echo "<!--";
		//print_r($arrFragments);
		$args = count($arrFragments); // Anzahl Argumente

		if($args == 1)
		{
			// In $args[0] steht das Seitenalias, jetzt prüfen auf URL-Parameter und ggfs. auf neue URL weiterleiten
			switch($arrFragments[0])
			{

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite():
					if(\Input::get('zps'))
					{
						header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite().'/'.\Input::get('zps').'.html');
					}
					elseif(\Input::get('pkz'))
					{
						header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite().'/'.\Input::get('pkz').'.html');
					}
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite():
					if(\Input::get('zps'))
					{
						header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/'.\Input::get('zps').'.html');
					}
					break;

				default:
			}
		}
		elseif($args > 1)
		{
			// In $args[0] steht das Seitenalias, ab $args[1] die Parameter
			switch($arrFragments[0])
			{

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite():
					if($arrFragments[1] == 'auto_item') $arrFragments[1] = 'id';
					// ZPS-Angabe ggfs. anpassen (4-stellige Mitgliedsnummer!)
					$zps = explode('-', $arrFragments[2]);
					$arrFragments[2] = count($zps) == 2 ? $zps[0].'-'.substr('0000'.$zps[1], -4) : $arrFragments[2];
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite():
					if($arrFragments[1] == 'auto_item') $arrFragments[1] = 'zps';
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite():
					if($arrFragments[1] == 'auto_item') $arrFragments[1] = 'zps';
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite():
					if($arrFragments[1] == 'auto_item')
					{
						$arrFragments[1] = 'code';
					}
					else
					{
						$newArray = array($arrFragments[0]);
						// 1. Wert ist offensichtlich ein Turniercode
						$newArray[1] = 'code';
						$newArray[2] = $arrFragments[1];
						if($arrFragments[2] == 'Ergebnisse')
						{
							// Ein weiterer Wert wartet: Ergebnisse des Turniers anzeigen
							$newArray[3] = 'view';
							$newArray[4] = 'results';
						}
						elseif($arrFragments[2])
						{
							// Ein weiterer Wert wartet: ID des Spielers
							$newArray[3] = 'id';
							$newArray[4] = $arrFragments[2];
							$newArray[5] = 'view';
							$newArray[6] = 'results';
						}
						$arrFragments = $newArray;
					}
					break;

				default:
			}
		}

		return $arrFragments;
	}

	public static function Verbandsliste($zps = '00000')
	{

		// Abfrageparameter einstellen
		$param = array
		(
			'funktion' => 'Verbaende',
			'cachekey' => $zps,
			'zps'      => $zps
		);

		$resultArr = self::autoQuery($param); // Abfrage ausführen

		// Nach Verbänden und Vereinen ordnen
		$verbaende = array(); $vereine = array();
		foreach($resultArr['body']['data'] as $item)
		{
			if(substr($item['clubVkz'], -2) == '00' || $item['clubVkz'] == 'L0001' || $item['clubVkz'] == 'M0001')
			{
				$verbaende[] = $item;
			}
			else
			{
				$vereine[] = $item;
			}
		}

		return array('verbaende' => $verbaende, 'vereine' => $vereine);
	}

	/**
	 * Lädt die FIDE-Daten Elo, Titel, Nation aus der lokalen Quelle
	 */
	public static function getFIDE($fideid)
	{
		$fide = array('land' => false, 'elo' => false, 'titel' => false);
		if($fideid)
		{
			// FIDE-ID in lokaler Datenbank suchen
			$objPlayer = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_elo WHERE fideid = ?")
			                                     ->execute($fideid);
			if($objPlayer->numRows)
			{
				$fide = array
				(
					'land'  => $objPlayer->country,
					'elo'   => $objPlayer->rating,
					'titel' => $objPlayer->title
				);
			}
		}
		return $fide;
	}

}
