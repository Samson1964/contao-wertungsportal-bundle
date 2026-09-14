<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Classes\SwissChess;

/**
 * Prüft die Regeln, die den Aufbau der Swiss-Chess-Dateien tragen: die
 * Zahlenkodierung der alten Fassung, den Namensindex samt Umschrift und
 * Sortierung, die Indexdatei und die Felder der LST.
 *
 * Abgeleitet sind sie aus den Originaldateien des DSB (2023 und 02.09.2026) und
 * aus der Datei des Swiss-Chess-Programmierers vom 09.09.2026. Die Beispiele zur
 * Kodierung und zur Indexformel stammen wörtlich daraus — sie sind der Beleg,
 * nicht bloß eine Annahme; die Namen zur Umschrift sind erfunden und folgen den
 * dort ausgemessenen Mustern.
 */
class SwissChessTest extends TestCase
{
	/**
	 * Die FIDE-Kennung 7127030 steht in der Originaldatei von 2023 als
	 * `B5 89 71 64`. Sie hat sieben Ziffern, also eine ungerade Zahl — die
	 * letzte Ziffer steht allein im Einzelziffernbereich.
	 */
	public function testKodierungEinesEchtenBeispiels(): void
	{
		$this->assertSame("\xB5\x89\x71\x64", SwissChess::verschluessle('7127030'));
		$this->assertSame('7127030', SwissChess::entschluessle("\xB5\x89\x71\x64"));
	}

	/**
	 * Gerade Ziffernzahl: nur Paarbytes, kein Einzelbyte.
	 *
	 * `78 B2 C6 AC` ist in der Originaldatei die Kennung 10688862.
	 */
	public function testKodierungMitGeraderZiffernzahl(): void
	{
		$this->assertSame("\x78\xB2\xC6\xAC", SwissChess::verschluessle('10688862'));
		$this->assertSame('10688862', SwissChess::entschluessle("\x78\xB2\xC6\xAC"));
	}

	/**
	 * Führende Nullen in einem Ziffernpaar müssen erhalten bleiben.
	 *
	 * `87 72 CB 8C` ist die Kennung 25049330 — das zweite Paar ist „04".
	 */
	public function testKodierungMitNullImPaar(): void
	{
		$this->assertSame('25049330', SwissChess::entschluessle("\x87\x72\xCB\x8C"));
		$this->assertSame("\x87\x72\xCB\x8C", SwissChess::verschluessle('25049330'));
	}

	/**
	 * Eine einzelne Ziffer und ein Jahr — die kürzesten Fälle.
	 */
	public function testKodierungKurzerZahlen(): void
	{
		// 97 ist ein Paar: 0x6E + 97 = 0xCF
		$this->assertSame("\xCF", SwissChess::verschluessle('97'));
		$this->assertSame('97', SwissChess::entschluessle("\xCF"));

		// 1978 = 19 | 78
		$this->assertSame("\x81\xBC", SwissChess::verschluessle('1978'));
		$this->assertSame('1978', SwissChess::entschluessle("\x81\xBC"));
	}

	/**
	 * Jede Zahl muß sich kodieren und unverändert wieder auslesen lassen —
	 * gerade wie ungerade, klein wie groß.
	 */
	public function testHinUndZurueck(): void
	{
		foreach (array('0', '5', '42', '123', '1978', '20000', '999999', '1503014', '366094480') as $zahl)
		{
			$roh = SwissChess::verschluessle($zahl);
			$this->assertSame($zahl, SwissChess::entschluessle($roh), 'Zahl '.$zahl);
		}
	}

	/**
	 * Nichtzahlen ergeben eine leere Bytefolge, und Bytes außerhalb des
	 * gültigen Bereichs gelten als „kein Zahlenfeld".
	 */
	public function testUngueltigeEingaben(): void
	{
		$this->assertSame('', SwissChess::verschluessle(''));
		$this->assertSame('', SwissChess::verschluessle('12a'));
		$this->assertSame('', SwissChess::verschluessle('-5'));

		// 0x30 ist die Ziffer '0' in ASCII und liegt unter 100
		$this->assertNull(SwissChess::entschluessle("\x30\x31"));
		// 0xFF liegt über 209
		$this->assertNull(SwissChess::entschluessle("\xFF"));
	}

	/**
	 * Die Satznummer des Index: erster Buchstabe mal 27, dazu das zweite
	 * Zeichen. Die Beispiele stammen aus der Originaldatei — dort beginnt an
	 * genau diesen Stellen der jeweilige Namensbereich.
	 */
	public function testIndexformel(): void
	{
		$this->assertSame(0, SwissChess::eimer('A.A ACHMAD TITO'));   // A + kein Buchstabe
		$this->assertSame(1, SwissChess::eimer('Aa Djoni Kosanda')); // Aa
		$this->assertSame(2, SwissChess::eimer('Ab. Alim,Ahmad'));   // Ab
		$this->assertSame(26, SwissChess::eimer('Az,Alp Emre'));     // Az
		$this->assertSame(27, SwissChess::eimer('B,A Raju'));        // B + kein Buchstabe
		$this->assertSame(28, SwissChess::eimer('Ba,Abdoul'));       // Ba
		$this->assertSame(675, SwissChess::eimer('Z A Ayasha'));     // Z + Leerzeichen
		$this->assertSame(676, SwissChess::eimer('Za Nya Hmue'));    // Za
		$this->assertSame(700, SwissChess::eimer('Zyabkin,Danil'));  // Zy
	}

