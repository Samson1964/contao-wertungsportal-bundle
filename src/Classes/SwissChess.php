<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Erzeugt die Hintergrunddateien für Swiss-Chess aus der Deutschland-Datei
 * des Wertungsportals (LV-0-csv).
 *
 * Swiss-Chess liest die Spielerdaten aus einem Dateipaar:
 *
 * * **LST** — ein Datensatz je Zeile, Felder durch Semikolon getrennt,
 *   Zeilenende CRLF, Zeichensatz DOS-Codepage 437.
 * * **SWX** — ein Index über den Namensanfang, damit das Programm nicht die
 *   ganze Liste durchsuchen muß.
 *
 * Es gibt zwei Fassungen, die sich im Inhalt der LST unterscheiden:
 *
 * * `FASSUNG_NEU` für **Swiss-Chess ab 10.0**: alle Felder im Klartext, die
 *   Spielerkennung ist die nuLiga-ID (`NU4005017`).
 * * `FASSUNG_ALT` für ältere Fassungen: die Zahlenfelder stehen binär
 *   kodiert, die Spielerkennung ist eine Zahl.
 *
 * **Woher das Format stammt:** Es ist nirgends dokumentiert. Der Aufbau wurde
 * aus den beiden Originaldateien des DSB abgeleitet (16.08.2023 und
 * 02.09.2026, zusammen 3,2 Millionen Datensätze) und gegengeprüft: Die
 * Zahlenkodierung ist an 4.494.303 Feldern nachgerechnet, die
 * Namensauflösung an 112.824 Spielern, die Indexformel an allen 702
 * Einträgen beider SWX-Dateien. Index, Eimer, Sortierung und Zeichensatz sind
 * seit 1.43.3 zusätzlich an der Datei ausgemessen, die der Swiss-Chess-
 * Programmierer am 09.09.2026 selbst erzeugt hat. Einzelheiten in
 * `docs/swiss-chess.md`.
 *
 * **Woher die Daten kommen:** Die DSB-Mitglieder stehen in der `spieler.csv`
 * der LV-0-csv, ihre FIDE-Angaben und alle übrigen weltweit von der FIDE
 * geführten Spieler in `tl_wertungsportal_elo`. Ist die Tabelle leer, entsteht
 * die Datei allein aus der CSV — dann eben nur mit den Mitgliedern.
 *
 * **Der einzige Wert, der nirgends steht, ist der K-Faktor** (Felder 21 und
 * 24). Das FIDE-XML führt ihn, der Import dieses Bundles legt ihn nicht ab;
 * geschätzt wird er nicht. Einzelheiten in `docs/swiss-chess.md`.
 */
class SwissChess
{
	/**
	 * Fassung für Swiss-Chess ab 10.0: alle Felder im Klartext.
	 */
	const FASSUNG_NEU = 'sc10';

	/**
	 * Fassung für ältere Swiss-Chess-Ausgaben: Zahlenfelder binär kodiert.
	 */
	const FASSUNG_ALT = 'alt';

	/**
	 * Anzahl der Felder je Datensatz. Der letzte ist ein einzelnes
	 * Anführungszeichen — so steht es in den Originaldateien.
	 */
	const FELDER = 29;

	/**
	 * Anzahl der Sätze in der Indexdatei: 26 Anfangsbuchstaben × 27 mögliche
	 * zweite Zeichen, dazu ein Schlußsatz mit der Dateigröße.
	 */
	const INDEXSAETZE = 702;

	/**
	 * Zählerwert des Kopfsatzes der SWX. In beiden Originaldateien steht dort
	 * derselbe Wert; er ist keine Anzahl, sondern eine Kennung.
	 */
	const INDEXKOPF = 0xFFFFFE44;

	/**
	 * Zählerwert des Schlußsatzes (Satz 701) der SWX in der neuen Fassung.
	 *
	 * Satz 701 trägt die Dateigröße der LST und dazu **keine Anzahl, sondern
	 * die Kennung der Fassung**: 0x01000000 in den beiden Dateien von 2026 (der
	 * des DSB vom 02.09. und der des Swiss-Chess-Programmierers vom 09.09.), 0
	 * in der alten Datei des DSB vom 16.08.2023. Eine Anzahl wäre in beiden
	 * Fällen etwas anderes (489 bzw. 374 für den letzten Eimer).
	 *
	 * Bis 1.43.2 stand hier die Satzzahl des letzten Eimers minus eins — weder
	 * die eine noch die andere Kennung. Ein Anwender bekam mit beiden Fassungen
	 * Name und Verein, aber leere Felder für Elo, DWZ und Geburtsjahr.
	 */
	const KENNUNG_NEU = 0x01000000;

	/**
	 * Zählerwert des Schlußsatzes in der alten Fassung, siehe KENNUNG_NEU.
	 */
	const KENNUNG_ALT = 0;

	/**
	 * Rangfolge bei gleichem Sortierschlüssel: reine FIDE-Sätze stehen vor den
	 * DSB-Mitgliedschaften, danach gilt die Reihenfolge des Einlesens. So hält
	 * es die Datei des Programmierers (4.411 zu 85 Gleichständen).
	 */
	const GRUPPE_FIDE = '0';

	/**
	 * Rangfolge der DSB-Mitgliedschaften bei gleichem Sortierschlüssel.
	 */
	const GRUPPE_DSB = '1';

	/**
	 * Umschrift der Sonderzeichen (CP437) für Eimer und Sortierung.
	 *
	 * Aus der Datei des Swiss-Chess-Programmierers vom 09.09.2026 ausgemessen,
	 * Zeichen für Zeichen an allen Nachbarpaaren, in denen es vorkommt:
	 * Umlaute und ß werden ausgeschrieben („Böttcher" steht im Eimer „Bo",
	 * „Özdemir" in „Oe", „Aßmann" in „As"). Zum Grundbuchstaben werden é, â, ë,
	 * ï, Å, ô, á, ó und ú. **Nicht** umgeschrieben, sondern wie ein Leerzeichen
	 * behandelt werden Ç, à, ç, í und ñ — „Çelik" steht im Eimer „El". É und ò
	 * stehen beim Sortieren vor dem Leerzeichen (siehe TIEF). Für è und ì gibt
	 * die Datei keinen Ausschlag; sie zählen wie alle übrigen Zeichen ohne
	 * Eintrag als Trenner.
	 *
	 * Zeichen außerhalb von CP437 kommen gar nicht erst an: nachCp437()
	 * schreibt sie beim Erzeugen der Zeile um („Ó" → „O").
	 */
	const UMSCHRIFT = array
	(
		"\x84" => 'AE', "\x8E" => 'AE', "\x94" => 'OE', "\x99" => 'OE', "\x81" => 'UE', "\x9A" => 'UE', "\xE1" => 'SS',
		"\x82" => 'E', "\x89" => 'E', "\x83" => 'A', "\x8F" => 'A', "\xA0" => 'A',
		"\x8B" => 'I', "\x93" => 'O', "\xA2" => 'O', "\xA3" => 'U',
	);

	/**
	 * Ab hier zählt ein Name für Eimer und Sortierung nicht mehr: an der ersten
	 * Ziffer und an einer Klammer, die ein Wort beginnt.
	 *
	 * Beim Programmierer steht „Muster 2016" wie „Muster" und „Muster (PER)"
	 * wie „Muster"; eine Klammer mitten im Wort — nach dem Muster von
	 * „Kumar(Jharkhand)" — trennt dagegen nur. Schnitte jede Klammer, stünden
	 * 69 Nachbarpaare seiner Datei in anderer Reihenfolge.
	 */
	const SCHNITT = '/(?<![^ ,])\(|[0-9]/';

	/**
	 * Zeichen, die beim Sortieren vor dem Leerzeichen stehen: `, ? und ' direkt
	 * an einem Leerzeichen, einem Komma oder am Namensende, dazu É und ò.
	 *
	 * Beim Programmierer steht „Muster`,Nicola" vor „Muster,Anna",
	 * „Muster,Md? Zaki" vor „Muster,Md Adnan" und „In 't Muster" vor „In,Anna";
	 * mitten im Wort — „D'Avola" — trennen die Zeichen nur. Ohne diese Regel
	 * stünden 164 Nachbarpaare seiner Datei in anderer Reihenfolge, darunter
	 * neun mit DSB-Mitgliedern. Für É und ò gibt es je einen eindeutigen Fall.
	 *
	 * Ersetzt werden die Zeichen durch das Byte 0x1F. Es liegt unter dem
	 * Leerzeichen, aber über dem Tabulator, der in den Eimerdateien den
	 * Schlüssel abschließt — so bleibt „Muster" vor „Muster`,Nicola".
	 */
	const TIEF = '/[`?\'](?=[ ,]|$)|(?<=[ ,])[`?\']|[\x90\x95]/';

