<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Contao5;

use PHPUnit\Framework\TestCase;

/**
 * Wacht darüber, dass kein Aufruf von `Statement::execute()` seine Werte als
 * einzelnes Array übergibt.
 *
 * Contao 4.13 packt ein solches Array noch selbst aus, mit einer
 * Abkündigungsmeldung. Contao 5 tut das nicht mehr: `Statement::query()`
 * serialisiert jedes Array zu EINEM Parameter. `IN (?)` mit einem Wert trifft
 * dann nichts, mehrere Platzhalter scheitern mit „Invalid parameter number".
 * PHPStan sieht den Unterschied nicht, weil `execute()` beide Formen annimmt,
 * und die übrigen Unit-Tests laufen ohne Datenbank. Bis 1.43.0 steckte der
 * Fehler deshalb unbemerkt an 42 Stellen. Richtig ist `->execute(...$werte)`,
 * bei Stapel-INSERTs `->execute(...array_merge(...$zeilen))`.
 *
 * Die Prüfung liest den Quelltext mit dem PHP-Tokenizer. Ein Array erkennt sie
 * an einem Literal, einer Array-Funktion oder einem `(array)`-Cast als
 * Argument; bei einer Variablen an einer Spur in derselben Funktion:
 * `$x = array(…)`, `$x = […]`, `$x = (array) …`, `$x = array_…(…)`,
 * `$x[] = …`, `foreach (array_chunk(…) as $x)` oder ein Parameter `array $x`.
 * Ein Array aus einem Methodenaufruf (`$x = self::liste()`) sieht sie nicht —
 * dafür bleiben die Prüfstände in den Contao-Installationen.
 */
class ExecuteArgumenteTest extends TestCase
{
	/**
	 * Funktionen, die immer ein Array zurückgeben.
	 */
	private const ARRAY_FUNKTIONEN = array
	(
		'array_chunk', 'array_column', 'array_combine', 'array_diff', 'array_fill',
		'array_filter', 'array_intersect', 'array_keys', 'array_map', 'array_merge',
		'array_reverse', 'array_slice', 'array_unique', 'array_values', 'compact',
		'explode', 'func_get_args', 'iterator_to_array', 'preg_split', 'range',
		'str_split',
	);

	/**
	 * Kein execute()-Aufruf im Bundle bekommt ein einzelnes Array.
	 *
	 * Durchsucht werden alle PHP-Dateien und Vorlagen unter src/. Die Prüfung
	 * muss dabei eine nennenswerte Zahl von Aufrufen gesehen haben — ein
	 * Tokenizer, der nichts findet, meldete sonst ebenfalls „keine Befunde".
	 */
	public function testKeinExecuteMitEinemArray(): void
	{
		$wurzel = \dirname(__DIR__, 2);
		$aufrufe = 0;
		$befunde = array();

		foreach (self::quelldateien($wurzel.'/src') as $datei) {
			$ergebnis = self::untersuche((string) file_get_contents($datei));
			$aufrufe += $ergebnis['aufrufe'];

			foreach ($ergebnis['befunde'] as $befund) {
				$befunde[] = str_replace('\\', '/', substr($datei, \strlen($wurzel) + 1)).':'.$befund['zeile'].' ('.$befund['grund'].')';
			}
		}

		$this->assertGreaterThan(100, $aufrufe, 'Die Prüfung hat kaum execute()-Aufrufe gefunden und ist damit selbst defekt.');
		$this->assertSame(array(), $befunde, 'execute() bekommt hier ein einzelnes Array. Contao 5 bindet es als EINEN serialisierten Parameter; richtig ist ->execute(...$werte).');
	}

	/**
	 * Formen, die die Prüfung erkennen MUSS.
	 *
	 * @return array<string, array{0: string}> Beschreibung => Quelltext ohne `<?php`
	 */
	public function erkannteFormen(): array
	{
		return array
		(
			'Block aus array_chunk' => array('foreach (array_chunk($ids, 500) as $chunk) { $db->prepare($sql)->execute($chunk); }'),
			'Variable aus array()'  => array('$ids = array(); $ids[] = 1; $db->prepare($sql)->execute($ids);'),
			'Variable aus []'       => array('$werte = [$von, $bis]; $db->prepare($sql)->execute($werte);'),
			'nur angehängt'         => array('$werte[] = $von; $db->prepare($sql)->execute($werte);'),
			'Cast'                  => array('$ids = (array) $eingabe; $db->prepare($sql)->execute($ids);'),
			'Variable aus Funktion' => array('$ids = array_keys($liste); $db->prepare($sql)->execute($ids);'),
			'Parameter array'       => array('function f(array $ids) { return $db->prepare($sql)->execute($ids); }'),
			'Stapel-INSERT'         => array('$db->prepare($sql)->execute(array_merge(...$zeilen));'),
			'array_values'          => array('$db->prepare($sql)->execute(array_values($ids));'),
			'voll qualifiziert'     => array('$db->prepare($sql)->execute(\array_values($ids));'),
			'Literal kurz'          => array('$db->prepare($sql)->execute([$von, $bis]);'),
			'Literal lang'          => array('$db->prepare($sql)->execute(array($von, $bis));'),
		);
	}

