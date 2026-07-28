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
	public array $auswahl; // Aktuelle Sucheinstellungen zur Vorbelegung
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	//  $Auswahl belegt die Felder mit den Werten einer laufenden Suche vor
	//  (zps, from_month, from_year, to_month, to_year, last_months);
	//  ohne Angabe gelten die Standardwerte wie auf der Suchseite
	// ─────────────────────────────────────────────
	public function __construct($Liste, $Auswahl = array())
	{
		$this->liste = is_array($Liste) ? $Liste : array('verbaende' => array());
		$this->auswahl = is_array($Auswahl) ? array_filter($Auswahl, function($wert) { return $wert !== null && $wert !== ''; }) : array();

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Liefert den vorbelegten Wert eines Feldes (oder false)
	// ─────────────────────────────────────────────
	protected function gewaehlt($feld)
	{
		return isset($this->auswahl[$feld]) ? (string) $this->auswahl[$feld] : false;
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

		// Vorbelegung: Wert der laufenden Suche schlägt das Cookie
		$zpswahl = $this->gewaehlt('zps');
		$zpscookie = $zpswahl !== false ? $zpswahl : \Input::cookie('dewis-verband-zps');

		// DSB eintragen
		$opArray = array('<option value="" class="level_0"'.($zpscookie ? '' : ' selected').'><b>0 - Alle Verbände</b></option>');

		// Auswahl Verbände
		foreach($this->liste['verbaende'] as $key => $value)
		{
			$kurz = rtrim($value['clubVkz'],0);
			$kurzlaenge = strlen($kurz);
			if($zpscookie)
			{
				// Verband vorselektieren (Suchwert oder Cookie); die Suche
				// übergibt die volle VKZ (z. B. "300"), das Cookie die gekürzte
				$selected = ($zpscookie == $kurz || rtrim((string) $zpscookie, '0') == $kurz) ? ' selected' : '';
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

		// Auswahl Monat: getrennt für Von und Bis, damit beide unabhängig
		// vorbelegt werden können (früher lieferte eine gemeinsame Liste
		// zweimal denselben ausgewählten Monat)
		$this->daten['FormVonmonat'] = $this->monatsliste($monate, $this->gewaehlt('from_month') !== false ? (int) $this->gewaehlt('from_month') : $aktmonat);
		$this->daten['FormBismonat'] = $this->monatsliste($monate, $this->gewaehlt('to_month') !== false ? (int) $this->gewaehlt('to_month') : $aktmonat);

		// Bestandsname beibehalten (ältere Templates lesen FormMonat)
		$this->daten['FormMonat'] = $this->daten['FormVonmonat'];

		// Auswahl Von-Jahr (Standard: Vorjahr)
		$this->daten['FormVonjahr'] = $this->jahresliste(2011, (int) $aktjahr, $this->gewaehlt('from_year') !== false ? (int) $this->gewaehlt('from_year') : (int) $aktjahr - 1);

		// Auswahl Bis-Jahr (Standard: aktuelles Jahr)
		$this->daten['FormBisjahr'] = $this->jahresliste(2011, (int) $aktjahr, $this->gewaehlt('to_year') !== false ? (int) $this->gewaehlt('to_year') : (int) $aktjahr);

		/*********************************************************
		 * Auswahl "Letzte x Monate"
		*/

		$gewaehlteMonate = $this->gewaehlt('last_months');
		$opArray = array('<option value=""'.($gewaehlteMonate ? '' : ' selected').'>Alternativen Zeitraum wählen …</option>');

		for($x = 1; $x <= 12; $x++)
		{
			$text = ($x == 1) ? 'Aktueller Monat' : 'Letzte '.$x.' Monate';
			$opArray[] = '<option value="'.$x.'"'.(($gewaehlteMonate !== false && (int) $gewaehlteMonate === $x) ? ' selected' : '').'>'.$text.'</option>';
		}

		$this->daten['FormLetzteMonate'] = implode("\n", $opArray);

		// Suchbegriff für die Vorbelegung des Textfelds
		$this->daten['FormSuchbegriff'] = $this->gewaehlt('keyword') !== false ? $this->gewaehlt('keyword') : '';
	}

	// ─────────────────────────────────────────────
	//  Baut die Monatsliste mit dem gewünschten Monat als Vorauswahl
	// ─────────────────────────────────────────────
	protected function monatsliste($monate, $auswahl)
	{
		$opArray = array();

		for($x = 1; $x <= 12; $x++)
		{
			$opArray[] = '<option value="'.sprintf('%02d', $x).'"'.(($x == $auswahl) ? ' selected' : '').'>'.$monate[$x].'</option>';
		}

		return implode("\n", $opArray);
	}

	// ─────────────────────────────────────────────
	//  Baut die Jahresliste mit dem gewünschten Jahr als Vorauswahl
	// ─────────────────────────────────────────────
	protected function jahresliste($von, $bis, $auswahl)
	{
		$opArray = array();

		for($x = $von; $x <= $bis; $x++)
		{
			$opArray[] = '<option value="'.$x.'"'.(($x == $auswahl) ? ' selected' : '').'>'.$x.'</option>';
		}

		return implode("\n", $opArray);
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
