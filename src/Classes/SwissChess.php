<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Erzeugt die Hintergrunddateien für Swiss-Chess aus der Deutschland-Datei
 * des Wertungsportals (LV-0-csv).
 *
 * Swiss-Chess liest die Spielerdaten aus einem Dateipaar:
 *
 * * **LST** — ein Datensatz je Zeile, Felder durch Semikolon getrennt,
 *   Zeilenende CRLF, Zeichensatz DOS-Codepage 850.
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
 * Einträgen beider SWX-Dateien. Einzelheiten in `docs/swiss-chess.md`.
 *
 * **Was diese Dateien NICHT enthalten:** Die Originaldateien des DSB führen
 * zusätzlich alle weltweit von der FIDE erfaßten Spieler und deren Schnell-
 * und Blitzwertungen. Beides steht nicht in der LV-0-csv; die hier erzeugten
 * Dateien umfassen deshalb nur die DSB-Mitglieder, und von den FIDE-Angaben
 * nur das, was die CSV hergibt (Elo, Titel, Kennung, Land).
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
	 * Spaltennummern der spieler.csv. Gelesen wird über diese Konstanten und
	 * nicht über die Kopfzeile: Die Datei kommt unverändert von nu, und eine
	 * verschobene Spalte soll auffallen statt stillschweigend falsche Dateien
	 * zu erzeugen (siehe pruefeKopfzeile()).
	 */
	const CSV_ID          = 0;
	const CSV_ZPS         = 1;
	const CSV_MITGLNR     = 2;
	const CSV_STATUS      = 3;
	const CSV_NAME        = 4;
	const CSV_GESCHLECHT  = 5;
	const CSV_GEBURTSJAHR = 7;
	const CSV_DWZ         = 9;
	const CSV_ELO         = 11;
	const CSV_TITEL       = 12;
	const CSV_FIDEID      = 13;
	const CSV_LAND        = 14;

	/**
	 * Erwartete Kopfzeile der spieler.csv.
	 */
	const CSV_KOPF = 'ID,ZPS,Mitgliedsnummer,Status,"Name,Vorname",Geschlecht,Spielberechtigung,Geburtsjahr,"Letzte Auswertung",DWZ,Index,FIDE-Elozahl,FIDE-Titel,FIDE-ID,FIDE-Land,Vorname,Nachname';

	/**
	 * Vereinsnamen, aufgeschlüsselt nach ZPS. Wird beim ersten Lauf gefüllt.
	 *
	 * @var array
	 */
	protected $vereine = array();

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
	 *
	 * @return array ['lst' => Pfad, 'swx' => Pfad, 'saetze' => Anzahl,
	 *                'uebersprungen' => Anzahl]
	 *
	 * @throws \RuntimeException wenn die Quelldateien fehlen oder die
	 *                           Kopfzeile der CSV nicht paßt
	 */
	public function erzeuge($quelle, $ziel, $fassung, $name, $melder = null)
	{
		$quelle = rtrim(str_replace('\\', '/', $quelle), '/');
		$ziel = rtrim(str_replace('\\', '/', $ziel), '/');

		$spielerdatei = $quelle.'/spieler.csv';
		$vereinsdatei = $quelle.'/vereine.csv';

		if (!is_file($spielerdatei)) throw new \RuntimeException('spieler.csv fehlt in '.$quelle);
		if (!is_file($vereinsdatei)) throw new \RuntimeException('vereine.csv fehlt in '.$quelle);
		if (!is_dir($ziel) && !@mkdir($ziel, 0777, true)) throw new \RuntimeException('Zielverzeichnis nicht anlegbar: '.$ziel);

		$this->setzeFassung($fassung);

		$this->melde($melder, 'Lese vereine.csv');
		$this->ladeVereine($vereinsdatei);

		$this->melde($melder, 'Lese spieler.csv');
		$saetze = $this->ladeSpieler($spielerdatei, $fassung);
		$uebersprungen = 0;

		// Sortieren ist Pflicht: Der Index verweist auf zusammenhängende
		// Bereiche je Namensanfang.
		//
		// Sortiert wird ZUERST nach der Satznummer des Index und erst danach
		// nach dem Namen. Eine reine Namenssortierung reicht nicht: Ein Umlaut
		// an zweiter Stelle -- "Bäcker" -- steht in CP850 hinter dem "z" und
		// landete damit hinter allen "Bz"-Namen. Der Eimer für "B + kein
		// Buchstabe" wäre zerrissen und sein Bereich im Index unbrauchbar;
		// genau das ist beim ersten Versuch passiert
		$this->melde($melder, 'Sortiere '.count($saetze).' Datensätze');
		usort($saetze, static function ($a, $b) {
			$ea = self::eimer($a['name']);
			$eb = self::eimer($b['name']);

			if ($ea !== $eb) return ($ea ?? 999) <=> ($eb ?? 999);

			$v = strcasecmp($a['name'], $b['name']);

			// Zweiter Schluessel BEWUSST nicht die fertige Zeile: Die sieht in
			// beiden Fassungen verschieden aus, und die Dateien haetten dann
			// bei Namensgleichheit eine unterschiedliche Reihenfolge
			return $v !== 0 ? $v : strcmp($a['schluessel'], $b['schluessel']);
		});

		// LST schreiben und dabei die Eimer des Index mitzählen
		$lstPfad = $ziel.'/'.$name.'.LST';
		$this->melde($melder, 'Schreibe '.basename($lstPfad));

		$fp = fopen($lstPfad, 'wb');
		if ($fp === false) throw new \RuntimeException('Kann '.$lstPfad.' nicht schreiben');

		$eimer = array();
		$pos = 0;

		foreach ($saetze as $satz) {
			$e = self::eimer($satz['name']);

			if ($e === null) {
				// Namen, die nicht mit A..Z beginnen, kann der Index nicht
				// führen. Sie kämen in keiner Suche vor und bleiben deshalb weg
				$uebersprungen++;
				continue;
			}

			if (!isset($eimer[$e])) $eimer[$e] = array('offset' => $pos, 'anzahl' => 0);
			$eimer[$e]['anzahl']++;

			fwrite($fp, $satz['zeile']);
			$pos += strlen($satz['zeile']);
		}

		fclose($fp);

		// SWX schreiben
		$swxPfad = $ziel.'/'.$name.'.SWX';
		$this->melde($melder, 'Schreibe '.basename($swxPfad));
		$this->schreibeIndex($swxPfad, $eimer, $pos);

		return array
		(
			'lst'           => $lstPfad,
			'swx'           => $swxPfad,
			'saetze'        => count($saetze) - $uebersprungen,
			'uebersprungen' => $uebersprungen,
		);
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
	 * @return array Liste aus ['name' => Sortiername, 'zeile' => fertige Zeile]
	 *
	 * @throws \RuntimeException bei unerwarteter Kopfzeile
	 */
	protected function ladeSpieler($datei, $fassung)
	{
		$fp = fopen($datei, 'rb');
		$kopf = fgets($fp);
		$this->pruefeKopfzeile($kopf);
		rewind($fp);
		fgetcsv($fp);

		$saetze = array();

		while (($z = fgetcsv($fp)) !== false)
		{
			if (count($z) < 15) continue;

			$name = trim((string) $z[self::CSV_NAME]);
			if ($name === '') continue;

			$saetze[] = array
			(
				'name'      => $name,
				// nuLiga-Kennung und Vereinskennziffer machen die Mitgliedschaft
				// eindeutig und sind in beiden Fassungen dieselben
				'schluessel' => (string) $z[self::CSV_ID].'|'.(string) $z[self::CSV_ZPS],
				'zeile'     => $this->baueZeile($z, $fassung),
			);
		}

		fclose($fp);

		return $saetze;
	}

	/**
	 * Prüft, ob die Kopfzeile der spieler.csv noch die erwartete ist.
	 *
	 * **Warum das sein muß:** Gelesen wird über feste Spaltennummern. Schiebt
	 * nu eine Spalte ein, entstünden sonst lautlos Dateien, in denen etwa die
	 * DWZ im Elo-Feld steht — und das fiele erst im Turniersaal auf.
	 *
	 * @param string|false $kopf Erste Zeile der Datei
	 *
	 * @return void
	 *
	 * @throws \RuntimeException wenn die Kopfzeile abweicht
	 */
	protected function pruefeKopfzeile($kopf)
	{
		$kopf = trim((string) $kopf);
		// Ein BOM am Dateianfang stört den Vergleich, sonst nichts
		$kopf = preg_replace('/^\xEF\xBB\xBF/', '', $kopf);

		if ($kopf !== self::CSV_KOPF)
		{
			throw new \RuntimeException(
				"Die spieler.csv hat eine unerwartete Kopfzeile.\n".
				"  erwartet: ".self::CSV_KOPF."\n".
				"  gelesen : ".$kopf."\n".
				'Die Spaltenzuordnung muß geprüft werden, bevor Dateien entstehen.'
			);
		}
	}

	/**
	 * Baut aus einer CSV-Zeile die fertige LST-Zeile samt Zeilenende.
	 *
	 * @param array  $z       Felder der CSV-Zeile
	 * @param string $fassung self::FASSUNG_NEU oder self::FASSUNG_ALT
	 *
	 * @return string Zeile in CP850 mit CRLF am Ende
	 */
	protected function baueZeile(array $z, $fassung)
	{
		$zps = (string) $z[self::CSV_ZPS];
		$titel = strtoupper(trim((string) $z[self::CSV_TITEL]));
		$land = trim((string) $z[self::CSV_LAND]);

		$fideId = $this->ziffern((string) $z[self::CSV_FIDEID]);
		$elo = $this->ziffern((string) $z[self::CSV_ELO]);

		$f = array_fill(0, self::FELDER, '');

		// Name und Vereinsname werden auf 40 Zeichen gekürzt — so hält es die
		// Originaldatei, in der kein Feld länger ist
		$f[0]  = $this->text((string) $z[self::CSV_NAME]);
		$f[1]  = $this->text($this->vereine[$zps] ?? '');

		// Die Nation steht NUR bei Spielern mit FIDE-Eintrag. In der
		// Originaldatei hat kein einziger der 57.369 Sätze ohne FIDE-Kennung
		// eine Nation — ein pauschales „GER" wäre also falsch
		$f[2]  = $fideId !== '' ? $this->text($land) : '';

		$f[3]  = $this->zahl($elo);
		$f[4]  = $this->zahl($this->ziffern((string) $z[self::CSV_DWZ]));
		$f[5]  = self::TITELCODE[$titel] ?? '';
		$f[6]  = $this->zahl($this->ziffern((string) $z[self::CSV_GEBURTSJAHR]));
		$f[7]  = $this->text((string) $z[self::CSV_ID]);
		$f[8]  = $this->zahl($fideId);
		$f[9]  = $this->zahl($this->ziffern((string) $z[self::CSV_MITGLNR]));
		$f[10] = $this->text((string) $z[self::CSV_GESCHLECHT]);
		$f[11] = $this->text($zps);
		// Erste Stelle der Kennziffer = Landesverband
		$f[12] = $zps !== '' ? substr($zps, 0, 1) : '';
		$f[13] = $f[9];
		$f[14] = $this->text((string) $z[self::CSV_STATUS]);

		// Die Felder 15 bis 27 tragen die FIDE-Angaben und stehen in
		// Anführungszeichen. Aus der CSV lassen sich nur der Frauentitel und
		// die Standard-Elo füllen; Schnell- und Blitzwertung, Partienzahlen,
		// K-Faktoren und Kennzeichen liefert sie nicht
		$f[15] = in_array($titel, self::FRAUENTITEL, true) ? $titel : '';
		$f[19] = $elo;

		for ($i = 15; $i <= 27; $i++)
		{
			$f[$i] = '"'.$f[$i].'"';
		}

		// Das letzte Feld ist ein einzelnes Anführungszeichen
		$f[28] = '"';

		return implode(';', $f)."\r\n";
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
	 * Bereitet ein Textfeld auf: Wandlung nach CP850 und Kürzung auf 40
	 * Zeichen.
	 *
	 * **Wichtig:** Nur Textfelder dürfen durch die Kodierwandlung laufen. Die
	 * Zahlenfelder der alten Fassung enthalten Bytes, die in windows-1252
	 * Sonderzeichen sind (0x82 etwa ist ein Anführungszeichen); eine Wandlung
	 * der ganzen Zeile zerstörte sie. Genau das ist beim ersten Versuch
	 * passiert — aus 0x82 wurde 0x60, und die Zahl war unlesbar.
	 *
	 * Da CP850 ein Einbyte-Zeichensatz ist, entspricht die Kürzung auf 40
	 * Bytes genau 40 Zeichen; ein Umlaut kann dabei nicht zerschnitten werden.
	 *
	 * @param string $wert Text in windows-1252
	 *
	 * @return string Text in CP850, höchstens 40 Zeichen
	 */
	protected function text($wert)
	{
		// Anführungszeichen fliegen raus: In der Originaldatei trägt kein
		// einziger der 300.000 geprüften Vereinsnamen eines. Sie würden auch
		// die Felder 15 bis 28 nachahmen, die genau daran zu erkennen sind
		$wert = str_replace('"', '', $wert);

		$raus = @iconv('CP1252', 'CP850//TRANSLIT', $wert);
		if ($raus === false) $raus = $wert;

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
	 * Der Index teilt nach den ersten beiden Zeichen auf: 26 Anfangsbuchstaben
	 * mal 27 Möglichkeiten für das zweite Zeichen (kein Buchstabe, dann a..z).
	 * Die Formel ist an allen 702 Einträgen beider Originaldateien geprüft.
	 *
	 * @param string $name Name wie in Feld 0
	 *
	 * @return int|null Satznummer 0..701, null wenn der Name nicht mit einem
	 *                  Buchstaben A..Z beginnt
	 */
	public static function eimer($name)
	{
		if ($name === '') return null;

		$erst = ord(strtoupper($name[0])) - 65;
		if ($erst < 0 || $erst > 25) return null;

		$zweit = 0;

		if (strlen($name) > 1)
		{
			$c = ord(strtolower($name[1]));
			if ($c >= 97 && $c <= 122) $zweit = $c - 96;
		}

		return $erst * 27 + $zweit;
	}

	/**
	 * Schreibt die Indexdatei.
	 *
	 * Aufbau: 702 Sätze à acht Byte, jeweils zwei vorzeichenlose 32-Bit-Zahlen
	 * in Intel-Reihenfolge. Der erste Wert ist der Byte-Offset, an dem der
	 * Eimer in der LST beginnt.
	 *
	 * Der zweite Wert gehört — so steht es in beiden Originaldateien — zum
	 * VORHERGEHENDEN Eimer und ist dessen Datensatzzahl **minus eins**. Diese
	 * Verschiebung sieht nach einem Eigenheit des ursprünglichen Programms
	 * aus; sie wird hier unverändert nachgebildet, weil Swiss-Chess sie so
	 * erwartet.
	 *
	 * Der Kopfsatz trägt Offset 0 und eine feste Kennung, der letzte Satz die
	 * Dateigröße.
	 *
	 * @param string $pfad   Ziel
	 * @param array  $eimer  Satznummer => ['offset' => int, 'anzahl' => int]
	 * @param int    $groesse Größe der geschriebenen LST in Bytes
	 *
	 * @return void
	 */
	protected function schreibeIndex($pfad, array $eimer, $groesse)
	{
		// Leere Eimer bekommen den Offset 0 — so hält es die Originaldatei
		// (dort sind 84 der 700 Sätze leer und tragen alle die 0). Wer den
		// Offset des Vorgängers einsetzte, ließe den Leser in fremde Daten
		// greifen; die 0 ist die eindeutige Anzeige „hier ist nichts". Die
		// belegten Offsets sind dadurch streng steigend, genau wie im Original
		$offsets = array();

		for ($s = 0; $s < self::INDEXSAETZE - 1; $s++)
		{
			$offsets[$s] = isset($eimer[$s]) ? $eimer[$s]['offset'] : 0;
		}

		$roh = '';

		for ($s = 0; $s < self::INDEXSAETZE; $s++)
		{
			if ($s === 0)
			{
				$roh .= pack('VV', 0, self::INDEXKOPF);
				continue;
			}

			$offset = $s < self::INDEXSAETZE - 1 ? $offsets[$s] : $groesse;

			// Zähler des vorhergehenden Eimers, minus eins
			$vor = isset($eimer[$s - 1]) ? $eimer[$s - 1]['anzahl'] - 1 : 0;
			if ($vor < 0) $vor = 0;

			$roh .= pack('VV', $offset, $vor);
		}

		file_put_contents($pfad, $roh);
	}

	/**
	 * Wandelt Text von der Kodierung der nu-Dateien in die DOS-Codepage 850.
	 *
	 * Die spieler.csv kommt in windows-1252, Swiss-Chess erwartet CP850. Für
	 * alle Zeichen, die in den Originaldateien tatsächlich vorkommen (deutsche
	 * Umlaute und einige westeuropäische Akzente), sind CP850 und CP437
	 * byteweise identisch — die Wahl zwischen beiden spielt hier also keine
	 * Rolle.
	 *
	 * `//TRANSLIT` sorgt dafür, daß ein Zeichen außerhalb der Codepage eine
	 * lesbare Entsprechung bekommt statt eines Fragezeichens.
	 *
	 * @param string $text Text in windows-1252
	 *
	 * @return string Text in CP850; bei einem Fehler unverändert
	 */
	protected function nachCp850($text)
	{
		$raus = @iconv('CP1252', 'CP850//TRANSLIT', $text);

		return $raus === false ? $text : $raus;
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
