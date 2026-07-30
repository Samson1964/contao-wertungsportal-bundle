<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Beantwortet Wertungsportal-Abfragen aus den lokalen Spiegeltabellen.
 *
 * Dritte und letzte Stufe der Auslieferung: Kommt eine Anfrage nicht von der
 * Schnittstelle (abgeschaltet oder ohne Antwort) und liegt auch nichts im
 * Zwischenspeicher, wird hier nachgesehen. Die Tabellen füllen sich über die
 * Syncs jeder erfolgreichen Abfrage sowie über die CSV-Importe.
 *
 * WICHTIG ZUR EINORDNUNG: Der lokale Bestand ist ein Spiegel, keine Kopie.
 * Er enthält nur, was schon einmal abgerufen oder importiert wurde, und ist
 * so alt wie der letzte Abgleich. Deshalb weist die Ausgabe darauf hin
 * (Helper::cacheHinweis) und nennt den Stand der Daten.
 *
 * Jede Methode baut ihre Antwort in der FORM DER SCHNITTSTELLE, damit die
 * Formatter-Klassen (Spielersuche, Karteikarte, Turnierauswertung …)
 * unverändert weiterarbeiten. Die Spaltennamen der Spiegeltabellen
 * entsprechen den Feldnamen der Schnittstelle — die Syncs schreiben sie 1:1.
 */
class Lokal
{
	/**
	 * Zeitstempel der Datensätze, die in diesem Seitenaufruf aus der lokalen
	 * Datenbank beantwortet wurden. Daraus baut Helper::cacheHinweis() den
	 * Hinweis samt Stand der Daten.
	 */
	protected static $stand = array();

	/**
	 * Obergrenze für Listen ohne eigene Begrenzung. Verhindert, dass eine
	 * Abfrage ohne Filter die halbe Personentabelle in den Speicher holt.
	 */
	const MAX_ZEILEN = 1000;

	/**
	 * Personenfelder, die eine Antwort der Schnittstelle mitbringt.
	 */
	const PERSONENFELDER = 'p.id, p.tstamp, p.uuid, p.nuLigaPersonId, p.firstname, p.lastname, p.birthyear, p.gender, p.fideId, p.rating, p.`index`, p.weekOfLastTournamentEvaluation';

	/**
	 * Verteiler: Beantwortet eine Abfrage aus der lokalen Datenbank.
	 *
	 * @param  array $params    Parameter wie für API::autoQuery (funktion + Argumente)
	 * @return array|false      Antwort in der Form der Schnittstelle, oder
	 *                          false, wenn lokal nichts vorliegt bzw. die
	 *                          Funktion örtlich nicht beantwortbar ist
	 */
	public static function abfrage($params)
	{
		// Der Notbetrieb darf NIEMALS schlimmer ausgehen als gar kein
		// Notbetrieb: Er springt ein, wenn die Schnittstelle schon versagt hat.
		// Scheitert dann auch die örtliche Abfrage (fehlende Spalte, fehlende
		// Tabelle, weil contao:migrate noch nicht gelaufen ist), muss das wie
		// „örtlich nichts gefunden" behandelt werden — die Ausgabe zeigt dann
		// die Meldung, dass keine Live-Daten verfügbar sind.
		// Ohne diese Absicherung wurde aus einer behandelten Fehlermeldung ein
		// HTTP 500; genau so ist es am 30.07.2026 auf schachbund.de passiert,
		// weil in partien() zwei DCA-Anzeigefelder als Spalten gelesen wurden.
		try
		{
			return self::abfrageIntern($params);
		}
		catch(\Throwable $e)
		{
			if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']))
			{
				log_message('Örtliche Abfrage ('.($params['funktion'] ?? '?').') fehlgeschlagen: '.$e->getMessage(), 'wertungsportal_oauth2client.log');
			}

			return false;
		}
	}

	/**
	 * Der eigentliche Verteiler (siehe abfrage).
	 *
	 * @param  array $params
	 * @return array|false
	 */
	protected static function abfrageIntern($params)
	{
		$funktion = (string) ($params['funktion'] ?? '');

		switch($funktion)
		{
			case 'Spielerliste':        $ergebnis = self::spielerliste($params); break;
			case 'Karteikarte':         $ergebnis = self::karteikarte($params); break;
			case 'Karteikarte_Turniere':$ergebnis = self::karteikarteTurniere($params); break;
			case 'Vereinsliste':        $ergebnis = self::vereinsliste($params); break;
			case 'Verbandsliste':       $ergebnis = self::verbandsliste($params); break;
			case 'Vereinsname':         $ergebnis = self::vereinsname($params); break;
			case 'Verbaende':           $ergebnis = self::verbaende($params); break;
			case 'Turnierliste':        $ergebnis = self::turnierliste($params); break;
			case 'Turnierinfo':         $ergebnis = self::turnierinfo($params); break;
			case 'Turnierauswertung':   $ergebnis = self::turnierauswertung($params); break;
			case 'Turnierergebnisse':   $ergebnis = self::turnierergebnisse($params); break;
			case 'Spielberichtsbogen':  $ergebnis = self::spielberichtsbogen($params); break;
			default:                    $ergebnis = null;
		}

		if(!is_array($ergebnis)) return false;

		$stand = (int) ($ergebnis['stand'] ?? 0);
		self::$stand[] = $stand;

		$result = array
		(
			'error'       => false,
			'http_code'   => 200,
			'body'        => $ergebnis['body'],
			'lokalquelle' => true,
			'lokalstand'  => $stand,
		);

		// FIDE-Daten wie bei einer Antwort der Schnittstelle anreichern,
		// damit Elo und Titel auch im Notbetrieb in den Listen stehen
		return \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::setFIDEDaten($result, $params);
	}

