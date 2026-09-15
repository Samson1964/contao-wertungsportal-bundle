<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Classes\DosFormat;

/**
 * Prüft das alte DOS-Format der DWZ-Archive, wie der DeWIS-Server es geliefert
 * hat (Vorlage `LV-0-dos_20240627.zip`): Pipe als Trenner, keine Kopfzeile,
 * DWZ und Index in einem Feld, Codepage 850, CRLF.
 *
 * Die Namen sind erfunden; Aufbau und Byte-Werte stammen aus der Vorlage.
 */
class DosFormatTest extends TestCase
{
	/**
	 * Kopfzeile der spieler.csv, wie nu sie liefert.
	 */
	private const KOPF_SPIELER = array('ID', 'ZPS', 'Mitgliedsnummer', 'Status', 'Name,Vorname', 'Geschlecht',
		'Spielberechtigung', 'Geburtsjahr', 'Letzte Auswertung', 'DWZ', 'Index', 'FIDE-Elozahl', 'FIDE-Titel',
		'FIDE-ID', 'FIDE-Land', 'Vorname', 'Nachname');

	/**
	 * Baut eine Zeile der spieler.csv zur Kopfzeile oben.
	 *
	 * @param array $abweichend Überschrift => Wert, der vom Grundmuster abweicht
	 *
	 * @return array Felder in der Reihenfolge der Kopfzeile
	 */
	private function spieler(array $abweichend = array()): array
	{
		$grund = array_combine(self::KOPF_SPIELER, array('NU4005017', 'C0505', '1043', 'A', 'Muster,Max', 'M', '',
			'1963', '202611', '1802', '45', '1850', 'FM', '4711', 'GER', 'Max', 'Muster'));

		return array_values(array_merge($grund, $abweichend));
	}

	/**
	 * Eine Mitgliedschaft wird zu 14 Feldern: nu-ID vorn, DWZ und Index als
	 * „1802-45", Vorname und Nachname fallen weg, CRLF am Ende, keine
	 * Kopfzeile.
	 */
	public function testSpielerzeileWieBeimDewisServer(): void
	{
		$inhalt = DosFormat::spielerTxt(array(self::KOPF_SPIELER, $this->spieler()));

		$this->assertSame("NU4005017|C0505|1043|A|Muster,Max|M||1963|202611|1802-45|1850|FM|4711|GER\r\n", $inhalt);
		$this->assertCount(14, explode('|', rtrim($inhalt, "\r\n")));
	}

	/**
	 * Ohne DWZ steht „0-0" wie in der Vorlage (dort bei allen Spielern mit
	 * Restpartien); fehlt nur der Index, steht eine 0 dahinter.
	 */
	public function testDwzIndex(): void
	{
		$this->assertSame('0-0', DosFormat::dwzIndex('', ''));
		$this->assertSame('0-0', DosFormat::dwzIndex('0', '0'));
		$this->assertSame('1500-0', DosFormat::dwzIndex('1500', ''));
		$this->assertSame('2843-103', DosFormat::dwzIndex('2843', '103'));

		$inhalt = DosFormat::spielerTxt(array(self::KOPF_SPIELER, $this->spieler(array('DWZ' => '', 'Index' => ''))));
		$this->assertSame('0-0', explode('|', $inhalt)[9]);
	}

	/**
	 * Die Spalten werden über ihre Überschriften gefunden — eine umgestellte
	 * oder angehängte Spalte ändert nichts am Ergebnis.
	 */
	public function testSpaltenUeberIhreNamen(): void
	{
		$erwartet = DosFormat::spielerTxt(array(self::KOPF_SPIELER, $this->spieler()));

		$kopf = array_reverse(self::KOPF_SPIELER);
		$kopf[] = 'FIDE-Frauentitel';
		$zeile = array_reverse($this->spieler());
		$zeile[] = 'WFM';

		$this->assertSame($erwartet, DosFormat::spielerTxt(array($kopf, $zeile)));
	}

	/**
	 * Fehlt eine gebrauchte Spalte, entsteht keine Datei mit verrutschten
	 * Feldern, sondern ein Abbruch, der die Spalte nennt.
	 */
	public function testFehlendeSpalteBrichtAb(): void
	{
		$kopf = self::KOPF_SPIELER;
		$kopf[9] = 'DWZ-Zahl';

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessageMatches('/DWZ/');

		DosFormat::spielerTxt(array($kopf, $this->spieler()));
	}

	/**
	 * Ein BOM vor der ersten Überschrift stört die Zuordnung nicht, und
	 * Leerzeilen fallen weg.
	 */
	public function testBomUndLeerzeilen(): void
	{
		$kopf = self::KOPF_SPIELER;
		$kopf[0] = "\xEF\xBB\xBF".$kopf[0];

		$inhalt = DosFormat::spielerTxt(array($kopf, array(null), $this->spieler(), array('')));

		$this->assertSame(1, substr_count($inhalt, "\r\n"));
		$this->assertStringStartsWith('NU4005017|', $inhalt);
	}

	/**
	 * Ein „|" im Wert würde das Feld zerteilen und wird zu „/"; Zeilenumbrüche
	 * und Tabulatoren werden zu Leerzeichen, außen stehende Leerzeichen fallen
	 * weg.
	 */
	public function testTrennzeichenImWert(): void
	{
		$this->assertSame('SK Muster/Beispiel', DosFormat::feld(' SK Muster|Beispiel '));
		$this->assertSame('SK Muster Beispiel', DosFormat::feld("SK Muster\r\nBeispiel"));
		$this->assertSame('a b', DosFormat::feld("a\tb"));
	}

