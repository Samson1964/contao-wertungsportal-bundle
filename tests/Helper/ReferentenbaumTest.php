<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Referentenbaum;

/**
 * Prüft das Verschachteln der Referenten-Gliederung für das Modul
 * „Wertungsreferenten (Baum)" (1.53.0).
 */
class ReferentenbaumTest extends TestCase
{
	/**
	 * Zeile, wie sie Referentenbaum::baum() liefert.
	 *
	 * @return array<string,mixed>
	 */
	private static function zeile(string $vkz, string $name): array
	{
		return array('vkz' => $vkz, 'name' => $name, 'ebene' => 'level_x', 'referenten' => array());
	}

	/**
	 * Hilfsfunktion: der Baum als „VKZ(Kinder …)" — lesbar im Vergleich.
	 *
	 * @param array<int,array<string,mixed>> $knoten Knoten
	 */
	private static function kurz(array $knoten): string
	{
		return implode(' ', array_map(static function (array $k): string {
			return $k['vkz'].($k['kinder'] ? '('.self::kurz($k['kinder']).')' : '');
		}, $knoten));
	}

	/**
	 * Der DSB fällt weg, die Landesverbände stehen oben, Bezirke darunter —
	 * und in Bayern/Sachsen die Kreise unter ihrem Bezirk (27100 unter 27000,
	 * F1500 unter F1000). Numerische Kennziffern bleiben Zeichenketten.
	 */
	public function testVerschachtelt(): void
	{
		$zeilen = array(
			self::zeile('00000', 'Deutscher Schachbund'),
			self::zeile('10000', 'Baden'),
			self::zeile('10100', 'Mannheim'),
			self::zeile('10200', 'Heidelberg'),
			self::zeile('20000', 'Bayern'),
			self::zeile('27000', 'Schwaben'),
			self::zeile('27100', 'Augsburg'),
			self::zeile('F0000', 'Sachsen'),
			self::zeile('F1000', 'Leipzig'),
			self::zeile('F1500', 'Stadt Leipzig'),
			self::zeile('L0001', 'Sonderfall'),
		);

		$baum = Referentenbaum::verschachtelt($zeilen);

		$this->assertSame('10000(10100 10200) 20000(27000(27100)) F0000(F1000(F1500)) L0001', self::kurz($baum));
		$this->assertSame('10000', $baum[0]['vkz']);
		$this->assertSame('Baden', $baum[0]['name']);
	}

	/**
	 * Fehlt eine Zwischenebene, hängt ein Bezirk am Landesverband; fehlt auch
	 * der, steht er oben. Mit leerer Weglassliste bleibt der DSB drin.
	 */
	public function testLueckenUndWeglassen(): void
	{
		$zeilen = array(self::zeile('00000', 'DSB'), self::zeile('20000', 'Bayern'), self::zeile('27100', 'Augsburg'), self::zeile('30100', 'Ohne Landesverband'));

		$this->assertSame('20000(27100) 30100', self::kurz(Referentenbaum::verschachtelt($zeilen)));
		$this->assertSame('00000 20000(27100) 30100', self::kurz(Referentenbaum::verschachtelt($zeilen, array())));
		$this->assertSame(array(), Referentenbaum::verschachtelt(array()));
	}
}
