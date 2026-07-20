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
 * Ausgabe Spielersuche / Ausgabe Spielerkarteikarte
 *
 * Die Aufbereitung der API-Daten für die Templates übernehmen die
 * Helper-Klassen Spielersuche und Karteikarte.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Spieler extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_spieler';
	protected $subTemplate = 'wertungsportal_sub_spielersuche';

	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL SPIELER ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}
		else
		{
			// FE-Modus: URL mit allen möglichen Parametern auflösen
			\Input::setGet('id', \Input::get('id')); // ID
			\Input::setGet('search', \Input::get('search')); // Suchbegriff
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		global $objPage;

		$id = \Input::get('id'); // Spielerkartei angefordert?
		$search = \Input::get('search'); // Spielersuche aktiv?

		// Template vorbelegen
		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'Wertungsportal - Spieler'; // Standard-Überschrift
		$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben

		$mitglied = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitglied(); // Daten des aktuellen Mitgliedes laden

		// Sperrstatus festlegen
		if($GLOBALS['TL_CONFIG']['wertungsportal_karteisperre_gaeste']) $gesperrt = $mitglied->id ? false : true;
		else $gesperrt = false;

		/*********************************************************
		 * Ergebnisse einer Suche ausgeben
		*/
		if($search)
		{
			// Template anpassen
			$this->Template->subHeadline = 'Suche nach '.$search; // Unterüberschrift setzen
			$this->Template->search = $search;

			// Suchbegriff analysieren – auf dem Rohstring, damit das Komma zwischen
			// Nachname und Vorname erhalten bleibt (contao.slug würde es sonst durch
			// "-" ersetzen und die Trennung zerstören)
			$check_search = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::checkSearchstringPlayer($search); // Suchbegriff analysieren

			// Abfrageparameter einstellen
			if($check_search['typ'] == 'name')
			{
				// Umlaute u.ä. erst jetzt auf den einzelnen Namensteilen konvertieren
				$slug = \System::getContainer()->get('contao.slug');
				$nachname = $check_search['nachname'] !== '' ? $slug->generate($check_search['nachname'], 1) : '';
				$vorname  = $check_search['vorname']  !== '' ? $slug->generate($check_search['vorname'], 1)  : '';

				// Spielersuche
				$param = array
				(
					'funktion'  => 'Spielerliste',
					'cachekey'  => trim($nachname.'-'.$vorname, '-'),
					'vorname'   => $vorname,
					'nachname'  => $nachname,
				);
			}
			else
			{
				// PKZ- oder ZPS-Eingabe: wird vom Wertungsportal nicht unterstützt —
				// Hinweis ausgeben statt mit undefiniertem $param abzustürzen
				$this->Template->fehler = 'Die Suche nach Mitglieds- oder ZPS-Nummern wird nicht unterstützt. Bitte einen Namen eingeben (Name,Vorname oder nur Name).';
			}

			if(isset($param))
			{
				$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

				// Trefferliste für das Template aufbereiten
				$suche = new \Schachbulle\ContaoWertungsportalBundle\Helper\Spielersuche($resultArr);
				$daten = $suche->Spielerliste;
			}
		}

		/*********************************************************
		 * Kartei angefordert, wenn ID im Format "NU12345"
		*/
		if($id && preg_match('/^NU\d+$/', $id))
		{
			$param = array
			(
				'funktion' => 'Karteikarte',
				'cachekey' => $id,
				'id'       => $id
			);
			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// API-Fehler im Template ausgeben statt mit ungültigen Daten abzustürzen
			if(!is_array($resultArr) || $resultArr['error'] || $resultArr['http_code'] != 200 || !is_array($resultArr['body']))
			{
				$meldung = '';
				if(isset($resultArr['body']) && is_string($resultArr['body']) && $resultArr['body'] != '') $meldung = $resultArr['body'];
				elseif(!empty($resultArr['error_message'])) $meldung = $resultArr['error_message'];
				$http = (is_array($resultArr) && isset($resultArr['http_code'])) ? $resultArr['http_code'] : 0;

				$this->Template->fehler = 'Die Wertungsportal-API meldet einen Fehler (HTTP-Code '.$http.')'.($meldung ? ': '.$meldung : '');
			}
			elseif(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istGeblockt($id))
			{
				// Blacklist: Karteikarte gesperrter Personen nicht anzeigen
				$this->Template->fehler = 'Diese Karteikarte ist nicht verfügbar.';
			}
			else
			{
				// Turnierauswertungen laden
				$param = array
				(
					'funktion' => 'Karteikarte_Turniere',
					'cachekey' => $id,
					'id'       => $id
				);
				$resultTur = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

				// Karteikarte für das Template aufbereiten
				$kartei = new \Schachbulle\ContaoWertungsportalBundle\Helper\Karteikarte($resultArr, $resultTur);

				// Seitentitel ändern
				$objPage->pageTitle = 'DWZ-Karteikarte '.$kartei->Titelname;
				$this->Template->subHeadline = 'DWZ-Karteikarte '.$kartei->Titelname; // Unterüberschrift setzen

				// Sichtbarkeit der Karteikarte festlegen
				$this->Template->sichtbar = $gesperrt ? false : true;
				$this->Template->sperre = $gesperrt;

				/*********************************************************
				 * Spielerfoto
				*/

				// Zuerst das eigene Bild der Person aus WP | Personen verwenden;
				// Fallback: tl_dwz_spi über die externe Nummer der Person
				// (dewisID = externeNr — die frühere Suche mit der nu-ID gegen
				// die dewisID konnte praktisch nie treffen)
				$objFile = null;
				$objPerson = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::findOneBy('nuLigaPersonId', $kartei->NuId);

				if($objPerson && $objPerson->addImage && $objPerson->singleSRC !== null)
				{
					// Spielerbild aus WP | Personen
					$objFile = \FilesModel::findByPk($objPerson->singleSRC);
				}
				elseif($objPerson && $objPerson->externeNr != '')
				{
					// Spieler in tl_dwz_spi über die externe Nummer suchen
					$objSpieler = \Schachbulle\ContaoWertungsportalBundle\Models\DwzSpiModel::findOneBy('dewisID', $objPerson->externeNr);

					if($objSpieler && $objSpieler->addImage)
					{
						$objFile = \FilesModel::findByPk($objSpieler->singleSRC);
					}
				}

				if(!$objFile)
				{
					// Standardbild verwenden
					$objFile = \FilesModel::findByUuid($GLOBALS['TL_CONFIG']['wertungsportal_playerDefaultImage']);
				}

				// Fall-Abfrage eingebaut, weil trotz vorhandenem Bild $objFile = NULL sein kann
				if($objFile)
				{
					$this->Template->addImage = true;
					// Bild für das Template erstellen (Methode ab Contao 4.10 möglich)
					$figureBuilder = \System::getContainer()->get('contao.image.studio')->createFigureBuilder();
					$figure = $figureBuilder->fromPath($objFile->path)
					                        ->setSize(unserialize($GLOBALS['TL_CONFIG']['wertungsportal_playerImageSize']))
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
				 * Ausgabe Kopfdaten, Vereine und Turnierauswertungen
				*/

				$this->Template->sichtbar     = true;
				$this->Template->spielername  = $kartei->Spielername;
				$this->Template->geburtsjahr  = $kartei->Geburtsjahr;
				$this->Template->geschlecht   = $kartei->Geschlecht;
				$this->Template->nu_id        = $kartei->NuId;
				$this->Template->dwz          = $kartei->DWZ;
				$this->Template->fide_id      = $kartei->FideId;
				$this->Template->elo          = $kartei->Elo;
				$this->Template->fide_titel   = $kartei->FideTitel;
				$this->Template->fide_nation  = $kartei->FideNation;
				$this->Template->historie     = $kartei->Historie;
				$this->Template->vereine      = $kartei->Vereine;
				$this->Template->referent     = $kartei->Referent;
				$this->Template->kartei       = $kartei->Kartei;
				$this->Template->diagramm     = $kartei->Diagramm;
				$this->Template->diagrammKomplett = $kartei->DiagrammKomplett;
			}
		}

		// Untertemplate initialisieren und füllen
		$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
		$this->Subtemplate->daten = isset($daten) ? $daten : false;
		$this->Subtemplate->anzahl = isset($daten) ? count($daten) : 0;
		$this->Template->searchresult = $this->Subtemplate->parse();
		$this->Template->searchform = true;
	}

}
