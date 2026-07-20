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
		$this->daten['Turnierergebnislink']   = sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/Ergebnisse.html">Turnierergebnisse</a>', $this->apiTurnierinfo['body']['tournament']['uuid']);
		$this->daten['Turniercode']           = $this->apiTurnierinfo['body']['tournament']['uuid'];
		$this->daten['Turnierende']           = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($this->apiTurnierinfo['body']['tournament']['enddate'] ?? null, 'Y-m-d', 'd.m.Y');
		$this->daten['Berechnet']             = 'unbekannt';
		$this->daten['Nachberechnet']         = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($this->apiTurnierinfo['body']['tournament']['lastCalculated'] ?? null, 'Y-m-d\TH:i:s', 'd.m.Y H:i');
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
			// FIDE-Daten für alle Spieler in einem Rutsch laden statt je Spieler einzeln
			$fideids = array();
			foreach($this->apiTurnierinfo['body']['players'] as $t)
			{
				if(!empty($t['fideId'])) $fideids[] = $t['fideId'];
			}
			$fideliste = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getFIDEDatenListe($fideids);

			// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
			$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($this->apiTurnierinfo['body']['players'], 'nuLigaPersonId'));

			foreach($this->apiTurnierinfo['body']['players'] as $t)
			{
				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				// Fehlende Felder des Spieler-DTOs mit Standardwerten auffüllen
				$t = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::PlayerDefaults($t);

				// Ratingdifferenz errechnen
				$ratingdiff = $t['ratingNew'] - $t['ratingOld'];
				if($ratingdiff > 0) $ratingdiff = "+".$ratingdiff;

				// Schlüssel für Sortierung generieren
				$key = \StringUtil::generateAlias(sprintf('%04d', $t['playerNo']).$t['lastname'].$t['firstname']);

				// FIDE-Daten laden
				$fide = $t['fideId'] && isset($fideliste[$t['fideId']]) ? $fideliste[$t['fideId']] : array('land' => '', 'elo' => '', 'titel' => '');

				$this->daten['Spieler'][$key] = array
				(
					'Nummer'        => $t['playerNo'],
					'PKZ'           => $t['nuLigaPersonId'],
					'Spielername'   => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($t),
					'Scoresheet'    => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/%s/%s.html" title="%s">SC</a>', $this->apiTurnierinfo['body']['tournament']['uuid'], $t['playerUuid'], 'Spielberichtsbogen von '.$t['firstname'].' '.$t['lastname'].' aufrufen'),
					'DWZ alt'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($t['ratingOld'], $t['indexOld']),
					'DWZ neu'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($t['ratingNew'], $t['indexNew']),
					'MglNr'         => sprintf('%04d', $t['memberNo']),
					'VKZ'           => $t['memberNo'] ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl().'/%s.html">%s</a>', $t['vkz'], sprintf('%s-%s', $t['vkz'], sprintf('%04d', $t['memberNo']))) : '',
					'ZPS'           => sprintf('%s-%s', $t['vkz'], sprintf('%04d', $t['memberNo'])),
					'Verein'        => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html\">%s</a>", $t['vkz'], $t['clubName']),
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

				// Blacklist: Die Zeile bleibt wegen der Turnier-Querbezüge erhalten,
				// aber ohne Personenbezug (kein Name, keine Links, kein Verein)
				if(!empty($t['nuLigaPersonId']) && isset($blacklist[$t['nuLigaPersonId']]))
				{
					$this->daten['Spieler'][$key] = array_merge($this->daten['Spieler'][$key], array
					(
						'PKZ'         => '',
						'Spielername' => '<i>gesperrt</i>',
						'Scoresheet'  => '',
						'MglNr'       => '',
						'VKZ'         => '',
						'ZPS'         => '',
						'Verein'      => '',
						'Geburt'      => '',
						'Elo'         => '',
						'Titel'       => '',
					));
				}
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
