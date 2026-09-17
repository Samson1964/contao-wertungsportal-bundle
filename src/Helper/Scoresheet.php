<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Scoresheet-Klasse (Spielberichtsbogen)
//  Gibt alle Daten für den Scoresheet formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Scoresheet
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiTurnierinfo; // Enthält das Array von der API mit den Turnierinfos
	public array $apiSpielberichtsbogen; // Enthält das Array von der API mit dem Spielberichtsbogen
	public string $idSpieler; // Spieler-ID aus GET-Parameter
	public string $idTurnier; // Turniercode aus GET-Parameter
	private array $daten; // Enthält die Scoresheet-Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Turnierinfo, $Spielberichtsbogen)
	{
		$this->apiTurnierinfo = $Turnierinfo;
		$this->apiSpielberichtsbogen = $Spielberichtsbogen;
		$this->idTurnier = \Contao\Input::get('code'); // Turniercode
		$this->idSpieler = \Contao\Input::get('id'); // Spieler-ID
		
		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die formatierten Daten für die Turnierinfo
	//  und den Spielberichtsbogen.
	// ─────────────────────────────────────────────
	public function compile() 
	{
		/*********************************************************
		 * Turnierheader
		*/

		// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
		if(!array_key_exists('referentLastname', $this->apiTurnierinfo['body'])) $this->apiTurnierinfo['body']['referentLastname'] = false;
		if(!array_key_exists('referentFirstname', $this->apiTurnierinfo['body'])) $this->apiTurnierinfo['body']['referentFirstname'] = false;
		if(!array_key_exists('referentEmail', $this->apiTurnierinfo['body'])) $this->apiTurnierinfo['body']['referentEmail'] = false;
		if(!array_key_exists('additionalReferentLastname', $this->apiTurnierinfo['body'])) $this->apiTurnierinfo['body']['additionalReferentLastname'] = false;
		if(!array_key_exists('additionalReferentFirstname', $this->apiTurnierinfo['body'])) $this->apiTurnierinfo['body']['additionalReferentFirstname'] = false;

		$this->daten['Turniername']           = $this->apiTurnierinfo['body']['label'];
		$this->daten['Turnierauswertunglink'] = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'">Turnierauswertung</a>', $this->apiTurnierinfo['body']['uuid']);
		$this->daten['Turnierergebnislink']   = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/Ergebnisse'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'">Turnierergebnisse</a>', $this->apiTurnierinfo['body']['uuid']);
		$this->daten['Turniercode']           = $this->apiTurnierinfo['body']['uuid'];
		$this->daten['Turnierende']           = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($this->apiTurnierinfo['body']['enddate'] ?? null, 'Y-m-d', 'd.m.Y');
		$this->daten['Berechnet']             = 'unbekannt';
		$this->daten['Nachberechnet']         = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($this->apiTurnierinfo['body']['lastCalculated'] ?? null, 'Y-m-d\TH:i:s', 'd.m.Y H:i');
		$this->daten['Auswerter1']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['referentFirstname'].' '.$this->apiTurnierinfo['body']['referentLastname'].' / {{email::'.$this->apiTurnierinfo['body']['referentEmail'].'}}';
		$this->daten['Auswerter2']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['additionalReferentFirstname'].' '.$this->apiTurnierinfo['body']['additionalReferentLastname'];
		$this->daten['AnzahlSpieler']         = $this->apiTurnierinfo['body']['playerCount'];
		$this->daten['AnzahlPartien']         = $this->apiTurnierinfo['body']['matchCount'];
		$this->daten['AnzahlRunden']          = $this->apiTurnierinfo['body']['rounds'];
		$this->daten['Spieler']               = array(); // Wird nachfolgend befüllt
		$this->daten['Ergebnisse']            = array(); // Wird nachfolgend befüllt
		$this->daten['Gesperrt']              = false; // Wird true, wenn der Scoresheet-Spieler auf der Blacklist steht

		// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
		$nuids = array();
		foreach($this->apiSpielberichtsbogen['body']['matches'] as $partie)
		{
			if(!empty($partie['whitePlayer']['nuLigaPersonId'])) $nuids[] = $partie['whitePlayer']['nuLigaPersonId'];
			if(!empty($partie['blackPlayer']['nuLigaPersonId'])) $nuids[] = $partie['blackPlayer']['nuLigaPersonId'];
		}
		$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist($nuids);

		// Ergebnisse laden

		foreach($this->apiSpielberichtsbogen['body']['matches'] as $partie)
		{
			// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
			// Fehlende Felder der Spieler-DTOs mit Standardwerten auffüllen
			$partie['whitePlayer'] = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::PlayerDefaults($partie['whitePlayer'] ?? null);
			$partie['blackPlayer'] = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::PlayerDefaults($partie['blackPlayer'] ?? null);

			// Blacklist: Gegner ohne Personenbezug ausgeben, gesperrten
			// Scoresheet-Spieler über das Gesperrt-Flag komplett abfangen
			$weissGesperrt = !empty($partie['whitePlayer']['nuLigaPersonId']) && isset($blacklist[$partie['whitePlayer']['nuLigaPersonId']]);
			$schwarzGesperrt = !empty($partie['blackPlayer']['nuLigaPersonId']) && isset($blacklist[$partie['blackPlayer']['nuLigaPersonId']]);

			// Wertungen beider Seiten aufbereiten: Bei Nichtmitgliedern steht
			// die Eingangswertung nur im Anzeigetext, eine neue DWZ wird für
			// sie nicht ausgewiesen (Wertungsordnung 3.4.3).
			// Die Gewinnerwartung je Partie kommt aus dem `expected` der
			// Schnittstelle (dort aus Sicht von Weiß): Nur damit ergeben die
			// Zeilen die Summe `winsExpected` darunter, und nur so bekommt auch
			// eine Partie gegen ein Nichtmitglied ohne Eingangswertung einen
			// Wert. Bis 1.44.1 wurde je Zeile aus den alten DWZ geschätzt —
			// Gegner ohne DWZ blieben leer, die Summe ging nicht auf
			$weissWertung = \Schachbulle\ContaoWertungsportalBundle\Helper\Spielerwertung::aufbereiten($partie['whitePlayer']);
			$schwarzWertung = \Schachbulle\ContaoWertungsportalBundle\Helper\Spielerwertung::aufbereiten($partie['blackPlayer']);

			if($this->idSpieler == $partie['whitePlayer']['playerUuid'])
			{
				// Der Scoresheet-Spieler hat Weiß, sein Gegner Schwarz
				if($weissGesperrt) $this->daten['Gesperrt'] = true;

				// Zuerst prüfen, ob die Daten des auszuwertenden Spielers da sind
				if(!$this->daten['Spieler'])
				{
					$this->daten['Spieler'] = array
					(
						'Gegner'         => $partie['whitePlayer']['averageRatingCompetitors'],
						'Punkte'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesamtpunkte($partie['whitePlayer']['wins']),
						'Partien'        => $partie['whitePlayer']['numberOfGames'],
						'Erwartungswert' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($weissWertung['winsExpected']),
						'Leistung'       => $partie['whitePlayer']['tournamentPerformance'],
						'Name'           => $partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'],
						'DWZ alt'        => $weissWertung['dwzAltKurz'],
						'DWZ neu'        => $weissWertung['ratingNew'] ?: '',
						'Nichtmitglied'  => $weissWertung['nichtmitglied'],
					);
				}
				// Gewinnerwartung dieser Partie aus Sicht von Weiß
				$erwartung = \Schachbulle\ContaoWertungsportalBundle\Helper\Spielerwertung::partieerwartung($partie, true, $weissWertung['ratingOld'], $schwarzWertung['ratingOld']);

				// Ergebnis eintragen
				$this->daten['Ergebnisse'][] = array
				(
					'Runde'             => $partie['round'],
					'Farbe'             => 'white',
					'Ergebnis'          => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), 0, 1),
					'Gegner_Name'       => $schwarzGesperrt ? '<i>gesperrt</i>' : ($partie['blackPlayer']['nuLigaPersonId'] ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl().'/%s'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'" title="%s">%s</a>', $partie['blackPlayer']['nuLigaPersonId'], 'Karteikarte von '.$partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'].' aufrufen', $partie['blackPlayer']['lastname'].', '.$partie['blackPlayer']['firstname']) : $partie['blackPlayer']['lastname'].', '.$partie['blackPlayer']['firstname']),
					'Gegner_UUID'       => $partie['blackPlayer']['playerUuid'],
					'Gegner_nuID'       => $partie['blackPlayer']['nuLigaPersonId'],
					'Gegner_DWZ'        => $schwarzWertung['dwzAltKurz'],
					'Gegner_Scoresheet' => $schwarzGesperrt ? '' : sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/%s'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['blackPlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'].' aufrufen'),
					'We'                => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($erwartung['wert'] === null ? false : $erwartung['wert']),
					'We_geschaetzt'     => $erwartung['geschaetzt'],
				);
			}
			else
			{
				// Der Scoresheet-Spieler hat Schwarz, sein Gegner Weiß
				if($schwarzGesperrt) $this->daten['Gesperrt'] = true;

				// Zuerst prüfen, ob die Daten des auszuwertenden Spielers da sind
				if(!$this->daten['Spieler'])
				{
					$this->daten['Spieler'] = array
					(
						'Gegner'         => $partie['blackPlayer']['averageRatingCompetitors'],
						'Punkte'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesamtpunkte($partie['blackPlayer']['wins']),
						'Partien'        => $partie['blackPlayer']['numberOfGames'],
						'Erwartungswert' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($schwarzWertung['winsExpected']),
						'Leistung'       => $partie['blackPlayer']['tournamentPerformance'],
						'Name'           => $partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'],
						'DWZ alt'        => $schwarzWertung['dwzAltKurz'],
						'DWZ neu'        => $schwarzWertung['ratingNew'] ?: '',
						'Nichtmitglied'  => $schwarzWertung['nichtmitglied'],
					);
				}
				// Gewinnerwartung dieser Partie aus Sicht von Schwarz
				$erwartung = \Schachbulle\ContaoWertungsportalBundle\Helper\Spielerwertung::partieerwartung($partie, false, $schwarzWertung['ratingOld'], $weissWertung['ratingOld']);

				// Ergebnis eintragen
				$this->daten['Ergebnisse'][] = array
				(
					'Runde'             => $partie['round'],
					'Farbe'             => 'black',
					'Ergebnis'          => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), -1),
					'Gegner_Name'       => $weissGesperrt ? '<i>gesperrt</i>' : ($partie['whitePlayer']['nuLigaPersonId'] ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl().'/%s'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'" title="%s">%s</a>', $partie['whitePlayer']['nuLigaPersonId'], 'Karteikarte von '.$partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'].' aufrufen', $partie['whitePlayer']['lastname'].', '.$partie['whitePlayer']['firstname']) : $partie['whitePlayer']['lastname'].', '.$partie['whitePlayer']['firstname']),
					'Gegner_UUID'       => $partie['whitePlayer']['playerUuid'],
					'Gegner_nuID'       => $partie['whitePlayer']['nuLigaPersonId'],
					'Gegner_DWZ'        => $weissWertung['dwzAltKurz'],
					'Gegner_Scoresheet' => $weissGesperrt ? '' : sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/%s'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['whitePlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'].' aufrufen'),
					'We'                => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($erwartung['wert'] === null ? false : $erwartung['wert']),
					'We_geschaetzt'     => $erwartung['geschaetzt'],
				);
			}
		}
			}

	// ─────────────────────────────────────────────
	//  Magische Methode __set
	//  Variable ($name) einen Wert ($value) zuweisen
	//  Aufruf: $Scoresheet->Funktion = 'Max';
	// ─────────────────────────────────────────────
	public function __set($name, $value) 
	{
		$this->daten[$name] = $value;
	}
	
	// ─────────────────────────────────────────────
	//  Magische Methode __get
	//  Wert der Variable ($name) abrufen
	//  Aufruf: echo $Scoresheet->Funktion;
	// ─────────────────────────────────────────────
	public function __get($name) 
	{
		return array_key_exists($name, $this->daten) ? $this->daten[$name] : null;
	}
}
