<?php

/**
 * Contao Open Source CMS
 *
 * @package   Wertungsportal
 * @file      Cacheverwaltung
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Modul „Zwischenspeicher": sucht und löscht einzelne Einträge des
 * Wertungsportal-Caches — wahlweise zu einem Turnier, einem Spieler oder
 * einem Verein.
 *
 * Zweck: Korrigiert nu einen einzelnen Datensatz, war bisher nur das Leeren
 * des gesamten Zwischenspeichers möglich. Danach ist jede Seite wieder
 * langsam, weil alles neu geholt werden muss. Hier fliegt nur weg, was
 * wirklich veraltet ist.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Cacheverwaltung extends \BackendModule
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_wp_cache';

	/**
	 * Baut die Ansicht auf: Formular, gefundene Einträge und das Ergebnis
	 * einer Löschung.
	 *
	 * Gesucht und gelöscht wird über zwei getrennte Schaltflächen — wer nur
	 * nachsehen will, was im Zwischenspeicher liegt, soll dabei nichts
	 * kaputtmachen können.
	 *
	 * @return void
	 */
	protected function compile()
	{
		$arten = \Schachbulle\ContaoWertungsportalBundle\Helper\Cachesuche::ARTEN;

		$art = (string) \Input::post('art');
		$wert = trim((string) \Input::post('wert'));
		$aktion = (string) \Input::post('aktion');

		if(!isset($arten[$art])) $art = 'turnier';

		$this->Template->arten = $arten;
		$this->Template->art = $art;
		$this->Template->wert = $wert;
		$this->Template->gesucht = false;
		$this->Template->geloescht = false;
		$this->Template->eintraege = array();
		$this->Template->fehler = '';
		$this->Template->hinweise = self::hinweise();

		if(\Input::post('FORM_SUBMIT') != 'wp_cache') return;

		if($wert === '')
		{
			$this->Template->fehler = 'Bitte einen Wert eingeben.';

			return;
		}

		$this->Template->gesucht = true;

		if($aktion === 'loeschen')
		{
			$eintraege = \Schachbulle\ContaoWertungsportalBundle\Helper\Cachesuche::loeschen($art, $wert);
			$this->Template->geloescht = true;

			$anzahl = count(array_filter(array_column($eintraege, 'geloescht')));

			\System::log('Wertungsportal: '.$anzahl.' Cache-Einträge zu '.$arten[$art].' „'.$wert.'" gelöscht', __METHOD__, defined('TL_GENERAL') ? TL_GENERAL : 'GENERAL');
		}
		else
		{
			$eintraege = \Schachbulle\ContaoWertungsportalBundle\Helper\Cachesuche::eintraege($art, $wert);
		}

		// Für die Anzeige aufbereiten, damit das Template nichts rechnen muss
		$format = \Config::get('datimFormat');
		$jetzt = time();

		foreach($eintraege as $i => $eintrag)
		{
			$eintraege[$i]['gespeichertText'] = $eintrag['gespeichert'] ? \Date::parse($format, $eintrag['gespeichert']) : '–';
			$eintraege[$i]['groesseText'] = self::groesse($eintrag['groesse']);

			if($eintrag['ablauf'] < 1)
			{
				// 0 steht für „läuft nie ab"
				$eintraege[$i]['ablaufText'] = 'unbegrenzt';
				$eintraege[$i]['abgelaufen'] = false;
			}
			else
			{
				// ablauf ist bereits der Zeitpunkt des Verfalls, keine Dauer
				$eintraege[$i]['ablaufText'] = \Date::parse($format, $eintrag['ablauf']);
				$eintraege[$i]['abgelaufen'] = ($eintrag['ablauf'] < $jetzt);
			}
		}

		$this->Template->eintraege = $eintraege;
	}

	/**
	 * Kurze Erläuterung je Suchart fürs Formular.
	 *
	 * @return array Schlüssel aus ARTEN => Hilfetext
	 */
	protected static function hinweise()
	{
		return array
		(
			'turnier' => 'Die UUID des Turniers, wie sie in der Adresse der Turnierseite steht (z. B. 381efcec-11f4-4fb5-b2d5-051bfcdbaf07). Betroffen sind Kopfdaten, Auswertung, Ergebnisse und alle Spielberichtsbögen dieses Turniers.',
			'spieler' => 'Die nu-Nummer des Spielers (z. B. NU4093214). Betroffen sind Karteikarte, Turnierhistorie und die Spielberichtsbögen dieses Spielers.',
			'verein'  => 'Die fünfstellige Vereinskennziffer (z. B. 30052). Betroffen sind Mitgliederliste und Vereinsname. Für einen Verband die dreistellige Nummer mit zwei Nullen, also 40000 für 400.',
		);
	}

	/**
	 * Formatiert eine Dateigröße lesbar.
	 *
	 * @param  int $bytes Größe in Byte
	 * @return string
	 */
	protected static function groesse($bytes)
	{
		$bytes = (int) $bytes;

		if($bytes >= 1048576) return number_format($bytes / 1048576, 1, ',', '.').' MB';
		if($bytes >= 1024) return number_format($bytes / 1024, 1, ',', '.').' KB';

		return $bytes.' Byte';
	}
}
