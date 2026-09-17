<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Spielerwertung;

/**
 * Prüft die Aufbereitung der Wertungsangaben eines Turnierspielers — vor allem
 * die Regeln für Nichtmitglieder („Textuelle"): keine neue DWZ, Eingangswertung
 * aus dem Anzeigetext, Gewinnerwartung je Partie aus dem `expected` der
 * Schnittstelle.
 *
 * Die Zahlen stammen aus einer echten Auswertung (Turnier mit 84 Teilnehmern,
 * davon 12 Nichtmitglieder; TODO vom 17.09.2026), die Namen sind weggelassen.
 */
class SpielerwertungTest extends TestCase
{
	/**
	 * Ein Mitglied, wie es die Schnittstelle liefert: Zahlenfelder und
	 * Anzeigetexte nebeneinander.
	 *
	 * @return array<string,mixed>
	 */
	private static function mitglied(): array
	{
		return array
		(
			'playerUuid'                => '2ba43af2-0000-0000-0000-000000000041',
			'nuLigaPersonId'            => 'NU0000041',
			'member'                    => true,
			'ratingOld'                 => 1887,
			'indexOld'                  => 44,
			'ratingNew'                 => 1876,
			'indexNew'                  => 45,
			'factorK'                   => 28.0,
			'winsExpected'              => 3.89098,
			'ratingOldDisplayString'    => '1887 - 44',
			'ratingNewDisplayString'    => '1876 - 45',
			'factorKDisplayString'      => '28.0',
			'winsExpectedDisplayString' => '3.891',
		);
	}

	/**
	 * Ein Nichtmitglied mit Eingangswertung: `ratingOld` fehlt, `ratingNew`
	 * wird (unzulässigerweise) mitgeliefert.
	 *
	 * @return array<string,mixed>
	 */
	private static function nichtmitglied(): array
	{
		return array
		(
			'playerUuid'                => '6e3f68b0-0000-0000-0000-000000000069',
			'member'                    => false,
			'ratingNew'                 => 1589,
			'indexNew'                  => 7,
			'factorK'                   => 56.9,
			'winsExpected'              => 1.87934,
			'ratingOldDisplayString'    => '1554',
			'factorKDisplayString'      => '(56.9)',
			'winsExpectedDisplayString' => '1.879',
		);
	}

