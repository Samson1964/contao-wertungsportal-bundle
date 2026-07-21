<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      VereineImport
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Import der Vereins-Stammdaten-CSV
 * (Vereine__Stammdaten__Adressen__Sportstaetten__JJJJMMTTHHIISS.csv)
 * in tl_wertungsportal_clubs. Wird als globale Operation (key=importClubs)
 * unter Vereine aufgerufen.
 *
 * Ablauf wie beim Personen-Import: Chunk-Upload nach system/tmp, dann Import
 * in Zeilenpaketen per Byte-Offset (Spaltenzuordnung über die Kopfzeile,
 * reihenfolgeunabhängig). Vereine werden per VKZ (VereinNr) angelegt bzw.
 * nur bei Änderungen aktualisiert (WertungsportalClubsModel::importCsvRows);
 * Datum/Uhrzeit aus dem Dateinamen wird als tstamp gesetzt, die Importdaten
 * haben höhere Priorität. Status "Archiv" wird auf das Löschkennzeichen
 * DELETE_STATE_TRUE abgebildet.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class VereineImport extends \Backend
{
	/**
	 * Pflichtspalten der CSV-Datei (Zuordnung erfolgt über die Kopfzeile)
	 */
	const PFLICHTSPALTEN = array('VereinNr', 'VereinName', 'Status');

	/**
	 * Zeilen pro Import-Schritt
	 */
	const ZEILEN_PRO_SCHRITT = 500;

	/**
	 * Einstiegspunkt der globalen Operation (key=importClubs)
	 *
	 * @param  object $dc DataContainer
	 * @return string     HTML der Importseite
	 */
	public function run($dc)
	{
		// AJAX-Aktionen beantworten (werfen eine ResponseException)
		$aktion = \Input::post('wpVereineAktion');
		if($aktion == 'upload') $this->ajaxUpload();
		if($aktion == 'import') $this->ajaxImport();

		// Importseite rendern
		$objTemplate = new \BackendTemplate('be_wp_vereineimport');
		$objTemplate->zurueck = str_replace('&key=importClubs', '', \Environment::get('request'));
		$objTemplate->pflichtspalten = implode(', ', self::PFLICHTSPALTEN);

		return $objTemplate->parse();
	}

	/**
	 * Pfad der temporären Upload-Datei (je Backend-Benutzer)
	 */
	protected function tempDatei()
	{
		return TL_ROOT . '/system/tmp/wp-vereineimport-' . \BackendUser::getInstance()->id . '.csv';
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

		// tstamp aus dem Dateinamen (JJJJMMTTHHIISS), Fallback aktuelle Zeit
		$tstamp = PersonenImport::tstampAusDateiname((string) \Input::post('dateiname'));

		if(!file_exists($datei))
		{
			$this->jsonAntwort(array('fehler' => 'Keine hochgeladene Datei gefunden. Bitte den Import neu starten.'));
		}

		$gesamt = filesize($datei);
		$fp = fopen($datei, 'r');

		// Kopfzeile immer zuerst lesen (Spaltenzuordnung über die Namen)
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
				$this->jsonAntwort(array('fehler' => 'Pflichtspalte "'.$pflicht.'" fehlt in der CSV-Datei. Es wird die Vereins-Stammdaten-Exportdatei (Vereine__Stammdaten__Adressen__Sportstaetten) erwartet.'));
			}
		}

		// Beim ersten Schritt beginnt der Import direkt nach der Kopfzeile
		if($offset > 0) fseek($fp, $offset);

		$arrVereine = array();
		$uebersprungen = 0;
		$zeilen = 0;

		while($zeilen < self::ZEILEN_PRO_SCHRITT && ($row = fgetcsv($fp, 0, ';')) !== false)
		{
			$zeilen++;
			$verein = self::mappeZeileVerein($row, $spalten);

			if($verein === null)
			{
				$uebersprungen++;
				continue;
			}

			// Doppelte VKZ: letzte Zeile gewinnt
			$arrVereine[$verein['clubVkz']] = $verein['felder'];
		}

		$neuerOffset = ftell($fp);
		$fertig = ($neuerOffset >= $gesamt) || feof($fp);
		fclose($fp);

		// Vereine anlegen/aktualisieren
		$ergebnis = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::importCsvRows($arrVereine, $tstamp);

		// Temporäre Datei nach dem letzten Schritt aufräumen
		if($fertig && file_exists($datei)) unlink($datei);

		$this->jsonAntwort(array
		(
			'ok'            => true,
			'offset'        => $neuerOffset,
			'gesamt'        => $gesamt,
			'fertig'        => $fertig,
			'zeilen'        => $zeilen,
			'uebersprungen' => $uebersprungen,
			'neu'           => $ergebnis['neu'],
			'aktualisiert'  => $ergebnis['aktualisiert'],
			'unveraendert'  => $ergebnis['unveraendert'],
		));
	}

	/**
	 * Mappt eine CSV-Zeile auf die DB-Felder von tl_wertungsportal_clubs.
	 * Liefert null, wenn die Zeile keine VereinNr enthält.
	 *
	 * @return array|null array('clubVkz' => ..., 'felder' => array(...))
	 */
	public static function mappeZeileVerein($row, $spalten)
	{
		$wert = function($name) use ($row, $spalten)
		{
			return isset($spalten[$name], $row[$spalten[$name]]) ? trim($row[$spalten[$name]]) : '';
		};

		// ja/nein-Kennzeichen auf Checkbox-Werte abbilden
		$janein = function($name) use ($wert)
		{
			return $wert($name) == 'ja' ? '1' : '';
		};

		$vkz = $wert('VereinNr');
		if($vkz === '') return null;

		return array
		(
			'clubVkz' => $vkz,
			'felder' => array
			(
				'clubName'                  => $wert('VereinName'),
				'verbandName'               => $wert('Verband'),
				'regionName'                => $wert('Region'),
				'kurzname'                  => $wert('VereinKurzName'),
				'druckname'                 => $wert('VereinDruckName'),
				'debitorNr'                 => $wert('DebitorNr'),
				'lsbNr'                     => $wert('LSBNr'),
				'vereinsregisterNr'         => $wert('VereinsregisterNr'),
				'gruendungsjahr'            => $wert('Gruendungsjahr'), // Jahr (JJJJ) oder volles Datum (TT.MM.JJJJ)
				'eintrittsdatum'            => $wert('EintrittsDatum'),
				'austrittsdatum'            => $wert('AustrittsDatum'),
				'zahlungsart'               => $wert('Zahlungsart'),
				'istVerband'                => $janein('IstVerbandBezirkKreis'),
				'istReinerSchachverein'     => $janein('IstReinerSchachverein'),
				'istMehrspartenverein'      => $janein('IstMehrspartenverein'),
				'istFreizeitverein'         => $janein('IstFreizeitverein'),
				'istSpielgemeinschaft'      => $janein('IstSpielgemeinschaft'),
				'istVereinOhneSpielbetrieb' => $janein('IstVereinOhneSpielbetrieb'),
				'personalisierteAnmeldung'  => $janein('PersonalisierteAnmeldung'),
				'istMitgliedsverein'        => $wert('Mitgliedsverein') == 'Mitgliedsverein' ? '1' : '',
				'istVeranstalter'           => ($wert('Veranstalter') == '' || $wert('Veranstalter') == 'kein Veranstalter') ? '' : '1',
				'bic'                       => $wert('BIC'),
				'bank'                      => $wert('Bank'),
				'iban'                      => $wert('IBAN'),
				'kontoinhaber'              => $wert('Kontoinhaber'),
				'adresseName'               => $wert('AdresseName'),
				'adresseStrasse'            => $wert('AdresseStrasse'),
				'adressePlz'                => $wert('AdressePLZ'),
				'adresseOrt'                => $wert('AdresseOrt'),
				'adresseEmail1'             => $wert('AdresseEMail1'),
				'adresseEmail2'             => $wert('AdresseEMail2'),
				'adresseHomepage'           => $wert('AdresseHomepage'),
				'adresseTelefonPrivat'      => $wert('AdresseTelefonPrivat'),
				'adresseTelefonGeschaeft'   => $wert('AdresseTelefonGeschaeft'),
				'adresseTelefonMobil'       => $wert('AdresseTelefonMobil'),
				'adresseFaxPrivat'          => $wert('AdresseFaxPrivat'),
				'adresseFaxGeschaeft'       => $wert('AdresseFaxGeschaeft'),
				'sportstaette1Name'         => $wert('Sportstaette1Name'),
				'sportstaette1Strasse'      => $wert('Sportstaette1Strasse'),
				'sportstaette1Plz'          => $wert('Sportstaette1PLZ'),
				'sportstaette1Ort'          => $wert('Sportstaette1Ort'),
				'sportstaette1Email'        => $wert('Sportstaette1EMail'),
				'sportstaette1Homepage'     => $wert('Sportstaette1Homepage'),
				'sportstaette1Telefon'      => $wert('Sportstaette1Telefon'),
				'sportstaette1Fax'          => $wert('Sportstaette1Fax'),
				'sportstaette2Name'         => $wert('Sportstaette2Name'),
				'sportstaette2Strasse'      => $wert('Sportstaette2Strasse'),
				'sportstaette2Plz'          => $wert('Sportstaette2PLZ'),
				'sportstaette2Ort'          => $wert('Sportstaette2Ort'),
				'sportstaette2Email'        => $wert('Sportstaette2EMail'),
				'sportstaette2Homepage'     => $wert('Sportstaette2Homepage'),
				'sportstaette2Telefon'      => $wert('Sportstaette2Telefon'),
				'sportstaette2Fax'          => $wert('Sportstaette2Fax'),
				'sportstaette3Name'         => $wert('Sportstaette3Name'),
				'sportstaette3Strasse'      => $wert('Sportstaette3Strasse'),
				'sportstaette3Plz'          => $wert('Sportstaette3PLZ'),
				'sportstaette3Ort'          => $wert('Sportstaette3Ort'),
				'sportstaette3Email'        => $wert('Sportstaette3EMail'),
				'sportstaette3Homepage'     => $wert('Sportstaette3Homepage'),
				'sportstaette3Telefon'      => $wert('Sportstaette3Telefon'),
				'sportstaette3Fax'          => $wert('Sportstaette3Fax'),
				'bemerkung'                 => $wert('Bemerkung'),
				// Status aktiv/Archiv auf das Löschkennzeichen abbilden
				'state'                     => $wert('Status') == 'aktiv' ? 'DELETE_STATE_FALSE' : 'DELETE_STATE_TRUE',
			),
		);
	}

	/**
	 * Sendet eine JSON-Antwort und beendet die Verarbeitung
	 */
	protected function jsonAntwort($daten)
	{
		throw new \Contao\CoreBundle\Exception\ResponseException(new \Symfony\Component\HttpFoundation\JsonResponse($daten));
	}
}
