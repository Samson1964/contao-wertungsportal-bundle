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

class Turnier extends \Contao\Module
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
		if (\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istBackend())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL TURNIER ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}
		else
		{
			// FE-Modus: URL mit allen möglichen Parametern auflösen. Unter
			// Contao 5 kommen die Glieder der Adresse als auto_item bzw. als
			// Paar Schlüssel/Wert an (siehe Helper::turnierParameterAusUrl())
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::turnierParameterAusUrl();
			\Contao\Input::setGet('code', \Contao\Input::get('code')); // Turniercode
			\Contao\Input::setGet('id', \Contao\Input::get('id')); // ID des Spielers
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		global $objPage;

		$search = \Contao\Input::get('search'); // Turniersuche aktiv?
		$turniercode = str_replace(' ', '+', (string) \Contao\Input::get('code')); // Turniercode, Leerzeichen durch + ersetzen, da der Browser aus + Leerzeichen macht
		$id = \Contao\Input::get('id'); // Spieler-ID
		$view = \Contao\Input::get('view'); // View

		// GET-Parameter nur bei aktiviertem Debug-Log protokollieren
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']))
		{
			$log = "GET-Parameter Turnier-Klasse\n";
			$log .= print_r($_GET, true)."\n";
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll($log, 'wertungsportal_oauth2client.log');
		}

		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->shl = 'h2'; // Standard-Überschriftgröße 2
		$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar

		// Alter DeWIS-Code statt UUID (ab 1.58.0): weiterleiten, bei mehrfach
		// vergebenem Code eine Auswahl zeigen. Unbekannte Codes gehen weiter
		// den bisherigen Weg (Fehlermeldung von nu)
		if(!$search && $turniercode !== '' && !\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istUuid($turniercode) && $this->alterCode($turniercode, $view == 'results' && !$id))
		{
			return;
		}

		// Turnier aus dem Wertungsarchiv (ab 2.0.0): „A<id>" oder ein
		// ELOBASE-Code, den nu nicht kennt. Nur für Mitglieder mit Zugriff
		// (Wertungsarchiv\Zugriff); DeWIS-Codes leitet alterCode() schon auf
		// die jüngere Fassung bei nu weiter
		if(!$search && $turniercode !== '' && !\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istUuid($turniercode) && $this->archivTurnier((string) \Contao\Input::get('code')))
		{
			return;
		}

		if($search)
		{
			/*********************************************************
			 * Turniersuche
			*/

			// Übergebenen ZPS-Parameter korrigieren: dreistellig und Großschreibung
			$zps = \Contao\Input::get('zps');

			// ZPS-Cookie setzen
			setcookie('dewis-verband-zps', rtrim((string) \Contao\Input::get('zps'), '0'), time()+8640000, '/');

			// GET-Parameter korrigieren
			$last_months = 0 + (int)\Contao\Input::get('last_months');
			$from_year = sprintf('%04d',\Contao\Input::get('from_year'));
			$to_year = sprintf('%04d',\Contao\Input::get('to_year'));
			$from_month = sprintf('%02d',\Contao\Input::get('from_month'));
			$to_month = sprintf('%02d',\Contao\Input::get('to_month'));
			($from_year < 2011) ? $from_year = 2011 : '';

			// Zeitraum anpassen, wenn "Letzte x Monate" gewählt wurde
			if($last_months > 0 && $last_months < 13)
			{
				// $aktzeit war nie gesetzt (Altfehler): PHP hat die Variable
				// als null gelesen, date() daraus die aktuelle Zeit gemacht und
				// nur eine Warnung protokolliert. Ab PHP 8.1 ist die Übergabe
				// von null an date() zusätzlich abgekündigt, in PHP 9 wird sie
				// zum Fehler — dann läge die Turniersuche mit „Letzte x Monate"
				// still. Die Absicht war offensichtlich die Jetzt-Zeit
				$aktzeit = time();

				$last_months--; // Wegen aktuellem Monat 1 abziehen
				$from_year = date('Y', strtotime('-'.$last_months.' months', mktime(0,0,0,date("n",$aktzeit),1,date("Y",$aktzeit))));
				$from_month = date('m', strtotime('-'.$last_months.' months', mktime(0,0,0,date("n",$aktzeit),1,date("Y",$aktzeit))));
				$to_year = date('Y', $aktzeit);
				$to_month = date('m', $aktzeit);
			}
			$periode = array
			(
				'von' => $from_year ? $from_year.'-'.$from_month.'-01' : $from_year.'-01-01',
				'bis' => $to_year ? $to_year.'-'.$to_month.'-'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Monatstage($to_year)[ltrim($to_month, '0')] : $to_year.'-12-31',
			);

			// ZPS-Nummer des Verbandes
			$param = array
			(
				'funktion'  => 'Turnierliste',
				'cachekey'  => strtolower((string) \Contao\Input::get('keyword')).'-'.$zps.'-'.$periode['von'].'-'.$periode['bis'],
				'von'       => $periode['von'],
				'bis'       => $periode['bis'],
				'zps'       => $zps,
				'suche'     => strtolower((string) \Contao\Input::get('keyword')),
			);
			$resultArr = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($param); // Abfrage ausführen

			// Ergebnis der Turniersuche auswerten
			$trefferliste = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turniersuche($resultArr);
			$lokaleSuche = false;

			// Keine Treffer an der Schnittstelle: lokale Suche als Fallback.
			// Sie greift bei abweichender Umlautschreibweise ("büchenbach"
			// gegen ein gespeichertes "Buechenbach") und bei Suchbegriffen
			// mitten in der Bezeichnung — nu vergleicht nur den Anfang.
			// Ohne Suchbegriff bleibt es beim Ergebnis der Schnittstelle
			if(!count($trefferliste->Turnierliste) && $param['suche'])
			{
				$lokal = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::lokaleTurniersuche($param['suche'], $periode['von'], $periode['bis'], $zps);

				if($lokal)
				{
					$trefferliste = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turniersuche($lokal);
					$lokaleSuche = (bool) count($trefferliste->Turnierliste);
				}
			}

			$title = 'Ergebnis für den Zeitraum '.$from_month.'/'.$from_year.' bis '.$to_month.'/'.$to_year;
			$objPage->pageTitle = $title;

			$this->Template = new \Contao\FrontendTemplate('wertungsportal_turniersuche');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar
			$this->Template->subHeadline = $title; // Unterüberschrift Turnier setzen
			$this->Template->daten = $trefferliste->Turnierliste;
			$this->Template->anzahl = count($trefferliste->Turnierliste);
			$this->Template->lokal = $lokaleSuche;
			$this->Template->search_keyword = $param['suche'];
			$this->Template->search_verband = $zps;
			$this->Template->search_from = $from_month.'/'.$from_year;
			$this->Template->search_to = $to_month.'/'.$to_year;

			// Reklamation unter der Trefferliste — an den DSB-Admin
			$this->Template->reklamation = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::link(\Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::fuerSuche('turniersuche', (string) $title, (string) \Contao\Environment::get('uri')));

			// Suchformular auch auf der Ergebnisseite ausgeben — mit den
			// Werten der laufenden Suche vorbelegt, damit sie sich direkt
			// anpassen lässt statt über den Zurück-Weg zur Suchseite
			$formular = new \Schachbulle\ContaoWertungsportalBundle\Helper\Turnierformular
			(
				\Schachbulle\ContaoWertungsportalBundle\Helper\API::Verbandsliste(),
				array
				(
					'zps'          => $zps,
					'keyword'      => \Contao\Input::get('keyword'),
					'from_month'   => $from_month,
					'from_year'    => $from_year,
					'to_month'     => $to_month,
					'to_year'      => $to_year,
					'last_months'  => \Contao\Input::get('last_months'),
				)
			);

			$this->Template->form_verbaende = $formular->FormVerbaende;
			$this->Template->form_vonmonat = $formular->FormVonmonat;
			$this->Template->form_bismonat = $formular->FormBismonat;
			$this->Template->form_vonjahr = $formular->FormVonjahr;
			$this->Template->form_bisjahr = $formular->FormBisjahr;
			$this->Template->form_letztemonate = $formular->FormLetzteMonate;
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

				$this->Template = new \Contao\FrontendTemplate('wertungsportal_spielberichtsbogen');
				$this->Template->hl = 'h1'; // Standard-Überschriftgröße
				$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar
				$this->Template->subHeadline = $scoresheet->Turniername; // Unterüberschrift Turnier setzen
				$this->Template->fehler = 'Dieser Spielberichtsbogen ist nicht verfügbar.';
				return;
			}

			$zustaendig = $this->zustaendigkeit((array) ($resultTur['body'] ?? array()), (string) $scoresheet->Auswerter1);

			$theader = array
			(
				'Auswertung'            => $scoresheet->Auswertung,
				'Turniercode'           => $scoresheet->Turniercode,
				'Turniername'           => $scoresheet->Turniername,
				'Turnierende'           => $scoresheet->Turnierende,
				'Berechnet'             => $scoresheet->Berechnet,
				'Altcode'               => $scoresheet->Altcode,
				'Nachberechnet'         => $scoresheet->Nachberechnet,
				'Auswerter1'            => $zustaendig['anzeige'],
				'Auswerter2'            => $scoresheet->Auswerter2,
				'Spieler'               => $scoresheet->AnzahlSpieler,
				'Partien'               => $scoresheet->AnzahlPartien,
				'Runden'                => $scoresheet->AnzahlRunden,
				'Turnierauswertunglink' => $scoresheet->Turnierauswertunglink,
				'Turnierergebnislink'   => $scoresheet->Turnierergebnislink,
			);

			$objPage->pageTitle = 'Turnier '.$scoresheet->Turniername.' | Spielbericht '.$scoresheet->Spieler['Name'];

			$this->Template = new \Contao\FrontendTemplate('wertungsportal_spielberichtsbogen');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar
			$this->Template->subHeadline = $scoresheet->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->playerHeadline = 'Spielberichtsbogen <b>'.$scoresheet->Spieler['Name'].'</b>'.($scoresheet->Spieler['DWZ alt'] ? ' / '.(!empty($scoresheet->Spieler['Nichtmitglied']) ? 'Eingangswertung' : 'DWZ').' '.$scoresheet->Spieler['DWZ alt'] : ''); // Unterüberschrift Spieler setzen
			$this->Template->turnierheader = $theader;
			$this->Template->partien = $scoresheet->Ergebnisse;
			$this->Template->spieler = $scoresheet->Spieler;

			// Reklamation neben dem Auswerter — an ihn, ohne ihn an die lokalen
			// Referenten, ohne beide an den DSB-Admin. Nur für angemeldete
			// Mitglieder, sonst bleibt es leer
			$this->Template->reklamation = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::link(\Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::fuerTurnier(
				(array) ($resultTur['body'] ?? array()),
				'spielberichtsbogen',
				(string) \Contao\Environment::get('uri'),
				array
				(
					'name' => (string) ($scoresheet->Spieler['Name'] ?? ''),
					'id'   => \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::nuIdAusBogen((array) ($resultErg['body'] ?? array()), (string) $id),
				),
				$zustaendig['ersatz']
			));
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

			$zustaendig = $this->zustaendigkeit((array) ($resultTur['body'] ?? array()), (string) $ergebnisse->Auswerter1);

			$theader = array
			(
				'Turniercode'           => $ergebnisse->Turniercode,
				'Turniername'           => $ergebnisse->Turniername,
				'Turnierende'           => $ergebnisse->Turnierende,
				'Berechnet'             => $ergebnisse->Berechnet,
				'Altcode'               => $ergebnisse->Altcode,
				'Nachberechnet'         => $ergebnisse->Nachberechnet,
				'Auswerter1'            => $zustaendig['anzeige'],
				'Auswerter2'            => $ergebnisse->Auswerter2,
				'Spieler'               => $ergebnisse->AnzahlSpieler,
				'Partien'               => $ergebnisse->AnzahlPartien,
				'Runden'                => $ergebnisse->AnzahlRunden,
				'Turnierauswertunglink' => $ergebnisse->Turnierauswertunglink,
			);

			$objPage->pageTitle = 'Ergebnisse '.$ergebnisse->Turniername;

			$this->Template = new \Contao\FrontendTemplate('wertungsportal_turnierergebnisse');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar
			$this->Template->subHeadline = $ergebnisse->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->turnierheader = $theader;
			$this->Template->spieler = $ergebnisse->Spieler;
			$this->Template->istKreuztabelle = $ergebnisse->istKreuztabelle;

			// Reklamation neben dem Auswerter, siehe Spielberichtsbogen
			$this->Template->reklamation = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::link(\Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::fuerTurnier((array) ($resultTur['body'] ?? array()), 'turnierergebnisse', (string) \Contao\Environment::get('uri'), null, $zustaendig['ersatz']));
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
				'cachekey'  => \Contao\Input::get('code'),
				'turnier'   => \Contao\Input::get('code')
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

			// Die Turnierauswertung liefert den Turnierkopf unter „tournament" —
			// zustaendigkeit() und Reklamation::fuerTurnier() nehmen beide Formen
			$zustaendig = $this->zustaendigkeit((array) ($resultTur['body'] ?? array()), (string) $auswertung->Auswerter1);

			$theader = array
			(
				'Auswertung'            => $auswertung->Auswertung,
				'Turniercode'           => $auswertung->Turniercode,
				'Turniername'           => $auswertung->Turniername,
				'Turnierende'           => $auswertung->Turnierende,
				'Berechnet'             => $auswertung->Berechnet,
				'Altcode'               => $auswertung->Altcode,
				'Nachberechnet'         => $auswertung->Nachberechnet,
				'Auswerter1'            => $zustaendig['anzeige'],
				'Auswerter2'            => $auswertung->Auswerter2,
				'Spieler'               => $auswertung->AnzahlSpieler,
				'Partien'               => $auswertung->AnzahlPartien,
				'Runden'                => $auswertung->AnzahlRunden,
				'Turnierauswertunglink' => $auswertung->Turnierauswertunglink,
				'Turnierergebnislink'   => $auswertung->Turnierergebnislink,
			);

			$objPage->pageTitle = 'DWZ-Auswertung '.$auswertung->Turniername;

			$this->Template = new \Contao\FrontendTemplate('wertungsportal_turnierauswertung');
			$this->Template->hl = 'h1'; // Standard-Überschriftgröße
			$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar
			$this->Template->subHeadline = $auswertung->Turniername; // Unterüberschrift Turnier setzen
			$this->Template->turnierheader = $theader;
			$this->Template->spieler = $auswertung->Spieler;

			// Reklamation neben dem Auswerter, siehe Spielberichtsbogen
			$this->Template->reklamation = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::link(\Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::fuerTurnier((array) ($resultTur['body'] ?? array()), 'turnierauswertung', (string) \Contao\Environment::get('uri'), null, $zustaendig['ersatz']));
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
			$this->Template->form_vonmonat = $formular->FormVonmonat;
			$this->Template->form_bismonat = $formular->FormBismonat;
			$this->Template->form_vonjahr = $formular->FormVonjahr;
			$this->Template->form_bisjahr = $formular->FormBisjahr;
			$this->Template->form_letztemonate = $formular->FormLetzteMonate;
		}
	}

	/**
	 * Behandelt eine Adresse mit altem DeWIS-Turniercode (ab 1.58.0).
	 *
	 * Gehört der Code genau einem Turnier, folgt eine dauerhafte
	 * Weiterleitung (301) auf dessen neue Adresse — so finden auch
	 * Suchmaschinen und Lesezeichen das Turnier wieder. Tragen ihn mehrere
	 * Turniere (74 Codes in DeWIS), zeigt die Seite eine Auswahl mit Name,
	 * Verband und Turnierende.
	 *
	 * Die alte Ergebnisseite führt auf die neue Ergebnisseite, alles andere
	 * (auch der alte Spielberichtsbogen mit DeWIS-Spielernummer) auf die
	 * Auswertung — siehe Altcodes::ziel().
	 *
	 * @param string $code       Code aus der Adresse, keine UUID
	 * @param bool   $ergebnisse true, wenn die Ergebnisseite verlangt war
	 *
	 * @throws \Contao\CoreBundle\Exception\RedirectResponseException bei genau einem Treffer
	 *
	 * @return bool true, wenn die Auswahl vorbereitet ist (compile() endet
	 *              dann); false, wenn der Code unbekannt ist
	 */
	protected function alterCode($code, $ergebnisse)
	{
		global $objPage;

		$treffer = \Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes::nachCode((string) $code);

		if(!$treffer) return false;

		// Die Seitenadresse ist unter Contao 4.13 relativ („dwz-turniere");
		// eine Weiterleitung braucht sie absolut, sonst löst der Browser sie
		// gegen die alte Adresse auf und landet eine Ebene zu tief
		$seite = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl();

		if(!preg_match('~^https?://~i', $seite))
		{
			// Schema, Host und Basispfad aus der laufenden Anfrage; ohne sie
			// (Konsole) bleibt Environment::get('base')
			$anfrage = \Contao\System::getContainer()->get('request_stack')->getCurrentRequest();
			$basis = $anfrage ? $anfrage->getSchemeAndHttpHost().$anfrage->getBasePath().'/' : \Contao\Environment::get('base');
			$seite = $basis.ltrim($seite, '/');
		}

		$suffix = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();

		if(count($treffer) === 1)
		{
			throw new \Contao\CoreBundle\Exception\RedirectResponseException(\Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes::ziel($seite, (string) $treffer[0]['uuid'], (bool) $ergebnisse, $suffix), 301);
		}

		$auswahl = array();

		foreach($treffer as $t)
		{
			$auswahl[] = array
			(
				'link'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes::ziel($seite, (string) $t['uuid'], (bool) $ergebnisse, $suffix),
				'name'         => (string) $t['tname'],
				'organisation' => (string) $t['organisation'],
				'ende'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes::datum((string) $t['finishedOn']),
				// Dubletten tragen oft denselben Namen und dasselbe Datum —
				// Runden und erste Berechnung helfen beim Unterscheiden
				'runden'       => (int) $t['rounds'],
				'berechnet'    => \Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes::datum((string) $t['computedOn']),
			);
		}

		$objPage->pageTitle = 'Turniercode '.$code;

		$this->Template = new \Contao\FrontendTemplate('wertungsportal_turnierauswertung');
		$this->Template->hl = 'h1';
		$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation();
		$this->Template->subHeadline = 'Turniercode '.\Contao\StringUtil::specialchars((string) $code);
		$this->Template->altcodeAuswahl = $auswahl;

		return true;
	}

	/**
	 * Zeigt ein Turnier aus dem Wertungsarchiv (Etappe 3, ab 2.0.0).
	 *
	 * Adressen: `turniere/A<id>` (archiv_turnier.id, so verlinkt die
	 * Karteikarte) oder `turniere/<ELOBASE-Code>` (alte Verweise aus der Zeit
	 * vor DeWIS). Angezeigt wird der gemeinsame Teil der Turnier-JSON:
	 * Turnierkopf und Teilnehmer; Rekonstruktionen und Abweichungen von der
	 * Kartei werden vermerkt. Teilnehmer mit NU-Nummer verlinken auf ihre
	 * Karteikarte.
	 *
	 * Ohne Zugriff (Wertungsarchiv\Zugriff) geschieht nichts — die Adresse
	 * geht den bisherigen Weg zu nu und endet dort mit „nicht gefunden".
	 *
	 * @param string $code Code aus der Adresse, keine UUID
	 *
	 * @return bool true, wenn die Seite vorbereitet ist (compile() endet dann)
	 */
	protected function archivTurnier($code)
	{
		global $objPage;

		if(!\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Zugriff::erlaubt()) return false;

		try
		{
			$archiv = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::aus(\Contao\System::getContainer()->get(\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung::class));
			if(!$archiv) return false;

			$turnier = preg_match('/^A(\d+)$/', $code, $m) ? $archiv->turnier((int) $m[1]) : $archiv->elobaseTurnier($code);
			if(!$turnier) return false;

			$json = $archiv->json($turnier);
			$teilnehmer = $json ? \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Darstellung::zeilen((array) ($json['teilnehmer'] ?? array())) : array();
			$nu = $archiv->nuNachKennung(array_column($teilnehmer, 'person'));

			// Ergebnisse (…/Ergebnisse): Kreuztabelle oder Rundenansicht aus
			// den Partien der Turnierdatei
			$partien = $json ? \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Darstellung::zeilen((array) ($json['partien'] ?? array())) : array();
			$ergebnisse = ('results' === \Contao\Input::get('view') && $partien)
				? \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Ergebnistabelle::bauen($teilnehmer, $partien)
				: null;
		}
		catch(\Throwable $e)
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Turnier '.$code.' nicht lesbar — '.$e->getMessage(), __METHOD__, 'ERROR');

			return false;
		}

		$kopf = (array) ($json['turnier'] ?? array());
		$name = (string) ($kopf['name'] ?? $turnier['name'] ?? $turnier['quell_id']);

		$objPage->pageTitle = $name;

		$this->Template = new \Contao\FrontendTemplate('wertungsportal_archivturnier');
		$this->Template->hl = 'h1';
		$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation();
		$this->Template->subHeadline = $name;
		$this->Template->turnier = $turnier;
		$this->Template->kopf = $kopf;
		$this->Template->json = $json;
		$this->Template->teilnehmer = $teilnehmer;
		$this->Template->nu = $nu;
		$this->Template->spielerseite = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl();
		$this->Template->suffix = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();
		$this->Template->ergebnisse = $ergebnisse;
		$this->Template->hatPartien = (bool) $partien;
		$adresse = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/A'.(int) $turnier['id'];
		$this->Template->linkAuswertung = $adresse.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();
		$this->Template->linkErgebnisse = $adresse.'/Ergebnisse'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix();

		// Ältere ELOBASE-Fassung desselben Turniers (Rückfrage R6): Verweis auf
		// die gültige ZDB-Fassung über ihren Code (Adresse turniere/<Code>)
		$extra = (array) json_decode((string) ($turnier['extra'] ?? ''), true);
		$this->Template->dubletteVon = (string) ($extra['erg_dublette_von'] ?? '');
		$this->Template->linkDubletteVon = '' !== $this->Template->dubletteVon
			? \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl().'/'.rawurlencode($this->Template->dubletteVon).\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix()
			: '';

		return true;
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

		$objPage->pageTitle = $strSubHeadline;

		$this->Template = new \Contao\FrontendTemplate($strTemplate);
		$this->Template->hl = 'h1'; // Standard-Überschriftgröße
		$this->Template->navigation = $this->wertungsportal_ohneNavigation ? array() : \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation(); // Navigation ausgeben, im Modul abschaltbar
		$this->Template->subHeadline = $strSubHeadline; // Unterüberschrift setzen
		$this->Template->fehler = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::apiFehler($arrResult);
	}

	/**
	 * Ermittelt den zuständigen Wertungsreferenten eines Turniers (ab 1.53.0).
	 *
	 * Der Auswerter von nu (API) steht zuerst; fehlt er oder weicht er von
	 * den lokalen Referenten des Turnierverbandes ab, stehen diese dabei
	 * (Lokal) — Regeln in Helper\Zustaendigkeit. Die lokalen gehen außerdem
	 * als Ersatzempfänger in die Reklamation, falls nu keinen Auswerter nennt.
	 *
	 * Für Gäste bleibt es bei der bisherigen Anzeige: Die Turnierseiten
	 * zeigen den Auswerter nur angemeldeten Besuchern (Helper::Gesperrt()),
	 * und die Helfer liefern dann bereits die Anmeldebitte.
	 *
	 * @param  array  $body   Antwort von nu, Turnierkopf flach oder unter `tournament`
	 * @param  string $bisher Bisherige Anzeige des Auswerters (für Gäste)
	 * @return array          anzeige (HTML für „Auswerter:") und ersatz (Liste
	 *                        mit name und email für die Reklamation)
	 */
	protected function zustaendigkeit(array $body, $bisher)
	{
		$kopf = (isset($body['tournament']) && is_array($body['tournament'])) ? $body['tournament'] : $body;
		$lokal = \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::lokal((string) ($kopf['vkz'] ?? ''));
		$eintraege = \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::eintraege(\Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::ausTurnier($kopf), $lokal);

		if(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Gesperrt())
		{
			$anzeige = (string) $bisher;
		}
		else
		{
			$anzeige = \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::html($eintraege);
			if($anzeige === '') $anzeige = '-';
		}

		return array
		(
			'anzeige' => $anzeige,
			'ersatz'  => \Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit::empfaenger($lokal),
		);
	}

}
