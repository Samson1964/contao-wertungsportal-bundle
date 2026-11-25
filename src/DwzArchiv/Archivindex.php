<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\DwzArchiv;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Verzeichnis der datierten DWZ-Dateien in den Archivordnern (ab 1.61.0).
 *
 * Gelesen wird direkt das Dateisystem, nicht die Dateiverwaltung (DBAFS) und
 * nicht das VFS von Contao 5 — so bleibt der Code in 4.13 und 5 gleich, und
 * keine Abfrage stößt eine Synchronisierung an. Erwartet wird der Aufbau, den
 * Converter, Swiss-Chess-Befehl und früher DeWIS angelegt haben:
 *
 *     <basis>/<JJJJ>/<ordner>/<datei>.zip
 *
 * `<ordner>` ist `lv<code>` für einen Landesverband (CSV, DOS und bei DeWIS
 * SQL im selben Ordner), jeder andere Ordner (csv, dos, sql, swiss, swiss8,
 * swiss10, swiss1003) gehört zum DSB (Verband `0`). Ordner außerhalb der
 * Jahresordner (`export/`, `raw/`, `tmp/` des Converters) werden nicht
 * gelesen.
 *
 * Der Index wird je Verband aufgebaut und im Pool `cache.app` abgelegt. Im
 * Schlüssel steckt ein Fingerabdruck aus den Änderungszeiten der Ordner des
 * Verbands: Kommt eine Datei hinzu, ändert sich die Zeit des Ordners, und der
 * nächste Aufruf baut neu. Zusätzlich verfällt jeder Eintrag nach einer
 * Stunde. Ein Verband hat einige tausend Dateien; den ganzen Bestand
 * (über 100.000) in einem Eintrag zu halten, hieße ihn bei jedem Seitenaufruf
 * vollständig zu entpacken.
 *
 * Ein Eintrag des Index ist ein Array mit den Schlüsseln
 *     name     Dateiname
 *     pfad     Quelle/JJJJ/Ordner/Dateiname — so geht er in die Download-Adresse
 *     quelle   Schlüssel des Basisordners, `wertungsportal` oder `dewis`
 *     datum    Datum aus dem Dateinamen, JJJJMMTT
 *     variante v2, ws7, ws822, ws833, swiss, swiss10, swiss1003 oder leer
 *     groesse  Größe in Bytes
 */
final class Archivindex
{
	/**
	 * Muster der Dateinamen. Jeder Name trägt sein Datum als JJJJMMTT.
	 * `swiss1003` (Swiss-Chess ab 10.03) fehlte in der Vorlage der Aufgabe und
	 * ist hier ergänzt.
	 */
	public const MUSTER = '/^(?:LV-(?<lv>[0-9A-Z])-(?<fmt>csv|dos|sql)(?:_(?<var>v2))?|dsb-(?<sw>ws7|ws822|ws833|swiss|swiss10|swiss1003))_(?<datum>\d{8})\.zip$/';

	/**
	 * Formatgruppen in der Reihenfolge der Ausgabe (Franks Entscheidung vom
	 * 25.11.2026: vier Gruppen für Swiss-Chess).
	 */
	public const GRUPPEN = array('csv', 'dos', 'sql', 'sc7', 'sc8', 'sc10', 'sc1003');

	/**
	 * Zuordnung der Swiss-Chess-Varianten zu den Gruppen. `swiss` ist im
	 * Wertungsportal die Fassung für ältere Programme (bis 9), `ws822`/`ws833`
	 * sind die beiden DeWIS-Fassungen ab 8.
	 */
	private const SWISS_GRUPPE = array
	(
		'ws7'       => 'sc7',
		'ws822'     => 'sc8',
		'ws833'     => 'sc8',
		'swiss'     => 'sc8',
		'swiss10'   => 'sc10',
		'swiss1003' => 'sc1003',
	);

	/** Präfix der Cacheschlüssel; dahinter folgen Verband und Fingerabdruck */
	public const CACHE_PRAEFIX = 'wertungsportal_dwz_archiv_';