	/**
	 * Umlaute, ß und Akzente an zweiter Stelle werden umgeschrieben, bevor der
	 * Eimer feststeht. Beim Swiss-Chess-Programmierer stehen alle 822
	 * „Bä"-Namen im Eimer „Ba", die 2.994 „Bö"-Namen in „Bo", die 2.703
	 * „Bü"-Namen in „Bu", „Aß" in „As" und „Bé" in „Be".
	 *
	 * Bis 1.43.2 zählte das rohe Byte, und alle landeten im Eimer „kein
	 * Buchstabe". Swiss-Chess sucht „Böttcher" aber unter „Bo".
	 */
	public function testUmlautAnZweiterStelle(): void
	{
		$this->assertSame(28, SwissChess::eimer('B'.chr(0x84).'cker,Uwe'));            // ä
		$this->assertSame(42, SwissChess::eimer('B'.chr(0x94).'ttcher,Test'));          // ö
		$this->assertSame(48, SwissChess::eimer('B'.chr(0x81).'hler,Test'));            // ü
		$this->assertSame(19, SwissChess::eimer('A'.chr(0xE1).'mann,Test'));            // ß
		$this->assertSame(32, SwissChess::eimer('B'.chr(0x82).'renger,Test'));          // é
	}

	/**
	 * Ö und Ü am Anfang werden ausgeschrieben, ein Zeichen ohne Umschrift wie
	 * Ç zählt als Trenner: Beim Programmierer stehen alle 133 „Ö"-Namen im
	 * Eimer „Oe", die „Ü"-Namen in „Ue" und „Çelik" in „El". Bis 1.43.2
	 * blieben solche Namen ganz draußen.
	 */
	public function testSonderzeichenAmAnfang(): void
	{
		$this->assertSame(383, SwissChess::eimer(chr(0x99).'zdemir,Test'));   // Ö → OE
		$this->assertSame(545, SwissChess::eimer(chr(0x9A).'bel,Test'));      // Ü → UE
		$this->assertSame(120, SwissChess::eimer(chr(0x80).'elik,Test'));     // Ç entfällt → EL
		$this->assertSame(501, SwissChess::eimer('*Sonderfall'));             // Satzzeichen entfällt → SO
	}

	/**
	 * Namen ohne Buchstaben vor der ersten Klammer oder Ziffer kann der Index
	 * nicht führen.
	 */
	public function testNamenOhneBuchstaben(): void
	{
		$this->assertNull(SwissChess::eimer(''));
		$this->assertNull(SwissChess::eimer('123,Test'));
		$this->assertNull(SwissChess::eimer('(PER)'));
		$this->assertNull(SwissChess::eimer('- , .'));
	}

	/**
	 * Ein einzelner Buchstabe gehört in den Eimer „Buchstabe + kein zweites
	 * Zeichen".
	 *
	 * In der Datei des Programmierers besteht bei 20 Namen der Suchschlüssel
	 * aus einem einzigen Buchstaben; 17 davon stehen vor dem Beginn ihres
	 * Eimers und werden dort nicht mitgezählt. Hier gehören alle in ihren
	 * Eimer.
	 */
	public function testEinzelnerBuchstabe(): void
	{
		$this->assertSame(0, SwissChess::eimer('A'));
		$this->assertSame(27, SwissChess::eimer('B'));
	}

	/**
	 * Der Suchschlüssel: Umschrift, Groß- und Kleinschreibung gleich,
	 * Satzzeichen als einzelnes Leerzeichen, ab Klammer oder Ziffer Schluß.
	 */
	public function testSuchschluessel(): void
	{
		$this->assertSame('BOETTCHER TEST', SwissChess::suchschluessel('B'.chr(0x94).'ttcher,Test'));
		$this->assertSame('ASSMANN TEST', SwissChess::suchschluessel('A'.chr(0xE1).'mann,Test'));
		$this->assertSame('BERENGER TEST', SwissChess::suchschluessel('b'.chr(0x82).'renger,test'));
		$this->assertSame('MUSTER CARLOS', SwissChess::suchschluessel('Muster,Carlos (PER)'));
		$this->assertSame('MUSTER', SwissChess::suchschluessel('Muster 2016,Test'));
		$this->assertSame('D AVOLA TEST', SwissChess::suchschluessel("D'Avola,Test"));
		$this->assertSame('A B TEST', SwissChess::suchschluessel('A.B. Test'));
	}