	/**
	 * Meldet, ob in diesem Seitenaufruf aus der lokalen Datenbank geantwortet
	 * wurde, und liefert den ältesten Stand der beteiligten Daten.
	 *
	 * @return false|int false = keine lokale Antwort, sonst Zeitstempel
	 *                   (0, wenn kein Datensatz einen Zeitstempel trägt)
	 */
	public static function stand()
	{
		if(!count(self::$stand)) return false;

		$zeiten = array_filter(self::$stand, function($wert) { return $wert > 0; });

		return count($zeiten) ? min($zeiten) : 0;
	}

	// ─────────────────────────────────────────────
	//  Vereine und Verbände
	// ─────────────────────────────────────────────

	/**
	 * Alle Vereine und Verbände (entspricht /dwz/dwzliste/clubs ohne Filter).
	 *
	 * @param  array $params  wird nicht ausgewertet (die Funktion kennt keine Argumente)
	 * @return array|null     ['body' => ['data' => [...]], 'stand' => ts]
	 */
	protected static function verbaende($params)
	{
		return self::clubs(array("c.published = '1'"), array());
	}

	/**
	 * Ein Verein oder Verband anhand seiner VKZ (entspricht
	 * /dwz/dwzliste/clubs?vkz=…).
	 *
	 * @param  array $params  zps = VKZ
	 * @return array|null
	 */
	protected static function vereinsname($params)
	{
		$zps = trim((string) ($params['zps'] ?? ''));
		if($zps === '') return null;

		return self::clubs(array("c.published = '1'", 'c.clubVkz = ?'), array($zps));
	}

	/**
	 * Gemeinsame Vereinsabfrage für verbaende() und vereinsname().
	 *
	 * @param  array $bedingungen  WHERE-Teile (mit Präfix c.)
	 * @param  array $werte        Werte zu den Platzhaltern
	 * @return array|null
	 */
	protected static function clubs($bedingungen, $werte)
	{
		$objClubs = \Database::getInstance()->prepare("SELECT c.tstamp, c.clubVkz, c.clubName, c.federation, c.parentFederation, c.state FROM tl_wertungsportal_clubs c WHERE ".implode(' AND ', $bedingungen)." ORDER BY c.clubVkz LIMIT ".self::MAX_ZEILEN * 5)
		                                   ->execute(...$werte);

		if(!$objClubs->numRows) return null;

		$daten = array();
		$stand = 0;

		while($objClubs->next())
		{
			$stand = max($stand, (int) $objClubs->tstamp);
			$daten[] = array
			(
				'clubVkz'          => $objClubs->clubVkz,
				'clubName'         => $objClubs->clubName,
				'federation'       => $objClubs->federation,
				'parentFederation' => $objClubs->parentFederation,
				'state'            => $objClubs->state,
			);
		}

		return array('body' => array('data' => $daten), 'stand' => $stand);
	}

	// ─────────────────────────────────────────────
	//  Personen
	// ─────────────────────────────────────────────

	/**
	 * Spielersuche. Nutzt die vorhandene Namensanfang-Suche über die
	 * Aliasfelder, die schon als Fallback der Schnittstellensuche dient —
	 * sie filtert Abgemeldete, Verstorbene und Gesperrte bereits korrekt.
	 *
	 * @param  array $params  nachname, vorname
	 * @return array|null
	 */
	protected static function spielerliste($params)
	{
		$treffer = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::lokaleSpielersuche((string) ($params['nachname'] ?? ''), (string) ($params['vorname'] ?? ''));

		if(!is_array($treffer) || empty($treffer['body']['data'])) return null;

		return array('body' => $treffer['body'], 'stand' => self::standDerPersonen($treffer['body']['data']));
	}

