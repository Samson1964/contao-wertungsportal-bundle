<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Classes\SwissChess;

/**
 * Prüft die beiden Regeln, die den Aufbau der Swiss-Chess-Dateien tragen:
 * die Zahlenkodierung der alten Fassung und die Formel des Namensindex.
 *
 * Beide sind aus den Originaldateien des DSB abgeleitet. Die Beispiele hier
 * stammen wörtlich daraus — sie sind der Beleg, nicht bloß eine Annahme.
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
	 * Ein Umlaut an zweiter Stelle gehört in den Eimer „kein Buchstabe" — und
	 * genau daran ist die erste Fassung des Erzeugers gescheitert: Sortiert
	 * man nur nach dem Namen, landet „Bäcker" hinter allen „Bz"-Namen und
	 * zerreißt den Bereich.
	 */
	public function testUmlautAnZweiterStelle(): void
	{
		$this->assertSame(27, SwissChess::eimer('B'.chr(0x84).'cker,Uwe'));  // ä in CP850
		$this->assertSame(0, SwissChess::eimer('A'.chr(0xE1).',Gerhard'));   // ß in CP850
	}

	/**
	 * Namen ohne Anfangsbuchstaben kann der Index nicht führen.
	 */
	public function testNamenOhneBuchstaben(): void
	{
		$this->assertNull(SwissChess::eimer(''));
		$this->assertNull(SwissChess::eimer('123,Test'));
		$this->assertNull(SwissChess::eimer('*Sonderfall'));
	}

	/**
	 * Ein einzelner Buchstabe ist ein gültiger Name und gehört in den
	 * Eimer „Buchstabe + kein zweites Zeichen".
	 */
	public function testEinzelnerBuchstabe(): void
	{
		$this->assertSame(0, SwissChess::eimer('A'));
		$this->assertSame(27, SwissChess::eimer('B'));
	}

	/**
	 * Die Titelcodes sind vollständig und eindeutig — jede Zahl kommt nur
	 * einmal vor, und die 5 fehlt (so steht es in der Originaldatei).
	 */
	public function testTitelcodes(): void
	{
		$codes = array_values(SwissChess::TITELCODE);

		$this->assertSame($codes, array_unique($codes), 'Codes müssen eindeutig sein');
		$this->assertNotContains('5', $codes, 'Die 5 kommt im Bestand nicht vor');
		$this->assertSame('1', SwissChess::TITELCODE['GM']);
		$this->assertSame('9', SwissChess::TITELCODE['WCM']);
		$this->assertCount(8, SwissChess::TITELCODE);
	}
}
