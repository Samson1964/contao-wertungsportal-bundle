<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Classes\InsertTags;

/**
 * Prüft die Insert-Tags {{dwz::…}}, {{elo::…}}, {{ftitel::…}} und {{verein::…}}.
 *
 * Die Tags lagen bis Helper-Bundle 2.x dort und fragten DeWIS mit der
 * DeWIS-ID ab. Festgehalten wird hier, was beim Umzug nicht verloren gehen
 * darf: fremde Tags werden weitergereicht statt verschluckt, jede Person wird
 * je Seitenaufruf nur einmal gelesen, gesperrte Personen und Fehler ergeben
 * nichts, und Ersetzung wie Kürzung arbeiten wie beschrieben.
 *
 * Datenbank und Contao ersetzt InsertTagsAttrappe; gegen die echten Tabellen
 * läuft der Prüfstand in den Testinstallationen (siehe docs/insert-tags.md).
 */
class InsertTagsTest extends TestCase
{
	/**
	 * Eine NU-Nummer mit vollständigem Datensatz.
	 */
	private const NU = 'NU4338664';

	/**
	 * Setzt vor jeder Prüfung Bestand, Mitschriften und Merker zurück und legt
	 * eine vollständige Person an.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		InsertTagsAttrappe::zuruecksetzen();
		InsertTagsAttrappe::$bestand[self::NU] = $this->person();
	}

	/**
	 * Baut einen Personendatensatz in der Form von laden().
	 *
	 * @param array $abweichungen Felder, die vom Vorgabesatz abweichen
	 *
	 * @return array
	 */
	private function person(array $abweichungen = array()): array
	{
		return array_merge(array
		(
			'dwz'     => 1876,
			'fideid'  => 24691011,
			'elo'     => 2451,
			'titel'   => 'IM',
			'vereine' => array
			(
				array('vkz' => 'B0101', 'memberNo' => '17', 'clubName' => 'Schachklub Passiv e.V.', 'licenceState' => 'PASSIVE'),
				array('vkz' => 'B0102', 'memberNo' => '4', 'clubName' => 'Schachfreunde Königsspringer Süd e.V.', 'licenceState' => 'ACTIVE'),
			),
		), $abweichungen);
	}

	/**
	 * Ersetzt ein Tag über den Hook.
	 *
	 * @param string $tag Tag ohne Klammern
	 *
	 * @return string|false Rückgabe des Hooks
	 */
	private function tag(string $tag)
	{
		return (new InsertTagsAttrappe())->ersetzen($tag);
	}

	/**
	 * Tags anderer Erweiterungen gehen mit false weiter — auch solche, die
	 * einem der vier Namen nur ähneln. Ein '' verschluckte sie.
	 *
	 * @return void
	 */
	public function testFremdeTagsGehenWeiter(): void
	{
		$fremde = array('flagge::GER', 'cache_flagge::GER', 'alter::01.01.2000', 'figur::wB', 'dwzliste::NU4338664', 'eloliste', 'vereinsregister_url::5', 'cache_', '', 'link_url::12', 'cache_cache_dwz::NU4338664');

		foreach($fremde as $tag)
		{
			$this->assertFalse($this->tag($tag), $tag);
		}

		$this->assertSame(array(), InsertTagsAttrappe::$sperrpruefungen);
		$this->assertSame(array(), InsertTagsAttrappe::$lesezugriffe);
	}

	/**
	 * Name, Wert und Zusatz werden getrennt; cache_ fällt weg, die
	 * Schreibweise des Namens spielt keine Rolle.
	 *
	 * @return void
	 */
	public function testTagzerlegung(): void
	{
		$this->assertSame(array('name' => 'dwz', 'wert' => 'NU4338664', 'zusatz' => ''), InsertTagsAttrappe::zerlegenOeffentlich('dwz::NU4338664'));
		$this->assertSame(array('name' => 'verein', 'wert' => 'NU1', 'zusatz' => '30'), InsertTagsAttrappe::zerlegenOeffentlich('cache_verein::NU1::30'));
		$this->assertSame(array('name' => 'ftitel', 'wert' => 'nu1', 'zusatz' => 'lang'), InsertTagsAttrappe::zerlegenOeffentlich('CACHE_FTITEL:: nu1 ::lang'));
		$this->assertSame(array('name' => 'elo', 'wert' => '', 'zusatz' => ''), InsertTagsAttrappe::zerlegenOeffentlich('elo'));
		$this->assertNull(InsertTagsAttrappe::zerlegenOeffentlich('flagge::GER'));
	}