	/**
	 * Karteikarte einer Person (entspricht /dwz/dwzliste/persons/{id}).
	 * Die Antwort ist FLACH — die Personenfelder stehen direkt unter body,
	 * die Mitgliedschaften als Unterarray.
	 *
	 * @param  array $params  id = nu-ID der Person
	 * @return array|null
	 */
	protected static function karteikarte($params)
	{
		$id = trim((string) ($params['id'] ?? ''));
		if($id === '') return null;

		$objPerson = \Database::getInstance()->prepare("SELECT id, tstamp, uuid, nuLigaPersonId, firstname, lastname, birthyear, gender, fideId, rating, `index`, weekOfLastTournamentEvaluation FROM tl_wertungsportal_persons WHERE published = '1' AND nuLigaPersonId = ?")
		                                    ->execute($id);

		// next() ausdrücklich: row() lädt die erste Zeile zwar von selbst nach,
		// aber darauf soll sich hier nichts verlassen
		if(!$objPerson->numRows || !$objPerson->next()) return null;

		$row = $objPerson->row();
		$body = self::personDto($row);
		$mitgliedschaften = self::mitgliedschaften(array((int) $row['id']));
		$body['memberships'] = $mitgliedschaften['daten'][(int) $row['id']] ?? array();

		return array('body' => $body, 'stand' => max((int) $row['tstamp'], $mitgliedschaften['stand']));
	}

	/**
	 * Turnierhistorie und DWZ-Umstufungen einer Person (entspricht
	 * /dwz/persons/{id}/history): body.entries[] mit je einem Unterarray
	 * "tournament" und "player", dazu body.upgrades[].
	 *
	 * @param  array $params  id = nu-ID der Person
	 * @return array|null
	 */
	protected static function karteikarteTurniere($params)
	{
		$id = trim((string) ($params['id'] ?? ''));
		if($id === '') return null;

		$db = \Database::getInstance();

		$objPerson = $db->prepare("SELECT id, tstamp, uuid, nuLigaPersonId, firstname, lastname, birthyear, gender, fideId, rating, `index`, weekOfLastTournamentEvaluation FROM tl_wertungsportal_persons WHERE published = '1' AND nuLigaPersonId = ?")
		                ->execute($id);

		if(!$objPerson->numRows || !$objPerson->next()) return null;

		$person = $objPerson->row();
		$pid = (int) $person['id'];
		$stand = (int) $person['tstamp'];

		// Turniereinträge samt Turnierbezeichnung. Die Verbindung läuft über
		// die Turnier-UUID; fehlt das Turnier lokal, bleibt die Bezeichnung
		// leer statt den Eintrag ganz zu verlieren
		$objEintraege = $db->prepare("SELECT e.*, t.label AS t_label, t.enddate AS t_enddate, t.vkz AS t_vkz FROM tl_wertungsportal_persons_tournaments e LEFT JOIN tl_wertungsportal_tournaments t ON t.uuid = e.tournamentUuid WHERE e.pid = ? AND e.published = '1' ORDER BY t.enddate DESC LIMIT ".self::MAX_ZEILEN)
		                   ->execute($pid);

		$entries = array();
		while($objEintraege->next())
		{
			$row = $objEintraege->row();
			$stand = max($stand, (int) $row['tstamp']);

			$entries[] = array
			(
				'tournament' => array
				(
					'uuid'    => $row['tournamentUuid'],
					'label'   => (string) $row['t_label'],
					'enddate' => (string) $row['t_enddate'],
					'vkz'     => (string) $row['t_vkz'],
				),
				'player' => self::spielerDto($row),
			);
		}

		// DWZ-Umstufungen
		$objUpgrades = $db->prepare("SELECT tstamp, referenceDate, name, ratingOld, indexOld, ratingNew, indexNew FROM tl_wertungsportal_persons_upgrades WHERE pid = ? AND published = '1' ORDER BY referenceDate DESC LIMIT ".self::MAX_ZEILEN)
		                  ->execute($pid);

		$upgrades = array();
		while($objUpgrades->next())
		{
			$stand = max($stand, (int) $objUpgrades->tstamp);
			$upgrades[] = array
			(
				'referenceDate' => $objUpgrades->referenceDate,
				'name'          => $objUpgrades->name,
				'ratingOld'     => self::zahl($objUpgrades->ratingOld),
				'indexOld'      => self::zahl($objUpgrades->indexOld),
				'ratingNew'     => self::zahl($objUpgrades->ratingNew),
				'indexNew'      => self::zahl($objUpgrades->indexNew),
			);
		}

		if(!count($entries) && !count($upgrades)) return null;

		$body = array
		(
			'person'   => self::personDto($person),
			'entries'  => $entries,
			'upgrades' => $upgrades,
		);

		return array('body' => $body, 'stand' => $stand);
	}

	/**
	 * Mitgliederliste eines Vereins (entspricht /dwz/dwzliste/persons?vkz=…
	 * mit fünfstelliger VKZ).
	 *
	 * @param  array $params  zps = VKZ des Vereins
	 * @return array|null
	 */
	protected static function vereinsliste($params)
	{
		$zps = trim((string) ($params['zps'] ?? ''));
		if($zps === '') return null;

		// Getrieben wird die Abfrage über den Index auf memberships.vkz: Ein
		// Verein hat wenige hundert Mitglieder, die Namenssortierung danach
		// kostet nichts (gemessen 1,6 ms bei 160 Mitgliedern)
		$laufend = self::laufendeGenehmigung();

		$sql = "SELECT DISTINCT ".self::PERSONENFELDER
		     . " FROM tl_wertungsportal_persons p"
		     . " INNER JOIN tl_wertungsportal_persons_memberships m ON m.pid = p.id AND m.published = '1' AND ".$laufend
		     . " WHERE p.published = '1' AND p.verstorben != '1' AND p.blocked != '1' AND m.vkz = ?"
		     . " ORDER BY p.lastnameAlias, p.firstnameAlias LIMIT ".self::MAX_ZEILEN;

		$objPersonen = \Database::getInstance()->prepare($sql)->execute(date('Ymd'), $zps);

		return self::personenAusAbfrage($objPersonen);
	}

