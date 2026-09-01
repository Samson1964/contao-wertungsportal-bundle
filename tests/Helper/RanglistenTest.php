<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Ranglisten;

/**
 * Prüft die Regeln der Ranglisten, die ohne Datenbank auskommen:
 * geteilte Platzziffern, Altersfilter, Entdoppeln, Verbandskürzel und
 * Parameterprüfung.
 *
 * Die geprüften Methoden sind `protected` — sie gehören nicht zur öffentlichen
 * Schnittstelle der Klasse, tragen aber die Regeln, an denen sich ein Fehler
 * am ehesten zeigt. Der Zugriff läuft deshalb über eine Ableitung, die sie
 * öffentlich weiterreicht; das ist einer Prüfung über Reflection vorzuziehen,
 * weil ein Aufruf mit falschen Typen dann weiterhin auffällt.
 */
class RanglistenTest extends TestCase
{
	/**
	 * Gleiche Wertungszahl heißt geteilter Platz — und der nächste Spieler
	 * überspringt die verbrauchten Ziffern.
	 *
	 * Erwartet wird die in Ranglisten übliche Zählung 1, 2, 2, 4: Nach zwei
	 * geteilten zweiten Plätzen folgt der VIERTE, nicht der dritte.
	 */
	public function testGeteiltePlatzziffern(): void
	{
		$zeilen = RanglistenPruefling::platzziffernOeffentlich(array
		(
			self::zeile(array('dwz' => 2400)),
			self::zeile(array('dwz' => 2350)),
			self::zeile(array('dwz' => 2350)),
			self::zeile(array('dwz' => 2300)),
		), 'dwz');

		$this->assertSame(array(1, 2, 2, 4), array_column($zeilen, 'platz'));
	}

	/**
	 * `platz` und `rang` tragen denselben Wert. Beide Felder gibt es nur, weil
	 * die Auswertungen des Topwertungszahlen-Bundles die Spalte `rank` führen;
	 * sie dürfen nie auseinanderlaufen.
	 */
	public function testRangEntsprichtPlatz(): void
	{
		$zeilen = RanglistenPruefling::platzziffernOeffentlich(array
		(
			self::zeile(array('dwz' => 2000)),
			self::zeile(array('dwz' => 2000)),
			self::zeile(array('dwz' => 1900)),
		), 'dwz');

		$this->assertSame(array_column($zeilen, 'platz'), array_column($zeilen, 'rang'));
	}

	/**
	 * In der Elo-Liste bestimmt die Elo die Platzziffer, nicht die DWZ.
	 *
	 * Der Fall ist keine Spitzfindigkeit: Fast jeder deutsche Elo-Spieler hat
	 * ZUSÄTZLICH eine DWZ. Würde die falsche Spalte gelesen, stimmte die
	 * Numerierung genau bei den Zeilen nicht, die man am ehesten anschaut.
	 */
	public function testPlatzziffernDerEloListeFolgenDerElo(): void
	{
		$zeilen = RanglistenPruefling::platzziffernOeffentlich(array
		(
			self::zeile(array('elo' => 2600, 'dwz' => 2400)),
			self::zeile(array('elo' => 2500, 'dwz' => 2500)),
			self::zeile(array('elo' => 2500, 'dwz' => 1800)),
			self::zeile(array('elo' => 2400, 'dwz' => 2600)),
		), 'elo');

		$this->assertSame(array(1, 2, 2, 4), array_column($zeilen, 'platz'));
	}

	/**
	 * Eine leere Liste bleibt eine leere Liste — kein Platz 1 aus dem Nichts.
	 */
	public function testPlatzziffernLeereListe(): void
	{
		$this->assertSame(array(), RanglistenPruefling::platzziffernOeffentlich(array(), 'dwz'));
	}

