<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Fertige, benannte Ranglisten für Deutschland.
 *
 * Diese Klasse beantwortet die Frage „wer sind die besten deutschen Spieler
 * dieser Altersklasse?" vollständig — mit geteilten Platzziffern, ohne
 * Doppelte, mit Verein und Verbandskürzel. Der Aufrufer bekommt eine
 * anzeigefertige Liste und muß weder die Schnittstelle kennen noch wissen, daß
 * hinter den Kulissen drei Bezugsquellen stehen.
 *
 * **Wozu das gut ist:** Das Bundle contao-topwertungszahlen-bundle hat seine
 * Ranglisten bisher selbst über die alte DeWIS-SOAP-Schnittstelle beschafft —
 * mit einem Einzelabruf je Spieler (`tournamentCardForId`), nur um dessen
 * Nation zu erfahren. Das ist mit dem Abschalten von DeWIS erledigt. Statt
 * dort die ganze Beschaffung neu zu bauen, holt sich das Bundle die fertige
 * Liste hier ab; die Kenntnis über Schnittstelle, Notbetrieb, Sperrliste und
 * Verbandskürzel bleibt damit an EINER Stelle.
 *
 * **Drei Bezugsquellen, ein Ergebnis:** `dwz()` läuft über `API::autoQuery()`
 * und erbt damit die dreistufige Auslieferung des Bundles (gültiger
 * Zwischenspeicher → abgelaufener Zwischenspeicher → örtlicher Bestand). Die
 * Liste kommt also auch dann, wenn nu gerade nicht antwortet — das Feld
 * `quelle` sagt, woher. `elo()` beantwortet sich vollständig aus der örtlichen
 * Elo-Tabelle; eine Schnittstelle gibt es dafür nicht.
 *
 * Beispiel:
 *
 *     $liste = Ranglisten::dwz(array('limit' => 50, 'alter_bis' => 20, 'geschlecht' => 'FEMALE'));
 *     foreach($liste['liste'] as $zeile)
 *     {
 *         echo $zeile['platz'].'. '.$zeile['vorname'].' '.$zeile['nachname'].' ('.$zeile['dwz_formatiert'].')';
 *     }
 *
 * Ausführlich beschrieben in docs/ranglisten.md.
 *
 * **Zur Schreibweise `\Contao\Database`:** Der Rest des Bundles benutzt den
 * kurzen Namen `\Database`. Den gibt es aber nur in Contao 4 — in 5.7 ist der
 * Kurzname nicht mehr angemeldet, ein Aufruf endet dort im schweren Fehler.
 * Diese Klasse benutzt deshalb durchweg den vollständigen Namen; er ist in
 * beiden Fassungen gültig.
 */
class Ranglisten
{
	/**
	 * Vorgabewerte aller Parameter. Was der Aufrufer nicht angibt, kommt von
	 * hier — an EINER Stelle, damit `dwz()` und `elo()` sich nicht
	 * auseinanderentwickeln.
	 */
	const VORGABEN = array
	(
		'limit'      => 50,
		'geschlecht' => '',
		'alter_von'  => 0,
		'alter_bis'  => 0,
		'nation'     => 'GER',
		'vkz'        => '',
		'nur_aktive' => true,
	);

	/**
	 * Faktor, um den mehr Datensätze angefordert werden, als am Ende gebraucht
	 * werden.
	 *
	 * **Warum das nötig ist:** Die Schnittstelle kennt keinen Nationenfilter.
	 * Sie liefert die besten Spieler des Verbands, und erst hier fallen die
	 * Ausländer heraus — für 50 Deutsche sind also mehr als 50 Zeilen nötig.
	 * Wer genau `limit` anfordert, bekommt am Ende zu wenige.
	 *
	 * Der alte Weg über DeWIS forderte für 50 Deutsche vorsorglich 1000
	 * Datensätze an — eine Sicherheitsmarge, keine gemessene Notwendigkeit: Die
	 * Schleife brach ab, sobald 50 Deutsche zusammen waren. Faktor 4 ist der
	 * Kompromiß: genug Luft für die Titelträger fremder Nationen an der Spitze,
	 * ohne jedesmal tausend Datensätze durch die Leitung zu ziehen.
	 *
	 * Reicht es im Einzelfall doch nicht, wird EINMAL mit der Obergrenze
	 * nachgefordert (siehe `hole()`). Die Liste ist also auch dann vollständig,
	 * wenn der Faktor zu klein gewählt war — sie kostet dann nur einen zweiten
	 * Abruf. Der Nachschlag entfällt nur, wenn schon der erste Abruf die
	 * Obergrenze angefordert hat; bei sehr großem `limit` kann die Liste
	 * deshalb kürzer ausfallen als gewünscht.
	 */
	const UEBERZUG = 4;

	/**
	 * Obergrenze für die Anzahl angeforderter Datensätze. Sie gilt für den
	 * Faktor oben ebenso wie für den Nachschlag und entspricht der Grenze der
	 * örtlichen Abfrage (`Lokal::MAX_ZEILEN`) — mehr würde die dritte
	 * Bezugsquelle ohnehin nicht liefern.
	 */
	const UEBERZUG_MAX = 1000;

	/**
	 * Obergrenze für `limit`. Mehr Plätze hat keine der zehn Standardlisten,
	 * und eine unbedachte 100000 im Aufruf soll nicht den Speicher sprengen.
	 */
	const LIMIT_MAX = 500;

	/**
	 * Kürzel der Landesverbände, aufgeschlüsselt nach der ERSTEN Stelle der
	 * Vereinskennziffer.
	 *
	 * Die Tabelle steht bewußt hier und wird nicht aus den Vereinsdaten
	 * gelesen: Die Felder `kurzname` und `druckname` sind in
	 * tl_wertungsportal_clubs für alle 17 Landesverbände leer, und die
	 * Schnittstelle kennt Verbände überhaupt nur als Vereine. Die Zuordnung
	 * ist seit Jahren unverändert und stammt aus dem Bestand des
	 * Topwertungszahlen-Bundles.
	 */
	const VERBANDSKUERZEL = array
	(
		'1' => 'BAD', '2' => 'BAY', '3' => 'BER', '4' => 'HAM', '5' => 'HES',
		'6' => 'NRW', '7' => 'NDS', '8' => 'RLP', '9' => 'SAR', 'A' => 'SH',
		'B' => 'BRE', 'C' => 'WÜR', 'D' => 'BRA', 'E' => 'MVP', 'F' => 'SAC',
		'G' => 'SAA', 'H' => 'THÜ', 'L' => 'BSB', 'M' => 'SWA',
	);

	/**
	 * Name des Cache-Verzeichnisses (ohne das Präfix `wp_`). Der Name steht
	 * zusätzlich in `API::cacheSpeicher()`, damit die Systemwartung und der
	 * Elo-Import diesen Speicher mit leeren.
	 */
	const CACHE = 'Rangliste';

	// ─────────────────────────────────────────────
	//  Öffentliche Schnittstelle
	// ─────────────────────────────────────────────

