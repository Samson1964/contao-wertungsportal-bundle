<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Insert-Tags mit den Wertungsdaten einer Person.
 *
 *     {{dwz::NU…}}              aktuelle DWZ als reine Zahl
 *     {{elo::NU…}}              Elo, verlinkt auf das FIDE-Profil
 *     {{ftitel::NU…}}           FIDE-Titel als Kürzel (GM, WIM …)
 *     {{ftitel::NU…::lang}}     FIDE-Titel ausgeschrieben
 *     {{verein::NU…}}           Vereinsname nach den Ersetzungen
 *     {{verein::NU…::30}}       dasselbe, gekürzt auf 30 Zeichen
 *
 * Jedes Tag gibt es auch mit dem Präfix `cache_` ({{cache_dwz::NU…}}), mit
 * derselben Ausgabe. Das Präfix richtet sich an den Contao-Kern: Er speichert
 * ein solches Tag in einer zwischengespeicherten Seite nicht mit, sondern
 * ersetzt es bei jedem Aufruf neu.
 *
 * Bis Helper-Bundle 2.x lagen die Tags dort und erwarteten die DeWIS-ID. Hier
 * erwarten sie die NU-Nummer (`nuLigaPersonId`); eine alte DeWIS-ID ergibt
 * eine leere Ausgabe. Beschrieben in docs/insert-tags.md.
 *
 * **Datenquelle sind die Spiegeltabellen, nicht die Schnittstelle.** Über
 * `API::autoQuery()` hätte eine Seite mit 40 Spielern nach Ablauf des
 * Zwischenspeichers bis zu 40 Abrufe bei nu ausgelöst — nacheinander, jeder bis
 * zur eingestellten Wartezeit (Standard 30 Sekunden) und jeder auf das
 * Tokenkontingent. Dazu hätte jeder Treffer im Zwischenspeicher die
 * Abrufstatistik hochgezählt, und jede gewöhnliche Seite mit einem Tag wäre
 * für die Besucherbremse zur Wertungsportal-Seite geworden: Die Bremse zählt
 * zwar nur einmal je Seitenaufruf (`Besucherbremse::gesperrt()` merkt sich das
 * Ergebnis), ein gebremster Besucher hätte aber auch auf Nachrichtenseiten
 * leere Werte gesehen. Nötig ist sie hier nicht: Welche Person ein Tag zeigt,
 * legt der Redakteur fest, nicht der Besucher. Elo und FIDE-Titel kommen
 * ohnehin aus `tl_wertungsportal_elo` — auch die Karteikarte reichert die
 * Antwort von nu daraus an.
 *
 * Aktuell ist der Spiegel, weil jeder Abruf einer Karteikarte, Spieler-,
 * Vereins- oder Verbandsliste die Personen abgleicht und der Vorlader nachts
 * die Karteikarten nachzieht. Er enthält alle Mitglieder aus dem CSV-Import.
 *
 * Je Seitenaufruf wird jede Person nur einmal gelesen: DWZ, Elo, Titel und
 * Verein derselben Person kosten zusammen eine Sperrprüfung und eine Abfrage
 * (Personensatz, Mitgliedschaften, Elo-Satz).
 *
 * Registriert über den Hook `replaceInsertTags` in config/config.php, dort
 * vorne eingereiht — die Begründung steht an der Stelle.
 */
class InsertTags
{
	/**
	 * Die bedienten Tags, ohne Präfix `cache_`.
	 */
	const NAMEN = array('dwz', 'elo', 'ftitel', 'verein');

	/**
	 * Ausgeschriebene FIDE-Titel für `{{ftitel::NU…::lang}}`. Jeder andere
	 * Titel bleibt als Kürzel stehen.
	 */
	const TITEL_LANG = array
	(
		'GM'  => 'Großmeister',
		'WGM' => 'Großmeisterin',
		'IM'  => 'Internationaler Meister',
		'WIM' => 'Internationale Meisterin',
		'FM'  => 'FIDE-Meister',
		'WFM' => 'FIDE-Meisterin',
		'CM'  => 'Kandidatenmeister',
		'WCM' => 'Kandidatenmeisterin',
	);

