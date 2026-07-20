<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Vereinsliste-Klasse
//  Gibt die Mitgliederliste eines Vereins (Rang- oder Alphaliste)
//  formatiert zurück, so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Vereinsliste
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiErgebnisse; // Enthält das Array von der API mit der Spielerliste des Vereins
	public string $zps; // ZPS-Nummer des Vereins
	public string $sortierung; // Sortierung: rang oder alpha
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Ergebnisse, $zps, $sortierung)
	{
		$this->apiErgebnisse = is_array($Ergebnisse) ? $Ergebnisse : array();
		$this->zps = (string) $zps;
		$this->sortierung = ($sortierung == 'alpha') ? 'alpha' : 'rang';

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die sortierte Mitgliederliste (bei Rangliste mit Platz)
	// ─────────────────────────────────────────────
	public function compile()
	{
		$liste = array();
		$z = 0;

		if(isset($this->apiErgebnisse['body']['data']) && is_array($this->apiErgebnisse['body']['data']))
		{
			// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
			$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($this->apiErgebnisse['body']['data'], 'nuLigaPersonId'));

			foreach($this->apiErgebnisse['body']['data'] as $mitglied)
			{
				// Blacklist-Personen nicht anzeigen
				if(!empty($mitglied['nuLigaPersonId']) && isset($blacklist[$mitglied['nuLigaPersonId']])) continue;

				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('rating', $mitglied)) $mitglied['rating'] = false;
				if(!array_key_exists('index', $mitglied)) $mitglied['index'] = false;

				// Schlüssel für Sortierung generieren
				$z++;
				$key = ($this->sortierung == 'alpha') ? \StringUtil::generateAlias($mitglied['lastname'].$mitglied['firstname'].$z) : sprintf('%05d-%04d-%s-%03d', 10000 - $mitglied['rating'], 1000 - $mitglied['index'], 'Z', $z);

				// Daten zuweisen
				$liste[$key] = array
				(
					'PKZ'         => $mitglied['nuLigaPersonId'],
					'Mglnr'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsnummer($mitglied, $this->zps),
					'Status'      => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsstatus($mitglied, $this->zps),
					'Spielername' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($mitglied),
					'Geschlecht'  => $mitglied['gender'] == 'MALE' ? 'M' : ($mitglied['gender'] == 'FEMALE' ? 'W' : strtoupper($mitglied['gender'])),
					'KW'          => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($mitglied),
					'DWZ'         => $mitglied['rating'].' - '.$mitglied['index'],
					'Elo'         => isset($mitglied['fideElo']) ? $mitglied['fideElo'] : '',
					'FIDE-Titel'  => isset($mitglied['fideTitle']) ? $mitglied['fideTitle'] : '',
				);
			}
		}

		// Liste sortieren (ASC)
		ksort($liste);

		// Platzierung hinzufügen
		$this->daten['Rangliste'] = ($this->sortierung == 'rang');
		if($this->daten['Rangliste'])
		{
			$z = 1;
			foreach($liste as $key => $value)
			{
				$liste[$key]['Platz'] = $z;
				$z++;
			}
		}

		$this->daten['Daten'] = $liste;
		$this->daten['Anzahl'] = count($liste);
	}

	// ─────────────────────────────────────────────
	//  Magische Methode __set
	// ─────────────────────────────────────────────
	public function __set($name, $value)
	{
		$this->daten[$name] = $value;
	}

	// ─────────────────────────────────────────────
	//  Magische Methode __get
	// ─────────────────────────────────────────────
	public function __get($name)
	{
		return array_key_exists($name, $this->daten) ? $this->daten[$name] : null;
	}
}
