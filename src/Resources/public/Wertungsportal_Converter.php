<?php

/**
 * Contao Open Source CMS, Copyright (C) 2005-2020 Leo Feyer
 */

use Contao\Controller;

/**
 * Initialize the system
 */
define('TL_MODE', 'FE');
define('TL_SCRIPT', 'bundles/contaowertungsportal/Wertungsportal_Converter.php');
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

class Wertungsportal_Converter
{
	protected $zielpfad;
	protected $packpfad;
	protected $archivpfad;
	protected $exportpfad;
	protected $spieler = array();
	protected $vereine = array();
	protected $verbaende = array();
	protected $suchen = array();
	protected $ersetzen = array();
	protected $archive = array();

	/**
	 * Klasse initialisieren
	 */
	public function __construct()
	{
		$this->zielpfad = substr($_SERVER['DOCUMENT_ROOT'], 0, -3).'files/wertungsportal/tmp/'; // web-Ordner entfernen und Zielordner anhängen
		if(!file_exists($this->zielpfad)) mkdir($this->zielpfad, 0777);
		$this->packpfad = substr($_SERVER['DOCUMENT_ROOT'], 0, -3).'files/wertungsportal/raw/'; // web-Ordner entfernen und Zielordner anhängen
		if(!file_exists($this->packpfad)) mkdir($this->packpfad, 0777);
		$this->archivpfad = substr($_SERVER['DOCUMENT_ROOT'], 0, -3).'files/wertungsportal/'.date('Y').'/'; // web-Ordner entfernen und Zielordner anhängen
		if(!file_exists($this->archivpfad)) mkdir($this->archivpfad, 0777);
		$this->exportpfad = substr($_SERVER['DOCUMENT_ROOT'], 0, -3).'files/wertungsportal/export/'; // web-Ordner entfernen und Zielordner anhängen
		if(!file_exists($this->exportpfad)) mkdir($this->exportpfad, 0777);
		$this->suchen = array('Ü', 'Ö', 'Ä', 'ü', 'ö', 'ä', 'ß', 'ú', 'ó', 'á', 'é', 'à', 'ò');
		$this->ersetzen = array('Ue', 'Oe', 'Ae', 'ue', 'oe', 'ae', 'ss', 'u', 'o', 'a', 'e', 'a', 'o');
		
		// Zielpfade, Dateinamen und Optionen festlegen
		$this->archive = array
		(
			array('verband' => ''),
			array('verband' => '1'),
			array('verband' => '2'),
			array('verband' => '3'),
			array('verband' => '4'),
			array('verband' => '5'),
			array('verband' => '6'),
			array('verband' => '7'),
			array('verband' => '8'),
			array('verband' => '9'),
			array('verband' => 'A'),
			array('verband' => 'B'),
			array('verband' => 'C'),
			array('verband' => 'D'),
			array('verband' => 'E'),
			array('verband' => 'F'),
			array('verband' => 'G'),
			array('verband' => 'H'),
			array('verband' => 'L'),
			array('verband' => 'M')
		);
	}

