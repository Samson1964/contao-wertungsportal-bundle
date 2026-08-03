<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Sucht und löscht einzelne Einträge im Zwischenspeicher des Wertungsportals.
 *
 * Der Zwischenspeicher liegt je Schnittstellenfunktion in einem eigenen
 * Verzeichnis (wp_Turnierinfo, wp_Karteikarte …), je Eintrag eine Datei. Der
 * Dateiname ist der SHA1-Wert des Schlüssels — aus dem Dateinamen lässt sich
 * der Schlüssel also NICHT zurückrechnen. Wo nur ein Namensteil bekannt ist
 * (Spielberichtsbögen heißen „Turnier-Spieler"), muss deshalb das Verzeichnis
 * durchgesehen und in jeder Datei nachgeschaut werden.
 *
 * Gedacht ist das für den Fall, dass nu einen einzelnen Datensatz nachträglich
 * korrigiert: Statt den gesamten Zwischenspeicher zu leeren und damit jede
 * Seite wieder langsam zu machen, wirft man genau die betroffenen Einträge weg.
 */
class Cachesuche
{
	/**
	 * Die drei Sucharten mit ihrer Beschriftung fürs Backend.
	 */
	const ARTEN = array
	(
		'turnier' => 'Turnier (UUID)',
		'spieler' => 'Spieler (nu-Nummer)',
		'verein'  => 'Verein (VKZ)',
	);

	/**
	 * Liefert die Regeln, nach denen für eine Suchart Einträge gefunden werden.
	 *
	 * Je Schnittstellenfunktion steht dort, wie der Schlüssel aussieht:
	 * „exakt" — der Wert IST der Schlüssel;
	 * „praefix" — der Schlüssel beginnt damit (Spielberichtsbogen: Turnier-Spieler);
	 * „suffix" — der Schlüssel endet damit (derselbe Fall aus Spielersicht).
	 *
	 * Die Suchen (Spielerliste, Turnierliste, Verbandsliste) bleiben bewusst
	 * außen vor: Ihre Schlüssel bestehen aus Suchbegriffen und Zeiträumen,
	 * nicht aus der Kennung eines Datensatzes. Wer sie loswerden will, leert
	 * den Zwischenspeicher über die Systemwartung.
	 *
	 * @param  string $art  Schlüssel aus ARTEN
	 * @return array        funktion => array('typ' => …, 'wert' => …)
	 */
	public static function regeln($art)
	{
		$art = strtolower(trim((string) $art));

		switch($art)
		{
			case 'turnier':
				return array
				(
					'Turnierinfo'        => array('typ' => 'exakt'),
					'Turnierauswertung'  => array('typ' => 'exakt'),
					'Turnierergebnisse'  => array('typ' => 'exakt'),
					// Je Spieler ein Bogen: <turnier>-<spielerId>
					'Spielberichtsbogen' => array('typ' => 'praefix', 'zusatz' => '-'),
				);

			case 'spieler':
				return array
				(
					'Karteikarte'          => array('typ' => 'exakt'),
					'Karteikarte_Turniere' => array('typ' => 'exakt'),
					'Spielberichtsbogen'   => array('typ' => 'suffix', 'zusatz' => '-'),
				);

			case 'verein':
				return array
				(
					'Vereinsliste' => array('typ' => 'exakt'),
					// Deckt auch Verbände ab: dort lautet der Schlüssel
					// <dreistellige zps>00, ist also ebenfalls fünfstellig
					'Vereinsname'  => array('typ' => 'exakt'),
				);
		}

		return array();
	}

	/**
	 * Sucht die Einträge, die zu einem Wert gehören.
	 *
	 * @param  string $art  Schlüssel aus ARTEN
	 * @param  string $wert Turnier-UUID, nu-Nummer oder VKZ
	 *
	 * @return array Liste aus funktion, schluessel, gespeichert (Zeitstempel),
	 *               ablauf (Zeitstempel des Verfalls, 0 = läuft nie ab),
	 *               groesse (Byte) und datei (vollständiger Pfad)
	 */
	public static function eintraege($art, $wert)
	{
		$wert = trim((string) $wert);

		if($wert === '') return array();

		$gefunden = array();

		foreach(self::regeln($art) as $funktion => $regel)
		{
			if($regel['typ'] === 'exakt')
			{
				$eintrag = self::eintrag($funktion, $wert);
				if($eintrag !== null) $gefunden[] = $eintrag;

				continue;
			}

			// Für Präfix und Suffix hilft nur, das Verzeichnis durchzusehen
			$muster = $regel['typ'] === 'praefix' ? $wert.$regel['zusatz'] : $regel['zusatz'].$wert;

			foreach(self::schluesselImVerzeichnis($funktion) as $schluessel)
			{
				$treffer = $regel['typ'] === 'praefix'
					? (strncmp($schluessel, $muster, strlen($muster)) === 0)
					: (substr($schluessel, -strlen($muster)) === $muster);

				if(!$treffer) continue;

				$eintrag = self::eintrag($funktion, $schluessel);
				if($eintrag !== null) $gefunden[] = $eintrag;
			}
		}

		return $gefunden;
	}

	/**
	 * Löscht die gefundenen Einträge.
	 *
	 * @param  string $art  Schlüssel aus ARTEN
	 * @param  string $wert Turnier-UUID, nu-Nummer oder VKZ
	 *
	 * @return array Dieselbe Liste wie eintraege(), jeder Eintrag zusätzlich
	 *               mit 'geloescht' => true|false
	 */
	public static function loeschen($art, $wert)
	{
		$liste = self::eintraege($art, $wert);

		foreach($liste as $i => $eintrag)
		{
			// Unmittelbar über die Datei statt über Cache::erase(): Je Schlüssel
			// gibt es genau eine Datei, und erase() wirft bei einem unbekannten
			// Schlüssel eine Ausnahme, statt einfach nichts zu tun
			$liste[$i]['geloescht'] = is_file($eintrag['datei']) && @unlink($eintrag['datei']);
		}

		return $liste;
	}

	/**
	 * Liest die Eckdaten eines einzelnen Eintrags.
	 *
	 * @param  string $funktion   Name der Schnittstellenfunktion
	 * @param  string $schluessel Cache-Schlüssel
	 *
	 * @return array|null null, wenn es den Eintrag nicht gibt
	 */
	protected static function eintrag($funktion, $schluessel)
	{
		try
		{
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => $schluessel, 'path' => 'wp_'.$funktion, 'extension' => '.cache'));

			// true = auch abgelaufene Einträge; sie belegen weiter Platz und
			// dienen als Notreserve, gehören also mit in die Anzeige
			if(!$cache->isCached($schluessel, true)) return null;

			$datei = $cache->getCacheDir();
			$gespeichert = (int) $cache->getStoreTime($schluessel);

			// getExpiration() liefert den ZEITPUNKT des Verfalls, nicht die
			// Dauer — und null, wenn kein Ablauf vereinbart wurde. Aus dem
			// null wird hier eine 0 für „läuft nie ab"
			$ablauf = (int) $cache->getExpiration($schluessel);

			return array
			(
				'funktion'    => $funktion,
				'schluessel'  => $schluessel,
				'gespeichert' => $gespeichert,
				'ablauf'      => $ablauf,
				'groesse'     => is_file($datei) ? (int) filesize($datei) : 0,
				'datei'       => $datei,
				'geloescht'   => false,
			);
		}
		catch(\Throwable $e)
		{
			return null;
		}
	}

	/**
	 * Liest alle Schlüssel aus dem Verzeichnis einer Funktion.
	 *
	 * Der Dateiname ist ein SHA1-Wert und verrät den Schlüssel nicht — der
	 * steht erst im Inhalt. Je Datei liegt genau ein Eintrag, die Dateien sind
	 * also klein; nur die Spielberichtsbögen kommen überhaupt hierher.
	 *
	 * @param  string $funktion Name der Schnittstellenfunktion
	 * @return array            Liste der Schlüssel
	 */
	protected static function schluesselImVerzeichnis($funktion)
	{
		try
		{
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => 'x', 'path' => 'wp_'.$funktion, 'extension' => '.cache'));
			$verzeichnis = $cache->getCachePath();
		}
		catch(\Throwable $e)
		{
			return array();
		}

		if(!is_dir($verzeichnis)) return array();

		$schluessel = array();

		foreach(glob(rtrim($verzeichnis, '/\\').'/*.cache') ?: array() as $datei)
		{
			$inhalt = @file_get_contents($datei);
			if($inhalt === false) continue;

			$daten = json_decode($inhalt, true);
			if(!is_array($daten)) continue;

			foreach(array_keys($daten) as $k) $schluessel[] = (string) $k;
		}

		return $schluessel;
	}
}