	/**
	 * Zeichen in UTF-8 => ihre Bytes in CP437, oder ein Ersatz aus ASCII für
	 * Zeichen, die CP437 nicht kennt.
	 *
	 * Swiss-Chess erwartet die Namen in der Codepage 437. In der Datei des
	 * Programmierers vom 09.09.2026 steht kein Byte, das in CP437 und CP850
	 * Verschiedenes bedeutet; Zeichen, die CP437 nicht kennt, schreibt er in
	 * ASCII um („Ó" → „O", „ý" → „y", „š" → „s", „’" und „´" → „'"). Mit dieser
	 * Tabelle stimmen die Namen aller 100.370 Mitgliedschaften aus dem LV-0-csv
	 * vom selben Tag mit seinen überein, bis auf einen, der schon in den Daten
	 * anders lautet. Bis 1.43.2 ging die Wandlung nach CP850: „´" stand dort als
	 * 0xEF, und das ist in CP437 „∩".
	 *
	 * Bewußt eine feste Tabelle statt `iconv(…//TRANSLIT)`: Deren Ergebnis hängt
	 * an der Bibliothek des Servers. Unter glibc zählt die Sprachumgebung (im
	 * C-Locale wird aus „Ó" ein „?"), libiconv macht aus „Ó" ein „'O". Zeichen,
	 * die weder hier noch in CP437 stehen, fallen weg.
	 */
	const ZEICHEN_CP437 = array
	(
		// In CP437 vorhanden
		'Ç' => "\x80", 'ü' => "\x81", 'é' => "\x82", 'â' => "\x83", 'ä' => "\x84", 'à' => "\x85", 'å' => "\x86", 'ç' => "\x87",
		'ê' => "\x88", 'ë' => "\x89", 'è' => "\x8A", 'ï' => "\x8B", 'î' => "\x8C", 'ì' => "\x8D", 'Ä' => "\x8E", 'Å' => "\x8F",
		'É' => "\x90", 'æ' => "\x91", 'Æ' => "\x92", 'ô' => "\x93", 'ö' => "\x94", 'ò' => "\x95", 'û' => "\x96", 'ù' => "\x97",
		'ÿ' => "\x98", 'Ö' => "\x99", 'Ü' => "\x9A", '¢' => "\x9B", '£' => "\x9C", '¥' => "\x9D", 'ƒ' => "\x9F",
		'á' => "\xA0", 'í' => "\xA1", 'ó' => "\xA2", 'ú' => "\xA3", 'ñ' => "\xA4", 'Ñ' => "\xA5", 'ª' => "\xA6", 'º' => "\xA7",
		'¿' => "\xA8", '¬' => "\xAA", '½' => "\xAB", '¼' => "\xAC", '¡' => "\xAD", '«' => "\xAE", '»' => "\xAF",
		'ß' => "\xE1", 'µ' => "\xE6", '±' => "\xF1", '÷' => "\xF6", '°' => "\xF8", '·' => "\xFA", '²' => "\xFD",
		// Nicht in CP437: Grundbuchstabe
		'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'ã' => 'a', 'Ā' => 'A', 'ā' => 'a', 'Ă' => 'A', 'ă' => 'a', 'Ą' => 'A', 'ą' => 'a',
		'Ć' => 'C', 'ć' => 'c', 'Ĉ' => 'C', 'ĉ' => 'c', 'Ċ' => 'C', 'ċ' => 'c', 'Č' => 'C', 'č' => 'c',
		'Ď' => 'D', 'ď' => 'd', 'Đ' => 'D', 'đ' => 'd', 'Ð' => 'D', 'ð' => 'd',
		'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'ē' => 'e', 'Ĕ' => 'E', 'ĕ' => 'e', 'Ė' => 'E', 'ė' => 'e', 'Ę' => 'E', 'ę' => 'e', 'Ě' => 'E', 'ě' => 'e',
		'Ĝ' => 'G', 'ĝ' => 'g', 'Ğ' => 'G', 'ğ' => 'g', 'Ġ' => 'G', 'ġ' => 'g', 'Ģ' => 'G', 'ģ' => 'g', 'Ĥ' => 'H', 'ĥ' => 'h', 'Ħ' => 'H', 'ħ' => 'h',
		'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ĩ' => 'I', 'ĩ' => 'i', 'Ī' => 'I', 'ī' => 'i', 'Ĭ' => 'I', 'ĭ' => 'i', 'Į' => 'I', 'į' => 'i', 'İ' => 'I', 'ı' => 'i',
		'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k', 'Ĺ' => 'L', 'ĺ' => 'l', 'Ļ' => 'L', 'ļ' => 'l', 'Ľ' => 'L', 'ľ' => 'l', 'Ŀ' => 'L', 'ŀ' => 'l', 'Ł' => 'L', 'ł' => 'l',
		'Ń' => 'N', 'ń' => 'n', 'Ņ' => 'N', 'ņ' => 'n', 'Ň' => 'N', 'ň' => 'n',
		'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'õ' => 'o', 'Ø' => 'O', 'ø' => 'o', 'Ō' => 'O', 'ō' => 'o', 'Ŏ' => 'O', 'ŏ' => 'o', 'Ő' => 'O', 'ő' => 'o', 'Œ' => 'OE', 'œ' => 'oe',
		'Ŕ' => 'R', 'ŕ' => 'r', 'Ŗ' => 'R', 'ŗ' => 'r', 'Ř' => 'R', 'ř' => 'r',
		'Ś' => 'S', 'ś' => 's', 'Ŝ' => 'S', 'ŝ' => 's', 'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's', 'Ș' => 'S', 'ș' => 's',
		'Ţ' => 'T', 'ţ' => 't', 'Ť' => 'T', 'ť' => 't', 'Ț' => 'T', 'ț' => 't', 'Þ' => 'TH', 'þ' => 'th',
		'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ũ' => 'U', 'ũ' => 'u', 'Ū' => 'U', 'ū' => 'u', 'Ŭ' => 'U', 'ŭ' => 'u', 'Ů' => 'U', 'ů' => 'u', 'Ű' => 'U', 'ű' => 'u', 'Ų' => 'U', 'ų' => 'u',
		'Ŵ' => 'W', 'ŵ' => 'w', 'Ý' => 'Y', 'ý' => 'y', 'Ŷ' => 'Y', 'ŷ' => 'y', 'Ÿ' => 'Y',
		'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z', 'ż' => 'z', 'Ž' => 'Z', 'ž' => 'z',
		// Nicht in CP437: Satzzeichen
		'’' => "'", '‘' => "'", '‚' => "'", '´' => "'", '“' => '"', '”' => '"', '„' => '"', '–' => '-', '—' => '-', '…' => '...', "\u{00A0}" => ' ',
	);

	/**
	 * Merker für nachCp437(): bereits gewandelte Zeichen.
	 *
	 * @var array
	 */
	protected static $cp437 = array();

	/**
	 * Hoechstlaenge der Textfelder Name und Verein. In der Originaldatei ist
	 * kein Feld laenger; laengere Vereinsnamen stehen dort abgeschnitten.
	 */
	const TEXTLAENGE = 40;

	/**
	 * Zahlencode der FIDE-Titel im Feld 5.
	 *
	 * Abgelesen aus der Originaldatei: 525 Großmeister tragen dort die 1,
	 * 590 Internationale Meister die 2 und so fort. Die 5 kommt im ganzen
	 * Bestand nicht vor.
	 */
	const TITELCODE = array
	(
		'GM'  => '1',
		'IM'  => '2',
		'FM'  => '3',
		'CM'  => '4',
		'WGM' => '6',
		'WIM' => '7',
		'WFM' => '8',
		'WCM' => '9',
	);

	/**
	 * Titel, die als Frauentitel in Feld 15 wiederholt werden.
	 */
	const FRAUENTITEL = array('WGM', 'WIM', 'WFM', 'WCM');

	/**
	 * Spalten der spieler.csv, die gebraucht werden: eigener Schlüssel =>
	 * Überschrift in der Kopfzeile.
	 *
	 * **Gelesen wird über die Namen, nicht über feste Spaltennummern.** Am
	 * 10.09.2026 hat nu drei Spalten angehängt, und der Erzeuger brach ab,
	 * weil er die Kopfzeile Zeichen für Zeichen verglich. Über die Namen ist
	 * das gleichgültig: Angehängte oder umgestellte Spalten stören nicht,
	 * eine fehlende fällt weiterhin sofort auf (siehe `spaltenZuordnen()`).
	 *
	 * Fehlt eine dieser Spalten, bricht der Lauf ab. Eine stillschweigend
	 * verrutschte Zuordnung wäre schlimmer als gar keine Datei: Sie stünde
	 * erst im Turniersaal in Frage.
	 */
	const CSV_PFLICHT = array
	(
		'id'          => 'ID',
		'zps'         => 'ZPS',
		'mitglnr'     => 'Mitgliedsnummer',
		'status'      => 'Status',
		'name'        => 'Name,Vorname',
		'geschlecht'  => 'Geschlecht',
		'geburtsjahr' => 'Geburtsjahr',
		'dwz'         => 'DWZ',
		'elo'         => 'FIDE-Elozahl',
		'titel'       => 'FIDE-Titel',
		'fideid'      => 'FIDE-ID',
		'land'        => 'FIDE-Land',
	);

