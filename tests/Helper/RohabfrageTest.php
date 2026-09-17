<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\API;
use Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage;

/**
 * Prüft den Rohdaten-Download ohne Schnittstelle: Funktionsliste,
 * Eingabeprüfung, Parameter, Dateiname und Aufbereitung.
 *
 * Der Abruf selbst braucht nu und läuft im Prüfstand der Testinstallation.
 */
class RohabfrageTest extends TestCase
{
	/**
	 * Setzt die Einstellungen nach jeder Prüfung zurück, damit hindernis()
	 * nicht von einer vorigen Prüfung abhängt.
	 *
	 * @return void
	 */
	protected function tearDown(): void
	{
		unset($GLOBALS['TL_CONFIG']);
	}

	/**
	 * Das Werkzeug bietet genau die Funktionen an, die die Schnittstelle kennt.
	 */
	public function testFunktionenPassenZurSchnittstelle(): void
	{
		$erwartet = array_keys(API::endpunkte());
		$vorhanden = array_keys(Rohabfrage::FUNKTIONEN);

		sort($erwartet);
		sort($vorhanden);

		$this->assertSame($erwartet, $vorhanden);
	}

	/**
	 * Jedes Feld einer Funktion ist beschrieben, und Pflicht- wie
	 * „eines von"-Felder gehören zur Funktion.
	 */
	public function testFelderSindBeschrieben(): void
	{
		foreach (Rohabfrage::FUNKTIONEN as $funktion => $regeln)
		{
			foreach ($regeln['felder'] as $feld)
			{
				$this->assertArrayHasKey($feld, Rohabfrage::PARAMETER, $funktion.': '.$feld);
			}

			foreach (array_merge($regeln['pflicht'], $regeln['eines'] ?? array()) as $feld)
			{
				$this->assertContains($feld, $regeln['felder'], $funktion.': '.$feld);
			}
		}
	}

	/**
	 * Die beiden Anfragen, die zu diesem Werkzeug geführt haben, ergeben die
	 * richtigen Adressen — Groß- und Kleinschreibung der UUID unverändert.
	 */
	public function testDieAnfrageVomSeptember(): void
	{
		$auswertung = Rohabfrage::eingabe('Turnierauswertung', array('turnier' => ' 3FB73B0D-85CD-421C-B441-D85C44207115 '));

		$this->assertSame(array(), $auswertung['fehler']);
		$this->assertSame(
			'/dwz/tournaments/3FB73B0D-85CD-421C-B441-D85C44207115/evaluation',
			API::adresse(Rohabfrage::parameter('Turnierauswertung', $auswertung['werte']))
		);

		$bogen = Rohabfrage::eingabe('Spielberichtsbogen', array(
			'turnier'     => '3FB73B0D-85CD-421C-B441-D85C44207115',
			'spieleruuid' => 'f22a1c0d-4ff8-11ee-a699-0050561f324c',
		));

		$this->assertSame(array(), $bogen['fehler']);
		$this->assertSame(
			array('funktion' => 'Spielberichtsbogen', 'turnier' => '3FB73B0D-85CD-421C-B441-D85C44207115', 'id' => 'f22a1c0d-4ff8-11ee-a699-0050561f324c'),
			Rohabfrage::parameter('Spielberichtsbogen', $bogen['werte'])
		);
	}

	/**
	 * Pflichtfelder fehlen, Unsinn wird abgewiesen.
	 */
	public function testPflichtfelderUndUnsinn(): void
	{
		$leer = Rohabfrage::eingabe('Spielberichtsbogen', array());
		$this->assertCount(2, $leer['fehler'], 'Turnier und Spieler fehlen beide');

		$falsch = Rohabfrage::eingabe('Turnierauswertung', array('turnier' => 'Rostock'));
		$this->assertCount(1, $falsch['fehler']);
		$this->assertStringContainsString('keine gültige UUID', $falsch['fehler'][0]);

		$unbekannt = Rohabfrage::eingabe('Gibtsnicht', array());
		$this->assertCount(1, $unbekannt['fehler']);
	}

