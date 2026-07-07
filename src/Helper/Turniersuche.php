<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Turnierergebnisse-Klasse
//  Gibt alle Daten für die Turnierergebnisse formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Turniersuche
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiErgebnisse; // Enthält das Array von der API mit den Suchergebnissen
	private array $daten; // Enthält die Scoresheet-Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Ergebnisse)
	{
		$this->apiErgebnisse = $Ergebnisse;
		$this->idTurnier = \Input::get('code'); // Turniercode
		
		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die formatierten Daten für das Suchformular
	//  und die Trefferliste.
	// ─────────────────────────────────────────────
	public function compile() 
	{

		$this->daten['Turnierliste'] = array(); // Wird nachfolgend befüllt

		if($this->apiErgebnisse['body']['data'])
		{
			foreach($this->apiErgebnisse['body']['data'] as $t)
			{
				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('referentLastname', $t)) $t['referentLastname'] = false;
				if(!array_key_exists('referentFirstname', $t)) $t['referentFirstname'] = false;
				if(!array_key_exists('vkz', $t)) $t['vkz'] = false;

				$this->daten['Turnierliste'][] = array
				(
					'Teilnehmer'    => '?', //$t->cntPlayer,
					'Turniercode'   => $t['uuid'],
					'Turniername'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite().'/%s.html" title="%s">%s</a>', $t['uuid'], $t['label'], \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Turnierkurzname($t['label'])),
					'Turnierregion' => $t['vkz'],
					'Turnierende'   => \DateTime::createFromFormat('Y-m-d', $t['enddate'])->format('d.m.Y'),
					'Auswerter'     => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt() ? 'Sie müssen sich anmelden, um diese Daten sehen zu können.' : ($t['referentLastname'] ? $t['referentFirstname'].' '.$t['referentLastname'] : '?'),
				);
			}
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
