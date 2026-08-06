<?php

namespace Schachbulle\ContaoWertungsportalBundle\Cron;

/**
 * Täglicher Cronjob: lädt Turnierdaten der letzten Wochen in den
 * Zwischenspeicher, bevor der erste Besucher sie anfordert.
 *
 * Hintergrund: Auswertung, Ergebnisse und Spielberichtsbögen kamen nur zu
 * wenigen Prozent aus dem Zwischenspeicher — fast jeder Aufruf wartete also
 * auf die Schnittstelle. Vorgeladen wird in drei Durchgängen nach
 * Wichtigkeit: erst die Auswertungen, dann die Ergebnisse, zuletzt die
 * Spielberichtsbögen. Die sind je Spieler ein eigener Abruf und würden das
 * Zeitbudget sonst für ein einziges Turnier verbrauchen.
 *
 * Das Zeitbudget ist der Kern: Contao ruft den Cron im Web-Betrieb nach der
 * Auslieferung der Seite auf (kernel.terminate), die Laufzeitgrenze von PHP
 * gilt aber für den gesamten Aufruf. Was in einem Lauf nicht geschafft wird,
 * holt der nächste — bereits vorhandene Einträge werden übersprungen.
 */
class TurnierVorlader
{
	/**
	 * Zeitraum in Tagen, aus dem Turniere vorgeladen werden. Ältere Turniere
	 * werden kaum noch aufgerufen; wer sie doch anfordert, wartet einmalig.
	 */
	const TAGE = 30;

	/**
	 * Höchstes Zeitbudget in Sekunden. Ist die Laufzeit des Skripts begrenzt,
	 * fällt das tatsächliche Budget kleiner aus (siehe zeitbudget()).
	 */
	const ZEITBUDGET = 20;

	/**
	 * Wartezeit eines einzelnen Abrufs während des Laufs, in Sekunden. Der
	 * Cronjob setzt die Einstellung für sich herunter: Er hat es nicht eilig,
	 * darf aber an einer klemmenden Schnittstelle nicht die Laufzeitgrenze
	 * reißen. Eine höhere Einstellung wird gekürzt, eine niedrigere behalten.
	 */
	const TIMEOUT_ABRUF = 8;

	/**
	 * Restbudget in Sekunden, ab dem der dritte Durchgang (Spielberichtsbögen)
	 * überhaupt beginnt. Ein einzelner Bogen ist schnell, aber ein Turnier hat
	 * schnell hundert davon — ohne Puffer bricht der Durchgang sofort wieder ab.
	 */
	const RESTBUDGET_BOEGEN = 5;

	/**
	 * Startzeitpunkt des Laufs.
	 * @var float
	 */
	protected $start = 0.0;

	/**
	 * Zeitbudget dieses Laufs in Sekunden.
	 * @var int
	 */
	protected $budget = self::ZEITBUDGET;

	/**
	 * Führt den Lauf aus.
	 *
	 * Contao ruft die Methode über den Dienst-Tag contao.cronjob auf und
	 * übergibt den Bereich ('cli' oder 'web').
	 *
	 * @param  string $scope Aufrufbereich, wird nur protokolliert
	 * @return void
	 */
	public function __invoke($scope = 'cli')
	{
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_cron_aus'])) return;

		// Ohne erreichbare Schnittstelle gibt es nichts vorzuladen — und ein
		// Lauf ins Leere würde nur das Zeitbudget verbrennen
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_api_aus'])) return;
		if(!\Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::eingerichtet()) return;
		if(!\Schachbulle\ContaoWertungsportalBundle\Helper\API::cacheAktiv('Turnierauswertung')) return;

		$this->start = microtime(true);
		$this->budget = $this->zeitbudget();

		$turniere = $this->turniere();

		if(!count($turniere)) return;

		$zaehler = array('Turnierauswertung' => 0, 'Turnierergebnisse' => 0, 'Spielberichtsbogen' => 0);

		$timeoutAlt = $this->timeoutSetzen();

		try
		{
			// Durchgang 1 und 2: je Funktion einmal durch alle Turniere. Bewusst
			// nacheinander und nicht je Turnier beides — reicht das Budget nicht,
			// haben so mehr Turniere wenigstens ihre Auswertung
			foreach(array('Turnierauswertung', 'Turnierergebnisse') as $funktion)
			{
				foreach($turniere as $uuid)
				{
					if($this->budgetAus()) break 2;
					if($this->imCache($funktion, $uuid)) continue;

					$this->hole($funktion, array('funktion' => $funktion, 'cachekey' => $uuid, 'turnier' => $uuid));
					$zaehler[$funktion]++;
				}
			}

			// Durchgang 3: Spielberichtsbögen, nur mit ordentlichem Restbudget
			if(!$this->budgetAus(self::RESTBUDGET_BOEGEN))
			{
				$zaehler['Spielberichtsbogen'] = $this->boegen($turniere);
			}
		}
		finally
		{
			// Unbedingt zurücksetzen: Auf der Kommandozeile laufen nach diesem
			// Cronjob weitere, im Web-Betrieb ist der Aufruf Teil eines
			// gewöhnlichen Seitenaufrufs
			$this->timeoutSetzen($timeoutAlt);
		}

		$this->protokolliere($scope, $zaehler);
	}

