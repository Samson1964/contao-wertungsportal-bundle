<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Verein
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * Wertungsportal-Abfrage:
 * Ausgabe Vereinssuche / Ausgabe Vereinsliste
 *
 * Die Aufbereitung der API-Daten für die Templates übernehmen die
 * Helper-Klassen Vereinssuche und Vereinsliste.
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
		$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben
		$this->Template->search = $search;

		// Auf ungültige Zeichen im Suchbegriff prüfen (alles außer Buchstaben,
		// Zahlen, Umlauten und Leerzeichen ist nicht erlaubt) — nur prüfen,
		// wenn überhaupt ein Suchbegriff eingegeben wurde
		if($search && !preg_match("#^[a-zA-Z0-9äöüÄÖÜß ]+$#", $search))
		{
			$this->Template->fehler = 'Der Suchbegriff darf nur Buchstaben, Zahlen und Leerzeichen enthalten!';
			$search = '';
		}

		if($search)
		{
			/*********************************************************
			 * Vereinssuche
			*/

			// Suchstring modifizieren, Umlaute u.ä. konvertieren
			$search = \System::getContainer()->get('contao.slug')->generate($search, 1);

			// Verbands- und Vereinsliste komplett holen und durchsuchen
			$liste = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste();
			$suche = new \Schachbulle\ContaoWertungsportalBundle\Helper\Vereinssuche($liste, $search);

			// Seitentitel ändern
			$objPage->pageTitle = 'Suche nach '.$search;
			$this->Template->subHeadline = 'Suche nach '.$search; // Unterüberschrift setzen

			// Direkt zum Verein springen, wenn nur 1 Treffer
			if(count($suche->Verbaende) == 0 && count($suche->Vereine) == 1)
			{
				header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/'.$suche->Vereine[0]['zps'].'.html');
			}

			// Templates füllen
			$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
			$this->Subtemplate->daten_vb = $suche->Verbaende;
			$this->Subtemplate->anzahl_vb = count($suche->Verbaende);
			$this->Subtemplate->daten_vn = $suche->Vereine;
			$this->Subtemplate->anzahl_vn = count($suche->Vereine);
			$this->Template->searchresult = $this->Subtemplate->parse();
		}
		// Vereinsliste anfordern
		elseif($zps)
		{
			/*********************************************************
			 * Vereinsliste (Rang- oder Alphaliste)
			*/

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

			// Vereinsdaten (Logo, Homepage, Info, Alternativname): zuerst aus
			// tl_wertungsportal_clubs, Fallback auf die Alttabelle tl_dwz_ver
			$objClub = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::findByVkz($zps);
			$objVerein = \Schachbulle\ContaoWertungsportalBundle\Models\DwzVerModel::findOneBy('zpsver', $zps);

			if($objClub && $objClub->homepage != '') $this->Template->homepage = $objClub->homepage;
			else $this->Template->homepage = isset($objVerein->homepage) ? $objVerein->homepage : '';

			if($objClub && $objClub->info != '') $this->Template->info = $objClub->info;
			else $this->Template->info = isset($objVerein->info) ? $objVerein->info : '';

			/*********************************************************
			 * Logo des Vereins
			*/

			if($objClub && $objClub->addImage && $objClub->singleSRC !== null)
			{
				// Vereinslogo aus WP | Vereine
				$objFile = \FilesModel::findByPk($objClub->singleSRC);
			}
			elseif(isset($objVerein->addImage))
			{
				// Vereinslogo aus der Alttabelle vorhanden
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
			 * Ausgabe Kopfdaten und Mitgliederliste
			*/
			if($objClub && $objClub->altname != '') $vereinsname = $objClub->altname;
			elseif(isset($objVerein->altname) && $objVerein->altname != '') $vereinsname = $objVerein->altname;
			else $vereinsname = $resultVerein['body']['data'][0]['clubName'];
			$this->Template->listenlink = ($order == 'alpha') ? sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html?order=rang\">Rangliste</a>", $zps) : sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html?order=alpha\">Alphaliste</a>", $zps);
			$this->Template->vereinsname = $vereinsname;

			// Seitentitel ändern
			$titel = ($order == 'alpha') ? 'DWZ-Vereinsliste '.$vereinsname : 'DWZ-Rangliste '.$vereinsname;
			$objPage->pageTitle = $titel;
			$this->Template->subHeadline = $titel; // Unterüberschrift setzen

			// Mitgliederliste für das Template aufbereiten
			$vereinsliste = new \Schachbulle\ContaoWertungsportalBundle\Helper\Vereinsliste($resultArr, $zps, $order);
			$this->Template->rangliste = $vereinsliste->Rangliste;
			$this->Template->daten = $vereinsliste->Daten;
			$this->Template->referent = ''; // Wertungsreferent zuweisen

			// Untertemplate initialisieren und füllen
			$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
			$this->Subtemplate->daten = $vereinsliste->Daten;
			$this->Subtemplate->anzahl = $vereinsliste->Anzahl;
			$this->Template->searchresult = $this->Subtemplate->parse();
			$this->Template->searchform = true;
		}
	}

}
