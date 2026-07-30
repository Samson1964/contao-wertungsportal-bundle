<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Schreibt je Wertungsportal-Abfrage eine Zeile nach var/logs.
 *
 * Zweck ist die Beobachtung der Laufzeiten: Welche Funktion braucht wie lange,
 * wie oft antwortet der Zwischenspeicher, wie träge ist die Schnittstelle zu
 * welcher Tageszeit. Ohne Einschalten in den Einstellungen wird nichts
 * geschrieben.
 *
 * Format: Semikolon-getrennt mit Kopfzeile — so lässt sich die Datei sowohl
 * mit einem Editor lesen als auch direkt in einer Tabellenkalkulation öffnen.
 * Werte werden in Anführungszeichen gesetzt und enthaltene Anführungszeichen
 * verdoppelt (CSV-Regel), damit ein Semikolon im Browserkennzeichen die
 * Spalten nicht verschiebt.
 *
 * Es wird EINE DATEI JE TAG geschrieben. Eine einzige, endlos wachsende Datei
 * wäre auf einer besuchten Website unbrauchbar; so ist auch klar, was man
 * löschen kann.
 *
 * DATENSCHUTZ: Die Zeile enthält die IP-Adresse des Besuchers und damit ein
 * personenbezogenes Datum. Wer das Log dauerhaft einschaltet, muss das in der
 * Datenschutzerklärung nennen und die Dateien regelmäßig löschen.
 */
class Zugriffslog
{
	/**
	 * Spaltenüberschriften; stehen als erste Zeile in jeder neuen Tagesdatei.
	 */
	const SPALTEN = array
	(
		'Zeitpunkt', 'Quelle', 'Funktion', 'Endpunkt', 'Cacheschluessel',
		'Dauer_ms', 'Dauer_API_ms', 'HTTP', 'Datensaetze',
		'IP', 'Browser', 'Seite', 'Herkunft', 'Angemeldet',
	);

	/**
	 * Merker, ob die Kopfzeile in dieser Anfrage schon geprüft wurde —
	 * erspart bei mehreren Abfragen je Seitenaufruf die wiederholte
	 * Dateiprüfung.
	 */
	protected static $kopfGeprueft = false;

	/**
	 * Meldet, ob das Log eingeschaltet ist.
	 */
	public static function aktiv()
	{
		return !empty($GLOBALS['TL_CONFIG']['wertungsportal_zugriffslog']);
	}

	/**
	 * Schreibt eine Zeile. Ist das Log abgeschaltet, kehrt die Methode sofort
	 * zurück, ohne die Umgebung anzufassen.
	 *
	 * Fehler beim Schreiben werden verschluckt: Ein volles Dateisystem oder
	 * ein fehlendes Schreibrecht darf die Seitenauslieferung nicht stören —
	 * das Log ist Beiwerk.
	 *
	 * @param array $params  Parameter der Abfrage (funktion, cachekey)
	 * @param array $angaben quelle (api|cache|lokal|notdaten|fehler),
	 *                       dauer (ms, gesamt), http, anzahl (Datensätze)
	 */
	public static function schreibe($params, $angaben)
	{
		if(!self::aktiv()) return;

		try
		{
			$datei = self::datei();
			if($datei === '') return;

			$funktion = (string) ($params['funktion'] ?? '');
			$endpunkte = \Schachbulle\ContaoWertungsportalBundle\Helper\API::endpunkte();

			$zeile = array
			(
				date('Y-m-d H:i:s'),
				(string) ($angaben['quelle'] ?? ''),
				$funktion,
				(string) ($endpunkte[$funktion] ?? ''),
				(string) ($params['cachekey'] ?? ''),
				number_format((float) ($angaben['dauer'] ?? 0), 1, '.', ''),
				// Nur bei einem echten Schnittstellenaufruf gefüllt; sonst
				// bleibt die Spalte leer statt eine 0 zu behaupten
				isset($angaben['dauer_api']) ? number_format((float) $angaben['dauer_api'], 1, '.', '') : '',
				(string) ($angaben['http'] ?? ''),
				isset($angaben['anzahl']) ? (string) (int) $angaben['anzahl'] : '',
				self::ip(),
				self::umgebung('HTTP_USER_AGENT'),
				self::umgebung('REQUEST_URI'),
				self::umgebung('HTTP_REFERER'),
				self::angemeldet(),
			);

			self::kopfzeile($datei);

			// LOCK_EX, damit sich gleichzeitige Zugriffe nicht in dieselbe
			// Zeile schreiben
			file_put_contents($datei, self::csv($zeile)."\n", FILE_APPEND | LOCK_EX);
		}
		catch(\Throwable $e)
		{
			// bewusst still
		}
	}

