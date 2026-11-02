<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Classes\PersonenImport;

// PersonenImport erbt von Contao\Backend; die Zuordnung der Zeilen braucht
// davon nichts. Ohne Contao genügt eine leere Elternklasse
if (!class_exists(\Contao\Backend::class)) {
	eval('namespace Contao; class Backend {}');
}

/**
 * Prüft die Zuordnung einer Zeile der Vereinsmitglieder-CSV auf die Felder
 * der Personentabelle.
 */
class PersonenImportTest extends TestCase
{
	/**
	 * Baut eine Zeile mit Kopfzeilen-Zuordnung, wie ajaxImport() sie an
	 * mappeZeile() übergibt. Die Werte sind erfunden.
	 *
	 * @param array<string,string> $werte Spaltenname => Wert
	 *
	 * @return array{0:array<int,string>,1:array<string,int>} Zeile und Zuordnung
	 */
	private static function zeile(array $werte): array
	{
		$werte += array('InterneNr' => 'NU1', 'ExterneNr' => '1', 'Nachname' => 'Muster', 'Vorname' => 'Max');

		return array(array_values($werte), array_flip(array_keys($werte)));
	}

	/**
	 * Export vom 02.11.2026: „unbekannt" als Geschlecht im Spielbetrieb hat
	 * neun Zeichen, die Spalte acht — der Import brach bei 43 % mit einem
	 * 500er ab. Bekannte Kürzel bleiben, alles andere wird leer.
	 */
	public function testGeschlechtImSpielbetriebPasstInDieSpalte(): void
	{
		foreach (array('m' => 'm', 'w' => 'w', 'd' => 'd', 'unbekannt' => '', '' => '') as $eingabe => $erwartet) {
			[$row, $spalten] = self::zeile(array('GeschlechtImSpielbetrieb' => $eingabe));

			$felder = PersonenImport::mappeZeile($row, $spalten)['felder'];

			$this->assertSame($erwartet, $felder['geschlechtSpielbetrieb'], 'Eingabe „'.$eingabe.'"');
			$this->assertLessThanOrEqual(8, mb_strlen($felder['geschlechtSpielbetrieb']));
		}
	}

	/**
	 * Das Geschlecht selbst wird wie bisher auf die Werte der Schnittstelle
	 * abgebildet, „unbekannt" wird leer.
	 */
	public function testGeschlechtWieBisher(): void
	{
		foreach (array('m' => 'MALE', 'w' => 'FEMALE', 'd' => 'DIVERSE', 'unbekannt' => '') as $eingabe => $erwartet) {
			[$row, $spalten] = self::zeile(array('Geschlecht' => $eingabe));

			$this->assertSame($erwartet, PersonenImport::mappeZeile($row, $spalten)['felder']['gender']);
		}
	}
}
