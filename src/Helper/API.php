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
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons
				break;

			case 'Karteikarte': // Karteikarte eines Spielers nach nu-ID
				// id = nu-ID des Spielers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons/'.$params['id']);
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons
				break;

			case 'Karteikarte_Turniere': // Turniere für die Karteikarte eines Spielers
				// id = nu-ID des Spielers
				//echo '/dwz/persons/'.$params['id'].'/history';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/persons/'.$params['id'].'/history');
				self::syncPersonHistory($result); // Abgleich mit tl_wertungsportal_persons, _tournaments, _upgrades und tl_wertungsportal_tournaments
				break;

			case 'Spielberichtsbogen': // Scoresheet eines Spielers für ein Turnier
				// id = nu-UUID des Spielers
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier'].'/players/'.$params['id'].'/scoresheet');
				self::syncScoresheet($result); // Abgleich Turnier, Partien und Spielerdaten mit den Turniertabellen
				break;

			case 'Turnierinfo': // Kopfdaten eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier']);
				self::syncTournaments($result); // Abgleich mit tl_wertungsportal_tournaments
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
				self::syncTournaments($result); // Abgleich mit tl_wertungsportal_tournaments
				break;

			case 'Turnierauswertung': // DWZ-Auswertung eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier'].'/evaluation');
				self::syncTournamentEvaluation($result); // Abgleich mit tl_wertungsportal_tournaments und _evaluation
				break;

			case 'Turnierergebnisse': // Ergebnisse eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.$params['turnier'].'/matches');
				self::syncTournamentMatches($result); // Abgleich mit tl_wertungsportal_tournaments_matches (Spielerdaten in _evaluation)
				break;

			case 'Vereinsliste': // Spielerliste eines Vereins
				// zps = fünfstellig
				$get =  $params['zps'] ? 'vkz='.rawurlencode($params['zps']) : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons (Vereine über die Mitgliedschaften)
				break;

			case 'Verbandsliste': // Spielerliste eines Verbands
				// zps = ein- bis fünfstellig
				$get =  $params['zps'] ? 'vkz='.rawurlencode($params['zps']).'&' : '';
				$get .= $params['limit'] ? 'limit='.$params['limit'].'&' : '';
				$get .= $params['geschlecht'] ? 'gender='.$params['geschlecht'].'&' : '';
				$get .= $params['alter_von'] ? 'minAge='.$params['alter_von'].'&' : '';
				$get .= $params['alter_bis'] ? 'maxAge='.$params['alter_bis'].'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons
				break;

			case 'Vereinsname': // Vereinsname anhand der ZPS
				// zps = fünfstellig
				$get =  $params['zps'] ? 'vkz='.rawurlencode($params['zps']) : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs?'.$get);
				self::syncClubs($result); // Abgleich mit tl_wertungsportal_clubs
				break;

			case 'Verbaende': // Verbände einer ZPS-Struktur laden
				// zps = fünfstellig
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs');
				self::syncClubs($result); // Abgleich mit tl_wertungsportal_clubs
				$result = self::BugfixVerbaende($result); // Fehlende Verbände ergänzen
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

	/*********************************************************
	 * syncClubs
	 * =========
	 * Gleicht die Rückgabe von /dwz/dwzliste/clubs mit der Tabelle
	 * tl_wertungsportal_clubs ab. Vorhandene Vereine werden anhand
	 * der VKZ nur bei tatsächlichen Änderungen aktualisiert, unbekannte
	 * gesammelt per Batch-INSERT neu angelegt.
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncClubs($result)
	{
		// Nur bei erfolgreicher Abfrage mit Datenarray abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']['data']) || !is_array($result['body']['data'])) return;

		\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::syncList($result['body']['data']);
	}

	/*********************************************************
	 * syncPersons
	 * ===========
	 * Gleicht die Rückgabe von /dwz/dwzliste/persons mit den Tabellen
	 * tl_wertungsportal_persons und tl_wertungsportal_persons_memberships
	 * ab. Vorhandene Personen werden anhand der UUID aktualisiert,
	 * unbekannte neu angelegt. Das Unter-Array "memberships" wird in die
	 * Kindtabelle übernommen (nicht mehr gemeldete Einträge werden gelöscht).
	 * Verarbeitet sowohl Listen (body.data) als auch einzelne Personen
	 * (z. B. Karteikarte: /dwz/dwzliste/persons/{id}).
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncPersons($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		// Listenabfrage (data-Array) oder einzelne Person?
		$persons = isset($result['body']['data']) ? $result['body']['data'] : $result['body'];
		if(isset($persons['uuid'])) $persons = array($persons); // Einzelne Person in Array packen
		if(!is_array($persons)) return;

		// Personen in einem Rutsch abgleichen (liefert UUID => Datensatz-ID)
		$arrMap = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::syncList($persons);

		// Mitgliedschaften je Person einsammeln und gesammelt abgleichen
		$arrMemberships = array();

		foreach($persons as $person)
		{
			if(!is_array($person) || empty($person['uuid'])) continue; // Ohne UUID kein Abgleich möglich

			$pid = isset($arrMap[(string) $person['uuid']]) ? $arrMap[(string) $person['uuid']] : 0;

			if($pid > 0 && isset($person['memberships']) && is_array($person['memberships']))
			{
				$arrMemberships[$pid] = $person['memberships'];
			}
		}

		if(count($arrMemberships))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::syncForPersons($arrMemberships);
		}
	}

	/*********************************************************
	 * syncPersonHistory
	 * =================
	 * Gleicht die Rückgabe von /dwz/persons/{id}/history (Karteikarte)
	 * mit den lokalen Tabellen ab:
	 * - body.person    -> tl_wertungsportal_persons (inkl. Mitgliedschaften)
	 * - body.entries   -> tl_wertungsportal_persons_tournaments, die
	 *                     Turnierdaten redundanzfrei nach tl_wertungsportal_tournaments
	 * - body.upgrades  -> tl_wertungsportal_persons_upgrades
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncPersonHistory($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		$body = $result['body'];

		// Ohne Person kein Abgleich möglich (pid der Kindtabellen)
		if(!isset($body['person']['uuid'])) return;

		// Person selbst abgleichen (inkl. Mitgliedschaften)
		$model = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::upsertByUuid($body['person']);

		if(isset($body['person']['memberships']) && is_array($body['person']['memberships']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::syncForPerson((int) $model->id, $body['person']['memberships']);
		}

		// Turnierhistorie abgleichen (legt auch die Turniere zentral ab)
		if(isset($body['entries']) && is_array($body['entries']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsTournamentsModel::syncForPerson((int) $model->id, $body['entries']);
		}

		// DWZ-Hochstufungen abgleichen
		if(isset($body['upgrades']) && is_array($body['upgrades']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsUpgradesModel::syncForPerson((int) $model->id, $body['upgrades']);
		}
	}

	/*********************************************************
	 * syncTournaments
	 * ===============
	 * Gleicht Turnierdaten (DSBTournamentDTO) mit der Tabelle
	 * tl_wertungsportal_tournaments ab. Verarbeitet sowohl Listen
	 * (body.data, z. B. Turnierliste) als auch einzelne Turniere
	 * (z. B. Turnierinfo: /dwz/tournaments/{uuid}).
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncTournaments($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		// Listenabfrage (data-Array) oder einzelnes Turnier?
		$tournaments = isset($result['body']['data']) ? $result['body']['data'] : $result['body'];
		if(isset($tournaments['uuid'])) $tournaments = array($tournaments); // Einzelnes Turnier in Array packen
		if(!is_array($tournaments)) return;

		\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::syncList($tournaments);
	}

	/*********************************************************
	 * syncTournamentEvaluation
	 * ========================
	 * Gleicht die Rückgabe von /dwz/tournaments/{uuid}/evaluation ab:
	 * - body.tournament -> tl_wertungsportal_tournaments
	 * - body.players    -> tl_wertungsportal_tournaments_evaluation
	 *                      (inkl. Löschung nicht mehr gemeldeter Spieler)
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncTournamentEvaluation($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		$body = $result['body'];

		// Ohne Turnier kein Abgleich möglich (pid der Kindtabelle)
		if(!isset($body['tournament']['uuid'])) return;

		$tournament = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::upsertByUuid($body['tournament']);

		if(isset($body['players']) && is_array($body['players']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsEvaluationModel::syncForTournament((int) $tournament->id, $body['players'], (string) $tournament->uuid);
		}
	}

	/*********************************************************
	 * syncTournamentMatches
	 * =====================
	 * Gleicht die Rückgabe von /dwz/tournaments/{uuid}/matches (body.data)
	 * mit tl_wertungsportal_tournaments_matches ab. Die eingebetteten
	 * Spieler-DTOs (whitePlayer/blackPlayer) werden redundanzfrei in
	 * tl_wertungsportal_tournaments_evaluation übernommen. Da die Abfrage
	 * paginiert ist, werden keine Partien gelöscht.
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncTournamentMatches($result)
	{
		// Nur bei erfolgreicher Abfrage mit Datenarray abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']['data']) || !is_array($result['body']['data'])) return;

		self::syncMatchList($result['body']['data']);
	}

	/*********************************************************
	 * syncScoresheet
	 * ==============
	 * Gleicht die Rückgabe von /dwz/tournaments/{t}/players/{p}/scoresheet
	 * ab. Das Scoresheet enthält keine neuen Datenstrukturen, aber die
	 * Partien (matches, restpartienAlt) samt Spieler-DTOs des betroffenen
	 * Turniers - diese werden in tl_wertungsportal_tournaments_matches
	 * bzw. tl_wertungsportal_tournaments_evaluation übernommen.
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncScoresheet($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		$matches = array();

		if(isset($result['body']['matches']) && is_array($result['body']['matches']))
		{
			$matches = $result['body']['matches'];
		}

		if(isset($result['body']['restpartienAlt']) && is_array($result['body']['restpartienAlt']))
		{
			$matches = array_merge($matches, $result['body']['restpartienAlt']);
		}

		if(count($matches)) self::syncMatchList($matches);
	}

	/*********************************************************
	 * syncMatchList
	 * =============
	 * Hilfsfunktion: Gruppiert Match-DTOs nach tournamentUuid, legt für
	 * unbekannte Turniere einen Platzhalter in tl_wertungsportal_tournaments
	 * an und übergibt die Partien an das Matches-Model.
	 *
	 * @param       Array $matches Array von Match-DTOs
	 */
	protected static function syncMatchList($matches)
	{
		// Partien nach Turnier gruppieren
		$arrGrouped = array();

		foreach($matches as $match)
		{
			if(!is_array($match) || empty($match['tournamentUuid'])) continue; // Ohne Turnier-UUID kein Abgleich möglich
			$arrGrouped[$match['tournamentUuid']][] = $match;
		}

		foreach($arrGrouped as $uuid => $arrMatches)
		{
			// Turnier ermitteln, ggf. als Platzhalter anlegen (Details kommen per Turnierinfo/Turnierliste)
			$tournament = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::upsertByUuid(array('uuid' => $uuid));
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsMatchesModel::syncForTournament((int) $tournament->id, $arrMatches, (string) $uuid);
		}
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
			$objPlayer = \Database::getInstance()->prepare("SELECT * FROM tl_wertungsportal_elo WHERE fideid = ?")
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

	/**
	 * Liefert die Namen aller Cache-Speicher des Wertungsportals zurück
	 * (entsprechen den funktion-Parametern von autoQuery, Präfix wp_)
	 *
	 * @return array
	 */
	public static function cacheSpeicher()
	{
		return array
		(
			'Spielerliste',
			'Karteikarte',
			'Karteikarte_Turniere',
			'Vereinsliste',
			'Vereinsname',
			'Verbaende',
			'Verbandsliste',
			'Turnierliste',
			'Turnierinfo',
			'Turnierauswertung',
			'Turnierergebnisse',
			'Spielberichtsbogen',
		);
	}

	/**
	 * PurgeJob-Funktion:
	 * Berechnet die Cache-Größe für die Anzeige in der Systemwartung
	 */
	public static function calcCache()
	{
		$string = '</label>';
		foreach(self::cacheSpeicher() as $item)
		{
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => 'wp_'.$item, 'extension' => '.cache'));
			$anzahl = count($cache->retrieveAll()); // Anzahl der Cache-Einträge
			$text = ($anzahl == 1) ? 'Eintrag' : 'Einträge';
			$string .= '<br><span style="font-weight:normal"><span style="color:black">'.$item.':</span> '.$anzahl.' '.$text.'</span>';
		}
		$string .= '<label>';

		return $string;
	}

	/**
	 * PurgeJob-Funktion:
	 * Löscht alle Caches des Wertungsportals. Wird von der Systemwartung
	 * (TL_PURGE) und automatisch nach jedem FIDE-Elo-Import aufgerufen,
	 * damit keine Cache-Einträge mit alter FIDE-Anreicherung übrig bleiben.
	 */
	public static function purgeCache()
	{
		foreach(self::cacheSpeicher() as $item)
		{
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => 'wp_'.$item, 'extension' => '.cache'));
			$cache->eraseAll(); // Cache löschen
		}

		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message('Wertungsportal-Cache geleert', 'wertungsportal.log');
	}

	public static function BugfixVerbaende($resultArr)
	{
		$missingFederations = array
		(
			array
			(
				'clubVkz'          => '20000',
				'clubName'         => 'Bayerischer Schachbund e.V.',
				'federation'       => '2',
				'parentFederation' => '200',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '30000',
				'clubName'         => 'Berliner Schachverband',
				'federation'       => '3',
				'parentFederation' => '300',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '40000',
				'clubName'         => 'Hamburger Schachverband',
				'federation'       => '4',
				'parentFederation' => '400',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '60000',
				'clubName'         => 'Schachbund Nordrhein-Westfalen e.V.',
				'federation'       => '6',
				'parentFederation' => '600',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '70000',
				'clubName'         => 'Niedersächsischer Schachverband e.V.',
				'federation'       => '7',
				'parentFederation' => '700',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '80000',
				'clubName'         => 'Schachbund Rheinland-Pfalz e.V.',
				'federation'       => '8',
				'parentFederation' => '800',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '90000',
				'clubName'         => 'Saarländischer Schachverband',
				'federation'       => '9',
				'parentFederation' => '900',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'A0000',
				'clubName'         => 'Schachverband Schleswig-Holstein',
				'federation'       => 'A',
				'parentFederation' => 'A00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'B0000',
				'clubName'         => 'Landesschachbund Bremen',
				'federation'       => 'B',
				'parentFederation' => 'B00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'C0000',
				'clubName'         => 'Schachverband Württemberg e.V.',
				'federation'       => 'C',
				'parentFederation' => 'C00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'D0000',
				'clubName'         => 'Landesschachbund Brandenburg',
				'federation'       => 'D',
				'parentFederation' => 'D00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'E0000',
				'clubName'         => 'LSV Mecklenburg-Vorpommern',
				'federation'       => 'E',
				'parentFederation' => 'E00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'G0000',
				'clubName'         => 'LSV Sachsen-Anhalt',
				'federation'       => 'G',
				'parentFederation' => 'G00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'H0000',
				'clubName'         => 'Thüringer Schachbund',
				'federation'       => 'H',
				'parentFederation' => 'H00',
				'state'            => 'DELETE_STATE_FALSE',
			),
		);

		// Vereine umbauen mit der VKZ als Index für eine schnellere Suche
		$vkzArr = array(); // Array[vkz] = Index
		for($x = 0; $x < count($resultArr['body']['data']); $x++)
		{
			$vkz = $resultArr['body']['data'][$x]['clubVkz'];
			$vkzArr[$vkz] = $x;
		}

		foreach($missingFederations as $federation)
		{
			if(!isset($vkzArr[$federation['clubVkz']]))
			{
				$resultArr['body']['data'][] = $federation;
				$vkzArr[$federation['clubVkz']] = count($resultArr['body']['data']) - 1;
			}
		}

		usort($resultArr['body']['data'], function($a, $b)
		{
			return strcmp($a['clubVkz'], $b['clubVkz']);
		});

		return $resultArr;
	}

}