	/**
	 * Ohne Wert bleibt die Ausgabe leer, ohne dass nachgesehen wird.
	 *
	 * @return void
	 */
	public function testOhneParameterLeer(): void
	{
		foreach(array('dwz', 'dwz::', 'cache_elo', 'ftitel::::lang', 'verein:: ::30') as $tag)
		{
			$this->assertSame('', $this->tag($tag), $tag);
		}

		$this->assertSame(array(), InsertTagsAttrappe::$lesezugriffe);
	}

	/**
	 * Alles, was keine NU-Nummer ist, ergibt '' — vor allem die alten
	 * DeWIS-IDs aus reinen Ziffern — und erreicht die Datenbank nie.
	 *
	 * @return void
	 */
	public function testUngueltigeNummerLeerOhneAbfrage(): void
	{
		InsertTagsAttrappe::$bestand['NU12'] = $this->person();

		$falsch = array('10012345', 'NU', 'NU12a4', 'XNU12', 'NU 12', "NU12' OR '1'='1", 'NU12;', 'NU'.str_repeat('1', 31));

		foreach($falsch as $wert)
		{
			$this->assertSame('', $this->tag('dwz::'.$wert), $wert);
		}

		$this->assertSame(array(), InsertTagsAttrappe::$sperrpruefungen);
		$this->assertSame(array(), InsertTagsAttrappe::$lesezugriffe);
	}

	/**
	 * Kleingeschrieben oder mit Leerzeichen am Rand wird die Nummer
	 * angenommen und in der gespeicherten Schreibweise gesucht.
	 *
	 * @return void
	 */
	public function testNummerWirdVereinheitlicht(): void
	{
		$this->assertSame('1876', $this->tag('dwz:: nu4338664 '));
		$this->assertSame(array(self::NU), InsertTagsAttrappe::$lesezugriffe);
	}

	/**
	 * {{dwz::…}} gibt die reine Zahl aus, ohne DWZ nichts.
	 *
	 * @return void
	 */
	public function testDwz(): void
	{
		InsertTagsAttrappe::$bestand['NU1'] = $this->person(array('dwz' => 0));

		$this->assertSame('1876', $this->tag('dwz::NU4338664'));
		$this->assertSame('1876', $this->tag('cache_dwz::NU4338664'));
		$this->assertSame('', $this->tag('dwz::NU1'));
	}

	/**
	 * {{elo::…}} verweist auf das FIDE-Profil; ohne Elo oder FIDE-ID nichts.
	 *
	 * @return void
	 */
	public function testElo(): void
	{
		InsertTagsAttrappe::$bestand['NU1'] = $this->person(array('elo' => 0));
		InsertTagsAttrappe::$bestand['NU2'] = $this->person(array('fideid' => 0));

		$link = '<a href="https://ratings.fide.com/profile/24691011" target="_blank">2451</a>';

		$this->assertSame($link, $this->tag('elo::NU4338664'));
		$this->assertSame($link, $this->tag('cache_elo::NU4338664'));
		$this->assertSame('', $this->tag('elo::NU1'));
		$this->assertSame('', $this->tag('elo::NU2'));
	}

	/**
	 * Liefert jeden ausschreibbaren Titel mit seiner Langform.
	 *
	 * @return array
	 */
	public function titel(): array
	{
		return array
		(
			'GM'  => array('GM', 'Großmeister'),
			'WGM' => array('WGM', 'Großmeisterin'),
			'IM'  => array('IM', 'Internationaler Meister'),
			'WIM' => array('WIM', 'Internationale Meisterin'),
			'FM'  => array('FM', 'FIDE-Meister'),
			'WFM' => array('WFM', 'FIDE-Meisterin'),
			'CM'  => array('CM', 'Kandidatenmeister'),
			'WCM' => array('WCM', 'Kandidatenmeisterin'),
		);
	}

