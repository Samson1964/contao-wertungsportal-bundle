<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Contao5;

use PHPUnit\Framework\TestCase;

/**
 * Hält fest, dass jeder Konsolenbefehl seinen Namen am Tag in services.yml
 * trägt.
 *
 * Bis 1.43.0 setzten die Befehle ihren Namen nur über
 * `protected static $defaultName`. Symfony 7 (Contao 5) liest diese
 * Eigenschaft nicht mehr; `AddConsoleCommandPass` nimmt den Namen nur noch aus
 * `#[AsCommand]` oder dem Tag-Attribut `command`. Ohne beides meldete
 * `contao-console` bei jedem Aufruf fünfmal „cannot have an empty name", und
 * keiner der Befehle war aufrufbar — auch nicht die Cronjobs für Download,
 * Converter und Swiss-Chess. Das Tag-Attribut wirkt in Symfony 5.4 und 7.4
 * gleich. `$defaultName` bleibt für Contao 4.13 stehen; beide Angaben müssen
 * übereinstimmen.
 *
 * Die services.yml wird als Text gelesen: Die Tests laufen ohne
 * Composer-Autoloader, symfony/yaml steht deshalb nicht zur Verfügung.
 */
class KonsolenbefehleTest extends TestCase
{
	/**
	 * Jede Befehlsklasse unter src/Command ist mit ihrem Namen getaggt, und
	 * der Name im Tag stimmt mit `$defaultName` überein.
	 */
	public function testJederBefehlTraegtSeinenNamenAmTag(): void
	{
		$befehle = self::befehle();
		$tags = self::konsolenTags();

		$this->assertNotEmpty($befehle, 'Keine Befehlsklasse gefunden — die Prüfung ist selbst defekt.');

		foreach ($befehle as $klasse => $name) {
			$this->assertStringStartsWith('wertungsportal:', $name, $klasse.': $defaultName fehlt oder liegt außerhalb des eigenen Namensraums.');
			$this->assertArrayHasKey($klasse, $tags, $klasse.' ist in services.yml nicht als console.command getaggt.');
			$this->assertSame($name, $tags[$klasse], $klasse.': Das Attribut command am Tag muss $defaultName entsprechen.');
		}
	}

	/**
	 * Kein console.command-Tag kommt ohne Namen aus, und zu jedem Tag gibt es
	 * eine Befehlsklasse — und umgekehrt.
	 */
	public function testKeinTagOhneNamen(): void
	{
		$tags = self::konsolenTags();

		foreach ($tags as $klasse => $name) {
			$this->assertNotSame('', $name, $klasse.' ist ohne das Attribut command getaggt — unter Contao 5 hat der Befehl dann keinen Namen.');
		}

		$getaggt = array_keys($tags);
		$vorhanden = array_keys(self::befehle());
		sort($getaggt);
		sort($vorhanden);

		$this->assertSame($vorhanden, $getaggt);
	}

	/**
	 * Liest die console.command-Tags aus services.yml.
	 *
	 * Ein Dienst beginnt mit vier Leerzeichen Einzug und endet auf einen
	 * Doppelpunkt; seine Tags stehen darunter als `- { name: …, … }`. Die
	 * Werte der Tags enthalten keine Kommas, das Aufteilen daran genügt.
	 *
	 * @return array<string, string> Dienst (Klassenname) => Wert des Attributs
	 *                               command, leer ohne das Attribut
	 */
	private static function konsolenTags(): array
	{
		$zeilen = preg_split('/\R/', (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/config/services.yml'));
		$dienst = null;
		$tags = array();

		foreach ($zeilen ?: array() as $zeile) {
			if (preg_match('/^ {4}([^\s#][^:]*):\s*$/', $zeile, $treffer)) {
				$dienst = $treffer[1];
				continue;
			}

			if ($dienst === null || !preg_match('/^\s+-\s*\{(.*)\}\s*$/', $zeile, $treffer)) {
				continue;
			}

			$attribute = array();

			foreach (explode(',', $treffer[1]) as $paar) {
				if (preg_match('/^\s*(\w+)\s*:\s*(.*?)\s*$/', $paar, $teil)) {
					$attribute[$teil[1]] = trim($teil[2], '\'"');
				}
			}

			if (($attribute['name'] ?? '') === 'console.command') {
				$tags[$dienst] = $attribute['command'] ?? '';
			}
		}

		return $tags;
	}

	/**
	 * Liest Klassenname und `$defaultName` aller Befehle unter src/Command.
	 *
	 * @return array<string, string> voller Klassenname => $defaultName; leer,
	 *                               wenn die Klasse keinen setzt
	 */
	private static function befehle(): array
	{
		$befehle = array();

		foreach (glob(\dirname(__DIR__, 2).'/src/Command/*Command.php') ?: array() as $datei) {
			$code = (string) file_get_contents($datei);

			if (!preg_match('/^namespace\s+([^;]+);/m', $code, $namensraum) || !preg_match('/^class\s+(\w+)\s+extends\s+Command\b/m', $code, $klasse)) {
				continue;
			}

			$name = preg_match('/protected\s+static\s+\$defaultName\s*=\s*\'([^\']*)\'/', $code, $treffer) ? $treffer[1] : '';
			$befehle[$namensraum[1].'\\'.$klasse[1]] = $name;
		}

		return $befehle;
	}
}
