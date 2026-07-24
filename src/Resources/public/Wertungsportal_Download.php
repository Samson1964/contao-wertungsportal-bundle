<?php

/**
 * Contao Open Source CMS, Copyright (C) 2005-2020 Leo Feyer
 */

use Contao\Controller;

/**
 * Initialize the system
 */
define('TL_MODE', 'FE');
define('TL_SCRIPT', 'bundles/contaowertungsportal/Wertungsportal_Download.php');
require($_SERVER['DOCUMENT_ROOT'].'/../system/initialize.php');

// Token-Schutz: Aufruf nur mit gültigem Schlüssel erlauben (?key=SCHLÜSSEL).
// Der Schlüssel wird in den Contao-Einstellungen gepflegt (wertungsportal_crontoken);
// ohne konfigurierten Schlüssel ist das Skript gesperrt.
$strCrontoken = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_crontoken'] ?? '');

if($strCrontoken === '' || !hash_equals($strCrontoken, (string) \Contao\Input::get('key')))
{
	http_response_code(403);
	die('Zugriff verweigert');
}

class Wertungsportal_Download
{
	public function run()
	{
		// Downloads aller DWZ-Dateien vom SVW-Server
		$links = array
		(
			'LV-0-dwzliste.zip',
			'LV-1-dwzliste.zip',
			'LV-2-dwzliste.zip',
			'LV-3-dwzliste.zip',
			'LV-4-dwzliste.zip',
			'LV-5-dwzliste.zip',
			'LV-6-dwzliste.zip',
			'LV-7-dwzliste.zip',
			'LV-8-dwzliste.zip',
			'LV-9-dwzliste.zip',
			'LV-A-dwzliste.zip',
			'LV-B-dwzliste.zip',
			'LV-C-dwzliste.zip',
			'LV-D-dwzliste.zip',
			'LV-E-dwzliste.zip',
			'LV-F-dwzliste.zip',
			'LV-G-dwzliste.zip',
			'LV-H-dwzliste.zip',
			'LV-L-dwzliste.zip',
			'LV-M-dwzliste.zip'
		);
		
		$url = 'https://schachde-apps.liga.nu/dsbwertungsportal/rs/dwz/dwzliste/download/';
		$datum = date('Ymd');
		$zielpfad = substr($_SERVER['DOCUMENT_ROOT'], 0, -3).'files/wertungsportal/downloads/'; // web-Ordner entfernen und Zielordner anhängen
		$fehlschlaege = 0;

		foreach($links as $link)
		{
			$link_array = pathinfo($link);
			$dateiname = $link_array['filename'];
			$suffix = $link_array['extension'];
			$zieldatei = $zielpfad.$dateiname.'_'.$datum.'_wertungsportal-version.'.$suffix;

			// Datei laden - mit Statuscheck, Zip-Prüfung und Wiederholungen
			echo "Lade $link<br>\n";
			$ergebnis = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DownloadDatei($url.$link, $zieldatei);

			if($ergebnis['success'])
			{
				if($ergebnis['versuche'] > 1) echo 'OK nach '.$ergebnis['versuche'].' Versuchen<br>'."\n";
			}
			else
			{
				echo 'FEHLER bei '.$link.': '.$ergebnis['error'].'<br>'."\n";
				$fehlschlaege++;
			}
		}

		if($fehlschlaege)
		{
			echo 'Fertig mit '.$fehlschlaege.' Fehlschlag/Fehlschlaegen von '.count($links).' Dateien';
		}
		else
		{
			echo 'Fertig';
		}
	}

}

/**
 * Instantiate controller
 */
$objSpielerdaten = new Wertungsportal_Download();
$objSpielerdaten->run();