	/**
	 * Ohne Zusatz das Kürzel, mit „lang" die Langform.
	 *
	 * @dataProvider titel
	 *
	 * @param string $kuerzel  Titel in der Elo-Tabelle
	 * @param string $langform Erwartete Langform
	 *
	 * @return void
	 */
	public function testTitel(string $kuerzel, string $langform): void
	{
		InsertTagsAttrappe::$bestand[self::NU] = $this->person(array('titel' => $kuerzel));

		$this->assertSame($kuerzel, $this->tag('ftitel::NU4338664'));
		$this->assertSame($langform, $this->tag('ftitel::NU4338664::lang'));
		$this->assertSame($langform, $this->tag('cache_ftitel::NU4338664::lang'));
	}

	/**
	 * Nur „lang" schreibt aus. Im Helper-Bundle machte eine Zuweisung statt
	 * eines Vergleichs jeden dritten Parameter zum „lang".
	 *
	 * @return void
	 */
	public function testTitelNurMitLangAusgeschrieben(): void
	{
		InsertTagsAttrappe::$bestand[self::NU] = $this->person(array('titel' => 'GM'));
		InsertTagsAttrappe::$bestand['NU1'] = $this->person(array('titel' => 'XY'));
		InsertTagsAttrappe::$bestand['NU2'] = $this->person(array('titel' => ''));

		$this->assertSame('GM', $this->tag('ftitel::NU4338664::kurz'));
		$this->assertSame('GM', $this->tag('ftitel::NU4338664::1'));
		$this->assertSame('Großmeister', $this->tag('ftitel::NU4338664::LANG'));
		$this->assertSame('XY', $this->tag('ftitel::NU1::lang'));
		$this->assertSame('', $this->tag('ftitel::NU2::lang'));
	}

	/**
	 * Die aktive Mitgliedschaft geht vor, sonst gilt die erste.
	 *
	 * @return void
	 */
	public function testVereinAktiveMitgliedschaftZuerst(): void
	{
		InsertTagsAttrappe::$bestand['NU1'] = $this->person(array('vereine' => array
		(
			array('clubName' => 'Schachklub Erster', 'licenceState' => 'PASSIVE'),
			array('clubName' => 'Schachclub Zweiter', 'licenceState' => 'SONDER'),
		)));
		InsertTagsAttrappe::$bestand['NU2'] = $this->person(array('vereine' => array()));

		$this->assertSame('SF Königsspringer Süd', $this->tag('verein::NU4338664'));
		$this->assertSame('SF Königsspringer Süd', $this->tag('cache_verein::NU4338664'));
		$this->assertSame('SK Erster', $this->tag('verein::NU1'));
		$this->assertSame('', $this->tag('verein::NU2'));
	}

	/**
	 * „+" steht in Suchbegriff und Ersatz für ein Leerzeichen; ersetzt wird
	 * ohne Rücksicht auf Groß- und Kleinschreibung.
	 *
	 * @return void
	 */
	public function testErsetzungMitPlus(): void
	{
		$vorgabe = InsertTags::VEREIN_ERSETZUNGEN;

		$this->assertSame('TV Bammental', InsertTagsAttrappe::vereinsnameOeffentlich('SABT TV Bammental', $vorgabe, ''));
		$this->assertSame('SV Werder Bremen', InsertTagsAttrappe::vereinsnameOeffentlich('SAbt SV Werder Bremen', $vorgabe, ''));
		$this->assertSame('SV Tempo Göttingen', InsertTagsAttrappe::vereinsnameOeffentlich('Schachverein Tempo Göttingen e.V.', $vorgabe, ''));
		$this->assertSame('SK Baunatal 1963', InsertTagsAttrappe::vereinsnameOeffentlich('Schachklub Baunatal 1963 eV', $vorgabe, ''));
		$this->assertSame('SC Bonn Beuel', InsertTagsAttrappe::vereinsnameOeffentlich('SCHACHCLUB Bonn Beuel', $vorgabe, ''));

		// Ohne „+" wäre „SABT" auch mitten im Wort weg, mit „+" nur vor einem Leerzeichen
		$this->assertSame('SABTEILUNG X', InsertTagsAttrappe::vereinsnameOeffentlich('SABTEILUNG X', $vorgabe, ''));

		$eigene = array(array('search' => 'SG+', 'replace' => 'Spielgemeinschaft+'));
		$this->assertSame('Spielgemeinschaft Porz', InsertTagsAttrappe::vereinsnameOeffentlich('SG Porz', $eigene, ''));
	}

