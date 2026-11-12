<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;

// Helper erbt von Contao\Frontend; ApiDatum() braucht davon nichts. Ohne
// Contao genügt eine leere Elternklasse
if (!class_exists(\Contao\Frontend::class)) {
	eval('namespace Contao; class Frontend {}');
}

/**
 * Prüft die fehlertolerante Datumsumwandlung für Werte der Schnittstelle.
 */
class ApiDatumTest extends TestCase
{
	/**
	 * nu liefert lastCalculated inzwischen mit Millisekunden. Bis 1.56.1
	 * stand „Letzte Berechnung am" bei solchen Turnieren auf „unbekannt".
	 */
	public function testSekundenbruchteileWerdenUebergangen(): void
	{
		$this->assertSame('12.11.2026 08:44', Helper::ApiDatum('2026-11-12T08:44:09.363', 'Y-m-d\TH:i:s', 'd.m.Y H:i'));
		$this->assertSame('12.11.2026 08:44', Helper::ApiDatum('2026-11-12T08:44:09', 'Y-m-d\TH:i:s', 'd.m.Y H:i'));
	}

	/**
	 * Reine Datumswerte und fehlende oder kaputte Werte wie bisher.
	 */
	public function testUebrigeFaelleUnveraendert(): void
	{
		$this->assertSame('02.08.2026', Helper::ApiDatum('2026-08-02'));
		$this->assertSame('unbekannt', Helper::ApiDatum(null, 'Y-m-d\TH:i:s', 'd.m.Y H:i'));
		$this->assertSame('unbekannt', Helper::ApiDatum('kaputt', 'Y-m-d\TH:i:s', 'd.m.Y H:i'));
		$this->assertSame('', Helper::ApiDatum('', 'Y-m-d', 'd.m.Y', ''));
	}
}
