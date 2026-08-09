<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Rückrufe des Backend-Moduls „Sperren".
 *
 * Die Liste zeigt, wer wegen Massenabfragen gebremst wurde. Diese Klasse
 * bereitet die Zeilen auf und räumt alte Einträge weg.
 */
class Sperrenverwaltung extends \Backend
{
	/**
	 * Tage, nach denen ein Eintrag als alt gilt und weggeräumt werden darf.
	 */
	const AUFBEWAHRUNG = 90;

	/**
	 * Baut die Spalten einer Listenzeile.
	 *
	 * Aufgerufen als label_callback von DC_Table. Zurückgegeben wird ein Array
	 * mit genau so vielen Werten, wie in list.label.fields stehen — sonst
	 * bricht Contao beim Zusammensetzen der Zeile ab.
	 *
	 * @param  array  $row    Datensatz
	 * @param  string $label  Vorgabe von Contao (ungenutzt)
	 * @return array          Werte der Spalten
	 */
	public function zeile($row, $label)
	{
		$gruende = $GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['gruende'] ?? array();

		// Mitglied: Kennung UND Name, denn Namen wiederholen sich und ändern
		// sich, die Kennung nicht
		$mitglied = '&ndash;';

		if((int) ($row['memberId'] ?? 0) > 0)
		{
			$mitglied = \StringUtil::specialchars((string) $row['memberName']).' <span class="wp-meta">(ID '.(int) $row['memberId'].')</span>';
		}

		return array
		(
			\Date::parse(\Config::get('datimFormat'), (int) $row['zeitpunkt']),
			\StringUtil::specialchars((string) $row['ip']),
			$gruende[$row['grund']] ?? $row['grund'],
			(int) $row['anzahl'].' von '.(int) $row['grenze'],
			$mitglied,
		);
	}

	/**
	 * Löscht Einträge, die älter als AUFBEWAHRUNG Tage sind.
	 *
	 * Aufgerufen als globale Operation (key=sperrenAufraeumen). Danach geht es
	 * zurück zur Liste; die Zahl der gelöschten Zeilen steht als Meldung
	 * darüber.
	 *
	 * @return void Leitet um und beendet den Aufruf
	 */
	public function aufraeumen()
	{
		$grenze = time() - self::AUFBEWAHRUNG * 86400;

		$objResult = \Database::getInstance()->prepare("DELETE FROM tl_wertungsportal_sperren WHERE zeitpunkt < ?")
		                                     ->execute($grenze);

		\Message::addConfirmation($objResult->affectedRows.' Einträge älter als '.self::AUFBEWAHRUNG.' Tage gelöscht.');

		\Controller::redirect(str_replace('&key=sperrenAufraeumen', '', \Environment::get('request')));
	}
}