	/**
	 * Beim Sortieren zählt „SZ" wie „SS" — nur so stehen die Namen in der
	 * Reihenfolge des Programmierers. Für den Eimer bleibt es bei „Sz".
	 */
	public function testSortierschluesselSzWieSs(): void
	{
		$this->assertLessThan(0, strcmp(SwissChess::sortierschluessel('Kaszab,Anna'), SwissChess::sortierschluessel('Kassel,Bernd')));
		$this->assertLessThan(0, strcmp(SwissChess::sortierschluessel('Muster,Szilvia'), SwissChess::sortierschluessel('Muster,Stefan')));
		$this->assertSame(18 * 27 + 26, SwissChess::eimer('Szabo,Test'));
	}

	/**
	 * Eine Klammer schneidet nur, wenn sie ein Wort beginnt; eine Ziffer
	 * schneidet immer. Beim Programmierer steht „Muster (PER)" wie „Muster",
	 * eine Klammer mitten im Wort trennt dagegen nur.
	 */
	public function testSchnittAnKlammerUndZiffer(): void
	{
		$this->assertSame('MUSTER', SwissChess::suchschluessel('Muster (PER)'));
		$this->assertSame('MUSTER', SwissChess::suchschluessel('Muster,(PER)'));
		$this->assertSame('MUSTER PER', SwissChess::suchschluessel('Muster(PER)'));
		$this->assertSame('MUSTER', SwissChess::suchschluessel('Muster2,Test'));
		$this->assertNull(SwissChess::eimer('(PER)'));
	}

	/**
	 * `, ? und ' an einem Wortrand sowie É und ò stehen beim Sortieren vor dem
	 * Leerzeichen; mitten im Wort trennen `, ? und ' nur. Für den Eimer sind
	 * sie immer Trenner.
	 *
	 * Die Namen folgen den Mustern, die in der Datei des Programmierers
	 * vorkommen: Ohne die Regel stünden dort 164 Nachbarpaare anders.
	 */
	public function testTiefeZeichenBeimSortieren(): void
	{
		$vor = static function (string $a, string $b): bool {
			return strcmp(SwissChess::sortierschluessel($a), SwissChess::sortierschluessel($b)) < 0;
		};

		$this->assertTrue($vor('Muster`,Nicola', 'Muster,Anna'), 'Gravis vor dem Komma');
		$this->assertTrue($vor('Muster,Md? Zaki', 'Muster,Md Adnan'), 'Fragezeichen am Wortende');
		$this->assertTrue($vor("In 't Muster,Rene", 'In,Anna'), 'Apostroph am Wortanfang');
		$this->assertTrue($vor('Muster,'.chr(0x90).'mile', 'Muster,Albert'), 'É');
		$this->assertTrue($vor('Muster'.chr(0x95).',Lukas', 'Muster,Charlie'), 'ò');
		$this->assertTrue($vor('Muster', 'Muster`,Nicola'), 'das Anfangsstück bleibt vorn');
		$this->assertTrue($vor("D'Avola,Test", 'Da,Anna'), 'Apostroph mitten im Wort trennt nur');

		$this->assertSame('D AVOLA TEST', SwissChess::suchschluessel("D'Avola,Test"));
		$this->assertSame(SwissChess::eimer('Muster,Md Zaki'), SwissChess::eimer('Muster,Md? Zaki'));
	}

	/**
	 * Die Indexdatei: Kopfsatz, Zähler des vorigen BELEGTEN Eimers, leere
	 * Eimer als (0, 0) und im Schlußsatz die Kennung der Fassung.
	 *
	 * Bis 1.43.2 stand der Zähler im Satz direkt dahinter, auch wenn der leer
	 * war, und der Schlußsatz trug eine Anzahl statt der Kennung.
	 */
	public function testIndexZaehlerUndKennung(): void
	{
		$eimer = array
		(
			0   => array('offset' => 0, 'anzahl' => 3),
			1   => array('offset' => 300, 'anzahl' => 2),
			3   => array('offset' => 500, 'anzahl' => 4),
			701 => array('offset' => 900, 'anzahl' => 1),
		);

		$sc = new SwissChessPruefling();
		$roh = $sc->index($eimer, 1000);
		$satz = static function (int $s) use (&$roh): array {
			return array_values(unpack('V2', substr($roh, $s * 8, 8)));
		};

		$this->assertSame(SwissChess::INDEXSAETZE * 8, strlen($roh));
		$this->assertSame(array(0, SwissChess::INDEXKOPF), $satz(0), 'Kopfsatz');
		$this->assertSame(array(300, 2), $satz(1), 'Zähler des Eimers 0 minus eins');
		$this->assertSame(array(0, 0), $satz(2), 'leerer Eimer');
		$this->assertSame(array(500, 1), $satz(3), 'Zähler des vorigen belegten Eimers 1 minus eins');
		$this->assertSame(array(0, 0), $satz(700), 'leerer Eimer am Ende');
		$this->assertSame(array(1000, SwissChess::KENNUNG_NEU), $satz(701), 'Dateigröße und Kennung der neuen Fassung');

		$sc->setzeFassung(SwissChess::FASSUNG_ALT);
		$roh = $sc->index($eimer, 1000);
		$this->assertSame(array(1000, SwissChess::KENNUNG_ALT), $satz(701), 'Kennung der alten Fassung');
		$this->assertSame(array(500, 1), $satz(3), 'Zähler wie in der neuen Fassung');
	}

