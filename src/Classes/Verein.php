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

		// Prüfen, ob der Suchbegriff überhaupt etwas Suchbares enthält.
		//
		// Hier stand bis 1.29.1 eine Liste erlaubter Zeichen
		// (`[a-zA-Z0-9äöüÄÖÜß ]`). Die wies aber 966 von 2401 Vereinsnamen ab:
		// Punkt (e.V.), Bindestrich (Baden-Baden), Schrägstrich
		// (Ludwigshafen/Rhein), Komma, Kaufmanns-Und. Gemeldet wurde es für
		// „baden-baden" — betroffen waren 40 % des Bestands.
		//
		// Geschützt hat die Liste dabei nichts: Contao wandelt `< > " ' ( ) = \ #`
		// bereits in `Input::get()` in Entities um, und verglichen wird ohnehin
		// über `Helper::alias()`, dessen Ausgabe nur `[a-z0-9-]` enthält. Ein
		// Angriffsversuch wird damit zu einem harmlosen Suchbegriff, der
		// schlicht nichts findet.
		//
		// Sinnvoll bleibt genau eine Frage: Ist nach der Umwandlung noch etwas
		// übrig? Bei „---" oder „..." ist es das nicht, und statt den ganzen
		// Bestand auszugeben, gehört dann ein Hinweis her.
		if($search && \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::alias($search) === '-')
		{
			$this->Template->fehler = 'Der Suchbegriff enthält keine verwertbaren Zeichen.';
			$search = '';
		}

		if($search)
		{
			/*********************************************************
			 * Vereinssuche
			*/

			// Suchbegriff NICHT sluggen: Die Suche läuft lokal per mb_stripos
			// über die echten Vereinsnamen — ein Slug (königsspringer →
			// koenigsspringer) machte Namen mit Umlauten unauffindbar

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
		// Verbands-VKZ: keine Mitgliederliste, sondern die Vereine darunter
		elseif($zps && \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istVerband($zps))
		{
			/*********************************************************
			 * Vereine eines Verbandes
			 *
			 * Der Parameter vkz der Schnittstelle ist eine Präfixsuche: „300"
			 * liefert die Mitglieder ALLER Berliner Vereine, „100" gar keine
			 * (der Verband selbst hat keine). Beides ergab bisher eine
			 * irreführende Seite — Mitglieder unter dem Namen des ersten
			 * Treffers beziehungsweise eine leere Rangliste
			*/

			$liste = \Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste();
			$vereine = new \Schachbulle\ContaoWertungsportalBundle\Helper\Verbandsvereine($liste, $zps);

			$this->Template->sichtbar = false; // keine Mitgliederliste anzeigen

			if(!$vereine->Gefunden)
			{
				$titel = 'Unbekannte Kennziffer '.$zps;
				$this->Template->fehler = 'Zu der Kennziffer '.$zps.' ist kein Verband bekannt.';
			}
			else
			{
				$name = $vereine->Name !== '' ? $vereine->Name : 'Verband '.$zps;
				$titel = 'Vereine im Verband '.$name;

				$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
				$this->Subtemplate->daten_vb = $vereine->Verbaende;
				$this->Subtemplate->anzahl_vb = count($vereine->Verbaende);
				$this->Subtemplate->daten_vn = $vereine->Vereine;
				$this->Subtemplate->anzahl_vn = count($vereine->Vereine);
				$this->Template->searchresult = $this->Subtemplate->parse();
			}

			$objPage->pageTitle = $titel;
			$this->Template->subHeadline = $titel;
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

			// Vereinsdaten (Logo, Homepage, Info, Alternativname) aus
			// tl_wertungsportal_clubs. Der frühere Rückgriff auf die Alttabelle
			// tl_dwz_ver ist entfallen: Sie gehört dem abgelösten DeWIS-Bundle,
			// fehlt in jeder Installation ohne dieses und ließ die Vereinsseite
			// dort mit einem SQL-Fehler abbrechen. Bestandsdaten holt man
			// einmalig über „Altdaten übernehmen" im Backend-Modul Vereine herüber
			$objClub = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::findByVkz($zps);

			$this->Template->homepage = ($objClub && $objClub->homepage != '') ? $objClub->homepage : '';
			$this->Template->info = ($objClub && $objClub->info != '') ? $objClub->info : '';

			/*********************************************************
			 * Logo des Vereins
			*/

			$objFile = null;

			if($objClub && $objClub->addImage && $objClub->singleSRC !== null)
			{
				// Vereinslogo aus dem Backend-Modul Vereine
				$objFile = \FilesModel::findByPk($objClub->singleSRC);
			}
			elseif(!empty($GLOBALS['TL_CONFIG']['wertungsportal_clubDefaultImage']))
			{
				// In den Einstellungen hinterlegtes Standardlogo
				$objFile = \FilesModel::findByUuid($GLOBALS['TL_CONFIG']['wertungsportal_clubDefaultImage']);
			}

			// Fall-Abfrage eingebaut, weil trotz vorhandenem Bild $objFile = NULL ist
			if($objFile)
			{
				$this->Template->addImage = true;
				// Bild für das Template erstellen (Methode ab Contao 4.10 möglich)
				$figureBuilder = \System::getContainer()->get('contao.image.studio')->createFigureBuilder();
				$figure = $figureBuilder->fromPath($objFile->path)
				                        ->setSize(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::bildgroesse('wertungsportal_clubImageSize'))
				                        ->enableLightbox(true)
				                        ->disableMetadata(true)
				                        ->build();
				$figure->applyLegacyTemplateData($this->Template);
			}
			else
			{
				// Weder ein eigenes Logo noch ein Standardlogo: das mitgelieferte
				// SVG zeigen. Es liegt im Bundle und nicht in der Dateiverwaltung,
				// läuft deshalb an der Bilderzeugung vorbei und wird im Template
				// unmittelbar eingebunden
				$this->Template->addImage = true;
				$this->Template->platzhalter = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::platzhalterbild('verein');
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
