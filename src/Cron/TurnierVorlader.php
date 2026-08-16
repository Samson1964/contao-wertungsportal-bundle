<?php

namespace Schachbulle\ContaoWertungsportalBundle\Cron;

/**
 * Nächtlicher Cronjob: lädt Turnierdaten der letzten Wochen in den
 * Zwischenspeicher, bevor der erste Besucher sie anfordert.
 *
 * Hintergrund: Auswertung, Ergebnisse und Spielberichtsbögen kamen nur zu
 * wenigen Prozent aus dem Zwischenspeicher — fast jeder Aufruf wartete also
 * auf die Schnittstelle. Vorgeladen wird in drei Durchgängen nach
 * Wichtigkeit: erst die Auswertungen, dann die Ergebnisse, zuletzt die
 * Spielberichtsbögen. Die sind je Spieler ein eigener Abruf und würden das
 * Zeitbudget sonst für ein einziges Turnier verbrauchen.
 *
 * **Ablauf einer Nacht** (Termin in der services.yml: alle 5 Minuten von 1
 * bis 3 Uhr): Der erste Lauf holt die Turnierliste des Zeitraums und beginnt
 * mit dem ersten Durchgang. Jeder folgende Lauf setzt die Arbeit fort — nicht
 * über eine gespeicherte Position, sondern weil bereits vorhandene Einträge
 * übersprungen werden. Das ist ohne Buchführung immer richtig, auch wenn ein
 * Lauf mittendrin abbricht oder ein Eintrag von Hand gelöscht wurde
 * (gemessen: 0,19 ms je übersprungenem Eintrag). Der Lauf um 3:00 ist der
 * letzte; die Termine danach ruhen bis zur nächsten Nacht, die mit einem
 * frischen Abruf der Turnierliste von vorn beginnt.
 *
 * Das Zeitbudget ist der Kern: Contao ruft den Cron im Web-Betrieb nach der
 * Auslieferung der Seite auf (kernel.terminate), die Laufzeitgrenze von PHP
 * gilt aber für den gesamten Aufruf. Was in einem Lauf nicht geschafft wird,
 * holt der nächste.
 */
class TurnierVorlader
{
	/**
	 * Zeitraum in Tagen, über den die Turnierliste der Nacht abgerufen wird.
	 *
	 * Das begrenzt NICHT, was vorgeladen wird — vorgeladen wird der gesamte
	 * örtliche Turnierbestand. Der Abruf dient allein dazu, neu angelegte
	 * Turniere kennenzulernen; die entstehen naturgemäß in den letzten Wochen.
	 * Ältere Turniere kommen über die Turniersuche der Besucher in den
	 * Bestand und werden von da an mit vorgeladen.
	 */
	const TAGE = 30;

	/**
	 * Stunde des Abschlusslaufs. Der Lauf zur vollen Stunde ist der letzte der
	 * Nacht; die Termine danach ruhen bis zum nächsten Abend.
	 *
	 * Muss zum Intervall in der services.yml passen (dort endet die
	 * Stundenspanne mit derselben Zahl).
	 */
	const STUNDE_ENDE = 3;

	/**
	 * Namensvorsatz des Schlüssels, unter dem die Turnierliste der Nacht im
	 * Zwischenspeicher liegt. Das angehängte Datum sorgt dafür, dass jede
	 * Nacht genau einmal frisch abgerufen wird.
	 */
	const LISTENSCHLUESSEL = 'vorlader-';

	/**
	 * Höchstes Zeitbudget in Sekunden. Ist die Laufzeit des Skripts begrenzt,
	 * fällt das tatsächliche Budget kleiner aus (siehe zeitbudget()) — der
	 * Wert wirkt sich also nur dort voll aus, wo keine Grenze gilt, praktisch
	 * auf der Kommandozeile.
	 *
	 * Bei 13 Terminen je Nacht bedeuten 300 Sekunden bis zu 65 Minuten
	 * Abrufzeit. Das ist Absicht: Die Schnittstellenlast soll möglichst
	 * vollständig in die Nacht wandern, damit tagsüber niemand mehr wartet.
	 *
	 * Zwischen zwei Läufen bleiben damit noch fünf Minuten Ruhe (Takt: alle
	 * zehn Minuten). Wer den Wert weiter anhebt, sollte den Takt im `interval`
	 * der services.yml mit vergrößern — sonst überholen sich die Läufe.
	 */
	const ZEITBUDGET = 300;

