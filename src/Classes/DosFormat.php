<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Baut die DOS-Fassung der DWZ-Archive im alten Format des DeWIS-Servers.
 *
 * **Warum ein eigenes Format:** Von 1.37.0 bis 1.43.3 war die DOS-Fassung eine
 * Kopie der CSV-Dateien, nur in die Codepage 850 gewandelt — mit Kopfzeile,
 * Kommas, Anführungszeichen und 17 Spalten. Wer die DOS-Dateien einliest,
 * erwartet aber den Aufbau des DeWIS-Servers. Frank am 15.09.2026: „altes
 * Format verwenden mit |-Zeichen als Trennzeichen", Vorlage ist
 * `LV-0-dos_20240627.zip` vom DeWIS-Server; statt der MIVIS/DeWIS-Kennung
 * steht die nu-ID im ersten Feld, alles andere bleibt gleich.
 *
 * **Das Format**, abgelesen an der Vorlage (96.081 Mitgliedschaften, 2.246
 * Vereine, 197 Verbände):
 *
 * * vier Dateien: `SPIELER.TXT`, `VEREINE.TXT`, `VERBAENDE.TXT`, `README.TXT`;
 * * keine Kopfzeile, Felder durch „|" getrennt, keine Anführungszeichen,
 *   Zeilenende CRLF, auch nach der letzten Zeile;
 * * Zeichensatz DOS-Codepage 850 — belegt durch „Ò", „ý" und „´", die in
 *   CP437 andere Zeichen wären;
 * * `SPIELER.TXT`: 14 Felder je Mitgliedschaft, DWZ und Index in EINEM Feld
 *   („2843-103"), ohne DWZ „0-0" — alle 96.081 Zeilen folgen diesem Muster;
 * * `VEREINE.TXT` und `VERBAENDE.TXT`: je vier Felder. In VEREINE.TXT stehen
 *   nur Vereine, keine Kennziffer endet auf „00" (siehe istVerband());
 * * `README.TXT` nennt als Spieler- und Vereinszahl die Zeilen von SPIELER.TXT
 *   und VEREINE.TXT („96081 Spieler in 2246 Vereinen").
 *
 * Die Zeilen behalten die Reihenfolge der nu-Dateien. `spieler.csv` ist dort
 * wie in der Vorlage absteigend nach DWZ sortiert, die Spieler ohne DWZ stehen
 * am Ende.
 *
 * Die Spalten der CSV-Dateien werden über ihre Überschriften zugeordnet, nicht
 * über feste Nummern: Hängt nu Spalten an oder stellt sie um, bleibt die
 * Zuordnung richtig; fehlt eine, bricht die Erzeugung ab und nennt sie.
 *
 * Alle Methoden sind statisch und ohne Contao prüfbar
 * (`tests/Classes/DosFormatTest.php`).
 */
class DosFormat
{
	/**
	 * Felder von SPIELER.TXT in ihrer Reihenfolge: eigener Schlüssel =>
	 * Überschrift in der spieler.csv.
	 *
	 * DWZ und Index sind zwei Spalten der CSV, aber EIN Feld der DOS-Datei
	 * (siehe dwzIndex()). Die nu-Spalten „Vorname" und „Nachname" gibt es im
	 * alten Format nicht; sie werden nicht gebraucht und dürfen fehlen.
	 */
	const SPIELER = array
	(
		'id'                => 'ID',
		'zps'               => 'ZPS',
		'mitgliedsnummer'   => 'Mitgliedsnummer',
		'status'            => 'Status',
		'name'              => 'Name,Vorname',
		'geschlecht'        => 'Geschlecht',
		'spielberechtigung' => 'Spielberechtigung',
		'geburtsjahr'       => 'Geburtsjahr',
		'auswertung'        => 'Letzte Auswertung',
		'dwz'               => 'DWZ',
		'index'             => 'Index',
		'elo'               => 'FIDE-Elozahl',
		'titel'             => 'FIDE-Titel',
		'fideid'            => 'FIDE-ID',
		'land'              => 'FIDE-Land',
	);

	/**
	 * Felder von VEREINE.TXT: eigener Schlüssel => Überschrift in der
	 * vereine.csv.
	 */
	const VEREINE = array
	(
		'zps'           => 'ZPS-Nummer',
		'landesverband' => 'Landesverband',
		'uebergeordnet' => 'UebergeordneterVerband',
		'name'          => 'Vereinsname',
	);

	/**
	 * Felder von VERBAENDE.TXT: eigener Schlüssel => Überschrift in der
	 * verbaende.csv.
	 */
	const VERBAENDE = array
	(
		'nummer'        => 'Verbandnummer',
		'landesverband' => 'Landesverband',
		'uebergeordnet' => 'UebergeordneterVerband',
		'name'          => 'Verbandname',
	);

	/**
	 * Zeichen aus windows-1252, die die Codepage 850 nicht kennt, und ihr
	 * Ersatz aus ASCII. Die fünf in windows-1252 unbelegten Bytes fallen weg.
	 *
	 * Bis 1.43.3 erledigte das `iconv(…//TRANSLIT)`. Auf dem Server von
	 * schachbund.de kam dabei dasselbe heraus — im Archiv vom 15.09.2026 steht
	 * „š" als „s" und „’" als „'" —, das Ergebnis hängt aber an der
	 * iconv-Bibliothek und ihrer Einrichtung; unter glibc im C-Locale wird aus
	 * „š" ein „?". Mit dieser Tabelle bleibt für `iconv` nichts mehr
	 * umzuschreiben: Alle übrigen Zeichen von windows-1252 stehen auch in CP850.
	 */
	const ERSATZ = array
	(
		"\x80" => 'EUR', "\x81" => '',    "\x82" => "'",  "\x84" => '"',  "\x85" => '...',
		"\x86" => '+',   "\x87" => '+',   "\x88" => '^',  "\x89" => '%',  "\x8A" => 'S',
		"\x8B" => '<',   "\x8C" => 'OE',  "\x8D" => '',   "\x8E" => 'Z',  "\x8F" => '',
		"\x90" => '',    "\x91" => "'",   "\x92" => "'",  "\x93" => '"',  "\x94" => '"',
		"\x95" => '*',   "\x96" => '-',   "\x97" => '-',  "\x98" => '~',  "\x99" => 'TM',
		"\x9A" => 's',   "\x9B" => '>',   "\x9C" => 'oe', "\x9D" => '',   "\x9E" => 'z',
		"\x9F" => 'Y',
	);

	/**
	 * Text der README.TXT unterhalb der beiden Kopfzeilen (Verband und Stand).
	 *
	 * Wortgleich mit der Vorlage des DeWIS-Servers bis auf diese Stellen: die
	 * nu-ID als erstes Feld, der Hinweis, daß nu die Spielberechtigung derzeit
	 * leer liefert, und drei Fehler der Vorlage — „Abspache", „FIDE-Masterin"
	 * und „VERBAND.TXT" statt des tatsächlichen Dateinamens VERBAENDE.TXT.
	 * Gespeichert in UTF-8, gewandelt wird in readme().
	 */
	const README_TEXT = <<<'TEXT'
===========================================================================
Eine Veröffentlichung dieser Daten ist nur nach vorheriger Absprache mit dem
zuständigen DWZ-Referenten erlaubt!
===========================================================================

Dateistruktur: (ASCII-Dateien, die Felder sind durch "|" getrennt.)

SPIELER.TXT - sortiert nach DWZ
-----------
- ID des Mitglieds (nu-ID)
- ZPS-Nummer des Vereins
- Mitgliedsnummer im Verein
- Status der Mitgliedschaft
    A - Aktiv
    P - Passiv
- Name,Vorname
- Geschlecht
    M - Männlich
    W - Weiblich
- Spielberechtigung (von nu derzeit leer geliefert)
    D - Deutscher
    G - Gleichgestellt
    E - EU-Ausländer
    A - Ausländer
    S - Sperre
- Geburtsjahr
- Woche der letzten Turnierauswertung (JJJJWW)
- DWZ-Index (0-0 bei Restpartien)
- FIDE-Elozahl
- FIDE-Titel
    CM - Kandidatenmeister         WCM - Kandidatenmeisterin
    FM - FIDE-Meister              WFM - FIDE-Meisterin
    IM - Internationaler Meister   WIM - Internationale Meisterin
    GM - Großmeister               WGM - Großmeisterin
- FIDE-ID
- FIDE-Land

VEREINE.TXT - sortiert nach ZPS-Nummer
-----------
- ZPS-Nummer des Vereins
- Landesverband
- Übergeordneter Verband
- Vereinsname

VERBAENDE.TXT - sortiert nach Verbandnummer
-------------
- Verbandnummer
- Landesverband
- Übergeordneter Verband
- Verbandname

TEXT;

	/**
	 * Baut SPIELER.TXT aus den Zeilen einer spieler.csv.
	 *
	 * @param array $zeilen Zeilen der CSV, wie `fgetcsv()` sie liefert, die
	 *                      Kopfzeile zuerst; die Werte in windows-1252
	 *
	 * @return string Dateiinhalt in CP850 mit CRLF nach jeder Zeile; leer, wenn
	 *                es außer der Kopfzeile nichts gibt
	 *
	 * @throws \RuntimeException wenn die Kopfzeile fehlt oder eine gebrauchte
	 *                           Spalte nicht darin steht
	 */
	public static function spielerTxt(array $zeilen)
	{
		$spalten = self::spalten(array_shift($zeilen), self::SPIELER);
		$raus = '';

		foreach ($zeilen as $z)
		{
			if (self::leer($z)) continue;

			$raus .= self::spielerzeile($z, $spalten)."\r\n";
		}

		return self::nachCp850($raus);
	}

	/**
	 * Baut VEREINE.TXT aus den Zeilen einer vereine.csv.
	 *
	 * Die Verbände, die nu in der vereine.csv zusätzlich wie Vereine aufführt,
	 * bleiben draußen (siehe istVerband()) — in der Vorlage des DeWIS-Servers
	 * endet keine einzige der 2.246 Kennziffern auf „00".
	 *
	 * @param array $zeilen Zeilen der CSV samt Kopfzeile (windows-1252)
	 *
	 * @return string Dateiinhalt in CP850 mit CRLF nach jeder Zeile
	 *
	 * @throws \RuntimeException wenn die Kopfzeile oder eine Spalte fehlt
	 */
	public static function vereineTxt(array $zeilen)
	{
		return self::tabelleTxt($zeilen, self::VEREINE, static function (array $f) {
			return !self::istVerband($f['zps']);
		});
	}

	/**
	 * Baut VERBAENDE.TXT aus den Zeilen einer verbaende.csv.
	 *
	 * Die Zeilen gehen unverändert durch, so wie nu sie liefert — auch die
	 * Wurzel „000" und mehrfach vergebene Nummern.
	 *
	 * @param array $zeilen Zeilen der CSV samt Kopfzeile (windows-1252)
	 *
	 * @return string Dateiinhalt in CP850 mit CRLF nach jeder Zeile
	 *
	 * @throws \RuntimeException wenn die Kopfzeile oder eine Spalte fehlt
	 */
	public static function verbaendeTxt(array $zeilen)
	{
		return self::tabelleTxt($zeilen, self::VERBAENDE);
	}

	/**
	 * Erkennt an der Kennziffer einen Verbandseintrag, der nicht nach
	 * VEREINE.TXT gehört.
	 *
	 * Kennziffern auf „00" bezeichnen Verbände. nu führt sie in der vereine.csv
	 * zusätzlich wie Vereine auf, im LV-0-csv vom 15.09.2026 sind es 181 (etwa
	 * 10000 „Badischer Schachverband e.V."), und keine Mitgliedschaft verweist
	 * auf eine davon.
	 *
	 * **Anders als `Helper::istVerband()`** zählen L0001 und M0001 hier nicht
	 * als Verband: In ihnen sind Mitglieder gemeldet — im LV-0-csv vom
	 * 09.09.2026 129 und 226 Mitgliedschaften —, sie gehören also in
	 * VEREINE.TXT, sonst verwiese SPIELER.TXT auf Vereine, die es dort nicht
	 * gibt.
	 *
	 * @param string $zps Kennziffer, z. B. „10000" oder „30101"
	 *
	 * @return bool true für einen Verband
	 */
	public static function istVerband($zps)
	{
		return substr(trim((string) $zps), -2) === '00';
	}

	/**
	 * Baut README.TXT im Wortlaut der DeWIS-Vorlage.
	 *
	 * Die Verbandszeile und das Datum kommen aus der README.txt der CSV-Fassung,
	 * die `Converter::writeReadme()` schon an den Verband angepaßt hat
	 * („Landesverband: 3 - Berlin", „DWZ-Datenbank vom 15.09.2026 - …"). Die
	 * Zahlen dagegen zählen wie in der Vorlage die Zeilen von SPIELER.TXT und
	 * VEREINE.TXT und stehen ohne Tausendertrenner. Fehlt die Verbandszeile,
	 * bleibt sie leer; fehlt das Datum, gilt $heute.
	 *
	 * @param string      $vorlage README.txt der CSV-Fassung (windows-1252)
	 * @param int         $spieler Zeilen von SPIELER.TXT
	 * @param int         $vereine Zeilen von VEREINE.TXT
	 * @param string|null $heute   Datum TT.MM.JJJJ, falls die Vorlage keines
	 *                             nennt; ohne Angabe das heutige
	 *
	 * @return string Dateiinhalt in CP850 mit CRLF
	 */
	public static function readme($vorlage, $spieler, $vereine, $heute = null)
	{
		$vorlage = (string) $vorlage;

		$kopf = preg_match('/^Landesverband:[^\r\n]*/m', $vorlage, $treffer) ? rtrim($treffer[0]) : 'Landesverband:';
		$datum = preg_match('/DWZ-Datenbank vom ([0-9]{2}\.[0-9]{2}\.[0-9]{4})/', $vorlage, $treffer) ? $treffer[1] : ($heute ?? date('d.m.Y'));
		$stand = 'DWZ-Datenbank vom '.$datum.' - '.(int) $spieler.' Spieler in '.(int) $vereine.' Vereinen';

		// Zeilenenden erst auf LF bringen: Liegt diese Datei nach einem
		// Checkout unter Windows mit CRLF vor, stünde sonst "\r\r\n" im Text
		$text = str_replace("\r\n", "\n", (string) mb_convert_encoding(self::README_TEXT, 'Windows-1252', 'UTF-8'));

		return self::nachCp850($kopf."\r\n\r\n".$stand."\r\n\r\n".str_replace("\n", "\r\n", $text));
	}

	/**
	 * Ordnet die gebrauchten Spalten einer CSV-Datei über die Kopfzeile zu.
	 *
	 * @param array|false|null $kopf   Kopfzeile, wie `fgetcsv()` sie liefert
	 * @param array            $felder Eigener Schlüssel => Überschrift
	 *
	 * @return array Eigener Schlüssel => Spaltennummer
	 *
	 * @throws \RuntimeException wenn die Kopfzeile fehlt oder eine Überschrift
	 *                           nicht darin steht — die Meldung nennt alle
	 *                           fehlenden
	 */
	public static function spalten($kopf, array $felder)
	{
		if (!is_array($kopf) || self::leer($kopf))
		{
			throw new \RuntimeException('Die CSV-Datei hat keine Kopfzeile.');
		}

		// Ein BOM am Dateianfang klebt sonst an der ersten Überschrift
		$kopf = array_values($kopf);
		$kopf[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $kopf[0]);

		$nummern = array();

		foreach ($kopf as $nummer => $ueberschrift)
		{
			$nummern[trim((string) $ueberschrift)] = $nummer;
		}

		$spalten = array();
		$fehlend = array();

		foreach ($felder as $schluessel => $ueberschrift)
		{
			if (isset($nummern[$ueberschrift])) $spalten[$schluessel] = $nummern[$ueberschrift];
			else $fehlend[] = $ueberschrift;
		}

		if (count($fehlend))
		{
			throw new \RuntimeException('Für die DOS-Fassung fehlen Spalten: '.implode(', ', $fehlend));
		}

		return $spalten;
	}

	/**
	 * Baut eine Zeile von SPIELER.TXT, noch in windows-1252 und ohne
	 * Zeilenende.
	 *
	 * @param array $z       Felder der CSV-Zeile
	 * @param array $spalten Zuordnung aus spalten() für self::SPIELER
	 *
	 * @return string 14 Felder, durch „|" getrennt
	 */
	public static function spielerzeile(array $z, array $spalten)
	{
		$f = array();

		foreach (array_keys(self::SPIELER) as $schluessel)
		{
			$f[$schluessel] = self::feld($z[$spalten[$schluessel]] ?? '');
		}

		// DWZ und Index stehen im alten Format in einem Feld
		$f['dwz'] = self::dwzIndex($f['dwz'], $f['index']);
		unset($f['index']);

		return implode('|', $f);
	}

	/**
	 * Setzt DWZ und Index zum Feld „DWZ-Index" zusammen.
	 *
	 * Ohne DWZ steht „0-0" — so hält es die Vorlage bei allen Spielern mit
	 * Restpartien, und ihre README beschreibt es so. Fehlt nur der Index, steht
	 * eine 0 dahinter; in den nu-Dateien kommt das bisher nicht vor.
	 *
	 * @param string $dwz   DWZ, oder leer
	 * @param string $index Index, oder leer
	 *
	 * @return string z. B. „1802-45" oder „0-0"
	 */
	public static function dwzIndex($dwz, $index)
	{
		$dwz = trim((string) $dwz);

		if ($dwz === '' || !ctype_digit($dwz) || (int) $dwz === 0) return '0-0';

		$index = trim((string) $index);

		return $dwz.'-'.($index === '' ? '0' : $index);
	}

	/**
	 * Bereitet einen Feldwert auf.
	 *
	 * Außen stehende Leerzeichen fallen weg. Ein „|" im Wert würde das Feld
	 * zerteilen und wird zu „/", ein Zeilenumbruch oder Tabulator — in einer
	 * CSV-Datei in Anführungszeichen möglich — zu einem Leerzeichen. In den
	 * nu-Dateien vom 15.09.2026 steht kein einziges „|".
	 *
	 * @param mixed $wert Wert aus der CSV
	 *
	 * @return string Aufbereiteter Wert
	 */
	public static function feld($wert)
	{
		return str_replace(array('|', "\r\n", "\r", "\n", "\t"), array('/', ' ', ' ', ' ', ' '), trim((string) $wert));
	}

	/**
	 * Wandelt Text von windows-1252 in die DOS-Codepage 850.
	 *
	 * Erst ersetzt ERSATZ die Zeichen, die CP850 nicht kennt; danach kann
	 * `iconv` ohne `//TRANSLIT` wandeln, und das Ergebnis ist auf jedem Server
	 * dasselbe. Scheitert `iconv` trotzdem, stehen statt der Bytes über 0x7F
	 * Fragezeichen da — ein vollständiges Archiv mit einem entstellten Zeichen
	 * ist besser als ein fehlendes.
	 *
	 * @param string $text Text in windows-1252
	 *
	 * @return string Text in CP850
	 */
	public static function nachCp850($text)
	{
		$text = strtr((string) $text, self::ERSATZ);
		$raus = @iconv('CP1252', 'CP850', $text);

		return $raus === false ? preg_replace('/[\x80-\xFF]/', '?', $text) : $raus;
	}

	/**
	 * Baut eine Datei aus vier Feldern — VEREINE.TXT oder VERBAENDE.TXT.
	 *
	 * @param array         $zeilen   Zeilen der CSV samt Kopfzeile
	 *                                (windows-1252)
	 * @param array         $felder   self::VEREINE oder self::VERBAENDE
	 * @param callable|null $behalten Bekommt die Felder einer Zeile (Schlüssel
	 *                                wie in $felder) und entscheidet, ob sie in
	 *                                die Datei kommt; ohne Angabe alle
	 *
	 * @return string Dateiinhalt in CP850 mit CRLF nach jeder Zeile
	 *
	 * @throws \RuntimeException wenn die Kopfzeile oder eine Spalte fehlt
	 */
	protected static function tabelleTxt(array $zeilen, array $felder, ?callable $behalten = null)
	{
		$spalten = self::spalten(array_shift($zeilen), $felder);
		$raus = '';

		foreach ($zeilen as $z)
		{
			if (self::leer($z)) continue;

			$f = array();

			foreach (array_keys($felder) as $schluessel)
			{
				$f[$schluessel] = self::feld($z[$spalten[$schluessel]] ?? '');
			}

			if ($behalten !== null && !$behalten($f)) continue;

			$raus .= implode('|', $f)."\r\n";
		}

		return self::nachCp850($raus);
	}

	/**
	 * Prüft, ob eine CSV-Zeile leer ist.
	 *
	 * `fgetcsv()` liefert für eine Leerzeile `array(null)`.
	 *
	 * @param mixed $z Zeile
	 *
	 * @return bool true für keine Zeile, eine Leerzeile oder nur leere Felder
	 */
	protected static function leer($z)
	{
		return !is_array($z) || trim(implode('', array_map('strval', $z))) === '';
	}
}
