<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Karteikarte;

/**
 * Prüft die Regel, nach der eine DWZ-Umstufung in die Karteikarte kommt.
 *
 * Anlaß ist eine Meldung vom 24.09.2026: Bei NU4112056 stand in der Kartei die
 * Zeile „Umstufung 2026 (08.06.2026)" ganz ohne Wertung — die Schnittstelle
 * liefert dort nur Stichtag und Name. Der Spieler hat keine DWZ und kein
 * ausgewertetes Turnier, die jährliche Umstufung ist bei ihm folgenlos
 * geblieben.
 */
class KarteikarteTest extends TestCase
{
	/**
	 * Genau die Antwort, die nu für NU4112056 schickt: keine einzige Zahl.
	 */
	public function testUmstufungOhneJedeAngabe(): void
	{
		$this->assertTrue(Karteikarte::umstufungOhneWerte(array(
			'referenceDate' => '2026-06-08',
			'name'          => 'Umstufung 2026',
		)));
	}

	/**
	 * Dieselbe Umstufung aus der örtlichen Spiegelung: Dort stehen Nullen, weil
	 * die Spalten Ganzzahlen sind. Der Notbetrieb darf nichts anderes zeigen
	 * als der Abruf bei nu.
	 */
	public function testUmstufungMitLauterNullen(): void
	{
		$this->assertTrue(Karteikarte::umstufungOhneWerte(array(
			'referenceDate' => '2026-06-08',
			'name'          => 'Umstufung 2026',
			'ratingOld'     => 0,
			'indexOld'      => 0,
			'ratingNew'     => 0,
			'indexNew'      => 0,
		)));
	}

	/**
	 * Der Regelfall — eine Umstufung, die eine DWZ ausweist.
	 */
	public function testUmstufungMitWerten(): void
	{
		$this->assertFalse(Karteikarte::umstufungOhneWerte(array(
			'referenceDate' => '2026-06-08',
			'name'          => 'Umstufung 2026',
			'ratingOld'     => 2841,
			'indexOld'      => 105,
			'ratingNew'     => 2841,
			'indexNew'      => 106,
		)));
	}

	/**
	 * Nur die alte Wertung bekannt: Das kann eine Streichung sein und bleibt
	 * deshalb in der Kartei stehen.
	 */
	public function testUmstufungNurMitAlterWertung(): void
	{
		$this->assertFalse(Karteikarte::umstufungOhneWerte(array(
			'referenceDate' => '2026-06-08',
			'name'          => 'Streichung',
			'ratingOld'     => 1420,
			'indexOld'      => 12,
		)));
	}

	/**
	 * Zahlen als Zeichenketten — so kommen sie aus der Datenbank.
	 */
	public function testZahlenAlsZeichenketten(): void
	{
		$this->assertFalse(Karteikarte::umstufungOhneWerte(array(
			'referenceDate' => '2026-06-08',
			'name'          => 'Umstufung 2026',
			'ratingOld'     => '0',
			'indexOld'      => '0',
			'ratingNew'     => '1318',
			'indexNew'      => '1',
		)));
	}

	/**
	 * Ein leerer Eintrag ist leer, auch ohne Stichtag und Namen.
	 */
	public function testGanzLeererEintrag(): void
	{
		$this->assertTrue(Karteikarte::umstufungOhneWerte(array()));
	}
}
