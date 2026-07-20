<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Verband
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * Wertungsportal-Abfrage:
 * Ausgabe Verbandssuche / Ausgabe Verbandsliste
 *
 * Die Aufbereitung der API-Daten für die Templates übernehmen die
 * Helper-Klassen Verbandsnavigation und Verbandsrangliste.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Verband extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_verband';
	protected $subTemplate = 'wertungsportal_sub_verbandsuche';

	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL VERBAND ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}
		else
		{
			// FE-Modus: URL mit allen möglichen Parametern auflösen
			\Input::setGet('zps', \Input::get('zps')); // ZPS-Nummer des Verbands
			\Input::setGet('toplist', \Input::get('toplist')); // Top x bei Toplistenausgabe
			\Input::setGet('sex', \Input::get('sex')); // Geschlecht bei Toplistenausgabe
			\Input::setGet('age_from', \Input::get('age_from')); // Alter von bei Toplistenausgabe
			\Input::setGet('age_to', \Input::get('age_to')); // Alter bis bei Toplistenausgabe
			\Input::setGet('german', \Input::get('german')); // Nur deutsche Spieler
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		global $objPage;

		// ZPS-Variable holen
		$zps = \Input::get('zps');
		if(!$zps) $zps = '000';
		// Listenvariablen holen und anpassen
		$toplist = \Input::get('toplist');
		if($toplist && $toplist > 1000) $toplist = 1000;
		$sex = \Input::get('sex');
		$age_from = \Input::get('age_from');
		$age_to = \Input::get('age_to');
		$german = \Input::get('german');

		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'DWZ - Verband'; // Standard-Überschrift
		$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
		$this->Template->zps = $zps; // Aktuelle ZPS-Nummer

		/*********************************************************
		 * Ausgabe Verbandszugehörigkeiten (übergeordnete)
		*/

		// Verbände/Vereine laden und Struktur aufbauen
		$result = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste('00000');
		$navigation = new \Schachbulle\ContaoWertungsportalBundle\Helper\Verbandsnavigation($result, $zps);
		$verbaende = $navigation->Verbaende;

		$this->Template->verbaende = $verbaende;

		/*********************************************************
		 * Ausgabe Suchformular, wenn keine Toplistenausgabe angefordert wurde
		*/

		if(!$toplist)
		{
			$this->Template->searchform = true;
			// Formularanzeige: Seitentitel ändern
			$objPage->pageTitle = 'DWZ-Listen '.$verbaende[$zps]['name'];
			$this->Template->subHeadline = 'DWZ-Listen '.$verbaende[$zps]['name']; // Unterüberschrift setzen
		}

		/*********************************************************
		 * Ausgabe Topliste des Verbandes
		*/

		if($zps && $toplist)
		{
			// Abfrageparameter einstellen
			$param = array
			(
				'funktion'    => 'Verbandsliste',
				'cachekey'    => $zps.'-'.$toplist.'-'.$sex.'-'.$age_from.'-'.$age_to.'-'.$german,
				'zps'         => $zps == '000' ? false : rtrim($zps, '0'), // Führt z.B. bei 100 sonst zu 0 Treffern, weil es keine Spieler mit ZPS 100xx in Baden gibt
				'limit'       => $german ? $toplist + 500 : $toplist + 50,
				'alter_von'   => $age_from ? (int)$age_from : '',
				'alter_bis'   => $age_to == 140 ? '' : (int)$age_to,
				'geschlecht'  => $sex == 'f' ? 'FEMALE' : ($sex == 'm' ? 'MALE' : ''),
			);
			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			/*********************************************************
			 * Verbandsname ermitteln
			*/
			$param = array
			(
				'funktion' => 'Vereinsname',
				'cachekey' => $zps.'00',
				'zps'      => $zps.'00'
			);
			$resultVerein = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen
			$verbandsname = $zps == '000' ? 'Deutscher Schachbund' : ($resultVerein['body']['data'] ? $resultVerein['body']['data'][0]['clubName'] : 'Unbekannter Verband');

			/*********************************************************
			 * Rangliste für das Template aufbereiten
			*/

			$rangliste = new \Schachbulle\ContaoWertungsportalBundle\Helper\Verbandsrangliste($resultArr, $zps, $toplist, $german);

			// Seitentitel/Unterüberschrift generieren
			$titel = $verbandsname.' | Top '.$toplist.(($sex == 'm')?' männlich':(($sex == 'f')?' weiblich':'')).(($age_from) ? ' '.$age_from.' - '.$age_to.' Jahre' : (($age_to == 140) ? '' : ' '.$age_from.' - '.$age_to.' Jahre'));
			$objPage->pageTitle = $titel;

			$this->Template = new \FrontendTemplate('wertungsportal_verbandsliste');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
			$this->Template->headline = 'DWZ - Verband'; // Standard-Überschrift
			$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $titel;
			$this->Template->daten = $rangliste->Rangliste;
			$this->Template->fehler = $resultArr['error'] ? $resultArr['error_message'] : false;
			$this->Template->verbaende = $verbaende;
		}
	}

}
