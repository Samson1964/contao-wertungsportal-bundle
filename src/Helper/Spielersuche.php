<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Spielersuche-Klasse
//  Gibt die Trefferliste einer Spielersuche formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Spielersuche
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiErgebnisse; // Enthält das Array von der API mit den Suchergebnissen
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Ergebnisse)
	{
		$this->apiErgebnisse = is_array($Ergebnisse) ? $Ergebnisse : array();

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die formatierte Trefferliste (sortiert nach Spielername)
	// ─────────────────────────────────────────────
	public function compile()
	{
		$this->daten['Spielerliste'] = array(); // Wird nachfolgend befüllt

		if(isset($this->apiErgebnisse['body']['data']) && is_array($this->apiErgebnisse['body']['data']))
		{
			// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
			$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($this->apiErgebnisse['body']['data'], 'nuLigaPersonId'));

			foreach($this->apiErgebnisse['body']['data'] as $person)
			{
				// Blacklist-Personen nicht anzeigen
				if(!empty($person['nuLigaPersonId']) && isset($blacklist[$person['nuLigaPersonId']])) continue;

				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('rating', $person)) $person['rating'] = false;
				if(!array_key_exists('index', $person)) $person['index'] = false;

				// Aktiv-Status suchen in Mitgliedschaften
				$verein = '';
				if(isset($person['memberships']))
				{
					foreach($person['memberships'] as $mitglied)
					{
						$verein = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);
						if($mitglied['licenceState'] == 'ACTIVE') break; // Abbruch wenn A-Status gefunden
					}
				}

				// Daten schreiben
				$this->daten['Spielerliste'][] = array
				(
					'PKZ'             => 'x',
					'Verein'          => $verein,
					'Spielername'     => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($person),
					'Spielername_RAW' => $person['lastname'].' '.$person['firstname'],
					'KW'              => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($person),
					'DWZ'             => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($person['rating'], $person['index']),
					'Elo'             => isset($person['fideElo']) ? $person['fideElo'] : '',
					'Titel'           => isset($person['fideTitle']) ? $person['fideTitle'] : '',
				);
			}

			// Sortierung nach Spielername (A → Z), Umlaute-sicher
			setlocale(LC_COLLATE, 'de_DE.UTF-8');
			usort($this->daten['Spielerliste'], function($a, $b)
			{
				return strcoll($a['Spielername_RAW'], $b['Spielername_RAW']);
			});
		}

		$this->daten['Anzahl'] = count($this->daten['Spielerliste']);
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
