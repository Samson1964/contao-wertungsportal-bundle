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
	 * Ist einem Referenten eine Adresse aus der Adressverwaltung zugeordnet
	 * (ab 1.51.0), kommen Name und Kontaktdaten von dort — alle Adressen mit
	 * einer Abfrage, siehe Adressverknuepfung::zusammenfuehren().
	 *
	 * @return array VKZ => Liste aufbereiteter Personen
	 */
	protected static function alle()
	{
		$zuordnung = array();
		$zeilen = array();

		try
		{
			$objReferenten = \Contao\Database::getInstance()->execute("SELECT * FROM tl_wertungsportal_referenten WHERE published = '1' ORDER BY nachname, vorname");

			while($objReferenten->next())
			{
				$zeilen[] = $objReferenten->row();
			}
		}
		catch(\Throwable $e)
		{
			// Tabelle fehlt (vor contao:migrate) — dann gibt es eben keine
			return array();
		}

		$adressen = Adressverknuepfung::lade(array_column($zeilen, 'adresse'));

		foreach($zeilen as $zeile)
		{
			$person = self::person(Adressverknuepfung::zusammenfuehren($zeile, $adressen[(int) ($zeile['adresse'] ?? 0)] ?? null));

			foreach(\Contao\StringUtil::deserialize($zeile['verbaende'] ?? null, true) as $vkz)
			{
				$zuordnung[(string) $vkz][] = $person;
			}
		}

		return $zuordnung;
	}

	/**
	 * Bereitet einen Referenten für die Ausgabe auf.
	 *
	 * Die E-Mail-Adresse wird als fertiger, verschleierter Link geliefert
	 * (`email`) und daneben als Klartext (`adresse`) für den Versand einer
	 * Reklamation — die Klartextfassung gehört in kein Template.
	 *
	 * Zur Verschleierung:
	 * StringUtil::encodeEmail wandelt sie in Entities, sodass sie im Quelltext
	 * nicht als Adresse zu lesen ist. Sammler, die stumpf nach „@" suchen,
	 * gehen damit leer aus.
	 *
	 * Erwartet wird die Zeile NACH Adressverknuepfung::zusammenfuehren(): Dort
	 * stehen Titel und Versandadresse, und eine nicht öffentliche Adresse ist
	 * in `email` schon leer, in `versandadresse` aber noch vorhanden. Eine
	 * rohe Zeile geht auch — dann gilt die eigene E-Mail für beides.
	 *
	 * @param  array $row Datensatz aus tl_wertungsportal_referenten, mit Adresse überlagert
	 * @return array      Aufbereitete Felder für das Template
	 */
	protected static function person($row)
	{
		$email = trim((string) ($row['email'] ?? ''));
		$versand = trim((string) ($row['versandadresse'] ?? $email));
		$teile = array(trim((string) ($row['titel'] ?? '')), trim((string) ($row['vorname'] ?? '')), trim((string) ($row['nachname'] ?? '')));

		return array
		(
			'name'     => implode(' ', Adressverknuepfung::ohneLeere($teile)),
			'nachname' => (string) ($row['nachname'] ?? ''),
			'vorname'  => (string) ($row['vorname'] ?? ''),
			'nuid'     => (string) ($row['nuId'] ?? ''),
			'strasse'  => (string) ($row['strasse'] ?? ''),
			'plz'      => (string) ($row['plz'] ?? ''),
			'ort'      => (string) ($row['ort'] ?? ''),
			'telefon'  => (string) ($row['telefon'] ?? ''),
			'email'    => $email !== '' ? \Contao\StringUtil::encodeEmail('<a href="mailto:'.$email.'">'.$email.'</a>') : '',

			// Klartextadresse für die Reklamation (Helper\Reklamation). Sie geht
			// signiert in den Kontext und nie ungeschützt ins Template — auch
			// dann, wenn sie in der Adressverwaltung nicht öffentlich ist
			'adresse'  => $versand,
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
				$objVerbaende = \Contao\Database::getInstance()->execute("SELECT clubVkz, clubName FROM tl_wertungsportal_clubs WHERE clubVkz LIKE '%00' OR clubVkz IN ('L0001','M0001')");

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