	/** Höchste Lebensdauer eines Cache-Eintrags in Sekunden */
	public const LEBENSDAUER = 3600;

	/** Höchstzahl der protokollierten Namen, die nicht ins Muster passen */
	private const HOECHSTZAHL_IGNORIERT = 50;

	/** @var string */
	private $projektpfad;

	/** @var array<string, string> */
	private $basisordner;

	/** @var CacheInterface|null */
	private $cache;

	/** @var LoggerInterface|null */
	private $logger;

	/** @var array<string, list<array{0: string, 1: string, 2: string}>>|null */
	private $ordnerMerker;

	/**
	 * Legt den Index an. Gelesen wird erst beim ersten Zugriff.
	 *
	 * @param string                $projektpfad Wurzel der Contao-Installation (`%kernel.project_dir%`)
	 * @param array<string, string> $basisordner Quelle => Ordner relativ zur Wurzel, in der Reihenfolge
	 *                                           des Vorrangs bei gleichem Dateinamen; Vorgabe in
	 *                                           services.yml (`wertungsportal.dwz_archiv.basisordner`)
	 * @param CacheInterface|null   $cache       Pool `cache.app`; ohne ihn wird bei jedem Zugriff neu gelesen
	 *                                           (so laufen die Tests)
	 * @param LoggerInterface|null  $logger      Nimmt beim Neuaufbau die Zahl der Dateien auf, die nicht ins
	 *                                           Muster passen
	 */
	public function __construct(string $projektpfad, array $basisordner, ?CacheInterface $cache = null, ?LoggerInterface $logger = null)
	{
		$this->projektpfad = rtrim(str_replace('\\', '/', $projektpfad), '/');
		$this->basisordner = $basisordner;
		$this->cache = $cache;
		$this->logger = $logger;
	}

	/**
	 * Zerlegt einen Dateinamen nach dem Muster.
	 *
	 * Swiss-Chess-Dateien gehören immer zum DSB (Verband `0`).
	 *
	 * @param string $name Reiner Dateiname ohne Pfad
	 *
	 * @return array{verband: string, gruppe: string, variante: string, datum: string}|null
	 *         null, wenn der Name nicht passt oder das Datum keinen gültigen Kalendertag nennt
	 */
	public static function zerlege(string $name): ?array
	{
		if (!preg_match(self::MUSTER, $name, $teile)) {
			return null;
		}

		$datum = $teile['datum'];

		// 20261131 passt ins Muster, ist aber kein Tag — die Abstandsrechnung
		// würde ihn still in den Dezember schieben
		if (!checkdate((int) substr($datum, 4, 2), (int) substr($datum, 6, 2), (int) substr($datum, 0, 4))) {
			return null;
		}

		if (!empty($teile['sw'])) {
			return array('verband' => '0', 'gruppe' => self::SWISS_GRUPPE[$teile['sw']], 'variante' => $teile['sw'], 'datum' => $datum);
		}

		// `var` steht immer im Treffer (leer ohne _v2), weil `datum` dahinter mitpaßt
		return array('verband' => $teile['lv'], 'gruppe' => $teile['fmt'], 'variante' => $teile['var'], 'datum' => $datum);
	}

	/**
	 * Liefert die Codes der Verbände, für die es Ordner gibt.
	 *
	 * @return list<string> Codes als Zeichenketten, sortiert (0–9, dann Buchstaben)
	 */
	public function verbaende(): array
	{
		$codes = array_map('strval', array_keys($this->ordner()));
		sort($codes, SORT_STRING);

		return $codes;
	}