	/**
	 * Spalten, die nu seit dem 10.09.2026 zusätzlich liefert.
	 *
	 * Sie sind freiwillig: Fehlen sie — etwa beim Nachbau einer älteren
	 * Fassung —, bleiben die zugehörigen Felder leer oder kommen aus
	 * `tl_wertungsportal_elo`.
	 */
	const CSV_KUER = array
	(
		'frauentitel' => 'FIDE-Frauentitel',
		'schnellelo'  => 'FIDE-Elozahl-Schnellschach',
		'blitzelo'    => 'FIDE-Elozahl-Blitz',
	);

	/**
	 * Vereinsnamen, aufgeschlüsselt nach ZPS. Wird beim ersten Lauf gefüllt.
	 *
	 * @var array
	 */
	protected $vereine = array();

	/**
	 * Spaltennummern der spieler.csv: eigener Schlüssel => Nummer. Gefüllt aus
	 * der Kopfzeile durch `spaltenZuordnen()`.
	 *
	 * @var array
	 */
	protected $spalten = array();

	/**
	 * Zahl der Felder, die eine CSV-Zeile mindestens haben muß, damit alle
	 * Pflichtspalten darin stehen. Kürzere Zeilen werden übergangen.
	 *
	 * @var int
	 */
	protected $mindestfelder = 0;

	/**
	 * Erzeugt ein Dateipaar (LST und SWX) aus einem entpackten LV-0-csv.
	 *
	 * @param string $quelle   Verzeichnis mit spieler.csv und vereine.csv
	 * @param string $ziel     Verzeichnis für die erzeugten Dateien; wird bei
	 *                         Bedarf angelegt
	 * @param string $fassung  self::FASSUNG_NEU oder self::FASSUNG_ALT
	 * @param string $name     Dateiname ohne Endung, üblich ist `fdsbJJMMTT`
	 * @param callable|null $melder Wird je Fortschrittsmeldung mit einem Text
	 *                              aufgerufen; ohne Angabe bleibt es still
	 * @param bool $mitFide Die Sätze um die FIDE-Angaben aus
	 *                      tl_wertungsportal_elo anreichern und alle dort
	 *                      geführten Spieler zusätzlich aufnehmen. Ohne
	 *                      Contao-Datenbank bleibt es bei den DSB-Mitgliedern
	 *
	 * @return array ['lst' => Pfad, 'swx' => Pfad, 'saetze' => Anzahl,
	 *                'dsb' => Anzahl, 'fide' => Anzahl,
	 *                'uebersprungen' => Anzahl]
	 *
	 * @throws \RuntimeException wenn die Quelldateien fehlen oder die
	 *                           Kopfzeile der CSV nicht paßt
	 */
	public function erzeuge($quelle, $ziel, $fassung, $name, $melder = null, $mitFide = true)
	{
		$quelle = rtrim(str_replace('\\', '/', $quelle), '/');
		$ziel = rtrim(str_replace('\\', '/', $ziel), '/');

		$spielerdatei = $quelle.'/spieler.csv';
		$vereinsdatei = $quelle.'/vereine.csv';

		if (!is_file($spielerdatei)) throw new \RuntimeException('spieler.csv fehlt in '.$quelle);
		if (!is_file($vereinsdatei)) throw new \RuntimeException('vereine.csv fehlt in '.$quelle);
		if (!is_dir($ziel) && !@mkdir($ziel, 0777, true)) throw new \RuntimeException('Zielverzeichnis nicht anlegbar: '.$ziel);

		$this->setzeFassung($fassung);
		$this->eimerVorbereiten($ziel.'/.eimer-'.getmypid());

		try
		{
			$this->melde($melder, 'Lese vereine.csv');
			$this->ladeVereine($vereinsdatei);

			$this->melde($melder, 'Lese spieler.csv');
			$saetze = $this->ladeSpieler($spielerdatei, $fassung);

			// FIDE-Angaben der DSB-Mitglieder in einem Zug nachladen, damit
			// nicht je Spieler eine Abfrage läuft
			$fide = array();

			if ($mitFide)
			{
				$this->melde($melder, 'Lade die FIDE-Angaben der Mitglieder');
				$fide = $this->fideDatenFuer(array_column($saetze, 'fideId'));
			}

			$this->melde($melder, 'Verteile '.count($saetze).' Mitgliedschaften auf die Eimer');
			$dsb = 0;
			$uebersprungen = 0;
			$bekannt = array();

			foreach ($saetze as $satz)
			{
				if ($satz['fideId'] > 0) $bekannt[$satz['fideId']] = true;

				$zeile = $this->baueZeile($satz['csv'], $fide[$satz['fideId']] ?? null);

				if ($this->inEimer(self::GRUPPE_DSB, $zeile)) $dsb++;
				else $uebersprungen++;
			}

			// Alle weiteren FIDE-Spieler anhängen — die ohne DSB-Mitgliedschaft
			$fideSaetze = 0;

			if ($mitFide)
			{
				$this->melde($melder, 'Nehme die übrigen FIDE-Spieler auf');
				$fideSaetze = $this->fideSpielerVerteilen($bekannt, $uebersprungen);
			}

			// Eimer der Reihe nach in die LST schreiben
			$lstPfad = $ziel.'/'.$name.'.LST';
			$this->melde($melder, 'Schreibe '.basename($lstPfad));
			$eimer = $this->eimerZusammenfuehren($lstPfad);

			$swxPfad = $ziel.'/'.$name.'.SWX';
			$this->melde($melder, 'Schreibe '.basename($swxPfad));
			$this->schreibeIndex($swxPfad, $eimer, (int) filesize($lstPfad));

			return array
			(
				'lst'           => $lstPfad,
				'swx'           => $swxPfad,
				'saetze'        => $dsb + $fideSaetze,
				'dsb'           => $dsb,
				'fide'          => $fideSaetze,
				'uebersprungen' => $uebersprungen,
			);
		}
		finally
		{
			$this->eimerAufraeumen();
		}
	}

	/**
	 * Liest vereine.csv und merkt sich die Vereinsnamen nach ZPS.
	 *
	 * @param string $datei Pfad zur vereine.csv
	 *
	 * @return void
	 */
	protected function ladeVereine($datei)
	{
		$this->vereine = array();
		$fp = fopen($datei, 'rb');
		fgetcsv($fp);   // Kopfzeile

		while (($z = fgetcsv($fp)) !== false)
		{
			if (count($z) < 4) continue;

			$this->vereine[(string) $z[0]] = (string) $z[3];
		}

		fclose($fp);
	}

	/**
	 * Liest spieler.csv und baut daraus die fertigen LST-Zeilen.
	 *
	 * Es entsteht **eine Zeile je Mitgliedschaft**, nicht je Spieler — genau
	 * so halten es die Originaldateien: Wer in zwei Vereinen gemeldet ist,
	 * steht zweimal in der Liste, jeweils mit seinem Verein.
	 *
	 * @param string $datei   Pfad zur spieler.csv
	 * @param string $fassung self::FASSUNG_NEU oder self::FASSUNG_ALT
	 *
	 * @return array Liste aus ['fideId' => FIDE-Kennung oder 0,
	 *               'csv' => Felder der CSV-Zeile]
	 *
	 * @throws \RuntimeException bei unerwarteter Kopfzeile
	 */
	protected function ladeSpieler($datei, $fassung)
	{
		$fp = fopen($datei, 'rb');
		$this->spaltenZuordnen(fgetcsv($fp));

		$saetze = array();
		
		while (($z = fgetcsv($fp)) !== false)
		{
			if (count($z) < $this->mindestfelder) continue;

			if (trim($this->wert($z, 'name')) === '') continue;

			// Eimer und Sortierschlüssel entstehen erst aus der fertigen Zeile,
			// aus dem Namen in CP437 (siehe inEimer())
			$saetze[] = array
			(
				'fideId' => (int) $this->wert($z, 'fideid'),
				'csv'    => $z,
			);
		}

		fclose($fp);

		return $saetze;
	}

