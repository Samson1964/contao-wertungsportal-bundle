<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;

// Helper erbt von Contao\Frontend; mitTitel() braucht davon nichts. Ohne
// Contao genügt eine leere Elternklasse
if (!class_exists(\Contao\Frontend::class)) {
	eval('namespace Contao; class Frontend {}');
}

/**
 * Prüft die Schreibweise des akademischen Titels am Namen (ab 1.59.0).
 */
class TitelTest extends TestCase
{
	/**
	 * Vorgabe Frank Binding: Der Titel steht hinten, „Müller,Hans,Dr.";
	 * das Trennzeichen folgt der Stelle (Listen mit, Karteikarte ohne
	 * Leerzeichen). Ohne Titel bleibt der Name, wie er ist.
	 */
	public function testTitelHinten(): void
	{
		$this->assertSame('Müller, Hans, Dr.', Helper::mitTitel('Müller, Hans', 'Dr.'));
		$this->assertSame('Müller,Hans,Prof. Dr.', Helper::mitTitel('Müller,Hans', 'Prof. Dr.', ','));
		$this->assertSame('Müller, Hans', Helper::mitTitel('Müller, Hans', ''));
		$this->assertSame('Müller, Hans', Helper::mitTitel('Müller, Hans', '  '));
	}

	/**
	 * Teilnehmer ohne NU-Nummer (Nichtmitglieder) haben keinen Titel —
	 * dafür wird nicht einmal die Datenbank gefragt.
	 */
	public function testOhneNuNummerKeinTitel(): void
	{
		$this->assertSame('', Helper::titel(''));
		$this->assertSame('', Helper::titel(null));
	}
}
