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
		$this->daten['Turnierauswertunglink']  = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s.html">Turnierauswertung</a>', $this->apiTurnierinfo['body']['uuid']);
		$this->daten['Turniercode']           = $this->apiTurnierinfo['body']['uuid'];
		$this->daten['Turnierende']           = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($this->apiTurnierinfo['body']['enddate'] ?? null, 'Y-m-d', 'd.m.Y');
		$this->daten['Berechnet']             = 'unbekannt';
		$this->daten['Nachberechnet']         = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($this->apiTurnierinfo['body']['lastCalculated'] ?? null, 'Y-m-d\TH:i:s', 'd.m.Y H:i');
		$this->daten['Auswerter1']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['referentFirstname'].' '.$this->apiTurnierinfo['body']['referentLastname'].' / {{email::'.$this->apiTurnierinfo['body']['referentEmail'].'}}';
		$this->daten['Auswerter2']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['additionalReferentFirstname'].' '.$this->apiTurnierinfo['body']['additionalReferentLastname'];
		$this->daten['AnzahlSpieler']         = $this->apiTurnierinfo['body']['playerCount'];
		$this->daten['AnzahlPartien']         = $this->apiTurnierinfo['body']['matchCount'];

		// Anzahl der Runden aus den Partien ermitteln (höchste Rundennummer),
		// da der Metadaten-Wert "rounds" von der nu-Schnittstelle falsch ist.
		// Fallback auf die Metadaten, falls keine Partien geliefert werden.
		$runden = 0;
		foreach($this->apiErgebnisse['body']['data'] as $partie)
		{
			if(isset($partie['round']) && (int)$partie['round'] > $runden) $runden = (int)$partie['round'];
		}
		if(!$runden) $runden = (int)$this->apiTurnierinfo['body']['rounds'];

		$this->daten['AnzahlRunden']          = $runden;
		$this->daten['Spieler']               = array_fill(1, $this->apiTurnierinfo['body']['playerCount'], []); // Wird nachfolgend befüllt

		// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
		$nuids = array();
		foreach($this->apiErgebnisse['body']['data'] as $partie)
		{
			if(!empty($partie['whitePlayer']['nuLigaPersonId'])) $nuids[] = $partie['whitePlayer']['nuLigaPersonId'];
			if(!empty($partie['blackPlayer']['nuLigaPersonId'])) $nuids[] = $partie['blackPlayer']['nuLigaPersonId'];
		}
		$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist($nuids);

		// Spielerstammdaten und Ergebnisse einlesen
		foreach($this->apiErgebnisse['body']['data'] as $partie)
		{
			// Fehlende Felder der Spieler-DTOs mit Standardwerten auffüllen
			$partie['whitePlayer'] = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::PlayerDefaults($partie['whitePlayer'] ?? null);
			$partie['blackPlayer'] = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::PlayerDefaults($partie['blackPlayer'] ?? null);

			// Blacklist: Die Zeile bleibt wegen der Kreuztabellen-Querbezüge erhalten,
			// aber ohne Personenbezug (kein Name, kein Scoresheet-Link)
			$weissGesperrt = !empty($partie['whitePlayer']['nuLigaPersonId']) && isset($blacklist[$partie['whitePlayer']['nuLigaPersonId']]);
			$schwarzGesperrt = !empty($partie['blackPlayer']['nuLigaPersonId']) && isset($blacklist[$partie['blackPlayer']['nuLigaPersonId']]);

			// Spielerstammdaten von Weiß prüfen
			if(!$this->daten['Spieler'][$partie['whitePlayer']['playerNo']])
			{
				// Spieler noch leer, deshalb Stammdaten eintragen
				$this->daten['Spieler'][$partie['whitePlayer']['playerNo']] = array
				(
					'playerUuid'     => $partie['whitePlayer']['playerUuid'],
					'nuLigaPersonId' => $partie['whitePlayer']['nuLigaPersonId'],
					'Vorname'        => $weissGesperrt ? '' : $partie['whitePlayer']['firstname'],
					'Nachname'       => $weissGesperrt ? '' : $partie['whitePlayer']['lastname'],
					'Spielername'    => $weissGesperrt ? '<i>gesperrt</i>' : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($partie['whitePlayer']),
					'Scoresheet'     => $weissGesperrt ? '' : sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['whitePlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'].' aufrufen'),
					'Nummer'         => $partie['whitePlayer']['playerNo'],
					'DWZ alt'        => $partie['whitePlayer']['ratingOld'],
					'Punkte'         => $partie['whitePlayer']['wins'],
					'Partien'        => $partie['whitePlayer']['numberOfGames'],
					'Ergebnis'       => sprintf('%s/%s', \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($partie['whitePlayer']['wins']), $partie['whitePlayer']['numberOfGames']),
					'Ergebnisse'     => array_fill(1, $runden, []),
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
					'Vorname'        => $schwarzGesperrt ? '' : $partie['blackPlayer']['firstname'],
					'Nachname'       => $schwarzGesperrt ? '' : $partie['blackPlayer']['lastname'],
					'Spielername'    => $schwarzGesperrt ? '<i>gesperrt</i>' : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($partie['blackPlayer']),
					'Scoresheet'     => $schwarzGesperrt ? '' : sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['uuid'], $partie['blackPlayer']['playerUuid'], 'Spielberichtsbogen von '.$partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'].' aufrufen'),
					'Nummer'         => $partie['blackPlayer']['playerNo'],
					'DWZ alt'        => $partie['blackPlayer']['ratingOld'],
					'Punkte'         => $partie['blackPlayer']['wins'],
					'Partien'        => $partie['blackPlayer']['numberOfGames'],
					'Ergebnis'       => sprintf('%s/%s', \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($partie['blackPlayer']['wins']), $partie['blackPlayer']['numberOfGames']),
					'Ergebnisse'     => array_fill(1, $runden, []),
				);
			}

			// Ergebnisse eintragen
			// Array ['Ergebnisse']['Runde'][] = mehrere Einträge je Runde möglich,
			// deshalb anhängen statt zuweisen (sonst überschreibt z. B. eine
			// Nachtragspartie das vorhandene Rundenergebnis)
			// Ergebnis von Weiß eintragen
			$this->daten['Spieler'][$partie['whitePlayer']['playerNo']]['Ergebnisse'][$partie['round']][] = array
			(
				'Resultat'   => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), 0, 1),
				'Gegner'     => $partie['blackPlayer']['playerNo'],
				'Gegnername' => $schwarzGesperrt ? '' : $partie['blackPlayer']['firstname'].' '.$partie['blackPlayer']['lastname'],
				'Farbklasse' => 'white',
				'Farbe'      => 'w',
			);
			// Ergebnis von Schwarz eintragen
			$this->daten['Spieler'][$partie['blackPlayer']['playerNo']]['Ergebnisse'][$partie['round']][] = array
			(
				'Resultat'   => mb_substr(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Resultat($partie['result']), -1),
				'Gegner'     => $partie['whitePlayer']['playerNo'],
				'Gegnername' => $weissGesperrt ? '' : $partie['whitePlayer']['firstname'].' '.$partie['whitePlayer']['lastname'],
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
					'Ergebnisse'     => array_fill(1, $runden, []),
				);
			}
		}

		$log = print_r($this->daten['Spieler'], true)."\n";
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message($log, 'wertungsportal.log');

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
