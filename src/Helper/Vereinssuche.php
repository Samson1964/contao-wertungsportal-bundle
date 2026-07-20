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
	//  Funktion compile
	//  Erstellt die Trefferlisten der Verbände und Vereine
	// ─────────────────────────────────────────────
	public function compile()
	{
		/*********************************************************
		 * Verbandsliste durchsuchen, Treffer in Array speichern
		*/

		$this->daten['Verbaende'] = array();
		foreach($this->liste['verbaende'] as $item)
		{
			if(stripos($item['clubName'], $this->suchbegriff) !== false)
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
			if(stripos($item['clubName'], $this->suchbegriff) !== false)
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