	/**
	 * Liefert die DWZ-Rangliste.
	 *
	 * Der Weg führt über `API::autoQuery()` mit der Funktion `Verbandsliste`
	 * und erbt damit Zwischenspeicher, Notreserve und örtlichen Bestand. Was
	 * die Schnittstelle nicht liefert — Nation, nationaler Titel, Sperrliste —
	 * kommt in einem Zug aus den Spiegeltabellen dazu; erst danach wird
	 * gefiltert, entdoppelt, sortiert und numeriert.
	 *
	 * @param array $params Alle Schlüssel sind freiwillig:
	 *                      `limit` (Anzahl Plätze, Vorgabe 50, höchstens
	 *                      LIMIT_MAX), `geschlecht` ('MALE', 'FEMALE',
	 *                      'DIVERSE' oder leer für alle), `alter_von` /
	 *                      `alter_bis` (Jahre, 0 = keine Grenze; gerechnet
	 *                      wird nach Jahrgang, nicht nach Geburtstag),
	 *                      `nation` (Vorgabe 'GER', leer = alle Nationen),
	 *                      `vkz` (Vereinskennziffer als Präfix, leer = ganzer
	 *                      DSB), `nur_aktive` (nur laufende Spielgenehmigung,
	 *                      Vorgabe true)
	 *
	 * @return array ['stand' => Zeitstempel der Daten,
	 *                'quelle' => 'api' oder 'lokal',
	 *                'liste'  => Array der Zeilen, Feldform siehe leereZeile()]
	 *               Findet sich nichts, ist `liste` ein leeres Array — nie null.
	 */
	public static function dwz(array $params = array()): array
	{
		$p = self::normalisiere($params);
		$schluessel = self::cacheschluessel('dwz', $p);

		$treffer = self::ausCache($schluessel);
		if($treffer !== null) return $treffer;

		$antwort = self::hole($p);
		$zeilen = self::platzziffern(array_slice($antwort['zeilen'], 0, $p['limit']), 'dwz');
		$zeilen = self::mitFideDaten($zeilen);

		$ergebnis = array
		(
			'stand'  => $antwort['stand'],
			'quelle' => $antwort['quelle'],
			'liste'  => $zeilen,
		);

		self::inCache($schluessel, $ergebnis);

		return $ergebnis;
	}

	/**
	 * Liefert die Elo-Rangliste.
	 *
	 * Anders als bei der DWZ gibt es hier keine Schnittstelle: Die
	 * FIDE-Wertungen liegen vollständig in tl_wertungsportal_elo und kommen
	 * über den monatlichen XML-Import dorthin. `quelle` meldet deshalb immer
	 * `lokal`, und `stand` nennt das Listendatum der beteiligten Einträge.
	 *
	 * Geführt wird die Liste von der Elo-Tabelle: Nation, Geschlecht und Alter
	 * kommen aus `country`, `sex` und `birthday`, inaktive Spieler (Kennzeichen
	 * `i` im Feld `flag`) bleiben draußen. Die Person wird über die FIDE-ID
	 * dazugesucht; findet sich keine, bleiben PKZ, Verein, Verbandskürzel und
	 * DWZ leer — der Elo-Eintrag fällt aber NICHT aus der Liste.
	 *
	 * **Zwei Parameter wirken deshalb anders als bei `dwz()`:** `vkz` und
	 * `nur_aktive` beschreiben eine Mitgliedschaft, die es ohne Person gar
	 * nicht gibt. Sie werden nur auf Einträge angewandt, zu denen eine Person
	 * gefunden wurde; Einträge ohne Person bleiben unangetastet. Anders wäre
	 * die Regel „ohne Person bleibt der Eintrag drin" nicht zu halten.
	 *
	 * @param array $params Wie bei `dwz()`
	 *
	 * @return array Wie bei `dwz()`; `quelle` ist immer 'lokal'
	 */
	public static function elo(array $params = array()): array
	{
		$p = self::normalisiere($params);
		$schluessel = self::cacheschluessel('elo', $p);

		$treffer = self::ausCache($schluessel);
		if($treffer !== null) return $treffer;

		$roh = self::eloAbfrage($p);
		$zeilen = self::platzziffern(array_slice(self::eloAufbereiten($roh, $p), 0, $p['limit']), 'elo');

		$ergebnis = array
		(
			'stand'  => self::aeltesterStand($roh),
			'quelle' => 'lokal',
			'liste'  => $zeilen,
		);

		self::inCache($schluessel, $ergebnis);

		return $ergebnis;
	}

	/**
	 * Liefert die zehn benannten Standardlisten mit ihren Parametern.
	 *
	 * Namen und Zuschnitte entsprechen denen, die das Topwertungszahlen-Bundle
	 * seit Jahren führt (dort mit dem Präfix `dwz_` beziehungsweise `elo_`),
	 * damit die bestehenden Auswertungen und Archivdaten weiterpassen.
	 *
	 * ACHTUNG, nicht „aufräumen": **U20 heißt hier `alter_bis` = 20**, nicht
	 * 19. Gerechnet wird nach Jahrgang; wer im laufenden Jahr 20 wird, gehört
	 * nach den Ausschreibungen des DSB noch in die U20. Eine 19 an dieser
	 * Stelle unterschlüge einen ganzen Jahrgang.
	 *
	 * @return array Schlüssel => ['name' => Klartext, 'params' => Parameter für
	 *               dwz() beziehungsweise elo()]
	 */
	public static function listentypen(): array
	{
		return array
		(
			'alle' => array('name' => 'Alle Spieler',    'params' => array()),
			'w'    => array('name' => 'Frauen',          'params' => array('geschlecht' => 'FEMALE')),
			'u20'  => array('name' => 'U20',             'params' => array('alter_bis' => 20)),
			'u20w' => array('name' => 'U20 weiblich',    'params' => array('alter_bis' => 20, 'geschlecht' => 'FEMALE')),
			'50+'  => array('name' => 'Senioren 50+',    'params' => array('alter_von' => 50)),
			'50w+' => array('name' => 'Seniorinnen 50+', 'params' => array('alter_von' => 50, 'geschlecht' => 'FEMALE')),
			'65+'  => array('name' => 'Senioren 65+',    'params' => array('alter_von' => 65)),
			'65w+' => array('name' => 'Seniorinnen 65+', 'params' => array('alter_von' => 65, 'geschlecht' => 'FEMALE')),
			'75+'  => array('name' => 'Senioren 75+',    'params' => array('alter_von' => 75)),
			'75w+' => array('name' => 'Seniorinnen 75+', 'params' => array('alter_von' => 75, 'geschlecht' => 'FEMALE')),
		);
	}

	// ─────────────────────────────────────────────
	//  Parameter und Zwischenspeicher
	// ─────────────────────────────────────────────

	/**
	 * Füllt fehlende Parameter mit den Vorgaben und bringt alle Werte in die
	 * Form, in der der Rest der Klasse mit ihnen rechnet.
	 *
	 * Unbekannte Schlüssel werden verworfen — sonst landeten Tippfehler
	 * unbemerkt im Cache-Schlüssel und erzeugten für dieselbe Liste zwei
	 * Einträge.
	 *
	 * @param array $params Rohe Parameter des Aufrufers
	 *
	 * @return array Vollständiger, typrichtiger Parametersatz
	 */
	protected static function normalisiere(array $params): array
	{
		$p = self::VORGABEN;

		foreach(array_keys(self::VORGABEN) as $feld)
		{
			if(array_key_exists($feld, $params)) $p[$feld] = $params[$feld];
		}

		$p['limit'] = (int) $p['limit'];
		if($p['limit'] < 1) $p['limit'] = (int) self::VORGABEN['limit'];
		if($p['limit'] > self::LIMIT_MAX) $p['limit'] = self::LIMIT_MAX;

		$p['geschlecht'] = strtoupper(trim((string) $p['geschlecht']));
		$p['nation']     = strtoupper(trim((string) $p['nation']));
		$p['vkz']        = trim((string) $p['vkz']);
		$p['alter_von']  = max(0, (int) $p['alter_von']);
		$p['alter_bis']  = max(0, (int) $p['alter_bis']);
		$p['nur_aktive'] = (bool) $p['nur_aktive'];

		return $p;
	}

