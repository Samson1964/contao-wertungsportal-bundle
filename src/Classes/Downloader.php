<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Laedt die zwanzig Landesverbands-Zips der DWZ-Liste vom nu-Server und legt
 * sie datiert unter `files/wertungsportal/downloads/` ab.
 *
 * **Herkunft:** Bis 1.35.2 war das ein eigenstaendiges Skript unter
 * `src/Resources/public/Wertungsportal_Download.php`, das der Hoster per Curl
 * ueber eine URL aufgerufen hat. Es band `system/initialize.php` ein — den Weg
 * gibt es in Contao 5 nicht mehr. Der Klassenrumpf ist unveraendert
 * uebernommen; angestossen wird er jetzt ueber `wertungsportal:download`.
 *
 * Der frueher noetige Token-Schutz (`?key=`) entfaellt ersatzlos: Ein
 * Konsolenbefehl ist von aussen nicht erreichbar.
 *
 * **Anmeldung (ab 1.46.0):** nu schuetzt die DWZ-Liste samt Zip-Downloads per
 * OAuth2. Geladen wird deshalb ueber `OAuth2Client::herunterladen()` mit dem
 * Token der DWZ-Liste; die Adresse baut `OAuth2Client::downloadAdresse()` aus
 * der Basisadresse der Einstellungen (siehe docs/zugang.md).
 *
 * Die Ausgabe geht weiterhin per `echo` heraus; der Konsolenbefehl faengt sie
 * ab und reicht sie zeilenweise durch, damit der Fortschritt waehrend des
 * Laufs sichtbar bleibt.
 */
class Downloader
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
		
		$datum = date('Ymd');
		$zielpfad = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::projektpfad().'/files/wertungsportal/downloads/';
		$fehlschlaege = 0;

		foreach($links as $link)
		{
			$link_array = pathinfo($link);
			$dateiname = $link_array['filename'];
			$suffix = $link_array['extension'];
			$zieldatei = $zielpfad.$dateiname.'_'.$datum.'_wertungsportal-version.'.$suffix;

			// Datei laden - mit Statuscheck, Zip-Prüfung und Wiederholungen
			echo "Lade $link<br>\n";
			$ergebnis = \Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::herunterladen(\Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::downloadAdresse($link), $zieldatei);

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

		// Rueckgabe ergaenzt beim Herausloesen aus dem alten Skript: Der
		// Konsolenbefehl braucht sie fuer seinen Rueckgabewert, damit ein
		// Cronjob einen Fehlschlag ueberhaupt bemerken kann
		return $fehlschlaege;
	}

}