	/**
	 * Die Kennungen sind die Werte aus den Originaldateien: 0x01000000 in den
	 * beiden Dateien von 2026, 0 in der von 2023.
	 */
	public function testKennungen(): void
	{
		$this->assertSame(16777216, SwissChess::KENNUNG_NEU);
		$this->assertSame(0, SwissChess::KENNUNG_ALT);
	}

	// ─────────────────────────────────────────────
	//  Die FIDE-Felder 15 bis 27
	// ─────────────────────────────────────────────

	/**
	 * Baut eine Elo-Zeile, wie sie aus tl_wertungsportal_elo käme.
	 *
	 * @param array $abweichend Felder, die vom Grundmuster abweichen sollen
	 *
	 * @return array Vollständige Zeile
	 */
	protected function eloZeile(array $abweichend = array()): array
	{
		return array_merge(array
		(
			'fideid'       => 4711,
			'surname'      => 'Muster',
			'prename'      => 'Erika',
			'country'      => 'GER',
			'sex'          => 'F',
			'title'        => 'WFM',
			'w_title'      => 'WFM',
			'o_title'      => 'NA',
			'foa_title'    => 'AFM',
			'rating'       => 1850,
			'games'        => 9,
			'flag'         => 'i',
			'rapid_rating' => 1790,
			'rapid_games'  => 4,
			'blitz_rating' => 1755,
			'blitz_games'  => 12,
			'birthday'     => 1994,
		), $abweichend);
	}

	/**
	 * Amtstitel und Arena-Titel sind ZWEI Felder, nicht eines.
	 *
	 * Die Originaldatei führt in Feld 16 die Schiedsrichter- und
	 * Trainertitel (NA, FA, IA, FT …) und in Feld 18 die Titel der FIDE
	 * Online Arena (AGM, AIM, AFM, ACM) — beides gleichzeitig belegt kommt
	 * vor. Wer den Arena-Titel als Ersatz in Feld 16 schreibt, verliert 276
	 * Angaben allein unter den deutschen Mitgliedern.
	 */
	public function testAmtstitelUndArenatitelStehenGetrennt(): void
	{
		$f = array_fill(0, SwissChess::FELDER, '');
		$sc = new SwissChessPruefling();
		$sc->fideFelder($f, $this->eloZeile());

		$this->assertSame('WFM', $f[15], 'Feld 15 trägt den Frauentitel');
		$this->assertSame('NA', $f[16], 'Feld 16 trägt den Amtstitel');
		$this->assertSame('i', $f[17], 'Feld 17 trägt das Kennzeichen');
		$this->assertSame('AFM', $f[18], 'Feld 18 trägt den Arena-Titel');
	}

	/**
	 * Ohne Amtstitel bleibt Feld 16 leer — der Arena-Titel rückt NICHT nach.
	 */
	public function testArenatitelRuecktNichtNach(): void
	{
		$f = array_fill(0, SwissChess::FELDER, '');
		$sc = new SwissChessPruefling();
		$sc->fideFelder($f, $this->eloZeile(array('o_title' => '')));

		$this->assertSame('', $f[16]);
		$this->assertSame('AFM', $f[18]);
	}

	/**
	 * Feld 27 wiederholt die Blitzpartien aus Feld 26.
	 *
	 * Das ist kein Blitz-K-Faktor: In allen 1.973.816 Sätzen der
	 * Originaldatei stehen in beiden Feldern dieselben Zeichen — ohne eine
	 * einzige Ausnahme.
	 */
	public function testFeld27WiederholtDieBlitzpartien(): void
	{
		$f = array_fill(0, SwissChess::FELDER, '');
		$sc = new SwissChessPruefling();
		$sc->fideFelder($f, $this->eloZeile());

		$this->assertSame('1755', $f[25]);
		$this->assertSame('12', $f[26]);
		$this->assertSame($f[26], $f[27]);
	}

	/**
	 * Die Wertungen landen in den Feldern 19, 22 und 25, die Partienzahlen
	 * daneben in 20, 23 und 26.
	 */
	public function testWertungenUndPartien(): void
	{
		$f = array_fill(0, SwissChess::FELDER, '');
		$sc = new SwissChessPruefling();
		$sc->fideFelder($f, $this->eloZeile());

		$this->assertSame(array('1850', '9'), array($f[19], $f[20]), 'Standard');
		$this->assertSame(array('1790', '4'), array($f[22], $f[23]), 'Schnellschach');
		$this->assertSame(array('1755', '12'), array($f[25], $f[26]), 'Blitz');
	}

