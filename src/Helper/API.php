<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// API-Dokumentation: https://schachde-appsdemo.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html
// API-Dokumentation: https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html

class API
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	protected static $instance = null;
	var $Fragmente;

	/**
	 * Ablaufzeitpunkte der Abfragen, die in diesem Seitenaufruf aus dem
	 * Zwischenspeicher beantwortet wurden. Daraus baut Helper::cacheHinweis()
	 * den Hinweis in der Ausgabe — die Module müssen dafür nichts durchreichen.
	 */
	protected static $cacheTreffer = array();

	/**
	 * Speicherzeitpunkte der Notdaten dieses Seitenaufrufs, also der
	 * abgelaufenen Cache-Einträge, die mangels erreichbarer Schnittstelle
	 * trotzdem ausgeliefert wurden. Ist das Array nicht leer, weist
	 * Helper::cacheHinweis() darauf hin.
	 */
	protected static $notdaten = array();

	/**
	 * Merker, ob der Grund einer gescheiterten Verbindung in diesem
	 * Seitenaufruf schon im Systemprotokoll steht. Verhindert, dass eine
	 * Störung so viele Protokollzeilen erzeugt, wie die Seite Abfragen hat.
	 */
	protected static $stoerungGemeldet = false;

	/**
	 * Grund der zuletzt gescheiterten Verbindung im Klartext.
	 * @var string
	 */
	protected static $letzteStoerung = '';

	/**
	 * Liefert den Grund der zuletzt gescheiterten Verbindung.
	 *
	 * Wird vom Vorlader für seine Protokollzeile gelesen: Nach einem Ausfall
	 * kommt die Antwort aus dem örtlichen Bestand und trägt nur noch „Der
	 * Abruf von Live-Daten ist z.Z. nicht möglich" — was die Ursache verschweigt.
	 *
	 * @return string Leer, wenn in diesem Aufruf keine Störung auftrat
	 */
	public static function letzteStoerung()
	{
		return self::$letzteStoerung;
	}

	/**
	 * Speicherzeitpunkte der in diesem Seitenaufruf aus dem Zwischenspeicher
	 * gelesenen Einträge. Daraus nennt der Hinweis, von wann die angezeigten
	 * Daten sind — nicht nur, bis wann sie gelten.
	 */
	protected static $cacheStand = array();

	/**
	 * Dasselbe für Abfragen, die in diesem Seitenaufruf FRISCH von der
	 * Schnittstelle kamen und dabei abgelegt wurden: Zeitpunkt des Abrufs und
	 * Ablauf des neuen Eintrags.
	 *
	 * Der Hinweis nennt den Stand auch für frische Daten — sonst stünde die
	 * Angabe nur auf manchen Seiten und sähe aus wie ein Mangel.
	 */
	protected static $frischStand = array();
	protected static $frischTreffer = array();

	/**
	 * Meldung, die angezeigt wird, wenn die Schnittstelle nicht zur
	 * Verfügung steht — abgeschaltet oder ohne Antwort.
	 */
	const MELDUNG_KEINE_LIVEDATEN = 'Der Abruf von Live-Daten ist z.Z. nicht möglich.';

	/**
	 * Voreingestellte Wartezeit eines Abrufs in Sekunden, falls in den
	 * Einstellungen nichts gewählt ist.
	 */
	const TIMEOUT_STANDARD = 30;

	/**
	 * Frist in Sekunden, für die ein Notdatensatz nach einem gescheiterten
	 * Abruf wieder als gültig gilt. Ohne sie liefe JEDER Seitenaufruf erneut
	 * in die volle Wartezeit — bei 30 Sekunden Timeout wäre die Website
	 * praktisch unbenutzbar, solange die Schnittstelle klemmt. Nach Ablauf
	 * der Frist wird die Schnittstelle wieder versucht.
	 */
	const NOTFRIST = 300;

	/**
	 * Kennzeichen für „läuft nie ab" in den Cachezeit-Einstellungen. 0 ist
	 * dafür nicht verwendbar: In den Einstellungen bedeutet 0 „gar nicht
	 * cachen". Beim Speichern wird daraus die 0, die die Cache-Klasse als
	 * „ohne Ablauf" versteht.
	 */
	const CACHE_UNBEGRENZT = -1;

	/**
	 * Frist in Sekunden, für die eine 404-Antwort gemerkt wird („gibt es
	 * nicht"). Ohne sie holt JEDER Besucher dieselbe Fehlanzeige einzeln bei
	 * der Schnittstelle ab: Im Zugriffs-Log vom 30.07.2026 fragten sechs
	 * verschiedene Besucher binnen drei Minuten dasselbe Turnier ohne
	 * Auswertung ab. Bewusst kurz, denn eine Auswertung kann jederzeit
	 * nachgereicht werden — anders als bei einer erfolgreichen Antwort soll
	 * hier zügig wieder nachgefragt werden.
	 */
	const NEGATIVFRIST = 600;

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct()
	{
		$this->Fragmente = '';
	}

	/**
	 * Return the current object instance (Singleton)
	 * @return BannerCheckHelper
	 */
	public static function getInstance()
	{
		if(self::$instance === null)
		{
			self::$instance = new \Schachbulle\ContaoWertungsportalBundle\Helper\API();
		}

		return self::$instance;
	}

	/*********************************************************
	 * autoQuery
	 * =========
	 * Vollautomatisierte Abfrage des Wertungsportals inkl. Cachenutzung
	 *
	 * @param       Array mit den Parametern
	 * $param = array
	 * (
	 * 	"funktion" => "Spielerliste", // Funktion/Cachename
	 * 	"cachekey" => "Cacheschlüssel", // Name des Datensatzes im Cache
	 * 	"vorname"  => $vorname, // definierbar anhand Wertungsportal-Funktion
	 * );
	 * @return      Array mit den Rückgabewerten
	*/
	public static function autoQuery($params)
	{
		// Ohne eingeschaltetes Zugriffs-Log ohne jeden Zusatzaufwand weiter
		if(!\Schachbulle\ContaoWertungsportalBundle\Helper\Zugriffslog::aktiv())
		{
			return static::autoQueryIntern($params);
		}

		// Zeit über die GANZE Abfrage nehmen (Zwischenspeicher, Schnittstelle
		// samt Abgleich mit der Datenbank oder örtliche Abfrage) und zusätzlich
		// die reine Dauer des Schnittstellenaufrufs, sofern einer stattfand
		$aufrufeVorher = \Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::aufrufe();
		$start = microtime(true);

		$result = static::autoQueryIntern($params);

		$angaben = array
		(
			'quelle' => self::quelleVon($result),
			'dauer'  => (microtime(true) - $start) * 1000,
			'http'   => is_array($result) ? ($result['http_code'] ?? '') : '',
			'anzahl' => \Schachbulle\ContaoWertungsportalBundle\Helper\Zugriffslog::anzahl($result),
		);

		if(\Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::aufrufe() > $aufrufeVorher)
		{
			$angaben['dauer_api'] = \Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::dauer();
		}

		\Schachbulle\ContaoWertungsportalBundle\Helper\Zugriffslog::schreibe($params, $angaben);

		return $result;
	}

	/**
	 * Benennt die Quelle einer Antwort für das Zugriffs-Log.
	 *
	 * @param  mixed $result Antwort von autoQueryIntern
	 * @return string        api|cache|notdaten|lokal|gesperrt|fehler
	 */
	protected static function quelleVon($result)
	{
		if(!is_array($result)) return 'fehler';
		if(!empty($result['lokalquelle'])) return 'lokal';
		if(!empty($result['notstand'])) return 'notdaten';
		if(!empty($result['cachequelle'])) return 'cache';
		if(!empty($result['keine_livedaten'])) return 'gesperrt';
		if(!empty($result['error'])) return 'fehler';

		return 'api';
	}

	/**
	 * Die eigentliche Abfrage (siehe autoQuery).
	 */
	protected static function autoQueryIntern($params)
	{
		// ======================================================================
		// Massenabfragen bremsen — VOR allem anderen, damit ein gebremster
		// Besucher weder die Schnittstelle noch den Zwischenspeicher noch die
		// örtliche Datenbank beschäftigt. Der Vorlade-Cronjob ist ausgenommen:
		// Er ist kein Besucher und hat keine sinnvolle IP-Adresse
		// ======================================================================
		if(!self::$vorladen && \Schachbulle\ContaoWertungsportalBundle\Helper\Besucherbremse::gesperrt())
		{
			return array
			(
				'error'         => true,
				'error_message' => \Schachbulle\ContaoWertungsportalBundle\Helper\Besucherbremse::MELDUNG,
				'http_code'     => 429,
				'gebremst'      => true,
			);
		}

		// ======================================================================
		// Wenn Cache aktiviert, dann Daten laden, wenn vorhanden
		// ======================================================================
		$cachetime = self::cachezeit($params['funktion']);
		$cache = null;

		if(self::cacheAktiv($params['funktion']))
		{
			// Cache initialisieren: eine Datei je Eintrag in einem eigenen
			// Verzeichnis der Funktion. Früher lagen alle Einträge einer
			// Funktion in EINER Datei — die wuchs mit jeder gecachten Liste
			// (Vereinslisten mit hunderten Spielern!) und musste bei jedem
			// Zugriff komplett gelesen, dekodiert und neu geschrieben werden
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => $params['cachekey'], 'path' => 'wp_'.$params['funktion'], 'extension' => '.cache'));

			// KEIN eraseExpired() mehr an dieser Stelle: Abgelaufene Einträge
			// sind die Notreserve, wenn die Schnittstelle nicht antwortet
			// (siehe notdaten()). Nötig war der Aufruf ohnehin nicht mehr —
			// isCached() und retrieve() prüfen den Ablauf seit Helper-Bundle
			// 1.8.8 selbst. Je Schlüssel liegt genau ein Eintrag in genau
			// einer Datei, der Cache wächst dadurch also nicht an; aufgeräumt
			// wird über „Cache leeren" im Backend
			if($cache->isCached($params['cachekey']) && !isset($params['nocache']))
			{
				return self::ausCache($cache, $params);
			}
		}

		// Live-Abruf in den Einstellungen abgeschaltet oder die Zugangsdaten
		// noch nicht gepflegt: gar nicht erst verbinden, sondern direkt auf
		// die Notreserve zurückgreifen. Eine frisch installierte Erweiterung
		// verhält sich damit wie eine mit gestörter Schnittstelle — sie zeigt
		// den örtlichen Bestand und einen Hinweis, statt in einen Fehler zu
		// laufen
		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_api_aus']) || !\Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::eingerichtet())
		{
			return self::notdaten($cache, $params, false);
		}

		// Echten Abruf bei der Schnittstelle für die Statistik zählen
		self::zaehleAbruf($params['funktion'], \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::QUELLE_API);

		// static:: statt self::, damit der Schnittstellenaufruf in einer
		// abgeleiteten Klasse ersetzbar bleibt (Prüfstand); in Produktion
		// gibt es keine Ableitung, das Verhalten ist identisch
		$result = static::getAPI($params);

		// Gar keine Antwort (Wartezeit abgelaufen, Verbindung gescheitert,
		// Namensauflösung fehlgeschlagen — alles HTTP-Code 0): Notreserve.
		// Fehlermeldungen MIT HTTP-Code bleiben unberührt, ein „Person not
		// found" ist eine gültige Antwort und darf keine alten Daten wecken.
		//
		// Ein gescheiterter TOKENABRUF zählt ausdrücklich mit dazu, obwohl er
		// einen HTTP-Code trägt (403). Er sagt nichts über die angefragten
		// Daten aus, sondern nur, dass der Zugang zur Schnittstelle gerade
		// nicht zu haben ist — und dann sind alte Daten mit Hinweis für den
		// Besucher allemal besser als eine Fehlermeldung. Gefunden am
		// 11.08.2026, als das Tokenkontingent bei nu erschöpft war und
		// sämtliche Turnierseiten statt der Daten einen Fehler zeigten
		if(!empty($result['error']) && (0 === (int) ($result['http_code'] ?? 0) || !empty($result['tokenfehler'])))
		{
			self::meldeStoerung($result);

			return self::notdaten($cache, $params, true);
		}

		if($result['http_code'] == '200')
		{
			// FIDE-Daten hinzuladen
			$result = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::setFIDEDaten($result, $params);

			// Im Cache speichern. Die Gültigkeit steht erst hier fest: Bei den
			// Turnierfunktionen hängt sie am Alter des Turniers, das aus der
			// Antwort hervorgeht (siehe cachezeitFuerAntwort). 0 bedeutet
			// „nicht cachen", CACHE_UNBEGRENZT wird zur 0 der Cache-Klasse,
			// die dort für „ohne Ablauf" steht
			$speicherzeit = self::cachezeitFuerAntwort($params, $result, $cachetime);

			if($cache !== null && $speicherzeit != 0)
			{
				$cache->store($params['cachekey'], $result, $speicherzeit > 0 ? $speicherzeit : 0);

				// Stand und Ablauf ERST NACH dem Ablegen vermerken, sonst
				// wanderten sie mit in den gespeicherten Datensatz und der
				// Hinweis nennte später den Zeitpunkt von damals
				$jetzt = time();
				$ablauf = $speicherzeit > 0 ? $jetzt + $speicherzeit : null;

				self::$frischStand[] = $jetzt;
				self::$frischTreffer[] = $ablauf;

				$result['cachestand'] = $jetzt;
				$result['cacheablauf'] = $ablauf;
			}
		}
		elseif($cache !== null && 404 === (int) $result['http_code'])
		{
			// „Gibt es nicht" kurz merken, damit nicht jeder Besucher dieselbe
			// Fehlanzeige einzeln bei der Schnittstelle abholt. Nur 404: Ein
			// 401/403 ist ein Zugangsproblem und ein 5xx eine Störung — beides
			// darf sich nicht festsetzen. Der Vermerk „negativ" sorgt dafür,
			// dass der Eintrag NICHT als Notreserve herhält (siehe notdaten)
			$result['negativ'] = true;
			$cache->store($params['cachekey'], $result, self::NEGATIVFRIST);
		}

		// Platzhalter-Mitgliedsnummern (0000) aus allen Ausgaben entfernen
		$result = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::filterMitgliedsnummern($result);

		return $result; // Abfrageergebnis von Schnittstelle zurückgeben
	}

	/**
	 * Liefert einen Cache-Eintrag als Antwort und vermerkt Herkunft und
	 * Gültigkeit, damit die Ausgabe darauf hinweisen kann.
	 *
	 * @param $cache       Cache-Instanz der Funktion
	 * @param $params      Parameter der Abfrage (funktion, cachekey)
	 * @param $abgelaufen  true = auch abgelaufene Einträge ausliefern (Notreserve)
	 * @return array|null  Antwort im API-Format, null wenn nichts hinterlegt ist
	 */
	protected static function ausCache($cache, $params, $abgelaufen = false)
	{
		$cache_result = $cache->retrieve($params['cachekey'], false, $abgelaufen);

		if(!is_array($cache_result)) return $cache_result;

		// Abruf aus dem lokalen Cache für die Statistik zählen
		self::zaehleAbruf($params['funktion'], \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::QUELLE_CACHE);

		// Gemerkte Fehlanzeige (404): unverändert durchreichen, aber OHNE
		// Herkunftsvermerk. Sonst stünde über der Fehlermeldung „Diese Daten
		// stammen aus dem Zwischenspeicher…" — und es gibt gar keine Daten
		if(!empty($cache_result['negativ'])) return $cache_result;

		// Herkunft und Gültigkeit vermerken, damit die Ausgabe einen Hinweis
		// anzeigen kann („aus dem Zwischenspeicher, gültig bis …") — sowohl in
		// der Antwort selbst als auch gesammelt für den ganzen Seitenaufruf
		$ablauf = $cache->getExpiration($params['cachekey']);
		self::$cacheTreffer[] = $ablauf;

		// Speicherzeitpunkt: Der Hinweis nennt damit nicht nur, bis wann die
		// Daten gelten, sondern auch von wann sie sind — bei einer Cachezeit
		// von einer Woche ist das der weit wichtigere Wert
		$stand = $cache->getStoreTime($params['cachekey']);
		if($stand > 0) self::$cacheStand[] = (int) $stand;

		$cache_result['cachequelle'] = true;
		$cache_result['cacheablauf'] = $ablauf;
		$cache_result['cachestand'] = (int) $stand;

		// Notdaten bleiben als solche erkennbar, auch wenn sie zwischenzeitlich
		// mit einer Notfrist neu datiert wurden und damit wieder als „gültig"
		// gelten — sonst verschwiege die Ausgabe, wie alt sie in Wahrheit sind
		if(!empty($cache_result['notstand'])) self::$notdaten[] = $cache_result['notstand'];

		// Platzhalter-Mitgliedsnummern auch bei Cache-Treffern herausfiltern
		// (wirkt sofort statt erst nach Cache-Ablauf)
		return \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::filterMitgliedsnummern($cache_result);
	}

	/**
	 * Notbetrieb: Die Schnittstelle steht nicht zur Verfügung (abgeschaltet
	 * oder ohne Antwort). Ausgeliefert wird der Cache-Eintrag OHNE Rücksicht
	 * auf seine Ablaufzeit — veraltete Daten sind besser als eine leere Seite.
	 *
	 * Nach einem gescheiterten Abruf bekommt der Eintrag zusätzlich eine
	 * Notfrist (self::NOTFRIST): Ohne sie liefe jeder weitere Seitenaufruf
	 * erneut in die volle Wartezeit der Schnittstelle. Der ursprüngliche
	 * Speicherzeitpunkt wandert dabei als „notstand" mit, damit die Ausgabe
	 * weiterhin das echte Alter der Daten nennen kann.
	 *
	 * @param $cache       Cache-Instanz der Funktion oder null (Cache aus)
	 * @param $params      Parameter der Abfrage (funktion, cachekey)
	 * @param $abgerufen   true = es gab einen gescheiterten Abrufversuch
	 * @return array       Antwort im API-Format; ohne Notreserve eine Fehlerantwort
	 */
	protected static function notdaten($cache, $params, $abgerufen)
	{
		$roh = ($cache !== null && $cache->isCached($params['cachekey'], true))
			? $cache->retrieve($params['cachekey'], false, true)
			: null;

		// Eine gemerkte Fehlanzeige taugt NICHT als Notreserve: Sie enthält
		// keine Daten, und als „zwischengespeicherte Daten" ausgegeben wäre
		// sie irreführend. Stattdessen wird gleich im örtlichen Bestand
		// weitergesucht — dort kann die Auswertung durchaus vorliegen
		if(is_array($roh) && !empty($roh['negativ'])) $roh = null;

		if(is_array($roh))
		{
			// Ursprünglicher Speicherzeitpunkt: aus einem früheren Notlauf
			// übernommen, sonst der Zeitstempel des Eintrags
			$stand = !empty($roh['notstand']) ? (int) $roh['notstand'] : $cache->getStoreTime($params['cachekey']);
			$roh['notstand'] = $stand;

			// Notfrist nur nach einem tatsächlich gescheiterten Abruf. Ist die
			// Schnittstelle bewusst abgeschaltet, kostet ein Seitenaufruf keine
			// Wartezeit — dann gibt es auch nichts abzufedern, und der Eintrag
			// bleibt unangetastet
			if($abgerufen) $cache->store($params['cachekey'], $roh, self::NOTFRIST);

			// Zählt als Cache-Abruf: Bei der Schnittstelle kam nichts an
			self::zaehleAbruf($params['funktion'], \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::QUELLE_CACHE);
			self::$notdaten[] = $stand;

			$roh['cachequelle'] = true;
			// Ein Ablaufzeitpunkt hat hier keine Aussage (längst verstrichen
			// oder die künstliche Notfrist), deshalb bewusst leer
			$roh['cacheablauf'] = null;

			return \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::filterMitgliedsnummern($roh);
		}

		// Zweite Rückfallebene: die lokalen Spiegeltabellen. Sie füllen sich
		// über die Syncs jeder erfolgreichen Abfrage und über die CSV-Importe
		// und sind damit oft aktueller als ein längst abgelaufener
		// Cache-Eintrag — nur eben nicht vollständig, weshalb sie erst nach
		// dem Zwischenspeicher zum Zug kommen
		$lokal = \Schachbulle\ContaoWertungsportalBundle\Helper\Lokal::abfrage($params);

		if(is_array($lokal))
		{
			self::zaehleAbruf($params['funktion'], \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::QUELLE_LOKAL);

			return \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::filterMitgliedsnummern($lokal);
		}

		// Keine Notreserve vorhanden: Fehlerantwort mit der Meldung, die die
		// Module über ihren Fehler-Slot ausgeben. Hier wird BEWUSST nichts in
		// $notdaten vermerkt — sonst behauptete der Hinweis über der Ausgabe,
		// es würden zwischengespeicherte Daten angezeigt, und stünde zusätzlich
		// zur Fehlermeldung doppelt auf der Seite
		return array
		(
			'error'           => true,
			'error_message'   => self::MELDUNG_KEINE_LIVEDATEN,
			'http_code'       => 0,
			'keine_livedaten' => true,
		);
	}

	/**
	 * Liefert die eingestellte Wartezeit eines Abrufs in Sekunden.
	 * Ohne Auswahl gilt self::TIMEOUT_STANDARD.
	 *
	 * @return int Wartezeit in Sekunden (mindestens 1)
	 */
	public static function timeout()
	{
		$wert = (int) ($GLOBALS['TL_CONFIG']['wertungsportal_api_timeout'] ?? 0);

		return $wert > 0 ? $wert : self::TIMEOUT_STANDARD;
	}

	/**
	 * Meldet, ob in diesem Seitenaufruf Notdaten ausgeliefert wurden, also
	 * abgelaufene Cache-Einträge mangels erreichbarer Schnittstelle.
	 *
	 * @return false|int|null false = keine Notdaten, sonst der älteste
	 *                        Speicherzeitpunkt (null, wenn unbekannt)
	 */
	public static function notstand()
	{
		if(!count(self::$notdaten)) return false;

		$zeiten = array_filter(self::$notdaten, function($wert) { return $wert > 0; });

		return count($zeiten) ? min($zeiten) : null;
	}

	/**
	 * Schreibt den Grund einer gescheiterten Verbindung ins Systemprotokoll.
	 *
	 * Das Frontend meldet nur „Der Abruf von Live-Daten ist zurzeit nicht
	 * möglich" — richtig für Besucher, aber für den Betreiber wertlos: Ob die
	 * Schnittstelle gerade streikt, die Wartezeit zu knapp ist oder gar keine
	 * Wurzelzertifikate installiert sind, macht einen erheblichen Unterschied.
	 * Der cURL-Fehlertext beantwortet das und steht damit unter
	 * System → Systemlog.
	 *
	 * Höchstens ein Eintrag je Seitenaufruf (statischer Merker): Eine Seite
	 * setzt mehrere Abfragen ab, und bei einer Störung scheitern sie alle —
	 * das Protokoll soll den Grund nennen, nicht zulaufen.
	 *
	 * @param  array $result Fehlerhafte Antwort mit error_message
	 * @return void
	 */
	protected static function meldeStoerung($result)
	{
		$meldung = trim((string) ($result['error_message'] ?? ''));
		if($meldung === '') $meldung = 'Die Schnittstelle hat nicht geantwortet.';

		// Grund festhalten, auch wenn schon gemeldet wurde: Der Vorlader baut
		// seine Protokollzeile daraus. Die Ersatzantwort aus dem örtlichen
		// Bestand trägt nur noch „Abruf nicht möglich" — die eigentliche
		// Ursache steckt allein hier
		self::$letzteStoerung = $meldung;

		if(self::$stoerungGemeldet) return;

		self::$stoerungGemeldet = true;

		try
		{
			\System::log('Wertungsportal: Kein Zugriff auf die Schnittstelle — '.$meldung, __METHOD__, defined('TL_ERROR') ? TL_ERROR : 'ERROR');
		}
		catch(\Throwable $e)
		{
			// Ein klemmendes Protokoll darf die Ausgabe nicht zusätzlich stören
		}
	}

	/*********************************************************
	 * getAPI
	 * =========
	 * Hier erfolgt die eigentliche Abfrage
	 *
	 * Führt den eigentlichen Abruf bei der Schnittstelle aus und gleicht die
	 * Antwort mit den örtlichen Spiegeltabellen ab.
	 *
	 * Der Verteiler baut je Funktion den Endpunkt samt Abfragezeichenkette und
	 * ruft danach den passenden Abgleich (syncPersons, syncClubs, syncTournaments
	 * …). Seiteneffekte stecken also in den Sync-Methoden: Sie legen Personen,
	 * Vereine und Turniere an und aktualisieren sie.
	 *
	 * ALLE Zugriffe auf $params sind mit `?? ''` abgesichert. Welche Angaben eine
	 * Funktion braucht, steht im jeweiligen Case; fehlt eine, entfällt der
	 * betroffene Teil der Abfrage, statt dass PHP 8 eine „Undefined array key"-
	 * Meldung ausgibt. Die Module füllen zwar alles, aber ein Aufruf aus einem
	 * Prüfstand, einem Cronjob oder künftigem Code tut das nicht zwingend.
	 *
	 * @param  array $params Parameter der Abfrage. 'funktion' wählt den Endpunkt,
	 *                       dazu je nach Funktion 'id', 'turnier', 'zps', 'vorname',
	 *                       'nachname', 'suche', 'von', 'bis', 'limit', 'geschlecht',
	 *                       'alter_von', 'alter_bis'
	 *
	 * @return array Antwort im Format von callApiWithRefresh (error, http_code,
	 *               body). Bei unbekannter oder fehlender Funktion eine
	 *               Fehlerantwort mit HTTP-Code 400 — die Abfrage hat die
	 *               Schnittstelle dann gar nicht erst erreicht
	 */
	public static function getAPI($params)
	{
		$client = new \Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client();
		$get = '';

		switch($params['funktion'] ?? '')
		{
			case 'Spielerliste': // Spielerliste einer Suche
				// vorname = Vorname des Spielers, default = leer
				// nachname = Nachname des Spielers
				$get = '';
				$get .=  ($params['vorname'] ?? '') ? 'firstname='.rawurlencode($params['vorname']).'&' : '';
				$get .=  ($params['nachname'] ?? '') ? 'lastname='.rawurlencode($params['nachname']).'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons
				break;

			case 'Karteikarte': // Karteikarte eines Spielers nach nu-ID
				// id = nu-ID des Spielers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons/'.($params['id'] ?? ''));
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons
				break;

			case 'Karteikarte_Turniere': // Turniere für die Karteikarte eines Spielers
				// id = nu-ID des Spielers
				//echo '/dwz/persons/'.$params['id'].'/history';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/persons/'.($params['id'] ?? '').'/history');
				self::syncPersonHistory($result); // Abgleich mit tl_wertungsportal_persons, _tournaments, _upgrades und tl_wertungsportal_tournaments
				break;

			case 'Spielberichtsbogen': // Scoresheet eines Spielers für ein Turnier
				// id = nu-UUID des Spielers
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.($params['turnier'] ?? '').'/players/'.($params['id'] ?? '').'/scoresheet');
				self::syncScoresheet($result); // Abgleich Turnier, Partien und Spielerdaten mit den Turniertabellen
				break;

			case 'Turnierinfo': // Kopfdaten eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.($params['turnier'] ?? ''));
				self::syncTournaments($result); // Abgleich mit tl_wertungsportal_tournaments
				break;

			case 'Turnierliste': // Suchergebnisse nach einem Turnier laden
				// suche = Suche nach Turniername
				$params['zps'] = rtrim($params['zps'] ?? '', '0'); // nu sucht nach Prefix der ZPS
				$get = '';
				$get .=  ($params['suche'] ?? '') ? 'label='.rawurlencode($params['suche']).'&' : '';
				$get .=  ($params['von'] ?? '') ? 'fromDate='.rawurlencode($params['von']).'&' : '';
				$get .=  ($params['bis'] ?? '') ? 'toDate='.rawurlencode($params['bis']).'&' : '';
				$get .=  $params['zps'] ? 'vkz='.rawurlencode($params['zps']).'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments?'.$get);
				self::syncTournaments($result); // Abgleich mit tl_wertungsportal_tournaments
				break;

			case 'Turnierauswertung': // DWZ-Auswertung eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.($params['turnier'] ?? '').'/evaluation');
				self::syncTournamentEvaluation($result); // Abgleich mit tl_wertungsportal_tournaments und _evaluation
				break;

			case 'Turnierergebnisse': // Ergebnisse eines Turniers laden
				// turnier = UUID des Turniers
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/tournaments/'.($params['turnier'] ?? '').'/matches');
				self::syncTournamentMatches($result); // Abgleich mit tl_wertungsportal_tournaments_matches (Spielerdaten in _evaluation)
				break;

			case 'Vereinsliste': // Spielerliste eines Vereins
				// zps = fünfstellig
				$get =  ($params['zps'] ?? '') ? 'vkz='.rawurlencode($params['zps']) : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons (Vereine über die Mitgliedschaften)
				break;

			case 'Verbandsliste': // Spielerliste eines Verbands
				// zps = ein- bis fünfstellig
				$get =  ($params['zps'] ?? '') ? 'vkz='.rawurlencode($params['zps']).'&' : '';
				$get .= ($params['limit'] ?? '') ? 'limit='.$params['limit'].'&' : '';
				$get .= ($params['geschlecht'] ?? '') ? 'gender='.$params['geschlecht'].'&' : '';
				$get .= ($params['alter_von'] ?? '') ? 'minAge='.$params['alter_von'].'&' : '';
				$get .= ($params['alter_bis'] ?? '') ? 'maxAge='.$params['alter_bis'].'&' : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/persons?'.$get);
				self::syncPersons($result); // Abgleich mit tl_wertungsportal_persons
				break;

			case 'Vereinsname': // Vereinsname anhand der ZPS
				// zps = fünfstellig
				$get =  ($params['zps'] ?? '') ? 'vkz='.rawurlencode($params['zps']) : '';
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs?'.$get);
				// Fehlende Verbände direkt nach dem Abruf ergänzen — hier nur
				// den angefragten, damit eine Einzelabfrage nicht plötzlich
				// alle Landesverbände zurückliefert
				$result = self::BugfixVerbaende($result, $params['zps'] ?? '');
				self::syncClubs($result); // Abgleich mit tl_wertungsportal_clubs
				break;

			case 'Verbaende': // Verbände einer ZPS-Struktur laden
				// zps = fünfstellig
				$result = $client->callApiWithRefresh($client->apiBaseUrl . '/dwz/dwzliste/clubs');
				// Ergänzung VOR dem Abgleich: Sonst kennt die lokale Tabelle
				// die fehlenden Verbände nie (der Sync bekam bisher die
				// unvollständige Antwort und erst danach wurde ergänzt)
				$result = self::BugfixVerbaende($result);
				self::syncClubs($result); // Abgleich mit tl_wertungsportal_clubs
				break;

			default:
				// Unbekannte oder fehlende Funktion: Bisher blieb $result hier
				// unbelegt, der Aufrufer bekam also eine „Undefined variable"-
				// Meldung und danach die Folgefehler beim Zugriff auf http_code.
				// Stattdessen eine Fehlerantwort im Format der Schnittstelle.
				// HTTP-Code 400 statt 0: Es ist ein Aufruffehler, keine gestörte
				// Verbindung — sonst löste er den Notbetrieb aus und schriebe
				// eine irreführende Störmeldung ins Systemlog
				$result = array
				(
					'error'         => true,
					'error_message' => 'Unbekannte Abfrage: '.($params['funktion'] ?? '(keine Funktion angegeben)'),
					'http_code'     => 400
				);
		}

		// Abfrageergebnis zurückgeben
		// $result = array
		// (
		//   'success'   => true|false,
		//   'http_code' => 200,          // 0 bei cURL-Fehler
		//   'error'     => null|'...',   // Fehlermeldung im Fehlerfall
		//   'body'      => [...],        // API-Antwort oder null
		// )
		return $result;
	}

	/*********************************************************
	 * syncClubs
	 * =========
	 * Gleicht die Rückgabe von /dwz/dwzliste/clubs mit der Tabelle
	 * tl_wertungsportal_clubs ab. Vorhandene Vereine werden anhand
	 * der VKZ nur bei tatsächlichen Änderungen aktualisiert, unbekannte
	 * gesammelt per Batch-INSERT neu angelegt.
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncClubs($result)
	{
		// Nur bei erfolgreicher Abfrage mit Datenarray abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']['data']) || !is_array($result['body']['data'])) return;

		\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalClubsModel::syncList($result['body']['data']);
	}

	/*********************************************************
	 * syncPersons
	 * ===========
	 * Gleicht die Rückgabe von /dwz/dwzliste/persons mit den Tabellen
	 * tl_wertungsportal_persons und tl_wertungsportal_persons_memberships
	 * ab. Vorhandene Personen werden anhand der UUID aktualisiert,
	 * unbekannte neu angelegt. Das Unter-Array "memberships" wird in die
	 * Kindtabelle übernommen (nicht mehr gemeldete Einträge werden gelöscht).
	 * Verarbeitet sowohl Listen (body.data) als auch einzelne Personen
	 * (z. B. Karteikarte: /dwz/dwzliste/persons/{id}).
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncPersons($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		// Listenabfrage (data-Array) oder einzelne Person?
		$persons = isset($result['body']['data']) ? $result['body']['data'] : $result['body'];
		if(isset($persons['uuid'])) $persons = array($persons); // Einzelne Person in Array packen
		if(!is_array($persons)) return;

		// Personen in einem Rutsch abgleichen (liefert UUID => Datensatz-ID)
		$arrMap = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::syncList($persons);

		// Mitgliedschaften je Person einsammeln und gesammelt abgleichen
		$arrMemberships = array();

		foreach($persons as $person)
		{
			if(!is_array($person) || empty($person['uuid'])) continue; // Ohne UUID kein Abgleich möglich

			$pid = isset($arrMap[(string) $person['uuid']]) ? $arrMap[(string) $person['uuid']] : 0;

			if($pid > 0 && isset($person['memberships']) && is_array($person['memberships']))
			{
				$arrMemberships[$pid] = $person['memberships'];
			}
		}

		if(count($arrMemberships))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::syncForPersons($arrMemberships);
		}
	}

	/*********************************************************
	 * syncPersonHistory
	 * =================
	 * Gleicht die Rückgabe von /dwz/persons/{id}/history (Karteikarte)
	 * mit den lokalen Tabellen ab:
	 * - body.person    -> tl_wertungsportal_persons (inkl. Mitgliedschaften)
	 * - body.entries   -> tl_wertungsportal_persons_tournaments, die
	 *                     Turnierdaten redundanzfrei nach tl_wertungsportal_tournaments
	 * - body.upgrades  -> tl_wertungsportal_persons_upgrades
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncPersonHistory($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		$body = $result['body'];

		// Ohne Person kein Abgleich möglich (pid der Kindtabellen)
		if(!isset($body['person']['uuid'])) return;

		// Person selbst abgleichen (inkl. Mitgliedschaften)
		$model = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsModel::upsertByUuid($body['person']);

		if(isset($body['person']['memberships']) && is_array($body['person']['memberships']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::syncForPerson((int) $model->id, $body['person']['memberships']);
		}

		// Turnierhistorie abgleichen (legt auch die Turniere zentral ab)
		if(isset($body['entries']) && is_array($body['entries']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsTournamentsModel::syncForPerson((int) $model->id, $body['entries']);
		}

		// DWZ-Hochstufungen abgleichen
		if(isset($body['upgrades']) && is_array($body['upgrades']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsUpgradesModel::syncForPerson((int) $model->id, $body['upgrades']);
		}
	}

	/*********************************************************
	 * syncTournaments
	 * ===============
	 * Gleicht Turnierdaten (DSBTournamentDTO) mit der Tabelle
	 * tl_wertungsportal_tournaments ab. Verarbeitet sowohl Listen
	 * (body.data, z. B. Turnierliste) als auch einzelne Turniere
	 * (z. B. Turnierinfo: /dwz/tournaments/{uuid}).
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncTournaments($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		// Listenabfrage (data-Array) oder einzelnes Turnier?
		$tournaments = isset($result['body']['data']) ? $result['body']['data'] : $result['body'];
		if(isset($tournaments['uuid'])) $tournaments = array($tournaments); // Einzelnes Turnier in Array packen
		if(!is_array($tournaments)) return;

		\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::syncList($tournaments);
	}

	/*********************************************************
	 * syncTournamentEvaluation
	 * ========================
	 * Gleicht die Rückgabe von /dwz/tournaments/{uuid}/evaluation ab:
	 * - body.tournament -> tl_wertungsportal_tournaments
	 * - body.players    -> tl_wertungsportal_tournaments_evaluation
	 *                      (inkl. Löschung nicht mehr gemeldeter Spieler)
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncTournamentEvaluation($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		$body = $result['body'];

		// Ohne Turnier kein Abgleich möglich (pid der Kindtabelle)
		if(!isset($body['tournament']['uuid'])) return;

		$tournament = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::upsertByUuid($body['tournament']);

		if(isset($body['players']) && is_array($body['players']))
		{
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsEvaluationModel::syncForTournament((int) $tournament->id, $body['players'], (string) $tournament->uuid);
		}
	}

	/*********************************************************
	 * syncTournamentMatches
	 * =====================
	 * Gleicht die Rückgabe von /dwz/tournaments/{uuid}/matches (body.data)
	 * mit tl_wertungsportal_tournaments_matches ab. Die eingebetteten
	 * Spieler-DTOs (whitePlayer/blackPlayer) werden redundanzfrei in
	 * tl_wertungsportal_tournaments_evaluation übernommen. Da die Abfrage
	 * paginiert ist, werden keine Partien gelöscht.
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncTournamentMatches($result)
	{
		// Nur bei erfolgreicher Abfrage mit Datenarray abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']['data']) || !is_array($result['body']['data'])) return;

		self::syncMatchList($result['body']['data']);
	}

	/*********************************************************
	 * syncScoresheet
	 * ==============
	 * Gleicht die Rückgabe von /dwz/tournaments/{t}/players/{p}/scoresheet
	 * ab. Das Scoresheet enthält keine neuen Datenstrukturen, aber die
	 * Partien (matches, restpartienAlt) samt Spieler-DTOs des betroffenen
	 * Turniers - diese werden in tl_wertungsportal_tournaments_matches
	 * bzw. tl_wertungsportal_tournaments_evaluation übernommen.
	 *
	 * @param       Array $result Rückgabe von callApiWithRefresh
	 */
	protected static function syncScoresheet($result)
	{
		// Nur bei erfolgreicher Abfrage abgleichen
		if(!is_array($result) || $result['error'] || $result['http_code'] != 200) return;
		if(!isset($result['body']) || !is_array($result['body'])) return;

		$matches = array();

		if(isset($result['body']['matches']) && is_array($result['body']['matches']))
		{
			$matches = $result['body']['matches'];
		}

		if(isset($result['body']['restpartienAlt']) && is_array($result['body']['restpartienAlt']))
		{
			$matches = array_merge($matches, $result['body']['restpartienAlt']);
		}

		if(count($matches)) self::syncMatchList($matches);
	}

	/*********************************************************
	 * syncMatchList
	 * =============
	 * Hilfsfunktion: Gruppiert Match-DTOs nach tournamentUuid, legt für
	 * unbekannte Turniere einen Platzhalter in tl_wertungsportal_tournaments
	 * an und übergibt die Partien an das Matches-Model.
	 *
	 * @param       Array $matches Array von Match-DTOs
	 */
	protected static function syncMatchList($matches)
	{
		// Partien nach Turnier gruppieren
		$arrGrouped = array();

		foreach($matches as $match)
		{
			if(!is_array($match) || empty($match['tournamentUuid'])) continue; // Ohne Turnier-UUID kein Abgleich möglich
			$arrGrouped[$match['tournamentUuid']][] = $match;
		}

		foreach($arrGrouped as $uuid => $arrMatches)
		{
			// Turnier ermitteln, ggf. als Platzhalter anlegen (Details kommen per Turnierinfo/Turnierliste)
			$tournament = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::upsertByUuid(array('uuid' => $uuid));
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsMatchesModel::syncForTournament((int) $tournament->id, $arrMatches, (string) $uuid);
		}
	}

	/**
	 * Hook-Funktion:
	 * Wertet das URL-Parameter-Array aus und modifiziert es, wenn das Array für DeWIS bestimmt ist
	 *
	 * @return array
	 */
	public static function getParamsFromUrl($arrFragments)
	{
		//echo "<!--";
		//print_r($arrFragments);
		$args = count($arrFragments); // Anzahl Argumente

		if($args == 1)
		{
			// In $args[0] steht das Seitenalias, jetzt prüfen auf URL-Parameter und ggfs. auf neue URL weiterleiten
			switch($arrFragments[0])
			{

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite():
					if(\Input::get('zps'))
					{
						header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite().'/'.\Input::get('zps').'.html');
					}
					elseif(\Input::get('pkz'))
					{
						header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite().'/'.\Input::get('pkz').'.html');
					}
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite():
					if(\Input::get('zps'))
					{
						header('Location:'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite().'/'.\Input::get('zps').'.html');
					}
					break;

				default:
			}
		}
		elseif($args > 1)
		{
			// In $args[0] steht das Seitenalias, ab $args[1] die Parameter
			switch($arrFragments[0])
			{

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseite():
					if($arrFragments[1] == 'auto_item') $arrFragments[1] = 'id';
					// ZPS-Angabe ggfs. anpassen (4-stellige Mitgliedsnummer!)
					$zps = explode('-', $arrFragments[2]);
					$arrFragments[2] = count($zps) == 2 ? $zps[0].'-'.substr('0000'.$zps[1], -4) : $arrFragments[2];
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseite():
					if($arrFragments[1] == 'auto_item') $arrFragments[1] = 'zps';
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseite():
					if($arrFragments[1] == 'auto_item') $arrFragments[1] = 'zps';
					break;

				case \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseite():
					if($arrFragments[1] == 'auto_item')
					{
						$arrFragments[1] = 'code';
					}
					else
					{
						$newArray = array($arrFragments[0]);
						// 1. Wert ist offensichtlich ein Turniercode
						$newArray[1] = 'code';
						$newArray[2] = $arrFragments[1];
						if($arrFragments[2] == 'Ergebnisse')
						{
							// Ein weiterer Wert wartet: Ergebnisse des Turniers anzeigen
							$newArray[3] = 'view';
							$newArray[4] = 'results';
						}
						elseif($arrFragments[2])
						{
							// Ein weiterer Wert wartet: ID des Spielers
							$newArray[3] = 'id';
							$newArray[4] = $arrFragments[2];
							$newArray[5] = 'view';
							$newArray[6] = 'results';
						}
						$arrFragments = $newArray;
					}
					break;

				default:
			}
		}

		return $arrFragments;
	}

	public static function Verbandsliste($zps = '00000')
	{

		// Abfrageparameter einstellen
		$param = array
		(
			'funktion' => 'Verbaende',
			'cachekey' => $zps,
			'zps'      => $zps
		);

		$resultArr = self::autoQuery($param); // Abfrage ausführen

		// Nach Verbänden und Vereinen ordnen. Der Guard ist nötig, seit die
		// Schnittstelle abgeschaltet werden kann und ohne Notreserve eine
		// Fehlerantwort ohne body zurückkommt — sonst liefe der foreach auf null
		if(!is_array($resultArr['body']['data'] ?? null)) return array('verbaende' => array(), 'vereine' => array());

		$verbaende = array(); $vereine = array();
		foreach($resultArr['body']['data'] as $item)
		{
			// Die Regel steht in Helper::istVerband() — dieselbe Stelle, an der
			// auch die Vereinsseite entscheidet, ob sie Mitglieder oder Vereine
			// zeigt. Zwei Kopien liefen sonst irgendwann auseinander
			if(\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istVerband($item['clubVkz']))
			{
				$verbaende[] = $item;
			}
			else
			{
				$vereine[] = $item;
			}
		}

		return array('verbaende' => $verbaende, 'vereine' => $vereine);
	}

	/**
	 * Lädt die FIDE-Daten Elo, Titel, Nation aus der lokalen Quelle
	 */
	public static function getFIDE($fideid)
	{
		$fide = array('land' => false, 'elo' => false, 'titel' => false);
		if($fideid)
		{
			// FIDE-ID in lokaler Datenbank suchen
			$objPlayer = \Database::getInstance()->prepare("SELECT * FROM tl_wertungsportal_elo WHERE fideid = ?")
			                                     ->execute($fideid);
			if($objPlayer->numRows)
			{
				$fide = array
				(
					'land'  => $objPlayer->country,
					'elo'   => $objPlayer->rating,
					'titel' => $objPlayer->title
				);
			}
		}
		return $fide;
	}

	/**
	 * Liefert die Namen aller Cache-Speicher des Wertungsportals zurück
	 * (entsprechen den funktion-Parametern von autoQuery, Präfix wp_)
	 *
	 * @return array
	 */
	public static function cacheSpeicher()
	{
		return array
		(
			'Spielerliste',
			'Karteikarte',
			'Karteikarte_Turniere',
			'Vereinsliste',
			'Vereinsname',
			'Verbaende',
			'Verbandsliste',
			'Turnierliste',
			'Turnierinfo',
			'Turnierauswertung',
			'Turnierergebnisse',
			'Spielberichtsbogen',
		);
	}

	/**
	 * Liefert den frühesten Ablaufzeitpunkt der Abfragen, die in diesem
	 * Seitenaufruf aus dem Zwischenspeicher kamen — also den Zeitpunkt, ab
	 * dem die Seite wieder frische Daten zeigt.
	 *
	 * @return      false|int|null  false = nichts aus dem Cache,
	 *                              null = ohne Ablauf, sonst Zeitstempel
	 */
	public static function cacheStatus()
	{
		if(!count(self::$cacheTreffer)) return false;

		$zeiten = array_filter(self::$cacheTreffer, function($wert) { return $wert > 0; });

		return count($zeiten) ? min($zeiten) : null;
	}

	/**
	 * Speicherzeitpunkt der auf dieser Seite aus dem Zwischenspeicher
	 * gelesenen Daten.
	 *
	 * Kamen mehrere Abfragen aus dem Zwischenspeicher, gilt der ÄLTESTE
	 * Zeitpunkt: Der Hinweis soll nicht jünger klingen, als die Seite in ihrem
	 * ältesten Bestandteil tatsächlich ist.
	 *
	 * @return int|false Zeitstempel, oder false wenn nichts aus dem
	 *                   Zwischenspeicher kam bzw. kein Zeitpunkt vorliegt
	 */
	public static function cacheStand()
	{
		if(!count(self::$cacheStand)) return false;

		return min(self::$cacheStand);
	}

	/**
	 * Gegenstück zu cacheStatus() für die in diesem Seitenaufruf FRISCH
	 * abgerufenen und abgelegten Daten.
	 *
	 * @return      false|int|null  false = nichts frisch geholt,
	 *                              null = ohne Ablauf, sonst Zeitstempel
	 */
	public static function frischStatus()
	{
		if(!count(self::$frischTreffer)) return false;

		$zeiten = array_filter(self::$frischTreffer, function($wert) { return $wert > 0; });

		return count($zeiten) ? min($zeiten) : null;
	}

	/**
	 * Zeitpunkt des ältesten frischen Abrufs dieses Seitenaufrufs.
	 *
	 * @return int|false Zeitstempel, oder false wenn nichts frisch geholt wurde
	 */
	public static function frischStand()
	{
		if(!count(self::$frischStand)) return false;

		return min(self::$frischStand);
	}

	/**
	 * Ordnet jeder internen Funktion den Pfad der Schnittstellenfunktion zu
	 * (siehe API-Dokumentation des Wertungsportals). Grundlage für die
	 * Abrufstatistik; {…} steht für den jeweiligen Parameter.
	 *
	 * @return array   Funktion => Endpunkt
	 */
	public static function endpunkte()
	{
		return array
		(
			// /dwz/dwzliste
			'Spielerliste'         => '/dwz/dwzliste/persons',
			'Vereinsliste'         => '/dwz/dwzliste/persons',
			'Verbandsliste'        => '/dwz/dwzliste/persons',
			'Karteikarte'          => '/dwz/dwzliste/persons/{id}',
			'Vereinsname'          => '/dwz/dwzliste/clubs',
			'Verbaende'            => '/dwz/dwzliste/clubs',
			// /dwz/persons
			'Karteikarte_Turniere' => '/dwz/persons/{id}/history',
			// /dwz/tournaments
			'Turnierliste'         => '/dwz/tournaments',
			'Turnierinfo'          => '/dwz/tournaments/{uuid}',
			'Turnierauswertung'    => '/dwz/tournaments/{uuid}/evaluation',
			'Turnierergebnisse'    => '/dwz/tournaments/{uuid}/matches',
			'Spielberichtsbogen'   => '/dwz/tournaments/{uuid}/players/{id}/scoresheet',
		);
	}

	/**
	 * Merker: Läuft gerade der nächtliche Vorlader?
	 * @var bool
	 */
	protected static $vorladen = false;

	/**
	 * Schaltet die Zählung auf den Vorlader um oder zurück.
	 *
	 * Der Vorlader holt bewusst über denselben Weg wie das Frontend
	 * (autoQuery), damit Cachezeiten, Abgleich und Zählung identisch sind.
	 * Damit landen aber auch seine Abrufe in der Statistik — und weil er in
	 * einer Nacht ein Vielfaches dessen holt, was Besucher an einem Tag
	 * auslösen, wäre danach nicht mehr zu erkennen, wie gut der
	 * Zwischenspeicher die BESUCHER bedient. Deshalb schaltet er für die Dauer
	 * seines Laufs auf eine eigene Quelle um.
	 *
	 * Aufzurufen paarweise in einem try/finally — bleibt der Schalter stehen,
	 * würden im Web-Betrieb die Abrufe des restlichen Seitenaufrufs
	 * falsch verbucht.
	 *
	 * @param  bool $an true zu Beginn des Laufs, false am Ende
	 * @return void
	 */
	public static function vorladen($an)
	{
		self::$vorladen = (bool) $an;
	}

	/**
	 * Zählt einen Abruf für die Statistik
	 *
	 * @param       String $funktion   interne Funktion
	 * @param       String $quelle     'api', 'cache' oder 'lokal'
	 */
	protected static function zaehleAbruf($funktion, $quelle)
	{
		$endpunkte = self::endpunkte();

		// Während des Vorladens zählt alles auf dessen Konto — auch die
		// Cache-Treffer, denn ausgelöst hat sie kein Besucher
		if(self::$vorladen) $quelle = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::QUELLE_VORLADER;

		\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::zaehle
		(
			(string) $funktion,
			$quelle,
			isset($endpunkte[$funktion]) ? $endpunkte[$funktion] : ''
		);
	}

	/**
	 * Ordnet jeder API-Funktion ihre Einstellungsgruppe zu. Die Cachezeiten
	 * werden in den System-Einstellungen je Gruppe gepflegt — Verbands- und
	 * Vereinsstammdaten ändern sich selten, Turnierauswertungen sind nach
	 * der Berechnung stabil, Suchen sollen dagegen aktuell bleiben.
	 *
	 * @return array   Funktion => Einstellungsfeld
	 */
	public static function cacheGruppen()
	{
		return array
		(
			'Spielerliste'         => 'wertungsportal_cachezeit_spieler',
			'Karteikarte'          => 'wertungsportal_cachezeit_spieler',
			'Karteikarte_Turniere' => 'wertungsportal_cachezeit_spieler',
			'Vereinsliste'         => 'wertungsportal_cachezeit_vereine',
			'Vereinsname'          => 'wertungsportal_cachezeit_vereine',
			'Verbandsliste'        => 'wertungsportal_cachezeit_vereine',
			'Verbaende'            => 'wertungsportal_cachezeit_verbaende',
			'Turnierliste'         => 'wertungsportal_cachezeit_turniersuche',
			// Turnierinfo gehört zu den Turnierdaten, nicht zur Suche: Es sind
			// die Kopfdaten EINES Turniers und damit genauso stabil wie
			// Auswertung, Ergebnisse und Spielberichtsbogen
			'Turnierinfo'          => 'wertungsportal_cachezeit_turnierdaten',
			'Turnierauswertung'    => 'wertungsportal_cachezeit_turnierdaten',
			'Turnierergebnisse'    => 'wertungsportal_cachezeit_turnierdaten',
			'Spielberichtsbogen'   => 'wertungsportal_cachezeit_turnierdaten',
		);
	}

	/**
	 * Meldet, ob für eine Funktion überhaupt zwischengespeichert wird.
	 *
	 * Eigene Methode, weil die Antwort bei den Turnierfunktionen an ZWEI
	 * Einstellungen hängt: Auch wenn junge Turniere nicht gecacht werden
	 * sollen, kann für alte trotzdem eine Cachezeit eingestellt sein — dann
	 * muss der Cache-Speicher angelegt werden.
	 *
	 * Öffentlich statt geschützt, seit der Vorlade-Cronjob
	 * (Cron\TurnierVorlader) vorab wissen muss, ob ein Vorladen überhaupt
	 * gespeichert würde — sonst liefe er täglich ins Leere.
	 *
	 * @param       String $funktion
	 * @return      Boolean
	 */
	public static function cacheAktiv($funktion)
	{
		if(empty($GLOBALS['TL_CONFIG']['wertungsportal_cache'])) return false;
		if(self::cachezeit($funktion) != 0) return true;

		return in_array($funktion, self::turnierFunktionen(), true)
			&& self::cachezeitAusFeld('wertungsportal_cachezeit_turnierdaten_alt', 0) != 0;
	}

	/**
	 * Funktionen, deren Daten sich auf genau EIN Turnier beziehen. Für sie
	 * hängt die Cachezeit zusätzlich am Alter des Turniers.
	 *
	 * @return array
	 */
	public static function turnierFunktionen()
	{
		return array('Turnierinfo', 'Turnierauswertung', 'Turnierergebnisse', 'Spielberichtsbogen');
	}

	/**
	 * Liefert die Cachezeit einer API-Funktion in Sekunden.
	 * Ohne Einstellung gelten 24 Stunden (bisheriges Verhalten);
	 * der Wert 0 schaltet den Cache für diese Funktion ab,
	 * self::CACHE_UNBEGRENZT steht für „läuft nie ab".
	 *
	 * @param       String $funktion
	 * @return      Integer Sekunden, 0 oder CACHE_UNBEGRENZT
	 */
	public static function cachezeit($funktion)
	{
		$gruppen = self::cacheGruppen();
		$feld = isset($gruppen[$funktion]) ? $gruppen[$funktion] : '';

		// Nicht zugeordnete Funktionen behalten die bisherigen 24 Stunden
		if($feld == '') return 3600 * 24;

		return self::cachezeitAusFeld($feld, 3600 * 24);
	}

	/**
	 * Liest eine Cachezeit-Einstellung aus und rechnet sie in Sekunden um.
	 *
	 * @param       String  $feld      Name der Einstellung
	 * @param       Integer $standard  Wert bei leerer Einstellung (Sekunden)
	 * @return      Integer Sekunden, 0 (kein Cache) oder CACHE_UNBEGRENZT
	 */
	protected static function cachezeitAusFeld($feld, $standard)
	{
		if(!isset($GLOBALS['TL_CONFIG'][$feld])) return $standard;

		$stunden = $GLOBALS['TL_CONFIG'][$feld];
		if($stunden === '' || $stunden === null) return $standard;

		// -1 steht für „unbegrenzt" und darf nicht mit Stunden verrechnet
		// werden — 0 bedeutet weiterhin „gar nicht cachen"
		if((int) $stunden < 0) return self::CACHE_UNBEGRENZT;

		return (int) $stunden * 3600;
	}

	/**
	 * Entscheidet beim SPEICHERN, wie lange eine Antwort gültig bleibt.
	 *
	 * Bei den vier Turnierfunktionen hängt das am Alter des Turniers: Nach der
	 * Erstauswertung sind Nachberechnungen im Wesentlichen nur im ersten Jahr
	 * zu erwarten, danach ändert sich an den Daten nichts mehr. Deshalb gibt es
	 * eine zweite Einstellung, die bis „unbegrenzt" reichen kann.
	 *
	 * Die Entscheidung fällt erst hier und nicht schon in cachezeit(), weil das
	 * Turnierende erst aus der Antwort hervorgeht.
	 *
	 * @param       Array   $params    Parameter der Abfrage
	 * @param       Array   $result    Antwort der Schnittstelle
	 * @param       Integer $standard  Cachezeit der Funktion (Sekunden)
	 * @return      Integer Sekunden, 0 oder CACHE_UNBEGRENZT
	 */
	public static function cachezeitFuerAntwort($params, $result, $standard)
	{
		if(!in_array($params['funktion'] ?? '', self::turnierFunktionen(), true)) return $standard;

		$ende = self::turnierEnde($params, $result);

		// Ohne bekanntes Turnierende bleibt es bei der normalen Cachezeit
		if($ende === '') return $standard;

		// Turnierende innerhalb des letzten Jahres: normale Cachezeit
		if($ende > date('Y-m-d', strtotime('-1 year'))) return $standard;

		// Ohne eigene Einstellung für alte Turniere gilt ebenfalls die normale
		return self::cachezeitAusFeld('wertungsportal_cachezeit_turnierdaten_alt', $standard);
	}

	/**
	 * Ermittelt das Turnierende (JJJJ-MM-TT) zu einer Antwort.
	 *
	 * Die Antworten legen es an unterschiedlichen Stellen ab; die Ergebnisliste
	 * (Partien) enthält es überhaupt nicht — dort hilft der örtliche Spiegel
	 * weiter, den der Abgleich kurz zuvor gefüllt hat.
	 *
	 * @param       Array $params
	 * @param       Array $result
	 * @return      String Datum oder '' wenn unbekannt
	 */
	protected static function turnierEnde($params, $result)
	{
		// Turnierinfo und Spielberichtsbogen: Turnierfelder flach unter body
		if(!empty($result['body']['enddate'])) return (string) $result['body']['enddate'];

		// Turnierauswertung: eigener tournament-Knoten
		if(!empty($result['body']['tournament']['enddate'])) return (string) $result['body']['tournament']['enddate'];

		// Turnierergebnisse: body.data enthält nur Partien — aus dem Spiegel lesen
		if(!empty($params['turnier']))
		{
			try
			{
				$objTurnier = \Database::getInstance()->prepare("SELECT enddate FROM tl_wertungsportal_tournaments WHERE uuid = ?")
				                                     ->execute($params['turnier']);

				if($objTurnier->numRows && $objTurnier->next()) return (string) $objTurnier->enddate;
			}
			catch(\Throwable $e)
			{
				// Fehlende Tabelle o. ä. darf die Auslieferung nicht stören
			}
		}

		return '';
	}

	/**
	 * Liefert den Verzeichnispfad eines Cache-Speichers (ein Verzeichnis je
	 * API-Funktion, darin eine Datei je Cache-Eintrag)
	 *
	 * @param       String $funktion
	 * @return      String Pfad mit abschließendem Trennzeichen
	 */
	protected static function cachePfad($funktion)
	{
		$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => 'x', 'path' => 'wp_'.$funktion, 'extension' => '.cache'));

		return $cache->getCachePath();
	}

	/**
	 * PurgeJob-Funktion:
	 * Berechnet die Cache-Größe für die Anzeige in der Systemwartung
	 */
	public static function calcCache()
	{
		$string = '</label>';
		foreach(self::cacheSpeicher() as $item)
		{
			// Je Eintrag eine Datei — die Dateien des Verzeichnisses zählen
			$dateien = glob(self::cachePfad($item).'*.cache');
			$anzahl = is_array($dateien) ? count($dateien) : 0;
			$text = ($anzahl == 1) ? 'Eintrag' : 'Einträge';
			$stunden = (int) (self::cachezeit($item) / 3600);
			$string .= '<br><span style="font-weight:normal"><span style="color:black">'.$item.':</span> '.$anzahl.' '.$text.' ('.($stunden > 0 ? $stunden.' h' : 'kein Cache').')</span>';
		}
		$string .= '<label>';

		return $string;
	}

	/**
	 * PurgeJob-Funktion:
	 * Löscht alle Caches des Wertungsportals. Wird von der Systemwartung
	 * (TL_PURGE) und automatisch nach jedem FIDE-Elo-Import aufgerufen,
	 * damit keine Cache-Einträge mit alter FIDE-Anreicherung übrig bleiben.
	 */
	public static function purgeCache()
	{
		foreach(self::cacheSpeicher() as $item)
		{
			// Alle Einträge des Funktionsverzeichnisses entfernen
			$dateien = glob(self::cachePfad($item).'*.cache');

			if(is_array($dateien))
			{
				foreach($dateien as $datei)
				{
					@unlink($datei);
				}
			}

			// Altbestand aus der Zeit der Sammeldateien mit aufräumen
			$cache = new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => 'wp_'.$item, 'extension' => '.cache'));
			$cache->eraseAll();
		}

		if($GLOBALS['TL_CONFIG']['wertungsportal_debuglog']) log_message('Wertungsportal-Cache geleert', 'wertungsportal.log');
	}

	/**
	 * Ergänzt die Verbände, die die Schnittstelle nicht liefert (laut
	 * Andreas Filmann sind Verbände in nu nicht vorgesehen, alles muss als
	 * Verein angelegt werden). Der Aufruf erfolgt DIREKT nach dem Abruf von
	 * /dwz/dwzliste/clubs und damit vor Abgleich und Zwischenspeicherung —
	 * so kennen lokale Tabelle, Cache und Frontend dieselben Verbände.
	 *
	 * @param       Array  $resultArr  API-Antwort
	 * @param       String $nurVkz     nur diesen Verband ergänzen (bei
	 *                                 gefilterter Abfrage); leer = alle
	 * @return      Array              ergänzte Antwort
	 */
	public static function BugfixVerbaende($resultArr, $nurVkz = '')
	{
		$missingFederations = array
		(
			array
			(
				'clubVkz'          => '20000',
				'clubName'         => 'Bayerischer Schachbund e.V.',
				'federation'       => '2',
				'parentFederation' => '200',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '30000',
				'clubName'         => 'Berliner Schachverband',
				'federation'       => '3',
				'parentFederation' => '300',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '40000',
				'clubName'         => 'Hamburger Schachverband',
				'federation'       => '4',
				'parentFederation' => '400',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '60000',
				'clubName'         => 'Schachbund Nordrhein-Westfalen e.V.',
				'federation'       => '6',
				'parentFederation' => '600',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '70000',
				'clubName'         => 'Niedersächsischer Schachverband e.V.',
				'federation'       => '7',
				'parentFederation' => '700',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '80000',
				'clubName'         => 'Schachbund Rheinland-Pfalz e.V.',
				'federation'       => '8',
				'parentFederation' => '800',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => '90000',
				'clubName'         => 'Saarländischer Schachverband',
				'federation'       => '9',
				'parentFederation' => '900',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'A0000',
				'clubName'         => 'Schachverband Schleswig-Holstein',
				'federation'       => 'A',
				'parentFederation' => 'A00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'B0000',
				'clubName'         => 'Landesschachbund Bremen',
				'federation'       => 'B',
				'parentFederation' => 'B00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'C0000',
				'clubName'         => 'Schachverband Württemberg e.V.',
				'federation'       => 'C',
				'parentFederation' => 'C00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'D0000',
				'clubName'         => 'Landesschachbund Brandenburg',
				'federation'       => 'D',
				'parentFederation' => 'D00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'E0000',
				'clubName'         => 'LSV Mecklenburg-Vorpommern',
				'federation'       => 'E',
				'parentFederation' => 'E00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'G0000',
				'clubName'         => 'LSV Sachsen-Anhalt',
				'federation'       => 'G',
				'parentFederation' => 'G00',
				'state'            => 'DELETE_STATE_FALSE',
			),
			array
			(
				'clubVkz'          => 'H0000',
				'clubName'         => 'Thüringer Schachbund',
				'federation'       => 'H',
				'parentFederation' => 'H00',
				'state'            => 'DELETE_STATE_FALSE',
			),
		);

		// Fehlerhafte oder leere Antworten unverändert zurückgeben
		if(!is_array($resultArr) || !isset($resultArr['body']['data']) || !is_array($resultArr['body']['data'])) return $resultArr;

		// Bei gefilterter Abfrage (eine bestimmte VKZ) nur den passenden
		// Verband ergänzen — sonst lieferte eine Vereinsabfrage plötzlich
		// sämtliche Landesverbände mit
		if($nurVkz !== '' && $nurVkz !== null)
		{
			$missingFederations = array_values(array_filter($missingFederations, function($federation) use ($nurVkz)
			{
				return $federation['clubVkz'] == $nurVkz;
			}));

			if(!count($missingFederations)) return $resultArr;
		}

		// Vereine umbauen mit der VKZ als Index für eine schnellere Suche
		$vkzArr = array(); // Array[vkz] = Index
		for($x = 0; $x < count($resultArr['body']['data']); $x++)
		{
			$vkz = $resultArr['body']['data'][$x]['clubVkz'];
			$vkzArr[$vkz] = $x;
		}

		foreach($missingFederations as $federation)
		{
			if(!isset($vkzArr[$federation['clubVkz']]))
			{
				$resultArr['body']['data'][] = $federation;
				$vkzArr[$federation['clubVkz']] = count($resultArr['body']['data']) - 1;
			}
		}

		usort($resultArr['body']['data'], function($a, $b)
		{
			return strcmp($a['clubVkz'], $b['clubVkz']);
		});

		return $resultArr;
	}

}