	/**
	 * Die Prüfung erkennt jede bekannte Form des Fehlers, und zwar genau
	 * einmal.
	 *
	 * @dataProvider erkannteFormen
	 *
	 * @param string $code Quelltext ohne `<?php`
	 */
	public function testErkenntEinArrayAlsEinzigesArgument(string $code): void
	{
		$this->assertCount(1, self::untersuche('<?php '.$code)['befunde']);
	}

	/**
	 * Formen, die die Prüfung NICHT beanstanden darf.
	 *
	 * @return array<string, array{0: string}> Beschreibung => Quelltext ohne `<?php`
	 */
	public function unbedenklicheFormen(): array
	{
		return array
		(
			'ausgepackt'                        => array('foreach (array_chunk($ids, 500) as $chunk) { $db->prepare($sql)->execute(...$chunk); }'),
			'Stapel-INSERT ausgepackt'          => array('$db->prepare($sql)->execute(...array_merge(...$zeilen));'),
			'Wert vor ausgepackten Werten'      => array('$db->prepare($sql)->execute($uuid, ...$chunk);'),
			'Skalar'                            => array('function f(int $id) { return $db->prepare($sql)->execute($id); }'),
			'Schleifenwert'                     => array('foreach ($uuids as $uuid) { $db->prepare($sql)->execute($uuid); }'),
			'mehrere Werte'                     => array('$db->prepare($sql)->execute($von, $bis);'),
			'ohne Werte'                        => array('$db->prepare($sql)->execute();'),
			'Arrayelement'                      => array('$db->prepare($sql)->execute($zeile[\'id\']);'),
			'Element eines Funktionsergebnisses' => array('$db->prepare($sql)->execute(array_values($ids)[0]);'),
			'Cast auf int'                      => array('$db->prepare($sql)->execute((int) $eingabe);'),
			'Funktion mit Skalar'               => array('$db->prepare($sql)->execute(date(\'Ymd\'));'),
			'gleicher Name in anderer Funktion' => array('function a() { $chunk = array(); } function b($chunk) { return $db->prepare($sql)->execute($chunk); }'),
			'Methode eines Befehls'             => array('class C { protected function execute($input, $output): int { return 0; } }'),
			'Doctrine'                          => array('$connection->executeQuery($sql, array($von));'),
		);
	}

	/**
	 * Die Prüfung lässt alles durch, was unter Contao 5 richtig läuft.
	 *
	 * @dataProvider unbedenklicheFormen
	 *
	 * @param string $code Quelltext ohne `<?php`
	 */
	public function testLaesstRichtigeAufrufeDurch(string $code): void
	{
		$this->assertSame(array(), self::untersuche('<?php '.$code)['befunde']);
	}

	/**
	 * Sammelt alle PHP-Dateien und Vorlagen unterhalb eines Verzeichnisses.
	 *
	 * @param string $verzeichnis Wurzel der Suche
	 *
	 * @return list<string> Pfade, sortiert, damit die Befunde stabil geordnet sind
	 */
	private static function quelldateien(string $verzeichnis): array
	{
		$dateien = array();
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($verzeichnis, \FilesystemIterator::SKIP_DOTS));

		foreach ($iterator as $datei) {
			if ($datei->isFile() && \in_array(strtolower($datei->getExtension()), array('php', 'html5'), true)) {
				$dateien[] = $datei->getPathname();
			}
		}

		sort($dateien);