	/**
	 * Ordnet die gebrauchten Spalten der spieler.csv ihren Nummern zu.
	 *
	 * **Warum über die Namen und nicht über feste Nummern:** Am 10.09.2026 hat
	 * nu drei Spalten angehängt (Frauentitel, Schnell- und Blitzwertung). Der
	 * Erzeuger verglich die Kopfzeile bis dahin Zeichen für Zeichen und brach
	 * ab — auf dem Livesystem mitten im Cronjob. Über die Namen ist eine
	 * angehängte oder umgestellte Spalte gleichgültig.
	 *
	 * Die Prüfung wird dadurch nicht schwächer, sondern schärfer: Jede
	 * gebrauchte Spalte muß namentlich dastehen, sonst bricht der Lauf ab. Eine
	 * stillschweigend verrutschte Zuordnung — die DWZ im Elo-Feld — fiele erst
	 * im Turniersaal auf.
	 *
	 * @param array|false $kopf Kopfzeile, wie `fgetcsv()` sie liefert
	 *
	 * @return void
	 *
	 * @throws \RuntimeException wenn eine Pflichtspalte fehlt
	 */
	protected function spaltenZuordnen($kopf)
	{
		if (!is_array($kopf))
		{
			throw new \RuntimeException('Die spieler.csv hat keine Kopfzeile.');
		}

		// Ein BOM am Dateianfang klebt sonst an der ersten Überschrift
		$kopf[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $kopf[0]);

		$nummern = array();

		foreach ($kopf as $nummer => $ueberschrift)
		{
			$nummern[trim((string) $ueberschrift)] = $nummer;
		}

		$this->spalten = array();
		$fehlend = array();

		foreach (self::CSV_PFLICHT as $schluessel => $ueberschrift)
		{
			if (!isset($nummern[$ueberschrift])) $fehlend[] = $ueberschrift;
			else $this->spalten[$schluessel] = $nummern[$ueberschrift];
		}

		if (count($fehlend))
		{
			throw new \RuntimeException(
				"Der spieler.csv fehlen Spalten: ".implode(', ', $fehlend)."\n".
				"  gelesen: ".implode(', ', array_keys($nummern))."\n".
				'Die Spaltenzuordnung muß geprüft werden, bevor Dateien entstehen.'
			);
		}

		// Eine Zeile muß mindestens so viele Felder haben, wie die letzte
		// Pflichtspalte weit hinten steht. Die freiwilligen zählen nicht mit —
		// sonst fiele eine ältere Datei komplett durch
		$this->mindestfelder = max($this->spalten) + 1;

		foreach (self::CSV_KUER as $schluessel => $ueberschrift)
		{
			if (isset($nummern[$ueberschrift])) $this->spalten[$schluessel] = $nummern[$ueberschrift];
		}
	}

	/**
	 * Liest ein Feld der CSV-Zeile über seinen Spaltenschlüssel.
	 *
	 * Eine Spalte, die die Datei nicht führt — die drei freiwilligen —, liefert
	 * einen leeren Text statt einer Warnung.
	 *
	 * @param array  $z           Felder der CSV-Zeile
	 * @param string $schluessel  Schlüssel aus CSV_PFLICHT oder CSV_KUER
	 *
	 * @return string Inhalt der Spalte, oder leer
	 */
	protected function wert(array $z, $schluessel)
	{
		if (!isset($this->spalten[$schluessel])) return '';

		return (string) ($z[$this->spalten[$schluessel]] ?? '');
	}

	/**
	 * Baut aus einer CSV-Zeile die fertige LST-Zeile samt Zeilenende.
	 *
	 * @param array      $z    Felder der CSV-Zeile
	 * @param array|null $fide Zeile aus tl_wertungsportal_elo, falls vorhanden
	 *
	 * @return string Zeile in CP437 mit CRLF am Ende
	 */
	protected function baueZeile(array $z, ?array $fide = null)
	{
		$zps = $this->wert($z, 'zps');
		$titel = strtoupper(trim($this->wert($z, 'titel')));
		$land = trim($this->wert($z, 'land'));

		$fideId = $this->ziffern($this->wert($z, 'fideid'));
		$elo = $this->ziffern($this->wert($z, 'elo'));

		$f = array_fill(0, self::FELDER, '');

		// Name und Vereinsname werden auf 40 Zeichen gekürzt — so hält es die
		// Originaldatei, in der kein Feld länger ist
		$f[0]  = $this->text($this->wert($z, 'name'));
		$f[1]  = $this->text($this->vereine[$zps] ?? '');

		// Die Nation steht NUR bei Spielern mit FIDE-Eintrag. In der
		// Originaldatei hat kein einziger der 57.369 Sätze ohne FIDE-Kennung
		// eine Nation — ein pauschales „GER" wäre also falsch
		// Nennt die CSV trotz FIDE-Kennung kein Land, gilt die Föderation aus der
		// Elo-Tabelle — so hält es die Datei des Programmierers
		if ($land === '' && $fide !== null) $land = trim((string) $fide['country']);

		$f[2]  = $fideId !== '' ? $this->text($land) : '';

		$f[3]  = $this->zahl($elo);
		$f[4]  = $this->dwzFeld($this->ziffern($this->wert($z, 'dwz')));
		$f[5]  = self::TITELCODE[$titel] ?? '';
		$f[6]  = $this->zahl($this->ziffern($this->wert($z, 'geburtsjahr')));
		// Die Spielerkennung ist die nuLiga-ID (NU4005017). Die alte Fassung
		// erwartet hier eine binär kodierte Zahl — die alte PKZ —, und eine
		// Buchstabenfolge läßt sich so nicht schreiben. Dort bleibt das Feld
		// deshalb leer, wie schon in der Umwandlung von 1999
		$f[7]  = $this->fassung === self::FASSUNG_ALT ? '' : $this->text($this->wert($z, 'id'));
		$f[8]  = $this->zahl($fideId);
		$f[9]  = $this->zahl($this->ziffern($this->wert($z, 'mitglnr')));
		$f[10] = $this->text($this->wert($z, 'geschlecht'));
		$f[11] = $this->text($zps);
		// Erste Stelle der Kennziffer = Landesverband
		$f[12] = $zps !== '' ? substr($zps, 0, 1) : '';
		// Feld 13 wiederholt die Mitgliedsnummer — in der alten Datei des DSB
		// von 2023 im Klartext, obwohl Feld 9 dort binär kodiert ist
		$f[13] = $this->ziffern($this->wert($z, 'mitglnr'));
		$f[14] = $this->text($this->wert($z, 'status'));

		// Die Felder 15 bis 27 tragen die FIDE-Angaben. Der vollständige Satz
		// steht in tl_wertungsportal_elo — nur dort gibt es Partienzahlen,
		// Kennzeichen sowie Amts- und Arena-Titel. Die CSV führt seit dem
		// 10.09.2026 immerhin Frauentitel, Schnell- und Blitzwertung; sie
		// springt ein, wenn zu einem Spieler kein Elo-Satz vorliegt
		if ($fide !== null)
		{
			$this->setzeFideFelder($f, $fide);

			// Die CSV hat eine einzige Spalte für den offenen Titel: Führt eine
			// Spielerin einen solchen, steht dort dieser, und der Frauentitel
			// kommt aus der eigenen Spalte oder der Elo-Tabelle
			if ($f[5] === '' && $fide['title'] !== '') $f[5] = self::TITELCODE[$fide['title']] ?? '';
		}
		elseif ($fideId !== '')
		{
			// Ohne Satz in der Elo-Tabelle bleibt nur, was die CSV hergibt.
			// Ein Spieler ohne FIDE-Kennung bekommt gar nichts — in der
			// Originaldatei sind bei allen 57.369 solchen Sätzen die Felder
			// 15 bis 27 ausnahmslos leer
			// Partienzahlen und Kennzeichen führt die CSV nicht — die Felder
			// 20, 23, 26 und 27 bleiben leer
			$f[15] = $this->frauentitel($z, $titel);
			$f[19] = $elo;
			$f[22] = $this->ziffern($this->wert($z, 'schnellelo'));
			$f[25] = $this->ziffern($this->wert($z, 'blitzelo'));
		}

		return $this->schlussFelder($f);
	}

	/**
	 * Ermittelt den Frauentitel für eine CSV-Zeile ohne Satz in der
	 * Elo-Tabelle.
	 *
	 * Seit dem 10.09.2026 führt die spieler.csv eine eigene Spalte dafür — bis
	 * dahin ließ sich der Titel nur in dem einen Fall erkennen, in dem eine
	 * Spielerin gar keinen offenen Titel hat und deshalb ihr Frauentitel in
	 * der allgemeinen Titelspalte steht. Beide Wege bleiben, damit sich auch
	 * eine ältere Datei noch verarbeiten läßt.
	 *
	 * @param array  $z     Felder der CSV-Zeile
	 * @param string $titel Inhalt der allgemeinen Titelspalte, in Großschrift
	 *
	 * @return string Frauentitel (WGM, WIM, WFM, WCM), oder leer
	 */
	protected function frauentitel(array $z, $titel)
	{
		$eigen = strtoupper(trim($this->wert($z, 'frauentitel')));

		if (in_array($eigen, self::FRAUENTITEL, true)) return $eigen;

		return in_array($titel, self::FRAUENTITEL, true) ? $titel : '';
	}

