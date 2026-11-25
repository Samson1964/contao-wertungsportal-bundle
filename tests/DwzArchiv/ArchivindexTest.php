<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\DwzArchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\DwzArchiv\Archivindex;

/**
 * Prüft den Index der DWZ-Archivdateien: Dateinamen, Suche nach dem
 * nächstgelegenen Datum, Aufbau aus einem Prüfordner und die Auflösung der
 * Download-Angaben (Sicherheit).
 *
 * Der Prüfordner entsteht für jeden Test neu im Temp-Verzeichnis, mit leeren
 * Zip-Dateien im Aufbau der echten Ordner auf schachbund.de.
 */
class ArchivindexTest extends TestCase
{
	/** @var string */
	private $wurzel;

	/**
	 * Legt den Prüfordner an: Wertungsportal und DeWIS mit Lücken, einem
	 * Landesverband, falsch abgelegten und unpassenden Dateien.
	 */
	protected function setUp(): void
	{
		$this->wurzel = sys_get_temp_dir().'/wp-dwzarchiv-'.getmypid().'-'.mt_rand();

		$dateien = array
		(
			'files/wertungsportal/downloads/2026/csv/LV-0-csv_20260824.zip',
			'files/wertungsportal/downloads/2026/csv/LV-0-csv_20260826.zip',
			'files/wertungsportal/downloads/2026/csv/LV-0-csv_20260828.zip',
			'files/wertungsportal/downloads/2026/csv/LV-0-csv_20260830.zip',
			'files/wertungsportal/downloads/2026/swiss10/dsb-swiss10_20260830.zip',
			'files/wertungsportal/downloads/2026/swiss1003/dsb-swiss1003_20261120.zip',
			'files/wertungsportal/downloads/2026/lv3/LV-3-csv_20260830.zip',
			'files/wertungsportal/downloads/2026/lv3/LV-3-dos_20260830.zip',
			// Im falschen Ordner: gehört zu Verband 4, darf bei 3 nicht erscheinen
			'files/wertungsportal/downloads/2026/lv3/LV-4-csv_20260830.zip',
			// Paßt nicht ins Muster
			'files/wertungsportal/downloads/2026/csv/LV-0-csv.zip',
			// Liegt außerhalb der Jahresordner und wird gar nicht gelesen
			'files/wertungsportal/downloads/export/csv/LV-0-csv_20260830.zip',
			'files/dewis/2026/csv/LV-0-csv_20260704.zip',
			'files/dewis/2026/csv/LV-0-csv_v2_20260704.zip',
			'files/dewis/2026/swiss8/dsb-ws833_20260704.zip',
			'files/dewis/2026/swiss8/dsb-ws822_20260704.zip',
			'files/dewis/2018/lv3/LV-3-sql_v2_20180508.zip',
			// Derselbe Name ein zweites Mal: wird zusammengeführt
			'files/dewis/2027/csv/LV-0-csv_20260704.zip',
		);

		foreach ($dateien as $datei) {
			$pfad = $this->wurzel.'/'.$datei;
			if (!is_dir(\dirname($pfad))) {
				mkdir(\dirname($pfad), 0777, true);
			}
			file_put_contents($pfad, '');
		}

		file_put_contents($this->wurzel.'/files/geheim.zip', 'nicht ausliefern');
	}

	/**
	 * Entfernt den Prüfordner samt Inhalt.
	 */
	protected function tearDown(): void
	{
		$eintraege = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->wurzel, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($eintraege as $eintrag) {
			if ($eintrag->isLink() || $eintrag->isFile()) {
				@unlink($eintrag->getPathname()) || @rmdir($eintrag->getPathname());
			} else {
				rmdir($eintrag->getPathname());
			}
		}

		rmdir($this->wurzel);
	}

	/**
	 * Alle Namensformen der Wertungsportal- und DeWIS-Ordner werden erkannt.
	 *
	 * @dataProvider gueltigeNamen
	 *
	 * @param string $name     Dateiname
	 * @param array  $erwartet Erwartetes Ergebnis von zerlege()
	 */
	public function testZerlegtGueltigeNamen(string $name, array $erwartet): void
	{
		$this->assertSame($erwartet, Archivindex::zerlege($name));
	}