	/**
	 * Baut den Cache-Schlüssel aus ALLEN Parametern.
	 *
	 * Der Schlüssel muß jeden Wert enthalten, der das Ergebnis verändert —
	 * sonst bekäme die Frauenliste die Zeilen der Gesamtliste. Die Parameter
	 * sind zu diesem Zeitpunkt normalisiert; gleichwertige Aufrufe (`'50'` und
	 * `50`) treffen deshalb denselben Eintrag.
	 *
	 * @param string $art    'dwz' oder 'elo'
	 * @param array  $params Normalisierte Parameter
	 *
	 * @return string Dateiname-tauglicher Schlüssel
	 */
	protected static function cacheschluessel(string $art, array $params): string
	{
		ksort($params);

		return $art.'-'.substr(md5((string) json_encode($params)), 0, 16);
	}

	/**
	 * Liest eine fertige Liste aus dem Zwischenspeicher.
	 *
	 * Die Cachezeit wird von der Funktion `Verbandsliste` übernommen: Die
	 * Rangliste ist deren Auswertung und soll nicht länger gelten als ihre
	 * Grundlage. Ist der Zwischenspeicher in den Einstellungen abgeschaltet,
	 * wird weder gelesen noch geschrieben.
	 *
	 * @param string $schluessel Schlüssel aus cacheschluessel()
	 *
	 * @return array|null Die Liste, oder null wenn nichts Gültiges vorliegt
	 */
	protected static function ausCache(string $schluessel): ?array
	{
		if(!\Schachbulle\ContaoWertungsportalBundle\Helper\API::cacheAktiv('Verbandsliste')) return null;

		$cache = self::cache($schluessel);
		if(!$cache->isCached($schluessel)) return null;

		$daten = $cache->retrieve($schluessel);

		return is_array($daten) && isset($daten['liste']) ? $daten : null;
	}

	/**
	 * Legt eine fertige Liste in den Zwischenspeicher.
	 *
	 * Eine LEERE Liste wird bewußt nicht gespeichert: Sie entsteht auch dann,
	 * wenn die Schnittstelle gerade nichts liefert und der örtliche Bestand
	 * noch leer ist. Wer sie speicherte, hielte den Ausfall über die ganze
	 * Cachezeit fest.
	 *
	 * @param string $schluessel Schlüssel aus cacheschluessel()
	 * @param array  $daten      Rückgabe von dwz()/elo()
	 */
	protected static function inCache(string $schluessel, array $daten): void
	{
		if(!\Schachbulle\ContaoWertungsportalBundle\Helper\API::cacheAktiv('Verbandsliste')) return;
		if(empty($daten['liste'])) return;

		$zeit = (int) \Schachbulle\ContaoWertungsportalBundle\Helper\API::cachezeit('Verbandsliste');
		if($zeit < 1) return;

		self::cache($schluessel)->store($schluessel, $daten, $zeit);
	}

	/**
	 * Erzeugt den Cache-Zugriff für einen Schlüssel.
	 *
	 * Wie im übrigen Bundle liegt je Eintrag eine eigene Datei in einem eigenen
	 * Verzeichnis — eine Sammeldatei müßte bei jedem Zugriff vollständig
	 * gelesen und neu geschrieben werden.
	 *
	 * @param string $schluessel Schlüssel aus cacheschluessel()
	 *
	 * @return \Schachbulle\ContaoHelperBundle\Classes\Cache
	 */
	protected static function cache($schluessel)
	{
		return new \Schachbulle\ContaoHelperBundle\Classes\Cache(array('name' => $schluessel, 'path' => 'wp_'.self::CACHE, 'extension' => '.cache'));
	}

	// ─────────────────────────────────────────────
	//  DWZ-Liste: Beschaffung und Aufbereitung
	// ─────────────────────────────────────────────

	/**
	 * Holt die Spieler für die DWZ-Liste und bereitet sie auf.
	 *
	 * Angefordert wird das Vielfache des Bedarfs (siehe UEBERZUG), weil die
	 * Schnittstelle keinen Nationenfilter kennt. Bleiben danach zu wenige
	 * Zeilen übrig UND hat die Quelle die angeforderte Menge tatsächlich
	 * ausgeschöpft, wird EINMAL mit der Obergrenze nachgefordert: Nur dann kann
	 * es weitere passende Spieler geben. Lieferte die Quelle weniger als
	 * angefordert, ist der Bestand erschöpft und ein zweiter Abruf reine
	 * Verschwendung.
	 *
	 * @param array $params Normalisierte Parameter
	 *
	 * @return array ['zeilen' => aufbereitete Zeilen (unbegrenzt, noch ohne
	 *               Platzziffern), 'stand' => Zeitstempel, 'quelle' => 'api'
	 *               oder 'lokal']
	 */
	protected static function hole(array $params): array
	{
		$angefordert = min($params['limit'] * self::UEBERZUG, self::UEBERZUG_MAX);

		$antwort = self::abrufen($params, $angefordert);
		$roh = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::datensaetze($antwort);
		$zeilen = self::aufbereiten($roh, $params);

		if(count($zeilen) < $params['limit'] && count($roh) >= $angefordert && $angefordert < self::UEBERZUG_MAX)
		{
			$antwort = self::abrufen($params, self::UEBERZUG_MAX);
			$roh = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::datensaetze($antwort);
			$zeilen = self::aufbereiten($roh, $params);
		}

		return array
		(
			'zeilen' => $zeilen,
			'stand'  => self::standDerAntwort($antwort),
			'quelle' => !empty($antwort['lokalquelle']) ? 'lokal' : 'api',
		);
	}

	/**
	 * Ruft die Verbandsliste über `API::autoQuery()` ab.
	 *
	 * Der Cache-Schlüssel der Schnittstellenabfrage trägt ein eigenes Präfix
	 * und enthält jeden Wert, der die Antwort verändert. Er ist bewußt NICHT
	 * mit dem der Verbandsseite identisch: Die Rangliste fordert ein Vielfaches
	 * an Datensätzen an, ihre Antwort taugt für die Verbandsseite also nicht
	 * als Treffer und umgekehrt erst recht nicht.
	 *
	 * @param array $params      Normalisierte Parameter
	 * @param int   $angefordert Anzahl Datensätze für die Schnittstelle
	 *
	 * @return array Antwort im Format der Schnittstelle; bei einem Fehler eine
	 *               Fehlerantwort, aus der `Helper::datensaetze()` ein leeres
	 *               Array macht
	 */
	protected static function abrufen(array $params, int $angefordert): array
	{
		// Die Schnittstelle erwartet das VKZ-Präfix ohne die nachlaufenden
		// Nullen (00000 = ganzer DSB wird zu einer leeren Angabe)
		$zps = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::vkzPraefix($params['vkz']);

		$abfrage = array
		(
			'funktion'   => 'Verbandsliste',
			'cachekey'   => 'rangliste-'.($zps !== '' ? $zps : 'dsb').'-'.$angefordert.'-'.$params['geschlecht'].'-'.$params['alter_von'].'-'.$params['alter_bis'],
			'zps'        => $zps,
			'limit'      => $angefordert,
			'geschlecht' => $params['geschlecht'],
			'alter_von'  => $params['alter_von'],
			'alter_bis'  => $params['alter_bis'],
		);

		$antwort = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery($abfrage);

		return is_array($antwort) ? $antwort : array();
	}

	/**
	 * Ermittelt den Zeitstempel, auf den sich eine Antwort bezieht.
	 *
	 * Kam sie aus dem örtlichen Bestand, ist es dessen Stand; kam sie aus dem
	 * Zwischenspeicher, der Speicherzeitpunkt (bei einer Notreserve der
	 * ursprüngliche, nicht der künstlich verlängerte); kam sie frisch von der
	 * Schnittstelle, ist es jetzt.
	 *
	 * @param array $antwort Antwort von autoQuery()
	 *
	 * @return int Unix-Zeitstempel
	 */
	protected static function standDerAntwort(array $antwort): int
	{
		if(!empty($antwort['lokalquelle'])) return (int) ($antwort['lokalstand'] ?? 0);
		if(!empty($antwort['notstand']))    return (int) $antwort['notstand'];
		if(!empty($antwort['cachestand']))  return (int) $antwort['cachestand'];

		return time();
	}

