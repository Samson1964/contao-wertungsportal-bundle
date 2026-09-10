<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Protokolliert Werte der Schnittstelle, die es nach dem Regelwerk nicht geben
 * dürfte — mit Spieler und Turnier, damit sich daraus eine Fehlermeldung an nu
 * schreiben läßt.
 *
 * Anlass war der 13.08.2026: nu lieferte eine **negative Turnierleistung**. Nach
 * der Wertungsordnung des DSB kann die Leistung nicht unter null fallen; der
 * Wert ist also ein Fehler auf der Gegenseite und keine Besonderheit, auf die
 * sich dieses Bundle einstellen müßte. Damit er meldbar wird, muß aber bekannt
 * sein, WEN es betrifft — und genau das ging bisher verloren: Der Datensatz
 * scheiterte beim Wegschreiben, und übrig blieb eine SQL-Meldung ohne Namen.
 *
 * Geschrieben wird in eine eigene Datei je Monat unter `var/logs`, nicht ins
 * Systemprotokoll: Dort wäre der Befund zwischen Hunderten Cron-Zeilen nicht
 * mehr zu finden, und die Datei läßt sich unverändert an nu weiterreichen.
 * Eine Zusammenfassung geht zusätzlich ins Systemprotokoll, damit die Sache
 * überhaupt auffällt.
 *
 * **Jeder Fall steht genau einmal darin.** Der Vorlader läuft jede Nacht über
 * dieselben Turniere; ohne Dublettenprüfung wuchs die Datei mit jedem Lauf um
 * dieselben Zeilen — im August 2026 waren es 45 für 21 Fälle. Die Datei ist
 * dabei ihr eigener Merkzettel: Sie wird beim ersten Befund eines Abrufs
 * eingelesen, es braucht weder Tabelle noch zweite Datei, und mit dem
 * Monatswechsel fängt die Zählung von selbst neu an. Zum Schlüssel gehört der
 * Wert, damit ein GEÄNDERTER Wert am selben Spieler wieder auffällt.
 */
class Auffaellig
{
	/**
	 * Ganzzahlfelder der Spieler-Datensätze, die nicht negativ sein dürfen.
	 *
	 * Alle liegen örtlich in vorzeichenlosen Spalten — ein negativer Wert
	 * bringt dort auf einem Server im Strict-Modus den ganzen Abgleich zu Fall.
	 * `tournamentPerformance` ist seit 1.26.2 vorzeichenbehaftet, damit der
	 * Betrieb weiterläuft; gemeldet wird der Wert trotzdem, denn richtig ist
	 * er deswegen nicht.
	 */
	const FELDER = array
	(
		'tournamentPerformance'    => 'Turnierleistung',
		'ratingOld'                => 'DWZ alt',
		'ratingNew'                => 'DWZ neu',
		'indexOld'                 => 'Index alt',
		'indexNew'                 => 'Index neu',
		'averageRatingCompetitors' => 'Gegnerschnitt',
		'numberOfGames'            => 'Partien',
		'birthyear'                => 'Geburtsjahr',
	);

	/**
	 * Befunde, die schon festgehalten sind — sowohl die dieses Seitenaufrufs
	 * als auch die, die bereits in der Monatsdatei stehen.
	 *
	 * `null` heißt „Datei noch nicht gelesen"; sie wird erst beim ersten
	 * Befund geöffnet, damit ein Abgleich ohne Auffälligkeiten sie gar nicht
	 * anfaßt.
	 *
	 * **Wozu:** Derselbe Spieler kommt in einem Abgleich mehrfach vorbei
	 * (Auswertung, Turnierhistorie, Partien), und der Vorlader läuft jede
	 * Nacht über dieselben Turniere. Ohne diese Liste stünde jeder Befund nach
	 * einer Woche siebenmal in der Datei, und wer sie an nu weiterreicht,
	 * müßte erst aufräumen. Im August 2026 waren es 45 Zeilen für 21 Fälle.
	 *
	 * @var array|null
	 */
	protected static $bekannt = null;

	/**
	 * Zahl der NEU festgehaltenen Befunde dieses Seitenaufrufs, für die
	 * Zusammenfassung. Wiederholungen zählen nicht mit — sonst meldete das
	 * Systemprotokoll Nacht für Nacht dieselbe Zahl.
	 */
	protected static $anzahl = 0;