	/**
	 * Zeilen ohne Suchbegriff, mit leerem oder nur aus Leerzeichen
	 * bestehendem Suchbegriff und kaputte Zeilen bleiben ohne Wirkung.
	 *
	 * @return void
	 */
	public function testZeilenOhneSuchbegriffUebersprungen(): void
	{
		$ersetzungen = array
		(
			array('replace' => 'X'),
			array('search' => '', 'replace' => 'X'),
			array('search' => '   ', 'replace' => 'X'),
			array('search' => array('Verein'), 'replace' => 'X'),
			'keine Zeile',
			array('search' => 'Verein'),
		);

		$this->assertSame('Mein ', InsertTagsAttrappe::vereinsnameOeffentlich('Mein Verein', $ersetzungen, ''));
		$this->assertSame('Mein Verein', InsertTagsAttrappe::vereinsnameOeffentlich('Mein Verein', array(), ''));
	}

	/**
	 * Die Zeilen wirken nacheinander, jede auf das Ergebnis der vorigen.
	 *
	 * @return void
	 */
	public function testReihenfolgeDerErsetzungen(): void
	{
		$kette = array
		(
			array('search' => 'Schachgemeinschaft', 'replace' => 'SG'),
			array('search' => 'SG+', 'replace' => 'Spielgemeinschaft+'),
		);

		$this->assertSame('Spielgemeinschaft Porz', InsertTagsAttrappe::vereinsnameOeffentlich('Schachgemeinschaft Porz', $kette, ''));
		$this->assertSame('SG Porz', InsertTagsAttrappe::vereinsnameOeffentlich('Schachgemeinschaft Porz', array_reverse($kette), ''));
	}

	/**
	 * Ersetzt wird nur an Wortgrenzen. Mit str_ireplace() machte die
	 * Voreinstellung in 1.43.0 aus „Schachvereinigung" ein „SVigung" und aus
	 * „Rochade Eving" ein „Rochadeing"; Frank hat daraufhin Wortgrenzen
	 * entschieden. Neben Leerzeichen grenzen auch Bindestrich, Klammer und
	 * Satzzeichen ab, Umlaute zählen als Buchstaben.
	 *
	 * @return void
	 */
	public function testNurAnWortgrenzen(): void
	{
		$vorgabe = InsertTags::VEREIN_ERSETZUNGEN;

		// Mitten im Wort bleibt alles stehen
		$this->assertSame('Schachvereinigung Weilerbach', InsertTagsAttrappe::vereinsnameOeffentlich('Schachvereinigung Weilerbach', $vorgabe, ''));
		$this->assertSame('SV Rochade Eving 25/64', InsertTagsAttrappe::vereinsnameOeffentlich('SV Rochade Eving 25/64', $vorgabe, ''));
		$this->assertSame('Kreisschachverein Nord', InsertTagsAttrappe::vereinsnameOeffentlich('Kreisschachverein Nord', $vorgabe, ''));

		// An Wortgrenzen wird ersetzt, auch neben Ziffern, Bindestrich und Klammer
		$this->assertSame('FC St. Pauli 1910 SAbt', InsertTagsAttrappe::vereinsnameOeffentlich('FC St. Pauli 1910 eV SAbt', $vorgabe, ''));
		$this->assertSame('Post-SV Leipzig', InsertTagsAttrappe::vereinsnameOeffentlich('Post-Schachverein Leipzig', $vorgabe, ''));
		$this->assertSame('SF Nord (1920)', InsertTagsAttrappe::vereinsnameOeffentlich('Schachfreunde Nord (1920) e.V.', $vorgabe, ''));

		// Wer den längeren Begriff kürzen will, trägt eine eigene Zeile ein
		$ergaenzt = array_merge(array(array('search' => 'Schachvereinigung', 'replace' => 'SVg')), $vorgabe);
		$this->assertSame('SVg Weilerbach', InsertTagsAttrappe::vereinsnameOeffentlich('Schachvereinigung Weilerbach', $ergaenzt, ''));

		// Umlaute sind Buchstaben: „rich" steht in „Zürich" nicht an einer Grenze.
		// Groß- und Kleinschreibung spielt auch bei Umlauten keine Rolle
		$eigene = array(array('search' => 'rich', 'replace' => 'X'), array('search' => 'SÜD', 'replace' => 'S'));
		$this->assertSame('SK Zürich', InsertTagsAttrappe::vereinsnameOeffentlich('SK Zürich', $eigene, ''));
		$this->assertSame('SF S', InsertTagsAttrappe::vereinsnameOeffentlich('SF Süd', $eigene, ''));
		$this->assertSame('SF Südwest', InsertTagsAttrappe::vereinsnameOeffentlich('SF Südwest', $eigene, ''));

		// Punkte im Suchbegriff gelten wörtlich, $ und \ im Ersatz ebenso
		$this->assertSame('SV Werder eaVb', InsertTagsAttrappe::vereinsnameOeffentlich('SV Werder eaVb', $vorgabe, ''));
		$this->assertSame('$1 \\1 Nord', InsertTagsAttrappe::vereinsnameOeffentlich('SV Nord', array(array('search' => 'SV', 'replace' => '$1 \\1')), ''));
	}