	/**
	 * Ermittelt die Anzahl der Datensätze einer Antwort — grob, aber
	 * aussagekräftig für die Beurteilung einer Laufzeit (eine Vereinsliste mit
	 * 800 Mitgliedern darf länger dauern als eine mit acht).
	 *
	 * @param  array|null $result Antwort im Format der Schnittstelle
	 * @return int|null           Anzahl oder null, wenn nicht bestimmbar
	 */
	public static function anzahl($result)
	{
		if(!is_array($result) || !isset($result['body']) || !is_array($result['body'])) return null;

		foreach(array('data', 'players', 'matches', 'entries') as $schluessel)
		{
			if(isset($result['body'][$schluessel]) && is_array($result['body'][$schluessel]))
			{
				return count($result['body'][$schluessel]);
			}
		}

		// Einzelantwort (Karteikarte, Turnierkopf)
		return 1;
	}

	/**
	 * Pfad der Tagesdatei. Liegt unter var/logs des Contao-Projekts, also dort,
	 * wo auch die Contao-eigenen Logs stehen.
	 *
	 * @return string Pfad oder '' wenn das Verzeichnis nicht nutzbar ist
	 */
	protected static function datei()
	{
		$verzeichnis = self::verzeichnis();
		if($verzeichnis === '') return '';

		return $verzeichnis.'/wertungsportal-zugriffe-'.date('Y-m-d').'.log';
	}

	/**
	 * Ermittelt das Log-Verzeichnis und legt es an, falls es fehlt.
	 *
	 * Der Projektpfad kommt über den Container-Parameter kernel.project_dir;
	 * ist der Container nicht verfügbar (z. B. in den eigenständigen
	 * Download-Skripten), wird über TL_ROOT ausgewichen.
	 *
	 * @return string Pfad ohne Schrägstrich am Ende, '' wenn nicht nutzbar
	 */
	protected static function verzeichnis()
	{
		$wurzel = '';

		try
		{
			$container = \System::getContainer();
			if($container && $container->hasParameter('kernel.project_dir')) $wurzel = (string) $container->getParameter('kernel.project_dir');
		}
		catch(\Throwable $e)
		{
			$wurzel = '';
		}

		if($wurzel === '' && defined('TL_ROOT')) $wurzel = TL_ROOT;
		if($wurzel === '') return '';

		$verzeichnis = $wurzel.'/var/logs';

		if(!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0775, true)) return '';
		if(!is_writable($verzeichnis)) return '';

		return $verzeichnis;
	}

	/**
	 * Schreibt die Kopfzeile, wenn die Tagesdatei neu angelegt wird.
	 */
	protected static function kopfzeile($datei)
	{
		if(self::$kopfGeprueft) return;

		self::$kopfGeprueft = true;

		if(!file_exists($datei) || filesize($datei) === 0)
		{
			file_put_contents($datei, self::csv(self::SPALTEN)."\n", LOCK_EX);
		}
	}

	/**
	 * Setzt eine Zeile nach CSV-Regeln zusammen (Semikolon als Trenner).
	 */
	protected static function csv($werte)
	{
		$felder = array();

		foreach($werte as $wert)
		{
			// Zeilenumbrüche würden die Zeile zerreißen
			$wert = str_replace(array("\r", "\n"), ' ', (string) $wert);
			$felder[] = '"'.str_replace('"', '""', $wert).'"';
		}

		return implode(';', $felder);
	}

	/**
	 * IP-Adresse des Besuchers. Contaos Environment berücksichtigt dabei die
	 * vertrauenswürdigen Proxys, weshalb sie dem rohen REMOTE_ADDR vorgezogen
	 * wird (hinter einem Reverse Proxy stünde dort sonst immer derselbe Wert).
	 */
	protected static function ip()
	{
		try
		{
			$ip = (string) \Environment::get('ip');
			if($ip !== '') return $ip;
		}
		catch(\Throwable $e)
		{
			// Fallback unten
		}

		return self::umgebung('REMOTE_ADDR');
	}

	/**
	 * Liest einen Wert aus der Serverumgebung und kürzt ihn auf ein für ein
	 * Log vernünftiges Maß.
	 */
	protected static function umgebung($schluessel)
	{
		$wert = isset($_SERVER[$schluessel]) ? (string) $_SERVER[$schluessel] : '';

		return mb_substr($wert, 0, 400);
	}

	/**
	 * Meldet, ob ein Mitglied angemeldet ist — erklärt Unterschiede in den
	 * Ausgaben (angemeldete Besucher sehen Daten, die Gästen gesperrt sind)
	 * und damit auch in den Laufzeiten. Der Name wird BEWUSST NICHT
	 * protokolliert, die Angabe bleibt auf ja/nein beschränkt.
	 */
	protected static function angemeldet()
	{
		try
		{
			return \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitglied()->id ? 'ja' : 'nein';
		}
		catch(\Throwable $e)
		{
			return '';
		}
	}
}