	/**
	 * Rangliste eines Verbands (entspricht /dwz/dwzliste/persons mit
	 * VKZ-Präfix, Geschlechts- und Altersfilter, nach DWZ sortiert).
	 *
	 * @param  array $params  zps (Präfix, kann false sein = ganzer DSB),
	 *                        limit, geschlecht (MALE/FEMALE/…),
	 *                        alter_von, alter_bis
	 * @return array|null
	 */
	protected static function verbandsliste($params)
	{
		$bedingungen = array("p.published = '1'", "p.verstorben != '1'", "p.blocked != '1'", 'p.rating > 0');
		$werte = array();

		$geschlecht = trim((string) ($params['geschlecht'] ?? ''));
		if($geschlecht !== '')
		{
			$bedingungen[] = 'p.gender = ?';
			$werte[] = $geschlecht;
		}

		// Altersfilter über das Geburtsjahr. birthyear führt entweder das
		// Jahr (JJJJ) oder ein volles Datum (TT.MM.JJJJ) — in beiden Fällen
		// stehen die letzten vier Zeichen für das Jahr
		$jahr = (int) date('Y');

		if((int) ($params['alter_von'] ?? 0) > 0)
		{
			$bedingungen[] = "RIGHT(p.birthyear, 4) != '' AND RIGHT(p.birthyear, 4) <= ?";
			$werte[] = (string) ($jahr - (int) $params['alter_von']);
		}
		if((int) ($params['alter_bis'] ?? 0) > 0)
		{
			$bedingungen[] = "RIGHT(p.birthyear, 4) != '' AND RIGHT(p.birthyear, 4) >= ?";
			$werte[] = (string) ($jahr - (int) $params['alter_bis']);
		}

		$limit = (int) ($params['limit'] ?? 0);
		if($limit < 1 || $limit > self::MAX_ZEILEN) $limit = self::MAX_ZEILEN;

		// Laufende Mitgliedschaft als EXISTS statt als JOIN mit DISTINCT:
		// Der JOIN zwang MySQL in eine Zwischentabelle samt Nachsortierung.
		// VKZ-Präfix wie an der Schnittstelle (der Aufrufer hat die
		// nachlaufenden Nullen schon entfernt).
		//
		// WICHTIG: Die Werte müssen in der Reihenfolge der Platzhalter im SQL
		// stehen. Die Bedingungen zur Person (Geschlecht, Alter) kommen VOR
		// dem EXISTS-Teil, deshalb wird das Vergleichsdatum hier angehängt und
		// nicht vorangestellt
		$mitBedingungen = array('m.pid = p.id', "m.published = '1'", self::laufendeGenehmigung());
		$werte[] = date('Ymd');

		$zps = trim((string) ($params['zps'] ?? ''));

		if($zps !== '')
		{
			$mitBedingungen[] = 'm.vkz LIKE ?';
			$werte[] = addcslashes($zps, '%_\\').'%';
		}

		$bedingungen[] = 'EXISTS (SELECT 1 FROM tl_wertungsportal_persons_memberships m WHERE '.implode(' AND ', $mitBedingungen).')';

		// SORTIERUNG NUR NACH rating: Mit einem zweiten Sortierschlüssel
		// (`index` DESC) kann der Index (published, rating) die Reihenfolge
		// nicht mehr liefern, MySQL sortiert dann 95.000 Zeilen nach —
		// gemessen 972 ms statt 40 ms. Bei gleicher DWZ entscheidet die
		// Nachsortierung unten in PHP, das kostet bei wenigen hundert
		// Zeilen nichts
		$sql = "SELECT ".self::PERSONENFELDER
		     . " FROM tl_wertungsportal_persons p"
		     . " WHERE ".implode(' AND ', $bedingungen)
		     . " ORDER BY p.rating DESC LIMIT ".$limit;

		$ergebnis = self::personenAusAbfrage(\Database::getInstance()->prepare($sql)->execute(...$werte));

		if($ergebnis === null) return null;

		// Bei gleicher DWZ gewinnt der höhere Wertungsindex — nachgeordnet,
		// damit die Anzeige nicht von der Speicherreihenfolge abhängt
		usort($ergebnis['body']['data'], function($a, $b)
		{
			if((int) $a['rating'] !== (int) $b['rating']) return (int) $b['rating'] <=> (int) $a['rating'];

			return (int) $b['index'] <=> (int) $a['index'];
		});

		return $ergebnis;
	}

