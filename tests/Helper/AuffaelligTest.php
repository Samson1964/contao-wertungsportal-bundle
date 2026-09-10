<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Auffaellig;

/**
 * Prüft das Protokoll der unmöglichen Werte — vor allem, daß jeder Befund
 * genau einmal darin steht.
 *
 * Anlaß ist der Bestand vom August 2026: Der Vorlader läuft jede Nacht über
 * dieselben Turniere, und ohne Dublettenprüfung standen 21 Fälle als 45 Zeilen
 * in der Datei. Wer sie an nu weiterreicht, müßte erst aufräumen.
 */
class AuffaelligTest extends TestCase
{
	/**
	 * Arbeitsverzeichnis dieses Prüflaufs.
	 *
	 * @var string
	 */
	private $verzeichnis = '';

	/**
	 * Legt ein leeres Arbeitsverzeichnis an und setzt den statischen Zustand
	 * der Klasse zurück.
	 *
	 * Der Zustand ist statisch, lebt also über die einzelne Prüfung hinaus —
	 * ohne dieses Zurücksetzen sähe die zweite Prüfung noch die Befunde der
	 * ersten.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		$this->verzeichnis = sys_get_temp_dir().'/wp-auffaellig-'.uniqid();
		mkdir($this->verzeichnis.'/var/logs', 0777, true);

		AuffaelligPruefling::verzeichnisSetzen($this->verzeichnis);
		AuffaelligPruefling::zuruecksetzen();
	}

	/**
	 * Räumt das Arbeitsverzeichnis wieder ab.
	 *
	 * @return void
	 */
	protected function tearDown(): void
	{
		foreach ((array) glob($this->verzeichnis.'/var/logs/*') as $datei)
		{
			@unlink($datei);
		}

		@rmdir($this->verzeichnis.'/var/logs');
		@rmdir($this->verzeichnis.'/var');
		@rmdir($this->verzeichnis);
	}

	/**
	 * Ein Spieler-Datensatz, wie ihn die Schnittstelle liefert.
	 *
	 * Die Werte stammen aus dem echten Protokoll: Mikael Avetisyan, fünf
	 * Partien gegen einen Schnitt von 505, kein Punkt, Turnierleistung −172.
	 *
	 * @param array $abweichend Felder, die abweichen sollen
	 *
	 * @return array Datensatz
	 */
	private function satz(array $abweichend = array()): array
	{
		return array_merge(array
		(
			'nuLigaPersonId'           => 'NU4542072',
			'lastname'                 => 'Avetisyan',
			'firstname'                => 'Mikael',
			'vkz'                      => '57007',
			'tournamentPerformance'    => -172,
			'numberOfGames'            => 5,
			'averageRatingCompetitors' => 505,
			'wins'                     => 0,
			'ratingOld'                => 731,
			'ratingNew'                => 695,
		), $abweichend);
	}

	/**
	 * Liest die Datenzeilen der Protokolldatei.
	 *
	 * @return array Zeilen ohne die Kopfzeile
	 */
	private function zeilen(): array
	{
		$datei = AuffaelligPruefling::datei();

		if (!is_file($datei))
		{
			return array();
		}

		$zeilen = array();
		$fp = fopen($datei, 'r');

		while (($z = fgetcsv($fp, 0, ';')) !== false)
		{
			if (count($z) > 1 && $z[0] !== 'Zeitpunkt') $zeilen[] = $z;
		}

		fclose($fp);

		return $zeilen;
	}

	/**
	 * Ein negativer Wert wird mit Spieler, Turnier und Rechenweg festgehalten.
	 */
	public function testNegativerWertWirdFestgehalten(): void
	{
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierauswertung');

		$zeilen = $this->zeilen();

		$this->assertCount(1, $zeilen);
		$this->assertSame('Turnierauswertung', $zeilen[0][1]);
		$this->assertSame('turnier-1', $zeilen[0][2]);
		$this->assertSame('NU4542072', $zeilen[0][3]);
		$this->assertSame('Avetisyan, Mikael', $zeilen[0][4]);
		$this->assertSame('57007', $zeilen[0][5]);
		$this->assertSame('Turnierleistung (tournamentPerformance)', $zeilen[0][6]);
		$this->assertSame('-172', $zeilen[0][7]);
		$this->assertSame('505', $zeilen[0][9], 'der Gegnerschnitt gehört dazu');
	}

	/**
	 * Derselbe Befund im selben Abruf ergibt keine zweite Zeile.
	 *
	 * Ein Spieler kommt in einem Abgleich mehrfach vorbei — über die
	 * Auswertung, die Turnierhistorie und die Partien.
	 */
	public function testDerselbeBefundImSelbenAbruf(): void
	{
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierauswertung');
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierhistorie');

		$this->assertCount(1, $this->zeilen());
		$this->assertSame(1, AuffaelligPruefling::anzahl());
	}

	/**
	 * Derselbe Befund im NÄCHSTEN Abruf ergibt ebenfalls keine zweite Zeile.
	 *
	 * Das ist der eigentliche Fall: Der Vorlader läuft jede Nacht neu, und
	 * jeder Lauf ist ein eigener Prozeß mit frischem statischem Zustand. Die
	 * Monatsdatei selbst muß den Merkzettel abgeben.
	 */
	public function testDerselbeBefundImNaechstenAbruf(): void
	{
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierauswertung');

		// Neuer Prozeß: alles, was im Speicher stand, ist weg
		AuffaelligPruefling::zuruecksetzen();

		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierauswertung');

		$this->assertCount(1, $this->zeilen(), 'die Datei darf nicht wachsen');
		$this->assertSame(0, AuffaelligPruefling::anzahl(), 'und nichts Neues zu melden haben');
	}