	/**
	 * Baut die Zeile eines Spielers, der nur bei der FIDE geführt wird.
	 *
	 * Solche Sätze machen den Löwenanteil der Datei aus — in der
	 * Originaldatei des DSB sind es 1.873.566 von 1.973.816. Sie haben weder
	 * Verein noch DWZ, weder nuLiga-Kennung noch Mitgliedsnummer; alle
	 * Vereinsfelder bleiben leer.
	 *
	 * @param array $e Zeile aus tl_wertungsportal_elo
	 *
	 * @return string Zeile in CP437 mit CRLF am Ende
	 */
	protected function baueFideZeile(array $e)
	{
		$f = array_fill(0, self::FELDER, '');

		$f[0]  = $this->text($this->fideName($e));
		$f[2]  = $this->text((string) $e['country']);
		$f[3]  = $this->zahl($this->ziffern((string) $e['rating']));
		$f[4]  = $this->dwzFeld('');
		$f[5]  = self::TITELCODE[(string) $e['title']] ?? '';
		$f[6]  = $this->zahl($this->ziffern((string) $e['birthday']));
		$f[8]  = $this->zahl($this->ziffern((string) $e['fideid']));
		// Die Elo-Tabelle führt das Geschlecht als M/F, die Datei als M/W
		$f[10] = ((string) $e['sex'] === 'F') ? 'W' : (string) $e['sex'];

		$this->setzeFideFelder($f, $e);

		return $this->schlussFelder($f);
	}

	/**
	 * Trägt die FIDE-Angaben in die Felder 15 bis 27 ein.
	 *
	 * Die Aufteilung ist aus der Originaldatei abgelesen und entspricht genau
	 * den dreizehn Angaben, die das FIDE-XML je Spieler außer der Kennung
	 * führt:
	 *
	 *     15 Frauentitel      16 Amtstitel        17 Kennzeichen
	 *     18 Arena-Titel      19 Elo              20 Partien
	 *     21 K-Faktor         22 Schnell-Elo      23 Schnell-Partien
	 *     24 Schnell-K        25 Blitz-Elo        26 Blitz-Partien
	 *     27 wie Feld 26
	 *
	 * **Feld 27 ist kein Blitz-K-Faktor.** In allen 1.973.816 Sätzen der
	 * Originaldatei steht dort dasselbe wie in Feld 26 — der Erzeuger des DSB
	 * schreibt die Blitzpartien schlicht zweimal. Nachgebaut wird das so, wie
	 * es die Datei tut.
	 *
	 * **Die K-Faktoren (21 und 24) bleiben leer, solange eine Wertung
	 * vorliegt.** Das FIDE-XML führt sie, der Import dieses Bundles legt sie
	 * aber nicht ab — tl_wertungsportal_elo hat keine Spalte dafür. Sie zu
	 * schätzen ginge nicht: Über 2400 wäre es immer 10, darunter hängt der
	 * Wert an der Zahl der bisher gewerteten Partien über die ganze Laufbahn,
	 * und die steht nirgends. Siehe `kFaktor()`.
	 *
	 * @param array $f Felder der Zeile, wird verändert
	 * @param array $e Zeile aus tl_wertungsportal_elo
	 *
	 * @return void
	 */
	protected function setzeFideFelder(array &$f, array $e)
	{
		$f[15] = $this->text((string) $e['w_title']);
		// Schiedsrichter- und Trainertitel: NA, FA, IA, FT und so fort
		$f[16] = $this->text((string) $e['o_title']);
		$f[17] = $this->text((string) $e['flag']);
		// Titel der FIDE Online Arena (AGM, AIM, AFM, ACM) — ein eigenes Feld,
		// nicht etwa ein Ersatz für den Amtstitel
		$f[18] = $this->text((string) $e['foa_title']);

		$f[19] = $this->ziffern((string) $e['rating']);
		$f[20] = $this->ziffern((string) $e['games']);

		$f[22] = $this->ziffern((string) $e['rapid_rating']);
		$f[23] = $this->ziffern((string) $e['rapid_games']);

		$f[25] = $this->ziffern((string) $e['blitz_rating']);
		$f[26] = $this->ziffern((string) $e['blitz_games']);
		$f[27] = $f[26];
	}

	/**
	 * Liefert den Inhalt eines K-Faktor-Feldes.
	 *
	 * Ohne Wertung trägt die Originaldatei dort eine „0" ein — das ist
	 * ausgezählt: In allen 1.346.756 Sätzen mit FIDE-Kennung und ohne
	 * Standardwertung steht genau diese Null, ohne eine einzige Ausnahme.
	 * Liegt eine Wertung vor, steht im Original der echte Faktor (10, 20 oder
	 * 40); den kennt dieses Bundle nicht, deshalb bleibt das Feld dann leer.
	 *
	 * Ein geratener Wert wäre schlimmer als keiner: Swiss-Chess rechnet damit
	 * Wertungsänderungen aus. Wie man ihn richtig bekommt, steht in der
	 * TODO.md — es braucht drei Spalten in tl_wertungsportal_elo und eine
	 * Erweiterung des XML-Imports.
	 *
	 * @param string $wertung Inhalt des zugehörigen Wertungsfeldes
	 *
	 * @return string „0" ohne Wertung, sonst leer
	 */
	protected function kFaktor($wertung)
	{
		return $wertung === '' ? '0' : '';
	}

	/**
	 * Setzt die Anführungszeichen um die Felder 15 bis 27 und hängt das
	 * Schlußfeld an.
	 *
	 * Hier fallen auch die beiden K-Faktor-Felder an: Sie hängen daran, ob
	 * überhaupt eine Wertung vorliegt, und lassen sich deshalb erst setzen,
	 * wenn alle übrigen Felder stehen.
	 *
	 * @param array $f Felder der Zeile
	 *
	 * @return string Fertige Zeile mit CRLF
	 */
	protected function schlussFelder(array $f)
	{
		// Ohne FIDE-Kennung bleibt der ganze Block leer, mit Kennung tragen
		// die K-Felder mindestens die Null
		if ($f[8] !== '')
		{
			$f[21] = $this->kFaktor($f[19]);
			$f[24] = $this->kFaktor($f[22]);
		}

		for ($i = 15; $i <= 27; $i++)
		{
			$f[$i] = '"'.$f[$i].'"';
		}

		// Das letzte Feld ist ein einzelnes Anführungszeichen
		$f[28] = '"';

		return implode(';', $f)."\r\n";
	}

	/**
	 * Setzt den Namen eines FIDE-Spielers in der Form „Nachname,Vorname"
	 * zusammen.
	 *
	 * Der Import hat den Namen der FIDE am Komma getrennt. Wer keinen Vornamen
	 * hat — bei der FIDE nicht selten —, behält nur den Nachnamen, ohne
	 * nachlaufendes Komma.
	 *
	 * @param array $e Zeile aus tl_wertungsportal_elo
	 *
	 * @return string Name
	 */
	protected function fideName(array $e)
	{
		$nach = trim((string) $e['surname']);
		$vor = trim((string) $e['prename']);

		return $vor !== '' ? $nach.','.$vor : $nach;
	}

	/**
	 * Bereitet einen Zahlenwert aus der CSV auf.
	 *
	 * Eine Null bedeutet in der CSV „kein Wert" und wird zu einem leeren Feld:
	 * In der Originaldatei kommt weder bei der Elo noch bei der DWZ eine „0"
	 * vor. Nicht numerische Angaben fallen ebenfalls weg.
	 *
	 * @param string $wert Rohwert aus der CSV
	 *
	 * @return string Nur Ziffern, oder leer
	 */
	protected function ziffern($wert)
	{
		$wert = ltrim(trim($wert), '0');

		return ($wert !== '' && ctype_digit($wert)) ? $wert : '';
	}

	/**
	 * Bereitet ein Textfeld auf: Wandlung nach CP437 und Kürzung auf 40
	 * Zeichen.
	 *
	 * **Wichtig:** Nur Textfelder dürfen durch die Kodierwandlung laufen. Die
	 * Zahlenfelder der alten Fassung enthalten Bytes, die in windows-1252
	 * Sonderzeichen sind (0x82 etwa ist ein Anführungszeichen); eine Wandlung
	 * der ganzen Zeile zerstörte sie. Genau das ist beim ersten Versuch
	 * passiert — aus 0x82 wurde 0x60, und die Zahl war unlesbar.
	 *
	 * Da CP437 ein Einbyte-Zeichensatz ist, entspricht die Kürzung auf 40
	 * Bytes genau 40 Zeichen; ein Umlaut kann dabei nicht zerschnitten werden.
	 *
	 * @param string $wert Text in windows-1252 (spieler.csv, vereine.csv) oder
	 *                     UTF-8 (Elo-Tabelle)
	 *
	 * @return string Text in CP437, höchstens 40 Zeichen
	 */
	protected function text($wert)
	{
		// Anführungszeichen fliegen raus: In der Originaldatei trägt kein
		// einziger der 300.000 geprüften Vereinsnamen eines. Sie würden auch
		// die Felder 15 bis 28 nachahmen, die genau daran zu erkennen sind.
		// Erst nach der Wandlung, die aus „ und “ selbst welche macht
		$raus = str_replace('"', '', self::nachCp437((string) $wert));

		// rtrim NACH dem Kuerzen: Sonst bliebe bei einem abgeschnittenen
		// Vereinsnamen ein Leerzeichen am Ende stehen; die Originaldatei hat
		// dort keines
		return rtrim(substr($raus, 0, self::TEXTLAENGE));
	}

