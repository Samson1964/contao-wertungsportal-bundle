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

class Spieler extends \Contao\Module
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
		if (\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istBackend())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL SPIELER ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}
		else
		{
			// FE-Modus: URL mit allen möglichen Parametern auflösen. Die
			// Karteikarte hängt an spieler/NU4005017.html — unter Contao 5 kommt
			// der Wert als auto_item (siehe Helper::urlParameter())
			\Contao\Input::setGet('id', \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlParameter('id')); // ID
			\Contao\Input::setGet('search', \Contao\Input::get('search')); // Suchbegriff

			// Alte Verweise der Form spieler.html?pkz=NU… oder ?zps=… auf die
			// heutige Adresse umleiten. Bis 1.44.0 tat das der Hook
			// getPageIdFromUrl mit header('Location: …') — wirkungslos, weil
			// Symfony den Status danach wieder auf 200 setzt und ein Browser
			// einem Location-Header nur bei 3xx folgt; die Seite blieb leer.
			// Controller::redirect() wirft die RedirectResponseException, die
			// beide Contao-Fassungen in eine echte Weiterleitung umsetzen
			if(!\Contao\Input::get('id'))
			{
				$alt = \Contao\Input::get('pkz') ?: \Contao\Input::get('zps');
				if($alt) \Contao\Controller::redirect(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl().'/'.$alt.''.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix().'');
			}
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		global $objPage;

		$id = \Contao\Input::get('id'); // Spielerkartei angefordert?
		$search = \Contao\Input::get('search'); // Spielersuche aktiv?

		// Template vorbelegen
		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar

		$mitglied = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitglied(); // Daten des aktuellen Mitgliedes laden

		// Sperrstatus festlegen
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_karteisperre_gaeste'])) $gesperrt = $mitglied->id ? false : true;
		else $gesperrt = false;

		/*********************************************************
		 * Ergebnisse einer Suche ausgeben
		*/
		if($search)
		{
			// Template anpassen
			$this->Template->subHeadline = 'Suche nach '.$search; // Unterüberschrift setzen
			$this->Template->search = $search;

			// Reklamation unter der Trefferliste — an den DSB-Admin
			$this->Template->reklamation = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::link(\Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::fuerSuche('spielersuche', (string) $search, (string) \Contao\Environment::get('uri')));

			// Suchbegriff analysieren – auf dem Rohstring, damit das Komma zwischen
			// Nachname und Vorname erhalten bleibt (contao.slug würde es sonst durch
			// "-" ersetzen und die Trennung zerstören)
			$check_search = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::checkSearchstringPlayer($search); // Suchbegriff analysieren

			// Abfrageparameter einstellen
			if($check_search['typ'] == 'name')
			{
				// Umlaute u.ä. erst jetzt auf den einzelnen Namensteilen konvertieren.
				// Helper::slugName sluggt jeden durch Leerzeichen getrennten Teil
				// einzeln — "von Dissen" bleibt "von dissen" und wird nicht als
				// "von-dissen" an die API übergeben (das fand nie einen Treffer)
				$nachname = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::slugName($check_search['nachname']);
				$vorname  = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::slugName($check_search['vorname']);

				// nu vergleicht den Vornamen nur vollständig, und die örtliche
				// Suche springt erst ab drei Zeichen Nachname ein. Bei kürzeren
				// Nachnamen („Li, Nat") deshalb nur nach dem Nachnamen fragen
				// und den Vornamensanfang unten selbst filtern
				$vornameFiltern = '';
				if($vorname !== '' && mb_strlen($check_search['nachname']) < \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::KURZER_NACHNAME)
				{
					$vornameFiltern = $check_search['vorname'];
					$vorname = '';
				}

				// Spielersuche
				$param = array
				(
					'funktion'  => 'Spielerliste',
					'cachekey'  => str_replace(' ', '_', trim($nachname.'-'.$vorname, '-')),
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
				if(!empty($vornameFiltern)) $resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::filterVornamenAnfang($resultArr, $vornameFiltern);

				// Trefferliste für das Template aufbereiten
				$suche = new \Schachbulle\ContaoWertungsportalBundle\Helper\Spielersuche($resultArr, 'API');
				$daten = $suche->Spielerliste;

				// Die örtliche Suche läuft IMMER mit, nicht mehr nur bei null
				// Treffern der Schnittstelle. Deren Ergebnisse sind nachweislich
				// unvollständig: "Eschen" fand dort "Eschen, Alexander", aber
				// nicht "Eschenauer, Frank"; "Esch" fand drei ganz andere.
				// Gesucht wird mit dem Rohstring, nicht mit dem Slug; ein
				// zweiter Versuch deutet die Eingabe als "Vorname Nachname"
				$lokal = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::lokaleSpielersuche($check_search['nachname'], $check_search['vorname']);
				if(!$lokal && $check_search['nachname2'] !== '' && $check_search['nachname2'] != $check_search['nachname'])
				{
					$lokal = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::lokaleSpielersuche($check_search['nachname2'], $check_search['vorname2']);
				}

				if($lokal)
				{
					$lokaleTreffer = (new \Schachbulle\ContaoWertungsportalBundle\Helper\Spielersuche($lokal, 'Lokal'))->Spielerliste;
					$daten = \Schachbulle\ContaoWertungsportalBundle\Helper\Spielersuche::zusammenfuehren($daten, $lokaleTreffer);
				}

				// Hinweis nur, wenn tatsächlich örtliche Treffer dabei sind
				$lokaleSuche = count(array_filter($daten, function($eintrag) { return $eintrag['Quelle'] === 'Lokal'; })) > 0;

				// Spieler, die nur noch im Wertungsarchiv stehen (ab 2.0.0) —
				// nur für Mitglieder mit Zugriff, sonst leer
				$archivTreffer = $this->archivsuche((string) $check_search['nachname'], (string) $check_search['vorname']);

				// Kam von der Schnittstelle ein Fehler und hat auch der örtliche
				// Bestand nichts, gehört das auf die Seite — bis 1.44.0 blieb
				// die Trefferliste dann wortlos leer, als gäbe es den Namen nicht
				if(!count($daten) && !empty($resultArr['error']))
				{
					$this->Template->fehler = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::apiFehler($resultArr);
				}
			}
		}

		/*********************************************************
		 * Karteikarte einer Person, die nur im Wertungsarchiv steht
		 * (ab 2.0.0): ID im Format "G12345" (Nummer der Gesamtperson)
		*/
		if($id && preg_match('/^G(\d+)$/', $id, $treffer) && $this->archivkarte((int) $treffer[1]))
		{
			return;
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
				$this->Template->fehler = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::apiFehler(is_array($resultArr) ? $resultArr : array());
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

				// Bild der Person aus dem Backend-Modul Personen. Der frühere Rückgriff auf
				// tl_dwz_spi ist entfallen: Die Tabelle gehört dem abgelösten
				// DeWIS-Bundle und fehlt in jeder Installation ohne dieses, was
				// die Karteikarte mit einem SQL-Fehler abbrechen ließ. Bilder
				// holt man einmalig über „Bilder übernehmen" im Backend-Modul Personen
				$objFile = null;
				$objPerson = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::findOneBy('nuLigaPersonId', $kartei->NuId);

				if($objPerson && $objPerson->addImage && $objPerson->singleSRC !== null)
				{
					// Spielerbild aus dem Backend-Modul Personen
					$objFile = \Contao\FilesModel::findByPk($objPerson->singleSRC);
				}

				if(!$objFile && !empty($GLOBALS['TL_CONFIG']['wertungsportal_playerDefaultImage']))
				{
					// In den Einstellungen hinterlegtes Standardbild. Es zählt wie der
					// Platzhalter als „kein Foto" und fällt in schmalen Fenstern weg
					// (ab 1.55.1, Klasse wp-kopf-bild--platzhalter)
					$objFile = \Contao\FilesModel::findByUuid($GLOBALS['TL_CONFIG']['wertungsportal_playerDefaultImage']);
					$this->Template->ohneBild = true;
				}

				// Fall-Abfrage eingebaut, weil trotz vorhandenem Bild $objFile = NULL sein kann
				if($objFile)
				{
					$this->Template->addImage = true;
					// Bild für das Template erstellen (Methode ab Contao 4.10 möglich)
					$figureBuilder = \Contao\System::getContainer()->get('contao.image.studio')->createFigureBuilder();
					$figure = $figureBuilder->fromPath($objFile->path)
					                        ->setSize(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::bildgroesse('wertungsportal_playerImageSize'))
					                        ->enableLightbox(true)
					                        ->disableMetadata(true)
					                        ->build();
					$figure->applyLegacyTemplateData($this->Template);
				}
				else
				{
					// Weder ein eigenes Bild noch ein Standardbild: das
					// mitgelieferte SVG zeigen (siehe Verein.php)
					$this->Template->addImage = true;
					$this->Template->platzhalter = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::platzhalterbild('spieler');
				}

				/*********************************************************
				 * Ausgabe Kopfdaten, Vereine und Turnierauswertungen
				*/

				// sichtbar wurde oben bereits anhand der Gästesperre gesetzt
				// (sichtbar = !$gesperrt) — hier NICHT erneut auf true setzen,
				// sonst wirkt die Karteikartensperre nicht
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
				// Zuständiger Wertungsreferent (ab 1.53.0): nu liefert bei Spielern
				// keinen, also der aus „Referenten" über den Verein (Lokal)
				$zustaendig = \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::lokal((string) $kartei->ReferentVkz);
				$this->Template->referent     = \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::html($zustaendig) ?: $kartei->Referent;

				// „Foto ändern" unter dem Spielerbild (ab 1.54.0) — Formular mit
				// Dateianhang an den DSB-Admin, nur für angemeldete Mitglieder
				$this->Template->fotolink     = $gesperrt ? '' : \Schachbulle\ContaoWertungsportalBundle\Helper\Aenderung::linkFoto((string) $kartei->Titelname, (string) $id, (string) \Contao\Environment::get('uri'));

				// Reklamation neben dem Wertungsreferenten — an ihn, ohne ihn an den DSB-Admin
				$this->Template->reklamation  = $gesperrt ? '' : \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::link(\Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::fuerKarteikarte((string) $kartei->Titelname, (string) $id, (string) \Contao\Environment::get('uri'), \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::empfaenger($zustaendig)));
				$this->Template->kartei       = $kartei->Kartei;
				$this->Template->altkartei    = $this->altkartei((string) $kartei->NuId);
				$this->Template->diagramm     = $kartei->Diagramm;
				$this->Template->diagrammKomplett = $kartei->DiagrammKomplett;
			}
		}

		// Untertemplate initialisieren und füllen
		$this->Subtemplate = new \Contao\FrontendTemplate($this->subTemplate);
		$this->Subtemplate->daten = isset($daten) ? $daten : false;
		$this->Subtemplate->anzahl = isset($daten) ? count($daten) : 0;
		$this->Subtemplate->lokal = isset($lokaleSuche) ? $lokaleSuche : false;
		// Eine Suche ohne Treffer bekommt Tipps — aber nicht, wenn schon eine
		// Fehlermeldung erklärt, warum die Liste leer ist
		$this->Subtemplate->archiv = $archivTreffer ?? array();
		$this->Subtemplate->keineTreffer = isset($daten) && !count($daten) && empty($archivTreffer) && empty($this->Template->fehler);
		$this->Template->searchresult = $this->Subtemplate->parse();
		$this->Template->searchform = true;
	}

	/**
	 * Liefert die älteren Turniere einer Person aus dem Wertungsarchiv
	 * (ELOBASE, DeWIS) für die Tabelle der Karteikarte (ab 2.0.0).
	 *
	 * Nur für Mitglieder mit dem Schalter „Zugriff auf das Wertungsarchiv"
	 * (Wertungsarchiv\Zugriff) — für alle anderen bleibt die Karteikarte, wie
	 * sie war. Turniere, die das Portal oder DeWIS selbst führen, entfallen
	 * (Regel „jüngste Quelle", siehe Wertungsarchiv\Archiv::altkartei()).
	 *
	 * Ein Fehler der Archivdatenbank darf die Karteikarte nicht kippen: Er
	 * kommt ins Systemprotokoll, die Karteikarte erscheint ohne Archiv.
	 *
	 * @param string $nuId NU-Nummer der Karteikarte
	 *
	 * @return array<int,array<string,mixed>> Zeilen wie Karteikarte::Kartei,
	 *                                        leer ohne Zugriff oder ohne Archivdaten
	 */
	protected function altkartei(string $nuId): array
	{
		if ('' === $nuId || !\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Zugriff::erlaubt())
		{
			return array();
		}

		try
		{
			$archiv = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::aus(\Contao\System::getContainer()->get(\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung::class));
			$gesamt = $archiv ? $archiv->gesamtNachNu($nuId) : null;

			if (null === $gesamt)
			{
				return array();
			}

			$basis = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl();
			$suffix = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();

			return array_map(static function (array $zeile) use ($basis, $suffix): array {
				return \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Darstellung::karteizeile($zeile, $basis, $suffix);
			}, $archiv->altkartei($gesamt));
		}
		catch (\Throwable $e)
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Karteikarte '.$nuId.' ohne Archiv — '.$e->getMessage(), __METHOD__, 'ERROR');

			return array();
		}
	}

	/**
	 * Sucht Spieler, die nur noch im Wertungsarchiv stehen (ab 2.0.0).
	 *
	 * Nur für Mitglieder mit Zugriff (Wertungsarchiv\Zugriff). Gefunden werden
	 * Gesamtpersonen OHNE NU-Nummer — wer eine hat, steht schon in der
	 * normalen Trefferliste. Ein Fehler der Archivdatenbank kommt ins
	 * Systemprotokoll, die Suche bleibt dann ohne Archivtreffer.
	 *
	 * @param string $nachname Nachname oder sein Anfang (Rohstring der Eingabe)
	 * @param string $vorname  Vorname oder sein Anfang
	 *
	 * @return array<int,array<string,mixed>> name, geburtsjahr, archive, link
	 */
	protected function archivsuche(string $nachname, string $vorname): array
	{
		if ('' === trim($nachname) || !\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Zugriff::erlaubt())
		{
			return array();
		}

		try
		{
			$archiv = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::aus(\Contao\System::getContainer()->get(\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung::class));

			if (!$archiv)
			{
				return array();
			}

			$seite = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl();
			$suffix = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();

			return array_map(static function (array $g) use ($seite, $suffix): array {
				return array(
					'name'        => (string) $g['name'],
					'geburtsjahr' => (string) ($g['geburtsjahr'] ?? ''),
					'archive'     => (string) $g['archive'],
					'link'        => $seite.'/G'.(int) $g['id'].$suffix,
				);
			}, $archiv->sucheOhneNu($nachname, $vorname));
		}
		catch (\Throwable $e)
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Suche „'.$nachname.'" ohne Archiv — '.$e->getMessage(), __METHOD__, 'ERROR');

			return array();
		}
	}

	/**
	 * Karteikarte einer Gesamtperson aus dem Wertungsarchiv (ab 2.0.0),
	 * Adresse `spieler/G<Nummer>`.
	 *
	 * Für Personen OHNE NU-Nummer — sie stehen bei nu nicht mehr. Hat die
	 * Gesamtperson doch eine, geht es auf ihre normale Karteikarte. Gezeigt
	 * werden Name, Geburtsjahr, Archive und die Kartei nach „jüngster Quelle"
	 * einschließlich des Portalarchivs (Archiv::altkartei($id, true)).
	 *
	 * Ohne Zugriff geschieht nichts: Die Adresse endet wie jede unbekannte
	 * Spieler-ID ohne Karteikarte.
	 *
	 * @param int $id Nummer der Gesamtperson
	 *
	 * @return bool true, wenn die Karteikarte vorbereitet ist (compile() endet dann)
	 *
	 * @throws \Contao\CoreBundle\Exception\RedirectResponseException wenn die Person eine NU-Nummer hat
	 */
	protected function archivkarte(int $id): bool
	{
		global $objPage;

		if (!\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Zugriff::erlaubt())
		{
			return false;
		}

		try
		{
			$archiv = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::aus(\Contao\System::getContainer()->get(\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung::class));
			$gesamt = $archiv ? $archiv->gesamtperson($id) : null;

			if (null === $gesamt)
			{
				return false;
			}

			if (!empty($gesamt['nu_id']))
			{
				\Contao\Controller::redirect(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl().'/'.$gesamt['nu_id'].\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix());
			}

			$basis = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl();
			$suffix = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();
			$kartei = array_map(static function (array $zeile) use ($basis, $suffix): array {
				return \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Darstellung::karteizeile($zeile, $basis, $suffix);
			}, $archiv->altkartei($id, true));
		}
		catch (\Contao\CoreBundle\Exception\RedirectResponseException $e)
		{
			throw $e;
		}
		catch (\Throwable $e)
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Karteikarte G'.$id.' nicht lesbar — '.$e->getMessage(), __METHOD__, 'ERROR');

			return false;
		}

		$objPage->pageTitle = 'DWZ-Karteikarte (Archiv) '.$gesamt['name'];

		$this->Template = new \Contao\FrontendTemplate('wertungsportal_archivkarte');
		$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation();
		$this->Template->gesamt = $gesamt;
		$this->Template->kartei = $kartei;

		return true;
	}

}