	/**
	 * Voreinstellung der Ersetzungen im Vereinsnamen (Einstellung
	 * `insert_verein_replaces`). Zeichen für Zeichen dieselbe wie im
	 * Helper-Bundle 2.0.0 — ein Test hält das fest. `+` steht für ein
	 * Leerzeichen, weil Contao Eingaben am Rand beschneidet.
	 */
	const VEREIN_ERSETZUNGEN = array
	(
		array('search' => 'Schachverein', 'replace' => 'SV'),
		array('search' => 'SABT+', 'replace' => ''),
		array('search' => 'Schachclub', 'replace' => 'SC'),
		array('search' => 'Schachklub', 'replace' => 'SK'),
		array('search' => 'Schachfreunde', 'replace' => 'SF'),
		array('search' => '+e.V.', 'replace' => ''),
		array('search' => '+eV', 'replace' => ''),
	);

	/**
	 * Adresse des FIDE-Profils; die FIDE-ID wird angehängt.
	 */
	const FIDE_PROFIL = 'https://ratings.fide.com/profile/';

	/**
	 * Gelesene Personen dieses Seitenaufrufs: NU-Nummer => Datensatz wie aus
	 * laden(), oder null für gesperrt, unbekannt oder fehlgeschlagen. Auch das
	 * Nein wird gemerkt, sonst fragte jedes Tag einer unbekannten Nummer
	 * erneut.
	 *
	 * @var array
	 */
	protected static $personen = array();

	/**
	 * Ersetzt eines der vier Tags; Contao ruft die Methode für jedes Tag auf,
	 * das der Kern nicht selbst kennt.
	 *
	 * Contao übergibt weitere Argumente (Cache-Schalter, Flags, alle Tags der
	 * Seite …); gebraucht wird nur das Tag selbst.
	 *
	 * @param string $strTag Tag ohne Klammern und ohne Flags, etwa
	 *                       'verein::NU1234567::30'
	 *
	 * @return string|false Der Ersatztext; '' ohne gültige NU-Nummer, für
	 *                      gesperrte oder unbekannte Personen und bei jedem
	 *                      Fehler. false für fremde Tags — nur dann reicht
	 *                      Contao das Tag an die übrigen Hooks weiter.
	 */
	public function ersetzen($strTag)
	{
		$teile = static::zerlegen($strTag);

		if(null === $teile) return false;

		// Gar nicht erst nachsehen, wenn der Wert keine NU-Nummer ist — auch
		// eine noch in Seiten stehende DeWIS-ID landet hier
		$nu = static::nuNummer($teile['wert']);

		if('' === $nu) return '';

		$person = static::person($nu);

		if(null === $person) return '';

		switch($teile['name'])
		{
			case 'dwz':
				return static::dwz($person);

			case 'elo':
				return static::elo($person);

			case 'ftitel':
				return static::ausgabe(static::titel($person['titel'], $teile['zusatz']));

			default:
				return static::ausgabe(static::vereinsname(static::verein($person['vereine']), static::ersetzungen(), $teile['zusatz']));
		}
	}

	/**
	 * Zerlegt ein Tag in Namen, Wert und Zusatz.
	 *
	 * Das Präfix `cache_` fällt weg, der Name wird klein geschrieben. Wert und
	 * Zusatz bleiben unverändert bis auf Leerzeichen am Rand — geprüft wird
	 * erst beim Gebrauch.
	 *
	 * @param string $strTag Tag ohne Klammern, etwa 'cache_ftitel::NU1::lang'
	 *
	 * @return array|null ['name' => 'ftitel', 'wert' => 'NU1', 'zusatz' => 'lang'];
	 *                    fehlende Teile als ''. null, wenn es keines der vier
	 *                    Tags ist.
	 */
	protected static function zerlegen($strTag)
	{
		$teile = explode('::', (string) $strTag);
		$name = strtolower($teile[0]);

		if(0 === strncmp($name, 'cache_', 6)) $name = substr($name, 6);

		if(!in_array($name, self::NAMEN, true)) return null;

		return array
		(
			'name'   => $name,
			'wert'   => isset($teile[1]) ? trim($teile[1]) : '',
			'zusatz' => isset($teile[2]) ? trim($teile[2]) : '',
		);
	}

