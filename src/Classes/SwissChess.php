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

				if ($this->inEimer($satz['name'], $satz['schluessel'], $zeile)) $dsb++;
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
				'fideId'    => (int) $z[self::CSV_FIDEID],
				'csv'       => $z,
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
	protected function baueZeile(array $z, array $fide = null)
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

		// Die Felder 15 bis 27 tragen die FIDE-Angaben. Aus der CSV allein
		// ließen sich nur der Frauentitel und die Standard-Elo füllen; mit
		// einem Satz aus tl_wertungsportal_elo kommen Schnell- und
		// Blitzwertung, Partienzahlen und Kennzeichen dazu
		if ($fide !== null)
		{
			$this->setzeFideFelder($f, $fide);

			// Der Frauentitel steht nur in der Elo-Tabelle: Die CSV hat eine
			// einzige Titelspalte, in der bei einer Spielerin mit offenem
			// Titel dieser steht
			if ($f[5] === '' && $fide['title'] !== '') $f[5] = self::TITELCODE[$fide['title']] ?? '';
		}
		elseif ($fideId !== '')
		{
			// Ohne Satz in der Elo-Tabelle bleibt nur, was die CSV hergibt.
			// Ein Spieler ohne FIDE-Kennung bekommt gar nichts — in der
			// Originaldatei sind bei allen 57.369 solchen Sätzen die Felder
			// 15 bis 27 ausnahmslos leer
			$f[15] = in_array($titel, self::FRAUENTITEL, true) ? $titel : '';
			$f[19] = $elo;
		}

		return $this->schlussFelder($f);
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
	 * @return string Zeile in CP850 mit CRLF am Ende
	 */
	protected function baueFideZeile(array $e)
	{
		$f = array_fill(0, self::FELDER, '');

		$f[0]  = $this->text($this->fideName($e));
		$f[2]  = $this->text((string) $e['country']);
		$f[3]  = $this->zahl($this->ziffern((string) $e['rating']));
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
	}

	/**
	 * Legt eine Zeile in ihren Eimer.
	 *
	 * @param string $name       Name für die Eimerbestimmung
	 * @param string $schluessel Zweiter Sortierschlüssel bei Namensgleichheit
	 * @param string $zeile      Fertige Zeile mit CRLF
	 *
	 * @return bool false, wenn der Name nicht mit A..Z beginnt — solche Sätze
	 *              kann der Index nicht führen und sie bleiben weg
	 */
	protected function inEimer($name, $schluessel, $zeile)
	{
		$e = self::eimer($name);

		if ($e === null) return false;

		// Der Sortierschlüssel wandert mit in die Datei und wird beim
		// Zusammenführen wieder abgeschnitten
		$this->puffer[$e][] = strtolower($name)."\t".$schluessel."\t".$zeile;

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
	 * Innerhalb eines Eimers wird nach dem Namen sortiert, bei Gleichheit nach
	 * dem zweiten Schlüssel. So sieht die Reihenfolge in beiden Fassungen
	 * gleich aus.
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

		for ($e = 0; $e < self::INDEXSAETZE - 1; $e++)
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
	 * @param int   $uebersprungen Wird um die Sätze erhöht, deren Name nicht
	 *                             mit A..Z beginnt
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

			if ($this->inEimer($name, (string) $zeile['fideid'], $this->baueFideZeile($zeile))) $anzahl++;
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
