<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Spieler
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * Wertungsportal-Abfrage:
 * Ausgabe Turniersuche / Ausgabe Turnierauswertung
 *
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Turnier extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_turnier';
	protected $subTemplate = 'wertungsportal_sub_turniersuche';

	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL TURNIER ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}
		else
		{
			// FE-Modus: URL mit allen möglichen Parametern auflösen
			\Input::setGet('code', \Input::get('code')); // Turniercode
			\Input::setGet('id', \Input::get('id')); // ID des Spielers
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{

		global $objPage;

		// Blacklist laden
		$Blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Blacklist();

		$code = \Input::get('code'); // Turniercode angefordert?
		$search = \Input::get('search'); // Turniersuche aktiv?
		$turniercode = str_replace(' ','+',\Input::get('code')); // Turniercode, Leerzeichen durch + ersetzen, da der Browser aus + Leerzeichen macht
		$id = \Input::get('id'); // Spieler-ID
		$view = \Input::get('view'); // View

		$log = "GET-Parameter Turnier-Klasse\n";
		$log .= print_r($_GET, true)."\n";
		log_message($log, 'wertungsportal_oauth2client.log');

		$mitglied = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitglied(); // Daten des aktuellen Mitgliedes laden

		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
		$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben

		// Sperrstatus festlegen
		if($GLOBALS['TL_CONFIG']['wertungsportal_karteisperre_gaeste']) $gesperrt = $mitglied->id ? false : true;
		else $gesperrt = false;

		// DeWIS-Klasse initialisieren
		$dewis = new \Schachbulle\ContaoWertungsportalBundle\Helper\API();

		// Verbands- und Vereinsliste holen
		$liste = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste('00000');

		if($search)
		{

			/*********************************************************
			 * Turniersuche
			*/

			// Übergebenen ZPS-Parameter korrigieren: dreistellig und Großschreibung
			$zps = \Input::get('zps');

			// ZPS-Cookie setzen
			setcookie('dewis-verband-zps', rtrim(\Input::get('zps'),0), time()+8640000, '/');

			// GET-Parameter korrigieren
			$last_months = 0 + (int)\Input::get('last_months');
			$from_year = sprintf('%04d',\Input::get('from_year'));
			$to_year = sprintf('%04d',\Input::get('to_year'));
			$from_month = sprintf('%02d',\Input::get('from_month'));
			$to_month = sprintf('%02d',\Input::get('to_month'));
			($from_year < 2011) ? $from_year = 2011 : '';

			// Zeitraum anpassen, wenn "Letzte x Monate" gewählt wurde
			if($last_months > 0 && $last_months < 13)
			{
				$last_months--; // Wegen aktuellem Monat 1 abziehen
				$from_year = date('Y', strtotime('-'.$last_months.' months', mktime(0,0,0,date("n",$aktzeit),1,date("Y",$aktzeit))));
				$from_month = date('m', strtotime('-'.$last_months.' months', mktime(0,0,0,date("n",$aktzeit),1,date("Y",$aktzeit))));
				$to_year = date('Y', $aktzeit);
				$to_month = date('m', $aktzeit);
			}
			$periode = array
			(
				'von' => $from_year ? $from_year.'-'.$from_month.'-01' : $from_year.'-01-01',
				'bis' => $to_year ? $to_year.'-'.$to_month.'-'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Monatstage($to_year)[ltrim($to_month,0)] : $to_year.'-12-31',
			);

			// ZPS-Nummer des Verbandes
			$param = array
			(
				'funktion'  => 'Turnierliste',
				'cachekey'  => strtolower(\Input::get('keyword')).'-'.$zps.'-'.$periode['von'].'-'.$periode['bis'],
				'von'       => $periode['von'],
				'bis'       => $periode['bis'],
				'zps'       => $zps,
				'suche'     => strtolower(\Input::get('keyword')),
			);
			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Ergebnis der Turniersuche auswerten
			$trefferliste = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turniersuche($resultArr);

			$title = 'Ergebnis für den Zeitraum '.$from_month.'/'.$from_year.' bis '.$to_month.'/'.$to_year;
			$objPage->pageTitle = $title;

			$this->Template = new \FrontendTemplate('wertungsportal_turniersuche');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
			$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $title; // Unterüberschrift Turnier setzen
			$this->Template->daten = $trefferliste->Turnierliste;
			$this->Template->anzahl = count($trefferliste->Turnierliste);
			$this->Template->search_keyword = $param['suche'];
			$this->Template->search_verband = $zps;
			$this->Template->search_from = $from_month.'/'.$from_year;
			$this->Template->search_to = $to_month.'/'.$to_year;

		}
		elseif($turniercode && $id)
		{
			/*********************************************************
			 * Ausgabe des Scoresheets (Spielberichtsbogen)
			*/

			// Turnierinfo laden
			$param = array
			(
				'funktion'  => 'Turnierinfo',
				'cachekey'  => $turniercode,
				'turnier'   => $turniercode
			);
			$resultTur = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Scoresheet laden
			$param = array
			(
				'funktion'  => 'Spielberichtsbogen',
				'cachekey'  => $turniercode.'-'.$id,
				'turnier'   => $turniercode,
				'id'        => $id
			);
			$resultErg = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Turnierinfo und Scoresheet auswerten
			$scoresheet = new \Schachbulle\ContaoWertungsportalBundle\Helper\Scoresheet($resultTur, $resultErg);

			$theader = array
			(
				'Auswertung'            => $scoresheet->Auswertung,
				'Turniercode'           => $scoresheet->Turniercode,
				'Turniername'           => $scoresheet->Turniername,
				'Turnierende'           => $scoresheet->Turnierende,
				'Berechnet'             => $scoresheet->Berechnet,
				'Nachberechnet'         => $scoresheet->Nachberechnet,
				'Auswerter1'            => $scoresheet->Auswerter1,
				'Auswerter2'            => $scoresheet->Auswerter2,
				'Spieler'               => $scoresheet->AnzahlSpieler,
				'Partien'               => $scoresheet->AnzahlPartien,
				'Runden'                => $scoresheet->AnzahlRunden,
				'Turnierauswertunglink' => $scoresheet->Turnierauswertunglink,
				'Turnierergebnislink'   => $scoresheet->Turnierergebnislink,
			);

			$objPage->pageTitle = 'Turnier '.$scoresheet->Turniername.' | Spielbericht '.$scoresheet->Spieler['Name'];

			$this->Template = new \FrontendTemplate('wertungsportal_spielberichtsbogen');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
			$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $scoresheet->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->playerHeadline = 'Spielberichtsbogen <b>'.$scoresheet->Spieler['Name'].'</b>'.($scoresheet->Spieler['DWZ alt'] ? ' / DWZ '.$scoresheet->Spieler['DWZ alt'] : ''); // Unterüberschrift Spieler setzen
			$this->Template->turnierheader = $theader;
			$this->Template->partien = $scoresheet->Ergebnisse;
			$this->Template->spieler = $scoresheet->Spieler;

		}
		elseif($turniercode && !$id && $view == 'results')
		{
			/*********************************************************
			 * Ausgabe der Turnierergebnisse
			*/

			// Turnierinfo laden
			$param = array
			(
				'funktion'  => 'Turnierinfo',
				'cachekey'  => $turniercode,
				'turnier'   => $turniercode
			);
			$resultTur = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Turnierergebnisse laden
			$param = array
			(
				'funktion'  => 'Turnierergebnisse',
				'cachekey'  => $turniercode,
				'turnier'   => $turniercode
			);
			$resultErg = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Turnierinfo und Turnierergebnisse auswerten
			$ergebnisse = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turnierergebnisse($resultTur, $resultErg);

			$theader = array
			(
				'Turniercode'           => $ergebnisse->Turniercode,
				'Turniername'           => $ergebnisse->Turniername,
				'Turnierende'           => $ergebnisse->Turnierende,
				'Berechnet'             => $ergebnisse->Berechnet,
				'Nachberechnet'         => $ergebnisse->Nachberechnet,
				'Auswerter1'            => $ergebnisse->Auswerter1,
				'Auswerter2'            => $ergebnisse->Auswerter2,
				'Spieler'               => $ergebnisse->AnzahlSpieler,
				'Partien'               => $ergebnisse->AnzahlPartien,
				'Runden'                => $ergebnisse->AnzahlRunden,
				'Turnierauswertunglink' => $ergebnisse->Turnierauswertunglink,
			);

			$objPage->pageTitle = 'Ergebnisse '.$ergebnisse->Turniername;

			$this->Template = new \FrontendTemplate('wertungsportal_turnierergebnisse');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
			$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $ergebnisse->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->turnierheader = $theader;
			$this->Template->spieler = $ergebnisse->Spieler;

		}
		elseif($turniercode && !$id)
		{
			/*********************************************************
			 * Ausgabe der Turnierauswertung
			*/

			// Turnierauswertung laden
			$param = array
			(
				'funktion'  => 'Turnierauswertung',
				'cachekey'  => \Input::get('code'),
				'turnier'   => \Input::get('code')
			);
			$resultTur = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Turnierauswertung auswerten
			$auswertung = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turnierauswertung($resultTur);

			$theader = array
			(
				'Auswertung'            => $auswertung->Auswertung,
				'Turniercode'           => $auswertung->Turniercode,
				'Turniername'           => $auswertung->Turniername,
				'Turnierende'           => $auswertung->Turnierende,
				'Berechnet'             => $auswertung->Berechnet,
				'Nachberechnet'         => $auswertung->Nachberechnet,
				'Auswerter1'            => $auswertung->Auswerter1,
				'Auswerter2'            => $auswertung->Auswerter2,
				'Spieler'               => $auswertung->AnzahlSpieler,
				'Partien'               => $auswertung->AnzahlPartien,
				'Runden'                => $auswertung->AnzahlRunden,
				'Turnierauswertunglink' => $auswertung->Turnierauswertunglink,
				'Turnierergebnislink'   => $auswertung->Turnierergebnislink,
			);

			$objPage->pageTitle = 'DWZ-Auswertung '.$auswertung->Turniername;

			$this->Template = new \FrontendTemplate('wertungsportal_turnierauswertung');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
			$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $auswertung->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->turnierheader = $theader;
			$this->Template->spieler = $auswertung->Spieler;

		}
		else
		{

			/*********************************************************
			 * Suche nicht aktiv, deshalb Suchformular initialisieren
			*/

			// ZPS der Verbände in Cookie gespeichert?
			$zpscookie = \Input::cookie('dewis-verband-zps');

			// DSB eintragen
			$opArray = array('<option value="" class="level_0"'.($zpscookie ? '' : ' selected').'><b>0 - Alle Verbände</b></option>');
			// Auswahl Verbände
			foreach($liste['verbaende'] as $key => $value)
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

			$this->Template->form_verbaende = implode("\n",$opArray);

			// Auswahl Zeitraum
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
			$this->Template->form_monat = implode("\n",$opArray);

			// Auswahl Von-Jahr
			$opArray = array();
			for($x = 2011; $x <= $aktjahr; $x++)
			{
				$opArray[] = ($x == $aktjahr - 1) ? '<option value="'.$x.'" selected>'.$x.'</option>' : '<option value="'.$x.'">'.$x.'</option>';
			}
			$this->Template->form_vonjahr = implode("\n",$opArray);

			// Auswahl Bis-Jahr
			$opArray = array();
			for($x = 2011; $x <= $aktjahr; $x++)
			{
				$opArray[] = ($x == $aktjahr) ? '<option value="'.$x.'" selected>'.$x.'</option>' : '<option value="'.$x.'">'.$x.'</option>';
			}
			$this->Template->form_bisjahr = implode("\n",$opArray);
		}


	}

}