	/**
	 * Liefert die Dateien eines Verbands, nach Formatgruppe getrennt und je
	 * Gruppe nach Datum aufsteigend sortiert.
	 *
	 * Liegt ein passender Cache-Eintrag vor (gleicher Fingerabdruck, jünger als
	 * eine Stunde), wird nichts gelesen außer den Ordnerzeiten.
	 *
	 * @param string $verband Code des Verbands, `0` für den DSB
	 *
	 * @return array{gruppen: array<string, list<array>>, ignoriert: list<string>}
	 *         Leere Gruppen fehlen; `ignoriert` nennt höchstens 50 Pfade, die
	 *         nicht ins Muster passen oder im Ordner eines anderen Verbands liegen
	 */
	public function dateien(string $verband): array
	{
		$ordner = $this->ordner()[$verband] ?? array();

		if (null === $this->cache) {
			return $this->baue($verband, $ordner);
		}

		$fingerabdruck = array();
		foreach ($ordner as $eintrag) {
			$fingerabdruck[] = $eintrag[1].'@'.(int) @filemtime($eintrag[2]);
		}

		// Symfony verbietet in Schlüsseln u. a. / \ : @ — Code und md5 sind frei davon
		$schluessel = self::CACHE_PRAEFIX.$verband.'_'.md5(implode('|', $fingerabdruck));

		return $this->cache->get($schluessel, function ($eintrag) use ($verband, $ordner) {
			$eintrag->expiresAfter(self::LEBENSDAUER);

			return $this->baue($verband, $ordner);
		});
	}

	/**
	 * Sucht in einer nach Datum sortierten Liste die Dateien zum Datum.
	 *
	 * `$nachbarn` ist die Zahl der Tage je Seite. Gibt es Dateien genau zu
	 * diesem Tag, kommen sie mit so vielen Tagen davor und danach zurück. Sonst
	 * so viele Tage davor und danach, mindestens aber der nächste auf jeder
	 * Seite — immer beide, auch bei gleichem Abstand. Bei 1 und einer Lücke
	 * also genau ein Tag davor und einer danach (bis 1.61.0 kamen hier zwei,
	 * weil der nächste Tag nicht mitzählte). Vor dem ersten bzw. nach dem
	 * letzten Bestand fehlt die jeweils andere Seite.
	 * Mehrere Dateien am selben Tag (ws822 und ws833, `_v2`) bilden eine Zeile.
	 *
	 * @param list<array> $liste    Einträge einer Formatgruppe, nach `datum` aufsteigend
	 * @param string      $datum    Gesuchter Tag, JJJJMMTT
	 * @param int         $nachbarn Tage je Seite, 0 oder mehr
	 *
	 * @return list<array{datum: string, abstand: int, dateien: list<array>}>
	 *         Nach Datum aufsteigend; `abstand` in Tagen, negativ = früher, 0 = genau.
	 *         Leer, wenn die Liste leer ist.
	 */
	public static function suche(array $liste, string $datum, int $nachbarn = 0): array
	{
		// Tage bündeln; die Liste ist sortiert, gleiche Tage stehen zusammen
		$tage = array();
		foreach ($liste as $eintrag) {
			$letzter = \count($tage) - 1;
			if ($letzter >= 0 && $tage[$letzter]['datum'] === $eintrag['datum']) {
				$tage[$letzter]['dateien'][] = $eintrag;
			} else {
				$tage[] = array('datum' => $eintrag['datum'], 'dateien' => array($eintrag));
			}
		}

		// Erster Tag, der nicht vor dem gesuchten liegt (binäre Suche;
		// JJJJMMTT vergleicht sich als Zeichenkette richtig)
		$links = 0;
		$rechts = \count($tage);
		while ($links < $rechts) {
			$mitte = intdiv($links + $rechts, 2);
			if (strcmp($tage[$mitte]['datum'], $datum) < 0) {
				$links = $mitte + 1;
			} else {
				$rechts = $mitte;
			}
		}

		$nachbarn = max(0, $nachbarn);
		$genau = $links < \count($tage) && $tage[$links]['datum'] === $datum;

		// Ohne genauen Treffer zählt der nächste Tag davor und danach mit;
		// $links zeigt dann schon auf den ersten Tag danach
		$seite = $genau ? $nachbarn : max(1, $nachbarn);
		$von = $links - $seite;
		$bis = $genau ? $links + $seite : $links + $seite - 1;

		$gesucht = self::tag($datum);
		$ergebnis = array();
		for ($i = max(0, $von); $i <= min(\count($tage) - 1, $bis); ++$i) {
			$abstand = (int) $gesucht->diff(self::tag($tage[$i]['datum']))->format('%r%a');
			$ergebnis[] = array('datum' => $tage[$i]['datum'], 'abstand' => $abstand, 'dateien' => $tage[$i]['dateien']);
		}

		return $ergebnis;
	}

