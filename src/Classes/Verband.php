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
 * Ausgabe Verbandssuche / Ausgabe Verbandsliste
 *
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

		// Blacklist laden
		$Blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Blacklist();

		// ZPS-Variable holen
		$zps = \Input::get('zps');
		if(!$zps) $zps = '000';
		// Listenvariablen holen und anpassen
		$toplist = \Input::get('toplist');
		if($toplist && $toplist > 950) $toplist = 950;
		$sex = \Input::get('sex'); 
		$age_from = \Input::get('age_from'); 
		$age_to = \Input::get('age_to'); 
		$german = \Input::get('german'); 
		
		$mitglied = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitglied(); // Daten des aktuellen Mitgliedes laden
		
		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'DWZ - Verband'; // Standard-Überschrift
		$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
		$this->Template->zps = $zps; // Aktuelle ZPS-Nummer

		// Sperrstatus festlegen
		if($GLOBALS['TL_CONFIG']['wertungsportal_karteisperre_gaeste']) $gesperrt = $mitglied->id ? false : true;
		else $gesperrt = false;

		/*********************************************************
		 * Ausgabe Verbandszugehörigkeiten (übergeordnete)
		*/

		// Verbände/Vereine laden
		$result = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste('00000');

		//$log = "API Verbandsliste:\n".print_r($result, true);
		//log_message($log, 'wertungsportal_oauth2client.log');

		// Verbände entsprechend ZPS laden und strukturieren, DSB zuerst anlegen
		$verbaende = array();
		$verbaende['000'] = array
		(
			'vkz'   => '000',
			'name'  => 'Deutscher Schachbund',
			'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite().'/%s.html">%s</a>', '000', 'Deutscher Schachbund'),
			'ebene' => 'level_0'
		);
		foreach($result['verbaende'] as $verband)
		{
			// Landes- oder Mitgliedsverband des DSB zuweisen (Ebene 1), VKZ = ?00??
			if(substr($verband['clubVkz'], 1, 2) == '00')
			{
				$verbaende[substr($verband['clubVkz'],0,3)] = array
				(
					'vkz'   => substr($verband['clubVkz'],0,3),
					'name'  => $verband['clubName'],
					'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite().'/%s.html">%s</a>', substr($verband['clubVkz'],0,3), $verband['clubName']),
					'ebene' => 'level_1'
				);
			}
			// Bezirk zuweisen, wenn 1. Stelle VKZ mit Such-ZPS übereinstimmt
			elseif(substr($zps,0,1) == substr($verband['clubVkz'],0,1) && substr($verband['clubVkz'],2,1) == '0')
			{
				$verbaende[substr($verband['clubVkz'],0,3)] = array
				(
					'vkz'   => substr($verband['clubVkz'],0,3),
					'name'  => $verband['clubName'],
					'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite().'/%s.html">%s</a>', substr($verband['clubVkz'],0,3), $verband['clubName']),
					'ebene' => 'level_2'
				);
			}
			// Kreis zuweisen, wenn 1.+2. Stelle VKZ mit Such-ZPS übereinstimmt
			elseif(substr($zps,0,2) == substr($verband['clubVkz'],0,2))
			{
				$verbaende[substr($verband['clubVkz'],0,3)] = array
				(
					'vkz'   => substr($verband['clubVkz'],0,3),
					'name'  => $verband['clubName'],
					'url'   => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite().'/%s.html">%s</a>', substr($verband['clubVkz'],0,3), $verband['clubName']),
					'ebene' => 'level_3'
				);
			}
		}

		// Vereine-Link ergänzen bei aktuell angeforderten Verband
		if($zps != '000')
		{
			$verbaende[$zps]['url'] = '<b>'.$verbaende[$zps]['url'].sprintf(' - <a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/%s.html">Vereine</a>', $zps).'</b>';
		}
		
		//print_r($verbaende);
		$this->Template->verbaende    = $verbaende;

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
				'zps'         => $zps == '000' ? false : $zps,
				'limit'       => $german ? $toplist + 500 : $toplist + 50,
				'alter_von'   => $age_from,
				'alter_bis'   => $age_to,
				'geschlecht'  => $sex,
			);

			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			/*********************************************************
			 * Ausgabe der Verbandsrangliste
			*/

			$rangliste = [];
			$platz = 0;
			if(!$resultArr['error'])
			{
				foreach($resultArr['body']['data'] as $spieler)
				{
					// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
					if(!array_key_exists('fideId', $spieler)) $spieler['fideId'] = false;
            	
					// FIDE-Daten aus Tabelle tl_dwz_elo holen
					$fide = \Schachbulle\ContaoWertungsportalBundle\Helper\API::getFIDE($spieler['fideId']); // Abfrage ausführen
					$fide_nation = $fide['land'];
					$fide_elo = $fide['elo'];
					$fide_titel = $fide['titel'];
            	
					$flag_css = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Laendercode($fide_nation);
					// Flagge anzeigen, wenn vorhanden
					if($flag_css)
						$flag_content = '<span class="'.$flag_css.'" title="'.$fide_nation.'"></span>';
					else
						$flag_content = '<span class="ioc_code" title="'.$fide_nation.'">'.$fide_nation.'</span>';
            	
					// Daten zuweisen
					$platz++;
					$rangliste[] = array
					(
						'Platz'       => $platz,
						'PKZ'         => $spieler['nuLigaPersonId'],
						'Mglnr'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsnummer($spieler, $zps),
						'Status'      => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsstatus($spieler, $zps),
						'Spielername' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($spieler),
						'Geschlecht'  => $spieler['gender'] == 'MALE' ? 'M' : ($spieler['gender'] == 'FEMALE' ? 'W' : strtoupper($spieler['gender'])),
						'KW'          => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($spieler),
						'DWZ'         => $spieler['rating'].' - '.$spieler['index'],
						'Elo'         => $fide_elo,
						'FIDE-Titel'  => $fide_titel,
						'FIDE-Nation' => $flag_content,
						'Verein'      => '',
					);
				}
			}

			// Seitentitel/Unterüberschrift generieren
			
			$titel = '{Verbandsname}'.' Top '.$toplist.(($sex == 'm')?' männlich':(($sex == 'f')?' weiblich':'')).(($age_from) ? ' '.$age_from.' - '.$age_to.' Jahre' : (($age_to == 140) ? '' : ' '.$age_from.' - '.$age_to.' Jahre'));
			$objPage->pageTitle = $titel;

			$this->Template = new \FrontendTemplate('wertungsportal_verbandsliste');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
			$this->Template->headline = 'DWZ - Verband'; // Standard-Überschrift
			$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
			$this->Template->subHeadline = $titel;
			$this->Template->daten = $rangliste;
			$this->Template->fehler = $resultArr['error'] ? $resultArr['error_message'] : false;
			$this->Template->verbaende    = $verbaende;
		}

	}

}