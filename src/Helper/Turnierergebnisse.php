<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Turnierergebnisse-Klasse
//  Gibt alle Daten für die Turnierergebnisse formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Turnierergebnisse
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiTurnierinfo; // Enthält das Array von der API mit den Turnierinfos
	public array $apiErgebnisse; // Enthält das Array von der API mit den Turnierergebnissen
	public string $idTurnier; // Turniercode aus GET-Parameter
	private array $daten; // Enthält die Scoresheet-Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Turnierinfo, $Ergebnisse)
	{
		$this->apiTurnierinfo = $Turnierinfo;
		$this->apiErgebnisse = $Ergebnisse;
		$this->idTurnier = \Input::get('code'); // Turniercode
		
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
		$this->daten['Turnierauswertunglink']  = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s.html">Turnierauswertung</a>', $this->apiTurnierinfo['body']['uuid']);
		$this->daten['Turniercode']           = $this->apiTurnierinfo['body']['uuid'];
		$this->daten['Turnierende']           = \DateTime::createFromFormat('Y-m-d', $this->apiTurnierinfo['body']['enddate'])->format('d.m.Y');
		$this->daten['Berechnet']             = 'unbekannt';
		$this->daten['Nachberechnet']         = \DateTime::createFromFormat('Y-m-d\TH:i:s', $this->apiTurnierinfo['body']['lastCalculated'])->format('d.m.Y H:i');
		$this->daten['Auswerter1']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['referentFirstname'].' '.$this->apiTurnierinfo['body']['referentLastname'].' / {{email::'.$this->apiTurnierinfo['body']['referentEmail'].'}}';
		$this->daten['Auswerter2']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['additionalReferentFirstname'].' '.$this->apiTurnierinfo['body']['additionalReferentLastname'];
		$this->daten['AnzahlSpieler']         = $this->apiTurnierinfo['body']['playerCount'];
		$this->daten['AnzahlPartien']         = $this->apiTurnierinfo['body']['matchCount'];
		$this->daten['AnzahlRunden']          = $this->apiTurnierinfo['body']['rounds'];
		$this->daten['Spieler']               = array_fill(1, $this->apiTurnierinfo['body']['playerCount'], []); // Wird nachfolgend befüllt

		// Spielerstammdaten und Ergebnisse einlesen
		foreach($this->apiErgebnisse['body']['data'] as $partie)
		{
			// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
			if(!array_key_exists('ratingOld', $partie['whitePlayer'])) $partie['whitePlayer']['ratingOld'] = false;
			if(!array_key_exists('ratingOld', $partie['blackPlayer'])) $partie['blackPlayer']['ratingOld'] = false;
			if(!array_key_exists('nuLigaPersonId', $partie['whitePlayer'])) $partie['whitePlayer']['nuLigaPersonId'] = false;
			if(!array_key_exists('nuLigaPersonId', $partie['blackPlayer'])) $partie['blackPlayer']['nuLigaPersonId'] = false;

			// Spielerstammdaten von Weiß prüfen
			if(!$this->daten['Spieler'][$partie['whitePlayer']['playerNo']])
			{
				// Spieler noch leer, deshalb Stammdaten eintragen
				$this->daten['Spieler'][$partie['whitePlayer']['playerNo']] = array
				(
					'playerUuid'     => $partie['whitePlayer']['playerUuid'],
					'nuLigaPersonId' => $partie['whitePlayer']['nuLigaPersonId'],
					'Vorname'        => $partie['whitePlayer']['firstname'],
					'Nachname'       => $partie['whitePlayer']['lastname'],
					'Spielername'    => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($partie['whitePlayer']),
					'Scoresheet'     => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['whitePlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'].' aufrufen'),
					'Nummer'         => $partie['whitePlayer']['playerNo'],
					'DWZ alt'        => $partie['whitePlayer']['ratingOld'],
					'Punkte'         => $partie['whitePlayer']['wins'],
					'Partien'        => $partie['whitePlayer']['numberOfGames'],
					'Ergebnis'       => sprintf('%s/%s', \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($partie['whitePlayer']['wins']), $partie['whitePlayer']['numberOfGames']),
					'Ergebnisse'     => array_fill(1, $this->apiTurnierinfo['body']['rounds'], []),
				);
			}

			// Spielerstammdaten von Schwarz prüfen
			if(!$this->daten['Spieler'][$partie['blackPlayer']['playerNo']])
			{
				// Spieler noch leer, deshalb Stammdaten eintragen
				$this->daten['Spieler'][$partie['blackPlayer']['playerNo']] = array
				(
					'playerUuid'     => $partie['blackPlayer']['playerUuid'],
					'nuLigaPersonId' => $partie['blackPlayer']['nuLigaPersonId'],
					'Vorname'        => $partie['blackPlayer']['firstname'],
					'Nachname'       => $partie['blackPlayer']['lastname'],
					'Spielername'    => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($partie['blackPlayer']),
					'Scoresheet'     => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['blackPlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'].' aufrufen'),
					'Nummer'         => $partie['blackPlayer']['playerNo'],
					'DWZ alt'        => $partie['blackPlayer']['ratingOld'],
					'Punkte'         => $partie['blackPlayer']['wins'],
					'Partien'        => $partie['blackPlayer']['numberOfGames'],
					'Ergebnis'       => sprintf('%s/%s', \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($partie['blackPlayer']['wins']), $partie['blackPlayer']['numberOfGames']),
					'Ergebnisse'     => array_fill(1, $this->apiTurnierinfo['body']['rounds'], []),
				);
			}
			
			// Ergebnisse eintragen
			// Array ['Ergebnisse']['Runde'][] = mehrere Einträge je Runde möglich
			// Ergebnis von Weiß eintragen
			$this->daten['Spieler'][$partie['whitePlayer']['playerNo']]['Ergebnisse'][$partie['round']] = array
			(
				'Resultat'   => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), 0, 1),
				'Gegner'     => $partie['blackPlayer']['playerNo'],
				'Gegnername' => $partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'],
				'Farbklasse' => 'white',
				'Farbe'      => 'w',
			);
			// Ergebnis von Schwarz eintragen
			$this->daten['Spieler'][$partie['blackPlayer']['playerNo']]['Ergebnisse'][$partie['round']] = array
			(
				'Resultat'   => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), -1),
				'Gegner'     => $partie['whitePlayer']['playerNo'],
				'Gegnername' => $partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'],
				'Farbklasse' => 'black',
				'Farbe'      => 's',
			);
			
		}
		
		// In einigen Turnieren stimmt die Spieleranzahl nicht mit der Anzahl der Spieler überein,
		// d.h. es sind einige Startnummern nicht belegt worden.
		// Beispiel: https://development.schachbund.org/turnier/35bd4642-6484-11f1-99a7-0a0027000005/Ergebnisse.html
		// Deshalb das Spieler-Array nochmal prüfen und fehlende Daten mit Leereintrag ergänzen
		for($x = 1; $x <= $this->apiTurnierinfo['body']['playerCount']; $x++)
		{
			if(!$this->daten['Spieler'][$x])
			{
				$this->daten['Spieler'][$x] = array
				(
					'playerUuid'     => '',
					'nuLigaPersonId' => '',
					'Vorname'        => '',
					'Nachname'       => '',
					'Spielername'    => '',
					'Scoresheet'     => '',
					'Nummer'         => $x,
					'DWZ alt'        => '',
					'Punkte'         => '',
					'Partien'        => '',
					'Ergebnis'       => '',
					'Ergebnisse'     => array_fill(1, $this->apiTurnierinfo['body']['rounds'], []),
				);
			}
		}

		$log = print_r($this->daten['Spieler'], true)."\n";
		log_message($log, 'wertungsportal.log');

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