	/**
	 * Löst eine Download-Angabe sicher in einen Dateipfad auf.
	 *
	 * Erlaubt ist genau `<quelle>/<JJJJ>/<ordner>/<datei>` mit einer bekannten
	 * Quelle und einem Dateinamen nach dem Muster. Leere Glieder, `.`, `..`,
	 * Rückstriche und Nullbytes werden abgewiesen. Zuletzt muß der echte Pfad
	 * (realpath, Verweise aufgelöst) im Basisordner der Quelle liegen — ein
	 * Symlink nach außen fällt dabei heraus.
	 *
	 * @param string $angabe Pfad aus der Adresse, z. B. `wertungsportal/2026/csv/LV-0-csv_20261125.zip`
	 *
	 * @return string|null Absoluter Pfad einer vorhandenen Datei, sonst null
	 */
	public function aufloesen(string $angabe): ?string
	{
		if ('' === $angabe || false !== strpbrk($angabe, "\\\0")) {
			return null;
		}

		$glieder = explode('/', $angabe);
		if (4 !== \count($glieder)) {
			return null;
		}

		foreach ($glieder as $glied) {
			if ('' === $glied || '.' === $glied || '..' === $glied) {
				return null;
			}
		}

		list($quelle, $jahr, $ordner, $name) = $glieder;

		if (!isset($this->basisordner[$quelle]) || !preg_match('/^\d{4}$/', $jahr) || null === self::zerlege($name)) {
			return null;
		}

		$basis = realpath($this->projektpfad.'/'.$this->basisordner[$quelle]);
		$datei = realpath($this->projektpfad.'/'.$this->basisordner[$quelle].'/'.$jahr.'/'.$ordner.'/'.$name);

		if (false === $basis || false === $datei || !is_file($datei)) {
			return null;
		}

		$basis = rtrim(str_replace('\\', '/', $basis), '/').'/';

		if (0 !== strncmp(str_replace('\\', '/', $datei), $basis, \strlen($basis))) {
			return null;
		}

		return $datei;
	}

	/**
	 * Sammelt die Jahres-Unterordner aller Basisordner, nach Verband getrennt.
	 *
	 * Wird je Objekt einmal gelesen (zwei Basisordner, je gut zehn Jahre mit
	 * bis zu 25 Unterordnern — wenige Millisekunden).
	 *
	 * @return array<string, list<array{0: string, 1: string, 2: string}>>
	 *         Verband => Liste aus [Quelle, relativer Pfad Quelle/JJJJ/Ordner, absoluter Pfad]
	 */
	private function ordner(): array
	{
		if (null !== $this->ordnerMerker) {
			return $this->ordnerMerker;
		}

		$ergebnis = array();

		foreach ($this->basisordner as $quelle => $relativ) {
			$basis = $this->projektpfad.'/'.trim($relativ, '/');

			foreach (self::unterordner($basis) as $jahr) {
				// Nur Jahresordner; export/, raw/ und tmp/ des Converters nicht
				if (!preg_match('/^\d{4}$/', $jahr)) {
					continue;
				}

				foreach (self::unterordner($basis.'/'.$jahr) as $ordner) {
					$verband = preg_match('/^lv([0-9a-z])$/', $ordner, $treffer) ? strtoupper($treffer[1]) : '0';
					$ergebnis[$verband][] = array($quelle, $quelle.'/'.$jahr.'/'.$ordner, $basis.'/'.$jahr.'/'.$ordner);
				}
			}
		}

		return $this->ordnerMerker = $ergebnis;
	}

