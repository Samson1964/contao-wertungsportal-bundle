<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Bereitet die Wertungsreferenten für die Ausgabe im Frontend auf.
 *
 * Zwei Betriebsarten:
 *
 * 1. `baum()` liefert alle Referenten als Gliederung — DSB, darunter die
 *    Landesverbände, darunter deren Bezirke, jeweils mit den zuständigen
 *    Personen. Aufgeführt werden nur Verbände, unter denen tatsächlich jemand
 *    steht, samt ihrer übergeordneten Ebenen; sonst stünden 197 leere Zeilen
 *    in der Liste.
 *
 * 2. `zustaendig()` beantwortet die Frage „wer ist für diese Kennziffer
 *    zuständig?" — und geht dabei die Gliederung hinauf: Hat ein Bezirk
 *    keinen eigenen Referenten, gilt der des Landesverbands.
 *
 * Die E-Mail-Adressen werden über StringUtil::encodeEmail verschleiert, damit
 * sie nicht im Klartext in der Seite stehen.
 */
class Referentenbaum
{
	/**
	 * Namen der Verbände, einmal je Aufruf gelesen.
	 * @var array|null
	 */
	protected static $namen = null;

	/**
	 * Liefert alle veröffentlichten Referenten als Gliederung.
	 *
	 * @return array Liste von Zeilen mit vkz, name, ebene (level_0…3) und
	 *               referenten (aufbereitete Personen)
	 */
	public static function baum()
	{
		$referenten = self::alle();

		if(!count($referenten)) return array();

		// Alle beteiligten Kennziffern samt übergeordneter Ebenen sammeln,
		// damit die Gliederung keine Lücke bekommt
		$vkzListe = array();

		foreach(array_keys($referenten) as $vkz)
		{
			foreach(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::vkzKette($vkz) as $stufe)
			{
				$vkzListe[$stufe] = true;
			}
		}

		// array_keys liefert numerische Schlüssel als Ganzzahlen zurück — aus
		// „10000" würde 10000. Für Kennziffern ist das falsch (führende Nullen
		// gingen verloren) und Vergleiche mit den Werten aus der Datenbank
		// schlügen fehl
		$vkzListe = array_map('strval', array_keys($vkzListe));
		sort($vkzListe, SORT_STRING);

		$zeilen = array();

		foreach($vkzListe as $vkz)
		{
			$zeilen[] = array
			(
				'vkz'        => $vkz,
				'name'       => self::verbandsname($vkz),
				'ebene'      => 'level_'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::verbandsebene($vkz),
				'referenten' => $referenten[$vkz] ?? array(),
			);
		}

		return $zeilen;
	}

	/**
	 * Liefert die für eine Kennziffer zuständigen Referenten.
	 *
	 * Gesucht wird zuerst die Kennziffer selbst, dann der Bezirk, dann der
	 * Landesverband, zuletzt der DSB. Zurückgegeben wird die erste Ebene, auf
	 * der jemand eingetragen ist — mitsamt der Angabe, welche das war, damit
	 * die Ausgabe „zuständig über den Landesverband" kenntlich machen kann.
	 *
	 * @param  string $vkz Kennziffer, ganz oder verkürzt (300 wie 30000)
	 * @return array       vkz, name, ersatzweise (bool) und referenten;
	 *                     referenten ist leer, wenn niemand eingetragen ist
	 */
	public static function zustaendig($vkz)
	{
		$gesucht = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::vkzVoll($vkz);
		$alle = self::alle();

		foreach(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::vkzKette($gesucht) as $stufe)
		{
			if(empty($alle[$stufe])) continue;

			return array
			(
				'vkz'         => $stufe,
				'name'        => self::verbandsname($stufe),
				'ersatzweise' => ($stufe !== $gesucht),
				'referenten'  => $alle[$stufe],
			);
		}

		return array('vkz' => $gesucht, 'name' => self::verbandsname($gesucht), 'ersatzweise' => false, 'referenten' => array());
	}

	/**
	 * Liest alle veröffentlichten Referenten und ordnet sie ihren Verbänden zu.
	 *
	 * Ein Referent kann für mehrere Verbände zuständig sein und taucht dann
	 * unter jedem auf.
	 *
	 * @return array VKZ => Liste aufbereiteter Personen
	 */
	protected static function alle()
	{
		$zuordnung = array();

		try
		{
			$objReferenten = \Database::getInstance()->execute("SELECT * FROM tl_wertungsportal_referenten WHERE published = '1' ORDER BY nachname, vorname");
		}
		catch(\Throwable $e)
		{
			// Tabelle fehlt (vor contao:migrate) — dann gibt es eben keine
			return array();
		}

		while($objReferenten->next())
		{
			$person = self::person($objReferenten->row());

			foreach(\StringUtil::deserialize($objReferenten->verbaende, true) as $vkz)
			{
				$zuordnung[(string) $vkz][] = $person;
			}
		}

		return $zuordnung;
	}

	/**
	 * Bereitet einen Referenten für die Ausgabe auf.
	 *
	 * Die E-Mail-Adresse wird als fertiger, verschleierter Link geliefert:
	 * StringUtil::encodeEmail wandelt sie in Entities, sodass sie im Quelltext
	 * nicht als Adresse zu lesen ist. Sammler, die stumpf nach „@" suchen,
	 * gehen damit leer aus.
	 *
	 * @param  array $row Datensatz aus tl_wertungsportal_referenten
	 * @return array      Aufbereitete Felder für das Template
	 */
	protected static function person($row)
	{
		$email = trim((string) ($row['email'] ?? ''));

		return array
		(
			'name'     => trim(($row['vorname'] ?? '').' '.($row['nachname'] ?? '')),
			'nachname' => (string) ($row['nachname'] ?? ''),
			'vorname'  => (string) ($row['vorname'] ?? ''),
			'nuid'     => (string) ($row['nuId'] ?? ''),
			'strasse'  => (string) ($row['strasse'] ?? ''),
			'plz'      => (string) ($row['plz'] ?? ''),
			'ort'      => (string) ($row['ort'] ?? ''),
			'telefon'  => (string) ($row['telefon'] ?? ''),
			'email'    => $email !== '' ? \StringUtil::encodeEmail('<a href="mailto:'.$email.'">'.$email.'</a>') : '',
		);
	}

	/**
	 * Liefert den Namen eines Verbandes zu seiner Kennziffer.
	 *
	 * @param  string $vkz Fünfstellige Kennziffer
	 * @return string      Name, oder die Kennziffer selbst wenn unbekannt
	 */
	protected static function verbandsname($vkz)
	{
		if(self::$namen === null)
		{
			self::$namen = array('00000' => 'Deutscher Schachbund');

			try
			{
				$objVerbaende = \Database::getInstance()->execute("SELECT clubVkz, clubName FROM tl_wertungsportal_clubs WHERE clubVkz LIKE '%00' OR clubVkz IN ('L0001','M0001')");

				while($objVerbaende->next())
				{
					self::$namen[(string) $objVerbaende->clubVkz] = (string) $objVerbaende->clubName;
				}
			}
			catch(\Throwable $e)
			{
				// Ohne Vereinsbestand bleiben die Kennziffern stehen
			}
		}

		return self::$namen[$vkz] ?? $vkz;
	}

	/**
	 * Setzt den Zwischenspeicher zurück. Nur für Prüfstände gedacht.
	 *
	 * @return void
	 */
	public static function zuruecksetzen()
	{
		self::$namen = null;
	}
}