	/**
	 * Der Altersfilter versteht beide Formate des Feldes `birthyear`: das
	 * blanke Jahr aus der Schnittstelle und das volle Datum, das der
	 * CSV-Import der Vereinsmitglieder schreibt.
	 *
	 * Geprüft wird mit einer festen Altersspanne gegen das laufende Jahr,
	 * damit der Test auch im nächsten Jahr noch gilt.
	 */
	public function testAltersfilterMitBeidenGeburtsjahrFormaten(): void
	{
		$jahr = (int) date('Y');
		$params = array('alter_von' => 0, 'alter_bis' => 20);

		// Jahrgang genau an der Grenze: wird in diesem Jahr 20 und gehört
		// nach der Jahrgangsrechnung noch dazu
		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich((string) ($jahr - 20), $params));
		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich('15.03.'.($jahr - 20), $params));

		// Ein Jahr älter: draußen, in beiden Formaten
		$this->assertFalse(RanglistenPruefling::passtAlterOeffentlich((string) ($jahr - 21), $params));
		$this->assertFalse(RanglistenPruefling::passtAlterOeffentlich('15.03.'.($jahr - 21), $params));

		// Deutlich jünger: drin, in beiden Formaten
		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich((string) ($jahr - 8), $params));
		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich('01.01.'.($jahr - 8), $params));
	}

	/**
	 * Die untere Altersgrenze arbeitet spiegelbildlich — geprüft an der
	 * Seniorenliste 50+.
	 */
	public function testAltersfilterUntereGrenze(): void
	{
		$jahr = (int) date('Y');
		$params = array('alter_von' => 50, 'alter_bis' => 0);

		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich((string) ($jahr - 50), $params));
		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich('30.12.'.($jahr - 70), $params));
		$this->assertFalse(RanglistenPruefling::passtAlterOeffentlich((string) ($jahr - 49), $params));
	}

	/**
	 * Ohne Geburtsjahr fällt ein Spieler aus einer Altersklassenliste heraus,
	 * steht ohne Altersfilter aber selbstverständlich drin.
	 */
	public function testAltersfilterOhneGeburtsjahr(): void
	{
		$this->assertFalse(RanglistenPruefling::passtAlterOeffentlich('', array('alter_von' => 0, 'alter_bis' => 20)));
		$this->assertFalse(RanglistenPruefling::passtAlterOeffentlich('unbekannt', array('alter_von' => 50, 'alter_bis' => 0)));
		$this->assertTrue(RanglistenPruefling::passtAlterOeffentlich('', array('alter_von' => 0, 'alter_bis' => 0)));
	}

	/**
	 * Dieselbe Person darf nur einmal in der Liste stehen, auch wenn sie über
	 * mehrere Mitgliedschaften mehrfach geliefert wurde. Es gewinnt der zuerst
	 * gefundene Eintrag — das ist die Zeile mit der bereits nach der
	 * Mitgliedschaftsregel ausgewählten Vereinsangabe.
	 */
	public function testEntdoppelnNachKennziffer(): void
	{
		$zeilen = RanglistenPruefling::entdoppelnOeffentlich(array
		(
			self::zeile(array('pkz' => '10001', 'dwz' => 2000, 'verein' => 'SC Erster')),
			self::zeile(array('pkz' => '10002', 'dwz' => 1900)),
			self::zeile(array('pkz' => '10001', 'dwz' => 2000, 'verein' => 'SC Zweiter')),
		));

		$this->assertCount(2, $zeilen);
		$this->assertSame(array('10001', '10002'), array_column($zeilen, 'pkz'));
		$this->assertSame('SC Erster', $zeilen[0]['verein']);
	}

	/**
	 * Zeilen ohne Kennziffer — Elo-Einträge, zu denen keine Person gefunden
	 * wurde — werden über die FIDE-ID unterschieden. Fehlt auch die, bleiben
	 * sie stehen: Zwei Namenlose sind nicht zwangsläufig dieselbe Person.
	 */
	public function testEntdoppelnOhneKennziffer(): void
	{
		$zeilen = RanglistenPruefling::entdoppelnOeffentlich(array
		(
			self::zeile(array('pkz' => '', 'fide_id' => 4611111, 'elo' => 2500)),
			self::zeile(array('pkz' => '', 'fide_id' => 4611111, 'elo' => 2500)),
			self::zeile(array('pkz' => '', 'fide_id' => 0, 'elo' => 2400)),
			self::zeile(array('pkz' => '', 'fide_id' => 0, 'elo' => 2300)),
		));

		$this->assertCount(3, $zeilen);
	}

	/**
	 * Die Sortierung folgt der Wertungszahl; bei Gleichstand entscheidet der
	 * höhere Wertungsindex, danach der Name. Ohne die zweite und dritte Stufe
	 * hinge die Reihenfolge an der Speicherreihenfolge der Datenbank, und zwei
	 * Aufrufe lieferten verschiedene Listen.
	 */
	public function testSortierungMitGleichstand(): void
	{
		$zeilen = RanglistenPruefling::sortiereOeffentlich(array
		(
			self::zeile(array('dwz' => 2000, 'dwz_index' => 10, 'nachname' => 'Zabel')),
			self::zeile(array('dwz' => 2100, 'dwz_index' => 5,  'nachname' => 'Adler')),
			self::zeile(array('dwz' => 2000, 'dwz_index' => 30, 'nachname' => 'Meier')),
			self::zeile(array('dwz' => 2000, 'dwz_index' => 10, 'nachname' => 'Berger')),
		), 'dwz');

		$this->assertSame(array('Adler', 'Meier', 'Berger', 'Zabel'), array_column($zeilen, 'nachname'));
	}

	/**
	 * Der Nationenfilter fragt in drei Stufen, und die erste bekannte Antwort
	 * entscheidet: Nation der Mitgliederdatei, dann FIDE-Föderation, dann
	 * „unbekannt, also behalten".
	 */
	public function testNationenfilterStufen(): void
	{
		$params = array('nation' => 'GER');

		// Stufe 1: Die Nation der Mitgliederdatei entscheidet allein
		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array('nation' => 'GER'), $params));
		$this->assertFalse(RanglistenPruefling::passtNationOeffentlich(array('nation' => 'ISR'), $params));

		// Stufe 2: Ohne Nation zählt die Föderation
		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array('nation' => '', 'foederation' => 'GER'), $params));
		$this->assertFalse(RanglistenPruefling::passtNationOeffentlich(array('nation' => '', 'foederation' => 'ISR'), $params));

		// Stufe 3: Ist beides unbekannt, bleibt die Person drin
		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array('nation' => '', 'foederation' => ''), $params));
		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array(), $params));
		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array('nation' => '-', 'foederation' => '-'), $params));

		// Ohne Filter paßt jede Nation
		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array('nation' => 'ISR'), array('nation' => '')));
	}

	/**
	 * Die Reihenfolge der Stufen ist wesentlich: Wer in der Mitgliederdatei
	 * ausdrücklich als deutsch geführt wird, bleibt deutsch — auch wenn er bei
	 * der FIDE für einen anderen Verband antritt. Ein „Und" statt der Staffel
	 * würde genau diese Spieler aus der deutschen Rangliste werfen.
	 */
	public function testNationSchlaegtFoederation(): void
	{
		$params = array('nation' => 'GER');

		$this->assertTrue(RanglistenPruefling::passtNationOeffentlich(array('nation' => 'GER', 'foederation' => 'AUT'), $params));
		$this->assertFalse(RanglistenPruefling::passtNationOeffentlich(array('nation' => 'AUT', 'foederation' => 'GER'), $params));
	}

	/**
	 * Die Mitgliedschaftsauswahl bevorzugt die aktive Spielgenehmigung und
	 * beachtet das VKZ-Präfix.
	 */
	public function testMitgliedschaftBevorzugtAktive(): void
	{
		$spieler = array('memberships' => array
		(
			array('vkz' => '30012', 'clubName' => 'SC Passiv', 'licenceState' => 'PASSIVE'),
			array('vkz' => '40007', 'clubName' => 'SC Aktiv',  'licenceState' => 'ACTIVE'),
		));

		$treffer = RanglistenPruefling::mitgliedschaftOeffentlich($spieler, array('vkz' => '', 'nur_aktive' => false));
		$this->assertSame('SC Aktiv', $treffer['verein']);

		// Mit VKZ-Präfix zählt nur der Berliner Verein — und der ist passiv
		$treffer = RanglistenPruefling::mitgliedschaftOeffentlich($spieler, array('vkz' => '3', 'nur_aktive' => false));
		$this->assertSame('SC Passiv', $treffer['verein']);

		// Nur aktive: der passive Berliner Verein paßt nicht mehr
		$this->assertNull(RanglistenPruefling::mitgliedschaftOeffentlich($spieler, array('vkz' => '3', 'nur_aktive' => true)));
	}

	/**
	 * Das Verbandskürzel kommt aus der ERSTEN Stelle der Vereinskennziffer.
	 * Eine unbekannte Stelle ergibt einen leeren Text, keine Warnung.
	 */
	public function testVerbandskuerzel(): void
	{
		$this->assertSame('BER', RanglistenPruefling::verbandskuerzelOeffentlich('30012'));
		$this->assertSame('BAY', RanglistenPruefling::verbandskuerzelOeffentlich('20000'));
		$this->assertSame('WÜR', RanglistenPruefling::verbandskuerzelOeffentlich('C0000'));
		$this->assertSame('SWA', RanglistenPruefling::verbandskuerzelOeffentlich('M1234'));
		$this->assertSame('', RanglistenPruefling::verbandskuerzelOeffentlich('X9999'));
		$this->assertSame('', RanglistenPruefling::verbandskuerzelOeffentlich(''));
	}

	/**
	 * Die Parameterprüfung füllt Vorgaben auf, deckelt das Limit und verwirft
	 * unbekannte Schlüssel — sonst erzeugte ein Tippfehler einen zweiten
	 * Cache-Eintrag für dieselbe Liste.
	 */
	public function testNormalisierung(): void
	{
		$p = RanglistenPruefling::normalisiereOeffentlich(array());
		$this->assertSame(50, $p['limit']);
		$this->assertSame('GER', $p['nation']);
		$this->assertTrue($p['nur_aktive']);

		$p = RanglistenPruefling::normalisiereOeffentlich(array('limit' => '99999', 'geschlecht' => 'female', 'alter_bis' => '20', 'tippfehler' => 'x'));
		$this->assertSame(Ranglisten::LIMIT_MAX, $p['limit']);
		$this->assertSame('FEMALE', $p['geschlecht']);
		$this->assertSame(20, $p['alter_bis']);
		$this->assertArrayNotHasKey('tippfehler', $p);

		// Unsinnige Werte fallen auf die Vorgabe zurück, statt eine leere
		// Liste zu erzeugen
		$this->assertSame(50, RanglistenPruefling::normalisiereOeffentlich(array('limit' => 0))['limit']);
		$this->assertSame(0, RanglistenPruefling::normalisiereOeffentlich(array('alter_von' => -5))['alter_von']);
	}

	/**
	 * Gleichwertige Aufrufe treffen denselben Cache-Eintrag, verschiedene
	 * Listen nicht.
	 */
	public function testCacheschluessel(): void
	{
		$a = RanglistenPruefling::cacheschluesselOeffentlich('dwz', RanglistenPruefling::normalisiereOeffentlich(array('limit' => 50, 'alter_bis' => 20)));
		$b = RanglistenPruefling::cacheschluesselOeffentlich('dwz', RanglistenPruefling::normalisiereOeffentlich(array('alter_bis' => '20', 'limit' => '50')));
		$c = RanglistenPruefling::cacheschluesselOeffentlich('dwz', RanglistenPruefling::normalisiereOeffentlich(array('limit' => 50, 'alter_bis' => 20, 'geschlecht' => 'FEMALE')));
		$d = RanglistenPruefling::cacheschluesselOeffentlich('elo', RanglistenPruefling::normalisiereOeffentlich(array('limit' => 50, 'alter_bis' => 20)));

		$this->assertSame($a, $b);
		$this->assertNotSame($a, $c);
		$this->assertNotSame($a, $d);
	}

	/**
	 * Die zehn Standardlisten sind vollständig, und U20 heißt weiterhin
	 * `alter_bis` = 20. Der Test hält genau diese Zahl fest: Eine „Korrektur"
	 * auf 19 unterschlüge einen ganzen Jahrgang.
	 */
	public function testListentypen(): void
	{
		$typen = Ranglisten::listentypen();

		$this->assertSame(
			array('alle', 'w', 'u20', 'u20w', '50+', '50w+', '65+', '65w+', '75+', '75w+'),
			array_keys($typen)
		);

		$this->assertSame(20, $typen['u20']['params']['alter_bis']);
		$this->assertSame(20, $typen['u20w']['params']['alter_bis']);
		$this->assertSame('FEMALE', $typen['u20w']['params']['geschlecht']);
		$this->assertSame(75, $typen['75w+']['params']['alter_von']);
		$this->assertSame(array(), $typen['alle']['params']);

		foreach($typen as $schluessel => $typ)
		{
			$this->assertArrayHasKey('name', $typ, 'Liste '.$schluessel.' ohne Klartextnamen');
			$this->assertNotSame('', $typ['name']);
		}
	}

	/**
	 * Die Schreibweise der DWZ setzt sich aus Zahl und Wertungsindex zusammen.
	 */
	public function testDwzSchreibweise(): void
	{
		$this->assertSame('1834-42', RanglistenPruefling::dwzFormatOeffentlich(1834, 42));
		$this->assertSame('1834', RanglistenPruefling::dwzFormatOeffentlich(1834, 0));
		$this->assertSame('', RanglistenPruefling::dwzFormatOeffentlich(0, 12));
	}

	/**
	 * Baut eine Ausgabezeile für die Prüfungen: alle Felder vorhanden, die
	 * angegebenen überschrieben.
	 *
	 * @param array $werte Felder, die vom Leerwert abweichen sollen
	 *
	 * @return array Vollständige Ausgabezeile
	 */
	protected static function zeile(array $werte): array
	{
		return array_merge(RanglistenPruefling::leereZeileOeffentlich(), $werte);
	}
}