	/**
	 * Macht aus den Spieler-Datensätzen der Schnittstelle die fertigen Zeilen.
	 *
	 * Reihenfolge der Schritte ist wichtig: Erst die Anreicherung aus den
	 * Spiegeltabellen (die Schnittstelle liefert weder Nation noch nationalen
	 * Titel), dann die Filter, dann das Entdoppeln, dann die Sortierung. Wer
	 * zuerst kürzt und dann filtert, verliert Plätze.
	 *
	 * @param array $roh    Datensätze aus der Antwort
	 * @param array $params Normalisierte Parameter
	 *
	 * @return array Zeilen in Ausgabeform, sortiert, noch ohne Platzziffern
	 */
	protected static function aufbereiten(array $roh, array $params): array
	{
		if(!count($roh)) return array();

		$pkzListe = array();
		foreach($roh as $spieler)
		{
			if(!empty($spieler['nuLigaPersonId'])) $pkzListe[] = $spieler['nuLigaPersonId'];
		}

		$personen = self::personendaten($pkzListe);
		$gesperrt = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist($pkzListe);

		$zeilen = array();

		foreach($roh as $spieler)
		{
			$pkz = (string) ($spieler['nuLigaPersonId'] ?? '');
			$person = $personen[$pkz] ?? array();

			// Gesperrte, verstorbene und im Backend abgeschaltete Personen
			// gehören nicht in eine Rangliste. Der örtliche Weg filtert sie
			// schon in der Abfrage, die Schnittstelle kennt diese Merkmale
			// nicht — deshalb hier noch einmal für alle drei Quellen
			if($pkz !== '' && isset($gesperrt[$pkz])) continue;
			if(!empty($person['verstorben'])) continue;
			if(isset($person['published']) && !$person['published']) continue;

			if(!self::passtNation($person, $params)) continue;
			if(!self::passtGeschlecht($spieler, $params)) continue;
			if(!self::passtAlter((string) ($spieler['birthyear'] ?? ''), $params)) continue;

			// Ohne Wertungszahl gibt es keinen Platz in einer Rangliste
			$dwz = (int) ($spieler['rating'] ?? 0);
			if($dwz < 1) continue;

			$mitglied = self::mitgliedschaft($spieler, $params);
			if($mitglied === null) continue;

			$zeile = self::leereZeile();
			$zeile['pkz']             = $pkz;
			$zeile['vorname']         = (string) ($spieler['firstname'] ?? '');
			$zeile['nachname']        = (string) ($spieler['lastname'] ?? '');
			$zeile['titel']           = (string) ($person['titel'] ?? '');
			$zeile['geschlecht']      = (string) ($spieler['gender'] ?? '');
			$zeile['geburtsjahr']     = self::jahrgang((string) ($spieler['birthyear'] ?? ''));
			$zeile['dwz']             = $dwz;
			$zeile['dwz_index']       = (int) ($spieler['index'] ?? 0);
			$zeile['dwz_formatiert']  = self::dwzFormat($dwz, $zeile['dwz_index']);
			$zeile['fide_id']         = (int) ($spieler['fideId'] ?? 0);
			// Die Föderation zuerst, sonst die Mitgliederdatei — dieselbe
			// Reihenfolge, nach der auch gefiltert wurde. Anders stünde in der
			// Ausgabe ein Wert, der der Liste widerspricht, in der er steht
			$zeile['nation']          = ($person['foederation'] ?? '') !== '' ? (string) $person['foederation'] : (string) ($person['nation'] ?? '');
			$zeile['vkz']             = $mitglied['vkz'];
			$zeile['verein']          = $mitglied['verein'];
			$zeile['verbandskuerzel'] = self::verbandskuerzel($mitglied['vkz']);

			$zeilen[] = $zeile;
		}

		return self::sortiere(self::entdoppeln($zeilen));
	}

	/**
	 * Lädt Nation, nationalen Titel und Status mehrerer Personen aus der
	 * Spiegeltabelle — in EINER Abfrage je 500 Kennziffern.
	 *
	 * **Warum das sein muß:** Die Schnittstelle liefert diese Felder gar nicht;
	 * `nation` und `titel` kommen ausschließlich aus dem Import der
	 * Vereinsmitglieder-CSV. Ohne diesen Schritt gäbe es keinen Nationenfilter
	 * und keinen Titel in der Ausgabe.
	 *
	 * Zusätzlich wird die **Föderation** ermittelt — der Verband, für den ein
	 * Spieler bei der FIDE antritt. Sie stammt aus `fideNation` der Person und,
	 * wenn die leer ist, aus `country` der Elo-Tabelle über die FIDE-ID. Damit
	 * steht auch für Personen eine Nationsangabe bereit, deren Datensatz allein
	 * über die Schnittstelle entstanden ist — und genau daran scheiterte der
	 * Nationenfilter bis 1.34.0 (siehe `passtNation()`).
	 *
	 * @param array $pkzListe nuLigaPersonId der gesuchten Personen
	 *
	 * @return array PKZ => ['nation', 'foederation', 'titel',
	 *               'verstorben' (bool), 'published' (bool)]; nicht gefundene
	 *               Personen fehlen
	 */
	protected static function personendaten(array $pkzListe): array
	{
		// Leere Kennziffern aussortieren — aber ohne array_filter() ohne
		// Rückruf: Das würde auch die Zeichenkette „0" verwerfen
		$pkzListe = array_values(array_unique(array_filter(array_map('strval', $pkzListe), static function(string $wert): bool { return $wert !== ''; })));
		if(!count($pkzListe)) return array();

		$daten = array();
		$offen = array();

		foreach(array_chunk($pkzListe, 500) as $block)
		{
			$platzhalter = implode(',', array_fill(0, count($block), '?'));
			$objPerson = \Contao\Database::getInstance()->prepare("SELECT nuLigaPersonId, nation, fideNation, titel, verstorben, published, fideId FROM tl_wertungsportal_persons WHERE nuLigaPersonId IN ($platzhalter)")
			                                     ->execute($block);

			while($objPerson->next())
			{
				$pkz = (string) $objPerson->nuLigaPersonId;

				$daten[$pkz] = array
				(
					'nation'      => (string) $objPerson->nation,
					'foederation' => (string) $objPerson->fideNation,
					'titel'       => (string) $objPerson->titel,
					'verstorben'  => (bool) $objPerson->verstorben,
					'published'   => (bool) $objPerson->published,
				);

				// Föderation unbekannt: Die Elo-Tabelle weiß sie meistens.
				// Gesammelt wird nach FIDE-ID, damit alle in einem Zug
				// nachgeschlagen werden.
				//
				// WICHTIG: Nachgeschlagen wird auch dann, wenn die Nation
				// bekannt ist. Die Föderation entscheidet vor ihr (siehe
				// `passtNation()`), und `fideNation` ist im Bestand fast
				// immer leer — ohne diesen Nachschlag käme die erste Stufe
				// gar nicht zum Tragen
				if($daten[$pkz]['foederation'] === '' && (int) $objPerson->fideId > 0)
				{
					$offen[(int) $objPerson->fideId][] = $pkz;
				}
			}
		}

		if(count($offen))
		{
			$fide = self::eloDaten(array_keys($offen));

			foreach($offen as $fideId => $kennziffern)
			{
				$land = (string) ($fide[$fideId]['country'] ?? '');
				if($land === '') continue;

				foreach($kennziffern as $pkz) $daten[$pkz]['foederation'] = $land;
			}
		}

		return $daten;
	}