	/**
	 * Ohne Wertung steht im K-Faktor-Feld eine Null, mit Wertung bleibt es
	 * leer.
	 *
	 * Die Null ist ausgezählt: In allen 1.346.756 Sätzen mit FIDE-Kennung und
	 * ohne Standardwertung steht dort genau sie. Der echte Faktor liegt
	 * diesem Bundle nicht vor.
	 */
	public function testKFaktor(): void
	{
		$sc = new SwissChessPruefling();

		$this->assertSame('0', $sc->kWert(''), 'ohne Wertung eine Null');
		$this->assertSame('', $sc->kWert('1850'), 'mit Wertung leer statt geraten');
	}

	/**
	 * Ein Satz ohne FIDE-Kennung hat die Felder 15 bis 27 sämtlich leer —
	 * auch die K-Faktoren. So halten es alle 57.369 solcher Sätze der
	 * Originaldatei.
	 */
	public function testOhneFideKennungBleibtAllesLeer(): void
	{
		$f = array_fill(0, SwissChess::FELDER, '');
		$f[0] = 'Muster,Erika';
		$f[8] = '';

		$zeile = (new SwissChessPruefling())->schluss($f);
		$felder = explode(';', rtrim($zeile, "\r\n"));

		for ($i = 15; $i <= 27; $i++)
		{
			$this->assertSame('""', $felder[$i], 'Feld '.$i.' muß leer sein');
		}

		$this->assertSame('"', $felder[28], 'Das Schlußfeld ist ein einzelnes Anführungszeichen');
	}

	/**
	 * Mit FIDE-Kennung tragen die beiden K-Felder mindestens die Null.
	 */
	public function testMitFideKennungStehtDieNull(): void
	{
		$f = array_fill(0, SwissChess::FELDER, '');
		$f[8] = '4711';
		$f[19] = '1850';   // Standardwertung vorhanden
		$f[22] = '';       // keine Schnellwertung

		$felder = explode(';', rtrim((new SwissChessPruefling())->schluss($f), "\r\n"));

		$this->assertSame('""', $felder[21], 'mit Wertung bleibt der Faktor leer');
		$this->assertSame('"0"', $felder[24], 'ohne Wertung steht die Null');
	}

	// ─────────────────────────────────────────────
	//  Spaltenzuordnung der spieler.csv
	// ─────────────────────────────────────────────

	/**
	 * Kopfzeile der spieler.csv, wie nu sie bis zum 09.09.2026 lieferte.
	 */
	private const KOPF_ALT = array('ID', 'ZPS', 'Mitgliedsnummer', 'Status', 'Name,Vorname',
		'Geschlecht', 'Spielberechtigung', 'Geburtsjahr', 'Letzte Auswertung', 'DWZ', 'Index',
		'FIDE-Elozahl', 'FIDE-Titel', 'FIDE-ID', 'FIDE-Land', 'Vorname', 'Nachname');

	/**
	 * Dieselbe Kopfzeile mit den drei Spalten, die nu seit dem 10.09.2026
	 * anhängt.
	 */
	private const KOPF_NEU = array('ID', 'ZPS', 'Mitgliedsnummer', 'Status', 'Name,Vorname',
		'Geschlecht', 'Spielberechtigung', 'Geburtsjahr', 'Letzte Auswertung', 'DWZ', 'Index',
		'FIDE-Elozahl', 'FIDE-Titel', 'FIDE-ID', 'FIDE-Land', 'Vorname', 'Nachname',
		'FIDE-Frauentitel', 'FIDE-Elozahl-Schnellschach', 'FIDE-Elozahl-Blitz');

	/**
	 * Beide Kopfzeilen werden angenommen — die alte wie die um drei Spalten
	 * erweiterte.
	 *
	 * Am 10.09.2026 hat nu angehängt, und der Erzeuger brach im Cronjob des
	 * Livesystems ab, weil er die Kopfzeile Zeichen für Zeichen verglich.
	 */
	public function testBeideKopfzeilenWerdenAngenommen(): void
	{
		$sc = new SwissChessPruefling();

		$sc->ordne(self::KOPF_ALT);
		$this->assertSame(13, $sc->spaltenNummer('fideid'), 'alte Kopfzeile');
		$this->assertSame('', $sc->spaltenSchluessel('frauentitel'), 'die Spalte gibt es dort nicht');

		$sc->ordne(self::KOPF_NEU);
		$this->assertSame(13, $sc->spaltenNummer('fideid'), 'neue Kopfzeile');
		$this->assertSame(17, $sc->spaltenNummer('frauentitel'));
		$this->assertSame(18, $sc->spaltenNummer('schnellelo'));
		$this->assertSame(19, $sc->spaltenNummer('blitzelo'));
	}