	/**
	 * Prüft eine NU-Nummer und bringt sie in die gespeicherte Schreibweise.
	 *
	 * Zugelassen sind „NU" und Ziffern, höchstens so lang, wie die Spalte
	 * `nuLigaPersonId` fasst (32 Zeichen). Kleinschreibung wird angenommen und
	 * umgewandelt. Reine Ziffern gelten bewusst NICHT: Das sind die alten
	 * DeWIS-IDs, und eine Umdeutung könnte eine fremde Person treffen.
	 *
	 * @param string $wert Wert aus dem Tag
	 *
	 * @return string Die NU-Nummer in Großbuchstaben, oder '' wenn der Wert
	 *                keine ist
	 */
	protected static function nuNummer($wert)
	{
		$wert = strtoupper(trim((string) $wert));

		return 1 === preg_match('/^NU[0-9]{1,30}$/', $wert) ? $wert : '';
	}

	/**
	 * Liefert eine Person — beim ersten Tag einer Nummer aus der Datenbank,
	 * danach aus dem Merker dieses Seitenaufrufs.
	 *
	 * @param string $nu Geprüfte NU-Nummer
	 *
	 * @return array|null Datensatz wie aus laden(); null für gesperrt,
	 *                    unbekannt oder fehlgeschlagen
	 */
	protected static function person($nu)
	{
		if(!array_key_exists($nu, self::$personen))
		{
			self::$personen[$nu] = static::ermitteln($nu);
		}

		return self::$personen[$nu];
	}

	/**
	 * Prüft die Sperre und liest die Person.
	 *
	 * Jeder Fehler — fehlende Tabelle vor `contao:migrate`, abgerissene
	 * Verbindung — endet als null und damit als leere Ausgabe: Ein Tag in einer
	 * Nachricht darf die Seite nicht mitreißen.
	 *
	 * @param string $nu Geprüfte NU-Nummer
	 *
	 * @return array|null Datensatz oder null
	 */
	protected static function ermitteln($nu)
	{
		try
		{
			// Gesperrte Personen zuerst: Von ihnen wird gar nichts gelesen
			if(static::gesperrt($nu)) return null;

			return static::laden($nu);
		}
		catch(\Throwable $e)
		{
			static::melde($nu, $e);

			return null;
		}
	}

	/**
	 * Meldet, ob die Person auf der Sperrliste steht.
	 *
	 * @param string $nu Geprüfte NU-Nummer
	 *
	 * @return bool true = nichts ausgeben
	 */
	protected static function gesperrt($nu)
	{
		return \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::istGeblockt($nu);
	}

	/**
	 * Liest DWZ, FIDE-ID, Elo, Titel und Mitgliedschaften einer Person.
	 *
	 * Person und laufende Mitgliedschaften kommen aus `Lokal::karteikarte()` —
	 * derselben Sicht, mit der die Karteikarte im Notbetrieb arbeitet:
	 * veröffentlichte Person, Mitgliedschaften mit laufender Spielgenehmigung,
	 * ACTIVE zuerst. Platzhalter-Mitgliedschaften mit der Nummer 0000 fallen
	 * wie in jeder Ausgabe weg. Elo und Titel liefert `tl_wertungsportal_elo`
	 * über die FIDE-ID.
	 *
	 * @param string $nu Geprüfte NU-Nummer
	 *
	 * @return array|null ['dwz' => int, 'fideid' => int, 'elo' => int,
	 *                    'titel' => string, 'vereine' => array]; 0 bzw. ''
	 *                    für fehlende Werte. null, wenn es keine
	 *                    veröffentlichte Person mit dieser Nummer gibt.
	 */
	protected static function laden($nu)
	{
		$karte = \Schachbulle\ContaoWertungsportalBundle\Helper\Lokal::karteikarte(array('id' => $nu));

		if(!is_array($karte) || !isset($karte['body']) || !is_array($karte['body'])) return null;

		$body = $karte['body'];
		$fideid = (int) ($body['fideId'] ?? 0);
		$fide = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getFIDEDatenLokal($fideid);

		return array
		(
			'dwz'     => (int) ($body['rating'] ?? 0),
			'fideid'  => $fideid,
			'elo'     => (int) ($fide['elo'] ?? 0),
			'titel'   => (string) ($fide['titel'] ?? ''),
			'vereine' => \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalPersonsMembershipsModel::filtereNullnummern((array) ($body['memberships'] ?? array())),
		);
	}

