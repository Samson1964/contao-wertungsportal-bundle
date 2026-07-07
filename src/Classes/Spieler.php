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

		// Blacklist laden
		$Blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Blacklist();

		$id = \Input::get('id'); // Spielerkartei angefordert?
		$search = \Input::get('search'); // Spielersuche aktiv?

		// Template vorbelegen
		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->headline = 'Wertungsportal - Spieler'; // Standard-Überschrift
		$this->Template->navigation   = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben

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

			// Suchstring modifizieren, Umlaute u.ä. konvertieren
			$search = \System::getContainer()->get('contao.slug')->generate($search, 1);
			$check_search = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::checkSearchstringPlayer($search); // Suchbegriff analysieren

			// Abfrageparameter einstellen
			if($check_search['typ'] == 'name')
			{
				// Spielersuche
				$param = array
				(
					'funktion'  => 'Spielerliste',
					'cachekey'  => $search,
					'vorname'   => $check_search['vorname'],
					'nachname'  => $check_search['nachname'],
				);
			}

			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			//echo "<pre>";
			//print_r($resultArr);
			//echo "</pre>";

			/*********************************************************
			 * Spielerliste entsprechend Suchname erstellen
			*/
			$daten = array();
			if(isset($resultArr['body']['data']))
			{
				// Daten konvertieren für Ausgabe
				foreach($resultArr['body']['data'] as $person)
				{
					// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
					if(!array_key_exists('rating', $person)) $person['rating'] = false;
					if(!array_key_exists('index', $person)) $person['index'] = false;

					// Aktiv-Status suchen in Mitgliedschaften
					$verein = '';
					if(isset($person['memberships']))
					{
						foreach($person['memberships'] as $mitglied)
						{
							$verein = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);
							if($mitglied['licenceState'] == 'ACTIVE') break; // Abbruch wenn A-Status gefunden
						}
					}

					// Daten schreiben
					$daten[] = array
					(
						'PKZ'             => 'x',
						'Verein'          => $verein,
						'Spielername'     => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($person),
						'Spielername_RAW' => $person['lastname'].' '.$person['firstname'],
						'KW'              => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($person),
						'DWZ'             => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($person['rating'], $person['index']),
						'Elo'             => isset($person['fideElo']) ? $person['fideElo'] : '',
						'Titel'           => isset($person['fideTitle']) ? $person['fideTitle'] : '',
					);
				}
				// Sortierung nach Spielername (A → Z), Umlaute-sicher
				setlocale(LC_COLLATE, 'de_DE.UTF-8');
				usort($daten, function($a, $b) {
					return strcoll($a['Spielername_RAW'], $b['Spielername_RAW']);
				});
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

			// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
			if(!array_key_exists('rating', $resultArr['body'])) $resultArr['body']['rating'] = false;
			if(!array_key_exists('index', $resultArr['body'])) $resultArr['body']['index'] = false;

			if(!$resultArr) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::get404(); // ID nicht gefunden

			// Seitentitel ändern
			$objPage->pageTitle = 'DWZ-Karteikarte '.$resultArr['body']['firstname'].' '.$resultArr['body']['lastname'];
			$this->Template->subHeadline = 'DWZ-Karteikarte '.$resultArr['body']['firstname'].' '.$resultArr['body']['lastname']; // Unterüberschrift setzen

			// Sichtbarkeit der Karteikarte festlegen
			$this->Template->sichtbar = $gesperrt ? false : true;
			$this->Template->sperre = $gesperrt;

			/*********************************************************
			 * Spielerfoto
			*/

			// Spieler in tl_dwz_spi suchen
			$objSpieler = \Schachbulle\ContaoWertungsportalBundle\Models\DwzSpiModel::findOneBy('dewisID', $resultArr['body']['nuLigaPersonId']);
			$this->Template->addImage     = true;

			if(isset($objSpieler->addImage))
			{
				// Spielerfoto vorhanden
				$objFile = \FilesModel::findByPk($objSpieler->singleSRC);
			}
			else
			{
				// Standardbild verwenden
				$objFile = \FilesModel::findByUuid($GLOBALS['TL_CONFIG']['wertungsportal_playerDefaultImage']);
			}

			// Bild für das Template erstellen (Methode ab Contao 4.10 möglich)
			$figureBuilder = \System::getContainer()->get('contao.image.studio')->createFigureBuilder();
			$figure = $figureBuilder->fromPath($objFile->path)
			                        ->setSize(unserialize($GLOBALS['TL_CONFIG']['wertungsportal_playerImageSize']))
			                        ->enableLightbox(true)
			                        ->disableMetadata(true)
			                        ->build();
			$figure->applyLegacyTemplateData($this->Template);

			/*********************************************************
			 * Ausgabe Kopfdaten
			*/

			$this->Template->sichtbar     = true;
			$this->Template->spielername  = sprintf("%s,%s", $resultArr['body']['lastname'], $resultArr['body']['firstname']);
			$this->Template->geburtsjahr  = $GLOBALS['TL_CONFIG']['wertungsportal_geburtsjahr_ausblenden'] ? '****' : $resultArr['body']['birthyear'];
			$this->Template->geschlecht   = $GLOBALS['TL_CONFIG']['wertungsportal_geschlecht_ausblenden'] ? '*' : ($resultArr['body']['gender'] == 'MALE' ? 'M' : ($resultArr['body']['gender'] == 'FEMALE' ? 'W' : strtoupper($resultArr['body']['gender'])));
			$this->Template->nu_id        = $resultArr['body']['nuLigaPersonId'];
			$this->Template->dwz          = $resultArr['body']['rating'].' - '.$resultArr['body']['index'];
			$this->Template->fide_id      = isset($resultArr['body']['fideId']) ? sprintf('<a href="https://ratings.fide.com/profile/%s" target="_blank">%s</a>',$resultArr['body']['fideId'], $resultArr['body']['fideId']) : '-';
			$this->Template->elo          = isset($resultArr['body']['fideElo']) ? $resultArr['body']['fideElo'] : '';
			$this->Template->fide_titel   = isset($resultArr['body']['fideTitle']) ? $resultArr['body']['fideTitle'] : '';;
			$this->Template->fide_nation  = isset($resultArr['body']['fideNation']) ? $resultArr['body']['fideNation'] : '';;
			$this->Template->historie     = '-';

			/*********************************************************
			 * Ausgabe Vereinsdaten
	 		*/

			$referent = '';
			$zps = '';
			$sortiert = array();
			if($resultArr['body']['memberships'])
			{
				foreach($resultArr['body']['memberships'] as $mitglied)
				{
					$status                    = substr($mitglied['licenceState'], 0, 1);
					$zps_nr                    = sprintf("%s-%04d", $mitglied['vkz'], $mitglied['memberNo']);
					$verein                    = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);

					$sortiert[$status.$zps_nr] = array
					(
						'name'   => $verein,
						'zps'    => $zps_nr,
						'status' => $status
					);
				}
			}
			ksort($sortiert);
			$this->Template->vereine      = $sortiert;

			/*********************************************************
			 * Ausgabe zuständiger Wertungsreferent
			*/

			$this->Template->referent = '-';

			/*********************************************************
			 * Ausgabe Turnierauswertungen
			*/
			$param = array
			(
				'funktion' => 'Karteikarte_Turniere',
				'cachekey' => $id,
				'id'       => $id
			);
			$resultTur = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			$kartei = array();
			if($resultTur['error'])
			{
				// Ein Fehler ist aufgetreten
			}
			else
			{
				$i = count($resultTur['body']['entries']) + 1;
				foreach($resultTur['body']['entries'] as $turnier)
				{
					$i--;

					// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
					if(!array_key_exists('winsExpected', $turnier['player'])) $turnier['player']['winsExpected'] = false;
					if(!array_key_exists('factorK', $turnier['player'])) $turnier['player']['factorK'] = false;
					if(!array_key_exists('tournamentPerformance', $turnier['player'])) $turnier['player']['tournamentPerformance'] = false;
					if(!array_key_exists('ratingOld', $turnier['player'])) $turnier['player']['ratingOld'] = false;
					if(!array_key_exists('indexOld', $turnier['player'])) $turnier['player']['indexOld'] = false;
					if(!array_key_exists('ratingNew', $turnier['player'])) $turnier['player']['ratingNew'] = false;
					if(!array_key_exists('indexNew', $turnier['player'])) $turnier['player']['indexNew'] = false;
					if(!array_key_exists('averageRatingCompetitors', $turnier['player'])) $turnier['player']['averageRatingCompetitors'] = false;

					$dwz_alt = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($turnier['player']['ratingOld'], $turnier['player']['indexOld']);
					$dwz_neu = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($turnier['player']['ratingNew'], $turnier['player']['indexNew']);

					$kartei[] = array
					(
						'nummer'     => ($i == count($resultTur['body']['entries']) && $dwz_neu != '&nbsp;') ? 'AKT' : $i,
						'jahr'       => substr($turnier['tournament']['enddate'], 0, 4),
						'turnier'    => sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite()."/%s/%s.html\" title=\"%s\">%s</a>", $turnier['tournament']['uuid'], $turnier['player']['playerUuid'], $turnier['tournament']['label'], \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Turnierkurzname($turnier['tournament']['label'])),
						'punkte'     => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($turnier['player']['wins']),
						'partien'    => $turnier['player']['numberOfGames'],
						'we'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($turnier['player']['winsExpected']),
						'e'          => $turnier['player']['factorK'],
						'gegner'     => $turnier['player']['averageRatingCompetitors'] ? $turnier['player']['averageRatingCompetitors'] : '',
						'leistung'   => $turnier['player']['tournamentPerformance'] ? $turnier['player']['tournamentPerformance'] : '',
						'dwz-neu'    => $dwz_neu,
						'ungewertet' => '',
					);
				}
			}
			$this->Template->kartei = $kartei;

		}

		// Untertemplate initialisieren und füllen
		$this->Subtemplate = new \FrontendTemplate($this->subTemplate);
		$this->Subtemplate->daten = isset($daten) ? $daten : false;
		//$this->Subtemplate->debugausgabe = isset($resultTur) ? $resultTur : false;
		$this->Subtemplate->anzahl = isset($daten) ? count($daten) : 0;
		$this->Template->searchresult = $this->Subtemplate->parse();
		$this->Template->searchform = true;


	}

}