	/**
	 * Vertauschte Spalten werden richtig zugeordnet — DWZ bleibt DWZ.
	 *
	 * Genau dieser Fall war der Grund für die frühere strenge Prüfung. Über
	 * die Namen löst er sich von selbst.
	 */
	public function testVertauschteSpaltenWerdenRichtigZugeordnet(): void
	{
		$kopf = self::KOPF_NEU;
		[$kopf[9], $kopf[11]] = [$kopf[11], $kopf[9]];

		$sc = new SwissChessPruefling();
		$sc->ordne($kopf);

		$this->assertSame(11, $sc->spaltenNummer('dwz'), 'DWZ steht jetzt an Position 11');
		$this->assertSame(9, $sc->spaltenNummer('elo'), 'die Elo an Position 9');
	}

	/**
	 * Fehlt eine Pflichtspalte, bricht der Lauf ab und nennt sie beim Namen.
	 *
	 * Eine stillschweigend verrutschte Zuordnung wäre schlimmer als gar keine
	 * Datei — sie fiele erst im Turniersaal auf.
	 */
	public function testFehlendePflichtspalteBrichtAb(): void
	{
		$kopf = self::KOPF_NEU;
		$kopf[13] = 'FIDE-Nummer';

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessageMatches('/FIDE-ID/');

		(new SwissChessPruefling())->ordne($kopf);
	}

	/**
	 * Ein BOM am Dateianfang klebt an der ersten Überschrift und darf die
	 * Zuordnung nicht verhindern.
	 */
	public function testBomStoertNicht(): void
	{
		$kopf = self::KOPF_NEU;
		$kopf[0] = "\xEF\xBB\xBF".$kopf[0];

		$sc = new SwissChessPruefling();
		$sc->ordne($kopf);

		$this->assertSame(0, $sc->spaltenNummer('id'));
	}

	/**
	 * Der Frauentitel kommt aus der eigenen Spalte; fehlt sie, greift der alte
	 * Weg über die allgemeine Titelspalte.
	 */
	public function testFrauentitel(): void
	{
		$sc = new SwissChessPruefling();

		$sc->ordne(self::KOPF_NEU);
		$zeile = array_fill(0, 20, '');
		$zeile[17] = 'WGM';
		$this->assertSame('WGM', $sc->holeFrauentitel($zeile, 'GM'), 'eigene Spalte schlägt den offenen Titel');
		$this->assertSame('', $sc->holeFrauentitel(array_fill(0, 20, ''), 'GM'), 'ein offener Titel ist kein Frauentitel');

		$sc->ordne(self::KOPF_ALT);
		$this->assertSame('WIM', $sc->holeFrauentitel(array_fill(0, 17, ''), 'WIM'), 'ohne die Spalte zählt die Titelspalte');
	}

	// ─────────────────────────────────────────────
	//  Felder einer Mitgliedszeile in beiden Fassungen
	// ─────────────────────────────────────────────

	/**
	 * Baut eine Zeile der spieler.csv zur neuen Kopfzeile.
	 *
	 * @param string $dwz  Inhalt der DWZ-Spalte
	 * @param string $name Inhalt der Spalte „Name,Vorname" in windows-1252
	 *
	 * @return array
	 */
	private function csvZeile(string $dwz, string $name = 'Muster,Max'): array
	{
		return array('NU4005017', 'C0505', '1043', 'A', $name, 'M', '', '1963', '', $dwz, '',
			'1802', '', '4711', 'GER', 'Max', 'Muster', '', '', '');
	}

	/**
	 * Die Felder der neuen Fassung stehen im Klartext: Spielerkennung als
	 * nuLiga-ID, Mitgliedsnummer in Feld 9 und 13, ohne DWZ ein leeres Feld.
	 */
	public function testNeueFassungFelder(): void
	{
		$sc = new SwissChessPruefling();
		$sc->ordne(self::KOPF_NEU);

		$f = explode(';', rtrim($sc->zeile($this->csvZeile('')), "\r\n"));

		$this->assertSame('', $f[4], 'ohne DWZ leer');
		$this->assertSame('NU4005017', $f[7]);
		$this->assertSame('1043', $f[9]);
		$this->assertSame('1043', $f[13]);
	}

	/**
	 * Die alte Fassung folgt der Datei des DSB von 2023: ohne DWZ die
	 * kodierte „0000", Feld 7 leer — die nuLiga-ID ist keine Zahl —, Feld 9
	 * binär, Feld 13 im Klartext.
	 */
	public function testAlteFassungFelder(): void
	{
		$sc = new SwissChessPruefling();
		$sc->ordne(self::KOPF_NEU);
		$sc->setzeFassung(SwissChess::FASSUNG_ALT);

		$f = explode(';', rtrim($sc->zeile($this->csvZeile('')), "\r\n"));

		$this->assertSame('nn', $f[4], 'ohne DWZ die kodierte 0000');
		$this->assertSame(SwissChess::verschluessle('1802'), $f[3], 'Elo binär');
		$this->assertSame(SwissChess::verschluessle('1963'), $f[6], 'Geburtsjahr binär');
		$this->assertSame('', $f[7], 'keine Spielerkennung');
		$this->assertSame(SwissChess::verschluessle('1043'), $f[9], 'Mitgliedsnummer binär');
		$this->assertSame('1043', $f[13], 'Wiederholung im Klartext');

		$f = explode(';', rtrim($sc->zeile($this->csvZeile('1813')), "\r\n"));
		$this->assertSame(SwissChess::verschluessle('1813'), $f[4], 'mit DWZ binär');
	}