		return $dateien;
	}

	/**
	 * Sucht in einem Quelltext alle Aufrufe `->execute(…)` und beanstandet
	 * die, deren einziges Argument ein Array ist.
	 *
	 * @param string $code Vollständiger Quelltext einer Datei, mit `<?php`
	 *
	 * @return array{aufrufe: int, befunde: list<array{zeile: int, grund: string}>}
	 *               Zahl aller gefundenen Aufrufe und die Beanstandungen
	 */
	private static function untersuche(string $code): array
	{
		$t = self::token($code);
		$funktionen = self::funktionen($t);
		$pfeile = \defined('T_NULLSAFE_OBJECT_OPERATOR') ? array(T_OBJECT_OPERATOR, \constant('T_NULLSAFE_OBJECT_OPERATOR')) : array(T_OBJECT_OPERATOR);
		$aufrufe = 0;
		$befunde = array();

		for ($i = 0, $n = \count($t); $i + 2 < $n; ++$i) {
			// Ein Aufruf ist `->execute(`; die Deklaration `function execute(` nicht
			if (!\in_array($t[$i]['id'], $pfeile, true) || $t[$i + 1]['id'] !== T_STRING || strtolower($t[$i + 1]['text']) !== 'execute' || $t[$i + 2]['text'] !== '(') {
				continue;
			}

			++$aufrufe;
			$argumente = self::argumente($t, $i + 2);

			if (\count($argumente) !== 1) {
				continue;
			}

			$grund = self::arrayGrund($t, $argumente[0], $funktionen);

			if ($grund !== null) {
				$befunde[] = array('zeile' => $t[$i + 1]['zeile'], 'grund' => $grund);
			}
		}

		return array('aufrufe' => $aufrufe, 'befunde' => $befunde);
	}

	/**
	 * Zerlegt Quelltext in Token ohne Leerraum, Kommentare und HTML.
	 *
	 * Einzelzeichen wie `(` liefert der Tokenizer ohne Zeilennummer; sie
	 * bekommen die Zeile des vorigen Tokens.
	 *
	 * @param string $code Quelltext mit `<?php`
	 *
	 * @return list<array{id: int|null, text: string, zeile: int}> id ist null
	 *         bei Einzelzeichen
	 */
	private static function token(string $code): array
	{
		$ergebnis = array();
		$zeile = 1;

		foreach (token_get_all($code) as $token) {
			if (!\is_array($token)) {
				$ergebnis[] = array('id' => null, 'text' => $token, 'zeile' => $zeile);
				continue;
			}

			$zeile = $token[2];

			if (!\in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML), true)) {
				$ergebnis[] = array('id' => $token[0], 'text' => $token[1], 'zeile' => $zeile);
			}
		}

		return $ergebnis;
	}

	/**
	 * Sagt, ob ein Token eine Klammer öffnet, schließt oder keines von beiden
	 * ist.
	 *
	 * `{$…}` und `${…}` in Zeichenketten öffnen ebenfalls und enden mit `}`.
	 *
	 * @param array{id: int|null, text: string} $token Token aus token()
	 *
	 * @return int 1 für öffnend, -1 für schließend, sonst 0
	 */
	private static function klammerwert(array $token): int
	{
		if (\in_array($token['text'], array('(', '[', '{'), true) || \in_array($token['id'], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) {
			return 1;
		}

		return \in_array($token['text'], array(')', ']', '}'), true) ? -1 : 0;
	}

	/**
	 * Sucht zur öffnenden Klammer an $auf die schließende.
	 *
	 * @param list<array> $t   Token aus token()
	 * @param int         $auf Index einer öffnenden Klammer
	 *
	 * @return int|null Index der schließenden Klammer, null ohne Gegenstück
	 */
	private static function gegenstueck(array $t, int $auf): ?int
	{
		$tiefe = 0;

		for ($i = $auf, $n = \count($t); $i < $n; ++$i) {
			$tiefe += self::klammerwert($t[$i]);

			if ($tiefe === 0) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Teilt die Argumente eines Aufrufs an den Kommas der obersten Ebene.
	 *
	 * @param list<array> $t   Token aus token()
	 * @param int         $auf Index der öffnenden Klammer des Aufrufs
	 *
	 * @return list<list<int>> Je Argument die Token-Indizes; leer bei `execute()`
	 */
	private static function argumente(array $t, int $auf): array
	{
		$argumente = array();
		$aktuell = array();
		$tiefe = 0;

		for ($i = $auf + 1, $n = \count($t); $i < $n; ++$i) {
			if ($tiefe === 0 && ($t[$i]['text'] === ')' || $t[$i]['text'] === ',')) {
				if ($aktuell !== array()) {
					$argumente[] = $aktuell;
				}

				if ($t[$i]['text'] === ')') {
					break;
				}

				$aktuell = array();
				continue;
			}

			$tiefe += self::klammerwert($t[$i]);
			$aktuell[] = $i;
		}

		return $argumente;
	}

	/**
	 * Ermittelt Signatur und Rumpf aller Funktionen, Methoden und Closures.
	 *
	 * @param list<array> $t Token aus token()
	 *
	 * @return list<array{signatur: int, rumpf: int, ende: int}> Token-Indizes
	 *         von `function`, öffnender und schließender Rumpfklammer;
	 *         abstrakte Methoden ohne Rumpf fehlen
	 */
	private static function funktionen(array $t): array
	{
		$bereiche = array();
		$n = \count($t);

		for ($i = 0; $i < $n; ++$i) {
			if ($t[$i]['id'] !== T_FUNCTION) {
				continue;
			}

			$k = $i + 1;

			while ($k < $n && $t[$k]['text'] !== '(') {
				++$k;
			}

			// Hinter der Parameterliste stehen allenfalls die use-Liste einer
			// Closure und der Rückgabetyp; ein Semikolon beendet eine
			// abstrakte Methode
			$k = $k < $n ? (self::gegenstueck($t, $k) ?? $n) : $n;

			while ($k < $n && $t[$k]['text'] !== '{' && $t[$k]['text'] !== ';') {
				$k = $t[$k]['text'] === '(' ? (self::gegenstueck($t, $k) ?? $n) : $k;
				++$k;
			}

			if ($k >= $n || $t[$k]['text'] !== '{') {
				continue;
			}

			$ende = self::gegenstueck($t, $k);

			if ($ende !== null) {
				$bereiche[] = array('signatur' => $i, 'rumpf' => $k, 'ende' => $ende);
			}
		}

		return $bereiche;
	}

	/**
	 * Prüft, ob an $pos der Aufruf einer Funktion aus ARRAY_FUNKTIONEN beginnt.
	 *
	 * @param list<array> $t   Token aus token()
	 * @param int         $pos Index des ersten Tokens
	 *
	 * @return array{0: string, 1: int}|null Funktionsname und Index der
	 *                                        öffnenden Klammer, sonst null
	 */
	private static function arrayFunktion(array $t, int $pos): ?array
	{
		// PHP 7 zerlegt `\array_merge` in Namensraumtrenner und Namen, PHP 8
		// liefert ein einziges Token
		if (($t[$pos]['id'] ?? null) === T_NS_SEPARATOR) {
			++$pos;
		}

		$namen = \defined('T_NAME_FULLY_QUALIFIED') ? array(T_STRING, \constant('T_NAME_FULLY_QUALIFIED')) : array(T_STRING);

		if (!isset($t[$pos], $t[$pos + 1]) || !\in_array($t[$pos]['id'], $namen, true) || $t[$pos + 1]['text'] !== '(') {
			return null;
		}

		$name = strtolower(ltrim($t[$pos]['text'], '\\'));

		return \in_array($name, self::ARRAY_FUNKTIONEN, true) ? array($name, $pos + 1) : null;
	}

	/**
	 * Entscheidet, ob das einzige Argument eines execute()-Aufrufs ein Array
	 * ist.
	 *
	 * @param list<array> $t          Token aus token()
	 * @param list<int>   $argument   Token-Indizes des Arguments
	 * @param list<array> $funktionen Bereiche aus funktionen()
	 *
	 * @return string|null Woran das Array zu erkennen ist; null, wenn nichts
	 *                     darauf hindeutet
	 */
	private static function arrayGrund(array $t, array $argument, array $funktionen): ?string
	{
		$erstes = $argument[0];
		$letztes = $argument[\count($argument) - 1];

		if ($t[$erstes]['id'] === T_ELLIPSIS) {
			return null;
		}

		// Ein Literal nur, wenn die Klammer das ganze Argument umschließt —
		// `[$a, $b][0]` ergäbe einen einzelnen Wert
		if ($t[$erstes]['text'] === '[' && self::gegenstueck($t, $erstes) === $letztes) {
			return 'Array-Literal';
		}

		if ($t[$erstes]['id'] === T_ARRAY && isset($argument[1]) && self::gegenstueck($t, $argument[1]) === $letztes) {
			return 'Array-Literal';
		}

		if ($t[$erstes]['id'] === T_ARRAY_CAST) {
			return '(array)-Cast';
		}

		$funktion = self::arrayFunktion($t, $erstes);

		if ($funktion !== null && self::gegenstueck($t, $funktion[1]) === $letztes) {
			return $funktion[0].'() liefert ein Array';
		}

		if (\count($argument) === 1 && $t[$erstes]['id'] === T_VARIABLE) {
			return self::variablenGrund($t, $erstes, $funktionen);
		}

		return null;
	}

	/**
	 * Sucht in der umgebenden Funktion nach einer Spur, dass eine Variable
	 * ein Array ist.
	 *
	 * Gewertet werden `$x = array(…);`, `$x = […];`, `$x = (array) …`,
	 * `$x = array_…(…);`, `$x[] = …`, `foreach (array_chunk(…) as $x)` und ein
	 * Parameter `array $x`. Gesucht wird in der innersten Funktion, die die
	 * Stelle enthält; außerhalb jeder Funktion in der ganzen Datei.
	 *
	 * @param list<array> $t          Token aus token()
	 * @param int         $pos        Index der Variablen im Aufruf
	 * @param list<array> $funktionen Bereiche aus funktionen()
	 *
	 * @return string|null Die gefundene Spur, null ohne Spur
	 */
	private static function variablenGrund(array $t, int $pos, array $funktionen): ?string
	{
		$name = $t[$pos]['text'];
		$bereich = array('signatur' => null, 'rumpf' => 0, 'ende' => \count($t) - 1);

		foreach ($funktionen as $funktion) {
			if ($funktion['rumpf'] < $pos && $pos < $funktion['ende'] && $funktion['rumpf'] >= $bereich['rumpf']) {
				$bereich = $funktion;
			}
		}

		if ($bereich['signatur'] !== null) {
			for ($i = $bereich['signatur']; $i < $bereich['rumpf']; ++$i) {
				if ($t[$i]['text'] === $name && ($t[$i - 1]['id'] ?? null) === T_ARRAY) {
					return 'Parameter array '.$name;
				}
			}
		}

		for ($i = $bereich['rumpf']; $i <= $bereich['ende']; ++$i) {
			if ($t[$i]['id'] !== T_VARIABLE || $t[$i]['text'] !== $name) {
				continue;
			}

			$folgt = $t[$i + 1]['text'] ?? '';

			if ($folgt === '[' && ($t[$i + 2]['text'] ?? '') === ']' && ($t[$i + 3]['text'] ?? '') === '=') {
				return $name.'[] = …';
			}

			if ($folgt === '=' && isset($t[$i + 2])) {
				if ($t[$i + 2]['id'] === T_ARRAY_CAST) {
					return $name.' = (array) …';
				}

				// Literal oder Array-Funktion zählen nur, wenn die Zuweisung
				// damit endet — `$x = array_keys($a)[0];` wäre ein einzelner Wert
				$klammer = $t[$i + 2]['text'] === '[' ? $i + 2 : ($t[$i + 2]['id'] === T_ARRAY ? $i + 3 : null);
				$funktion = self::arrayFunktion($t, $i + 2);
				$klammer = $funktion !== null ? $funktion[1] : $klammer;
				$ende = $klammer !== null && isset($t[$klammer]) ? self::gegenstueck($t, $klammer) : null;

				if ($ende !== null && ($t[$ende + 1]['text'] ?? '') === ';') {
					return $name.' = '.($funktion !== null ? $funktion[0].'(…)' : 'Array-Literal');
				}
			}

			if (($t[$i - 1]['id'] ?? null) === T_AS) {
				$kopf = self::foreachKlammer($t, $i);
				$funktion = $kopf !== null ? self::arrayFunktion($t, $kopf + 1) : null;

				if ($funktion !== null && $funktion[0] === 'array_chunk') {
					return 'foreach (array_chunk(…) as '.$name.')';
				}
			}
		}

		return null;
	}

	/**
	 * Sucht rückwärts die öffnende Klammer eines foreach-Kopfs, in dem $pos
	 * steht.
	 *
	 * @param list<array> $t   Token aus token()
	 * @param int         $pos Index einer Stelle im Kopf
	 *
	 * @return int|null Index der Klammer; null, wenn $pos in keinem
	 *                  foreach-Kopf steht
	 */
	private static function foreachKlammer(array $t, int $pos): ?int
	{
		$tiefe = 0;

		for ($i = $pos - 1; $i >= 0; --$i) {
			$wert = self::klammerwert($t[$i]);

			if ($wert === 1 && $tiefe === 0) {
				return $t[$i]['text'] === '(' && ($t[$i - 1]['id'] ?? null) === T_FOREACH ? $i : null;
			}

			if ($t[$i]['text'] === ';') {
				return null;
			}

			$tiefe -= $wert;
		}

		return null;
	}
}