	/**
	 * Bedingung „laufende Spielgenehmigung" für die Mitgliedschaftstabelle.
	 * Das Datum liegt als TT.MM.JJJJ vor und wird für den Vergleich nach
	 * JJJJMMTT umgestellt; der Vergleichswert kommt als Platzhalter dazu.
	 */
	protected static function laufendeGenehmigung()
	{
		return "(m.spielgenehmigungBis = '' OR CONCAT(SUBSTRING(m.spielgenehmigungBis, 7, 4), SUBSTRING(m.spielgenehmigungBis, 4, 2), SUBSTRING(m.spielgenehmigungBis, 1, 2)) >= ?)";
	}

	/**
	 * Wandelt das Ergebnis einer Personenabfrage in eine Antwort um und lädt
	 * die Mitgliedschaften in einem Rutsch nach.
	 *
	 * @param  object $objPersonen Datenbankergebnis (Felder wie PERSONENFELDER)
	 * @return array|null
	 */
	protected static function personenAusAbfrage($objPersonen)
	{
		if(!$objPersonen->numRows) return null;

		$daten = array();
		$ids = array();
		$stand = 0;

		while($objPersonen->next())
		{
			$row = $objPersonen->row();
			$ids[] = (int) $row['id'];
			$stand = max($stand, (int) $row['tstamp']);
			$daten[(int) $row['id']] = self::personDto($row);
		}

		$mitgliedschaften = self::mitgliedschaften($ids);
		$stand = max($stand, $mitgliedschaften['stand']);

		foreach($daten as $id => $person)
		{
			$daten[$id]['memberships'] = $mitgliedschaften['daten'][$id] ?? array();
		}

		return array('body' => array('data' => array_values($daten)), 'stand' => $stand);
	}

	/**
	 * Lädt die laufenden Mitgliedschaften mehrerer Personen in einer Abfrage.
	 * ACTIVE zuerst, weil die Aufbereitung beim ersten aktiven Status abbricht.
	 *
	 * @param  array $ids  Datensatz-IDs der Personen
	 * @return array       ['daten' => pid => [Mitgliedschaften], 'stand' => ts]
	 */
	protected static function mitgliedschaften($ids)
	{
		$ids = array_values(array_unique(array_map('intval', (array) $ids)));
		if(!count($ids)) return array('daten' => array(), 'stand' => 0);

		$objMitglied = \Database::getInstance()->prepare("SELECT m.pid, m.tstamp, m.vkz, m.memberNo, m.clubName, m.licenceState, m.regionName, m.federationName FROM tl_wertungsportal_persons_memberships m WHERE m.pid IN (".implode(',', $ids).") AND m.published = '1' AND ".self::laufendeGenehmigung()." ORDER BY m.licenceState")
		                                      ->execute(date('Ymd'));

		$daten = array();
		$stand = 0;

		while($objMitglied->next())
		{
			$stand = max($stand, (int) $objMitglied->tstamp);
			$daten[(int) $objMitglied->pid][] = array
			(
				'vkz'            => $objMitglied->vkz,
				'memberNo'       => $objMitglied->memberNo,
				'clubName'       => $objMitglied->clubName,
				'licenceState'   => $objMitglied->licenceState,
				'regionName'     => $objMitglied->regionName,
				'federationName' => $objMitglied->federationName,
			);
		}

		// Die Platzhalter-Mitgliedsnummern (0000) werden NICHT hier gefiltert,
		// sondern zentral über Helper::filterMitgliedsnummern auf der ganzen
		// Antwort — genau wie bei einer Antwort der Schnittstelle
		return array('daten' => $daten, 'stand' => $stand);
	}

	// ─────────────────────────────────────────────
	//  Turniere
	// ─────────────────────────────────────────────

	/**
	 * Turniersuche. Nutzt die vorhandene lokale Suche über das Aliasfeld, die
	 * schon als Fallback der Schnittstellensuche dient.
	 *
	 * @param  array $params  suche, von, bis, zps
	 * @return array|null
	 */
	protected static function turnierliste($params)
	{
		$treffer = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::lokaleTurniersuche
		(
			(string) ($params['suche'] ?? ''),
			(string) ($params['von'] ?? ''),
			(string) ($params['bis'] ?? ''),
			(string) ($params['zps'] ?? '')
		);

		if(!is_array($treffer) || empty($treffer['body']['data'])) return null;

		return array('body' => $treffer['body'], 'stand' => self::standDerTurniere(array_column($treffer['body']['data'], 'uuid')));
	}

	/**
	 * Kopfdaten eines Turniers (entspricht /dwz/tournaments/{uuid}).
	 * Die Antwort ist flach: die Turnierfelder stehen direkt unter body.
	 *
	 * @param  array $params  turnier = UUID
	 * @return array|null
	 */
	protected static function turnierinfo($params)
	{
		$turnier = self::turnierZeile((string) ($params['turnier'] ?? ''));
		if($turnier === null) return null;

		return array('body' => self::turnierDto($turnier), 'stand' => (int) $turnier['tstamp']);
	}