	/**
	 * Wandlung nach CP850 mit den Bytes der Vorlage: ü = 0x81, ö = 0x94,
	 * ý = 0xEC, Ò = 0xE3, ´ = 0xEF, ß = 0xE1. Zeichen, die CP850 nicht kennt,
	 * werden zu ASCII — auf jedem Server gleich, ohne `//TRANSLIT`.
	 */
	public function testCp850(): void
	{
		$this->assertSame("M\x81ller,J\x94rg", DosFormat::nachCp850("M\xFCller,J\xF6rg"));
		$this->assertSame("Novotn\xEC", DosFormat::nachCp850("Novotn\xFD"));
		$this->assertSame("Dell \xE3ro", DosFormat::nachCp850("Dell \xD2ro"));
		$this->assertSame("D\xEFAntuono", DosFormat::nachCp850("D\xB4Antuono"));
		$this->assertSame("Gro\xE1", DosFormat::nachCp850("Gro\xDF"));
		$this->assertSame('Sasa', DosFormat::nachCp850("\x8Aa\x9Aa"), 'Š und š');
		$this->assertSame('"Max" - 5 EUR', DosFormat::nachCp850("\x93Max\x94 \x96 5 \x80"));
		$this->assertSame('AB', DosFormat::nachCp850("A\x81B"), 'in windows-1252 unbelegtes Byte');
	}

	/**
	 * VEREINE.TXT und VERBAENDE.TXT: vier Felder, keine Kopfzeile, CRLF, Namen
	 * ohne Anführungszeichen — auch mit Komma. Verbandseinträge mit einer
	 * Kennziffer auf „00" bleiben aus VEREINE.TXT heraus; VERBAENDE.TXT
	 * übernimmt alle Zeilen, auch die Wurzel „000".
	 */
	public function testVereineUndVerbaende(): void
	{
		$vereine = DosFormat::vereineTxt(array(
			array('ZPS-Nummer', 'Landesverband', 'UebergeordneterVerband', 'Vereinsname'),
			array('30000', '3', '300', 'Musterverband e.V.'),
			array('30101', '3', '300', "SV M\xFCsterhausen, e.V."),
		));

		$verbaende = DosFormat::verbaendeTxt(array(
			array('Verbandnummer', 'Landesverband', 'UebergeordneterVerband', 'Verbandname'),
			array('000', '0', '', 'Deutschland'),
			array('300', '3', '000', 'Musterverband'),
		));

		$this->assertSame("30101|3|300|SV M\x81sterhausen, e.V.\r\n", $vereine);
		$this->assertSame("000|0||Deutschland\r\n300|3|000|Musterverband\r\n", $verbaende);
	}

	/**
	 * Verbandseinträge haben eine Kennziffer auf „00". L0001 und M0001 zählen
	 * hier — anders als in Helper::istVerband() — als Verein, weil dort
	 * Mitglieder gemeldet sind.
	 */
	public function testIstVerband(): void
	{
		$this->assertTrue(DosFormat::istVerband('10000'));
		$this->assertTrue(DosFormat::istVerband('10A00'));
		$this->assertFalse(DosFormat::istVerband('30101'));
		$this->assertFalse(DosFormat::istVerband('C0505'));
		$this->assertFalse(DosFormat::istVerband('L0001'));
		$this->assertFalse(DosFormat::istVerband('M0001'));
	}

	/**
	 * README.TXT: Verbandszeile und Datum aus der README der CSV-Fassung, die
	 * Zahlen aus den Zeilen der erzeugten Dateien und ohne Tausendertrenner,
	 * danach der Text der Vorlage mit der nu-ID als erstem Feld — in CP850 und
	 * mit CRLF in jeder Zeile.
	 */
	public function testReadme(): void
	{
		$vorlage = "Landesverband: H - Th\xFCringen\r\n\r\nDWZ-Datenbank vom 15.09.2026 - 96,673 Spieler in 2,385 Vereinen\r\n\r\n===\r\n";

		$text = DosFormat::readme($vorlage, 1234, 56);

		$this->assertStringStartsWith("Landesverband: H - Th\x81ringen\r\n\r\nDWZ-Datenbank vom 15.09.2026 - 1234 Spieler in 56 Vereinen\r\n\r\n=====", $text);
		$this->assertStringContainsString("SPIELER.TXT - sortiert nach DWZ\r\n-----------\r\n- ID des Mitglieds (nu-ID)\r\n- ZPS-Nummer des Vereins\r\n", $text);
		$this->assertStringContainsString('VERBAENDE.TXT - sortiert nach Verbandnummer', $text);
		$this->assertStringContainsString("M\x84nnlich", $text);
		$this->assertStringContainsString('Absprache', $text);
		$this->assertSame(substr_count($text, "\n"), substr_count($text, "\r\n"), 'jede Zeile endet mit CRLF');
		$this->assertStringNotContainsString("\r\r", $text);
	}

	/**
	 * Ohne verwertbare Vorlage bleibt die Verbandszeile leer, und das Datum
	 * kommt aus dem Aufruf.
	 */
	public function testReadmeOhneVorlage(): void
	{
		$text = DosFormat::readme('', 10, 2, '15.09.2026');

		$this->assertStringStartsWith("Landesverband:\r\n\r\nDWZ-Datenbank vom 15.09.2026 - 10 Spieler in 2 Vereinen\r\n\r\n", $text);
	}
}
