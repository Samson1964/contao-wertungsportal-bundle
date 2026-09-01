<?php

declare(strict_types=1);

/**
 * Startdatei der Prüfungen.
 *
 * Das Bundle hat bewußt kein eigenes `vendor/`-Verzeichnis (es wird immer als
 * Abhängigkeit einer Contao-Installation eingebunden), deshalb gibt es hier
 * auch keinen Composer-Autoloader zum Einbinden. Statt dessen wird ein
 * schlanker PSR-4-Lader für die beiden Namensräume des Bundles registriert.
 *
 * Die Prüfungen in `tests/` kommen damit ohne Contao aus: Sie prüfen die
 * Regeln, die ohne Datenbank und ohne Framework gelten. Alles, was gegen die
 * echten Tabellen laufen muß, wird über die Prüfstände in den Contao-
 * Testinstallationen abgedeckt (siehe docs/).
 *
 * Aufruf (PHPUnit liegt gemeinsam unter F:\Claude\tools\phpunit9):
 *
 *     php F:\Claude\tools\phpunit9\vendor\bin\phpunit
 */

spl_autoload_register(static function (string $klasse): void {
	$namensraeume = array
	(
		'Schachbulle\\ContaoWertungsportalBundle\\Tests\\' => __DIR__.'/',
		'Schachbulle\\ContaoWertungsportalBundle\\'        => __DIR__.'/../src/',
	);

	foreach ($namensraeume as $praefix => $verzeichnis) {
		if (strncmp($klasse, $praefix, strlen($praefix)) !== 0) {
			continue;
		}

		$datei = $verzeichnis.str_replace('\\', '/', substr($klasse, strlen($praefix))).'.php';

		if (is_file($datei)) {
			require_once $datei;
		}

		return;
	}
});
