<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Karteikarte;

// Das Diagramm maskiert Tooltips über Contao\StringUtil. Ohne Contao genügt
// ein Platzhalter mit derselben Wirkung
if (!class_exists(\Contao\StringUtil::class)) {
	eval('namespace Contao; class StringUtil { public static function specialchars($s) { return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8"); } }');
}

/**
 * Prüft das DWZ-Diagramm mit Werten aus dem Wertungsarchiv (ab 2.0.0).
 *
 * Archivpunkte stehen vorn, werden grau hinterlegt und bekommen keine
 * geschätzte Leistung — weder aus Gegnerschnitt und Punkten noch durch
 * Interpolation zwischen den Turnieren von nu.
 */
class KarteikarteDiagrammTest extends TestCase
{
	/**
	 * Baut einen Diagrammpunkt.
	 *
	 * @param string   $jahr     Jahr der Beschriftung
	 * @param int      $dwz      DWZ nach dem Turnier
	 * @param int      $leistung Leistung, 0 = keine
	 * @param bool     $archiv   Punkt aus dem Wertungsarchiv
	 *
	 * @return array<string,mixed> Punkt in der Form von Karteikarte::diagramm()
	 */
	private static function punkt(string $jahr, int $dwz, int $leistung, bool $archiv): array
	{
		return array(
			'nummer' => $archiv ? 'ZDB' : '1', 'jahr' => $jahr, 'dwz' => $dwz, 'leistung' => $leistung,
			'gegner' => $archiv ? 0 : 1800, 'punkte' => $archiv ? 0 : 3, 'partien' => 5,
			'bezeichnung' => 'T'.$jahr, 'archiv' => $archiv,
		);
	}

	/**
	 * Mit Archivpunkten: graues Band, Legende, und die Leistungslinie zeigt
	 * genau die Leistungen von nu — kein geschätzter (hohler) Punkt im Archiv.
	 */
	public function testArchivOhneSchaetzung(): void
	{
		$svg = Karteikarte::diagramm(array(
			self::punkt('1995', 1600, 0, true),
			self::punkt('1998', 1650, 0, true),
			self::punkt('2015', 1700, 1750, false),
			self::punkt('2016', 1710, 0, false),
			self::punkt('2017', 1720, 1800, false),
		));

		$this->assertStringContainsString('fill="#e9edf2"', $svg);
		$this->assertStringContainsString('Wertungsarchiv', $svg);
		$this->assertStringNotContainsString('T1995 (1995): Leistung', $svg);
		$this->assertStringNotContainsString('T1998 (1998): Leistung', $svg);
		// Lücke bei nu wird weiter geschätzt
		$this->assertStringContainsString('T2016 (2016): Leistung', $svg);
	}

	/**
	 * Ohne Archivpunkte bleibt das Diagramm wie bisher: kein Band, keine
	 * Legende des Archivs.
	 */
	public function testOhneArchiv(): void
	{
		$svg = Karteikarte::diagramm(array(
			self::punkt('2015', 1700, 1750, false),
			self::punkt('2016', 1710, 1760, false),
		));

		$this->assertStringNotContainsString('#e9edf2', $svg);
		$this->assertStringNotContainsString('Wertungsarchiv', $svg);
	}
}