	/**
	 * Prüft eine Liste von Spieler-Datensätzen und schreibt Auffälliges weg.
	 *
	 * Wird aus den Abgleichen aufgerufen und ist bewußt anspruchslos: Sie wirft
	 * nichts, gibt nichts zurück und darf den Abgleich unter keinen Umständen
	 * aufhalten. Ein Fehler bei der Protokollierung wäre schlimmer als der
	 * Befund, den sie festhalten soll.
	 *
	 * @param  array  $spieler   Spieler-Datensätze der Schnittstelle
	 * @param  string $turnier   UUID des Turniers, darf leer sein
	 * @param  string $herkunft  Welcher Abgleich meldet (für die Spalte „Quelle")
	 * @return void
	 */
	public static function pruefeSpieler($spieler, $turnier = '', $herkunft = '')
	{
		if(!is_array($spieler)) return;

		foreach($spieler as $satz)
		{
			if(!is_array($satz)) continue;

			foreach(self::FELDER as $feld => $bezeichnung)
			{
				if(!array_key_exists($feld, $satz)) continue;
				if(!is_numeric($satz[$feld])) continue;

				$wert = (int) $satz[$feld];

				if($wert >= 0) continue;

				self::melde($satz, $turnier, $feld, $bezeichnung, $wert, $herkunft);
			}
		}
	}

	/**
	 * Schreibt einen einzelnen Befund weg.
	 *
	 * Mitgeschrieben wird nicht nur der beanstandete Wert, sondern auch das,
	 * woraus er sich errechnet (Gegnerschnitt, Partien, Punkte). Nur damit
	 * kann die Gegenseite nachvollziehen, wie er zustande kam, ohne selbst
	 * suchen zu müssen.
	 *
	 * @param  array  $satz         Spieler-Datensatz der Schnittstelle
	 * @param  string $turnier      UUID des Turniers
	 * @param  string $feld         Feldname der Schnittstelle
	 * @param  string $bezeichnung  Deutscher Name des Feldes
	 * @param  int    $wert         Der beanstandete Wert
	 * @param  string $herkunft     Welcher Abgleich meldet
	 * @return void
	 */
	protected static function melde($satz, $turnier, $feld, $bezeichnung, $wert, $herkunft)
	{
		$person = (string) ($satz['nuLigaPersonId'] ?? ($satz['playerUuid'] ?? ''));
		$schluessel = self::schluessel($turnier, $person, $feld, $wert);

		// Steht der Fall schon in der Monatsdatei oder kam er in diesem Abruf
		// bereits vorbei, braucht es weder eine zweite Zeile noch eine neue
		// Meldung im Systemprotokoll
		self::bekannteLaden();

		if(isset(self::$bekannt[$schluessel])) return;

		self::$bekannt[$schluessel] = true;
		++self::$anzahl;

		$zeile = array
		(
			date('Y-m-d H:i:s'),
			$herkunft,
			$turnier,
			$person,
			trim((string) ($satz['lastname'] ?? '').', '.(string) ($satz['firstname'] ?? ''), ', '),
			(string) ($satz['vkz'] ?? ''),
			$bezeichnung.' ('.$feld.')',
			(string) $wert,
			(string) ($satz['numberOfGames'] ?? ''),
			(string) ($satz['averageRatingCompetitors'] ?? ''),
			(string) ($satz['wins'] ?? ''),
			(string) ($satz['ratingOld'] ?? ''),
			(string) ($satz['ratingNew'] ?? ''),
		);

		self::schreibe($zeile);
	}

	/**
	 * Bildet den Schlüssel, unter dem ein Befund als „schon bekannt" gilt.
	 *
	 * Turnier, Person, Feld und Wert zusammen. Der Wert gehört dazu, damit ein
	 * geänderter Wert am selben Spieler wieder auffällt — korrigiert nu die
	 * Auswertung nur zur Hälfte, soll das nicht untergehen.
	 *
	 * @param  string $turnier UUID des Turniers
	 * @param  string $person  nuLiga-Kennung oder Spieler-UUID
	 * @param  string $feld    Feldname der Schnittstelle
	 * @param  mixed  $wert    Der beanstandete Wert
	 * @return string Schlüssel
	 */
	protected static function schluessel($turnier, $person, $feld, $wert)
	{
		return $turnier.'|'.$person.'|'.$feld.'|'.$wert;
	}