	/**
	 * DWZ-Auswertung eines Turniers (entspricht
	 * /dwz/tournaments/{uuid}/evaluation): body.tournament + body.players[].
	 *
	 * @param  array $params  turnier = UUID
	 * @return array|null
	 */
	protected static function turnierauswertung($params)
	{
		$turnier = self::turnierZeile((string) ($params['turnier'] ?? ''));
		if($turnier === null) return null;

		$objSpieler = \Database::getInstance()->prepare("SELECT * FROM tl_wertungsportal_tournaments_evaluation WHERE pid = ? AND published = '1' ORDER BY playerNo LIMIT ".self::MAX_ZEILEN)
		                                     ->execute((int) $turnier['id']);

		if(!$objSpieler->numRows) return null;

		$spieler = array();
		$stand = (int) $turnier['tstamp'];

		while($objSpieler->next())
		{
			$row = $objSpieler->row();
			$stand = max($stand, (int) $row['tstamp']);
			$spieler[] = self::spielerDto($row);
		}

		return array('body' => array('tournament' => self::turnierDto($turnier), 'players' => $spieler), 'stand' => $stand);
	}

	/**
	 * Partien eines Turniers (entspricht /dwz/tournaments/{uuid}/matches):
	 * body.data[] mit je einem Unterarray whitePlayer und blackPlayer.
	 *
	 * Lokal liegen die Partien redundanzfrei — nur mit den Spieler-UUIDs des
	 * Turniers. Die Spielerdaten stehen in der Auswertungstabelle und werden
	 * hier wieder zusammengeführt, damit die Antwort der Form der
	 * Schnittstelle entspricht.
	 *
	 * @param  array $params  turnier = UUID
	 * @return array|null
	 */
	protected static function turnierergebnisse($params)
	{
		$turnier = self::turnierZeile((string) ($params['turnier'] ?? ''));
		if($turnier === null) return null;

		$partien = self::partien((int) $turnier['id']);
		if($partien === null) return null;

		return array('body' => array('data' => $partien['daten']), 'stand' => max((int) $turnier['tstamp'], $partien['stand']));
	}

	/**
	 * Spielberichtsbogen einer Person in einem Turnier (entspricht
	 * /dwz/tournaments/{uuid}/players/{spieler}/scoresheet): die Turnierfelder
	 * flach unter body, dazu body.matches[] mit NUR den Partien dieser Person.
	 *
	 * @param  array $params  turnier = UUID, id = Spieler-UUID im Turnier
	 * @return array|null
	 */
	protected static function spielberichtsbogen($params)
	{
		$turnier = self::turnierZeile((string) ($params['turnier'] ?? ''));
		if($turnier === null) return null;

		$spielerUuid = trim((string) ($params['id'] ?? ''));
		if($spielerUuid === '') return null;

		$partien = self::partien((int) $turnier['id'], $spielerUuid);
		if($partien === null) return null;

		$body = self::turnierDto($turnier);
		$body['matches'] = $partien['daten'];

		return array('body' => $body, 'stand' => max((int) $turnier['tstamp'], $partien['stand']));
	}

	/**
	 * Lädt die Partien eines Turniers und hängt die Spielerdaten aus der
	 * Auswertungstabelle an.
	 *
	 * @param  int    $pid          Datensatz-ID des Turniers
	 * @param  string $spielerUuid  nur Partien dieser Spieler-UUID (leer = alle)
	 * @return array|null           ['daten' => [...], 'stand' => ts]
	 */
	protected static function partien($pid, $spielerUuid = '')
	{
		$db = \Database::getInstance();

		$bedingungen = array('pid = ?', "published = '1'");
		$werte = array($pid);

		if($spielerUuid !== '')
		{
			$bedingungen[] = '(whitePlayerUuid = ? OR blackPlayerUuid = ?)';
			$werte[] = $spielerUuid;
			$werte[] = $spielerUuid;
		}

		// ACHTUNG: whitePlayerName/blackPlayerName sind KEINE Datenbankspalten,
		// sondern reine Anzeigefelder des DCA (input_field_callback, der den
		// Namen zur Laufzeit aus der Auswertungstabelle holt). Sie hier
		// mitzulesen war ein Fehler und führte auf dem Livesystem zu einem
		// 500er, sobald der Notbetrieb griff
		$objPartien = $db->prepare("SELECT tstamp, round, result, expected, restpartie, whitePlayerUuid, blackPlayerUuid FROM tl_wertungsportal_tournaments_matches WHERE ".implode(' AND ', $bedingungen)." ORDER BY round LIMIT ".self::MAX_ZEILEN)
		                 ->execute(...$werte);

		if(!$objPartien->numRows) return null;

		$rohPartien = array();
		$stand = 0;

		while($objPartien->next())
		{
			$rohPartien[] = $objPartien->row();
			$stand = max($stand, (int) $objPartien->tstamp);
		}

		// Spielerdaten des Turniers einmalig laden (UUID => DTO)
		$objSpieler = $db->prepare("SELECT * FROM tl_wertungsportal_tournaments_evaluation WHERE pid = ? AND published = '1'")
		                 ->execute($pid);

		$spieler = array();
		while($objSpieler->next())
		{
			$row = $objSpieler->row();
			$stand = max($stand, (int) $row['tstamp']);
			$spieler[(string) $row['playerUuid']] = self::spielerDto($row);
		}

		$daten = array();

		foreach($rohPartien as $partie)
		{
			$eintrag = array
			(
				'round'      => (int) $partie['round'],
				'result'     => $partie['result'],
				'expected'   => $partie['expected'],
				'restpartie' => $partie['restpartie'],
			);

			$eintrag['whitePlayer'] = self::spielerZuUuid($spieler, (string) $partie['whitePlayerUuid']);
			$eintrag['blackPlayer'] = self::spielerZuUuid($spieler, (string) $partie['blackPlayerUuid']);

			$daten[] = $eintrag;
		}

		return array('daten' => $daten, 'stand' => $stand);
	}

