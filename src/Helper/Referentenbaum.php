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
	 * Verschachtelt die flachen Zeilen aus baum() zu einem Baum (ab 1.53.0).
	 *
	 * Jeder Verband hängt unter dem nächsten übergeordneten, der in den Zeilen
	 * vorkommt: zuerst erste drei Stellen + 00, dann erste zwei Stellen + 000
	 * (Bezirke in Bayern und Sachsen), dann der Landesverband (erste Stelle +
	 * 0000) — dieselben Stufen wie Helper::vkzKette(). Wer keinen hat, steht
	 * oben.
	 * Weggelassene Kennziffern (vorgegeben: 00000, der DSB) verschwinden samt
	 * ihrer Referenten; die Landesverbände rücken dann nach oben.
	 *
	 * Ohne Contao — die Kette wird hier selbst gebildet, weil Helper nur mit
	 * Contao zu laden ist (Prüfung in tests/Helper/ReferentenbaumTest.php).
	 *
	 * @param array<int,array<string,mixed>> $zeilen    Zeilen aus baum(), nach Kennziffer sortiert
	 * @param array<int,string>              $weglassen Kennziffern, die nicht erscheinen
	 *
	 * @return list<array<string,mixed>> Knoten wie die Zeilen, dazu `kinder`
	 *                                   (Liste von Knoten); Reihenfolge wie in $zeilen
	 */
	public static function verschachtelt(array $zeilen, array $weglassen = array('00000'))
	{
		$knoten = array();

		foreach($zeilen as $zeile)
		{
			$vkz = (string) ($zeile['vkz'] ?? '');

			if($vkz === '' || in_array($vkz, $weglassen, true)) continue;

			$zeile['vkz'] = $vkz;
			$zeile['kinder'] = array();
			$knoten[] = $zeile;
		}

		$vorhanden = array_flip(array_column($knoten, 'vkz'));
		$eltern = array();

		foreach($knoten as $k)
		{
			$eltern[$k['vkz']] = '';

			if(strlen($k['vkz']) !== 5) continue;

			foreach(array(substr($k['vkz'], 0, 3).'00', substr($k['vkz'], 0, 2).'000', substr($k['vkz'], 0, 1).'0000') as $kandidat)
			{
				if($kandidat !== $k['vkz'] && isset($vorhanden[$kandidat]))
				{
					$eltern[$k['vkz']] = $kandidat;
					break;
				}
			}
		}

		$baue = static function ($elter) use (&$baue, $knoten, $eltern)
		{
			$liste = array();

			foreach($knoten as $k)
			{
				if($eltern[$k['vkz']] === $elter)
				{
					$k['kinder'] = $baue($k['vkz']);
					$liste[] = $k;
				}
			}

			return $liste;
		};

		return $baue('');
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
	 * Die Kontaktdaten stammen seit 1.52.0 nur aus der zugeordneten Adresse
	 * (Adressverknuepfung::zusammenfuehren()); dazu kommt die Funktions-E-Mail
	 * des Referats, die in `emails` immer vorn steht. Eine Zeile, die noch
	 * nicht zusammengeführt ist, wird hier ohne Adresse zusammengeführt — so
	 * können die alten Spalten telefon, strasse, plz und ort auf keinem Weg in
	 * eine Ausgabe gelangen.
	 *
	 * Geliefert werden:
	 * - `emails`: verschleierte Links, Funktions-E-Mail zuerst; `email` fasst
	 *   sie mit Komma zusammen (für ältere, angepasste Templates)
	 * - `telefone`: Nummern als Text; `telefon` fasst sie mit Komma zusammen
	 * - `strasse`, `plz`, `ort`: Anschrift, leer wenn nicht öffentlich
	 * - `adresse`: Klartext-Empfänger einer Reklamation — gehört in KEIN
	 *   Template
	 *
	 * Zur Verschleierung:
	 * StringUtil::encodeEmail wandelt die Adressen in Entities, sodass sie im
	 * Quelltext nicht als Adresse zu lesen sind. Sammler, die stumpf nach „@"
	 * suchen, gehen damit leer aus.
	 *
	 * @param  array $row Datensatz aus tl_wertungsportal_referenten, möglichst
	 *                    schon mit der Adresse zusammengeführt
	 * @return array      Aufbereitete Felder für das Template
	 */
	protected static function person($row)
	{
		if(!array_key_exists('ausAdressverwaltung', $row))
		{
			$row = Adressverknuepfung::zusammenfuehren($row, null);
		}

		$teile = array(trim((string) ($row['titel'] ?? '')), trim((string) ($row['vorname'] ?? '')), trim((string) ($row['nachname'] ?? '')));
		$emails = array();

		foreach((array) ($row['emails'] ?? array()) as $email)
		{
			$emails[] = \Contao\StringUtil::encodeEmail('<a href="mailto:'.$email.'">'.$email.'</a>');
		}

		$telefone = array_values((array) ($row['telefone'] ?? array()));

		return array
		(
			'name'     => implode(' ', Adressverknuepfung::ohneLeere($teile)),
			'nachname' => (string) ($row['nachname'] ?? ''),
			'vorname'  => (string) ($row['vorname'] ?? ''),
			'nuid'     => (string) ($row['nuId'] ?? ''),
			'strasse'  => (string) ($row['strasse'] ?? ''),
			'plz'      => (string) ($row['plz'] ?? ''),
			'ort'      => (string) ($row['ort'] ?? ''),
			'telefone' => $telefone,
			'telefon'  => implode(', ', $telefone),
			'emails'   => $emails,
			'email'    => implode(', ', $emails),

			// Klartextadresse für die Reklamation (Helper\Reklamation). Sie geht
			// signiert in den Kontext und nie ungeschützt ins Template — auch
			// dann, wenn sie in der Adressverwaltung nicht öffentlich ist
			'adresse'  => trim((string) ($row['versandadresse'] ?? '')),

			// Die angezeigten Adressen im Klartext — nur zum Vergleich mit dem
			// Referenten von nu (Helper\Zustaendigkeit), gehört in KEIN Template
			'klartext' => array_values((array) ($row['emails'] ?? array())),
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
				// 00000 ist immer der DSB — auf schachbund.de stand dort im
				// Vereinsbestand ein gewöhnlicher Verein (1.53.0)
				$objVerbaende = \Contao\Database::getInstance()->execute("SELECT clubVkz, clubName FROM tl_wertungsportal_clubs WHERE (clubVkz LIKE '%00' OR clubVkz IN ('L0001','M0001')) AND clubVkz <> '00000'");

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
