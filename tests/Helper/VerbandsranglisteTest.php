<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Verbandsrangliste;

/**
 * Prüft die Flaggenausgabe der Verbandsrangliste.
 *
 * Anlaß ist das Systemprotokoll des Livesystems: `{{flagge::}}` ohne Kürzel
 * war dort der mit Abstand häufigste Eintrag („Unknown insert tag") und
 * verdeckte die Meldungen, auf die es ankommt. Ein Spieler ohne FIDE-Eintrag
 * hat schlicht keine Nation.
 */
class VerbandsranglisteTest extends TestCase
{
	/**
	 * Ruft die geschützte Methode `flagge()` auf, ohne die Klasse zu bauen.
	 *
	 * Der Konstruktor stößt sofort `compile()` an und braucht dafür
	 * API-Ergebnisse und eine Datenbank — für die Prüfung einer reinen
	 * Textfunktion wäre das unverhältnismäßig.
	 *
	 * @param mixed $nation Länderkürzel
	 *
	 * @return string Ergebnis von flagge()
	 */
	private function flagge($nation): string
	{
		$klasse = new \ReflectionClass(Verbandsrangliste::class);
		$objekt = $klasse->newInstanceWithoutConstructor();
		$methode = $klasse->getMethod('flagge');
		$methode->setAccessible(true);

		return (string) $methode->invoke($objekt, $nation);
	}

	/**
	 * Ein echtes Länderkürzel ergibt das Insert-Tag.
	 */
	public function testEchtesKuerzel(): void
	{
		$this->assertSame('{{flagge::GER}}', $this->flagge('GER'));
		$this->assertSame('{{flagge::NOR}}', $this->flagge('NOR'));
		$this->assertSame('{{flagge::IND}}', $this->flagge('IND'));
	}

	/**
	 * Kleinschreibung und Leerzeichen aus der Tabelle stören nicht.
	 */
	public function testKuerzelWirdAufgeraeumt(): void
	{
		$this->assertSame('{{flagge::GER}}', $this->flagge('ger'));
		$this->assertSame('{{flagge::GER}}', $this->flagge(' GER '));
	}

	/**
	 * Ohne FIDE-Eintrag entsteht KEIN Insert-Tag.
	 *
	 * Die Elo-Tabelle liefert dann einen leeren Text; `Helper::leererFIDESatz()`
	 * setzt in anderen Fällen ein „-" ein. Beides darf nicht zu `{{flagge::}}`
	 * werden.
	 */
	public function testOhneNationKeinInsertTag(): void
	{
		$this->assertSame('', $this->flagge(''), 'leer');
		$this->assertSame('', $this->flagge('-'), 'Platzhalter der Elo-Tabelle');
		$this->assertSame('', $this->flagge(null), 'gar nichts geliefert');
		$this->assertSame('', $this->flagge(false), 'leererFIDESatz kann false führen');
		$this->assertSame('', $this->flagge('   '), 'nur Leerzeichen');
	}

	/**
	 * Was kein dreistelliges Buchstabenkürzel ist, ergibt ebenfalls nichts.
	 *
	 * Ein Insert-Tag mit Unsinn darin wäre so nutzlos wie eines ohne Inhalt —
	 * und stünde genauso im Protokoll.
	 */
	public function testUnsinnErgibtNichts(): void
	{
		$this->assertSame('', $this->flagge('DE'), 'zwei Buchstaben');
		$this->assertSame('', $this->flagge('GERM'), 'vier Buchstaben');
		$this->assertSame('', $this->flagge('12'), 'Ziffern');
		$this->assertSame('', $this->flagge('G3R'), 'Ziffer mittendrin');
	}
}
