<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      EloImport
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Import der FIDE-Ratingliste (XML) in tl_wertungsportal_elo.
 * Wird als globale Operation (key=importElo) unter WP | FIDE-Elo aufgerufen.
 *
 * Die XML-Datei ist sehr groß (komplette FIDE-Liste, mehrere hundert MB),
 * deshalb läuft alles in kleinen AJAX-Schritten wie beim Personen-Import:
 * - Upload: Die Datei wird clientseitig in 2-MB-Blöcke zerlegt und blockweise
 *   in eine temporäre Datei (system/tmp) hochgeladen. Auch die gezippte
 *   FIDE-Datei wird akzeptiert und vor dem Import serverseitig entpackt.
 * - Import: Je Aufruf wird ein Paket von <player>-Blöcken verarbeitet; der
 *   Byte-Offset wandert mit (Puffer-Parser statt XMLReader, damit jeder
 *   Schritt per fseek an der letzten Position weitermachen kann).
 *
 * Importregel: Die FIDE-Daten überschreiben den Bestand (Upsert per fideid,
 * geschrieben wird nur bei tatsächlichen Änderungen; es wird nichts gelöscht).
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class EloImport extends \Backend
{
	/**
	 * Spieler-Blöcke pro Import-Schritt
	 */
	const SPIELER_PRO_SCHRITT = 2000;

	/**
	 * Lesepuffer-Blockgröße in Bytes
	 */
	const LESEBLOCK = 65536;

	/**
	 * Einstiegspunkt der globalen Operation (key=importElo)
	 *
	 * @param  object $dc DataContainer
	 * @return string     HTML der Importseite
	 */
	public function run($dc)
	{
		// AJAX-Aktionen beantworten (werfen eine ResponseException)
		$aktion = \Input::post('wpEloAktion');
		if($aktion == 'upload') $this->ajaxUpload();
		if($aktion == 'import') $this->ajaxImport();

		// Importseite rendern
		$objTemplate = new \BackendTemplate('be_wp_eloimport');
		$objTemplate->zurueck = str_replace('&key=importElo', '', \Environment::get('request'));

		return $objTemplate->parse();
	}

	/**
	 * Pfad der temporären Upload-Datei (je Backend-Benutzer)
	 */
	protected function tempDatei()
	{
		return TL_ROOT . '/system/tmp/wp-eloimport-' . \BackendUser::getInstance()->id . '.xml';
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
	 * AJAX: Ein Paket von <player>-Blöcken ab dem übergebenen Byte-Offset importieren
	 */
	protected function ajaxImport()
	{
		$offset = (int) \Input::post('offset');
		$datei = $this->tempDatei();

		// Listendatum des Importlaufs: wird vom Client einmalig beim Start
		// erzeugt und mit jedem Schritt mitgeschickt, damit alle Datensätze
		// des Laufs dasselbe elodate/tstamp bekommen
		$elodate = (int) \Input::post('elodate');
		if(!$elodate) $elodate = time();

		if(!file_exists($datei))
		{
			$this->jsonAntwort(array('fehler' => 'Keine hochgeladene Datei gefunden. Bitte den Import neu starten.'));
		}

		// Beim ersten Schritt eine eventuell gezippte FIDE-Datei entpacken
		if($offset == 0)
		{
			$this->entpackeFallsZip($datei);
			clearstatcache();
		}

		$gesamt = filesize($datei);
		$fp = fopen($datei, 'r');
		if($offset > 0) fseek($fp, $offset);

		$puffer = '';
		$verarbeitet = 0;
		$uebersprungen = 0;
		$arrSpieler = array();

		// Datei blockweise lesen und komplette <player>-Blöcke herausschneiden
		while($verarbeitet < self::SPIELER_PRO_SCHRITT && ($block = fread($fp, self::LESEBLOCK)) !== false && $block !== '')
		{
			$puffer .= $block;

			while($verarbeitet < self::SPIELER_PRO_SCHRITT && ($ende = strpos($puffer, '</player>')) !== false)
			{
				$start = strpos($puffer, '<player>');

				if($start === false || $start > $ende)
				{
					// Kein zugehöriger Anfang — Pufferanfang verwerfen
					$puffer = substr($puffer, $ende + 9);
					continue;
				}

				$xml = substr($puffer, $start, $ende + 9 - $start);
				$puffer = substr($puffer, $ende + 9);
				$verarbeitet++;

				$spieler = self::parseSpieler($xml, $elodate);

				if($spieler === null) $uebersprungen++;
				else $arrSpieler[$spieler['fideid']] = $spieler; // Duplikate: letzter gewinnt
			}
		}

		// Konsumierte Bytes = gelesene Position minus unverarbeiteter Pufferrest;
		// nicht verarbeitete Spieler im Puffer werden im nächsten Schritt neu gelesen
		$neuerOffset = ftell($fp) - strlen($puffer);
		$fertig = ($verarbeitet == 0) && feof($fp);
		fclose($fp);

		// Spieler in die Datenbank schreiben (Upsert per fideid)
		$ergebnis = $this->schreibeSpieler($arrSpieler, $elodate);

		// Nach dem letzten Schritt aufräumen: temporäre Datei löschen und den
		// Wertungsportal-Cache leeren — gecachte Seiten tragen die FIDE-Werte
		// eingebacken und würden sonst bis zu 24 h alte Elo/Titel zeigen
		if($fertig)
		{
			if(file_exists($datei)) unlink($datei);
			\Schachbulle\ContaoWertungsportalBundle\Helper\API::purgeCache();
		}

		$this->jsonAntwort(array
		(
			'ok'            => true,
			'offset'        => $neuerOffset,
			'gesamt'        => $gesamt,
			'fertig'        => $fertig,
			'cacheGeleert'  => $fertig,
			'verarbeitet'   => $verarbeitet,
			'uebersprungen' => $uebersprungen,
			'neu'           => $ergebnis['neu'],
			'aktualisiert'  => $ergebnis['aktualisiert'],
			'unveraendert'  => $ergebnis['unveraendert'],
		));
	}

	/**
	 * Entpackt die temporäre Datei, wenn es sich um ein Zip handelt
	 * (die FIDE-Ratingliste wird gezippt angeboten). Die erste XML-Datei
	 * des Archivs ersetzt die temporäre Datei.
	 *
	 * @param string $datei Pfad der temporären Datei
	 */
	protected function entpackeFallsZip($datei)
	{
		$fp = fopen($datei, 'r');
		$magic = fread($fp, 4);
		fclose($fp);

		if($magic != "PK\x03\x04") return; // Keine Zip-Datei

		$zip = new \ZipArchive();

		if($zip->open($datei) !== true)
		{
			$this->jsonAntwort(array('fehler' => 'Die hochgeladene Zip-Datei konnte nicht geöffnet werden.'));
		}

		// Erste XML-Datei im Archiv suchen
		$eintrag = false;
		for($x = 0; $x < $zip->numFiles; $x++)
		{
			$name = $zip->getNameIndex($x);
			if(strtolower(substr($name, -4)) == '.xml')
			{
				$eintrag = $name;
				break;
			}
		}

		if($eintrag === false)
		{
			$zip->close();
			$this->jsonAntwort(array('fehler' => 'Die Zip-Datei enthält keine XML-Datei.'));
		}

		// XML streamend in eine neue Datei entpacken und die Zip-Datei ersetzen
		$quelle = $zip->getStream($eintrag);
		$ziel = fopen($datei . '.entpackt', 'w');

		while(!feof($quelle))
		{
			fwrite($ziel, fread($quelle, self::LESEBLOCK));
		}

		fclose($quelle);
		fclose($ziel);
		$zip->close();

		unlink($datei);
		rename($datei . '.entpackt', $datei);
	}

	/**
	 * Wandelt einen <player>-Block der FIDE-XML in ein Feld-Array für
	 * tl_wertungsportal_elo um. Liefert null bei Parse-Fehlern oder
	 * fehlender FIDE-ID.
	 *
	 * @param  string $xml     XML-Block <player>...</player>
	 * @param  int    $elodate Listendatum des Importlaufs
	 * @return array|null
	 */
	public static function parseSpieler($xml, $elodate)
	{
		$player = @simplexml_load_string($xml);
		if($player === false) return null;

		$fideid = (int) $player->fideid;
		if(!$fideid) return null;

		// Name "Nachname, Vorname[, Zusatz]" zerlegen
		$name = array_map('trim', explode(',', (string) $player->name, 3));

		return array
		(
			'fideid'       => $fideid,
			'elodate'      => (int) $elodate,
			'surname'      => mb_substr($name[0], 0, 64),
			'prename'      => isset($name[1]) ? mb_substr($name[1], 0, 64) : '',
			'intent'       => isset($name[2]) ? mb_substr($name[2], 0, 16) : '',
			'country'      => mb_substr((string) $player->country, 0, 3),
			'sex'          => mb_substr((string) $player->sex, 0, 1),
			'title'        => mb_substr((string) $player->title, 0, 3),
			'w_title'      => mb_substr((string) $player->w_title, 0, 3),
			'o_title'      => mb_substr((string) $player->o_title, 0, 3),
			'foa_title'    => mb_substr((string) $player->foa_title, 0, 3),
			'rating'       => (int) $player->rating,
			'games'        => (int) $player->games,
			'rapid_rating' => (int) $player->rapid_rating,
			'rapid_games'  => (int) $player->rapid_games,
			'blitz_rating' => (int) $player->blitz_rating,
			'blitz_games'  => (int) $player->blitz_games,
			'birthday'     => (int) $player->birthday,
			'flag'         => mb_substr((string) $player->flag, 0, 8),
			'rapid_flag'   => mb_substr((string) $player->rapid_flag, 0, 8),
			'blitz_flag'   => mb_substr((string) $player->blitz_flag, 0, 8),
		);
	}

	/**
	 * Schreibt ein Spieler-Paket in tl_wertungsportal_elo:
	 * Bestand wird per fideid geladen, bestehende Datensätze werden nur bei
	 * Änderungen aktualisiert, neue per Batch-INSERT angelegt (published='1').
	 *
	 * @param  array $arrSpieler fideid => Feld-Array (aus parseSpieler)
	 * @param  int   $elodate    Listendatum = tstamp des Importlaufs
	 * @return array             ['neu' => x, 'aktualisiert' => y, 'unveraendert' => z]
	 */
	protected function schreibeSpieler($arrSpieler, $elodate)
	{
		$ergebnis = array('neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0);
		if(!count($arrSpieler)) return $ergebnis;

		$felder = array_keys(reset($arrSpieler)); // fideid, elodate, surname, ...
		$objDatabase = \Database::getInstance();

		// Bestand blockweise per FIDE-ID laden
		$arrBestand = array();
		foreach(array_chunk(array_keys($arrSpieler), 500) as $chunk)
		{
			$platzhalter = implode(',', array_fill(0, count($chunk), '?'));
			$objRows = $objDatabase->prepare('SELECT id, ' . implode(', ', $felder) . ' FROM tl_wertungsportal_elo WHERE fideid IN (' . $platzhalter . ')')
			                       ->execute($chunk);
			while($objRows->next())
			{
				$arrBestand[(int) $objRows->fideid] = $objRows->row();
			}
		}

		$arrInsert = array();

		foreach($arrSpieler as $fideid => $spieler)
		{
			if(!isset($arrBestand[$fideid]))
			{
				$zeile = array($elodate);
				foreach($felder as $feld) $zeile[] = $spieler[$feld];
				$zeile[] = '1';
				$arrInsert[] = $zeile;
				continue;
			}

			// Nur bei tatsächlichen Änderungen schreiben. elodate bleibt beim
			// Vergleich außen vor, sonst würde jeder Importlauf alle ~1,9 Mio.
			// Datensätze aktualisieren — elodate/tstamp wandern nur mit, wenn
			// sich am Spieler wirklich etwas geändert hat
			$set = array();
			foreach($felder as $feld)
			{
				if($feld == 'elodate') continue;
				if((string) $arrBestand[$fideid][$feld] !== (string) $spieler[$feld]) $set[$feld] = $spieler[$feld];
			}

			if($set)
			{
				$set['elodate'] = $spieler['elodate'];
				$set['tstamp'] = $elodate;
				$objDatabase->prepare('UPDATE tl_wertungsportal_elo %s WHERE id=?')
				            ->set($set)
				            ->execute($arrBestand[$fideid]['id']);
				$ergebnis['aktualisiert']++;
			}
			else
			{
				$ergebnis['unveraendert']++;
			}
		}

		// Neue Datensätze blockweise anlegen
		$spalten = 'tstamp, ' . implode(', ', $felder) . ', published';
		$tupel = '(' . implode(', ', array_fill(0, count($felder) + 2, '?')) . ')';

		foreach(array_chunk($arrInsert, 100) as $chunk)
		{
			$werte = implode(', ', array_fill(0, count($chunk), $tupel));
			$objDatabase->prepare('INSERT INTO tl_wertungsportal_elo (' . $spalten . ') VALUES ' . $werte)
			            ->execute(array_merge(...$chunk));
		}

		$ergebnis['neu'] = count($arrInsert);

		return $ergebnis;
	}

	/**
	 * Sendet eine JSON-Antwort und beendet die Verarbeitung
	 */
	protected function jsonAntwort($daten)
	{
		throw new \Contao\CoreBundle\Exception\ResponseException(new \Symfony\Component\HttpFoundation\JsonResponse($daten));
	}
}