/**
 * Ableitung, die die internen Methoden der Ranglisten für die Prüfung
 * öffentlich macht.
 *
 * Sie gehört bewußt in die Testdatei und nicht ins Bundle: Im Betrieb soll
 * niemand an diesen Methoden vorbei die Auswahl- und Sortierregeln umgehen
 * können.
 */
class RanglistenPruefling extends Ranglisten
{
	/**
	 * Reicht platzziffern() für die Prüfung durch.
	 *
	 * @param array  $zeilen Sortierte Zeilen
	 * @param string $art    'dwz' oder 'elo'
	 *
	 * @return array Zeilen mit Platzziffern
	 */
	public static function platzziffernOeffentlich(array $zeilen, string $art): array
	{
		return self::platzziffern($zeilen, $art);
	}

	/**
	 * Reicht sortiere() für die Prüfung durch.
	 *
	 * @param array  $zeilen Unsortierte Zeilen
	 * @param string $art    'dwz' oder 'elo'
	 *
	 * @return array Sortierte Zeilen
	 */
	public static function sortiereOeffentlich(array $zeilen, string $art): array
	{
		return self::sortiere($zeilen, $art);
	}

	/**
	 * Reicht entdoppeln() für die Prüfung durch.
	 *
	 * @param array $zeilen Zeilen, möglicherweise mit Doppelten
	 *
	 * @return array Zeilen ohne Doppelte
	 */
	public static function entdoppelnOeffentlich(array $zeilen): array
	{
		return self::entdoppeln($zeilen);
	}