	/**
	 * Liefert gültige Namen mit dem erwarteten Ergebnis.
	 *
	 * @return array<string, array{0: string, 1: array}>
	 */
	public function gueltigeNamen(): array
	{
		$e = static function (string $verband, string $gruppe, string $variante, string $datum): array {
			return array('verband' => $verband, 'gruppe' => $gruppe, 'variante' => $variante, 'datum' => $datum);
		};

		return array
		(
			'DSB CSV'          => array('LV-0-csv_20261125.zip', $e('0', 'csv', '', '20261125')),
			'DSB DOS'          => array('LV-0-dos_20261125.zip', $e('0', 'dos', '', '20261125')),
			'DSB SQL v2'       => array('LV-0-sql_v2_20190101.zip', $e('0', 'sql', 'v2', '20190101')),
			'Berlin CSV v2'    => array('LV-3-csv_v2_20180508.zip', $e('3', 'csv', 'v2', '20180508')),
			'Sachsen DOS'      => array('LV-F-dos_20261125.zip', $e('F', 'dos', '', '20261125')),
			'Schwalbe'         => array('LV-M-csv_20261125.zip', $e('M', 'csv', '', '20261125')),
			'Swiss-Chess 7'    => array('dsb-ws7_20160105.zip', $e('0', 'sc7', 'ws7', '20160105')),
			'Swiss-Chess 822'  => array('dsb-ws822_20200101.zip', $e('0', 'sc8', 'ws822', '20200101')),
			'Swiss-Chess 833'  => array('dsb-ws833_20200101.zip', $e('0', 'sc8', 'ws833', '20200101')),
			'Swiss-Chess alt'  => array('dsb-swiss_20261125.zip', $e('0', 'sc8', 'swiss', '20261125')),
			'Swiss-Chess 10'   => array('dsb-swiss10_20261125.zip', $e('0', 'sc10', 'swiss10', '20261125')),
			'Swiss-Chess 10.03' => array('dsb-swiss1003_20261125.zip', $e('0', 'sc1003', 'swiss1003', '20261125')),
		);
	}

	/**
	 * Namen außerhalb des Musters werden abgewiesen.
	 *
	 * @dataProvider ungueltigeNamen
	 *
	 * @param string $name Dateiname
	 */
	public function testWeistUngueltigeNamenAb(string $name): void
	{
		$this->assertNull(Archivindex::zerlege($name));
	}

	/**
	 * Liefert ungültige Namen.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function ungueltigeNamen(): array
	{
		return array
		(
			'ohne Datum'         => array('LV-0-csv.zip'),
			'Download-Rohdatei'  => array('LV-0-dwzliste_20261125_wertungsportal-version.zip'),
			'falsche Endung'     => array('LV-0-csv_20261125.zip.php'),
			'Großbuchstaben'     => array('LV-0-CSV_20261125.zip'),
			'kein Kalendertag'   => array('LV-0-csv_20261131.zip'),
			'Monat 13'           => array('LV-0-csv_20261301.zip'),
			'Datum zu kurz'      => array('LV-0-csv_2026112.zip'),
			'kleiner Code'       => array('LV-a-csv_20261125.zip'),
			'unbekannte Variante' => array('LV-0-csv_v3_20261125.zip'),
			'unbekanntes Swiss'  => array('dsb-ws9_20261125.zip'),
			'Pfad davor'         => array('../LV-0-csv_20261125.zip'),
		);
	}

	/**
	 * Genauer Treffer: nur dieser Tag, mit Abstand 0.
	 */
	public function testSucheGenauerTreffer(): void
	{
		$ergebnis = Archivindex::suche(self::liste('20260824', '20260826', '20260828'), '20260826');

		$this->assertSame(array(array('20260826', 0)), self::kurz($ergebnis));
	}

	/**
	 * Lücke: der nächste Tag davor und danach, mit Abstand in Tagen.
	 */
	public function testSucheLueckeZeigtDavorUndDanach(): void
	{
		$ergebnis = Archivindex::suche(self::liste('20260824', '20260826', '20260830'), '20260827');

		$this->assertSame(array(array('20260826', -1), array('20260830', 3)), self::kurz($ergebnis));
	}

	/**
	 * Gleicher Abstand davor und danach: beide erscheinen.
	 */
	public function testSucheGleicherAbstandZeigtBeide(): void
	{
		$ergebnis = Archivindex::suche(self::liste('20260826', '20260828'), '20260827');

		$this->assertSame(array(array('20260826', -1), array('20260828', 1)), self::kurz($ergebnis));
	}

	/**
	 * Vor dem ersten Bestand: nur der erste Tag danach.
	 */
	public function testSucheVorDemErstenBestand(): void
	{
		$ergebnis = Archivindex::suche(self::liste('20260824', '20260826'), '20260101');

		$this->assertSame(array(array('20260824', 235)), self::kurz($ergebnis));
	}