	/**
	 * `member` entscheidet — in jeder Schreibweise, in der es ankommen kann:
	 * als Wahrheitswert von der Schnittstelle, als '1'/'0' aus der
	 * Spiegeltabelle.
	 */
	public function testMitgliedsstatusAusDemFeldMember(): void
	{
		$this->assertFalse(Spielerwertung::istNichtmitglied(array('member' => true)));
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('member' => false)));
		$this->assertFalse(Spielerwertung::istNichtmitglied(array('member' => '1')));
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('member' => '0')));
		$this->assertFalse(Spielerwertung::istNichtmitglied(array('member' => 1)));
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('member' => 0)));
	}

	/**
	 * `member = false` gilt auch dann, wenn die Person noch verknüpft ist —
	 * der Fall des ausgetretenen Mitglieds in der Vorhaltefrist. Umgekehrt
	 * macht eine fehlende Personennummer aus einem Mitglied kein
	 * Nichtmitglied, solange `member` etwas anderes sagt.
	 */
	public function testMemberGehtVorPersonennummer(): void
	{
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('member' => false, 'nuLigaPersonId' => 'NU123')));
		$this->assertFalse(Spielerwertung::istNichtmitglied(array('member' => true, 'nuLigaPersonId' => '')));
	}

	/**
	 * Ohne `member` (Zeilen der Spiegeltabelle aus der Zeit vor 1.45.0, dort
	 * kommt das Feld leer oder gar nicht) entscheidet die Personennummer: Ein
	 * textuell erfasster Teilnehmer hat keine.
	 */
	public function testOhneMemberEntscheidetDiePersonennummer(): void
	{
		$this->assertFalse(Spielerwertung::istNichtmitglied(array('nuLigaPersonId' => 'NU123')));
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('nuLigaPersonId' => '')));
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('nuLigaPersonId' => false)));
		$this->assertTrue(Spielerwertung::istNichtmitglied(array('member' => '', 'nuLigaPersonId' => '')));
		$this->assertFalse(Spielerwertung::istNichtmitglied(array('member' => null, 'nuLigaPersonId' => 'NU123')));
		$this->assertFalse(Spielerwertung::istNichtmitglied(null));
	}

	/**
	 * Für ein Mitglied ändert sich nichts: dieselbe Tabellenform wie
	 * Helper::DWZ() (geschützte Leerzeichen halten die Spalte bündig),
	 * Differenz mit Vorzeichen.
	 */
	public function testMitgliedBleibtWieBisher(): void
	{
		$w = Spielerwertung::aufbereiten(self::mitglied());

		$this->assertFalse($w['nichtmitglied']);
		$this->assertSame('1887 -&nbsp;&nbsp;44', $w['dwzAlt']);
		$this->assertSame('1876 -&nbsp;&nbsp;45', $w['dwzNeu']);
		$this->assertSame('1887', $w['dwzAltKurz']);
		$this->assertSame('-11', $w['differenz']);
		$this->assertSame(28.0, $w['factorK']);
		$this->assertSame(3.89098, $w['winsExpected']);
	}

	/**
	 * Steigt die Wertung, trägt die Differenz ein Pluszeichen; bleibt sie
	 * gleich, steht eine 0 da (wie vor 1.45.0).
	 */
	public function testDifferenzMitVorzeichen(): void
	{
		$spieler = self::mitglied();
		$spieler['ratingNew'] = 1905;
		$this->assertSame('+18', Spielerwertung::aufbereiten($spieler)['differenz']);

		$spieler['ratingNew'] = 1887;
		$this->assertSame('0', Spielerwertung::aufbereiten($spieler)['differenz']);
	}

	/**
	 * Der Kern des Auftrags: Für ein Nichtmitglied wird KEINE neue DWZ
	 * ausgewiesen, auch wenn die Schnittstelle sie liefert (Wertungsordnung
	 * 3.4.3) — dafür die Eingangswertung aus dem Anzeigetext, ohne Index.
	 */
	public function testNichtmitgliedOhneNeueDwzMitEingangswertung(): void
	{
		$w = Spielerwertung::aufbereiten(self::nichtmitglied());

		$this->assertTrue($w['nichtmitglied']);
		$this->assertSame('1554', $w['dwzAlt']);
		$this->assertSame('1554', $w['dwzAltKurz']);
		$this->assertSame(1554, $w['ratingOld']);
		$this->assertSame(0, $w['indexOld']);
		$this->assertSame('', $w['dwzNeu']);
		$this->assertSame(0, $w['ratingNew']);
		$this->assertSame('', $w['differenz']);

		// Entwicklungskoeffizient und Erwartungswert bleiben stehen: Die
		// Wertungsordnung verbietet nur DWZ und Restpartien
		$this->assertSame(56.9, $w['factorK']);
		$this->assertSame(1.87934, $w['winsExpected']);
	}

	/**
	 * Fehlen die Zahlenfelder, springen die Anzeigetexte ein: „(56.9)" kommt
	 * mit der Klammer von nu an, „1.879" als Zahl.
	 */
	public function testAnzeigetexteAlsErsatzFuerFehlendeZahlen(): void
	{
		$spieler = self::nichtmitglied();
		unset($spieler['factorK'], $spieler['winsExpected']);

		$w = Spielerwertung::aufbereiten($spieler);

		$this->assertSame('(56.9)', $w['factorK']);
		$this->assertSame(1.879, $w['winsExpected']);
	}

	/**
	 * Der Sonderfall des Teilnehmers ohne jede Wertung: Die Schnittstelle
	 * liefert nur `ratingNewDisplayString` „(1318)". Das ist eine neue
	 * Wertung eines Nichtmitglieds und wird nicht gezeigt; alle Felder
	 * bleiben leer.
	 */
	public function testNichtmitgliedOhneEingangswertungBleibtLeer(): void
	{
		$w = Spielerwertung::aufbereiten(array('member' => false, 'ratingNewDisplayString' => '(1318)', 'wins' => 1.0, 'numberOfGames' => 6));

		$this->assertSame('', $w['dwzAlt']);
		$this->assertSame('', $w['dwzAltKurz']);
		$this->assertSame('', $w['dwzNeu']);
		$this->assertSame('', $w['differenz']);
		$this->assertSame('', $w['factorK']);
		$this->assertFalse($w['winsExpected']);
	}

	/**
	 * Ältere Zeilen der Spiegeltabelle tragen die Eingangswertung eines
	 * textuellen Teilnehmers in `ratingOld`, mit Index 0. Bis 1.44.1 stand
	 * dafür „1905 - 0" in der Auswertung.
	 */
	public function testWertungOhneIndexZeigtNurDieZahl(): void
	{
		$w = Spielerwertung::aufbereiten(array('nuLigaPersonId' => '', 'ratingOld' => '1905', 'indexOld' => '0', 'ratingNew' => '1953', 'indexNew' => '7'));

		$this->assertTrue($w['nichtmitglied']);
		$this->assertSame('1905', $w['dwzAlt']);
		$this->assertSame('', $w['dwzNeu']);
	}

	/**
	 * Ein ausgetretener Spieler: nu liefert seine frühere DWZ nur noch als
	 * Anzeigetext in Klammern. In der Spiegeltabelle stehen daneben noch die
	 * Zahlen aus der Zeit der Mitgliedschaft (der Abgleich setzt nicht mehr
	 * gelieferte Felder nie zurück). Der Anzeigetext geht bei Nichtmitgliedern
	 * vor — sonst zeigte der Notbetrieb „1537 - 47", die Schnittstelle „(1537)".
	 */
	public function testNichtmitgliedAnzeigetextGehtVorZahlen(): void
	{
		$w = Spielerwertung::aufbereiten(array('member' => '0', 'nuLigaPersonId' => '', 'ratingOld' => '1537', 'indexOld' => '47', 'ratingOldDisplayString' => '(1537)', 'ratingNew' => '1536', 'indexNew' => '48'));

		$this->assertSame('(1537)', $w['dwzAlt']);
		$this->assertSame('(1537)', $w['dwzAltKurz']);
		$this->assertSame(1537, $w['ratingOld']);
		$this->assertSame(0, $w['indexOld']);
		$this->assertSame('', $w['dwzNeu']);

		// Beim Mitglied bleibt es bei den Zahlen, auch wenn der Text abwiche
		$w = Spielerwertung::aufbereiten(array('member' => true, 'ratingOld' => 1537, 'indexOld' => 47, 'ratingOldDisplayString' => '(1537)'));
		$this->assertSame('1537 -&nbsp;&nbsp;47', $w['dwzAlt']);
	}

	/**
	 * Ein Mitglied mit erster DWZ hat keine alte Wertung — dann gibt es auch
	 * keine Differenz.
	 */
	public function testMitgliedMitErsterDwz(): void
	{
		$w = Spielerwertung::aufbereiten(array('member' => true, 'nuLigaPersonId' => 'NU1', 'ratingNew' => 2053, 'indexNew' => 1, 'ratingNewDisplayString' => '2053 - 1'));

		$this->assertSame('', $w['dwzAlt']);
		$this->assertSame('2053 -&nbsp;&nbsp;&nbsp;&nbsp;1', $w['dwzNeu']);
		$this->assertSame('', $w['differenz']);
	}

	/**
	 * Die Platzhalter aus Helper::PlayerDefaults() (false für jedes fehlende
	 * Feld) und ein ganz fehlender Spieler (spielfrei) dürfen nichts werfen.
	 */
	public function testPlatzhalterUndLeererSpieler(): void
	{
		$leer = Spielerwertung::aufbereiten(array('ratingOld' => false, 'indexOld' => false, 'ratingNew' => false, 'indexNew' => false, 'factorK' => false, 'winsExpected' => false, 'nuLigaPersonId' => false));

		$this->assertSame('', $leer['dwzAlt']);
		$this->assertSame('', $leer['dwzNeu']);
		$this->assertSame('', $leer['factorK']);
		$this->assertFalse($leer['winsExpected']);
		$this->assertSame($leer, Spielerwertung::aufbereiten(null));
	}

	/**
	 * Die drei beobachteten Formen des Anzeigetextes — und alles andere wird
	 * verworfen statt durchgereicht, denn der Text landet in der Vorlage.
	 */
	public function testZerlegeAnzeigetext(): void
	{
		$this->assertSame(array('rating' => 1887, 'index' => 44, 'klammer' => false), Spielerwertung::zerlege('1887 - 44'));
		$this->assertSame(array('rating' => 1905, 'index' => 0, 'klammer' => false), Spielerwertung::zerlege('1905'));
		$this->assertSame(array('rating' => 1318, 'index' => 0, 'klammer' => true), Spielerwertung::zerlege('(1318)'));
		$this->assertSame(array('rating' => 987, 'index' => 3, 'klammer' => false), Spielerwertung::zerlege(' 987 -3 '));
		$this->assertNull(Spielerwertung::zerlege(''));
		$this->assertNull(Spielerwertung::zerlege(null));
		$this->assertNull(Spielerwertung::zerlege('0'));
		$this->assertNull(Spielerwertung::zerlege('<b>1905</b>'));
		$this->assertNull(Spielerwertung::zerlege('Restpartien'));
	}

	/**
	 * Eine errechnete Eingangswertung in Klammern bliebe als solche
	 * erkennbar, falls nu sie einmal im Anzeigetext der alten Wertung liefert.
	 */
	public function testKlammerBleibtErhalten(): void
	{
		$w = Spielerwertung::aufbereiten(array('member' => false, 'ratingOldDisplayString' => '(1318)'));

		$this->assertSame('(1318)', $w['dwzAlt']);
		$this->assertSame('(1318)', $w['dwzAltKurz']);
		$this->assertSame(1318, $w['ratingOld']);
	}

	/**
	 * `expected` gilt aus Sicht von WEISS. Nachgerechnet am Bogen aus dem
	 * TODO: Der Spieler (1887) hatte viermal Weiß und dreimal Schwarz; erst
	 * mit 1 − expected für die Schwarzpartien ergibt die Summe das gelieferte
	 * `winsExpected` von 3,89098.
	 */
	public function testPartieerwartungAusSichtVonWeiss(): void
	{
		// Runde => [expected, Spieler hat Weiß]
		$partien = array
		(
			1 => array(0.977875, true),
			2 => array(0.619457, false),
			3 => array(0.42291, true),
			4 => array(0.60728, false),
			5 => array(0.361837, true),
			6 => array(0.525371, false),
			7 => array(0.880469, true),
		);

		$summe = 0.0;
		$zeilen = array();

		foreach ($partien as $runde => $p) {
			$e = Spielerwertung::partieerwartung(array('result' => 'REMIS', 'expected' => $p[0]), $p[1]);
			$this->assertFalse($e['geschaetzt']);
			$summe += $e['wert'];
			$zeilen[$runde] = sprintf('%.3f', $e['wert']);
		}

		// Dieselben Werte zeigt das Wertungsportal von nu für diesen Bogen
		$this->assertSame(array(1 => '0.978', 2 => '0.381', 3 => '0.423', 4 => '0.393', 5 => '0.362', 6 => '0.475', 7 => '0.880'), $zeilen);
		$this->assertEqualsWithDelta(3.89098, $summe, 0.00001);
	}

	/**
	 * Kampflose Partien werden nicht gewertet und bekommen keinen
	 * Erwartungswert — auch wenn die Schnittstelle einen mitliefert und beide
	 * Wertungen bekannt sind.
	 */
	public function testKampflosePartieOhneErwartung(): void
	{
		foreach (array('PLUS_MINUS', 'MINUS_PLUS', 'MINUS_MINUS', 'ZERO_MINUS', 'MINUS_ZERO') as $code) {
			$this->assertTrue(Spielerwertung::istKampflos($code), $code);
			$this->assertNull(Spielerwertung::partieerwartung(array('result' => $code, 'expected' => 0.75), true, 1900, 1700)['wert'], $code);
		}

		foreach (array('WHITE_WINS', 'BLACK_WINS', 'REMIS', 'ZERO_HALF', 'HALF_ZERO', '', null) as $code) {
			$this->assertFalse(Spielerwertung::istKampflos($code), (string) $code);
		}
	}

	/**
	 * Fehlt `expected` — im Notbetrieb bei Partien, die nur über die
	 * Ergebnisliste gespiegelt wurden; die Spalte trägt dann 0 —, wird aus den
	 * alten Wertungen gerechnet und das Ergebnis als Schätzung gekennzeichnet.
	 * Ohne beide Wertungen bleibt das Feld leer.
	 */
	public function testErsatzrechnungOhneExpected(): void
	{
		$e = Spielerwertung::partieerwartung(array('result' => 'REMIS'), false, 1887, 1973);
		$this->assertTrue($e['geschaetzt']);
		$this->assertSame('0.381', sprintf('%.3f', $e['wert']));

		$e = Spielerwertung::partieerwartung(array('result' => 'REMIS', 'expected' => 0), false, 1887, 1973);
		$this->assertTrue($e['geschaetzt']);

		$e = Spielerwertung::partieerwartung(array('result' => 'WHITE_WINS'), true, 1887, 0);
		$this->assertNull($e['wert']);
		$this->assertFalse($e['geschaetzt']);

		$this->assertNull(Spielerwertung::partieerwartung(null, true)['wert']);
	}

	/**
	 * Die Gewinnerwartung folgt der Wertungsordnung (Normalverteilung,
	 * Streuung 200 × √2). Geprüft gegen Werte, die die Schnittstelle für
	 * dieselben Wertungspaare geliefert hat — die frühere Elo-Formel ergab
	 * für 1887 gegen 1318 nur 0,964.
	 */
	public function testGewinnerwartungNachWertungsordnung(): void
	{
		$this->assertEqualsWithDelta(0.977875, Spielerwertung::gewinnerwartung(1887, 1318), 0.00001);
		$this->assertEqualsWithDelta(0.619457, Spielerwertung::gewinnerwartung(1973, 1887), 0.00001);
		$this->assertEqualsWithDelta(0.525371, Spielerwertung::gewinnerwartung(1905, 1887), 0.00001);
		$this->assertEqualsWithDelta(0.880469, Spielerwertung::gewinnerwartung(1887, 1554), 0.00001);
		$this->assertSame(0.5, Spielerwertung::gewinnerwartung(1800, 1800));

		// Beide Seiten ergänzen sich zu 1
		$this->assertEqualsWithDelta(1.0, Spielerwertung::gewinnerwartung(2100, 1650) + Spielerwertung::gewinnerwartung(1650, 2100), 0.0000001);

		$this->assertNull(Spielerwertung::gewinnerwartung(0, 1800));
		$this->assertNull(Spielerwertung::gewinnerwartung(1800, 0));
	}
}