	/**
	 * Liefert das Spieler-DTO zu einer Turnier-Spieler-UUID.
	 *
	 * Die Partien halten örtlich nur die UUID; die Spielerdaten stehen in der
	 * Auswertungstabelle. Fehlt der Spieler dort (etwa weil zu dem Turnier nur
	 * die Partien und nie die Auswertung abgerufen wurden), gibt es örtlich
	 * keinen Namen — dann bleibt ein Datensatz mit UUID und leeren Namen, damit
	 * die Aufbereitung dieselbe Struktur vorfindet wie bei der Schnittstelle.
	 *
	 * @param  array  $spieler  UUID => DTO
	 * @param  string $uuid     gesuchte UUID
	 * @return array|null       DTO oder null bei fehlender UUID (spielfrei/kampflos)
	 */
	protected static function spielerZuUuid($spieler, $uuid)
	{
		if($uuid === '') return null; // spielfrei / kampflos
		if(isset($spieler[$uuid])) return $spieler[$uuid];

		return array
		(
			'playerUuid'     => $uuid,
			'nuLigaPersonId' => '',
			'lastname'       => '',
			'firstname'      => '',
			'playerNo'       => 0,
		);
	}

	/**
	 * Liest die Turnierzeile zu einer UUID.
	 *
	 * @param  string $uuid
	 * @return array|null  Datenbankzeile oder null
	 */
	protected static function turnierZeile($uuid)
	{
		$uuid = trim((string) $uuid);
		if($uuid === '') return null;

		$objTurnier = \Database::getInstance()->prepare("SELECT * FROM tl_wertungsportal_tournaments WHERE uuid = ? AND published = '1'")
		                                     ->execute($uuid);

		return ($objTurnier->numRows && $objTurnier->next()) ? $objTurnier->row() : null;
	}

	// ─────────────────────────────────────────────
	//  Umwandlung Datenbankzeile => Feldform der Schnittstelle
	// ─────────────────────────────────────────────

	/**
	 * Personendatensatz in die Feldform der Schnittstelle bringen.
	 * rating und index liefert die Schnittstelle als false, wenn keine
	 * Wertung vorliegt — die Aufbereitung verlässt sich darauf.
	 *
	 * @param  array $row  Datenbankzeile
	 * @return array
	 */
	protected static function personDto($row)
	{
		$dto = array
		(
			'uuid'           => (string) ($row['uuid'] ?? ''),
			'nuLigaPersonId' => (string) ($row['nuLigaPersonId'] ?? ''),
			'firstname'      => (string) ($row['firstname'] ?? ''),
			'lastname'       => (string) ($row['lastname'] ?? ''),
			'birthyear'      => (string) ($row['birthyear'] ?? ''),
			'gender'         => (string) ($row['gender'] ?? ''),
			'rating'         => !empty($row['rating']) ? (int) $row['rating'] : false,
			'index'          => !empty($row['index']) ? (int) $row['index'] : false,
			'memberships'    => array(),
		);

		if(!empty($row['fideId'])) $dto['fideId'] = (int) $row['fideId'];
		if((string) ($row['weekOfLastTournamentEvaluation'] ?? '') !== '') $dto['weekOfLastTournamentEvaluation'] = $row['weekOfLastTournamentEvaluation'];

		return $dto;
	}

