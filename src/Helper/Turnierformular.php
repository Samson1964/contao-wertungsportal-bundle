<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Turnierformular-Klasse
//  Baut die Auswahlfelder des Turnier-Suchformulars auf
//  (Verbände, Monate, Von-/Bis-Jahr)
// ─────────────────────────────────────────────

class Turnierformular
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $liste; // Enthält die Verbands- und Vereinsliste (API::Verbandsliste)
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Liste)
	{
		$this->liste = is_array($Liste) ? $Liste : array('verbaende' => array());

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die Optionslisten für das Suchformular
	// ─────────────────────────────────────────────
	public function compile()
	{
		/*********************************************************
		 * Auswahl Verbände (Vorauswahl über Cookie)
		*/

		// ZPS der Verbände in Cookie gespeichert?
		$zpscookie = \Input::cookie('dewis-verband-zps');

		// DSB eintragen
		$opArray = array('<option value="" class="level_0"'.($zpscookie ? '' : ' selected').'><b>0 - Alle Verbände</b></option>');

		// Auswahl Verbände
		foreach($this->liste['verbaende'] as $key => $value)
		{
			$kurz = rtrim($value['clubVkz'],0);
			$kurzlaenge = strlen($kurz);
			if($zpscookie)
			{
				// Verband vorselektieren, wenn Cookie gesetzt ist
				$selected = ($zpscookie == $kurz) ? ' selected' : '';
			}
			else
			{
				// Kein oder leeres Cookie, ZPS 0 setzen
				$selected = ($kurzlaenge) ? '' : ' selected';
			}

			switch($kurzlaenge)
			{
				case 1:
					$opArray[] = sprintf('<option value="%s00" class="level_1"'.$selected.'>%s - %s</option>', $kurz, $kurz, $value['clubName']);
					break;
				case 2:
					$opArray[] = sprintf('<option value="%s0" class="level_2"'.$selected.'>%s - %s</option>', $kurz, $kurz, $value['clubName']);
					break;
				case 3:
					$opArray[] = sprintf('<option value="%s" class="level_3"'.$selected.'>%s - %s</option>', $kurz, $kurz, $value['clubName']);
					break;
				default:
			}
		}

		$this->daten['FormVerbaende'] = implode("\n", $opArray);

		/*********************************************************
		 * Auswahl Zeitraum
		*/

		$aktjahr = date("Y");
		$aktmonat = date("n");
		$monate = array
		(
			1 => "Januar",
			2 => "Februar",
			3 => "März",
			4 => "April",
			5 => "Mai",
			6 => "Juni",
			7 => "Juli",
			8 => "August",
			9 => "September",
			10 => "Oktober",
			11 => "November",
			12 => "Dezember"
		);

		// Auswahl Von-Monat/Bis-Monat
		$opArray = array();
		for($x = 1; $x <= 12; $x++)
		{
			$opArray[] = ($x == $aktmonat) ? '<option value="'.sprintf("%02d",$x).'" selected>'.$monate[$x].'</option>' : '<option value="'.sprintf("%02d",$x).'">'.$monate[$x].'</option>';
		}
		$this->daten['FormMonat'] = implode("\n", $opArray);

		// Auswahl Von-Jahr
		$opArray = array();
		for($x = 2011; $x <= $aktjahr; $x++)
		{
			$opArray[] = ($x == $aktjahr - 1) ? '<option value="'.$x.'" selected>'.$x.'</option>' : '<option value="'.$x.'">'.$x.'</option>';
		}
		$this->daten['FormVonjahr'] = implode("\n", $opArray);

		// Auswahl Bis-Jahr
		$opArray = array();
		for($x = 2011; $x <= $aktjahr; $x++)
		{
			$opArray[] = ($x == $aktjahr) ? '<option value="'.$x.'" selected>'.$x.'</option>' : '<option value="'.$x.'">'.$x.'</option>';
		}
		$this->daten['FormBisjahr'] = implode("\n", $opArray);
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