	/**
	 * Nach dem letzten Bestand: nur der letzte Tag davor, auch über einen
	 * Jahreswechsel.
	 */
	public function testSucheNachDemLetztenBestand(): void
	{
		$ergebnis = Archivindex::suche(self::liste('20261230', '20261231'), '20270102');

		$this->assertSame(array(array('20261231', -2)), self::kurz($ergebnis));
	}

	/**
	 * Nachbarn: n Tage je Seite. Beim Treffer kommen sie zum Tag hinzu, in
	 * einer Lücke zählt der nächste Tag mit (1 = genau einer davor und einer
	 * danach, bis 1.61.0 kamen zwei); am Rand so viele, wie es gibt.
	 */
	public function testSucheMitNachbarn(): void
	{
		$liste = self::liste('20260820', '20260822', '20260824', '20260826', '20260828');

		$this->assertSame(
			array(array('20260822', -2), array('20260824', 0), array('20260826', 2)),
			self::kurz(Archivindex::suche($liste, '20260824', 1))
		);
		$this->assertSame(
			array(array('20260824', -1), array('20260826', 1)),
			self::kurz(Archivindex::suche($liste, '20260825', 1))
		);
		$this->assertSame(
			array(array('20260822', -3), array('20260824', -1), array('20260826', 1), array('20260828', 3)),
			self::kurz(Archivindex::suche($liste, '20260825', 2))
		);
		$this->assertSame(
			array(array('20260824', -1), array('20260826', 1)),
			self::kurz(Archivindex::suche($liste, '20260825', 0))
		);
		$this->assertSame(
			array(array('20260820', 0), array('20260822', 2), array('20260824', 4)),
			self::kurz(Archivindex::suche($liste, '20260820', 2))
		);
	}

	/**
	 * Leere Liste: leeres Ergebnis.
	 */
	public function testSucheOhneBestand(): void
	{
		$this->assertSame(array(), Archivindex::suche(array(), '20261125'));
	}

	/**
	 * Mehrere Dateien am selben Tag (ws822 und ws833) bilden eine Zeile.
	 */
	public function testSucheBuendeltDateienEinesTages(): void
	{
		$gruppen = $this->index()->dateien('0')['gruppen'];
		$ergebnis = Archivindex::suche($gruppen['sc8'], '20260704');

		$this->assertCount(1, $ergebnis);
		$this->assertSame(array('dsb-ws822_20260704.zip', 'dsb-ws833_20260704.zip'), array_column($ergebnis[0]['dateien'], 'name'));
	}

	/**
	 * Der Aufbau trennt Verbände und Gruppen, sortiert nach Datum, führt
	 * beide Quellen zusammen und übergeht Unpassendes.
	 */
	public function testBautIndexAusDenOrdnern(): void
	{
		$index = $this->index();

		$this->assertSame(array('0', '3'), $index->verbaende());

		$dsb = $index->dateien('0');
		$this->assertSame(array('csv', 'sc8', 'sc10', 'sc1003'), array_keys($dsb['gruppen']));

		// DeWIS (04.07.) und Wertungsportal (ab 24.08.) in einer Liste; der
		// doppelte Name aus 2027 ist zusammengeführt, export/ nicht gelesen
		$this->assertSame(
			array('20260704', '20260704', '20260824', '20260826', '20260828', '20260830'),
			array_column($dsb['gruppen']['csv'], 'datum')
		);
		$this->assertSame('dewis/2026/csv/LV-0-csv_20260704.zip', $dsb['gruppen']['csv'][0]['pfad']);
		$this->assertSame('LV-0-csv_v2_20260704.zip', $dsb['gruppen']['csv'][1]['name']);
		$this->assertSame('wertungsportal', $dsb['gruppen']['csv'][2]['quelle']);
		$this->assertSame(array('wertungsportal/2026/csv/LV-0-csv.zip'), $dsb['ignoriert']);

		$berlin = $index->dateien('3');
		$this->assertSame(array('csv', 'dos', 'sql'), array_keys($berlin['gruppen']));
		$this->assertSame(array('wertungsportal/2026/lv3/LV-4-csv_20260830.zip'), $berlin['ignoriert']);
		$this->assertSame('v2', $berlin['gruppen']['sql'][0]['variante']);

		$this->assertSame(array('gruppen' => array(), 'ignoriert' => array()), $index->dateien('7'));
	}

