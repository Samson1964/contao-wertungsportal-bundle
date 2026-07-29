<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Vereinssuche-Klasse
//  Durchsucht die Verbands- und Vereinsliste nach einem Suchbegriff
//  und gibt die Trefferlisten formatiert zurück
// ─────────────────────────────────────────────

class Vereinssuche
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $liste; // Enthält die Verbands- und Vereinsliste (API::Verbandsliste)
	public string $suchbegriff; // Suchbegriff (bereits konvertiert)
	private string $aliasSuche = ''; // Suchbegriff als Alias (umlautunabhängiger Vergleich)
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Liste, $Suchbegriff)
	{
		$this->liste = is_array($Liste) ? $Liste : array('verbaende' => array(), 'vereine' => array());
		$this->suchbegriff = (string) $Suchbegriff;

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion trifft
	//  Prüft, ob ein Vereinsname den Suchbegriff enthält — verglichen wird
	//  über die Suchaliase (kleingeschrieben, ohne Umlaute), damit die
	//  Schreibweise keine Rolle spielt: "königsspringer" findet auch
	//  "Koenigsspringer", "büchenbach" auch "Buechenbach".
	//  Die Aliase der Vereine stehen zwar auch in tl_wertungsportal_clubs,
	//  hier wird aber die API-Liste durchsucht (sie enthält Verbände und
	//  Vereine und ist die Quelle der Anzeige) — deshalb wird der Alias für
	//  den Vergleich erzeugt statt aus der Datenbank gelesen.
	// ─────────────────────────────────────────────
	private function trifft($name)
	{
		if($this->aliasSuche === '') return false;

		return strpos(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::alias($name), $this->aliasSuche) !== false;
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die Trefferlisten der Verbände und Vereine
	// ─────────────────────────────────────────────
	public function compile()
	{
		// Suchbegriff einmalig in seinen Alias umwandeln. "-" heißt: keine
		// verwertbaren Zeichen — dann bleiben beide Trefferlisten leer
		$alias = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::alias($this->suchbegriff);
		$this->aliasSuche = ($alias === '-') ? '' : $alias;

		/*********************************************************
		 * Verbandsliste durchsuchen, Treffer in Array speichern
		*/

		$this->daten['Verbaende'] = array();
		foreach($this->liste['verbaende'] as $item)
		{
			if($this->trifft($item['clubName']))
			{
				$this->daten['Verbaende'][] = array
				(
					'zps'  => $item['clubVkz'],
					'name' => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseiteUrl().'/%s.html">%s</a>', $item['clubVkz'], $item['clubName']),
				);
			}
		}

		/*********************************************************
		 * Vereinsliste durchsuchen, Treffer in Array speichern
		*/

		$this->daten['Vereine'] = array();
		foreach($this->liste['vereine'] as $item)
		{
			if($this->trifft($item['clubName']))
			{
				$this->daten['Vereine'][] = array
				(
					'zps'  => $item['clubVkz'],
					'name' => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl().'/%s.html">%s</a>', $item['clubVkz'], $item['clubName']),
				);
			}
		}
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