	/**
	 * Reicht passtAlter() für die Prüfung durch.
	 *
	 * @param string $birthyear Jahr oder volles Datum
	 * @param array  $params    Parameter mit alter_von/alter_bis
	 *
	 * @return bool true = behalten
	 */
	public static function passtAlterOeffentlich(string $birthyear, array $params): bool
	{
		return self::passtAlter($birthyear, $params);
	}

	/**
	 * Reicht passtNation() für die Prüfung durch.
	 *
	 * @param array $person Personendatensatz
	 * @param array $params Parameter mit nation
	 *
	 * @return bool true = behalten
	 */
	public static function passtNationOeffentlich(array $person, array $params): bool
	{
		return self::passtNation($person, $params);
	}

	/**
	 * Reicht mitgliedschaft() für die Prüfung durch.
	 *
	 * @param array $spieler Datensatz mit memberships
	 * @param array $params  Parameter mit vkz/nur_aktive
	 *
	 * @return array|null Gewählte Mitgliedschaft oder null
	 */
	public static function mitgliedschaftOeffentlich(array $spieler, array $params): ?array
	{
		return self::mitgliedschaft($spieler, $params);
	}

	/**
	 * Reicht verbandskuerzel() für die Prüfung durch.
	 *
	 * @param string $vkz Vereinskennziffer
	 *
	 * @return string Kürzel oder leer
	 */
	public static function verbandskuerzelOeffentlich(string $vkz): string
	{
		return self::verbandskuerzel($vkz);
	}

	/**
	 * Reicht normalisiere() für die Prüfung durch.
	 *
	 * @param array $params Rohe Parameter
	 *
	 * @return array Vollständiger Parametersatz
	 */
	public static function normalisiereOeffentlich(array $params): array
	{
		return self::normalisiere($params);
	}

	/**
	 * Reicht cacheschluessel() für die Prüfung durch.
	 *
	 * @param string $art    'dwz' oder 'elo'
	 * @param array  $params Normalisierte Parameter
	 *
	 * @return string Schlüssel
	 */
	public static function cacheschluesselOeffentlich(string $art, array $params): string
	{
		return self::cacheschluessel($art, $params);
	}

	/**
	 * Reicht dwzFormat() für die Prüfung durch.
	 *
	 * @param int $dwz   Wertungszahl
	 * @param int $index Wertungsindex
	 *
	 * @return string Schreibweise
	 */
	public static function dwzFormatOeffentlich(int $dwz, int $index): string
	{
		return self::dwzFormat($dwz, $index);
	}

	/**
	 * Reicht leereZeile() für die Prüfung durch.
	 *
	 * @return array Ausgabezeile mit Leerwerten
	 */
	public static function leereZeileOeffentlich(): array
	{
		return self::leereZeile();
	}
}