	/**
	 * NU-Nummer und VKZ werden in Großschrift gebracht und streng geprüft.
	 */
	public function testNuNummerUndVkz(): void
	{
		$karte = Rohabfrage::eingabe('Karteikarte', array('nuid' => 'nu4338664'));
		$this->assertSame(array(), $karte['fehler']);
		$this->assertSame('NU4338664', $karte['werte']['nuid']);
		$this->assertSame(array('funktion' => 'Karteikarte', 'id' => 'NU4338664'), Rohabfrage::parameter('Karteikarte', $karte['werte']));

		$this->assertCount(1, Rohabfrage::eingabe('Karteikarte', array('nuid' => 'NU43-38664'))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Karteikarte', array('nuid' => '4338664'))['fehler']);

		$verein = Rohabfrage::eingabe('Vereinsliste', array('zps' => 'c0109'));
		$this->assertSame('C0109', $verein['werte']['zps']);
		$this->assertCount(1, Rohabfrage::eingabe('Vereinsliste', array('zps' => '123456'))['fehler']);
	}

	/**
	 * Die Spielersuche braucht wenigstens einen Namen — ohne käme die ganze
	 * DWZ-Liste zurück.
	 */
	public function testSpielersucheBrauchtEinenNamen(): void
	{
		$ohne = Rohabfrage::eingabe('Spielerliste', array('nachname' => '', 'vorname' => ''));
		$this->assertCount(1, $ohne['fehler']);
		$this->assertStringContainsString('mindestens eines', $ohne['fehler'][0]);

		$this->assertSame(array(), Rohabfrage::eingabe('Spielerliste', array('vorname' => 'Karsten'))['fehler']);
	}

	/**
	 * Freier Text: Steuerzeichen und Überlänge werden abgewiesen, Klammern
	 * und Umlaute nicht.
	 */
	public function testFreierText(): void
	{
		$this->assertSame(array(), Rohabfrage::eingabe('Turnierliste', array('suche' => 'Open (A) Müller'))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Turnierliste', array('suche' => "Open\nA"))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Turnierliste', array('suche' => str_repeat('x', 101)))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Turnierliste', array('suche' => "\xC3\x28"))['fehler'], 'ungültiges UTF-8');
	}

	/**
	 * Daten müssen echte Kalendertage sein.
	 */
	public function testDatum(): void
	{
		$this->assertSame(array(), Rohabfrage::eingabe('Turnierliste', array('von' => '2026-02-28', 'bis' => '2026-12-31'))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Turnierliste', array('von' => '2026-02-30'))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Turnierliste', array('von' => '28.02.2026'))['fehler']);
		$this->assertSame(array(), Rohabfrage::eingabe('Turnierliste', array())['fehler'], 'die Turniersuche geht auch ganz ohne Angaben');
	}