	/**
	 * Liest die Ersetzungen im Vereinsnamen aus den Einstellungen.
	 *
	 * Ein gespeicherter Wert in der localconfig.php hat Vorrang vor der
	 * Voreinstellung aus config.php. `StringUtil::deserialize()` statt
	 * `unserialize()`: Je nach Alter der Installation steht dort ein Array,
	 * eine serialisierte Zeichenkette oder gar nichts.
	 *
	 * @return array Zeilen mit 'search' und 'replace'; leer, wenn nichts
	 *               eingestellt ist
	 */
	protected static function ersetzungen()
	{
		return \Contao\StringUtil::deserialize($GLOBALS['TL_CONFIG']['insert_verein_replaces'] ?? '', true);
	}

	/**
	 * Schreibt einen Fehler ins Debug-Protokoll, sofern es eingeschaltet ist.
	 *
	 * Nur dort: Ein Fehler trifft jedes Tag jeder Seite, im Systemprotokoll
	 * gingen die übrigen Meldungen darunter verloren. Das Protokollieren darf
	 * seinerseits nichts auslösen.
	 *
	 * @param string     $nu NU-Nummer, bei der es scheiterte
	 * @param \Throwable $e  Der Fehler
	 *
	 * @return void
	 */
	protected static function melde($nu, $e)
	{
		if(empty($GLOBALS['TL_CONFIG']['wertungsportal_debuglog'])) return;

		try
		{
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::protokoll('Insert-Tag für '.$nu.' fehlgeschlagen: '.$e->getMessage(), 'wertungsportal_inserttags.log');
		}
		catch(\Throwable $f)
		{
			// Bewusst still, siehe oben
		}
	}

	/**
	 * Ausgabe für {{dwz::…}}.
	 *
	 * @param array $person Datensatz aus laden()
	 *
	 * @return string Die DWZ als Zahl, '' ohne DWZ
	 */
	protected static function dwz(array $person)
	{
		return $person['dwz'] > 0 ? (string) $person['dwz'] : '';
	}

	/**
	 * Ausgabe für {{elo::…}}: die Elo als Verweis auf das FIDE-Profil.
	 *
	 * @param array $person Datensatz aus laden()
	 *
	 * @return string '<a href="https://ratings.fide.com/profile/FIDEID"
	 *                target="_blank">ELO</a>'; '' ohne Elo oder ohne FIDE-ID
	 */
	protected static function elo(array $person)
	{
		if($person['elo'] < 1 || $person['fideid'] < 1) return '';

		return sprintf('<a href="%s%d" target="_blank">%d</a>', self::FIDE_PROFIL, $person['fideid'], $person['elo']);
	}

	/**
	 * Ausgabe für {{ftitel::…}}: Kürzel, mit Zusatz „lang" ausgeschrieben.
	 *
	 * Der Zusatz wird wirklich verglichen. Im Helper-Bundle stand bis 1.x
	 * `$arrSplit[2] = 'lang'` — eine Zuweisung, die jeden dritten Parameter
	 * zum „lang" machte.
	 *
	 * @param string $titel  FIDE-Titel aus der Elo-Tabelle
	 * @param string $zusatz Dritter Teil des Tags; 'lang' ohne Rücksicht auf
	 *                       die Schreibweise schreibt aus
	 *
	 * @return string Kürzel oder ausgeschriebener Titel; ein Titel ohne
	 *                Langform bleibt unverändert, ohne Titel ''
	 */
	protected static function titel($titel, $zusatz)
	{
		$titel = trim((string) $titel);

		if('lang' === strtolower((string) $zusatz) && array_key_exists($titel, self::TITEL_LANG))
		{
			return self::TITEL_LANG[$titel];
		}

		return $titel;
	}

