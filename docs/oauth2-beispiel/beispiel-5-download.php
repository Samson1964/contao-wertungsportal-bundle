<?php

declare(strict_types=1);

/**
 * Beispiel 5 — die DWZ-Liste als Zip-Datei holen.
 *
 * Für große Datenmengen ist das der richtige Weg: Statt Zehntausende Spieler
 * einzeln abzurufen, lädt man eine Datei. `LV-0-dwzliste.zip` enthält den
 * gesamten Bestand, `LV-1` bis `LV-8` je einen Landesverband. In jedem Archiv
 * liegen `spieler.csv`, `vereine.csv` und `verbaende.csv` im offiziellen
 * DSB-Format.
 *
 * Diese Dateien werden einmal am Tag neu erzeugt. Sie mehrmals täglich zu
 * holen bringt nichts und belastet beide Seiten.
 *
 * Aufruf:
 *   php beispiel-5-download.php                 (LV-0, der ganze Bestand)
 *   php beispiel-5-download.php LV-3-dwzliste.zip
 *
 * Scope: dwz_liste
 */

$clients = require __DIR__.'/start.php';

$dateiname = $argv[1] ?? 'LV-0-dwzliste.zip';
$ziel = __DIR__.'/'.$dateiname;
$client = $clients['liste'] ?? $clients['turniere'];

if (null === ($clients['liste'] ?? null)) {
	echo "Hinweis: keine eigene Freischaltung für die DWZ-Liste eingetragen —\n"
		."der Download läuft mit dem Token für Turniere.\n\n";
}

try {
	printf("Lade %s …\n", $dateiname);

	$start = microtime(true);

	// Prüft das Ergebnis gleich als Zip-Archiv — das erkennt auch einen
	// abgeschnittenen Download, der sonst erst beim Auspacken auffiele
	$groesse = $client->herunterladen($dateiname, $ziel);

	printf("Fertig: %s (%.1f MB) in %.1f Sekunden\n\n", $ziel, $groesse / 1048576, microtime(true) - $start);

	// ── Hineinschauen, ohne auszupacken ───────────────────────────────
	$archiv = new ZipArchive();

	if (true === $archiv->open($ziel)) {
		echo "Inhalt des Archivs\n";
		echo str_repeat('-', 54)."\n";

		for ($i = 0; $i < $archiv->numFiles; ++$i) {
			$eintrag = $archiv->statIndex($i);

			printf("%-28s %10d Bytes\n", (string) $eintrag['name'], (int) $eintrag['size']);
		}

		// Die ersten Zeilen der Spielerdatei zeigen, wie sie aufgebaut ist.
		// Die Dateien sind nach Windows-1252 kodiert, nicht UTF-8 — beim
		// Einlesen also umwandeln
		$inhalt = $archiv->getFromName('spieler.csv');

		if (false !== $inhalt) {
			echo "\nDie ersten drei Zeilen aus spieler.csv\n";

			foreach (array_slice(explode("\n", $inhalt), 0, 3) as $zeile) {
				echo '  '.mb_convert_encoding(rtrim($zeile, "\r"), 'UTF-8', 'Windows-1252')."\n";
			}
		}

		$archiv->close();
	}
} catch (DsbOAuth2Exception $e) {
	echo 'Fehler: '.$e->getMessage()."\n";

	exit(1);
}