	/**
	 * Ermittelt das Zeitbudget dieses Laufs.
	 *
	 * Maßgeblich ist die Laufzeitgrenze des Skripts. Nach dem letzten
	 * Budgettest läuft ein begonnener Abruf noch bis zu seiner Wartezeit
	 * weiter — im ungünstigsten Fall zweimal, weil bei abgelaufenem Token ein
	 * zweiter Aufruf für die Erneuerung dazukommt. Diese Zeit plus eine
	 * Sekunde Luft muss von der Grenze übrig bleiben, sonst wird der Lauf
	 * mitten im Schreiben eines Cache-Eintrags abgeschossen.
	 *
	 * @return int Budget in Sekunden, mindestens 5
	 */
	protected function zeitbudget()
	{
		$grenze = (int) ini_get('max_execution_time');

		// 0 heißt unbegrenzt — üblich auf der Kommandozeile
		if($grenze < 1) return self::ZEITBUDGET;

		return max(5, min(self::ZEITBUDGET, $grenze - 2 * self::TIMEOUT_ABRUF - 1));
	}

	/**
	 * Setzt die Wartezeit der Schnittstelle für die Dauer des Laufs herunter.
	 *
	 * Gearbeitet wird über die Konfiguration und nicht am OAuth2Client vorbei,
	 * damit der Abruf sonst genau derselbe bleibt wie im Frontend.
	 *
	 * @param  int|null|false $wert Beim Zurücksetzen der zuvor gelieferte
	 *                              Rückgabewert; false setzt die Wartezeit neu
	 * @return int|null             Vorheriger Wert (null, wenn nichts gesetzt war)
	 */
	protected function timeoutSetzen($wert = false)
	{
		$alt = $GLOBALS['TL_CONFIG']['wertungsportal_api_timeout'] ?? null;

		if($wert === false)
		{
			$GLOBALS['TL_CONFIG']['wertungsportal_api_timeout'] = min(\Schachbulle\ContaoWertungsportalBundle\Helper\API::timeout(), self::TIMEOUT_ABRUF);
		}
		elseif($wert === null)
		{
			unset($GLOBALS['TL_CONFIG']['wertungsportal_api_timeout']);
		}
		else
		{
			$GLOBALS['TL_CONFIG']['wertungsportal_api_timeout'] = $wert;
		}

		return $alt;
	}

	/**
	 * Liefert die UUIDs der Turniere, die vorgeladen werden sollen.
	 *
	 * Sortiert wird nach Auswertungsstand und Datum: Ein bereits gewertetes
	 * Turnier hat Daten, ein noch nicht gewertetes liefert nur eine
	 * Fehlanzeige. Innerhalb dessen zuerst die jüngsten — die werden am
	 * ehesten aufgerufen.
	 *
	 * @return array Liste der UUIDs
	 */
	protected function turniere()
	{
		$von = date('Y-m-d', time() - self::TAGE * 86400);

		try
		{
			$objTurniere = \Database::getInstance()
				->prepare("SELECT uuid FROM tl_wertungsportal_tournaments WHERE uuid != '' AND enddate >= ? AND enddate <= ? ORDER BY (ratingState = 'RATED') DESC, enddate DESC")
				->execute($von, date('Y-m-d'));
		}
		catch(\Throwable $e)
		{
			return array();
		}

		$uuids = array();
		while($objTurniere->next()) $uuids[] = (string) $objTurniere->uuid;

		return $uuids;
	}

