<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Verbandsrangliste-Klasse
//  Gibt die Topliste eines Verbands formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Verbandsrangliste
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiErgebnisse; // Enthält das Array von der API mit der Verbandsliste
	public string $zps; // ZPS-Nummer des Verbands
	public int $toplist; // Maximale Anzahl der Plätze
	public bool $german; // Nur deutsche Spieler ausgeben
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Ergebnisse, $zps, $toplist, $german)
	{
		$this->apiErgebnisse = is_array($Ergebnisse) ? $Ergebnisse : array();
		$this->zps = (string) $zps;
		$this->toplist = (int) $toplist;
		$this->german = (bool) $german;

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die Rangliste inkl. FIDE-Daten (Bulk-Abfrage) und
	//  optionalem Filter auf deutsche Spieler
	// ─────────────────────────────────────────────
	public function compile()
	{
		$rangliste = array();
		$platz = 0;

		if(empty($this->apiErgebnisse['error']) && isset($this->apiErgebnisse['body']['data']) && is_array($this->apiErgebnisse['body']['data']))
		{
			// FIDE-Daten für alle Spieler in einem Rutsch laden statt je Spieler einzeln
			$fideids = array();
			foreach($this->apiErgebnisse['body']['data'] as $spieler)
			{
				if(!empty($spieler['fideId'])) $fideids[] = $spieler['fideId'];
			}
			$fideliste = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getFIDEDatenListe($fideids);

			// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
			$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($this->apiErgebnisse['body']['data'], 'nuLigaPersonId'));

			foreach($this->apiErgebnisse['body']['data'] as $spieler)
			{
				// Blacklist-Personen nicht anzeigen
				if(!empty($spieler['nuLigaPersonId']) && isset($blacklist[$spieler['nuLigaPersonId']])) continue;

				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('fideId', $spieler)) $spieler['fideId'] = false;
				if(!array_key_exists('rating', $spieler)) $spieler['rating'] = false;
				if(!array_key_exists('index', $spieler)) $spieler['index'] = false;

				// FIDE-Daten aus Tabelle tl_wertungsportal_elo holen
				$fide = $spieler['fideId'] && isset($fideliste[$spieler['fideId']]) ? $fideliste[$spieler['fideId']] : array('land' => '', 'elo' => '', 'titel' => '');
				$fide_nation = $fide['land'];
				$fide_elo = $fide['elo'];
				$fide_titel = $fide['titel'];

				if($this->german && $fide_nation != 'GER' && $fide_nation != '' && $fide_nation != '-')
				{
					continue; // Nur Deutsche gesucht, also nächsten Datensatz prüfen
				}

				// Mitgliedschaft durchsuchen, bei aktiver abbrechen
				$mitgliedschaft = array('verein' => '', 'status' => '');
				if(isset($spieler['memberships']))
				{
					foreach($spieler['memberships'] as $mitglied)
					{
						$mitgliedschaft['verein'] = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);
						$mitgliedschaft['status'] = $mitglied['licenceState'] == 'ACTIVE' ? '' : substr($mitglied['licenceState'], 0, 1);
						if($mitglied['licenceState'] == 'ACTIVE') break; // Abbruch wenn A-Status gefunden
					}
				}

				// Daten zuweisen
				$platz++;
				$rangliste[] = array
				(
					'Platz'       => $platz,
					'PKZ'         => $spieler['nuLigaPersonId'],
					'Mglnr'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsnummer($spieler, $this->zps),
					'Status'      => $mitgliedschaft['status'],
					'Spielername' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($spieler),
					'Geschlecht'  => $spieler['gender'] == 'MALE' ? 'M' : ($spieler['gender'] == 'FEMALE' ? 'W' : strtoupper($spieler['gender'])),
					'KW'          => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($spieler),
					'DWZ'         => $spieler['rating'].' - '.$spieler['index'],
					'Elo'         => $fide_elo ? $fide_elo : '-',
					'FIDE-Titel'  => $fide_titel,
					'FIDE-Nation' => '{{flagge::'.$fide_nation.'}}',
					'Verein'      => $mitgliedschaft['verein'],
				);
				if($platz == $this->toplist) break; // Abbruch wenn Limit erreicht
			}
		}

		$this->daten['Rangliste'] = $rangliste;
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