	/**
	 * Liest die Dateien der Ordner eines Verbands ein.
	 *
	 * Gleiche Dateinamen werden zusammengeführt; es gilt der erste Fund (die
	 * Quellen in der Reihenfolge von `$basisordner`, darin die Jahre
	 * aufsteigend).
	 *
	 * @param string $verband Code des Verbands
	 * @param list<array{0: string, 1: string, 2: string}> $ordner Ordner des Verbands aus ordner()
	 *
	 * @return array{gruppen: array<string, list<array>>, ignoriert: list<string>}
	 */
	private function baue(string $verband, array $ordner): array
	{
		$gruppen = array();
		$gesehen = array();
		$ignoriert = array();
		$anzahlIgnoriert = 0;

		foreach ($ordner as list($quelle, $relativ, $absolut)) {
			$namen = @scandir($absolut);
			if (false === $namen) {
				continue;
			}

			foreach ($namen as $name) {
				if ('.zip' !== strtolower(substr($name, -4)) || !is_file($absolut.'/'.$name)) {
					continue;
				}

				$teile = self::zerlege($name);

				// Auch ein richtiger Name im Ordner eines anderen Verbands
				// wird übergangen, sonst erschiene er doppelt
				if (null === $teile || $teile['verband'] !== $verband) {
					if (++$anzahlIgnoriert <= self::HOECHSTZAHL_IGNORIERT) {
						$ignoriert[] = $relativ.'/'.$name;
					}
					continue;
				}

				if (isset($gesehen[$name])) {
					continue;
				}
				$gesehen[$name] = true;

				$gruppen[$teile['gruppe']][] = array
				(
					'name'     => $name,
					'pfad'     => $relativ.'/'.$name,
					'quelle'   => $quelle,
					'datum'    => $teile['datum'],
					'variante' => $teile['variante'],
					'groesse'  => (int) @filesize($absolut.'/'.$name),
				);
			}
		}

		// Nach Datum, am selben Tag nach Name (ws822 vor ws833, ohne _v2 vor _v2)
		foreach ($gruppen as &$liste) {
			usort($liste, static function (array $a, array $b): int {
				return array($a['datum'], $a['name']) <=> array($b['datum'], $b['name']);
			});
		}
		unset($liste);

		// Feste Reihenfolge der Gruppen für die Ausgabe
		$sortiert = array();
		foreach (self::GRUPPEN as $gruppe) {
			if (isset($gruppen[$gruppe])) {
				$sortiert[$gruppe] = $gruppen[$gruppe];
			}
		}

		if ($anzahlIgnoriert > 0 && null !== $this->logger) {
			$this->logger->warning(sprintf('DWZ-Archiv: %d Datei(en) im Verband %s passen nicht ins Namensmuster, z. B. %s', $anzahlIgnoriert, $verband, $ignoriert[0]));
		}

		return array('gruppen' => $sortiert, 'ignoriert' => $ignoriert);
	}

	/**
	 * Liefert die Namen der Unterordner eines Ordners, sortiert.
	 *
	 * @param string $ordner Absoluter Pfad
	 *
	 * @return list<string> Leer, wenn der Ordner fehlt oder nicht lesbar ist
	 */
	private static function unterordner(string $ordner): array
	{
		$namen = @scandir($ordner);
		if (false === $namen) {
			return array();
		}

		return array_values(array_filter($namen, static function (string $name) use ($ordner): bool {
			return '.' !== $name[0] && is_dir($ordner.'/'.$name);
		}));
	}

	/**
	 * Macht aus JJJJMMTT einen Tag ohne Uhrzeit.
	 *
	 * @param string $datum JJJJMMTT, vorher über zerlege() geprüft
	 *
	 * @return \DateTimeImmutable Mitternacht in UTC (Sommerzeit darf den Abstand nicht verschieben)
	 */
	private static function tag(string $datum): \DateTimeImmutable
	{
		return \DateTimeImmutable::createFromFormat('!Ymd', $datum, new \DateTimeZone('UTC'));
	}
}
