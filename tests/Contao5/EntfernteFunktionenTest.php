<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Contao5;

use PHPUnit\Framework\TestCase;

/**
 * Wächter gegen drei Bauarten, die unter Contao 4.13 laufen und unter Contao 5
 * abbrechen oder wirkungslos bleiben. Alle drei standen bis 1.44.0 im Bundle
 * und sind erst beim Rendern der Backend- und Frontend-Seiten in der
 * 5.7-Prüfinstallation aufgefallen — ein Prüfstand, den nicht jede Änderung
 * durchläuft. Die Tests lesen die Dateien als Text; sie brauchen kein Contao.
 */
class EntfernteFunktionenTest extends TestCase
{
	/**
	 * Globale Funktionen, die Contao 4.13 noch mitbringt (abgekündigt) und
	 * Contao 5 nicht mehr kennt. `ampersand()` in den drei Import-Vorlagen
	 * ließ unter Contao 5 jede der drei Import-Ansichten mit „Call to
	 * undefined function" abbrechen.
	 */
	private const FUNKTIONEN = array(
		'ampersand', 'specialchars', 'standardize', 'deserialize', 'trimsplit', 'scan',
		'nl2br_html5', 'nl2br_pre', 'nl2br_callback', 'array_insert', 'array_duplicate',
		'array_move_up', 'array_move_down', 'array_delete', 'array_is_assoc',
		'basename_natcasesort', 'natcaseksort', 'strip_insert_tags', 'utf8_chr', 'utf8_ord',
		'utf8_convert_encoding', 'utf8_decode_entities', 'utf8_chr_callback', 'utf8_hexchr_callback',
		'utf8_strlen', 'utf8_strpos', 'utf8_strrchr', 'utf8_strrpos', 'utf8_strstr', 'utf8_strtolower',
		'utf8_strtoupper', 'utf8_substr', 'utf8_ucfirst', 'utf8_str_split', 'utf8_romanize',
	);

	/**
	 * Keine Vorlage und keine Klasse ruft eine der entfernten globalen
	 * Funktionen auf. Statische Aufrufe wie `StringUtil::ampersand()` sind
	 * erlaubt und der richtige Ersatz.
	 */
	public function testKeineEntferntenGlobalenFunktionen(): void
	{
		$muster = '/(?<![\\\\:>$\w])(?:'.implode('|', self::FUNKTIONEN).')\s*\(/';
		$funde = array();

		foreach (self::dateien(array('php', 'html5')) as $datei) {
			foreach (self::codezeilen($datei) as $nummer => $zeile) {
				if (preg_match($muster, $zeile)) {
					$funde[] = self::relativ($datei).':'.$nummer.': '.trim($zeile);
				}
			}
		}

		$this->assertSame(array(), $funde, "Entfernte globale Funktion (Contao 5), Ersatz meist in Contao\\StringUtil oder Contao\\ArrayUtil:\n".implode("\n", $funde));
	}

	/**
	 * Der DCA-Treiber steht als Klassenname, nicht als Kurzform. Contao 5 kennt
	 * `'dataContainer' => 'Table'` nicht mehr („Class "Table" not found") —
	 * so fiel bis 1.44.0 das Backend-Modul Statistik aus. `DC_Table::class`
	 * versteht auch Contao 4.13.
	 */
	public function testDcaTreiberAlsKlassenname(): void
	{
		$funde = array();

		foreach (self::dateien(array('php'), 'src/Resources/contao/dca') as $datei) {
			foreach (self::codezeilen($datei) as $nummer => $zeile) {
				if (preg_match('/[\'"]dataContainer[\'"]\s*=>\s*[\'"](Table|File|Folder)[\'"]/', $zeile)) {
					$funde[] = self::relativ($datei).':'.$nummer.': '.trim($zeile);
				}
			}
		}

		$this->assertSame(array(), $funde, "DCA-Treiber als Kurzform — Contao 5 verlangt den Klassennamen (\\Contao\\DC_Table::class):\n".implode("\n", $funde));
	}

	/**
	 * Keine Weiterleitung über header('Location: …'). Symfony setzt den Status
	 * danach wieder auf 200, ein Browser folgt dem Location-Header dann nicht.
	 * Der Weg in beiden Fassungen ist `Controller::redirect()`, das die
	 * RedirectResponseException wirft. Bis 1.44.0 blieben so der Sprung zum
	 * einzigen Vereinstreffer und die alten `?pkz=`-Verweise wirkungslos.
	 */
	public function testKeineWeiterleitungPerHeader(): void
	{
		$funde = array();

		foreach (self::dateien(array('php', 'html5')) as $datei) {
			foreach (self::codezeilen($datei) as $nummer => $zeile) {
				if (preg_match('/\bheader\s*\(\s*[\'"]Location:/i', $zeile)) {
					$funde[] = self::relativ($datei).':'.$nummer.': '.trim($zeile);
				}
			}
		}

		$this->assertSame(array(), $funde, "Weiterleitung per header('Location') wirkt nicht — Controller::redirect() verwenden:\n".implode("\n", $funde));
	}

	/**
	 * Liefert die Codezeilen einer Datei ohne Kommentarzeilen — ein Verweis
	 * auf `utf8_decode()` in einem Docblock ist kein Aufruf.
	 *
	 * @param string $datei Pfad
	 *
	 * @return array Zeilennummer => Zeile
	 */
	private static function codezeilen(string $datei): array
	{
		$zeilen = array();

		foreach (file($datei) as $i => $zeile) {
			$kurz = ltrim($zeile);
			if ($kurz === '' || strpos($kurz, '*') === 0 || strpos($kurz, '//') === 0 || strpos($kurz, '/*') === 0) {
				continue;
			}
			$zeilen[$i + 1] = $zeile;
		}

		return $zeilen;
	}

	/**
	 * Sammelt die Dateien unter src/ mit den genannten Endungen.
	 *
	 * @param array  $endungen Dateiendungen ohne Punkt
	 * @param string $ordner   Unterordner des Bundles
	 *
	 * @return array Pfade
	 */
	private static function dateien(array $endungen, string $ordner = 'src'): array
	{
		$dateien = array();
		$wurzel = dirname(__DIR__, 2).'/'.$ordner;

		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($wurzel, \FilesystemIterator::SKIP_DOTS)) as $eintrag) {
			if (\in_array(strtolower($eintrag->getExtension()), $endungen, true)) {
				$dateien[] = $eintrag->getPathname();
			}
		}

		sort($dateien);

		return $dateien;
	}

	/**
	 * Kürzt einen Pfad auf den Teil unterhalb des Bundles.
	 *
	 * @param string $datei Pfad
	 *
	 * @return string
	 */
	private static function relativ(string $datei): string
	{
		return str_replace('\\', '/', substr($datei, \strlen(dirname(__DIR__, 2)) + 1));
	}
}