	public function run()
	{
		// Download der CSV-Datei Deutschland vom nu-Server
		$url = 'https://schachde-apps.liga.nu/dsbwertungsportal/rs/dwz/dwzliste/download/LV-0-dwzliste.zip';
		$datum = date('Ymd');

		$link_array = pathinfo($url);
		$dateiname = $link_array['filename'];
		$suffix = $link_array['extension'];

		// Datei laden - mit Statuscheck, Zip-Prüfung und Wiederholungen
		echo "Lade $url<br>\n";
		$zieldatei = $this->zielpfad.$dateiname.'_'.$datum.'.'.$suffix;
		echo "Schreibe $zieldatei<br>\n";
		$ergebnis = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DownloadDatei($url, $zieldatei);

		if(!$ergebnis['success'])
		{
			echo 'FEHLER: Download fehlgeschlagen ('.$ergebnis['error'].'), Abbruch<br>'."\n";
			return;
		}

		if($ergebnis['versuche'] > 1) echo 'OK nach '.$ergebnis['versuche'].' Versuchen<br>'."\n";
		echo "Entpacke $zieldatei<br>\n";

		$zip = new \ZipArchive;
		$res = $zip->open($zieldatei);
		if($res === true)
		{
			$zip->extractTo($this->zielpfad); // wohin soll es entpackt werden
			$zip->close();
			echo "OK<br>\n";

			// CSV-Dateien einlesen
			$this->spieler = self::readCSV('spieler.csv');
			$this->verbaende = self::readCSV('verbaende.csv');
			$this->vereine = self::readCSV('vereine.csv');

			// Spieler bearbeiten
			self::modifySpieler();

			// Dateien schreiben und packen
			foreach($this->archive as $archiv)
			{
				self::Packer($archiv['verband']);
			}

			// Verzeichnisse aufräumen
			unlink($this->zielpfad.'spieler.csv');
			unlink($this->zielpfad.'vereine.csv');
			unlink($this->zielpfad.'verbaende.csv');
			unlink($this->zielpfad.'README.txt');
			unlink($this->packpfad.'spieler.csv');
			unlink($this->packpfad.'vereine.csv');
			unlink($this->packpfad.'verbaende.csv');
			unlink($this->packpfad.'README.txt');
			
			// Kopien der Dateien im Exportverzeichnis anlegen
			$csvpfad = $this->exportpfad.'csv/';
			if(!file_exists($csvpfad)) mkdir($csvpfad, 0777);
			foreach($this->archive as $item)
			{
				// Pfade anlegen
				if($item['verband'])
				{
					// Mitgliedsverband
					$quelle = $this->archivpfad.'lv'.strtolower($item['verband']).'/LV-'.$item['verband'].'-csv_'.date('Ymd').'.zip';
					$ziel = $csvpfad.'LV-'.$item['verband'].'-csv.zip';
					echo "Kopiere $quelle => $ziel<br>";
					copy($quelle, $ziel);
				}
				else
				{
					// $verband ist leer, also DSB
					$quelle = $this->archivpfad.'csv/LV-0-csv_'.date('Ymd').'.zip';
					$ziel = $csvpfad.'LV-0-csv.zip';
					echo "Kopiere $quelle => $ziel<br>";
					copy($quelle, $ziel);
				}
			}
			
		}
		else
		{
			echo 'FEHLER: Zip-Archiv konnte nicht entpackt werden<br>'."\n";
		}
	}

	public function Packer($verband)
	{
		// Pfade anlegen
		if($verband)
		{
			// Mitgliedsverband
			$csvpfad = $this->archivpfad.'lv'.strtolower($verband).'/';
			if(!file_exists($csvpfad)) mkdir($csvpfad, 0777);
			
		}
		else
		{
			// $verband ist leer, also DSB
			$csvpfad = $this->archivpfad.'csv/';
			if(!file_exists($csvpfad)) mkdir($csvpfad, 0777);
		}
	
		// spieler.csv schreiben
		$fp_csv = fopen($this->packpfad.'spieler.csv', 'w');
		$zeile = 0; $anzahl = 0;
		foreach($this->spieler as $item)
		{
			$zeile++;
			if($zeile == 1) 
			{
				fputcsv($fp_csv,$item); // 1. Zeile immer, nur nicht bei SQL
			}
			elseif($verband && $verband == substr($item[1], 0, 1))
			{
				fputcsv($fp_csv,$item); // Nur diesen Verband speichern
				$anzahl++;
			}
			elseif($verband == '') 
			{
				fputcsv($fp_csv,$item); // DSB immer alle Datensätze
			}
		}
		fclose($fp_csv);
		if($verband) $spieleranzahl = $anzahl;
		else $spieleranzahl = $zeile - 1;

		// vereine.csv schreiben
		$fp_csv = fopen($this->packpfad.'vereine.csv', 'w');
		$zeile = 0; $anzahl = 0;
		foreach($this->vereine as $item)
		{
			$zeile++;
			if($zeile == 1) 
			{
				fputcsv($fp_csv,$item); // 1. Zeile immer, nur nicht bei SQL
			}
			elseif($verband && $verband == substr($item[1], 0, 1))
			{
				fputcsv($fp_csv,$item); // Nur diesen Verband speichern
				$anzahl++;
			}
			elseif($verband == '') 
			{
				fputcsv($fp_csv,$item); // DSB immer alle Datensätze
			}
		}
		fclose($fp_csv);
		if($verband) $vereinsanzahl = $anzahl;
		else $vereinsanzahl = $zeile - 1;

		// verbaende.csv schreiben
		$fp_csv = fopen($this->packpfad.'verbaende.csv', 'w');
		$zeile = 0; $anzahl = 0;
		foreach($this->verbaende as $item)
		{
			$zeile++;
			if($zeile == 1) 
			{
				fputcsv($fp_csv,$item); // 1. Zeile immer, nur nicht bei SQL
			}
			elseif($verband && $verband == substr($item[1], 0, 1))
			{
				fputcsv($fp_csv,$item); // Nur diesen Verband speichern
				$anzahl++;
			}
			elseif($verband == '') 
			{
				fputcsv($fp_csv,$item); // DSB immer alle Datensätze
			}
		}
		fclose($fp_csv);

		// README-Datei mit angepasster Überschrift ins Packverzeichnis schreiben
		self::writeReadme($verband, $spieleranzahl, $vereinsanzahl);

		// --------------------------------------------------------------------
		// CSV-Dateien packen
		// --------------------------------------------------------------------
		$files = array
		(
			$this->packpfad.'spieler.csv',
			$this->packpfad.'vereine.csv',
			$this->packpfad.'verbaende.csv',
			$this->packpfad.'README.txt'
		);
		$zip = new ZipArchive;

		if($verband) 
		{
			$ziel = $csvpfad.'LV-'.$verband.'-csv_'.date('Ymd').'.zip';
			$datei = 'LV-'.$verband.'-csv_'.date('Ymd').'.zip';
		}
		else 
		{
			$ziel = $csvpfad.'LV-0-csv_'.date('Ymd').'.zip';
			$datei = 'LV-0-csv_'.date('Ymd').'.zip';
		}

		if($zip->open($ziel,ZipArchive::CREATE))
		{
			foreach($files as $file)
			{
				$zip->addFile(realpath($file), str_replace($this->packpfad, '', $file));
			}
			$zip->close();
			// Datei in Dateiverwaltung eintragen
			$pfad = substr(str_replace(TL_ROOT, '', $csvpfad), 1);
			self::writeDbafs($pfad, $datei);
		}

	}