	/**
	 * Reichert fertige Zeilen um die FIDE-Daten an — Elo, Partienzahl und die
	 * beiden Titelfelder.
	 *
	 * Bewußt eine eigene Abfrage statt `Helper::getFIDEDatenListe()`: Jene
	 * liefert weder die Partienzahl noch den Frauentitel, beide gehören aber
	 * zur Ausgabe dieser Klasse.
	 *
	 * Der Schritt läuft NACH dem Kürzen auf `limit` — angereichert werden nur
	 * die Zeilen, die auch ausgegeben werden.
	 *
	 * @param array $zeilen Zeilen in Ausgabeform
	 *
	 * @return array Dieselben Zeilen mit gefüllten FIDE-Feldern
	 */
	protected static function mitFideDaten(array $zeilen): array
	{
		$ids = array();
		foreach($zeilen as $zeile)
		{
			if($zeile['fide_id'] > 0) $ids[] = $zeile['fide_id'];
		}

		$fide = self::eloDaten($ids);

		foreach($zeilen as $i => $zeile)
		{
			$satz = $fide[$zeile['fide_id']] ?? null;
			if($satz === null) continue;

			$zeilen[$i]['elo']          = (int) $satz['rating'];
			$zeilen[$i]['elo_partien']  = (int) $satz['games'];
			$zeilen[$i]['fide_titel']   = (string) $satz['title'];
			$zeilen[$i]['fide_titel_w'] = (string) $satz['w_title'];

			// `nation` wird hier NICHT mehr angefaßt: Die Föderation ist schon
			// beim Filtern eingesetzt worden (siehe personendaten()), Ausgabe
			// und Filter sagen dadurch dasselbe
		}

		return $zeilen;
	}

	// ─────────────────────────────────────────────
	//  Elo-Liste
	// ─────────────────────────────────────────────

	/**
	 * Fragt die Elo-Tabelle ab.
	 *
	 * Gefiltert wird direkt in SQL, weil hier — anders als bei der DWZ — alle
	 * nötigen Merkmale in derselben Tabelle stehen. Nur `vkz` und `nur_aktive`
	 * bleiben außen vor; sie hängen an der Person und werden erst in
	 * `eloAufbereiten()` angewandt.
	 *
	 * Angefordert wird auch hier ein Vielfaches: Die Person kann fehlen oder
	 * die Mitgliedschaftsfilter nicht erfüllen.
	 *
	 * @param array $params Normalisierte Parameter
	 *
	 * @return array Datenbankzeilen als Arrays
	 */
	protected static function eloAbfrage(array $params): array
	{
		$bedingungen = array("e.published = '1'", 'e.rating > 0');
		$werte = array();

		// Inaktive Spieler tragen im Kennzeichen ein „i" (FIDE-Konvention).
		// Ein leeres Kennzeichen erfüllt die Bedingung von selbst
		$bedingungen[] = "e.flag NOT LIKE '%i%'";

		// Leere Nation zählt als passend — genau wie bei der DWZ-Liste, damit
		// beide Listen dieselbe Regel kennen
		if($params['nation'] !== '')
		{
			$bedingungen[] = "(e.country = ? OR e.country = '')";
			$werte[] = $params['nation'];
		}

		// Die Elo-Tabelle führt das Geschlecht als M/F (FIDE-Kürzel), die
		// Parameter dieser Klasse sprechen die Form der nu-Schnittstelle
		$sex = self::eloGeschlecht($params['geschlecht']);
		if($sex !== '')
		{
			$bedingungen[] = 'e.sex = ?';
			$werte[] = $sex;
		}

		$jahr = (int) date('Y');

		if($params['alter_von'] > 0)
		{
			$bedingungen[] = 'e.birthday > 0 AND e.birthday <= ?';
			$werte[] = $jahr - $params['alter_von'];
		}
		if($params['alter_bis'] > 0)
		{
			$bedingungen[] = 'e.birthday > 0 AND e.birthday >= ?';
			$werte[] = $jahr - $params['alter_bis'];
		}

		$angefordert = min($params['limit'] * self::UEBERZUG, self::UEBERZUG_MAX);

		$sql = 'SELECT e.fideid, e.prename, e.surname, e.title, e.w_title, e.country, e.sex, e.rating, e.games, e.birthday, e.elodate, e.tstamp'
		     . ' FROM tl_wertungsportal_elo e'
		     . ' WHERE '.implode(' AND ', $bedingungen)
		     . ' ORDER BY e.rating DESC LIMIT '.$angefordert;

		$objElo = \Contao\Database::getInstance()->prepare($sql)->execute(...$werte);

		$zeilen = array();
		while($objElo->next())
		{
			$zeilen[] = $objElo->row();
		}

		return $zeilen;
	}

	/**
	 * Macht aus den Zeilen der Elo-Tabelle die fertigen Ausgabezeilen.
	 *
	 * Die Person wird über die FIDE-ID dazugesucht. Fehlt sie, bleiben die
	 * Personenfelder leer und der Eintrag bleibt in der Liste — die
	 * Mitgliedschaftsfilter `vkz` und `nur_aktive` können auf ihn dann gar
	 * nicht angewandt werden (siehe Klassenkommentar von `elo()`).
	 *
	 * @param array $roh    Zeilen aus eloAbfrage()
	 * @param array $params Normalisierte Parameter
	 *
	 * @return array Zeilen in Ausgabeform, sortiert, noch ohne Platzziffern
	 */
	protected static function eloAufbereiten(array $roh, array $params): array
	{
		if(!count($roh)) return array();

		$personen = self::personenZuFideIds(array_column($roh, 'fideid'));
		$gesperrt = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($personen, 'pkz'));

		$zeilen = array();

		foreach($roh as $satz)
		{
			$fideId = (int) $satz['fideid'];
			$person = $personen[$fideId] ?? null;

			if($person !== null)
			{
				if($person['pkz'] !== '' && isset($gesperrt[$person['pkz']])) continue;
				if($person['verstorben']) continue;
				if(!$person['published']) continue;

				$mitglied = self::mitgliedschaft($person['roh'], $params);
				if($mitglied === null) continue;
			}
			else
			{
				$mitglied = array('vkz' => '', 'verein' => '');
			}

			$zeile = self::leereZeile();
			$zeile['pkz']             = $person['pkz'] ?? '';
			// Die Schreibweise des DSB ist der der FIDE vorzuziehen: Dort
			// stehen Umlaute als „ue"/„oe", und Doppelnamen sind zusammengezogen
			$zeile['vorname']         = ($person['vorname'] ?? '') !== '' ? $person['vorname'] : (string) $satz['prename'];
			$zeile['nachname']        = ($person['nachname'] ?? '') !== '' ? $person['nachname'] : (string) $satz['surname'];
			$zeile['titel']           = (string) ($person['titel'] ?? '');
			$zeile['geschlecht']      = self::nuGeschlecht((string) $satz['sex']);
			$zeile['geburtsjahr']     = (int) $satz['birthday'];
			$zeile['dwz']             = (int) ($person['dwz'] ?? 0);
			$zeile['dwz_index']       = (int) ($person['dwz_index'] ?? 0);
			$zeile['dwz_formatiert']  = self::dwzFormat($zeile['dwz'], $zeile['dwz_index']);
			$zeile['fide_id']         = $fideId;
			$zeile['elo']             = (int) $satz['rating'];
			$zeile['elo_partien']     = (int) $satz['games'];
			$zeile['fide_titel']      = (string) $satz['title'];
			$zeile['fide_titel_w']    = (string) $satz['w_title'];
			// Die Föderation der Elo-Tabelle zuerst: Nach ihr wurde gefiltert,
			// sie gehört also auch in die Ausgabe
			$zeile['nation']          = (string) $satz['country'] !== '' ? (string) $satz['country'] : (string) ($person['nation'] ?? '');
			$zeile['vkz']             = $mitglied['vkz'];
			$zeile['verein']          = $mitglied['verein'];
			$zeile['verbandskuerzel'] = self::verbandskuerzel($mitglied['vkz']);

			$zeilen[] = $zeile;
		}