	/**
	 * Zahl aufeinanderfolgender Fehlschläge, nach der ein Lauf abbricht.
	 *
	 * Antwortet die Schnittstelle nicht mehr (Wartung, erschöpftes
	 * Token-Kontingent, HTTP 403/500), bringt Weitermachen nichts: Der Lauf
	 * würde sein ganzes Budget gegen die Wand rennen und nebenbei die
	 * Statistik mit Fehlversuchen fluten. Ein einzelner Fehlschlag ist dagegen
	 * normal — nicht jedes Turnier hat zu jeder Funktion Daten.
	 */
	const FEHLSCHLAEGE_MAX = 5;

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
	 * Fehlschläge dieses Laufs insgesamt (für das Protokoll).
	 * @var int
	 */
	protected $fehlschlaege = 0;

	/**
	 * Fehlschläge in ununterbrochener Folge (für den Abbruch).
	 * @var int
	 */
	protected $fehlschlaegeInFolge = 0;

	/**
	 * Meldung des zuletzt gescheiterten Abrufs, für das Protokoll.
	 * @var string
	 */
	protected $letzterFehler = '';

	/**
	 * Beobachter, der über jeden einzelnen Abruf unterrichtet wird.
	 *
	 * Der Cronjob setzt keinen — er protokolliert nur die Zusammenfassung.
	 * Der Befehl `wertungsportal:vorladen` hängt sich hier ein, damit auf der
	 * Kommandozeile zu sehen ist, was gerade passiert und woran es hakt.
	 * Aufgerufen mit (Funktion, Schlüssel, Erfolg, Fehlertext).
	 *
	 * @var callable|null
	 */
	protected $melder;

	/**
	 * Abweichendes Zeitbudget in Sekunden; 0 = ZEITBUDGET.
	 * @var int
	 */
	protected $budgetWunsch = 0;

	/**
	 * Ausdrücklich von Hand angestoßen — dann gilt die Ruhezeit nicht.
	 * @var bool
	 */
	protected $aufAbruf = false;

	/**
	 * Hängt einen Beobachter ein, der über jeden Abruf unterrichtet wird.
	 *
	 * Gedacht für den Kommandozeilenbefehl: Der Cronjob schweigt bis zur
	 * Zusammenfassung, von Hand will man dagegen zusehen können.
	 *
	 * @param  callable|null $melder Funktion(string $funktion, string $schluessel, bool $erfolg, string $fehler)
	 * @return $this
	 */
	public function setMelder($melder = null)
	{
		$this->melder = \is_callable($melder) ? $melder : null;

		return $this;
	}

	/**
	 * Setzt ein abweichendes Zeitbudget für diesen Lauf.
	 *
	 * Die Deckelung durch die Laufzeitgrenze des Skripts bleibt bestehen — ein
	 * Wunsch von einer Stunde nützt nichts, wenn der Hoster nach 30 Sekunden
	 * abschaltet.
	 *
	 * @param  int $sekunden 0 = Vorgabe der Klasse (ZEITBUDGET)
	 * @return $this
	 */
	public function setBudget($sekunden)
	{
		$this->budgetWunsch = max(0, (int) $sekunden);

		return $this;
	}

	/**
	 * Meldet den Lauf als von Hand angestoßen an.
	 *
	 * Damit entfällt die Ruhezeit nach dem Abschlusslauf: Wer den Befehl
	 * ausdrücklich eintippt, will nicht mit „ist gerade Feierabend" abgewiesen
	 * werden. Die übrigen Sperren (Vorladen abgeschaltet, Schnittstelle aus,
	 * keine Zugangsdaten) gelten weiter — sie sind bewusste Einstellungen.
	 *
	 * @param  bool $an
	 * @return $this
	 */
	public function setAufAbruf($an = true)
	{
		$this->aufAbruf = (bool) $an;

		return $this;
	}

	/**
	 * Verzeichnis des Zwischenspeichers je Funktion (false = eigene
	 * Pfadberechnung nicht verwendbar, siehe cachepfad()).
	 * @var array
	 */
	protected $pfade = array();

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
		if(!$this->aufAbruf && $this->feierabend()) return;

		$this->start = microtime(true);
		$this->budget = $this->zeitbudget();

		$timeoutAlt = $this->timeoutSetzen();

		// Abrufe dieses Laufs zählen als eigene Quelle, sonst überdeckt der
		// Vorlader in der Statistik die Abrufe der Besucher
		\Schachbulle\ContaoWertungsportalBundle\Helper\API::vorladen(true);

