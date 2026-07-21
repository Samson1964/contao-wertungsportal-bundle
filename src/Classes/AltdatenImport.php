<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      AltdatenImport
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Übernahme von Altdaten aus den DWZ-Tabellen des alten
 * contao-dewis-bundles in die Wertungsportal-Tabellen:
 * - runVereine (key=importDwzVer, Modul Vereine): Logo, Homepage, Info und
 *   Alternativname aus tl_dwz_ver nach tl_wertungsportal_clubs (Match per VKZ);
 *   fehlende Vereine werden mit Name/Status aus tl_dwz_ver neu angelegt
 * - runPersonen (key=importPhotos, Modul Personen): Spielerbild aus tl_dwz_spi
 *   nach tl_wertungsportal_persons (Match externeNr = dewisID)
 *
 * Bestehende Datensätze: Es werden nur leere Zielfelder befüllt — die
 * Übernahme ist damit idempotent und überschreibt keine manuelle Pflege.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class AltdatenImport extends \Backend
{
	/**
	 * Globale Operation unter Vereine (key=importDwzVer):
	 * Logo, Homepage, Info und Alternativname aus tl_dwz_ver übernehmen.
	 * Vereine, deren VKZ noch nicht in tl_wertungsportal_clubs existiert,
	 * werden automatisch mit angelegt (Name und Status aus tl_dwz_ver;
	 * abgemeldete Vereine erhalten das Löschkennzeichen DELETE_STATE_TRUE).
	 *
	 * @param  object $dc DataContainer
	 * @return string     HTML der Ergebnisseite
	 */
	public function runVereine($dc)
	{
		$aktualisiert = 0;
		$unveraendert = 0;
		$angelegt = 0;

		// Quelldatensätze mit übernehmbaren Inhalten laden (VKZ => Datensatz)
		$arrQuelle = array();
		$objQuelle = \Database::getInstance()->execute("SELECT zpsver, name, status, altname, homepage, info, addImage, singleSRC FROM tl_dwz_ver WHERE altname != '' OR homepage != '' OR (info IS NOT NULL AND info != '') OR (addImage = '1' AND singleSRC IS NOT NULL)");

		while($objQuelle->next())
		{
			$arrQuelle[$objQuelle->zpsver] = $objQuelle->row();
		}

		// Zielvereine blockweise laden (VKZ => Datensatz)
		$arrZiel = array();
		foreach(array_chunk(array_keys($arrQuelle), 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objZiel = \Database::getInstance()->prepare("SELECT id, clubVkz, altname, homepage, info, addImage FROM tl_wertungsportal_clubs WHERE clubVkz IN ($platzhalter)")
			                                   ->execute($chunk);
			while($objZiel->next())
			{
				$arrZiel[$objZiel->clubVkz] = $objZiel->row();
			}
		}

		foreach($arrQuelle as $vkz => $alt)
		{
			if(!isset($arrZiel[$vkz]))
			{
				// Verein fehlt im Wertungsportal — mit Stammdaten aus tl_dwz_ver
				// anlegen (Status L = abgemeldet => Löschkennzeichen setzen)
				$set = array
				(
					'tstamp'   => time(),
					'clubVkz'  => $vkz,
					'clubName' => $alt['name'] != '' ? $alt['name'] : ($alt['altname'] != '' ? $alt['altname'] : $vkz),
					'state'    => ($alt['status'] == 'L') ? 'DELETE_STATE_TRUE' : 'DELETE_STATE_FALSE',
					'published' => '1',
				);

				if($alt['altname'] != '') $set['altname'] = $alt['altname'];
				if($alt['homepage'] != '') $set['homepage'] = $alt['homepage'];
				if($alt['info'] !== null && $alt['info'] != '') $set['info'] = $alt['info'];
				if($alt['addImage'] == '1' && $alt['singleSRC'] !== null)
				{
					$set['addImage'] = '1';
					$set['singleSRC'] = $alt['singleSRC'];
				}

				\Database::getInstance()->prepare("INSERT INTO tl_wertungsportal_clubs %s")
				                        ->set($set)
				                        ->execute();
				$angelegt++;
				continue;
			}

			$ziel = $arrZiel[$vkz];
			$set = array();

			// Nur leere Zielfelder befüllen (keine manuelle Pflege überschreiben)
			if($alt['altname'] != '' && $ziel['altname'] == '') $set['altname'] = $alt['altname'];
			if($alt['homepage'] != '' && $ziel['homepage'] == '') $set['homepage'] = $alt['homepage'];
			if($alt['info'] !== null && $alt['info'] != '' && ($ziel['info'] === null || $ziel['info'] == '')) $set['info'] = $alt['info'];
			if($alt['addImage'] == '1' && $alt['singleSRC'] !== null && $ziel['addImage'] != '1')
			{
				$set['addImage'] = '1';
				$set['singleSRC'] = $alt['singleSRC'];
			}

			if($set)
			{
				$set['tstamp'] = time();
				\Database::getInstance()->prepare("UPDATE tl_wertungsportal_clubs %s WHERE id=?")
				                        ->set($set)
				                        ->execute($ziel['id']);
				$aktualisiert++;
			}
			else
			{
				$unveraendert++;
			}
		}

		return $this->ergebnis('Altdaten-Übernahme aus DWZ-Vereine (tl_dwz_ver)', array
		(
			'Quelldatensätze mit übernehmbaren Inhalten: '.count($arrQuelle),
			'Vereine aktualisiert: '.$aktualisiert,
			'Ohne Änderung (Zielfelder bereits gefüllt): '.$unveraendert,
			'Vereine neu angelegt (VKZ fehlte, Name/Status aus tl_dwz_ver): '.$angelegt,
		), 'importDwzVer');
	}

	/**
	 * Globale Operation unter Personen (key=importPhotos):
	 * Spielerbild aus tl_dwz_spi in vorhandene Personen übernehmen.
	 *
	 * Die Personen kommen über die CSV-Importe (Vereinsmitglieder,
	 * Spielgenehmigungen) in tl_wertungsportal_persons; der Bild-Import legt
	 * daher KEINE Personen an, sondern ordnet die Fotos den bestehenden
	 * Personen zu. Der Match läuft dreistufig, jeweils nur für die noch nicht
	 * zugeordneten Quellspieler:
	 *   1. externe Nummer = DeWIS-Spielernummer (Regelfall — die dewisID wurde
	 *      von nu als externeNr übernommen)
	 *   2. FIDE-ID (falls nu die externeNr auf einen "C"-Präfix mit abweichender
	 *      Nummer geändert hat; die FIDE-ID ist in beiden Tabellen dieselbe)
	 *   3. Nachname + Vorname + Geburtsjahr, aber NUR wenn auf beiden Seiten
	 *      eindeutig (schützt vor Fehlzuordnung bei Namensgleichheit)
	 * Wer sich keiner Person zuordnen lässt, wird ins System-Log geschrieben
	 * und auf der Ergebnisseite aufgelistet.
	 *
	 * @param  object $dc DataContainer
	 * @return string     HTML der Ergebnisseite
	 */
	public function runPersonen($dc)
	{
		$ueberExtern = 0;
		$ueberFide = 0;
		$ueberName = 0;
		$unveraendert = 0;
		$nichtZugeordnet = array();

		// Quellspieler mit Bild laden (dewisID => Datensatz)
		$arrQuelle = array();
		$objQuelle = \Database::getInstance()->execute("SELECT dewisID, vorname, nachname, geburtstag, fideID, addImage, singleSRC FROM tl_dwz_spi WHERE addImage = '1' AND singleSRC IS NOT NULL AND dewisID > 0");

		while($objQuelle->next())
		{
			$arrQuelle[(string) $objQuelle->dewisID] = $objQuelle->row();
		}

		// 1. Zielpersonen blockweise über die externe Nummer laden
		$arrPerExtern = array();
		foreach(array_chunk(array_keys($arrQuelle), 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objZiel = \Database::getInstance()->prepare("SELECT id, externeNr, addImage FROM tl_wertungsportal_persons WHERE externeNr IN ($platzhalter)")
			                                   ->execute($chunk);
			while($objZiel->next())
			{
				$arrPerExtern[(string) $objZiel->externeNr] = $objZiel->row();
			}
		}

		// 2. Für Spieler ohne externeNr-Treffer, aber mit FIDE-ID: Personen
		// über die FIDE-ID nachschlagen (mehrdeutige FIDE-IDs überspringen)
		$arrFideIds = array();
		foreach($arrQuelle as $dewisID => $alt)
		{
			if(!isset($arrPerExtern[$dewisID]) && (int) $alt['fideID'] > 0) $arrFideIds[] = (int) $alt['fideID'];
		}

		$arrPerFide = array();
		$arrFideMehrdeutig = array();
		foreach(array_chunk(array_values(array_unique($arrFideIds)), 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objZiel = \Database::getInstance()->prepare("SELECT id, fideId, addImage FROM tl_wertungsportal_persons WHERE fideId > 0 AND fideId IN ($platzhalter)")
			                                   ->execute($chunk);
			while($objZiel->next())
			{
				$fid = (int) $objZiel->fideId;
				if(isset($arrPerFide[$fid])) $arrFideMehrdeutig[$fid] = true; // FIDE-ID mehrfach → nicht verwenden
				else $arrPerFide[$fid] = $objZiel->row();
			}
		}

		// 3. Für die verbleibenden Spieler: Match über Nachname + Vorname +
		// Geburtsjahr vorbereiten. Quellseitig mehrdeutige Schlüssel werden
		// ausgeschlossen; die Nachnamen werden für die gezielte Ziel-Abfrage
		// gesammelt (nur die relevanten Personen laden statt der ganzen Tabelle).
		$arrNamensSchluessel = array();
		$arrRestNachnamen = array();
		foreach($arrQuelle as $dewisID => $alt)
		{
			if(isset($arrPerExtern[$dewisID])) continue;
			if((int) $alt['fideID'] > 0 && isset($arrPerFide[(int) $alt['fideID']]) && !isset($arrFideMehrdeutig[(int) $alt['fideID']])) continue;
			$key = self::namensSchluessel($alt['nachname'], $alt['vorname'], self::geburtstagAusSpi($alt['geburtstag']));
			if($key !== '')
			{
				$arrNamensSchluessel[$key] = ($arrNamensSchluessel[$key] ?? 0) + 1;
				$arrRestNachnamen[(string) $alt['nachname']] = true;
			}
		}

		// Personen mit passendem Nachnamen laden (Index auf lastname), daraus
		// die Namensschlüssel bilden und zielseitig mehrdeutige merken
		$arrPerName = array();
		$arrNameMehrdeutig = array();
		foreach(array_chunk(array_keys($arrRestNachnamen), 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objZiel = \Database::getInstance()->prepare("SELECT id, lastname, firstname, birthyear, addImage FROM tl_wertungsportal_persons WHERE lastname IN ($platzhalter)")
			                                   ->execute($chunk);
			while($objZiel->next())
			{
				$key = self::namensSchluessel($objZiel->lastname, $objZiel->firstname, $objZiel->birthyear);
				if($key === '') continue;
				if(isset($arrPerName[$key])) $arrNameMehrdeutig[$key] = true;
				else $arrPerName[$key] = $objZiel->row();
			}
		}

		// ─── Zuordnung durchführen ───
		foreach($arrQuelle as $dewisID => $alt)
		{
			// 1. externe Nummer = dewisID
			if(isset($arrPerExtern[$dewisID]))
			{
				$this->bildZuweisen($arrPerExtern[$dewisID], $alt['singleSRC'], $ueberExtern, $unveraendert);
				continue;
			}

			// 2. FIDE-ID (nur eindeutige)
			$fid = (int) $alt['fideID'];
			if($fid > 0 && isset($arrPerFide[$fid]) && !isset($arrFideMehrdeutig[$fid]))
			{
				$this->bildZuweisen($arrPerFide[$fid], $alt['singleSRC'], $ueberFide, $unveraendert);
				continue;
			}

			// 3. Nachname + Vorname + Geburtsjahr (nur beidseitig eindeutig)
			$key = self::namensSchluessel($alt['nachname'], $alt['vorname'], self::geburtstagAusSpi($alt['geburtstag']));
			if($key !== '' && ($arrNamensSchluessel[$key] ?? 0) == 1 && isset($arrPerName[$key]) && !isset($arrNameMehrdeutig[$key]))
			{
				$this->bildZuweisen($arrPerName[$key], $alt['singleSRC'], $ueberName, $unveraendert);
				continue;
			}

			$nichtZugeordnet[] = 'dewisID '.$dewisID.': '.$alt['nachname'].', '.$alt['vorname'];
		}

		// Nicht zugeordnete Spieler ins Contao-System-Log schreiben
		if(count($nichtZugeordnet))
		{
			\System::log('Spielerbild-Übernahme: '.count($nichtZugeordnet).' Spieler aus tl_dwz_spi keiner Person zugeordnet (kein Treffer über externe Nummer, FIDE-ID oder Name): '.implode('; ', $nichtZugeordnet), __METHOD__, TL_GENERAL);
		}

		$zeilen = array
		(
			'Quellspieler mit Bild: '.count($arrQuelle),
			'Zugeordnet über die externe Nummer: '.$ueberExtern,
			'Zugeordnet über die FIDE-ID: '.$ueberFide,
			'Zugeordnet über Name + Geburtsjahr: '.$ueberName,
			'Ohne Änderung (Person hat bereits ein Bild): '.$unveraendert,
			'Nicht zugeordnet, ins System-Log geschrieben: '.count($nichtZugeordnet),
		);

		foreach($nichtZugeordnet as $eintrag)
		{
			$zeilen[] = '&mdash; '.$eintrag;
		}

		return $this->ergebnis('Spielerbild-Übernahme aus DWZ-Spieler (tl_dwz_spi)', $zeilen, 'importPhotos');
	}

	/**
	 * Weist einer Zielperson das Spielerbild zu, sofern sie noch keins hat
	 * (keine manuelle Pflege überschreiben), und zählt das Ergebnis.
	 *
	 * @param  array  $ziel         Zielperson (Zeile mit id, addImage)
	 * @param  mixed  $singleSRC    UUID der Bilddatei aus tl_dwz_spi
	 * @param  int    &$zaehlerNeu  Zähler für übernommene Bilder
	 * @param  int    &$zaehlerAlt  Zähler für bereits vorhandene Bilder
	 */
	protected function bildZuweisen($ziel, $singleSRC, &$zaehlerNeu, &$zaehlerAlt)
	{
		if($ziel['addImage'] == '1')
		{
			$zaehlerAlt++;
			return;
		}

		\Database::getInstance()->prepare("UPDATE tl_wertungsportal_persons %s WHERE id=?")
		                        ->set(array('addImage' => '1', 'singleSRC' => $singleSRC, 'tstamp' => time()))
		                        ->execute($ziel['id']);
		$zaehlerNeu++;
	}

	/**
	 * Bildet einen normalisierten Namensschlüssel (nachname|vorname|jahr) für
	 * den Namens-Match. Liefert '' bei fehlendem Namen oder Jahr, damit
	 * unvollständige Datensätze nicht fälschlich zusammengeführt werden.
	 *
	 * @param  string $nachname
	 * @param  string $vorname
	 * @param  string $geburt   Geburtsjahr (JJJJ) oder volles Datum (TT.MM.JJJJ)
	 * @return string
	 */
	public static function namensSchluessel($nachname, $vorname, $geburt)
	{
		$nachname = trim(mb_strtolower((string) $nachname));
		$vorname = trim(mb_strtolower((string) $vorname));

		// Geburtsjahr extrahieren (vierstellige Jahreszahl)
		$jahr = '';
		if(preg_match('/(\d{4})/', (string) $geburt, $m)) $jahr = $m[1];

		if($nachname === '' || $vorname === '' || $jahr === '') return '';

		return $nachname.'|'.$vorname.'|'.$jahr;
	}

	/**
	 * Wandelt den Geburtstag aus tl_dwz_spi (int: JJJJMMTT, JJJJMM oder JJJJ)
	 * in das birthyear-Format der Personentabelle um (TT.MM.JJJJ oder JJJJ)
	 *
	 * @param  mixed  $wert Geburtstag aus tl_dwz_spi
	 * @return string
	 */
	public static function geburtstagAusSpi($wert)
	{
		$wert = (string) (int) $wert;

		switch(strlen($wert))
		{
			case 8: // JJJJMMTT -> TT.MM.JJJJ
				return substr($wert, 6, 2).'.'.substr($wert, 4, 2).'.'.substr($wert, 0, 4);
			case 6: // JJJJMM -> JJJJ
			case 4: // JJJJ
				return substr($wert, 0, 4);
			default:
				return '';
		}
	}

	/**
	 * Rendert die Ergebnisseite einer Übernahme
	 *
	 * @param  string $headline Überschrift
	 * @param  array  $zeilen   Ergebniszeilen
	 * @param  string $key      key-Parameter der globalen Operation (für den Zurück-Link)
	 * @return string           HTML der Ergebnisseite
	 */
	protected function ergebnis($headline, $zeilen, $key)
	{
		$objTemplate = new \BackendTemplate('be_wp_altdaten');
		$objTemplate->headline = $headline;
		$objTemplate->zeilen = $zeilen;
		$objTemplate->zurueck = str_replace('&key='.$key, '', \Environment::get('request'));

		return $objTemplate->parse();
	}
}
