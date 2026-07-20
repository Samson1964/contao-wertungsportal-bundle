<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Verbandsnavigation-Klasse
//  Baut die Struktur der Verbandszugehörigkeiten (DSB → Landesverband
//  → Bezirk → Kreis) für die Ausgabe im Verbandsmodul auf
// ─────────────────────────────────────────────

class Verbandsnavigation
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $liste; // Enthält die Verbands- und Vereinsliste (API::Verbandsliste)
	public string $zps; // ZPS-Nummer des angeforderten Verbands (dreistellig)
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Liste, $zps)
	{
		$this->liste = is_array($Liste) ? $Liste : array('verbaende' => array());
		$this->zps = (string) $zps;

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die Verbandsstruktur entsprechend der angeforderten ZPS,
	//  DSB zuerst; beim aktuellen Verband wird der Vereine-Link ergänzt
	// ─────────────────────────────────────────────
	public function compile()
	{
		$verbaende = array();
		$verbaende['000'] = array
		(
			'vkz'   => '000',
			'name'  => 'Deutscher Schachbund',
			'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseiteUrl().'/%s.html">%s</a>', '000', 'Deutscher Schachbund'),
			'ebene' => 'level_0'
		);

		foreach($this->liste['verbaende'] as $verband)
		{
			// Landes- oder Mitgliedsverband des DSB zuweisen (Ebene 1), VKZ = ?00??
			if(substr($verband['clubVkz'], 1, 2) == '00')
			{
				$verbaende[substr($verband['clubVkz'],0,3)] = array
				(
					'vkz'   => substr($verband['clubVkz'],0,3),
					'name'  => $verband['clubName'],
					'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseiteUrl().'/%s.html">%s</a>', substr($verband['clubVkz'],0,3), $verband['clubName']),
					'ebene' => 'level_1'
				);
			}
			// Bezirk zuweisen, wenn 1. Stelle VKZ mit Such-ZPS übereinstimmt
			elseif(substr($this->zps,0,1) == substr($verband['clubVkz'],0,1) && substr($verband['clubVkz'],2,1) == '0')
			{
				$verbaende[substr($verband['clubVkz'],0,3)] = array
				(
					'vkz'   => substr($verband['clubVkz'],0,3),
					'name'  => $verband['clubName'],
					'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseiteUrl().'/%s.html">%s</a>', substr($verband['clubVkz'],0,3), $verband['clubName']),
					'ebene' => 'level_2'
				);
			}
			// Kreis zuweisen, wenn 1.+2. Stelle VKZ mit Such-ZPS übereinstimmt
			elseif(substr($this->zps,0,2) == substr($verband['clubVkz'],0,2))
			{
				$verbaende[substr($verband['clubVkz'],0,3)] = array
				(
					'vkz'   => substr($verband['clubVkz'],0,3),
					'name'  => $verband['clubName'],
					'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseiteUrl().'/%s.html">%s</a>', substr($verband['clubVkz'],0,3), $verband['clubName']),
					'ebene' => 'level_3'
				);
			}
		}

		// Vereine-Link ergänzen bei aktuell angefordertem Verband
		if($this->zps != '000' && isset($verbaende[$this->zps]))
		{
			$verbaende[$this->zps]['url'] = '<b>'.$verbaende[$this->zps]['url'].sprintf(' - <a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl().'/%s.html">Vereine</a>', $this->zps).'</b>';
		}

		$this->daten['Verbaende'] = $verbaende;
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
