<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Turnier
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * Wertungsportal-Abfrage:
 * Ausgabe Turniersuche / Ausgabe Turnierauswertung
 *
 * Die Aufbereitung der API-Daten für die Templates übernehmen die
 * Helper-Klassen Turniersuche, Scoresheet, Turnierergebnisse,
 * Turnierauswertung und Turnierformular.
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

		$search = \Input::get('search'); // Turniersuche aktiv?
		$turniercode = str_replace(' ','+',\Input::get('code')); // Turniercode, Leerzeichen durch + ersetzen, da der Browser aus + Leerzeichen macht
		$id = \Input::get('id'); // Spieler-ID
		$view = \Input::get('view'); // View

		// GET-Parameter nur bei aktiviertem Debug-Log protokollieren
		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])
		{
			$log = "GET-Parameter Turnier-Klasse\n";
			$log .= print_r($_GET, true)."\n";
			log_message($log, 'wertungsportal_oauth2client.log');
		}

		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
		$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben

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
			$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
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

			// API-Fehler im Template ausgeben statt auf die 404-Seite umzuleiten
			if($resultTur['error'] || $resultTur['http_code'] != 200)
			{
				$this->templateFehler('wertungsportal_spielberichtsbogen', 'Spielberichtsbogen', $resultTur);
				return;
			}
			if($resultErg['error'] || $resultErg['http_code'] != 200)
			{
				$this->templateFehler('wertungsportal_spielberichtsbogen', 'Spielberichtsbogen', $resultErg);
				return;
			}

			// Turnierinfo und Scoresheet auswerten
			$scoresheet = new \Schachbulle\ContaoWertungsportalBundle\Helper\Scoresheet($resultTur, $resultErg);

			// Blacklist: Spielberichtsbogen gesperrter Personen nicht anzeigen
			if($scoresheet->Gesperrt)
			{
				$objPage->pageTitle = 'Spielberichtsbogen';

				$this->Template = new \FrontendTemplate('wertungsportal_spielberichtsbogen');
				$this->Template->hl = 'h1'; // Standard-Überschriftgröße
				$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
				$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
				$this->Template->subHeadline = $scoresheet->Turniername; // Unterüberschrift Turnier setzen
				$this->Template->fehler = 'Dieser Spielberichtsbogen ist nicht verfügbar.';
				return;
			}

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
			$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
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

			// API-Fehler im Template ausgeben statt auf die 404-Seite umzuleiten
			if($resultTur['error'] || $resultTur['http_code'] != 200)
			{
				$this->templateFehler('wertungsportal_turnierergebnisse', 'Turnierergebnisse', $resultTur);
				return;
			}
			if($resultErg['error'] || $resultErg['http_code'] != 200)
			{
				$this->templateFehler('wertungsportal_turnierergebnisse', 'Turnierergebnisse', $resultErg);
				return;
			}

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
			$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
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

			// API-Fehler (z. B. "No evaluation found") im Template ausgeben statt auf die 404-Seite umzuleiten
			if($resultTur['error'] || $resultTur['http_code'] != 200)
			{
				$this->templateFehler('wertungsportal_turnierauswertung', 'DWZ-Auswertung', $resultTur);
				return;
			}

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
			$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $auswertung->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->turnierheader = $theader;
			$this->Template->spieler = $auswertung->Spieler;
		}
		else
		{
			/*********************************************************
			 * Suche nicht aktiv, deshalb Suchformular initialisieren
			*/

			// Verbands- und Vereinsliste holen (nur hier benötigt)
			$liste = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste('00000');

			// Auswahlfelder des Formulars aufbereiten
			$formular = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turnierformular($liste);

			$this->Template->form_verbaende = $formular->FormVerbaende;
			$this->Template->form_monat = $formular->FormMonat;
			$this->Template->form_vonjahr = $formular->FormVonjahr;
			$this->Template->form_bisjahr = $formular->FormBisjahr;
		}
	}

	/**
	 * Bereitet das Template für die Ausgabe eines API-Fehlers vor.
	 * Der Besucher bleibt auf der Seite und sieht die Meldung der API,
	 * statt auf die 404-Seite umgeleitet zu werden.
	 *
	 * @param string $strTemplate    Name des Templates (z. B. wertungsportal_turnierauswertung)
	 * @param string $strSubHeadline Unterüberschrift der Seite
	 * @param array  $arrResult      Rückgabe von API::autoQuery
	 */
	protected function templateFehler($strTemplate, $strSubHeadline, $arrResult)
	{
		global $objPage;

		// Fehlermeldung der API ermitteln (body ist im Fehlerfall meist ein String)
		$meldung = '';
		if(isset($arrResult['body']) && is_string($arrResult['body']) && $arrResult['body'] != '') $meldung = $arrResult['body'];
		elseif(!empty($arrResult['error_message'])) $meldung = $arrResult['error_message'];

		$objPage->pageTitle = $strSubHeadline;

		$this->Template = new \FrontendTemplate($strTemplate);
		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->headline = 'DWZ - Turnier'; // Standard-Überschrift
		$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
		$this->Template->subHeadline = $strSubHeadline; // Unterüberschrift setzen
		$this->Template->fehler = 'Die Wertungsportal-API meldet einen Fehler (HTTP-Code '.$arrResult['http_code'].')'.($meldung ? ': '.$meldung : '');
	}

}
