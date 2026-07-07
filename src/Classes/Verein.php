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
 * Ausgabe Vereinssuche / Ausgabe Vereinsliste
 *
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Verein extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_verein';
	protected $subTemplate = 'wertungsportal_sub_vereinsuche';
	
	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL VEREIN ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}
		else
		{
			// FE-Modus: URL mit allen möglichen Parametern auflösen
			\Input::setGet('zps', \Input::get('zps')); // ZPS-Nummer des Vereins
			\Input::setGet('search', \Input::get('search')); // Suchbegriff
			\Input::setGet('order', \Input::get('order')); // Sortierung
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
	
		global $objPage;

		// Vereinsliste angefordert?
		$zps = \Input::get('zps');
		// Vereinssuche aktiv?
		$search = \Input::get('search');
		// Sortierung festlegen
		$order = \Input::get('order');
		$order = ($order == 'alpha') ? 'alpha' : 'rang';

		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'Wertungsportal - Verein'; // Standard-Überschrift
		$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben

		// Auf ungültige Zeichen im Suchbegriff prüfen (alles außer Buchstaben, Zahlen, Umlaute, Leerzeichen ist nicht erlaubt)
		if(!preg_match("#^[a-zA-Z0-9äöüÄÖÜß ]+$#", $search))
		{
			$this->Template->fehler = 'Der Suchbegriff darf nur Buchstaben, Zahlen und Leerzeichen enthalten!';
			$this->Template->search = $search;
			$search = '';
		}
		else
		{
			$this->Template->search = $search;
		}

		if($search)
		{
			// Suchstring modifizieren, Umlaute u.ä. konvertieren
			$search = \System::getContainer()->get('contao.slug')->generate($search, 1);  

			/*********************************************************
			 * Verbands- und Vereinsliste komplett holen
			*/
			$liste = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste();

			/*********************************************************
			 * Verbandsliste durchsuchen, Treffer in Array speichern
			*/
			//print_r($liste['verbaende']);
			$result_vb = array();
			foreach($liste['verbaende'] as $item)
			{
				if(stripos($item['clubName'], $search) !== false)
				{
					$result_vb[] = array
					(
						'zps'       => $item['clubVkz'],
						'name'      => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite().'/%s.html">%s</a>', $item['clubVkz'], $item['clubName']),
					);
				}
			}

			/*********************************************************
			 * Vereinsliste durchsuchen, Treffer in Array speichern
			*/
			$result_vn = array();
			foreach($liste['vereine'] as $item)
			{
				if(stripos($item['clubName'], $search) !== false)
				{
					$result_vn[] = array
					(
						'zps'       => $item['clubVkz'],
						'name'      => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/%s.html">%s</a>', $item['clubVkz'], $item['clubName']),
					);
				}
			}

			/*********************************************************
			 * Seitentitel ändern
			*/

			$objPage->pageTitle = 'Suche nach '.$search;
			$this->Template->subHeadline = 'Suche nach '.$search; // Unterüberschrift setzen

			/*********************************************************
			 * Direkt zum Verein springen, wenn nur 1 Treffer
			*/
			if(count($result_vb) == 0 && count($result_vn) == 1)
			{
				header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/'.$result_vn[0]['zps'].'.html');
			}

			/*********************************************************
			 * Templates füllen
			*/
			//print_r($result_vn);
			$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
			$this->Subtemplate->daten_vb = $result_vb;
			$this->Subtemplate->anzahl_vb = count($result_vb);
			$this->Subtemplate->daten_vn = $result_vn;
			$this->Subtemplate->anzahl_vn = count($result_vn);
			$this->Template->searchresult = $this->Subtemplate->parse();
			
		}
		// Vereinsliste anfordern
		elseif($zps)
		{

			// Abfrageparameter einstellen
			$param = array
			(
				'funktion' => 'Vereinsliste',
				'cachekey' => $zps,
				'zps'      => $zps
			);
			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Sichtbarkeit der Vereinsliste festlegen
			$this->Template->sichtbar = true;

			// Verein in tl_dwz_ver suchen
			$objVerein = \Schachbulle\ContaoWertungsportalBundle\Models\DwzVerModel::findOneBy('zpsver', $zps);
			$this->Template->homepage     = isset($objVerein->homepage) ? $objVerein->homepage : '';
			$this->Template->info         = isset($objVerein->info) ? $objVerein->info : '';

			/*********************************************************
			 * Logo des Vereins
			*/

			if(isset($objVerein->addImage))
			{
				// Vereinslogo vorhanden
				$objFile = \FilesModel::findByPk($objVerein->singleSRC);
			}
			else
			{
				// Standardlogo verwenden
				$objFile = \FilesModel::findByUuid($GLOBALS['TL_CONFIG']['wertungsportal_clubDefaultImage']);
			}

			// Fall-Abfrage eingebaut, weil trotz vorhandenem Bild $objFile = NULL ist
			if($objFile)
			{
				$this->Template->addImage = true;
				// Bild für das Template erstellen (Methode ab Contao 4.10 möglich)
				$figureBuilder = \System::getContainer()->get('contao.image.studio')->createFigureBuilder();
				$figure = $figureBuilder->fromPath($objFile->path)
				                        ->setSize(unserialize($GLOBALS['TL_CONFIG']['wertungsportal_clubImageSize']))
				                        ->enableLightbox(true)
				                        ->disableMetadata(true)
				                        ->build();
				$figure->applyLegacyTemplateData($this->Template);
			}
			else
			{
				$this->Template->addImage = false;
			}

			/*********************************************************
			 * Vereinsname ermitteln
			*/
			$param = array
			(
				'funktion' => 'Vereinsname',
				'cachekey' => $zps,
				'zps'      => $zps
			);
			$resultVerein = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			/*********************************************************
			 * Ausgabe Kopfdaten
			*/
			$vereinsname = (isset($objVerein->altname) && $objVerein->altname != '') ? $objVerein->altname : $resultVerein['body']['data'][0]['clubName'];
			$this->Template->listenlink = ($order == 'alpha') ? sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite()."/%s.html?order=rang\">Rangliste</a>", $zps, $vereinsname) : sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite()."/%s.html?order=alpha\">Alphaliste</a>", $zps, $vereinsname);
			$this->Template->vereinsname = $vereinsname;
			$referent = ''; // Wertungsreferent zuweisen

			/*********************************************************
			 * Ausgabe der Vereinsliste
			*/
			$daten = array();
			$z = 0;
			// Seitentitel ändern
			$objPage->pageTitle = ($order == 'alpha') ? 'DWZ-Vereinsliste '.$vereinsname : 'DWZ-Rangliste '.$vereinsname;
			$this->Template->subHeadline = ($order == 'alpha') ? 'DWZ-Vereinsliste '.$vereinsname : 'DWZ-Rangliste '.$vereinsname; // Unterüberschrift setzen
			foreach($resultArr['body']['data'] as $mitglied)
			{
				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('rating', $mitglied)) $mitglied['rating'] = false;
				if(!array_key_exists('index', $mitglied)) $mitglied['index'] = false;

				// Schlüssel für Sortierung generieren
				$z++;
				$key = ($order == 'alpha') ? \StringUtil::generateAlias($mitglied['lastname'].$mitglied['firstname'].$z) : sprintf('%05d-%04d-%s-%03d', 10000 - $mitglied['rating'], 1000 - $mitglied['index'], ('') ? '' : 'Z', $z);
				// Daten zuweisen
				$daten[$key] = array
				(
					'PKZ'         => $mitglied['nuLigaPersonId'],
					'Mglnr'       => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsnummer($mitglied, $zps),
					'Status'      => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitgliedsstatus($mitglied, $zps),
					'Spielername' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($mitglied),
					'Geschlecht'  => $mitglied['gender'] == 'MALE' ? 'M' : ($mitglied['gender'] == 'FEMALE' ? 'W' : strtoupper($mitglied['gender'])),
					'KW'          => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($mitglied),
					'DWZ'         => $mitglied['rating'].' - '.$mitglied['index'],
					'Elo'         => isset($mitglied['fideElo']) ? $mitglied['fideElo'] : '',
					'FIDE-Titel'  => isset($mitglied['fideTitle']) ? $mitglied['fideTitle'] : '',
				);
			}

			// Liste sortieren (ASC)
			ksort($daten);
			// Platzierung hinzufügen
			if($order == 'rang')
			{
				$this->Template->rangliste = true;
				$z = 1;
				foreach($daten as $key => $value)
				{
					$daten[$key]['Platz'] = $z;
					$z++;
				}
			}
			$this->Template->daten = $daten;
			
			// Untertemplate initialisieren und füllen
			$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
			$this->Subtemplate->daten = isset($daten) ? $daten : false;
			//$this->Subtemplate->debugausgabe = isset($resultVerein) ? $resultVerein : false;
			$this->Subtemplate->anzahl = isset($daten) ? count($daten) : 0;
			$this->Template->searchresult = $this->Subtemplate->parse();
			$this->Template->searchform = true;


		}

	}

}
