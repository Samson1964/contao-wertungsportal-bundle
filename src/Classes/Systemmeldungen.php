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
	 * Warnt auf der Backend-Startseite, wenn der letzte Personen-Import
	 * (Abgleich mit dem Mitgliederportal) länger als 31 Tage zurückliegt
	 * oder noch nie erfasst wurde. Ohne den monatlichen Abgleich veralten
	 * die lokalen Personendaten — die nu-Schnittstelle liefert abgemeldete
	 * Spieler nicht mehr, Abmeldungen kommen also nur über die Importe an;
	 * betroffen sind die lokale Teilstring-Spielersuche und die Bestenliste.
	 *
	 * Der Datenstand wird beim Abschluss des CSV-Imports gespeichert
	 * (wertungsportal_personimport = Exportdatum aus dem Dateinamen).
	 *
	 * @return string    Meldung als HTML oder leerer String
	 */
	public function importWarnung()
	{
		$stand = isset($GLOBALS['TL_CONFIG']['wertungsportal_personimport']) ? (int) $GLOBALS['TL_CONFIG']['wertungsportal_personimport'] : 0;

		if(!$stand)
		{
			return '<p class="tl_error">Wertungsportal: Es ist noch kein Mitgliederdaten-Import erfasst. Bitte unter Wertungsportal &rarr; Personen &rarr; CSV-Import die aktuellen Exportdateien des Mitgliederportals importieren (Reihenfolge: Vereine, Abmeldungen, Anmeldungen).</p>';
		}

		$tage = (int) floor((time() - $stand) / 86400);

		if($tage > 31)
		{
			return '<p class="tl_error">Wertungsportal: Der letzte Mitgliederdaten-Import liegt '.$tage.' Tage zurück (Datenstand '.date('d.m.Y', $stand).'). Bitte unter Wertungsportal &rarr; Personen &rarr; CSV-Import die aktuellen Exportdateien des Mitgliederportals importieren, sonst veralten die lokalen Personendaten (Abmeldungen kommen nur über die Importe an).</p>';
		}

		return '';
	}
}