		try
		{
			$zaehler = $this->durchgaenge();
		}
		finally
		{
			// Unbedingt zurücksetzen: Auf der Kommandozeile laufen nach diesem
			// Cronjob weitere, im Web-Betrieb ist der Aufruf Teil eines
			// gewöhnlichen Seitenaufrufs
			$this->timeoutSetzen($timeoutAlt);
			\Schachbulle\ContaoWertungsportalBundle\Helper\API::vorladen(false);
		}

		$this->protokolliere($scope, $zaehler);
	}

	/**
	 * Meldet, ob dieser Lauf in die Ruhezeit nach dem Abschlusslauf fällt.
	 *
	 * Der Lauf um STUNDE_ENDE:00 ist der letzte der Nacht; die Termine
	 * derselben Stunde danach (:05 bis :55) sollen nichts mehr tun.
	 *
	 * **Bewusst nur diese eine Stunde und keine allgemeine Zeitfensterprüfung:**
	 * Im Web-Betrieb löst nicht die Uhr den Cronjob aus, sondern ein
	 * Seitenaufruf. Auf einer nachts stillen Website kommt der um 1:00 fällige
	 * Termin erst morgens dran. Eine Prüfung „nur zwischen 1 und 3 Uhr" würde
	 * dann für immer alles abweisen — der Vorlader liefe nie. So arbeitet ein
	 * verspäteter Lauf ganz normal.
	 *
	 * @param  int|null $zeitpunkt Vergleichszeitpunkt, sonst jetzt (für Tests)
	 * @return bool
	 */
	protected function feierabend($zeitpunkt = null)
	{
		$zeitpunkt = $zeitpunkt ?? time();

		return self::STUNDE_ENDE === (int) date('G', $zeitpunkt) && (int) date('i', $zeitpunkt) > 0;
	}

	/**
	 * Arbeitet die drei Durchgänge ab, soweit das Zeitbudget reicht.
	 *
	 * @return array Funktion => Zahl der geholten Einträge
	 */
	protected function durchgaenge()
	{
		$zaehler = array('Turnierauswertung' => 0, 'Turnierergebnisse' => 0, 'Karteikarte' => 0, 'Karteikarte_Turniere' => 0, 'Spielberichtsbogen' => 0);

		$turniere = $this->turniere();

		// Durchgang 1 und 2: je Funktion einmal durch alle Turniere. Bewusst
		// nacheinander und nicht je Turnier beides — reicht das Budget nicht,
		// haben so mehr Turniere wenigstens ihre Auswertung
		foreach(array('Turnierauswertung', 'Turnierergebnisse') as $funktion)
		{
			foreach($turniere as $uuid)
			{
				if($this->budgetAus() || $this->abbruch()) break 2;
				if($this->imCache($funktion, $uuid)) continue;

				if($this->hole($funktion, array('funktion' => $funktion, 'cachekey' => $uuid, 'turnier' => $uuid))) $zaehler[$funktion]++;
			}
		}

		// Durchgang 3 und 4: Karteikarten und deren Turnierhistorie.
		//
		// Vor den Spielberichtsbögen, weil eine Karteikarte weit häufiger
		// aufgerufen wird als ein einzelner Bogen — und weil es von den Bögen
		// ein Vielfaches gibt. Die Personen werden erst hier gelesen: Reicht
		// das Budget nicht einmal für die Turniere, wäre die Abfrage umsonst
		if(!$this->budgetAus() && !$this->abbruch())
		{
			$personen = $this->personen();

			foreach(array('Karteikarte', 'Karteikarte_Turniere') as $funktion)
			{
				foreach($personen as $nuid)
				{
					if($this->budgetAus() || $this->abbruch()) break 2;
					if($this->imCache($funktion, $nuid)) continue;

					if($this->hole($funktion, array('funktion' => $funktion, 'cachekey' => $nuid, 'id' => $nuid))) $zaehler[$funktion]++;
				}
			}
		}

		// Durchgang 5: Spielberichtsbögen, nur mit ordentlichem Restbudget
		if(!$this->budgetAus(self::RESTBUDGET_BOEGEN) && !$this->abbruch())
		{
			$zaehler['Spielberichtsbogen'] = $this->boegen($turniere);
		}

		return $zaehler;
	}

	/**
	 * Liefert die nu-Nummern der Personen, deren Karteikarte vorgeladen wird.
	 *
	 * Gesperrte Personen (Blacklist) bleiben außen vor: Ihre Karteikarte
	 * zeigt das Frontend ohnehin nicht an — der Abruf wäre verschwendet, und
	 * die Daten von jemandem, der der Veröffentlichung widersprochen hat,
	 * hätten im Zwischenspeicher nichts zu suchen.
	 *
	 * Sortiert nach DWZ absteigend: Ein vollständiger Durchgang über den
	 * ganzen Bestand dauert mehrere Nächte, und die stärksten Spieler werden
	 * am häufigsten nachgeschlagen.
	 *
	 * @return array Liste der nu-Nummern
	 */
	protected function personen()
	{
		try
		{
			$objPersonen = \Database::getInstance()
				->prepare("SELECT nuLigaPersonId FROM tl_wertungsportal_persons WHERE published = '1' AND nuLigaPersonId != '' AND blocked != '1' ORDER BY rating DESC, id");
			$objPersonen = $objPersonen->execute();
		}
		catch(\Throwable $e)
		{
			return array();
		}

		$nuids = array();
		while($objPersonen->next()) $nuids[] = (string) $objPersonen->nuLigaPersonId;

		return $nuids;
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
		$grenze = $this->laufzeitgrenze();

		// Steht die Laufzeitgrenze dem vollen Budget im Weg, erst einmal
		// höflich nachfragen. Auf der Kommandozeile geht das durch; im
		// Web-Betrieb und bei gesperrter Funktion bleibt sie stehen, und das
		// Budget fällt weiter unten entsprechend kleiner aus.
		//
		// Ohne diesen Versuch nützte ein hohes ZEITBUDGET nichts: Ein echter
		// Hoster-Cronjob läuft zwar über die Kommandozeile, viele php-cli.ini
		// setzen dort aber trotzdem 30 Sekunden — der Lauf bekäme dann nur
		// 13 Sekunden, obwohl 120 eingestellt sind
		// Von Hand darf ein anderes Budget gewünscht werden (Befehl
		// wertungsportal:vorladen --budget); der Cronjob nimmt die Vorgabe
		$wunsch = $this->budgetWunsch > 0 ? $this->budgetWunsch : self::ZEITBUDGET;

		$noetig = $wunsch + 2 * self::TIMEOUT_ABRUF + 1;

		if($grenze > 0 && $grenze < $noetig)
		{
			@set_time_limit($noetig);
			$grenze = $this->laufzeitgrenze();
		}

		// 0 heißt unbegrenzt — üblich auf der Kommandozeile
		if($grenze < 1) return $wunsch;

		return max(5, min($wunsch, $grenze - 2 * self::TIMEOUT_ABRUF - 1));
	}

	/**
	 * Liefert die geltende Laufzeitgrenze in Sekunden (0 = unbegrenzt).
	 *
	 * Eigene Methode, damit der Prüfstand den Fall nachstellen kann, dass ein
	 * Hoster das Anheben verbietet — dann bleibt die Grenze stehen und das
	 * Budget muss sich danach richten.
	 *
	 * @return int
	 */
	protected function laufzeitgrenze()
	{
		return (int) ini_get('max_execution_time');
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

		$this->turnierlisteHolen($von);

		try
		{
			// Ohne Zeitfenster: Vorgeladen wird der GESAMTE örtliche Bestand.
			// Die Reihenfolge sorgt dafür, daß die lohnenden zuerst drankommen
			$objTurniere = \Database::getInstance()
				->prepare("SELECT uuid FROM tl_wertungsportal_tournaments WHERE uuid != '' ORDER BY (ratingState = 'RATED') DESC, enddate DESC")
				->execute();
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
	 * Gleicht die Turnierliste des Zeitraums einmal je Nacht mit der
	 * Schnittstelle ab.
	 *
	 * Ohne diesen Abruf kennt der örtliche Bestand nur die Turniere, nach
	 * denen zufällig jemand gesucht hat — vorgeladen würde dann nur ein
	 * Ausschnitt. Der Abruf zieht über den Abgleich in API::getAPI() alles
	 * Fehlende in tl_wertungsportal_tournaments nach.
	 *
	 * **Genau ein Abruf je Nacht, ohne eigene Buchführung:** Der
	 * Cache-Schlüssel enthält das Datum. Der erste Lauf der Nacht geht an die
	 * Schnittstelle, die folgenden bekommen die Antwort aus dem
	 * Zwischenspeicher (und lösen dort keinen erneuten Abgleich aus). Am
	 * nächsten Tag lautet der Schlüssel anders — also wird frisch geholt.
	 *
	 * @param  string $von Frühestes Enddatum (JJJJ-MM-TT)
	 * @return void
	 */
	protected function turnierlisteHolen($von)
	{
		try
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery(array
			(
				'funktion' => 'Turnierliste',
				'cachekey' => self::LISTENSCHLUESSEL.date('Y-m-d'),
				'suche'    => '',
				'zps'      => '',
				'von'      => $von,
				'bis'      => date('Y-m-d'),
			));
		}
		catch(\Throwable $e)
		{
			// Klemmt der Abruf, wird mit dem gearbeitet, was örtlich vorliegt
		}
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
			if($this->budgetAus() || $this->abbruch()) break;

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
				if($this->budgetAus() || $this->abbruch()) break 2;

				$schluessel = $uuid.'-'.$objSpieler->playerUuid;

				if($this->imCache('Spielberichtsbogen', $schluessel)) continue;

				if($this->hole('Spielberichtsbogen', array('funktion' => 'Spielberichtsbogen', 'cachekey' => $schluessel, 'turnier' => $uuid, 'id' => (string) $objSpieler->playerUuid))) $geholt++;
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
			$pfad = $this->cachepfad($funktion, $schluessel);

			// Der schnelle Weg: nur nachsehen, ob die Datei da ist.
			// isCached() liest und entpackt dafür den ganzen Eintrag — bei
			// gemessenen 0,19 ms je vorhandenem Eintrag und 200.000 Einträgen
			// gingen 38 der 180 Sekunden allein fürs Überspringen drauf.
			// Der Dateitest kostet 0,014 ms.
			//
			// Inhaltlich ist beides gleichwertig: Gefragt ist „liegt etwas
			// vor", NICHT „ist es noch gültig" — ein abgelaufener Eintrag ist
			// die Notreserve und wird bewusst nicht ersetzt
			if($pfad !== null) return is_file($pfad);

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
	 * Baut den Dateipfad eines Cache-Eintrags selbst.
	 *
	 * Das Helper-Bundle legt je Eintrag eine Datei ab, deren Name der
	 * SHA1-Wert des bereinigten Schlüssels ist (kleingeschrieben, alles außer
	 * Ziffern, Buchstaben, Punkt, Unterstrich und Bindestrich entfernt). Diese
	 * Regel wird hier nachgebildet, um nicht für jeden der zehntausenden
	 * Schlüssel ein Cache-Objekt bauen zu müssen.
	 *
	 * **Selbstprüfung:** Einmal je Funktion wird der eigene Pfad gegen den des
	 * Helper-Bundles gehalten. Weichen sie ab — etwa weil dort die Regel
	 * geändert wurde —, wird der eigene Weg NICHT verwendet. Ohne diese
	 * Prüfung hielte der Vorlader jeden Eintrag für fehlend und holte jede
	 * Nacht den gesamten Bestand neu, ohne daß es jemandem auffiele.
	 *
	 * @param  string $funktion   Name der Schnittstellenfunktion
	 * @param  string $schluessel Cache-Schlüssel
	 * @return string|null        Pfad, oder null wenn die Regel nicht paßt
	 */
	protected function cachepfad($funktion, $schluessel)
	{
		if(!array_key_exists($funktion, $this->pfade))
		{
			$probe = 'vorlader-pruefschluessel';
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => $probe, 'path' => 'wp_'.$funktion, 'extension' => '.cache'));

			$muster = (string) $cache->getCacheDir();
			$eigen = dirname($muster).'/'.sha1($probe).'.cache';

			$this->pfade[$funktion] = ($muster === $eigen) ? dirname($muster).'/' : false;
		}

		if($this->pfade[$funktion] === false) return null;

		return $this->pfade[$funktion].sha1(preg_replace('/[^0-9a-z\.\_\-]/i', '', strtolower((string) $schluessel))).'.cache';
	}

	/**
	 * Holt einen Eintrag über den normalen Weg und legt ihn damit ab.
	 *
	 * Bewusst über API::autoQuery und nicht über einen eigenen Abruf: So
	 * gelten dieselben Cachezeiten, dieselbe Statistikzählung und derselbe
	 * Abgleich mit den Spiegeltabellen wie im Frontend.
	 *
	 * **Maßstab für den Erfolg ist nicht die Antwort, sondern was von ihr
	 * gespeichert wurde:** Fehlgeschlagene Abrufe legt autoQuery bewusst nicht
	 * ab. Wer nur die Versuche zählt, meldet auch dann Vollzug, wenn die
	 * Schnittstelle durchgehend mit HTTP 403 antwortet — und genau als
	 * Nachweis, dass das Vorladen wirkt, ist die Zählung gedacht.
	 *
	 * @param  string $funktion Name der Schnittstellenfunktion
	 * @param  array  $params   Parameter für autoQuery
	 * @return bool             Ob der Eintrag jetzt im Zwischenspeicher liegt
	 */
	protected function hole($funktion, $params)
	{
		$antwort = null;

		try
		{
			$antwort = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($params);
		}
		catch(\Throwable $e)
		{
			// Ein einzelner Fehlschlag darf den Lauf nicht beenden
			$this->letzterFehler = $funktion.': '.$e->getMessage();
		}

		$erfolg = $this->imCache($funktion, $params['cachekey']);

		if($erfolg)
		{
			$this->fehlschlaegeInFolge = 0;
		}
		else
		{
			$this->fehlschlaege++;
			$this->fehlschlaegeInFolge++;

			// Grund festhalten, solange er noch vorliegt — die Zählung allein
			// sagt nicht, ob die Schnittstelle den Zugang verweigert, eine
			// Störung hat oder das Turnier schlicht keine Daten führt.
			//
			// Vorrang hat die gemeldete Störung: Bei einem Ausfall kommt die
			// Antwort aus dem örtlichen Bestand und trägt nur noch „Abruf
			// z.Z. nicht möglich" — der eigentliche Grund steckt in der Störung
			$stoerung = \Schachbulle\ContaoWertungsportalBundle\Helper\API::letzteStoerung();

			if($stoerung !== '')
			{
				$this->letzterFehler = $funktion.' — '.$stoerung;
			}
			elseif(is_array($antwort) && !empty($antwort['error']))
			{
				$code = (int) ($antwort['http_code'] ?? 0);
				$this->letzterFehler = $funktion.' HTTP '.$code.' — '.trim((string) ($antwort['error_message'] ?? 'ohne Meldung'));
			}
			else
			{
				// Kein Fehler in der Antwort, trotzdem nichts abgelegt: Das ist
				// der Fall „nicht zwischenspeicherbar" (Cachezeit 0 für diese
				// Funktion). Ohne diesen Zweig bliebe die Meldung von vorhin
				// stehen und zeigte auf die falsche Ursache
				$this->letzterFehler = $funktion.' — Antwort kam an, wurde aber nicht abgelegt (Cachezeit 0?)';
			}
		}

		// Beobachter unterrichten (Kommandozeile); der Cronjob setzt keinen
		if($this->melder !== null)
		{
			\call_user_func($this->melder, $funktion, (string) $params['cachekey'], $erfolg, $erfolg ? '' : $this->letzterFehler);
		}

		return $erfolg;
	}

	/**
	 * Meldet, ob der Lauf wegen anhaltender Fehlschläge abbrechen soll.
	 *
	 * @return bool
	 */
	protected function abbruch()
	{
		return $this->fehlschlaegeInFolge >= self::FEHLSCHLAEGE_MAX;
	}

	/**
	 * Meldet nach dem Lauf, ob er wegen anhaltender Fehlschläge abgebrochen ist.
	 *
	 * Für den Kommandozeilenbefehl, der daraus seinen Rückgabewert bildet —
	 * damit ein Skript den Unterschied zwischen „fertig" und „aufgegeben"
	 * erkennt, ohne ihn aus Laufzeit und Fehlerzahl zu erraten.
	 *
	 * @return bool
	 */
	public function abgebrochen()
	{
		return $this->abbruch();
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

		// Ein Lauf ohne Fehlschläge, der nichts zu tun fand, schweigt. Sobald
		// aber etwas schiefging, gehört das ins Protokoll — auch (und gerade)
		// wenn NICHTS geholt werden konnte
		if($summe < 1 && $this->fehlschlaege < 1) return;

		$teile = array();
		foreach($zaehler as $funktion => $anzahl)
		{
			if($anzahl > 0) $teile[] = $anzahl.'× '.$funktion;
		}

		if($this->fehlschlaege > 0)
		{
			$teile[] = $this->fehlschlaege.' Fehlschläge';
			if($this->abbruch()) $teile[] = 'Lauf abgebrochen';

			// Ohne den Grund ist die Meldung wertlos. Am 11.08.2026 stand hier
			// anderthalb Tage lang „5 Fehlschläge, Lauf abgebrochen" — und
			// nirgends, dass die Schnittstelle das Zugangstoken verweigerte
			if($this->letzterFehler !== '') $teile[] = 'zuletzt: '.$this->letzterFehler;
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