	/**
	 * Wählt den Verein aus den Mitgliedschaften: die erste aktive, sonst die
	 * erste überhaupt — wie die Karteikarte beim Verweis auf die alte
	 * Karteikarte.
	 *
	 * @param array $vereine Mitgliedschaften mit 'clubName' und 'licenceState'
	 *
	 * @return string Vereinsname unverändert, '' ohne Mitgliedschaft
	 */
	protected static function verein(array $vereine)
	{
		$wahl = null;

		foreach($vereine as $mitglied)
		{
			if(!is_array($mitglied)) continue;

			if(null === $wahl) $wahl = $mitglied;

			if('ACTIVE' === ($mitglied['licenceState'] ?? ''))
			{
				$wahl = $mitglied;
				break;
			}
		}

		return null === $wahl ? '' : (string) ($wahl['clubName'] ?? '');
	}

	/**
	 * Wendet die Ersetzungen auf einen Vereinsnamen an und kürzt ihn.
	 *
	 * Reihenfolge: Entitäten auflösen, ersetzen, kürzen. Aufgelöst wird, weil
	 * Contao Backend-Eingaben mit Entitäten speichert (aus „&" wird „&amp;")
	 * — ungelöst träfe ein Suchbegriff den Namen nicht, und die Kürzung könnte
	 * mitten in einer Entität schneiden.
	 *
	 * Ersetzt wird mit `str_ireplace()`: Zeile für Zeile von oben nach unten,
	 * ohne Rücksicht auf Groß- und Kleinschreibung (bei Umlauten nur exakt),
	 * und auch mitten im Wort. „Schachverein" macht deshalb aus
	 * „Schachvereinigung" ein „SVigung", wenn keine Zeile für den längeren
	 * Begriff davor steht. In Suchbegriff und Ersatz steht `+` für ein
	 * Leerzeichen. Zeilen ohne Suchbegriff werden übersprungen.
	 *
	 * Gekürzt wird nach Zeichen, nicht nach Bytes — ein Umlaut zählt einfach
	 * und wird nie zerschnitten.
	 *
	 * @param string $name        Vereinsname
	 * @param array  $ersetzungen Zeilen mit 'search' und 'replace'
	 * @param string $laenge      Höchstzahl der Zeichen; nur eine ganze Zahl
	 *                            größer 0 kürzt, alles andere bleibt ohne
	 *                            Wirkung
	 *
	 * @return string Der Name ohne HTML-Maskierung
	 */
	protected static function vereinsname($name, $ersetzungen, $laenge)
	{
		$name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		$suchen = array();
		$ersetzen = array();

		foreach((array) $ersetzungen as $zeile)
		{
			if(!is_array($zeile) || !isset($zeile['search']) || !is_scalar($zeile['search']) || '' === trim((string) $zeile['search'])) continue;

			$suchen[] = static::ersetzungstext($zeile['search']);
			$ersetzen[] = static::ersetzungstext(isset($zeile['replace']) && is_scalar($zeile['replace']) ? $zeile['replace'] : '');
		}

		if(count($suchen)) $name = str_ireplace($suchen, $ersetzen, $name);

		$laenge = trim((string) $laenge);

		if('' !== $laenge && ctype_digit($laenge) && (int) $laenge > 0)
		{
			$name = mb_substr($name, 0, (int) $laenge, 'UTF-8');
		}

		return $name;
	}

	/**
	 * Bereitet Suchbegriff oder Ersatz einer Ersetzungszeile auf: Entitäten
	 * auflösen, `+` zu Leerzeichen.
	 *
	 * @param mixed $wert Eingabe aus der Einstellung
	 *
	 * @return string
	 */
	protected static function ersetzungstext($wert)
	{
		return str_replace('+', ' ', html_entity_decode((string) $wert, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}

	/**
	 * Maskiert einen Text für die Ausgabe in HTML.
	 *
	 * Vereinsname und Titel stammen aus der Schnittstelle bzw. dem FIDE-Import
	 * und kommen so in die Seite. Auch geschweifte Klammern werden maskiert,
	 * damit Contao ein „{{" im Namen nicht als weiteres Tag liest.
	 *
	 * @param string $text Unmaskierter Text
	 *
	 * @return string
	 */
	protected static function ausgabe($text)
	{
		$text = htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return str_replace(array('{', '}'), array('&#123;', '&#125;'), $text);
	}
}