	/**
	 * Funktion writeReadme
	 * Schreibt die README.txt für das Packverzeichnis. Vorlage ist die
	 * README.txt aus dem Gesamtdeutschland-Download; für Mitgliedsverbände
	 * werden die Überschrift (Landesverband) und die Spieler-/Vereinszahlen
	 * an den jeweiligen Verband angepasst. Für LV-0 (Verband leer) wird die
	 * Vorlage unverändert übernommen.
	 *
	 * @param string $verband        Verbandskennung (1-9, A-H, L, M) oder leer für DSB
	 * @param int    $spieleranzahl  Anzahl der Spieler in der gepackten spieler.csv
	 * @param int    $vereinsanzahl  Anzahl der Vereine in der gepackten vereine.csv
	 */
	public function writeReadme($verband, $spieleranzahl, $vereinsanzahl)
	{
		$quelle = $this->zielpfad.'README.txt';
		$ziel = $this->packpfad.'README.txt';

		$inhalt = file_get_contents($quelle);

		if($inhalt === false)
		{
			echo "Fehler beim Lesen der Datei $quelle.<br>\n";
			return;
		}

		if($verband)
		{
			// Verbandsname aus verbaende.csv ermitteln (oberste Ebene: Verbandnummer X00)
			$verbandsname = '';

			foreach($this->verbaende as $item)
			{
				if($item[0] == $verband.'00')
				{
					$verbandsname = $item[3];
					break;
				}
			}

			// Überschrift ersetzen: "Landesverband: 0 - Deutschland" => z.B. "Landesverband: 3 - Berlin"
			// [^\r\n] statt Punkt, damit die Windows-Zeilenenden (CRLF) erhalten bleiben
			$inhalt = preg_replace('/^Landesverband:[^\r\n]*/m', 'Landesverband: '.$verband.($verbandsname !== '' ? ' - '.$verbandsname : ''), $inhalt, 1);

			// Spieler- und Vereinszahlen an den Verband anpassen (Zahlenformat wie in der Vorlage: 94,787)
			$inhalt = preg_replace('/ - [\d,.]+ Spieler in [\d,.]+ Vereinen/', ' - '.number_format($spieleranzahl, 0, '.', ',').' Spieler in '.number_format($vereinsanzahl, 0, '.', ',').' Vereinen', $inhalt, 1);
		}

		if(file_put_contents($ziel, $inhalt) !== false)
		{
			echo 'README.txt fuer Verband '.($verband !== '' ? $verband : '0').' geschrieben<br>'."\n";
		}
		else
		{
			echo "Fehler beim Schreiben der Datei $ziel.<br>\n";
		}
	}

	public function readCSV($datei)
	{
		$arr = array();
		if(($fp = fopen($this->zielpfad.$datei, "r")) !== FALSE)
		{
			while(($data = fgetcsv($fp, 1000, ',')) !== FALSE)
			{
				$arr[] = $data;
			}
			fclose($fp);
		}
		return $arr;
	}