	/**
	 * Bereitet ein Zahlenfeld für die gewünschte Fassung auf.
	 *
	 * In der neuen Fassung steht die Zahl im Klartext, in der alten binär
	 * kodiert. Ein leerer oder nicht numerischer Wert bleibt leer.
	 *
	 * Welche Fassung gilt, steht in der Eigenschaft `$fassung`; sie wird zu
	 * Beginn von erzeuge() gesetzt. So bleibt der Aufruf in baueZeile() kurz,
	 * wo die Methode zwanzigmal vorkommt.
	 *
	 * @param string $wert Rohwert aus der CSV
	 *
	 * @return string Klartext oder Bytefolge
	 */
	protected function zahl($wert)
	{
		$wert = trim($wert);

		if ($wert === '' || !ctype_digit($wert)) return '';

		return $this->fassung === self::FASSUNG_ALT ? self::verschluessle($wert) : $wert;
	}

	/**
	 * Bereitet das DWZ-Feld auf.
	 *
	 * Wie zahl(), mit einer Ausnahme in der alten Fassung: Ohne DWZ steht dort
	 * kein leeres Feld, sondern die kodierte „0000" (die Bytes `nn`). So hält
	 * es die alte Datei des DSB von 2023 bei allen 1.284.415 Sätzen ohne DWZ,
	 * und so schrieb es schon die Umwandlung von 1999 — bei der Elo dagegen
	 * bleibt das Feld dort leer.
	 *
	 * @param string $wert Ziffern der DWZ, oder leer
	 *
	 * @return string Klartext, Bytefolge oder leer
	 */
	protected function dwzFeld($wert)
	{
		if ($wert === '' && $this->fassung === self::FASSUNG_ALT) return self::verschluessle('0000');

		return $this->zahl($wert);
	}

	/**
	 * Aktuelle Fassung während eines Laufs.
	 *
	 * @var string
	 */
	protected $fassung = self::FASSUNG_NEU;

	/**
	 * Kodiert eine Zahl in die Bytefolge der alten Fassung.
	 *
	 * Die Regel ist aus den Originaldateien abgeleitet und an 4.494.303
	 * Feldern nachgerechnet:
	 *
	 *   Byte 100..109 (0x64..0x6D)  =  EINE Ziffer  (Byte − 100)
	 *   Byte 110..209 (0x6E..0xD1)  =  ZWEI Ziffern (Byte − 110)
	 *
	 * Die Ziffern werden von links nach rechts aneinandergehängt. Weil sich
	 * die beiden Bytebereiche nicht überschneiden, weiß der Leser ohne weitere
	 * Angabe, ob ein Byte für eine oder zwei Ziffern steht. Bei ungerader
	 * Ziffernzahl steht die LETZTE Ziffer allein — so machen es die
	 * Originaldateien, und nur so entstehen wieder dieselben Bytes.
	 *
	 * Beispiel: 7127030 wird zu 71 | 27 | 03 | 0 und damit zu B5 89 71 64.
	 *
	 * @param string $zahl Nur Ziffern
	 *
	 * @return string Bytefolge, leer wenn die Eingabe keine Zahl ist
	 */
	public static function verschluessle($zahl)
	{
		if ($zahl === '' || !ctype_digit($zahl)) return '';

		$raus = '';
		$i = 0;
		$paare = (int) (strlen($zahl) / 2);

		for ($p = 0; $p < $paare; $p++)
		{
			$raus .= chr(110 + (int) substr($zahl, $i, 2));
			$i += 2;
		}

		if ($i < strlen($zahl)) $raus .= chr(100 + (int) $zahl[$i]);

		return $raus;
	}

	/**
	 * Liest eine binär kodierte Zahl der alten Fassung wieder aus.
	 *
	 * Gegenstück zu verschluessle(); gebraucht wird es zum Prüfen der
	 * erzeugten Dateien.
	 *
	 * @param string $roh Bytefolge
	 *
	 * @return string|null Zahl als Zeichenkette, null sobald ein Byte
	 *                     außerhalb von 100..209 liegt
	 */
	public static function entschluessle($roh)
	{
		$raus = '';

		for ($i = 0; $i < strlen($roh); $i++)
		{
			$b = ord($roh[$i]);

			if ($b >= 100 && $b <= 109) $raus .= (string) ($b - 100);
			elseif ($b >= 110 && $b <= 209) $raus .= sprintf('%02d', $b - 110);
			else return null;
		}

		return $raus;
	}

	/**
	 * Liefert die Satznummer der Indexdatei zu einem Namen.
	 *
	 * Der Index teilt nach den ersten beiden Zeichen des Suchschlüssels auf
	 * (siehe suchschluessel()): 26 Anfangsbuchstaben mal 27 Möglichkeiten für
	 * das zweite Zeichen — 0 für „kein Buchstabe", sonst 1..26 für A..Z. Die
	 * Formel ist an allen 702 Einträgen der Originaldateien geprüft, die
	 * Umschrift an allen 1.976.362 Sätzen der Datei des Swiss-Chess-
	 * Programmierers vom 09.09.2026. Abweichend sind dort nur 17 der 20 Namen,
	 * deren Schlüssel aus einem einzigen Buchstaben besteht („B", „D", „O" …):
	 * Er stellt sie vor ihren Eimer, ohne sie mitzuzählen; hier gehören sie in
	 * den Eimer „Buchstabe + kein Buchstabe".
	 *
	 * Bis 1.43.2 zählte das rohe zweite Byte: „Böttcher" landete im Eimer
	 * „B + kein Buchstabe" statt in „Bo", und Namen mit Ö, Ü oder Ç am Anfang
	 * fehlten ganz.
	 *
	 * @param string $name Name wie in Feld 0 (CP437)
	 *
	 * @return int|null Satznummer 0..701, null wenn der Name vor dem Schnitt
	 *                  (SCHNITT) keinen Buchstaben hat
	 */
	public static function eimer($name)
	{
		$schluessel = self::suchschluessel($name);

		if ($schluessel === '') return null;

		$zweit = (strlen($schluessel) > 1 && $schluessel[1] !== ' ') ? ord($schluessel[1]) - 64 : 0;

		return (ord($schluessel[0]) - 65) * 27 + $zweit;
	}

	/**
	 * Bildet den Suchschlüssel eines Namens, aus dem sich der Eimer ergibt.
	 *
	 * Die Regeln sind aus der Datei des Swiss-Chess-Programmierers ausgemessen:
	 *
	 * * Ab der ersten Ziffer oder einer Klammer, die ein Wort beginnt, zählt
	 *   nichts mehr (SCHNITT) — Zusätze wie „(PER)" oder „2016" in FIDE-Namen
	 *   bleiben außen vor.
	 * * Umlaute und ß werden ausgeschrieben (Ö → OE, ß → SS), Akzente fallen
	 *   weg (é → E); siehe UMSCHRIFT.
	 * * Alles andere außer A..Z — Komma, Punkt, Bindestrich, Apostroph, ein
	 *   unbekanntes Sonderzeichen — trennt nur: Folgen davon werden zu einem
	 *   Leerzeichen, am Rand fallen sie weg.
	 * * Groß- und Kleinschreibung spielt keine Rolle.
	 *
	 * @param string $name Name wie in Feld 0 (CP437)
	 *
	 * @return string Nur A..Z und einzelne Leerzeichen, oder leer
	 */
	public static function suchschluessel($name)
	{
		$name = preg_split(self::SCHNITT, (string) $name, 2)[0];
		$name = strtoupper(strtr($name, self::UMSCHRIFT));

		return trim(preg_replace('/[^A-Z]+/', ' ', $name));
	}