	/**
	 * Ändert sich der Wert, ist es ein neuer Befund.
	 *
	 * Korrigiert nu die Auswertung nur zur Hälfte, soll das nicht untergehen.
	 */
	public function testGeaenderterWertIstEinNeuerBefund(): void
	{
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierauswertung');
		AuffaelligPruefling::zuruecksetzen();
		AuffaelligPruefling::pruefeSpieler(array($this->satz(array('tournamentPerformance' => -170))), 'turnier-1', 'Turnierauswertung');

		$this->assertCount(2, $this->zeilen());
	}

	/**
	 * Derselbe Spieler in einem anderen Turnier ist ein eigener Befund —
	 * so wie Rika Scholz, die im August mit zwei Turnieren im Protokoll steht.
	 */
	public function testSelberSpielerAnderesTurnier(): void
	{
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-1', 'Turnierauswertung');
		AuffaelligPruefling::pruefeSpieler(array($this->satz()), 'turnier-2', 'Turnierauswertung');

		$this->assertCount(2, $this->zeilen());
	}

	/**
	 * Ein Spieler ohne nuLiga-Kennung wird über seine Spieler-UUID geführt.
	 *
	 * Solche Sätze liefert nu tatsächlich — im August 2026 waren zehn der 45
	 * Zeilen ohne Kennung.
	 */
	public function testSpielerOhneNuLigaKennung(): void
	{
		$satz = $this->satz(array('nuLigaPersonId' => null, 'playerUuid' => '019f75bb-20f8', 'vkz' => ''));
		unset($satz['nuLigaPersonId']);

		AuffaelligPruefling::pruefeSpieler(array($satz), 'turnier-1', 'Turnierauswertung');

		$zeilen = $this->zeilen();

		$this->assertCount(1, $zeilen);
		$this->assertSame('019f75bb-20f8', $zeilen[0][3]);
	}

	/**
	 * Werte ab null lösen nichts aus, und ein fehlendes Feld ebenfalls nicht.
	 */
	public function testGueltigeWerteBleibenStill(): void
	{
		AuffaelligPruefling::pruefeSpieler(array($this->satz(array('tournamentPerformance' => 0))), 'turnier-1');
		AuffaelligPruefling::pruefeSpieler(array($this->satz(array('tournamentPerformance' => 1234))), 'turnier-2');

		$satz = $this->satz();
		unset($satz['tournamentPerformance']);
		AuffaelligPruefling::pruefeSpieler(array($satz), 'turnier-3');

		$this->assertCount(0, $this->zeilen());
		$this->assertSame(0, AuffaelligPruefling::anzahl());
	}

	/**
	 * Auch die übrigen überwachten Felder werden geprüft, jedes für sich.
	 */
	public function testWeitereFelder(): void
	{
		AuffaelligPruefling::pruefeSpieler(
			array($this->satz(array('ratingNew' => -5, 'numberOfGames' => -1))),
			'turnier-1'
		);

		$zeilen = $this->zeilen();
		$felder = array_column($zeilen, 6);

		$this->assertCount(3, $zeilen, 'Turnierleistung, DWZ neu und Partien');
		$this->assertContains('DWZ neu (ratingNew)', $felder);
		$this->assertContains('Partien (numberOfGames)', $felder);
	}

	/**
	 * Die Prüfung darf einen Abgleich unter keinen Umständen aufhalten —
	 * auch nicht bei Unsinn als Eingabe.
	 */
	public function testUnsinnStuerztNichtAb(): void
	{
		AuffaelligPruefling::pruefeSpieler('kein Array');
		AuffaelligPruefling::pruefeSpieler(array('auch kein Array'));
		AuffaelligPruefling::pruefeSpieler(array(array('tournamentPerformance' => 'null Ahnung')));

		$this->assertCount(0, $this->zeilen());
	}
}

/**
 * Lenkt die Protokolldatei in ein Arbeitsverzeichnis um und macht den
 * statischen Zustand zurücksetzbar.
 *
 * Ohne das Umlenken schriebe die Prüfung in `var/logs` einer echten
 * Installation — dafür ist `datei()` bewußt über `static::` aufgerufen.
 */
class AuffaelligPruefling extends Auffaellig
{
	/**
	 * Wurzelverzeichnis, unter dem die Datei entsteht.
	 *
	 * @var string
	 */
	protected static $verzeichnis = '';

	/**
	 * @param string $verzeichnis Wurzelverzeichnis des Prüflaufs
	 *
	 * @return void
	 */
	public static function verzeichnisSetzen($verzeichnis): void
	{
		static::$verzeichnis = $verzeichnis;
	}

	/**
	 * Vergißt alles, was im Speicher steht — wie ein neuer Prozeß.
	 *
	 * @return void
	 */
	public static function zuruecksetzen(): void
	{
		static::$bekannt = null;
		static::$anzahl = 0;
	}

	/**
	 * @return string Pfad der Protokolldatei im Arbeitsverzeichnis
	 */
	public static function datei()
	{
		return static::$verzeichnis.'/var/logs/wertungsportal-auffaellig-'.date('Y-m').'.log';
	}
}