	public function modifySpieler()
	{
		// Logdatei anlegen
		$fp = fopen($this->packpfad.'log_'.date('Ymd').'.txt', 'w');
		// Spieler-Array anhand Datenbanktabelle elo modifizieren, Abweichungen bei Name und Geschlecht loggen
		for($x = 1; $x < count($this->spieler); $x++)
		{
			$objPlayer = \Database::getInstance()->prepare("SELECT * FROM tl_wertungsportal_elo WHERE fideid = ?")
			                                     ->execute($this->spieler[$x][13]);
			$ungleich = array();
			if($objPlayer->numRows)
			{
				$differenzen = self::compareSpieler($x, $this->spieler[$x], $objPlayer);
				if(count($differenzen))
				{
					fputs($fp, $this->spieler[$x][4]."\n");
					foreach($differenzen as $item)
					{
						fputs($fp, '- '.$item."\n");
					}
					fputs($fp, "\n");
				}
			}
		}
		fclose($fp);
	}

	/**
	 * Funktion compareSpieler
	 * Spielerdaten von DeWIS und FIDE miteinander vergleichen und loggen
	 */
	public function compareSpieler($index, $spieler, $objPlayer)
	{
		$ungleich = array();

		// ==========================
		// Name vergleichen
		// ==========================
		$dsbname = str_replace($this->suchen, $this->ersetzen, utf8_encode($spieler[4]));
		$fidename = $objPlayer->surname.','.$objPlayer->prename;
		// Komma am Ende entfernen
		if(substr($fidename, -1) == ',') $fidename = substr($fidename, 0, -1);
		if($dsbname != $fidename)
		{
			$ungleich[] = 'Name: '.$dsbname.' => '.$fidename;
		}

		// ==========================
		// Geschlecht vergleichen
		// ==========================
		if($objPlayer->sex == 'F') $objPlayer->sex = 'W';
		if($spieler[5] != $objPlayer->sex)
		{
			$ungleich[] = 'Geschlecht: '.$spieler[5].' => '.$objPlayer->sex;
		}

		// ==========================
		// Geburtsjahr vergleichen
		// ==========================
		if($spieler[7] != $objPlayer->birthday)
		{
			$ungleich[] = 'Geburtsjahr: '.$spieler[7].' => '.$objPlayer->birthday;
		}

		// ==========================
		// Elo vergleichen und ggfs. ändern
		// ==========================
		if($spieler[11] != $objPlayer->rating)
		{
			$ungleich[] = 'FIDE-Elo: '.$spieler[11].' => '.$objPlayer->rating;
			$this->spieler[$index][11] = $objPlayer->rating;
		}

		// ==========================
		// Titel vergleichen und ggfs. ändern
		// ==========================
		if($spieler[12] != $objPlayer->title)
		{
			$ungleich[] = 'FIDE-Titel: '.$spieler[12].' => '.$objPlayer->title;
			$this->spieler[$index][12] = $objPlayer->title;
		}

		// ==========================
		// Land vergleichen und ggfs. ändern
		// ==========================
		if($spieler[14] != $objPlayer->country)
		{
			$ungleich[] = 'FIDE-Land: '.$spieler[14].' => '.$objPlayer->country;
			$this->spieler[$index][14] = $objPlayer->country;
		}

		return $ungleich;
	}

	/**
	 * Generiert den Datensatz in tl_files
	 * (copied from FormFileUpload.php)
	 *   
	 * @param string $strUploadFolder  ohne Prefix TL_ROOT/, ohne Suffix /
	 * @param string $filename
	 */
	protected function writeDbafs($strUploadFolder, $filename)
	{
		// Generate the DB entries
		$strFile = $strUploadFolder . '/' . $filename;
		$objFile = \FilesModel::findByPath($strFile);
		
		// Existing file is being replaced (see contao/core#4818)
		if ($objFile !== null)
		{
			$objFile->tstamp = time();
			$objFile->path   = $strFile;
			$objFile->hash   = md5_file(TL_ROOT . '/' . $strFile);
			$objFile->save();
		}
		else
		{
			\Dbafs::addResource($strFile);
		}
		
		// Update the hash of the target folder
		\Dbafs::updateFolderHashes($strUploadFolder);
		
	}

}

/**
 * Instantiate controller
 */
$objSpielerdaten = new Wertungsportal_Converter();
$objSpielerdaten->run();