	/**
	 * Bildet den Schlüssel, nach dem die Sätze innerhalb eines Eimers
	 * sortiert werden.
	 *
	 * Der Suchschlüssel mit zwei Unterschieden: „SZ" zählt wie „SS", und die
	 * Zeichen aus TIEF stehen vor dem Leerzeichen. Nur so stehen die Namen in
	 * derselben Reihenfolge wie in der Datei des Programmierers — „Kaszab" vor
	 * „Kassel", „Muster,Szilvia" vor „Muster,Stefan". Für den Eimer gilt beides
	 * nicht, „Szabo" bleibt in „Sz".
	 *
	 * Gegen die Reihenfolge seiner Datei vom 09.09.2026 bleiben damit 13 von
	 * 1.976.362 Nachbarpaaren abweichend, keines davon mit einem DSB-Mitglied:
	 * acht FIDE-Namen, bei denen er „sy" vor „sz" stellt, drei lange Namen, bei
	 * denen der längere vor seinem Anfangsstück steht, und zwei mit einer Null
	 * statt eines O. Mit dem Vergleich bis 1.43.2 — kleingeschriebener Name,
	 * Byte für Byte — wären es 119.582.
	 *
	 * @param string $name Name wie in Feld 0 (CP437)
	 *
	 * @return string Schlüssel aus A..Z, einzelnen Leerzeichen und dem Byte
	 *                0x1F für die Zeichen aus TIEF
	 */
	public static function sortierschluessel($name)
	{
		$name = preg_replace(self::TIEF, "\x1F", (string) $name);
		$name = preg_split(self::SCHNITT, $name, 2)[0];
		$name = strtoupper(strtr($name, self::UMSCHRIFT));

		return str_replace('SZ', 'SS', trim(preg_replace('/[^A-Z\x1F]+/', ' ', $name)));
	}

	/**
	 * Schreibt die Indexdatei.
	 *
	 * @param string $pfad    Ziel
	 * @param array  $eimer   Satznummer => ['offset' => int, 'anzahl' => int]
	 * @param int    $groesse Größe der geschriebenen LST in Bytes
	 *
	 * @return void
	 */
	protected function schreibeIndex($pfad, array $eimer, $groesse)
	{
		file_put_contents($pfad, $this->indexInhalt($eimer, $groesse));
	}

	/**
	 * Baut den Inhalt der Indexdatei.
	 *
	 * Aufbau: 702 Sätze à acht Byte, jeweils zwei vorzeichenlose 32-Bit-Zahlen
	 * in Intel-Reihenfolge. Nachgemessen an den Originaldateien des DSB von
	 * 2023 und vom 02.09.2026 und an der Datei des Swiss-Chess-Programmierers
	 * vom 09.09.2026:
	 *
	 * * **Satz 0** ist der Kopf: Offset 0 und die Kennung 0xFFFFFE44. Eimer 0
	 *   beginnt damit am Dateianfang.
	 * * **Satz 1..700** eines belegten Eimers: sein Offset in der LST und die
	 *   Satzzahl des **vorigen belegten** Eimers minus eins. Die Größe eines
	 *   Eimers steht also am nächsten belegten Satz.
	 * * **Leere Eimer** tragen (0, 0).
	 * * **Satz 701** trägt die Dateigröße und die Kennung der Fassung
	 *   (KENNUNG_NEU bzw. KENNUNG_ALT), keine Anzahl.
	 *
	 * Bis 1.43.2 stand der Zähler im Satz direkt hinter dem Eimer — nach einer
	 * Lücke also im leeren Satz, und der nächste belegte trug die 0. Satz 701
	 * trug die Satzzahl des letzten Eimers statt der Kennung.
	 *
	 * Eigene Methode, damit sich der Aufbau ohne Dateizugriff prüfen läßt.
	 *
	 * @param array $eimer   Satznummer => ['offset' => int, 'anzahl' => int];
	 *                       ein Eimer 701 („Zz") steht in der LST, bekommt
	 *                       aber keinen eigenen Indexsatz
	 * @param int   $groesse Größe der LST in Bytes
	 *
	 * @return string Die 5.616 Byte der SWX
	 */
	protected function indexInhalt(array $eimer, $groesse)
	{
		$roh = pack('VV', 0, self::INDEXKOPF);
		$vorige = isset($eimer[0]) ? (int) $eimer[0]['anzahl'] : 0;

		for ($s = 1; $s < self::INDEXSAETZE - 1; $s++)
		{
			if (!isset($eimer[$s]))
			{
				$roh .= pack('VV', 0, 0);
				continue;
			}

			$roh .= pack('VV', $eimer[$s]['offset'], max(0, $vorige - 1));
			$vorige = (int) $eimer[$s]['anzahl'];
		}

		$kennung = $this->fassung === self::FASSUNG_ALT ? self::KENNUNG_ALT : self::KENNUNG_NEU;

		return $roh.pack('VV', $groesse, $kennung);
	}

	/**
	 * Wandelt Text in die DOS-Codepage 437.
	 *
	 * Die spieler.csv und die vereine.csv kommen in windows-1252, die Namen der
	 * Elo-Tabelle in UTF-8 (so legt der XML-Import sie ab). Gültiges UTF-8
	 * bleibt deshalb UTF-8, alles andere wird als windows-1252 gelesen — ein
	 * Name in windows-1252 mit Umlaut ist praktisch nie zugleich gültiges
	 * UTF-8.
	 *
	 * Danach geht jedes Zeichen über ZEICHEN_CP437: Was CP437 kennt, bekommt
	 * sein Byte; was nicht, einen Ersatz („Ó" → „O", „’" → „'"); was in keiner
	 * Liste steht, fällt weg. Für die deutschen Umlaute, ß und die üblichen
	 * Akzente sind die Bytes dieselben wie in CP850 — die Wandlung ändert an
	 * bisherigen Dateien nur die seltenen Zeichen außerhalb von CP437.
	 *
	 * @param string $text Text in windows-1252 oder UTF-8
	 *
	 * @return string Text in CP437
	 */
	public static function nachCp437($text)
	{
		$text = (string) $text;

		// Reines ASCII bleibt, wie es ist — bei weitem der häufigste Fall
		if (!preg_match('/[\x80-\xFF]/', $text)) return $text;

		if (!preg_match('//u', $text)) $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');

		$raus = '';

		foreach ((array) preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $zeichen)
		{
			if (strlen($zeichen) === 1)
			{
				$raus .= $zeichen;
				continue;
			}

			if (!array_key_exists($zeichen, self::$cp437))
			{
				self::$cp437[$zeichen] = array_key_exists($zeichen, self::ZEICHEN_CP437) ? self::ZEICHEN_CP437[$zeichen] : '';
			}

			$raus .= self::$cp437[$zeichen];
		}

		return $raus;
	}

	/**
	 * Reicht eine Fortschrittsmeldung an den Aufrufer weiter.
	 *
	 * @param callable|null $melder Empfänger, oder null
	 * @param string        $text   Meldung
	 *
	 * @return void
	 */
	protected function melde($melder, $text)
	{
		if (is_callable($melder)) $melder($text);
	}

	// ─────────────────────────────────────────────
	//  Eimer: die Sätze werden erst getrennt gesammelt und zuletzt der
	//  Reihe nach zusammengeführt
	// ─────────────────────────────────────────────

	/**
	 * Arbeitsverzeichnis der Eimerdateien.
	 *
	 * @var string
	 */
	protected $eimerpfad = '';

	/**
	 * Sammelpuffer je Eimer, damit nicht für jeden Satz eine Datei geöffnet
	 * werden muß.
	 *
	 * @var array
	 */
	protected $puffer = array();

	/**
	 * Laufende Nummer der einsortierten Sätze. Sie hält bei gleichem
	 * Sortierschlüssel die Reihenfolge des Einlesens fest.
	 *
	 * @var int
	 */
	protected $laufnummer = 0;

	/**
	 * Legt das Arbeitsverzeichnis für die Eimerdateien an.
	 *
	 * **Warum überhaupt Eimer:** Mit den FIDE-Spielern zusammen sind es rund
	 * zwei Millionen Sätze. Alle im Speicher zu halten und dann zu sortieren
	 * kostet mehrere Gigabyte. Statt dessen wandert jeder Satz sofort in die
	 * Datei seines Eimers; am Ende wird jeder Eimer einzeln sortiert und
	 * angehängt. Der größte Eimer bestimmt den Speicherbedarf, nicht die
	 * ganze Datei — und die Reihenfolge der Eimer ist genau die, die der Index
	 * braucht.
	 *
	 * @param string $verzeichnis Pfad des Arbeitsverzeichnisses
	 *
	 * @return void
	 *
	 * @throws \RuntimeException wenn sich das Verzeichnis nicht anlegen läßt
	 */
	protected function eimerVorbereiten($verzeichnis)
	{
		$this->eimerAufraeumen();

		if (!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0777, true))
		{
			throw new \RuntimeException('Arbeitsverzeichnis nicht anlegbar: '.$verzeichnis);
		}