	/**
	 * Gekürzt wird nach Zeichen: Ein Umlaut zählt einfach und wird nicht
	 * zerschnitten. Nur eine ganze Zahl größer 0 kürzt.
	 *
	 * @return void
	 */
	public function testKuerzungMitUmlauten(): void
	{
		$vorgabe = InsertTags::VEREIN_ERSETZUNGEN;
		$name = 'Schachfreunde Königsspringer Süd e.V.';

		$gekuerzt = InsertTagsAttrappe::vereinsnameOeffentlich($name, $vorgabe, '6');

		$this->assertSame('SF Kön', $gekuerzt);
		$this->assertTrue(mb_check_encoding($gekuerzt, 'UTF-8'));
		$this->assertSame('SF Königsspringer Süd', InsertTagsAttrappe::vereinsnameOeffentlich($name, $vorgabe, '21'));
		$this->assertSame('SF Königsspringer Sü', InsertTagsAttrappe::vereinsnameOeffentlich($name, $vorgabe, '20'));

		foreach(array('', '0', '-5', 'abc', '2.5', '99') as $laenge)
		{
			$this->assertSame('SF Königsspringer Süd', InsertTagsAttrappe::vereinsnameOeffentlich($name, $vorgabe, $laenge), $laenge);
		}

		$this->assertSame('SF Kön', $this->tag('verein::NU4338664::6'));
		$this->assertSame('SF Königsspringer Süd', $this->tag('verein::NU4338664::abc'));
	}

	/**
	 * Sonderzeichen werden für HTML maskiert, erst nach dem Kürzen; auch
	 * Entitäten im Bestand werden vorher aufgelöst.
	 *
	 * @return void
	 */
	public function testAusgabeWirdMaskiert(): void
	{
		InsertTagsAttrappe::$bestand['NU1'] = $this->person(array('vereine' => array(array('clubName' => 'SK König & Turm <1920> {{x}}', 'licenceState' => 'ACTIVE'))));
		InsertTagsAttrappe::$bestand['NU2'] = $this->person(array('vereine' => array(array('clubName' => 'SK K&ouml;nig &amp; Turm', 'licenceState' => 'ACTIVE'))));
		InsertTagsAttrappe::$bestand['NU3'] = $this->person(array('titel' => '"><b'));

		$this->assertSame('SK König &amp; Turm &lt;1920&gt; &#123;&#123;x&#125;&#125;', $this->tag('verein::NU1'));
		$this->assertSame('SK König &amp;', $this->tag('verein::NU1::10'));
		$this->assertSame('SK König &amp; Turm', $this->tag('verein::NU2'));
		$this->assertSame('&quot;&gt;&lt;b', $this->tag('ftitel::NU3'));
	}

