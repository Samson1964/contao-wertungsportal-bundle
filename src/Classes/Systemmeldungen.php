<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Systemmeldungen
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * Systemmeldungen für die Backend-Startseite (Hook getSystemMessages)
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Systemmeldungen
{
	/**
	 * Nach so vielen Tagen ohne Spieler-Import erscheint der Hinweis.
	 *
	 * Bis 1.53.0 erst ab dem 32. Tag (`> 31`); der Abgleich ist monatlich
	 * fällig, also ab dem 30. Tag (Frank, 06.10.2026).
	 */
	public const FRIST_TAGE = 30;

	/**
	 * Warnt auf der Backend-Startseite, wenn der letzte Spieler-Import
	 * (Abgleich mit dem Mitgliederportal) mindestens FRIST_TAGE Tage
	 * zurückliegt oder noch nie erfasst wurde. Ohne den monatlichen Abgleich
	 * veralten die lokalen Spielerdaten — die nu-Schnittstelle liefert
	 * abgemeldete Spieler nicht mehr, Abmeldungen kommen also nur über die
	 * Importe an; betroffen sind die lokale Teilstring-Spielersuche und die
	 * Bestenliste.
	 *
	 * Der Datenstand wird beim Abschluss des CSV-Imports gespeichert
	 * (wertungsportal_personimport = Exportdatum aus dem Dateinamen).
	 *
	 * @return string    Meldung als HTML oder leerer String
	 */
	public function importWarnung()
	{
		$stand = isset($GLOBALS['TL_CONFIG']['wertungsportal_personimport']) ? (int) $GLOBALS['TL_CONFIG']['wertungsportal_personimport'] : 0;

		return self::meldung($stand, time());
	}

	/**
	 * Bildet den Hinweis zum Spieler-Import für einen Datenstand.
	 *
	 * Ohne Contao, damit Frist und Wortlaut prüfbar sind
	 * (tests/Classes/SystemmeldungenTest.php). Der Menüpunkt heißt im Backend
	 * „Spieler", nicht „Personen" — so steht es jetzt auch im Hinweis.
	 *
	 * @param  int $stand Datenstand des letzten Imports als Zeitstempel, 0 für „noch nie"
	 * @param  int $jetzt Aktuelle Zeit als Zeitstempel
	 * @return string     Meldung als HTML; '' solange der Import jünger als
	 *                    FRIST_TAGE Tage ist
	 */
	public static function meldung($stand, $jetzt)
	{
		$wohin = 'Bitte unter Wertungsportal &rarr; Spieler &rarr; CSV-Import die aktuellen Exportdateien des Mitgliederportals importieren';
		$reihenfolge = 'Reihenfolge: Vereinsmitglieder, Abgemeldete im Zeitraum, Angemeldete im Zeitraum';

		if(!$stand)
		{
			return '<p class="tl_error">Wertungsportal: Es ist noch kein Spieler-Import erfasst. '.$wohin.' ('.$reihenfolge.').</p>';
		}

		$tage = (int) floor(($jetzt - $stand) / 86400);

		if($tage >= self::FRIST_TAGE)
		{
			return '<p class="tl_error">Wertungsportal: Der letzte Spieler-Import liegt '.$tage.' Tage zurück (Datenstand '.date('d.m.Y', $stand).'). '.$wohin.' ('.$reihenfolge.'), sonst veralten die lokalen Spielerdaten (Abmeldungen kommen nur über die Importe an).</p>';
		}

		return '';
	}
}
