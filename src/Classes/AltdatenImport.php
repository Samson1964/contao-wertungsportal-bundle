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
	 * Spielerbild aus tl_dwz_spi übernehmen (Match externeNr = dewisID).
	 *
	 * Quellspieler ohne Treffer über die externe Nummer (z. B. abgemeldete
	 * Personen, die nicht im Vereinsmitglieder-CSV stehen) werden dreistufig
	 * behandelt:
	 * 1. Existiert die Person unter ihrer nuLiga-ID, wird sie zugeordnet
	 *    (externe Nummer ergänzt) und das Bild übernommen.
	 * 2. Sonst wird die Person mit den Stammdaten aus tl_dwz_spi neu angelegt.
	 * 3. Hat tl_dwz_spi KEINE nuLiga-ID, wird NICHT angelegt — ein späterer
	 *    CSV-Import würde dieselbe Person über die InterneNr sonst doppelt
	 *    anlegen. Diese Spieler werden ins System-Log geschrieben und auf
	 *    der Ergebnisseite aufgelistet.
	 *
	 * @param  object $dc DataContainer
	 * @return string     HTML der Ergebnisseite
	 */
	public function runPersonen($dc)
	{
		$aktualisiert = 0;
		$unveraendert = 0;
		$zugeordnet = 0;
		$angelegt = 0;
		$nichtAnlegbar = array();

		// Quellspieler mit Bild laden (dewisID => Datensatz) — die Stammdaten
		// werden für das automatische Anlegen fehlender Personen mitgeladen
		$arrQuelle = array();
		$objQuelle = \Database::getInstance()->execute("SELECT dewisID, nuLigaPersonId, vorname, nachname, geschlecht, geburtstag, fideID, verstorben, addImage, singleSRC FROM tl_dwz_spi WHERE addImage = '1' AND singleSRC IS NOT NULL AND dewisID > 0");

		while($objQuelle->next())
		{
			$arrQuelle[(string) $objQuelle->dewisID] = $objQuelle->row();
		}

		// Zielpersonen blockweise über die externe Nummer laden
		$arrZiel = array();
		foreach(array_chunk(array_keys($arrQuelle), 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objZiel = \Database::getInstance()->prepare("SELECT id, externeNr, addImage FROM tl_wertungsportal_persons WHERE externeNr IN ($platzhalter)")
			                                   ->execute($chunk);
			while($objZiel->next())
			{
				$arrZiel[(string) $objZiel->externeNr] = $objZiel->row();
			}
		}

		// nuLiga-IDs der Quellspieler normalisieren: Die Personentabelle führt
		// sie mit NU-Präfix (z. B. NU4168433) — rein numerische Werte aus
		// tl_dwz_spi werden entsprechend ergänzt
		foreach($arrQuelle as $dewisID => $alt)
		{
			$nuId = trim((string) $alt['nuLigaPersonId']);
			if($nuId != '' && preg_match('/^\d+$/', $nuId)) $nuId = 'NU'.$nuId;
			$arrQuelle[$dewisID]['nuLigaPersonId'] = $nuId;
		}

		// Für Quellspieler ohne externeNr-Treffer: Personen über die
		// nuLiga-ID blockweise nachschlagen (z. B. per API-Sync angelegt,
		// aber ohne externe Nummer)
		$arrNuIds = array();
		foreach($arrQuelle as $dewisID => $alt)
		{
			if(!isset($arrZiel[$dewisID]) && $alt['nuLigaPersonId'] != '') $arrNuIds[] = $alt['nuLigaPersonId'];
		}

		$arrPerNuId = array();
		foreach(array_chunk($arrNuIds, 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objZiel = \Database::getInstance()->prepare("SELECT id, nuLigaPersonId, externeNr, addImage FROM tl_wertungsportal_persons WHERE nuLigaPersonId IN ($platzhalter)")
			                                   ->execute($chunk);
			while($objZiel->next())
			{
				$arrPerNuId[(string) $objZiel->nuLigaPersonId] = $objZiel->row();
			}
		}

		foreach($arrQuelle as $dewisID => $alt)
		{
			if(!isset($arrZiel[$dewisID]))
			{
				if($alt['nuLigaPersonId'] == '')
				{
					// Ohne nuLiga-ID nicht anlegbar (Dublettengefahr beim CSV-Import)
					$nichtAnlegbar[] = 'dewisID '.$dewisID.': '.$alt['nachname'].', '.$alt['vorname'];
					continue;
				}

				if(isset($arrPerNuId[$alt['nuLigaPersonId']]))
				{
					// Person existiert unter ihrer nuLiga-ID: externe Nummer
					// ergänzen und Bild übernehmen (sofern noch keins gesetzt)
					$ziel = $arrPerNuId[$alt['nuLigaPersonId']];
					$set = array('tstamp' => time());
					if($ziel['externeNr'] == '') $set['externeNr'] = (string) $dewisID;
					if($ziel['addImage'] != '1')
					{
						$set['addImage'] = '1';
						$set['singleSRC'] = $alt['singleSRC'];
					}

					\Database::getInstance()->prepare("UPDATE tl_wertungsportal_persons %s WHERE id=?")
					                        ->set($set)
					                        ->execute($ziel['id']);
					$zugeordnet++;
					continue;
				}

				// Person mit den Stammdaten aus tl_dwz_spi neu anlegen
				\Database::getInstance()->prepare("INSERT INTO tl_wertungsportal_persons %s")
				                        ->set(array
				                        (
					                        'tstamp'         => time(),
					                        'nuLigaPersonId' => $alt['nuLigaPersonId'],
					                        'externeNr'      => (string) $dewisID,
					                        'firstname'      => $alt['vorname'],
					                        'lastname'       => $alt['nachname'],
					                        'gender'         => $alt['geschlecht'] == 'M' ? 'MALE' : ($alt['geschlecht'] == 'W' ? 'FEMALE' : ''),
					                        'birthyear'      => self::geburtstagAusSpi($alt['geburtstag']),
					                        'fideId'         => (int) $alt['fideID'],
					                        'verstorben'     => $alt['verstorben'] == '1' ? '1' : '',
					                        'addImage'       => '1',
					                        'singleSRC'      => $alt['singleSRC'],
					                        'published'      => '1',
				                        ))
				                        ->execute();
				$angelegt++;
				continue;
			}

			// Nur Personen ohne eigenes Bild befüllen (keine manuelle Pflege überschreiben)
			if($arrZiel[$dewisID]['addImage'] == '1')
			{
				$unveraendert++;
				continue;
			}

			\Database::getInstance()->prepare("UPDATE tl_wertungsportal_persons %s WHERE id=?")
			                        ->set(array('addImage' => '1', 'singleSRC' => $alt['singleSRC'], 'tstamp' => time()))
			                        ->execute($arrZiel[$dewisID]['id']);
			$aktualisiert++;
		}

		// Nicht anlegbare Spieler ins Contao-System-Log schreiben
		if(count($nichtAnlegbar))
		{
			\System::log('Spielerbild-Übernahme: '.count($nichtAnlegbar).' Spieler aus tl_dwz_spi ohne nuLiga-ID nicht angelegt: '.implode('; ', $nichtAnlegbar), __METHOD__, TL_GENERAL);
		}

		$zeilen = array
		(
			'Quellspieler mit Bild: '.count($arrQuelle),
			'Personen aktualisiert: '.$aktualisiert,
			'Ohne Änderung (Person hat bereits ein Bild): '.$unveraendert,
			'Über die nuLiga-ID zugeordnet (externe Nummer ergänzt): '.$zugeordnet,
			'Personen neu angelegt (Stammdaten + Bild aus tl_dwz_spi): '.$angelegt,
			'Nicht anlegbar, ins System-Log geschrieben (keine nuLiga-ID in tl_dwz_spi): '.count($nichtAnlegbar),
		);

		foreach($nichtAnlegbar as $eintrag)
		{
			$zeilen[] = '&mdash; '.$eintrag;
		}

		return $this->ergebnis('Spielerbild-Übernahme aus DWZ-Spieler (tl_dwz_spi)', $zeilen, 'importPhotos');
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