	/**
	 * Turnierdatensatz in die Feldform der Schnittstelle bringen.
	 *
	 * @param  array $row  Datenbankzeile
	 * @return array
	 */
	protected static function turnierDto($row)
	{
		return array
		(
			'uuid'                        => (string) $row['uuid'],
			'label'                       => (string) $row['label'],
			'vkz'                         => (string) $row['vkz'],
			'startdate'                   => (string) $row['startdate'],
			'enddate'                     => (string) $row['enddate'],
			'processingState'              => (string) $row['processingState'],
			'ratingState'                 => (string) $row['ratingState'],
			'referentFirstname'           => (string) $row['referentFirstname'],
			'referentLastname'            => (string) $row['referentLastname'],
			'referentEmail'               => (string) $row['referentEmail'],
			'additionalReferentFirstname' => (string) $row['additionalReferentFirstname'],
			'additionalReferentLastname'  => (string) $row['additionalReferentLastname'],
			'additionalReferentEmail'     => (string) $row['additionalReferentEmail'],
			'location'                    => (string) $row['location'],
			'url'                         => (string) $row['url'],
			'lastCalculated'              => (string) $row['lastCalculated'],
			'rounds'                      => (int) $row['rounds'],
			'playerCount'                 => (int) $row['playerCount'],
			'matchCount'                  => (int) $row['matchCount'],
		);
	}

	/**
	 * Turnierspieler (Auswertungszeile oder Historieneintrag) in die
	 * Feldform der Schnittstelle bringen. Nicht berechnete Werte liefert die
	 * Schnittstelle nicht mit; die Aufbereitung prüft auf array_key_exists,
	 * deshalb bleiben leere Zahlenfelder hier weg statt als 0 zu erscheinen.
	 *
	 * @param  array $row  Datenbankzeile
	 * @return array
	 */
	protected static function spielerDto($row)
	{
		$dto = array
		(
			'playerUuid'     => (string) ($row['playerUuid'] ?? ''),
			'nuLigaPersonId' => (string) ($row['nuLigaPersonId'] ?? ''),
			'firstname'      => (string) ($row['firstname'] ?? ''),
			'lastname'       => (string) ($row['lastname'] ?? ''),
			'birthyear'      => (string) ($row['birthyear'] ?? ''),
			'vkz'            => (string) ($row['vkz'] ?? ''),
			'memberNo'       => (string) ($row['memberNo'] ?? ''),
			'clubName'       => (string) ($row['clubName'] ?? ''),
			'playerNo'       => (int) ($row['playerNo'] ?? 0),
			'numberOfGames'  => (int) ($row['numberOfGames'] ?? 0),
		);

		if(!empty($row['fideId'])) $dto['fideId'] = (int) $row['fideId'];
		if(!empty($row['eloPlayer'])) $dto['eloPlayer'] = (int) $row['eloPlayer'];

		// Auswertungswerte nur übernehmen, wenn sie gefüllt sind
		foreach(array('ratingOld', 'indexOld', 'ratingNew', 'indexNew', 'factorK', 'averageRatingCompetitors', 'tournamentPerformance') as $feld)
		{
			if(!empty($row[$feld])) $dto[$feld] = self::zahl($row[$feld]);
		}

		foreach(array('wins', 'winsExpected') as $feld)
		{
			if((string) ($row[$feld] ?? '') !== '') $dto[$feld] = self::zahl($row[$feld]);
		}

		return $dto;
	}

	/**
	 * Wandelt einen Datenbankwert in eine Zahl: ganze Zahlen als int,
	 * gebrochene als float (Punkte und Erwartungswerte sind gebrochen).
	 *
	 * @param  mixed $wert
	 * @return int|float
	 */
	protected static function zahl($wert)
	{
		$zahl = (float) $wert;

		return ($zahl == (int) $zahl) ? (int) $zahl : $zahl;
	}

	/**
	 * Ermittelt den jüngsten Zeitstempel zu einer Liste von Personen-DTOs
	 * (für die Antworten, die über vorhandene Suchfunktionen entstehen und
	 * den Zeitstempel selbst nicht mitliefern).
	 *
	 * @param  array $personen  DTOs mit nuLigaPersonId
	 * @return int              Zeitstempel oder 0
	 */
	protected static function standDerPersonen($personen)
	{
		$ids = array_filter(array_column((array) $personen, 'nuLigaPersonId'));
		if(!count($ids)) return 0;

		$platzhalter = implode(',', array_fill(0, count($ids), '?'));
		$objStand = \Database::getInstance()->prepare("SELECT MAX(tstamp) AS stand FROM tl_wertungsportal_persons WHERE nuLigaPersonId IN ($platzhalter)")
		                                   ->execute(array_values($ids));

		return (int) $objStand->stand;
	}

	/**
	 * Ermittelt den jüngsten Zeitstempel zu einer Liste von Turnier-UUIDs.
	 *
	 * @param  array $uuids
	 * @return int  Zeitstempel oder 0
	 */
	protected static function standDerTurniere($uuids)
	{
		$uuids = array_filter((array) $uuids);
		if(!count($uuids)) return 0;

		$platzhalter = implode(',', array_fill(0, count($uuids), '?'));
		$objStand = \Database::getInstance()->prepare("SELECT MAX(tstamp) AS stand FROM tl_wertungsportal_tournaments WHERE uuid IN ($platzhalter)")
		                                   ->execute(array_values($uuids));

		return (int) $objStand->stand;
	}
}
