<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Turnierauswertung-Klasse
//  Gibt alle Daten für die Turnierauswertung formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Turnierauswertung
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiTurnierinfo; // Enthält das Array von der API mit den Turnierinfos
	public string $idTurnier; // Turniercode aus GET-Parameter
	private array $daten; // Enthält die Scoresheet-Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Turnierinfo)
	{
		$this->apiTurnierinfo = $Turnierinfo;
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
		if(!array_key_exists('referentLastname', $this->apiTurnierinfo['body']['tournament'])) $this->apiTurnierinfo['body']['tournament']['referentLastname'] = false;
		if(!array_key_exists('referentFirstname', $this->apiTurnierinfo['body']['tournament'])) $this->apiTurnierinfo['body']['tournament']['referentFirstname'] = false;
		if(!array_key_exists('referentEmail', $this->apiTurnierinfo['body']['tournament'])) $this->apiTurnierinfo['body']['tournament']['referentEmail'] = false;
		if(!array_key_exists('additionalReferentLastname', $this->apiTurnierinfo['body']['tournament'])) $this->apiTurnierinfo['body']['tournament']['additionalReferentLastname'] = false;
		if(!array_key_exists('additionalReferentFirstname', $this->apiTurnierinfo['body']['tournament'])) $this->apiTurnierinfo['body']['tournament']['additionalReferentFirstname'] = false;

		$this->daten['Turniername']           = $this->apiTurnierinfo['body']['tournament']['label'];
		$this->daten['Turnierergebnislink']   = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/Ergebnisse.html">Turnierergebnisse</a>', $this->apiTurnierinfo['body']['tournament']['uuid']);
		$this->daten['Turniercode']           = $this->apiTurnierinfo['body']['tournament']['uuid'];
		$this->daten['Turnierende']           = \DateTime::createFromFormat('Y-m-d', $this->apiTurnierinfo['body']['tournament']['enddate'])->format('d.m.Y');
		$this->daten['Berechnet']             = 'unbekannt';
		$this->daten['Nachberechnet']         = \DateTime::createFromFormat('Y-m-d\TH:i:s', $this->apiTurnierinfo['body']['tournament']['lastCalculated'])->format('d.m.Y H:i');
		$this->daten['Auswerter1']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['tournament']['referentFirstname'].' '.$this->apiTurnierinfo['body']['tournament']['referentLastname'].' / {{email::'.$this->apiTurnierinfo['body']['tournament']['referentEmail'].'}}';
		$this->daten['Auswerter2']            = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : $this->apiTurnierinfo['body']['tournament']['additionalReferentFirstname'].' '.$this->apiTurnierinfo['body']['tournament']['additionalReferentLastname'];
		$this->daten['AnzahlSpieler']         = $this->apiTurnierinfo['body']['tournament']['playerCount'];
		$this->daten['AnzahlPartien']         = $this->apiTurnierinfo['body']['tournament']['matchCount'];
		$this->daten['AnzahlRunden']          = $this->apiTurnierinfo['body']['tournament']['rounds'];
		$this->daten['Spieler']               = array(); // Wird nachfolgend befüllt

		/*********************************************************
		 * Auswertung
		*/

		if(isset($this->apiTurnierinfo['body']['players']))
		{
			foreach($this->apiTurnierinfo['body']['players'] as $t)
			{
				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('nuLigaPersonId', $t)) $t['nuLigaPersonId'] = false;
				if(!array_key_exists('ratingNew', $t)) $t['ratingNew'] = false;
				if(!array_key_exists('ratingOld', $t)) $t['ratingOld'] = false;
				if(!array_key_exists('indexNew', $t)) $t['indexNew'] = false;
				if(!array_key_exists('indexOld', $t)) $t['indexOld'] = false;
				if(!array_key_exists('tournamentPerformance', $t)) $t['tournamentPerformance'] = false;
				if(!array_key_exists('fideId', $t)) $t['fideId'] = false;
				if(!array_key_exists('memberNo', $t)) $t['memberNo'] = false;
				if(!array_key_exists('vkz', $t)) $t['vkz'] = false;
				if(!array_key_exists('clubName', $t)) $t['clubName'] = false;

				// Ratingdifferenz errechnen
				$ratingdiff = $t['ratingNew'] - $t['ratingOld'];
				if($ratingdiff > 0) $ratingdiff = "+".$ratingdiff;

				// Schlüssel für Sortierung generieren
				$key = \StringUtil::generateAlias(sprintf('%04d', $t['playerNo']).$t['lastname'].$t['firstname']);

				// FIDE-Daten laden
				$fide = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getFIDEDatenLokal($t['fideId']);

				$this->daten['Spieler'][$key] = array
				(
					'Nummer'        => $t['playerNo'],
					'PKZ'           => $t['nuLigaPersonId'],
					'Spielername'   => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($t),
					'Scoresheet'    => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['tournament']['uuid'], $t['playerUuid'], 'Spielberichtsbogen von '.$t['firstname'].' '.$t['lastname'].' aufrufen'),
					'DWZ alt'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($t['ratingOld'], $t['indexOld']),
					'DWZ neu'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($t['ratingNew'], $t['indexNew']),
					'MglNr'         => sprintf('%04d', $t['memberNo']),
					'VKZ'           => $t['memberNo'] ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/%s.html">%s</a>', $t['vkz'], sprintf('%s-%s', $t['vkz'], sprintf('%04d', $t['memberNo']))) : '',
					'ZPS'           => sprintf('%s-%s', $t['vkz'], sprintf('%04d', $t['memberNo'])),
					'Verein'        => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite()."/%s.html\">%s</a>", $t['vkz'], $t['clubName']),
					'Geburt'        => $t['birthyear'],
					'Geschlecht'    => '',
					'Elo'           => $fide['elo'],
					'Titel'         => $fide['titel'],
					'DWZ+-'         => $t['ratingNew'] && $t['ratingOld'] ? $ratingdiff : '',
					'Punkte'        => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($t['wins']),
					'Partien'       => $t['numberOfGames'],
					'Ungewertet'    => '',
					'E'             => $t['factorK'],
					'Ergebnis'      => sprintf('%s/%s', \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($t['wins']), $t['numberOfGames']),
					'We'            => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($t['winsExpected']),
					'Niveau'        => $t['averageRatingCompetitors'],
					'Leistung'      => $t['tournamentPerformance'],
				);
			}
			// Liste nach dem Schlüssel (Nr./Nachname/Vorname) sortieren 
			ksort($this->daten['Spieler']);
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