	/**
	 * Verbandsliste: ohne VKZ nur mit Höchstzahl, Zahlen in Grenzen,
	 * Geschlecht nur aus der Liste.
	 */
	public function testVerbandsliste(): void
	{
		$this->assertCount(1, Rohabfrage::eingabe('Verbandsliste', array())['fehler']);

		$mit = Rohabfrage::eingabe('Verbandsliste', array('limit' => '0050', 'geschlecht' => 'FEMALE', 'alter_bis' => '20'));
		$this->assertSame(array(), $mit['fehler']);
		$this->assertSame('50', $mit['werte']['limit'], 'führende Nullen weg');
		$this->assertSame(
			'/dwz/dwzliste/persons?limit=50&gender=FEMALE&maxAge=20&',
			API::adresse(Rohabfrage::parameter('Verbandsliste', $mit['werte']))
		);

		$this->assertCount(1, Rohabfrage::eingabe('Verbandsliste', array('limit' => '10', 'geschlecht' => 'X'))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Verbandsliste', array('limit' => '10', 'alter_bis' => '141'))['fehler']);
		$this->assertCount(1, Rohabfrage::eingabe('Verbandsliste', array('limit' => '-5'))['fehler']);
	}

	/**
	 * Werte einer vorher gewählten Funktion stören nicht.
	 */
	public function testFremdeFelderZaehlenNicht(): void
	{
		$pruefung = Rohabfrage::eingabe('Turnierauswertung', array('turnier' => '019f75b9-af19-7de0-a772-7da0ebbd8f0c', 'nuid' => 'Unsinn', 'von' => 'auch Unsinn'));

		$this->assertSame(array(), $pruefung['fehler']);
		$this->assertSame(array('turnier'), array_keys($pruefung['werte']));
	}

	/**
	 * Ohne Häkchen kommt der Antworttext Byte für Byte zurück.
	 */
	public function testRohBleibtRoh(): void
	{
		$roh = '{"b":1,"a":{},"c":1.0,"d":"ä\/x"}';

		$this->assertSame($roh, Rohabfrage::inhalt($roh, false));
	}

	/**
	 * Eingerückt ändert sich die Darstellung, aber nicht die Struktur.
	 *
	 * Das leere Objekt bleibt ein Objekt, 1.0 eine Kommazahl, die Reihenfolge
	 * der Schlüssel bleibt, und Umlaute stehen lesbar da.
	 */
	public function testLesbarBehaeltDieStruktur(): void
	{
		$roh = '{"b":1,"a":{},"e":[],"c":1.0,"d":"ä\/x","g":123456789012345678901234567890}';
		$lesbar = Rohabfrage::inhalt($roh, true);

		$this->assertStringContainsString("\n", $lesbar);
		$this->assertStringContainsString('"a": {}', $lesbar);
		$this->assertStringContainsString('"e": []', $lesbar);
		$this->assertStringContainsString('"c": 1.0', $lesbar);
		$this->assertStringContainsString('"d": "ä/x"', $lesbar);
		$this->assertStringContainsString('123456789012345678901234567890', $lesbar, 'große Ganzzahl ohne Rundung');
		$this->assertLessThan(strpos($lesbar, '"a"'), strpos($lesbar, '"b"'), 'Reihenfolge der Schlüssel bleibt');
	}

	/**
	 * Kein JSON — etwa eine Fehlerseite — kommt auch mit Häkchen unverändert.
	 */
	public function testKeinJsonBleibtUnveraendert(): void
	{
		$this->assertSame('<html>Fehler</html>', Rohabfrage::inhalt('<html>Fehler</html>', true));
		$this->assertSame('', Rohabfrage::inhalt('', true));
	}

	/**
	 * „Eingerückt" ist die Vorgabe — aber nur, solange das Formular noch nie
	 * abgeschickt wurde. Danach gilt das Kästchen: Ein entfernter Haken kommt
	 * als fehlendes Feld an und darf nicht wieder zur Vorgabe werden, sonst
	 * ließe sich die unveränderte Antwort gar nicht mehr holen.
	 */
	public function testEingeruecktIstDieVorgabe(): void
	{
		$this->assertTrue(Rohabfrage::lesbar(false, ''), 'erster Aufruf der Seite');
		$this->assertTrue(Rohabfrage::lesbar(true, '1'), 'abgeschickt mit Haken');
		$this->assertFalse(Rohabfrage::lesbar(true, ''), 'abgeschickt ohne Haken');
		$this->assertFalse(Rohabfrage::lesbar(true, '0'));
	}

	/**
	 * Der Dateiname nennt Funktion, Angaben und Zeitpunkt und enthält nichts,
	 * was ein Dateisystem stört.
	 */
	public function testDateiname(): void
	{
		$zeit = mktime(10, 12, 0, 9, 14, 2026);

		$this->assertSame(
			'nu_Spielberichtsbogen_3FB73B0D-85CD-421C-B441-D85C44207115_f22a1c0d-4ff8-11ee-a699-0050561f324c_20260914-1012.json',
			Rohabfrage::dateiname('Spielberichtsbogen', array('turnier' => '3FB73B0D-85CD-421C-B441-D85C44207115', 'spieleruuid' => 'f22a1c0d-4ff8-11ee-a699-0050561f324c'), $zeit)
		);

		$this->assertSame(
			'nu_Spielerliste_Mueller_Karl-Heinz-O-Neil_20260914-1012.json',
			Rohabfrage::dateiname('Spielerliste', array('nachname' => 'Müller', 'vorname' => "Karl-Heinz O'Neil"), $zeit)
		);

		$this->assertSame('nu_Verbaende_20260914-1012.json', Rohabfrage::dateiname('Verbaende', array(), $zeit));
	}

	/**
	 * Abgeschaltete Schnittstelle und fehlende Zugangsdaten verhindern den
	 * Abruf — mit einer Meldung, die sagt, woran es liegt.
	 */
	public function testHindernis(): void
	{
		$GLOBALS['TL_CONFIG'] = array();
		$this->assertStringContainsString('Zugangsdaten', Rohabfrage::hindernis());

		$GLOBALS['TL_CONFIG'] = array(
			'wertungsportal_apiBasisURL'  => 'https://example.com/rs',
			'wertungsportal_tokenURL'     => 'https://example.com/token',
			'wertungsportal_clientID'     => 'id',
			'wertungsportal_clientSecret' => 'geheim',
		);
		$this->assertSame('', Rohabfrage::hindernis());

		$GLOBALS['TL_CONFIG']['wertungsportal_api_aus'] = '1';
		$this->assertStringContainsString('abgeschaltet', Rohabfrage::hindernis());
	}

	/**
	 * Die Hinweise treffen die Fälle aus dem Betrieb.
	 */
	public function testHinweise(): void
	{
		$gross = array('turnier' => '3FB73B0D-85CD-421C-B441-D85C44207115', 'spieleruuid' => 'f22a1c0d-4ff8-11ee-a699-0050561f324c');

		$bogen = Rohabfrage::hinweis('Spielberichtsbogen', array('http' => 404), $gross);
		$this->assertStringContainsString('playerUuid', $bogen);
		$this->assertStringContainsString('Großbuchstaben', $bogen);

		$this->assertStringContainsString('Tokenkontingent', Rohabfrage::hinweis('Turnierauswertung', array('http' => 403), $gross));
		$this->assertStringContainsString('nicht geantwortet', Rohabfrage::hinweis('Turnierauswertung', array('http' => 0), $gross));
		$this->assertStringContainsString('kennt nu nicht', Rohabfrage::hinweis('Karteikarte', array('http' => 404), array('nuid' => 'NU1')));
		$this->assertSame('', Rohabfrage::hinweis('Karteikarte', array('http' => 200), array()));
	}

	/**
	 * Die Protokollzeile nennt nur, was angegeben wurde.
	 */
	public function testBeschreibung(): void
	{
		$this->assertSame('turnier=abc, spieleruuid=def', Rohabfrage::beschreibung(array('turnier' => 'abc', 'spieleruuid' => 'def', 'leer' => '')));
		$this->assertSame('ohne Angaben', Rohabfrage::beschreibung(array()));
	}
}