	/**
	 * Den Akut „´" schreibt der Programmierer als Apostroph.
	 */
	public function testAkutWirdApostroph(): void
	{
		$sc = new SwissChessPruefling();
		$sc->ordne(self::KOPF_NEU);

		$f = explode(';', rtrim($sc->zeile($this->csvZeile('', "D\xB4Avola,Test")), "\r\n"));

		$this->assertSame("D'Avola,Test", $f[0]);
	}

	// ─────────────────────────────────────────────
	//  Zeichensatz CP437
	// ─────────────────────────────────────────────

	/**
	 * windows-1252 aus den CSV-Dateien und UTF-8 aus der Elo-Tabelle ergeben
	 * dieselben Bytes. Für Umlaute, ß und die üblichen Akzente sind es dieselben
	 * wie in CP850 — an ihnen ändert sich gegenüber 1.43.2 nichts.
	 */
	public function testNachCp437(): void
	{
		$this->assertSame('Muster,Max', SwissChess::nachCp437('Muster,Max'), 'reines ASCII bleibt');
		$this->assertSame("M\x81ller,J\x94rg", SwissChess::nachCp437("M\xFCller,J\xF6rg"), 'windows-1252');
		$this->assertSame("M\x81ller,J\x94rg", SwissChess::nachCp437('Müller,Jörg'), 'UTF-8');
		$this->assertSame("Gro\xE1", SwissChess::nachCp437('Groß'));
		$this->assertSame("\x99zdemir", SwissChess::nachCp437("\xD6zdemir"));
		$this->assertSame("\x9Abel", SwissChess::nachCp437('Übel'));
		$this->assertSame("Ren\x82 Fran\x87ois Nu\xA4ez", SwissChess::nachCp437("Ren\xE9 Fran\xE7ois Nu\xF1ez"));
	}

	/**
	 * Was CP437 nicht kennt, wird zum Grundbuchstaben oder zu einem Satzzeichen
	 * aus ASCII — so steht es in der Datei des Swiss-Chess-Programmierers. Bis
	 * 1.43.2 ging die Wandlung nach CP850: Der Akut stand dann als 0xEF in der
	 * Datei, und das ist in CP437 ein „∩".
	 */
	public function testZeichenAusserhalbVonCp437(): void
	{
		$this->assertSame('Oscar', SwissChess::nachCp437('Óscar'), 'UTF-8');
		$this->assertSame('Oscar', SwissChess::nachCp437("\xD3scar"), 'windows-1252');
		$this->assertSame('Sasa', SwissChess::nachCp437('Šaša'));
		$this->assertSame('Sasa', SwissChess::nachCp437("\x8Aa\x9Aa"), 'windows-1252');
		$this->assertSame('Holy', SwissChess::nachCp437("Hol\xFD"));
		$this->assertSame('Moller', SwissChess::nachCp437('Møller'));
		$this->assertSame('Lukasz Ozdoba', SwissChess::nachCp437('Łukasz Ożdoba'));
		$this->assertSame("O'Neill", SwissChess::nachCp437("O\x92Neill"), 'typografischer Apostroph');
		$this->assertSame("D'Avola", SwissChess::nachCp437('D´Avola'), 'Akut');
		$this->assertSame('AB', SwissChess::nachCp437('A€B'), 'unbekannte Zeichen fallen weg');
	}

	/**
	 * Anführungszeichen fallen erst NACH der Wandlung weg: „ und “ werden dabei
	 * selbst zu geraden Anführungszeichen, und die dürfen in den Textfeldern
	 * nicht stehen — die Felder 15 bis 28 sind genau daran zu erkennen.
	 */
	public function testAnfuehrungszeichenNachDerWandlung(): void
	{
		$sc = new SwissChessPruefling();
		$sc->ordne(self::KOPF_NEU);

		$f = explode(';', rtrim($sc->zeile($this->csvZeile('', "Muster,Max \x84Maxi\x93")), "\r\n"));

		$this->assertSame('Muster,Max Maxi', $f[0]);
		$this->assertCount(SwissChess::FELDER, $f);
	}