	/**
	 * Lädt die fehlenden Spielberichtsbögen der Turniere.
	 *
	 * Die Spielerliste kommt aus der örtlichen Auswertungstabelle, kostet also
	 * keinen Abruf. Turniere ohne gespeicherte Auswertung werden übersprungen —
	 * für sie ist auch nicht bekannt, welche Bögen es überhaupt gibt.
	 *
	 * @param  array $turniere Liste der UUIDs
	 * @return int             Zahl der geholten Bögen
	 */
	protected function boegen($turniere)
	{
		$geholt = 0;

		foreach($turniere as $uuid)
		{
			if($this->budgetAus()) break;

			try
			{
				$objSpieler = \Database::getInstance()
					->prepare("SELECT e.playerUuid FROM tl_wertungsportal_tournaments_evaluation e INNER JOIN tl_wertungsportal_tournaments t ON t.id = e.pid WHERE t.uuid = ? AND e.playerUuid != ''")
					->execute($uuid);
			}
			catch(\Throwable $e)
			{
				continue;
			}

			while($objSpieler->next())
			{
				if($this->budgetAus()) break 2;

				$schluessel = $uuid.'-'.$objSpieler->playerUuid;

				if($this->imCache('Spielberichtsbogen', $schluessel)) continue;

				$this->hole('Spielberichtsbogen', array('funktion' => 'Spielberichtsbogen', 'cachekey' => $schluessel, 'turnier' => $uuid, 'id' => (string) $objSpieler->playerUuid));
				$geholt++;
			}
		}

		return $geholt;
	}

	/**
	 * Prüft, ob zu einer Funktion bereits ein Eintrag im Zwischenspeicher liegt.
	 *
	 * Geprüft wird EINSCHLIESSLICH abgelaufener Einträge: Vorgeladen wird nur,
	 * was fehlt. Einen vorhandenen Eintrag frischt der nächste Seitenaufruf
	 * ohnehin auf, und ein abgelaufener ist immer noch die Notreserve.
	 *
	 * @param  string $funktion   Name der Schnittstellenfunktion
	 * @param  string $schluessel Cache-Schlüssel
	 * @return bool
	 */
	protected function imCache($funktion, $schluessel)
	{
		try
		{
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => $schluessel, 'path' => 'wp_'.$funktion, 'extension' => '.cache'));

			return (bool) $cache->isCached($schluessel, true);
		}
		catch(\Throwable $e)
		{
			// Im Zweifel als vorhanden werten: lieber einen Abruf auslassen,
			// als bei einem Dateisystemproblem in einer Schleife zu hängen
			return true;
		}
	}

	/**
	 * Holt einen Eintrag über den normalen Weg und legt ihn damit ab.
	 *
	 * Bewusst über API::autoQuery und nicht über einen eigenen Abruf: So
	 * gelten dieselben Cachezeiten, dieselbe Statistikzählung und derselbe
	 * Abgleich mit den Spiegeltabellen wie im Frontend.
	 *
	 * @param  string $funktion Name der Schnittstellenfunktion
	 * @param  array  $params   Parameter für autoQuery
	 * @return void
	 */
	protected function hole($funktion, $params)
	{
		try
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($params);
		}
		catch(\Throwable $e)
		{
			// Ein einzelner Fehlschlag darf den Lauf nicht beenden
		}
	}

	/**
	 * Prüft, ob das Zeitbudget aufgebraucht ist.
	 *
	 * @param  int $puffer Zusätzlich freizuhaltende Sekunden
	 * @return bool
	 */
	protected function budgetAus($puffer = 0)
	{
		return (microtime(true) - $this->start) >= ($this->budget - $puffer);
	}

	/**
	 * Schreibt eine Zusammenfassung ins Systemprotokoll — aber nur, wenn
	 * tatsächlich etwas geholt wurde. Sonst stünde dort jeden Tag eine
	 * Nullmeldung.
	 *
	 * @param  string $scope   Aufrufbereich
	 * @param  array  $zaehler Funktion => Anzahl
	 * @return void
	 */
	protected function protokolliere($scope, $zaehler)
	{
		$summe = array_sum($zaehler);

		if($summe < 1) return;

		$teile = array();
		foreach($zaehler as $funktion => $anzahl)
		{
			if($anzahl > 0) $teile[] = $anzahl.'× '.$funktion;
		}

		try
		{
			\System::log(
				'Wertungsportal: '.$summe.' Turnierabrufe vorgeladen ('.implode(', ', $teile).', '.round(microtime(true) - $this->start, 1).' s von '.$this->budget.' s, '.$scope.')',
				__METHOD__,
				defined('TL_CRON') ? TL_CRON : 'CRON'
			);
		}
		catch(\Throwable $e)
		{
			// Protokoll ist Beiwerk
		}
	}
}
