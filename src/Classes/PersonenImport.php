<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      PersonenImport
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Import für die Vereinsmitglieder-CSV in tl_wertungsportal_persons.
 * Wird als globale Operation (key=importPersons) unter WP | Personen
 * aufgerufen. Upload und Import laufen in kleinen AJAX-Schritten, damit es
 * auch bei sehr großen Dateien (~100.000 Zeilen) nicht zu Timeouts kommt:
 * - Upload: Die Datei wird clientseitig in Blöcke zerlegt und blockweise in
 *   eine temporäre Datei (system/tmp) hochgeladen.
 * - Import: Je Aufruf wird ein Zeilenpaket verarbeitet (Bulk-Upsert über
 *   WertungsportalPersonsModel::importCsvRows), der Byte-Offset wandert mit.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class PersonenImport extends \Backend
{
	/**
	 * Pflichtspalten der CSV-Datei (Zuordnung erfolgt über die Kopfzeile)
	 */
	const PFLICHTSPALTEN = array('InterneNr', 'ExterneNr', 'Nachname', 'Vorname');

	/**
	 * Zeilen pro Import-Schritt
	 */
	const ZEILEN_PRO_SCHRITT = 1000;

	/**
	 * Einstiegspunkt der globalen Operation (key=importPersons)
	 *
	 * @param  object $dc DataContainer
	 * @return string     HTML der Importseite
	 */
	public function run($dc)
	{
		// AJAX-Aktionen beantworten (werfen eine ResponseException)
		$aktion = \Input::post('wpImportAktion');
		if($aktion == 'upload') $this->ajaxUpload();
		if($aktion == 'import') $this->ajaxImport();

		// Importseite rendern
		$objTemplate = new \BackendTemplate('be_wp_personenimport');
		$objTemplate->zurueck = str_replace('&key=importPersons', '', \Environment::get('request'));
		$objTemplate->pflichtspalten = implode(', ', self::PFLICHTSPALTEN);

		return $objTemplate->parse();
	}

	/**
	 * Pfad der temporären Upload-Datei (je Backend-Benutzer)
	 */
	protected function tempDatei()
	{
		return TL_ROOT . '/system/tmp/wp-personenimport-' . \BackendUser::getInstance()->id . '.csv';
	}

	/**
	 * AJAX: Einen Upload-Block an die temporäre Datei anhängen
	 */
	protected function ajaxUpload()
	{
		$offset = (int) \Input::post('offset');
		$ziel = $this->tempDatei();

		if(!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK)
		{
			$this->jsonAntwort(array('fehler' => 'Upload-Fehler: Block wurde nicht empfangen (evtl. post_max_size/upload_max_filesize zu klein).'));
		}

		if($offset == 0)
		{
			// Neue Datei beginnen
			if(file_exists($ziel)) unlink($ziel);
			file_put_contents($ziel, file_get_contents($_FILES['chunk']['tmp_name']));
		}
		else
		{
			// Fortsetzung: Offset muss zur bisherigen Dateigröße passen
			if(!file_exists($ziel) || filesize($ziel) != $offset)
			{
				$this->jsonAntwort(array('fehler' => 'Upload nicht fortsetzbar (Offset passt nicht zur Dateigröße). Bitte den Import neu starten.'));
			}
			file_put_contents($ziel, file_get_contents($_FILES['chunk']['tmp_name']), FILE_APPEND);
		}

		clearstatcache();
		$this->jsonAntwort(array('ok' => true, 'groesse' => filesize($ziel)));
	}

	/**
	 * AJAX: Ein Zeilenpaket ab dem übergebenen Byte-Offset importieren
	 */
	protected function ajaxImport()
	{
		$offset = (int) \Input::post('offset');
		$datei = $this->tempDatei();

		// tstamp der importierten Datensätze: Datum und Uhrzeit aus dem
		// Original-Dateinamen (Vereine__Vereinsmitglieder__JJJJMMTTHHIISS),
		// Fallback aktuelle Zeit. Die Importdaten haben höhere Priorität und
		// überschreiben Bestandsdaten auch dann, wenn deren tstamp jünger ist.
		$tstamp = self::tstampAusDateiname((string) \Input::post('dateiname'));

		if(!file_exists($datei))
		{
			$this->jsonAntwort(array('fehler' => 'Keine hochgeladene Datei gefunden. Bitte den Import neu starten.'));
		}

		$gesamt = filesize($datei);
		$fp = fopen($datei, 'r');

		// Kopfzeile immer zuerst lesen - die Spaltenzuordnung erfolgt über die
		// Spaltennamen und ist damit unabhängig von der Spaltenreihenfolge
		$header = fgetcsv($fp, 0, ';');

		if(!is_array($header))
		{
			fclose($fp);
			$this->jsonAntwort(array('fehler' => 'Die Kopfzeile der CSV-Datei konnte nicht gelesen werden.'));
		}

		// Eventuelles UTF-8-BOM am Dateianfang entfernen
		$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
		$spalten = array_flip($header);

		foreach(self::PFLICHTSPALTEN as $pflicht)
		{
			if(!isset($spalten[$pflicht]))
			{
				fclose($fp);
				$this->jsonAntwort(array('fehler' => 'Pflichtspalte "'.$pflicht.'" fehlt in der CSV-Datei. Es wird die Vereinsmitglieder-Exportdatei erwartet.'));
			}
		}

		// Beim ersten Schritt beginnt der Import direkt nach der Kopfzeile
		if($offset > 0) fseek($fp, $offset);

		$arrPersonen = array();
		$arrMitgliedschaften = array();
		$arrVereine = array();
		$uebersprungen = 0;
		$zeilen = 0;

		while($zeilen < self::ZEILEN_PRO_SCHRITT && ($row = fgetcsv($fp, 0, ';')) !== false)
		{
			$zeilen++;
			$person = self::mappeZeile($row, $spalten);

			if($person === null)
			{
				$uebersprungen++;
				continue;
			}

			// Mehrfachzeilen derselben Person (Mehrfachmitgliedschaften):
			// die Personendaten sind identisch, die erste Zeile genügt
			if(!isset($arrPersonen[$person['nuLigaPersonId']]))
			{
				$arrPersonen[$person['nuLigaPersonId']] = $person['felder'];
			}

			// Mitgliedschaft der Zeile einsammeln (Schlüssel VKZ + Mitgliedsnummer)
			if($person['mitgliedschaft'] !== null)
			{
				$m = $person['mitgliedschaft'];
				$key = $m['vkz'].'|'.$m['memberNo'];

				if(!isset($arrMitgliedschaften[$key]))
				{
					$arrMitgliedschaften[$key] = array
					(
						'nuId'     => $person['nuLigaPersonId'],
						'vkz'      => $m['vkz'],
						'memberNo' => $m['memberNo'],
						'felder'   => $m['felder'],
					);
				}

				// Verein für den gesammelten Vereins-Abgleich vormerken
				$arrVereine[$m['vkz']] = array('clubVkz' => $m['vkz'], 'clubName' => $m['felder']['clubName']);
			}
		}

		$neuerOffset = ftell($fp);
		$fertig = ($neuerOffset >= $gesamt) || feof($fp);
		fclose($fp);

		// 1. Personen anlegen/aktualisieren (liefert nuLigaPersonId => ID)
		$ergebnis = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::importCsvRows($arrPersonen, $tstamp);

		// 2. Vereine der Mitgliedschaften anlegen/aktualisieren
		if(count($arrVereine))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::syncList(array_values($arrVereine));
		}

		// 3. Mitgliedschaften mit der Personen-ID verknüpfen und importieren
		$arrImport = array();

		foreach($arrMitgliedschaften as $key => $item)
		{
			$pid = isset($ergebnis['ids'][$item['nuId']]) ? $ergebnis['ids'][$item['nuId']] : 0;
			if(!$pid) continue; // Ohne Person keine Mitgliedschaft

			$arrImport[$key] = array
			(
				'pid'      => $pid,
				'vkz'      => $item['vkz'],
				'memberNo' => $item['memberNo'],
				'felder'   => $item['felder'],
			);
		}

		$ergebnisM = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::importCsvRows($arrImport, $tstamp);

		// Temporäre Datei nach dem letzten Schritt aufräumen
		if($fertig && file_exists($datei)) unlink($datei);

		$this->jsonAntwort(array
		(
			'ok'             => true,
			'offset'         => $neuerOffset,
			'gesamt'         => $gesamt,
			'fertig'         => $fertig,
			'zeilen'         => $zeilen,
			'uebersprungen'  => $uebersprungen,
			'neu'            => $ergebnis['neu'],
			'aktualisiert'   => $ergebnis['aktualisiert'],
			'unveraendert'   => $ergebnis['unveraendert'],
			'mNeu'           => $ergebnisM['neu'],
			'mAktualisiert'  => $ergebnisM['aktualisiert'],
			'mUnveraendert'  => $ergebnisM['unveraendert'],
		));
	}

	/**
	 * Mappt eine CSV-Zeile auf die DB-Felder von tl_wertungsportal_persons
	 * und tl_wertungsportal_persons_memberships.
	 * $spalten ist die Zuordnung Spaltenname => Index aus der Kopfzeile.
	 * Liefert null, wenn die Zeile keine InterneNr enthält;
	 * 'mitgliedschaft' ist null, wenn VereinNr oder Mitgliedernummer fehlen.
	 *
	 * @return array|null array('nuLigaPersonId' => ..., 'felder' => array(...), 'mitgliedschaft' => array|null)
	 */
	public static function mappeZeile($row, $spalten)
	{
		$wert = function($name) use ($row, $spalten)
		{
			return isset($spalten[$name], $row[$spalten[$name]]) ? trim($row[$spalten[$name]]) : '';
		};

		$nuId = $wert('InterneNr');
		if($nuId === '') return null;

		// Geschlecht auf das API-Format der Personentabelle abbilden
		$geschlecht = $wert('Geschlecht');
		if($geschlecht == 'm') $geschlecht = 'MALE';
		elseif($geschlecht == 'w') $geschlecht = 'FEMALE';
		elseif($geschlecht == 'd') $geschlecht = 'DIVERSE';
		else $geschlecht = ''; // 'unbekannt' u.ä.

		// Spielgenehmigung auf den Lizenzstatus der API abbilden
		$lizenz = $wert('Spielgenehmigung');
		if($lizenz == 'Aktiv') $lizenz = 'ACTIVE';
		elseif($lizenz == 'Passiv') $lizenz = 'PASSIVE';
		elseif($lizenz == 'Sondermitgliedschaft') $lizenz = 'SONDER';
		elseif($lizenz == 'ohne Spielgenehmigung') $lizenz = 'OHNE';

		// Mitgliedschaft der Zeile (Schlüssel: VKZ + Mitgliedsnummer)
		$mitgliedschaft = null;

		if($wert('VereinNr') !== '' && $wert('Mitgliedernummer') !== '')
		{
			$mitgliedschaft = array
			(
				'vkz'      => $wert('VereinNr'),
				'memberNo' => $wert('Mitgliedernummer'),
				'felder'   => array
				(
					'clubName'            => $wert('VereinName'),
					'licenceState'        => $lizenz,
					'regionName'          => $wert('Region'),
					'federationName'      => $wert('Verband'),
					'spielgenehmigungVon' => $wert('SpielgenehmigungVon'),
					'spielgenehmigungBis' => $wert('SpielgenehmigungBis'),
				),
			);
		}

		return array
		(
			'nuLigaPersonId' => $nuId,
			'mitgliedschaft' => $mitgliedschaft,
			'felder' => array
			(
				'externeNr'               => $wert('ExterneNr'),
				'anrede'                  => $wert('Anrede'),
				'titel'                   => $wert('Titel'),
				'firstname'               => $wert('Vorname'),
				'lastname'                => $wert('Nachname'),
				'geburtsname'             => $wert('Geburtsname'),
				'birthyear'               => $wert('Geburtsdatum'), // volles Datum TT.MM.JJJJ (birthyear erlaubt Jahr ODER Datum)
				'geburtsort'              => $wert('Geburtsort'),
				'verstorben'              => $wert('Verstorben') == 'ja' ? '1' : '',
				'verstorbenAm'            => $wert('VerstorbenAm'),
				'nation'                  => $wert('Nation'),
				'fideNation'              => $wert('FideNation'),
				'gender'                  => $geschlecht,
				'geschlechtSpielbetrieb'  => $wert('GeschlechtImSpielbetrieb'),
				'strasse'                 => $wert('AdresseStrasse'),
				'plz'                     => $wert('AdressePLZ'),
				'ort'                     => $wert('AdresseOrt'),
				'land'                    => $wert('AdresseLand'),
				'email1'                  => $wert('AdresseEMail1'),
				'email2'                  => $wert('AdresseEMail2'),
				'telPrivat'               => $wert('AdresseTelPrivat'),
				'telMobil'                => $wert('AdresseTelMobil'),
				'telGeschaeft'            => $wert('AdresseTelGeschaeft'),
				'faxPrivat'               => $wert('AdresseFaxPrivat'),
				'faxGeschaeft'            => $wert('AdresseFaxGeschaft'), // Achtung: Tippfehler in der CSV-Quelle ("Gescha" ohne e)
				'datenschutzDatum'        => $wert('DatenschutzerklaerungDatum'),
				'datenschutzBenutzer'     => $wert('DatenschutzerklaerungBenutzer'),
				'datenschutzInfoDatum'    => $wert('DatenschutzInformationspflichtDatum'),
				'datenschutzInfoBenutzer' => $wert('DatenschutzInformationspflichtBenutzer'),
				'fideId'                  => (int) $wert('FideId'),
			),
		);
	}

	/**
	 * Liest Datum und Uhrzeit aus dem Namen der Importdatei
	 * (Vereine__Vereinsmitglieder__JJJJMMTTHHIISS.csv) und liefert den
	 * Unix-Timestamp zurück; 0, wenn der Name kein gültiges Datum enthält.
	 *
	 * @param  string $dateiname Original-Dateiname
	 * @return int               Unix-Timestamp oder 0
	 */
	public static function tstampAusDateiname($dateiname)
	{
		if(preg_match('/(\d{14})/', (string) $dateiname, $treffer))
		{
			$datum = \DateTime::createFromFormat('YmdHis', $treffer[1]);

			// Nur plausible Datumswerte übernehmen (createFromFormat "korrigiert"
			// ungültige Angaben wie den 32. Tag stillschweigend)
			if($datum && $datum->format('YmdHis') === $treffer[1])
			{
				return $datum->getTimestamp();
			}
		}

		return 0;
	}

	/**
	 * Sendet eine JSON-Antwort und beendet die Verarbeitung
	 */
	protected function jsonAntwort($daten)
	{
		throw new \Contao\CoreBundle\Exception\ResponseException(new \Symfony\Component\HttpFoundation\JsonResponse($daten));
	}
}