	/**
	 * Gesperrte Personen ergeben bei allen Tags nichts, und von ihnen wird
	 * gar nicht erst gelesen.
	 *
	 * @return void
	 */
	public function testGesperrteLeer(): void
	{
		InsertTagsAttrappe::$sperrliste[self::NU] = true;

		foreach($this->alleTags(self::NU) as $tag)
		{
			$this->assertSame('', $this->tag($tag), $tag);
		}

		$this->assertSame(array(self::NU), InsertTagsAttrappe::$sperrpruefungen);
		$this->assertSame(array(), InsertTagsAttrappe::$lesezugriffe);
	}

	/**
	 * Ein Fehler beim Lesen ergibt nichts und keine Ausnahme — auch ein
	 * Error, nicht nur eine Exception.
	 *
	 * @return void
	 */
	public function testFehlerLeerOhneAusnahme(): void
	{
		InsertTagsAttrappe::$fehler = new \RuntimeException('Table tl_wertungsportal_persons does not exist');

		foreach($this->alleTags(self::NU) as $tag)
		{
			$this->assertSame('', $this->tag($tag), $tag);
		}

		InsertTagsAttrappe::zuruecksetzen();
		InsertTagsAttrappe::$fehler = new \TypeError('kaputt');

		$this->assertSame('', $this->tag('verein::NU4338664'));
	}

	/**
	 * Unbekannte Nummern ergeben nichts.
	 *
	 * @return void
	 */
	public function testUnbekannteNummerLeer(): void
	{
		foreach($this->alleTags('NU9999999') as $tag)
		{
			$this->assertSame('', $this->tag($tag), $tag);
		}
	}

	/**
	 * Alle Tags derselben Person auf einer Seite kosten eine Sperrprüfung und
	 * einen Lesezugriff; eine zweite Person einen weiteren. Auch das Nein für
	 * eine unbekannte Nummer wird gemerkt.
	 *
	 * @return void
	 */
	public function testEineAbfrageJePerson(): void
	{
		InsertTagsAttrappe::$bestand['NU1'] = $this->person();

		foreach(array_merge($this->alleTags(self::NU), $this->alleTags('nu4338664'), $this->alleTags('NU1'), $this->alleTags('NU9999999')) as $tag)
		{
			$this->tag($tag);
		}

		$this->assertSame(array(self::NU, 'NU1', 'NU9999999'), InsertTagsAttrappe::$sperrpruefungen);
		$this->assertSame(array(self::NU, 'NU1', 'NU9999999'), InsertTagsAttrappe::$lesezugriffe);
	}

	/**
	 * Die Voreinstellung ist Zeichen für Zeichen die des Helper-Bundles 2.0.0
	 * (src/Resources/contao/config/config.php, Zeile 35). So ändert sich für
	 * Installationen ohne eigenen Wert nichts am Ergebnis.
	 *
	 * @return void
	 */
	public function testVoreinstellungWieImHelperBundle(): void
	{
		$helper = 'a:7:{i:0;a:2:{s:6:"search";s:12:"Schachverein";s:7:"replace";s:2:"SV";}i:1;a:2:{s:6:"search";s:5:"SABT+";s:7:"replace";s:0:"";}i:2;a:2:{s:6:"search";s:10:"Schachclub";s:7:"replace";s:2:"SC";}i:3;a:2:{s:6:"search";s:10:"Schachklub";s:7:"replace";s:2:"SK";}i:4;a:2:{s:6:"search";s:13:"Schachfreunde";s:7:"replace";s:2:"SF";}i:5;a:2:{s:6:"search";s:5:"+e.V.";s:7:"replace";s:0:"";}i:6;a:2:{s:6:"search";s:3:"+eV";s:7:"replace";s:0:"";}}';

		$this->assertSame($helper, serialize(InsertTags::VEREIN_ERSETZUNGEN));
	}

	/**
	 * Liefert alle Schreibweisen der vier Tags für eine Nummer.
	 *
	 * @param string $nu NU-Nummer
	 *
	 * @return array
	 */
	private function alleTags(string $nu): array
	{
		$tags = array();

		foreach(array('dwz::%s', 'elo::%s', 'ftitel::%s', 'ftitel::%s::lang', 'verein::%s', 'verein::%s::30') as $muster)
		{
			$tags[] = sprintf($muster, $nu);
			$tags[] = 'cache_'.sprintf($muster, $nu);
		}

		return $tags;
	}
}