		return self::sortiere(self::entdoppeln($zeilen), 'elo');
	}

	/**
	 * Sucht zu FIDE-IDs die zugehörigen Personen samt ihren laufenden
	 * Mitgliedschaften — in einer Abfrage je 500 IDs.
	 *
	 * Die Mitgliedschaften werden in der Feldform der Schnittstelle
	 * nachgebildet (`memberships` mit `vkz`, `clubName`, `licenceState`), damit
	 * `mitgliedschaft()` für beide Listen dieselbe Auswahllogik benutzen kann.
	 *
	 * @param array $fideIds FIDE-IDs
	 *
	 * @return array FIDE-ID => ['pkz', 'vorname', 'nachname', 'titel',
	 *               'nation', 'dwz', 'dwz_index', 'verstorben' (bool),
	 *               'published' (bool), 'roh' => Datensatz mit 'memberships']
	 */
	protected static function personenZuFideIds(array $fideIds): array
	{
		$fideIds = array_values(array_unique(array_filter(array_map('intval', $fideIds))));
		if(!count($fideIds)) return array();

		$personen = array();
		$idZuFide = array();

		foreach(array_chunk($fideIds, 500) as $block)
		{
			// Abgeschaltete und gesperrte Personen werden hier NICHT
			// ausgeschlossen: Wer sie in der Abfrage wegläßt, findet zu ihrem
			// Elo-Eintrag keine Person mehr — und der Eintrag stünde dann mit
			// dem FIDE-Namen anonym in der Liste, statt zu verschwinden.
			// Aussortiert wird deshalb erst in eloAufbereiten()
			$platzhalter = implode(',', array_fill(0, count($block), '?'));
			$objPerson = \Contao\Database::getInstance()->prepare("SELECT p.id, p.nuLigaPersonId, p.fideId, p.firstname, p.lastname, p.titel, p.nation, p.rating, p.`index`, p.verstorben, p.published FROM tl_wertungsportal_persons p WHERE p.fideId IN ($platzhalter)")
			                                     ->execute($block);

			while($objPerson->next())
			{
				// `index` ist ein reserviertes MySQL-Wort und wird deshalb über
				// die Zeile gelesen, nicht über den Eigenschaftszugriff
				$zeile = $objPerson->row();
				$fideId = (int) $zeile['fideId'];

				// Doppelte FIDE-IDs sind Datenfehler; die zuerst gefundene
				// Person gewinnt, damit die Liste stabil bleibt
				if(isset($personen[$fideId])) continue;

				$idZuFide[(int) $zeile['id']] = $fideId;
				$personen[$fideId] = array
				(
					'pkz'        => (string) $zeile['nuLigaPersonId'],
					'vorname'    => (string) $zeile['firstname'],
					'nachname'   => (string) $zeile['lastname'],
					'titel'      => (string) $zeile['titel'],
					'nation'     => (string) $zeile['nation'],
					'dwz'        => (int) $zeile['rating'],
					'dwz_index'  => (int) $zeile['index'],
					'verstorben' => (bool) $zeile['verstorben'],
					'published'  => (bool) $zeile['published'],
					'roh'        => array('memberships' => array()),
				);
			}
		}

		if(!count($idZuFide)) return $personen;

		$ids = implode(',', array_keys($idZuFide));
		$objMitglied = \Contao\Database::getInstance()->prepare("SELECT m.pid, m.vkz, m.clubName, m.licenceState FROM tl_wertungsportal_persons_memberships m WHERE m.pid IN ($ids) AND m.published = '1' ORDER BY m.licenceState")
		                                       ->execute();

		while($objMitglied->next())
		{
			$fideId = $idZuFide[(int) $objMitglied->pid] ?? 0;
			if(!$fideId) continue;

			$personen[$fideId]['roh']['memberships'][] = array
			(
				'vkz'          => (string) $objMitglied->vkz,
				'clubName'     => (string) $objMitglied->clubName,
				'licenceState' => (string) $objMitglied->licenceState,
			);
		}

		return $personen;
	}

	/**
	 * Lädt FIDE-Datensätze zu mehreren FIDE-IDs.
	 *
	 * @param array $fideIds FIDE-IDs
	 *
	 * @return array FIDE-ID => Datenbankzeile (rating, games, title, w_title,
	 *               country); nicht gefundene IDs fehlen
	 */
	protected static function eloDaten(array $fideIds): array
	{
		$fideIds = array_values(array_unique(array_filter(array_map('intval', $fideIds))));
		if(!count($fideIds)) return array();

		$daten = array();

		foreach(array_chunk($fideIds, 500) as $block)
		{
			$platzhalter = implode(',', array_fill(0, count($block), '?'));
			$objElo = \Contao\Database::getInstance()->prepare("SELECT fideid, rating, games, title, w_title, country FROM tl_wertungsportal_elo WHERE fideid IN ($platzhalter)")
			                                  ->execute($block);

			while($objElo->next())
			{
				$daten[(int) $objElo->fideid] = $objElo->row();
			}
		}

		return $daten;
	}

	/**
	 * Ermittelt den Stand der Elo-Liste — den ältesten Listenzeitpunkt der
	 * beteiligten Einträge.
	 *
	 * `elodate` trägt das Datum der FIDE-Liste, aus der ein Eintrag stammt.
	 * Alle Einträge eines Importlaufs teilen sich diesen Wert; der älteste ist
	 * damit der ehrliche Stand der ganzen Liste. Fehlt er, wird auf den
	 * Zeitstempel des Datensatzes ausgewichen.
	 *
	 * @param array $roh Zeilen aus eloAbfrage()
	 *
	 * @return int Unix-Zeitstempel, 0 wenn keine Zeile einen Wert trägt
	 */
	protected static function aeltesterStand(array $roh): int
	{
		$stand = 0;

		foreach($roh as $satz)
		{
			$wert = (int) ($satz['elodate'] ?? 0);
			if($wert < 1) $wert = (int) ($satz['tstamp'] ?? 0);
			if($wert < 1) continue;

			$stand = ($stand === 0) ? $wert : min($stand, $wert);
		}

		return $stand;
	}

	// ─────────────────────────────────────────────
	//  Filter, Sortierung, Platzziffern
	// ─────────────────────────────────────────────

	/**
	 * Prüft, ob eine Person zum Nationenfilter paßt.
	 *
	 * Gefragt wird in drei Stufen, und die erste bekannte Antwort entscheidet:
	 *
	 * 1. **Die FIDE-Föderation** — `persons.fideNation`, ersatzweise `country`
	 *    der Elo-Tabelle über die FIDE-ID.
	 * 2. **Die Nation der Mitgliederdatei** (`persons.nation`), wenn keine
	 *    Föderation bekannt ist.
	 * 3. **Ist beides unbekannt, paßt die Person.** Ausgeschlossen wird nur,
	 *    wer nachweislich anderswo geführt wird.
	 *
	 * **Warum die Föderation und nicht die Staatsangehörigkeit zuerst kommt:**
	 * Eine Rangliste beantwortet die Frage „wer spielt für Deutschland?", nicht
	 * „wer hat einen deutschen Paß?". Georg Meier ist deutscher
	 * Staatsangehöriger, tritt bei der FIDE aber für Uruguay an und darf für
	 * Deutschland nicht spielen — in einer deutschen Rangliste hat er nichts zu
	 * suchen, und Leser, die ihn dort fänden, würden sich zu Recht wundern. Die
	 * umgekehrte Reihenfolge stünde außerdem quer zur Elo-Liste, die schon
	 * immer nach `country` der Elo-Tabelle filtert.
	 *
	 * Die zweite Stufe bleibt trotzdem nötig: Ohne FIDE-Eintrag gibt es keine
	 * Föderation, und dann ist die Mitgliederdatei die beste vorhandene
	 * Auskunft.
	 *
	 * **Zur Vorgeschichte:** Bis 1.34.0 gab es nur die Mitgliederdatei und
	 * „unbekannt, also behalten". Da `nation` im Livebestand kaum gepflegt ist,
	 * fiel praktisch jede Prüfung auf die dritte Stufe — und in den
	 * Top-10-Listen standen Ausländer. 1.35.0 nahm die Föderation dazu, aber an
	 * zweiter Stelle; seit 1.35.1 steht sie vorn, wo sie hingehört.
	 *
	 * @param array $person Datensatz aus personendaten() (kann leer sein)
	 * @param array $params Normalisierte Parameter
	 *
	 * @return bool true = behalten
	 */
	protected static function passtNation(array $person, array $params): bool
	{
		if($params['nation'] === '') return true;

		$foederation = strtoupper(trim((string) ($person['foederation'] ?? '')));
		if($foederation !== '' && $foederation !== '-') return $foederation === $params['nation'];

		$nation = strtoupper(trim((string) ($person['nation'] ?? '')));
		if($nation !== '' && $nation !== '-') return $nation === $params['nation'];

		return true;
	}

	/**
	 * Prüft, ob ein Spieler zum Geschlechtsfilter paßt.
	 *
	 * Die Prüfung läuft hier noch einmal, obwohl Schnittstelle und örtliche
	 * Abfrage bereits filtern: Nur so liefern alle drei Bezugsquellen dasselbe
	 * Ergebnis, auch wenn eine von ihnen den Filter einmal anders auslegt.
	 *
	 * @param array $spieler Datensatz der Schnittstelle
	 * @param array $params  Normalisierte Parameter
	 *
	 * @return bool true = behalten
	 */
	protected static function passtGeschlecht(array $spieler, array $params): bool
	{
		if($params['geschlecht'] === '') return true;

		return strtoupper((string) ($spieler['gender'] ?? '')) === $params['geschlecht'];
	}

	/**
	 * Prüft, ob ein Geburtsjahr in die Altersspanne paßt.
	 *
	 * Gerechnet wird nach JAHRGANG, nicht nach Geburtstag: Wer im laufenden
	 * Jahr 20 wird, gilt das ganze Jahr über als U20. So sind die
	 * Altersklassen des DSB ausgeschrieben, und so rechnet auch die
	 * Schnittstelle.
	 *
	 * Ist ein Filter gesetzt und das Geburtsjahr unbekannt, fällt der Spieler
	 * heraus — eine Altersklassenliste darf niemanden enthalten, dessen Alter
	 * niemand kennt.
	 *
	 * @param string $birthyear Rohwert aus der Schnittstelle: Jahr (JJJJ) oder
	 *                          volles Datum (TT.MM.JJJJ)
	 * @param array  $params    Normalisierte Parameter
	 *
	 * @return bool true = behalten
	 */
	protected static function passtAlter(string $birthyear, array $params): bool
	{
		if($params['alter_von'] < 1 && $params['alter_bis'] < 1) return true;

		$jahrgang = self::jahrgang($birthyear);
		if($jahrgang < 1) return false;

		$jahr = (int) date('Y');

		if($params['alter_von'] > 0 && $jahrgang > $jahr - $params['alter_von']) return false;
		if($params['alter_bis'] > 0 && $jahrgang < $jahr - $params['alter_bis']) return false;

		return true;
	}

	/**
	 * Liest den Jahrgang aus dem Feld `birthyear`.
	 *
	 * Das Feld führt entweder das Jahr (JJJJ) oder ein volles Datum
	 * (TT.MM.JJJJ, so schreibt es der CSV-Import) — in beiden Fällen stehen die
	 * letzten vier Zeichen für das Jahr. Genauso rechnet die örtliche Abfrage
	 * mit `RIGHT(birthyear, 4)`.
	 *
	 * @param string $birthyear Rohwert
	 *
	 * @return int Jahrgang, 0 wenn unbekannt oder unbrauchbar
	 */
	protected static function jahrgang(string $birthyear): int
	{
		$jahr = substr(trim($birthyear), -4);

		if(strlen($jahr) < 4 || !ctype_digit($jahr)) return 0;

		return (int) $jahr;
	}

	/**
	 * Wählt die Mitgliedschaft, mit der ein Spieler in der Liste erscheint.
	 *
	 * Eine Person kann in mehreren Vereinen gemeldet sein. Genommen wird die
	 * erste passende, wobei eine aktive Spielgenehmigung Vorrang hat — sonst
	 * stünde ein Spieler mit seinem Zweitverein in der Liste. Ist `vkz`
	 * gesetzt, zählen nur Mitgliedschaften, deren Kennziffer mit diesem Präfix
	 * beginnt.
	 *
	 * @param array $spieler Datensatz mit `memberships`
	 * @param array $params  Normalisierte Parameter
	 *
	 * @return array|null ['vkz', 'verein'] oder null, wenn keine Mitgliedschaft
	 *                    paßt — dann gehört der Spieler nicht in die Liste
	 */
	protected static function mitgliedschaft(array $spieler, array $params): ?array
	{
		$treffer = null;

		foreach((array) ($spieler['memberships'] ?? array()) as $mitglied)
		{
			$vkz = (string) ($mitglied['vkz'] ?? '');
			$status = (string) ($mitglied['licenceState'] ?? '');

			if($params['vkz'] !== '' && strncmp($vkz, $params['vkz'], strlen($params['vkz'])) !== 0) continue;
			if($params['nur_aktive'] && $status !== 'ACTIVE') continue;

			$satz = array('vkz' => $vkz, 'verein' => (string) ($mitglied['clubName'] ?? ''));

			// Eine aktive Mitgliedschaft beendet die Suche sofort; alles andere
			// wird nur gemerkt, falls nichts Besseres kommt
			if($status === 'ACTIVE') return $satz;
			if($treffer === null) $treffer = $satz;
		}

		// Ohne Mitgliedschaftsfilter darf ein Spieler auch ganz ohne
		// Vereinsangabe in der Liste stehen — die Schnittstelle liefert die
		// Mitgliedschaften nicht immer mit
		if($treffer === null && !$params['nur_aktive'] && $params['vkz'] === '')
		{
			return array('vkz' => '', 'verein' => '');
		}

		return $treffer;
	}

	/**
	 * Entfernt Doppeleinträge.
	 *
	 * Dieselbe Person kann in der Antwort mehrfach vorkommen — bei mehreren
	 * Mitgliedschaften oder wenn Schnittstelle und örtlicher Bestand
	 * zusammengeführt wurden. Es gewinnt der zuerst gefundene Eintrag, also die
	 * bereits nach Mitgliedschaftsregel ausgewählte Zeile.
	 *
	 * Personen ohne Kennziffer (Elo-Einträge ohne zugeordnete Person) werden
	 * über die FIDE-ID unterschieden; fehlt auch die, bleibt die Zeile stehen.
	 *
	 * @param array $zeilen Zeilen in Ausgabeform
	 *
	 * @return array Zeilen ohne Doppelte, Reihenfolge unverändert
	 */
	protected static function entdoppeln(array $zeilen): array
	{
		$gesehen = array();
		$ergebnis = array();

		foreach($zeilen as $zeile)
		{
			$schluessel = $zeile['pkz'] !== '' ? 'p'.$zeile['pkz'] : ($zeile['fide_id'] > 0 ? 'f'.$zeile['fide_id'] : '');

			if($schluessel !== '')
			{
				if(isset($gesehen[$schluessel])) continue;
				$gesehen[$schluessel] = true;
			}

			$ergebnis[] = $zeile;
		}

		return $ergebnis;
	}

	/**
	 * Sortiert die Liste.
	 *
	 * Erster Schlüssel ist die Wertungszahl. Bei Gleichstand entscheidet bei
	 * der DWZ der höhere Wertungsindex (mehr ausgewertete Turniere), bei Elo
	 * die höhere Partienzahl; danach Nachname und Vorname, damit die
	 * Reihenfolge nicht von der Speicherreihenfolge der Datenbank abhängt und
	 * zwei Aufrufe dasselbe liefern.
	 *
	 * **Warum in PHP und nicht in SQL:** Ein zweiter Sortierschlüssel in der
	 * Abfrage macht den Index (published, rating) für die Ordnung nutzlos —
	 * MySQL sortiert dann 95.000 Zeilen nach (gemessen 972 ms statt 40 ms).
	 * Bei wenigen hundert Zeilen kostet die Nachsortierung hier nichts.
	 *
	 * @param array  $zeilen Zeilen in Ausgabeform
	 * @param string $art    'dwz' oder 'elo' — bestimmt die Wertungsspalte
	 *
	 * @return array Sortierte Zeilen
	 */
	protected static function sortiere(array $zeilen, string $art = 'dwz'): array
	{
		$wertung = $art === 'elo' ? 'elo' : 'dwz';
		$zweit   = $art === 'elo' ? 'elo_partien' : 'dwz_index';

		usort($zeilen, function($a, $b) use ($wertung, $zweit)
		{
			if($a[$wertung] !== $b[$wertung]) return $b[$wertung] <=> $a[$wertung];
			if($a[$zweit] !== $b[$zweit]) return $b[$zweit] <=> $a[$zweit];

			$nach = strcmp($a['nachname'], $b['nachname']);
			if($nach !== 0) return $nach;

			return strcmp($a['vorname'], $b['vorname']);
		});

		return $zeilen;
	}

	/**
	 * Vergibt die Platzziffern — geteilt bei gleicher Wertungszahl.
	 *
	 * Drei Spieler mit 2400, 2350 und 2350 stehen auf den Plätzen 1, 2 und 2;
	 * der nächste bekommt die 4, nicht die 3. So ist es in Ranglisten üblich,
	 * und so hat es das Topwertungszahlen-Bundle immer schon gemacht.
	 *
	 * Die Felder `platz` und `rang` tragen denselben Wert. Beide gibt es, weil
	 * die Auswertungen des Topwertungszahlen-Bundles die Spalte `rank` führen,
	 * die Anzeige aber vom „Platz" spricht — so muß niemand umbenennen.
	 *
	 * Welche Spalte über den Gleichstand entscheidet, sagt `$art`: In der
	 * Elo-Liste haben viele Zeilen ZUSÄTZLICH eine DWZ, und die dürfte hier auf
	 * keinen Fall die Platzziffer bestimmen.
	 *
	 * @param array  $zeilen Sortierte Zeilen in Ausgabeform
	 * @param string $art    'dwz' oder 'elo' — die maßgebliche Wertungsspalte
	 *
	 * @return array Zeilen mit gefüllten Feldern `platz` und `rang`
	 */
	protected static function platzziffern(array $zeilen, string $art = 'dwz'): array
	{
		$wertungsfeld = $art === 'elo' ? 'elo' : 'dwz';
		$platz = 0;
		$letzte = null;
		$laufend = 0;

		foreach($zeilen as $i => $zeile)
		{
			$laufend++;
			$wertung = (int) $zeile[$wertungsfeld];

			// Neue Wertungszahl: Der Platz springt auf die laufende Nummer.
			// Gleiche Wertungszahl: Der vorige Platz wird geteilt
			if($letzte === null || $wertung !== $letzte)
			{
				$platz = $laufend;
				$letzte = $wertung;
			}

			$zeilen[$i]['platz'] = $platz;
			$zeilen[$i]['rang'] = $platz;
		}

		return $zeilen;
	}

	// ─────────────────────────────────────────────
	//  Kleinteile
	// ─────────────────────────────────────────────

	/**
	 * Setzt DWZ und Wertungsindex zur üblichen Schreibweise zusammen.
	 *
	 * @param int $dwz   Wertungszahl
	 * @param int $index Wertungsindex (Anzahl der Auswertungen)
	 *
	 * @return string Etwa „1834-42", ohne Index nur die Zahl, ohne DWZ leer
	 */
	protected static function dwzFormat(int $dwz, int $index): string
	{
		if($dwz < 1) return '';

		return $index > 0 ? $dwz.'-'.$index : (string) $dwz;
	}

	/**
	 * Liefert das Kürzel des Landesverbands zu einer Vereinskennziffer.
	 *
	 * Maßgeblich ist die ERSTE Stelle der Kennziffer; sie bezeichnet den
	 * Landesverband. Ist sie unbekannt (neue Kennziffer, Tippfehler), kommt ein
	 * leerer Text zurück statt einer Warnung.
	 *
	 * @param string $vkz Vereinskennziffer
	 *
	 * @return string Kürzel wie „BER", oder leer
	 */
	protected static function verbandskuerzel(string $vkz): string
	{
		if($vkz === '') return '';

		return self::VERBANDSKUERZEL[strtoupper(substr($vkz, 0, 1))] ?? '';
	}

	/**
	 * Übersetzt die Geschlechtsangabe dieser Klasse in das Kürzel der
	 * Elo-Tabelle.
	 *
	 * @param string $geschlecht 'MALE', 'FEMALE', 'DIVERSE' oder leer
	 *
	 * @return string 'M', 'F' oder leer (letzteres auch für DIVERSE — die FIDE
	 *                kennt die Angabe nicht, ein Filter darauf ergäbe eine
	 *                immer leere Liste)
	 */
	protected static function eloGeschlecht(string $geschlecht): string
	{
		if($geschlecht === 'MALE') return 'M';
		if($geschlecht === 'FEMALE') return 'F';

		return '';
	}

	/**
	 * Übersetzt das Kürzel der Elo-Tabelle in die Geschlechtsangabe der
	 * nu-Schnittstelle, damit beide Listen dieselbe Form ausgeben.
	 *
	 * @param string $sex 'M', 'F' oder leer
	 *
	 * @return string 'MALE', 'FEMALE' oder leer
	 */
	protected static function nuGeschlecht(string $sex): string
	{
		$sex = strtoupper(trim($sex));

		if($sex === 'M') return 'MALE';
		if($sex === 'F') return 'FEMALE';

		return '';
	}

	/**
	 * Liefert eine leere Ausgabezeile mit allen Feldern.
	 *
	 * Eigene Methode, damit die Feldform an EINER Stelle steht: Kommt ein Feld
	 * hinzu, fehlt es sonst in einer der beiden Listen, und der Aufrufer
	 * bekommt für manche Zeilen eine Warnung statt eines leeren Wertes.
	 *
	 * @return array Alle Ausgabefelder mit Leerwert
	 */
	protected static function leereZeile(): array
	{
		return array
		(
			'platz'           => 0,   // Platzziffer, bei Gleichstand geteilt
			'rang'            => 0,   // identisch mit platz, siehe platzziffern()
			'pkz'             => '',  // nuLigaPersonId
			'vorname'         => '',
			'nachname'        => '',
			'titel'           => '',  // nationaler Titel aus der Mitgliederdatei
			'geschlecht'      => '',  // MALE, FEMALE, DIVERSE oder leer
			'geburtsjahr'     => 0,
			'dwz'             => 0,
			'dwz_index'       => 0,
			'dwz_formatiert'  => '',  // etwa „1834-42"
			'fide_id'         => 0,
			'elo'             => 0,
			'elo_partien'     => 0,
			'fide_titel'      => '',  // GM, IM, FM …
			'fide_titel_w'    => '',  // WGM, WIM, WFM …
			'nation'          => '',
			'vkz'             => '',  // Vereinskennziffer der gewählten Mitgliedschaft
			'verein'          => '',
			'verbandskuerzel' => '',  // BER, BAY, NRW …
		);
	}
}