	/**
	 * Liest die Befunde ein, die in der Monatsdatei schon stehen.
	 *
	 * Die Datei ist damit ihr eigener Merkzettel — es braucht weder eine
	 * Tabelle noch eine zweite Datei, und mit dem Monatswechsel fängt die
	 * Zählung von selbst neu an.
	 *
	 * Aus der Spalte „Feld" wird der Schnittstellenname wieder herausgelöst;
	 * dort steht er in Klammern hinter der deutschen Bezeichnung, etwa
	 * `Turnierleistung (tournamentPerformance)`.
	 *
	 * Läßt sich die Datei nicht lesen, bleibt die Liste leer: Lieber eine
	 * Zeile zuviel als ein verlorener Befund.
	 *
	 * @return void
	 */
	protected static function bekannteLaden()
	{
		if(self::$bekannt !== null) return;

		self::$bekannt = array();

		try
		{
			$datei = static::datei();

			if($datei === '' || !is_file($datei)) return;

			$fp = @fopen($datei, 'r');

			if($fp === false) return;

			while(($zeile = fgetcsv($fp, 0, ';')) !== false)
			{
				// Kopfzeile und alles, was zu kurz ist, übergehen
				if(count($zeile) < 8 || $zeile[0] === 'Zeitpunkt') continue;

				$feld = (string) $zeile[6];

				if(preg_match('/\(([^)]+)\)\s*$/', $feld, $treffer)) $feld = $treffer[1];

				self::$bekannt[self::schluessel((string) $zeile[2], (string) $zeile[3], $feld, (string) $zeile[7])] = true;
			}

			fclose($fp);
		}
		catch(\Throwable $e)
		{
			// Ohne Merkzettel wird höchstens doppelt protokolliert
		}
	}

	/**
	 * Hängt eine Zeile an die Monatsdatei an und legt sie samt Kopfzeile an,
	 * wenn es sie noch nicht gibt.
	 *
	 * @param  array $zeile Feldwerte in der Reihenfolge der Kopfzeile
	 * @return void
	 */
	protected static function schreibe($zeile)
	{
		try
		{
			$datei = static::datei();

			if($datei === '') return;

			$neu = !is_file($datei);
			$fp = @fopen($datei, 'a');

			if($fp === false) return;

			if($neu)
			{
				fputcsv($fp, array('Zeitpunkt', 'Quelle', 'Turnier-UUID', 'Person', 'Name', 'VKZ', 'Feld', 'Wert', 'Partien', 'Gegnerschnitt', 'Punkte', 'DWZ alt', 'DWZ neu'), ';');
			}

			fputcsv($fp, $zeile, ';');
			fclose($fp);
		}
		catch(\Throwable $e)
		{
			// Ein klemmendes Protokoll darf den Abgleich nicht stören
		}
	}

	/**
	 * Liefert den Pfad der Monatsdatei.
	 *
	 * Eine Datei je Monat statt je Tag: Diese Befunde sollten selten sein, und
	 * zum Weiterreichen an nu ist eine Datei praktischer als dreißig.
	 *
	 * @return string Vollständiger Pfad, '' wenn kein Verzeichnis nutzbar ist
	 */
	public static function datei()
	{
		$wurzel = '';

		try
		{
			$container = \Contao\System::getContainer();
			if($container && $container->hasParameter('kernel.project_dir')) $wurzel = (string) $container->getParameter('kernel.project_dir');
		}
		catch(\Throwable $e)
		{
			$wurzel = '';
		}

		if($wurzel === '' && \defined('TL_ROOT')) $wurzel = TL_ROOT;
		if($wurzel === '') return '';

		$verzeichnis = $wurzel.'/var/logs';

		if(!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0775, true)) return '';
		if(!is_writable($verzeichnis)) return '';

		return $verzeichnis.'/wertungsportal-auffaellig-'.date('Y-m').'.log';
	}

	/**
	 * Schreibt eine Zusammenfassung ins Systemprotokoll.
	 *
	 * Aufgerufen am Ende eines Abrufs. Ohne diese Zeile bliebe die Datei
	 * unbemerkt liegen — niemand sieht regelmäßig in `var/logs` nach.
	 *
	 * @return void
	 */
	public static function fasseZusammen()
	{
		if(self::$anzahl < 1) return;

		$anzahl = self::$anzahl;

		// Zurücksetzen, damit ein langlaufender Vorgang (Vorlader) je Abruf
		// meldet und nicht immer wieder dieselbe Summe
		self::$anzahl = 0;

		try
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog(
				'Wertungsportal: '.$anzahl.' neue unmögliche Werte von der Schnittstelle erhalten (negative Zahlen, wo es keine geben kann). Einzelheiten samt Spieler und Turnier in '.basename(static::datei()).' — geeignet als Fehlermeldung an nu. Bereits festgehaltene Fälle werden nicht erneut gemeldet.',
				__METHOD__,
				'ERROR'
			);
		}
		catch(\Throwable $e)
		{
			// Beiwerk
		}
	}

	/**
	 * Zahl der bisher in diesem Seitenaufruf festgehaltenen Befunde.
	 *
	 * Nur für Prüfstände und die Zusammenfassung gedacht.
	 *
	 * @return int
	 */
	public static function anzahl()
	{
		return self::$anzahl;
	}
}