		$this->eimerpfad = $verzeichnis;
		$this->puffer = array();
		$this->laufnummer = 0;
	}

	/**
	 * Legt eine Zeile in ihren Eimer.
	 *
	 * Eimer und Sortierschlüssel kommen aus Feld 0 der fertigen Zeile — aus
	 * dem Namen in CP437 und gekürzt, genau wie er in der Datei steht. Die
	 * Umschrift der Sonderzeichen setzt diese Kodierung voraus; die spieler.csv
	 * kommt in windows-1252, die Elo-Tabelle in UTF-8.
	 *
	 * @param string $gruppe GRUPPE_FIDE oder GRUPPE_DSB: Rangfolge bei gleichem
	 *                       Sortierschlüssel
	 * @param string $zeile  Fertige Zeile mit CRLF
	 *
	 * @return bool false, wenn der Name vor dem Schnitt (SCHNITT) keinen
	 *              Buchstaben hat — solche Sätze kann der Index nicht führen
	 *              und sie bleiben weg
	 */
	protected function inEimer($gruppe, $zeile)
	{
		$name = substr($zeile, 0, (int) strpos($zeile, ';'));
		$e = self::eimer($name);

		if ($e === null) return false;

		// Der Sortierschlüssel wandert mit in die Datei und wird beim
		// Zusammenführen wieder abgeschnitten. Die laufende Nummer hält bei
		// gleichem Schlüssel die Reihenfolge des Einlesens fest — sort() ist
		// nicht stabil
		$this->puffer[$e][] = self::sortierschluessel($name)."\t".$gruppe.sprintf('%08d', $this->laufnummer++)."\t".$zeile;

		if (count($this->puffer[$e]) >= 5000) $this->pufferLeeren($e);

		return true;
	}

	/**
	 * Schreibt den Puffer eines Eimers in seine Datei.
	 *
	 * @param int $e Eimernummer
	 *
	 * @return void
	 */
	protected function pufferLeeren($e)
	{
		if (empty($this->puffer[$e])) return;

		file_put_contents($this->eimerpfad.'/'.$e, implode('', $this->puffer[$e]), FILE_APPEND);
		$this->puffer[$e] = array();
	}

	/**
	 * Führt die Eimer der Reihe nach zu einer LST zusammen.
	 *
	 * Innerhalb eines Eimers wird nach dem Sortierschlüssel sortiert, bei
	 * Gleichheit stehen reine FIDE-Sätze vor den Mitgliedschaften und sonst in
	 * der Reihenfolge des Einlesens. So sieht die Reihenfolge in beiden
	 * Fassungen gleich aus.
	 *
	 * Auch Eimer 701 („Zz") wird geschrieben. Er hat keinen eigenen Indexsatz —
	 * Satz 701 ist der Schlußsatz —, seine Namen stehen deshalb wie in der
	 * Datei des Programmierers hinter Eimer 700. Bis 1.43.2 fielen sie weg.
	 *
	 * @param string $lstPfad Ziel
	 *
	 * @return array Eimernummer => ['offset' => int, 'anzahl' => int]
	 *
	 * @throws \RuntimeException wenn die Zieldatei nicht schreibbar ist
	 */
	protected function eimerZusammenfuehren($lstPfad)
	{
		foreach (array_keys($this->puffer) as $e)
		{
			$this->pufferLeeren($e);
		}

		$fp = fopen($lstPfad, 'wb');
		if ($fp === false) throw new \RuntimeException('Kann '.$lstPfad.' nicht schreiben');

		$eimer = array();
		$pos = 0;

		for ($e = 0; $e < self::INDEXSAETZE; $e++)
		{
			$datei = $this->eimerpfad.'/'.$e;
			if (!is_file($datei)) continue;

			$zeilen = explode("\r\n", (string) file_get_contents($datei));
			// Der letzte Eintrag ist leer, weil jede Zeile mit CRLF endet
			array_pop($zeilen);

			sort($zeilen, SORT_STRING);

			$eimer[$e] = array('offset' => $pos, 'anzahl' => count($zeilen));

			foreach ($zeilen as $zeile)
			{
				// Sortierpräfix (Name + Tabulator + Schlüssel + Tabulator)
				// wieder abschneiden
				$roh = substr($zeile, strpos($zeile, "\t", strpos($zeile, "\t") + 1) + 1)."\r\n";
				fwrite($fp, $roh);
				$pos += strlen($roh);
			}

			@unlink($datei);
		}

		fclose($fp);

		return $eimer;
	}

	/**
	 * Löscht das Arbeitsverzeichnis samt Eimerdateien.
	 *
	 * @return void
	 */
	protected function eimerAufraeumen()
	{
		if ($this->eimerpfad === '' || !is_dir($this->eimerpfad)) return;

		foreach ((array) glob($this->eimerpfad.'/*') as $datei)
		{
			@unlink($datei);
		}

		@rmdir($this->eimerpfad);
		$this->eimerpfad = '';
		$this->puffer = array();
	}

	// ─────────────────────────────────────────────
	//  FIDE-Bestand aus tl_wertungsportal_elo
	// ─────────────────────────────────────────────

	/**
	 * Spalten, die aus der Elo-Tabelle gebraucht werden.
	 */
	const ELO_FELDER = 'fideid, surname, prename, country, sex, title, w_title, o_title, foa_title, rating, games, flag, rapid_rating, rapid_games, blitz_rating, blitz_games, birthday';

	/**
	 * Lädt die FIDE-Angaben zu einer Liste von Kennungen.
	 *
	 * @param array $fideIds FIDE-Kennungen der DSB-Mitglieder
	 *
	 * @return array FIDE-Kennung => Zeile; leer, wenn keine Datenbank
	 *               erreichbar ist
	 */
	protected function fideDatenFuer(array $fideIds)
	{
		$verbindung = $this->verbindung();
		if ($verbindung === null) return array();

		$fideIds = array_values(array_unique(array_filter(array_map('intval', $fideIds))));
		if (!count($fideIds)) return array();

		$daten = array();

		foreach (array_chunk($fideIds, 1000) as $block)
		{
			$sql = 'SELECT '.self::ELO_FELDER.' FROM tl_wertungsportal_elo WHERE fideid IN ('.implode(',', $block).')';

			foreach ($verbindung->iterateAssociative($sql) as $zeile)
			{
				$daten[(int) $zeile['fideid']] = $zeile;
			}
		}

		return $daten;
	}

	/**
	 * Verteilt alle FIDE-Spieler auf die Eimer, die nicht schon als
	 * DSB-Mitglied darin stehen.
	 *
	 * Gelesen wird über `iterateAssociative` und NICHT über die
	 * Contao-Datenbankklasse: Deren Ergebnisobjekt behält jede gelesene Zeile
	 * im Speicher, was bei knapp zwei Millionen Sätzen das Skript sprengt.
	 *
	 * @param array $bekannt       FIDE-Kennungen, die schon als DSB-Satz
	 *                             geschrieben wurden
	 * @param int   $uebersprungen Wird um die Sätze erhöht, deren Name vor dem
	 *                             Schnitt keinen Buchstaben hat
	 *
	 * @return int Anzahl der aufgenommenen FIDE-Spieler
	 */
	protected function fideSpielerVerteilen(array $bekannt, &$uebersprungen)
	{
		$verbindung = $this->verbindung();
		if ($verbindung === null) return 0;

		$anzahl = 0;
		$sql = 'SELECT '.self::ELO_FELDER." FROM tl_wertungsportal_elo WHERE published = '1'";

		foreach ($verbindung->iterateAssociative($sql) as $zeile)
		{
			if (isset($bekannt[(int) $zeile['fideid']])) continue;

			$name = $this->fideName($zeile);
			if ($name === '') continue;

			if ($this->inEimer(self::GRUPPE_FIDE, $this->baueFideZeile($zeile))) $anzahl++;
			else $uebersprungen++;
		}

		return $anzahl;
	}

	/**
	 * Liefert die Datenbankverbindung, wenn eine erreichbar ist.
	 *
	 * Ohne Contao — etwa im Prüfstand — gibt es keine; der Erzeuger arbeitet
	 * dann allein mit der CSV.
	 *
	 * @return \Doctrine\DBAL\Connection|null
	 */
	protected function verbindung()
	{
		try
		{
			$container = \Contao\System::getContainer();

			if ($container === null || !$container->has('database_connection')) return null;

			$verbindung = $container->get('database_connection');

			// Fehlt die Tabelle, ist der FIDE-Import noch nie gelaufen
			$vorhanden = $verbindung->fetchOne("SHOW TABLES LIKE 'tl_wertungsportal_elo'");

			return $vorhanden ? $verbindung : null;
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	/**
	 * Setzt die Fassung für den nächsten Lauf.
	 *
	 * @param string $fassung self::FASSUNG_NEU oder self::FASSUNG_ALT
	 *
	 * @return void
	 */
	public function setzeFassung($fassung)
	{
		$this->fassung = $fassung === self::FASSUNG_ALT ? self::FASSUNG_ALT : self::FASSUNG_NEU;
	}
}
