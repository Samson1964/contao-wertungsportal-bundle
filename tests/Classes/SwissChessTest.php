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
}