	/**
	 * Gültige Angaben führen auf die Datei.
	 */
	public function testLoestGueltigeAngabeAuf(): void
	{
		$datei = $this->index()->aufloesen('wertungsportal/2026/csv/LV-0-csv_20260830.zip');

		$this->assertNotNull($datei);
		$this->assertSame('LV-0-csv_20260830.zip', basename($datei));
		$this->assertNotNull($this->index()->aufloesen('dewis/2026/swiss8/dsb-ws822_20260704.zip'));
	}

	/**
	 * Angaben, die aus dem Basisordner herausführen oder nicht ins Muster
	 * passen, werden abgewiesen.
	 *
	 * @dataProvider unsichereAngaben
	 *
	 * @param string $angabe Pfad aus der Adresse
	 */
	public function testWeistUnsichereAngabenAb(string $angabe): void
	{
		$this->assertNull($this->index()->aufloesen($angabe));
	}

	/**
	 * Liefert Angaben, die nicht zu einer Datei führen dürfen.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function unsichereAngaben(): array
	{
		return array
		(
			'leer'                  => array(''),
			'Punkte'                => array('wertungsportal/2026/../2026/LV-0-csv_20260830.zip'),
			'Punkte nach oben'      => array('wertungsportal/../../geheim.zip'),
			'Punkt'                 => array('wertungsportal/2026/./LV-0-csv_20260830.zip'),
			'absolut'               => array('/etc/passwd'),
			'absolut mit Quelle'    => array('/wertungsportal/2026/csv/LV-0-csv_20260830.zip'),
			'Laufwerk'              => array('C:/Windows/win.ini'),
			'Rückstrich'            => array('wertungsportal\\2026\\csv\\LV-0-csv_20260830.zip'),
			'Nullbyte'              => array("wertungsportal/2026/csv/LV-0-csv_20260830.zip\0.txt"),
			'doppelter Strich'      => array('wertungsportal//2026/csv/LV-0-csv_20260830.zip'),
			'unbekannte Quelle'     => array('files/2026/csv/LV-0-csv_20260830.zip'),
			'fremde Endung'         => array('wertungsportal/2026/csv/LV-0-csv_20260830.php'),
			'ohne Datum'            => array('wertungsportal/2026/csv/LV-0-csv.zip'),
			'kein Jahresordner'     => array('wertungsportal/export/csv/LV-0-csv_20260830.zip'),
			'zu flach'              => array('wertungsportal/LV-0-csv_20260830.zip'),
			'zu tief'               => array('wertungsportal/2026/csv/x/LV-0-csv_20260830.zip'),
			'fehlende Datei'        => array('wertungsportal/2026/csv/LV-0-csv_20260825.zip'),
		);
	}

	/**
	 * Ein Symlink, der aus dem Basisordner herausführt, wird abgewiesen.
	 * Unter Windows ohne Rechte für Symlinks wird die Prüfung übersprungen.
	 */
	public function testWeistSymlinkNachAussenAb(): void
	{
		$ziel = $this->wurzel.'/files/geheim.zip';
		$verweis = $this->wurzel.'/files/wertungsportal/downloads/2026/csv/LV-0-csv_20260101.zip';

		if (!@symlink($ziel, $verweis)) {
			$this->markTestSkipped('Symlinks lassen sich hier nicht anlegen.');
		}

		$this->assertNull($this->index()->aufloesen('wertungsportal/2026/csv/LV-0-csv_20260101.zip'));
	}

	/**
	 * Erzeugt den Index über dem Prüfordner, ohne Cache.
	 */
	private function index(): Archivindex
	{
		return new Archivindex($this->wurzel, array('wertungsportal' => 'files/wertungsportal/downloads', 'dewis' => 'files/dewis'));
	}

	/**
	 * Baut eine sortierte Liste mit je einer Datei je Tag.
	 *
	 * @param string ...$tage Tage als JJJJMMTT, aufsteigend
	 *
	 * @return list<array>
	 */
	private static function liste(string ...$tage): array
	{
		return array_map(static function (string $tag): array {
			return array('name' => 'LV-0-csv_'.$tag.'.zip', 'datum' => $tag);
		}, $tage);
	}

	/**
	 * Verkürzt ein Suchergebnis auf [Datum, Abstand] je Zeile.
	 *
	 * @param list<array> $ergebnis Ergebnis von suche()
	 *
	 * @return list<array{0: string, 1: int}>
	 */
	private static function kurz(array $ergebnis): array
	{
		return array_map(static function (array $zeile): array {
			return array($zeile['datum'], $zeile['abstand']);
		}, $ergebnis);
	}
}