	/**
	 * Die Namen reiner FIDE-Spieler kommen aus der Elo-Tabelle in UTF-8. Bis
	 * 1.43.2 liefen sie durch die Wandlung für windows-1252, ein „ö" wäre dabei
	 * zu zwei falschen Zeichen geworden. In der Datei des Programmierers sind
	 * alle Namen reiner FIDE-Spieler ASCII; der Fall ist also selten.
	 */
	public function testFideNameAusUtf8(): void
	{
		$sc = new SwissChessPruefling();

		$f = explode(';', rtrim($sc->fideZeile($this->eloZeile(array('surname' => 'Möller', 'prename' => 'Zoë'))), "\r\n"));
		$this->assertSame("M\x94ller,Zo\x89", $f[0]);

		$f = explode(';', rtrim($sc->fideZeile($this->eloZeile(array('surname' => 'Dvořák', 'prename' => ''))), "\r\n"));
		$this->assertSame("Dvor\xA0k", $f[0]);
	}

	/**
	 * Nennt die CSV trotz FIDE-Kennung kein Land, kommt die Föderation aus der
	 * Elo-Tabelle; in der Datei des Programmierers steht in solchen Fällen ein
	 * Land. Ein Land aus der CSV geht vor.
	 */
	public function testNationAusDerEloTabelle(): void
	{
		$sc = new SwissChessPruefling();
		$sc->ordne(self::KOPF_NEU);

		$ohneLand = $this->csvZeile('1813');
		$ohneLand[14] = '';

		$f = explode(';', rtrim($sc->zeile($ohneLand, $this->eloZeile(array('country' => 'AUT'))), "\r\n"));
		$this->assertSame('AUT', $f[2], 'aus der Elo-Tabelle');

		$f = explode(';', rtrim($sc->zeile($ohneLand), "\r\n"));
		$this->assertSame('', $f[2], 'ohne Elo-Satz bleibt es leer');

		$f = explode(';', rtrim($sc->zeile($this->csvZeile('1813'), $this->eloZeile(array('country' => 'AUT'))), "\r\n"));
		$this->assertSame('GER', $f[2], 'das Land der CSV geht vor');
	}
}

/**
 * Macht die geschützten Methoden für die Prüfung erreichbar.
 *
 * Sie sind bewußt nicht öffentlich — sie gehören zum inneren Aufbau der
 * Zeile und niemand außerhalb der Klasse hat dort etwas zu suchen. Für den
 * Nachweis der Formatregeln braucht es sie aber einzeln.
 */
class SwissChessPruefling extends SwissChess
{
	/**
	 * @param array $f Felder der Zeile, wird verändert
	 * @param array $e Zeile aus tl_wertungsportal_elo
	 *
	 * @return void
	 */
	public function fideFelder(array &$f, array $e): void
	{
		$this->setzeFideFelder($f, $e);
	}

	/**
	 * @param string $wertung Inhalt des Wertungsfeldes
	 *
	 * @return string Inhalt des K-Faktor-Feldes
	 */
	public function kWert($wertung): string
	{
		return $this->kFaktor($wertung);
	}

	/**
	 * @param array $f Felder der Zeile
	 *
	 * @return string Fertige Zeile
	 */
	public function schluss(array $f): string
	{
		return $this->schlussFelder($f);
	}

	/**
	 * @param array $kopf Kopfzeile der spieler.csv
	 *
	 * @return void
	 */
	public function ordne($kopf): void
	{
		$this->spaltenZuordnen($kopf);
	}

	/**
	 * @param string $schluessel Spaltenschlüssel
	 *
	 * @return int Zugeordnete Spaltennummer, oder -1 wenn nicht vorhanden
	 */
	public function spaltenNummer($schluessel): int
	{
		return $this->spalten[$schluessel] ?? -1;
	}

	/**
	 * @param string $schluessel Spaltenschlüssel
	 *
	 * @return string Der Schlüssel selbst, wenn zugeordnet — sonst leer
	 */
	public function spaltenSchluessel($schluessel): string
	{
		return isset($this->spalten[$schluessel]) ? $schluessel : '';
	}

	/**
	 * @param array  $z     Felder der CSV-Zeile
	 * @param string $titel Inhalt der allgemeinen Titelspalte
	 *
	 * @return string Frauentitel
	 */
	public function holeFrauentitel(array $z, $titel): string
	{
		return $this->frauentitel($z, $titel);
	}

	/**
	 * @param array $eimer   Satznummer => ['offset' => int, 'anzahl' => int]
	 * @param int   $groesse Größe der LST in Bytes
	 *
	 * @return string Inhalt der SWX
	 */
	public function index(array $eimer, int $groesse): string
	{
		return $this->indexInhalt($eimer, $groesse);
	}

	/**
	 * @param array      $z    Felder der CSV-Zeile
	 * @param array|null $fide Zeile aus tl_wertungsportal_elo, falls vorhanden
	 *
	 * @return string Fertige LST-Zeile
	 */
	public function zeile(array $z, ?array $fide = null): string
	{
		return $this->baueZeile($z, $fide);
	}

	/**
	 * @param array $e Zeile aus tl_wertungsportal_elo
	 *
	 * @return string Fertige LST-Zeile eines reinen FIDE-Spielers
	 */
	public function fideZeile(array $e): string
	{
		return $this->baueFideZeile($e);
	}
}
