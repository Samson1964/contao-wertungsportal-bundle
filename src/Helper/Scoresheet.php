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
		$this->idTurnier = \Input::get('code'); // Turniercode
		$this->idSpieler = \Input::get('id'); // Spieler-ID
		
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
		$this->daten['Turnierauswertunglink'] = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s.html">Turnierauswertung</a>', $this->apiTurnierinfo['body']['uuid']);
		$this->daten['Turnierergebnislink']   = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/Ergebnisse.html">Turnierergebnisse</a>', $this->apiTurnierinfo['body']['uuid']);
		$this->daten['Turniercode']           = $this->apiTurnierinfo['body']['uuid'];
		$this->daten['Turnierende']           = \DateTime::createFromFormat('Y-m-d', $this->apiTurnierinfo['body']['enddate'])->format('d.m.Y');
		$this->daten['Berechnet']             = 'unbekannt';
		$this->daten['Nachberechnet']         = \DateTime::createFromFormat('Y-m-d\TH:i:s', $this->apiTurnierinfo['body']['lastCalculated'])->format('d.m.Y H:i');
		$this->daten['Auswerter1']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['referentFirstname'].' '.$this->apiTurnierinfo['body']['referentLastname'].' / {{email::'.$this->apiTurnierinfo['body']['referentEmail'].'}}';
		$this->daten['Auswerter2']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['additionalReferentFirstname'].' '.$this->apiTurnierinfo['body']['additionalReferentLastname'];
		$this->daten['AnzahlSpieler']         = $this->apiTurnierinfo['body']['playerCount'];
		$this->daten['AnzahlPartien']         = $this->apiTurnierinfo['body']['matchCount'];
		$this->daten['AnzahlRunden']          = $this->apiTurnierinfo['body']['rounds'];
		$this->daten['Spieler']               = array(); // Wird nachfolgend befüllt
		$this->daten['Ergebnisse']            = array(); // Wird nachfolgend befüllt

		// Ergebnisse laden

		foreach($this->apiSpielberichtsbogen['body']['matches'] as $partie)
		{
			// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
			if(!array_key_exists('nuLigaPersonId', $partie['whitePlayer'])) $partie['whitePlayer']['nuLigaPersonId'] = false;
			if(!array_key_exists('nuLigaPersonId', $partie['blackPlayer'])) $partie['blackPlayer']['nuLigaPersonId'] = false;
			if(!array_key_exists('tournamentPerformance', $partie['whitePlayer'])) $partie['whitePlayer']['tournamentPerformance'] = false;
			if(!array_key_exists('tournamentPerformance', $partie['blackPlayer'])) $partie['blackPlayer']['tournamentPerformance'] = false;
			if(!array_key_exists('ratingOld', $partie['whitePlayer'])) $partie['whitePlayer']['ratingOld'] = false;
			if(!array_key_exists('ratingOld', $partie['blackPlayer'])) $partie['blackPlayer']['ratingOld'] = false;
			if(!array_key_exists('ratingNew', $partie['whitePlayer'])) $partie['whitePlayer']['ratingNew'] = false;
			if(!array_key_exists('ratingNew', $partie['blackPlayer'])) $partie['blackPlayer']['ratingNew'] = false;

			if($this->idSpieler == $partie['whitePlayer']['playerUuid'])
			{
				// Der Scoresheet-Spieler hat Weiß, sein Gegner Schwarz
				// Zuerst prüfen, ob die Daten des auszuwertenden Spielers da sind
				if(!$this->daten['Spieler'])
				{
					$this->daten['Spieler'] = array
					(
						'Gegner'         => $partie['whitePlayer']['averageRatingCompetitors'],
						'Punkte'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesamtpunkte($partie['whitePlayer']['wins']),
						'Partien'        => $partie['whitePlayer']['numberOfGames'],
						'Erwartungswert' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($partie['whitePlayer']['winsExpected']),
						'Leistung'       => $partie['whitePlayer']['tournamentPerformance'],
						'Name'           => $partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'],
						'DWZ alt'        => $partie['whitePlayer']['ratingOld'],
						'DWZ neu'        => $partie['whitePlayer']['ratingNew']
					);
				}
				// Ergebnis eintragen
				$this->daten['Ergebnisse'][] = array
				(
					'Runde'             => $partie['round'],
					'Farbe'             => 'white',
					'Ergebnis'          => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), 0, 1),
					'Gegner_Name'       => $partie['blackPlayer']['nuLigaPersonId'] ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite().'/%s.html" title="%s">%s</a>', $partie['blackPlayer']['nuLigaPersonId'], 'Karteikarte von '.$partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'].' aufrufen', $partie['blackPlayer']['lastname'].', '.$partie['blackPlayer']['firstname']) : $partie['blackPlayer']['lastname'].', '.$partie['blackPlayer']['firstname'],
					'Gegner_UUID'       => $partie['blackPlayer']['playerUuid'],
					'Gegner_nuID'       => $partie['blackPlayer']['nuLigaPersonId'],
					'Gegner_DWZ'        => $partie['blackPlayer']['ratingOld'],
					'Gegner_Scoresheet' => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['blackPlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'].' aufrufen'),
					'We'                => '0' // Wird später berechnet
				);
			}
			else
			{
				// Der Scoresheet-Spieler hat Schwarz, sein Gegner Weiß
				// Zuerst prüfen, ob die Daten des auszuwertenden Spielers da sind
				if(!$this->daten['Spieler'])
				{
					$this->daten['Spieler'] = array
					(
						'Gegner'         => $partie['blackPlayer']['averageRatingCompetitors'],
						'Punkte'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesamtpunkte($partie['blackPlayer']['wins']),
						'Partien'        => $partie['blackPlayer']['numberOfGames'],
						'Erwartungswert' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($partie['blackPlayer']['winsExpected']),
						'Leistung'       => $partie['blackPlayer']['tournamentPerformance'],
						'Name'           => $partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'],
						'DWZ alt'        => $partie['blackPlayer']['ratingOld'],
						'DWZ neu'        => $partie['blackPlayer']['ratingNew']
					);
				}
				// Ergebnis eintragen
				$this->daten['Ergebnisse'][] = array
				(
					'Runde'             => $partie['round'],
					'Farbe'             => 'black',
					'Ergebnis'          => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), -1),
					'Gegner_Name'       => $partie['whitePlayer']['nuLigaPersonId'] ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite().'/%s.html" title="%s">%s</a>', $partie['whitePlayer']['nuLigaPersonId'], 'Karteikarte von '.$partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'].' aufrufen', $partie['whitePlayer']['lastname'].', '.$partie['whitePlayer']['firstname']) : $partie['whitePlayer']['lastname'].', '.$partie['whitePlayer']['firstname'],
					'Gegner_UUID'       => $partie['whitePlayer']['playerUuid'],
					'Gegner_nuID'       => $partie['whitePlayer']['nuLigaPersonId'],
					'Gegner_DWZ'        => $partie['whitePlayer']['ratingOld'],
					'Gegner_Scoresheet' => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['whitePlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'].' aufrufen'),
					'We'                => '0' // Wird später berechnet
				);
			}
		}
		
		// Gewinnerwartung bei den Einzelergebnissen eintragen
		for($x = 0; $x < count($this->daten['Ergebnisse']); $x++)
		{
			$this->daten['Ergebnisse'][$x]['We'] = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gewinnerwartung($this->daten['Spieler']['DWZ alt'], $this->daten['Ergebnisse'][$x]['Gegner_DWZ']);
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
