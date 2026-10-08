<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\API;

/**
 * Prüft `API::BugfixVerbaende()` — die Ergänzung der Verbände, die
 * `/dwz/dwzliste/clubs` nicht liefert.
 *
 * Anlaß (1.54.1): Seit dem 07.10.2026 liefert nu dort gar keine
 * Verbandseinträge mehr. Gemessen am 08.10.2026: 2.190 Einträge, keiner mit
 * einer Kennziffer auf „00"; gegenüber dem örtlichen Bestand fehlten 195 —
 * alle 17 Landesverbände und sämtliche Bezirke und Kreise. Bis dahin fehlten
 * nur 14 Landesverbände, die die feste Liste ergänzte.
 *
 * Die Ergänzung hat seitdem zwei Quellen: den örtlichen Vereinsbestand (hier
 * als drittes Argument übergeben, damit die Prüfung ohne Datenbank läuft) und
 * die feste Liste der 17 Landesverbände als Netz darunter.
 */
class ApiVerbaendeTest extends TestCase
{
	/**
	 * Baut eine Antwort der Schnittstelle in der Form, in der sie
	 * `OAuth2Client::callApiWithRefresh()` zurückgibt.
	 *
	 * @param array $eintraege Liste der Vereinseinträge (`body.data`)
	 *
	 * @return array Antwort mit HTTP 200 und ohne Fehler
	 */
	private static function antwort(array $eintraege): array
	{
		return array('error' => false, 'http_code' => 200, 'body' => array('data' => $eintraege));
	}

	/**
	 * Baut einen Vereins- oder Verbandseintrag in der Feldform von nu.
	 *
	 * @param string $vkz  Fünfstellige Kennziffer
	 * @param string $name Name des Vereins oder Verbandes
	 *
	 * @return array Eintrag mit den fünf Feldern der Schnittstelle
	 */
	private static function eintrag(string $vkz, string $name): array
	{
		return array(
			'clubVkz' => $vkz,
			'clubName' => $name,
			'federation' => substr($vkz, 0, 1),
			'parentFederation' => substr($vkz, 0, 3),
			'state' => 'DELETE_STATE_FALSE',
		);
	}

	/**
	 * Liefert die Einträge einer Antwort als Zuordnung Kennziffer → Name.
	 *
	 * @param array $antwort Antwort nach der Ergänzung
	 *
	 * @return array<string, string> Kennziffer (als Zeichenkette) → Name
	 */
	private static function namen(array $antwort): array
	{
		$namen = array();

		foreach ($antwort['body']['data'] as $eintrag) {
			$namen[' '.$eintrag['clubVkz']] = $eintrag['clubName'];
		}

		// Das Leerzeichen verhindert, daß PHP „10000" zum Ganzzahlschlüssel macht
		return array_combine(array_map('trim', array_keys($namen)), array_values($namen)) ?: array();
	}

	/**
	 * Ohne jeden Verband in der Antwort und ohne örtlichen Bestand stehen
	 * alle 17 Landesverbände da — auch Baden, Hessen und Sachsen, die nu bis
	 * zum 06.10.2026 selbst lieferte und die deshalb in der Liste fehlten.
	 */
	public function testAlleSiebzehnLandesverbaendeAusDerFestenListe(): void
	{
		$antwort = API::BugfixVerbaende(self::antwort(array(self::eintrag('10101', 'SK Mannheim-Lindenhof'))), '', array());
		$vkz = array_column($antwort['body']['data'], 'clubVkz');

		$this->assertSame(
			array('10000', '10101', '20000', '30000', '40000', '50000', '60000', '70000', '80000', '90000', 'A0000', 'B0000', 'C0000', 'D0000', 'E0000', 'F0000', 'G0000', 'H0000'),
			$vkz,
			'17 Landesverbände, nach Kennziffer einsortiert'
		);

		$eintraege = array_column($antwort['body']['data'], null, 'clubVkz');

		$this->assertSame(
			array('clubVkz' => '10000', 'clubName' => 'Badischer Schachverband e.V.', 'federation' => '1', 'parentFederation' => '100', 'state' => 'DELETE_STATE_FALSE'),
			$eintraege['10000']
		);
		$this->assertSame(
			array('clubVkz' => '50000', 'clubName' => 'Hessischer Schachverband e. V.', 'federation' => '5', 'parentFederation' => '500', 'state' => 'DELETE_STATE_FALSE'),
			$eintraege['50000']
		);
		$this->assertSame(
			array('clubVkz' => 'F0000', 'clubName' => 'Schachverband Sachsen e.V.', 'federation' => 'F', 'parentFederation' => 'F00', 'state' => 'DELETE_STATE_FALSE'),
			$eintraege['F0000']
		);
	}

	/**
	 * Was nu selbst liefert, bleibt unangetastet: kein zweiter Eintrag, kein
	 * überschriebener Name — weder aus der festen Liste noch aus dem Bestand.
	 */
	public function testGelieferteEintraegeBleiben(): void
	{
		$antwort = API::BugfixVerbaende(
			self::antwort(array(self::eintrag('10000', 'Baden (von nu)'), self::eintrag('10100', 'Mannheim (von nu)'))),
			'',
			array(self::eintrag('10000', 'Baden (örtlich)'), self::eintrag('10100', 'Mannheim (örtlich)'))
		);
		$namen = self::namen($antwort);

		$this->assertSame('Baden (von nu)', $namen['10000']);
		$this->assertSame('Mannheim (von nu)', $namen['10100']);
		$this->assertCount(18, $antwort['body']['data'], '2 gelieferte + 16 ergänzte Landesverbände');
	}

	/**
	 * Bezirke und Kreise kommen aus dem örtlichen Bestand. Der Bestand geht
	 * der festen Liste vor: Er trägt den letzten Stand von nu oder das, was
	 * im Backend berichtigt wurde.
	 */
	public function testBezirkeAusDemOertlichenBestand(): void
	{
		$antwort = API::BugfixVerbaende(
			self::antwort(array(self::eintrag('10101', 'SK Mannheim-Lindenhof'))),
			'',
			array(self::eintrag('10100', 'Mannheim'), self::eintrag('27000', 'Schwaben'), self::eintrag('50000', 'Hessischer Schachverband (im Backend berichtigt)'))
		);
		$namen = self::namen($antwort);

		$this->assertSame('Mannheim', $namen['10100']);
		$this->assertSame('Schwaben', $namen['27000']);
		$this->assertSame('Hessischer Schachverband (im Backend berichtigt)', $namen['50000'], 'Der Bestand geht der festen Liste vor');
		$this->assertSame('Badischer Schachverband e.V.', $namen['10000'], 'Fehlt ein Landesverband im Bestand, kommt er aus der festen Liste');
		$this->assertCount(1 + 2 + 17, $antwort['body']['data']);
		$this->assertSame($antwort['body']['data'], array_values($antwort['body']['data']), 'fortlaufend numeriert');

		$vkz = array_column($antwort['body']['data'], 'clubVkz');
		$sortiert = $vkz;
		sort($sortiert, SORT_STRING);
		$this->assertSame($sortiert, $vkz, 'nach Kennziffer sortiert');
	}

	/**
	 * Aus dem Bestand wird nur übernommen, was ein Verband ist. 00000 nie:
	 * Auf schachbund.de stand unter dieser Kennziffer ein gewöhnlicher
	 * Verein, der sonst den Deutschen Schachbund in der Navigation ersetzte.
	 */
	public function testNurVerbaendeAusDemBestand(): void
	{
		$antwort = API::BugfixVerbaende(
			self::antwort(array()),
			'',
			array(
				self::eintrag('00000', 'SC Lörzweiler'),
				self::eintrag('10101', 'SK Mannheim-Lindenhof'),
				self::eintrag('10200', ''),
				array('clubName' => 'ohne Kennziffer'),
				'kein Feld',
				self::eintrag('10300', 'Odenwald'),
			)
		);
		$namen = self::namen($antwort);

		$this->assertArrayNotHasKey('00000', $namen);
		$this->assertArrayNotHasKey('10101', $namen, 'Vereine werden nicht ergänzt');
		$this->assertArrayNotHasKey('10200', $namen, 'ohne Namen nicht ergänzen');
		$this->assertSame('Odenwald', $namen['10300']);
		$this->assertCount(18, $antwort['body']['data']);
	}

	/**
	 * Bei der Einzelabfrage (`clubs?vkz=…`) kommt nur der angefragte Verband
	 * dazu — aus dem Bestand oder aus der festen Liste —, sonst nichts.
	 */
	public function testEinzelabfrage(): void
	{
		$bestand = array(self::eintrag('10100', 'Mannheim'), self::eintrag('10200', 'Heidelberg'));

		$bezirk = API::BugfixVerbaende(self::antwort(array()), '10100', $bestand);
		$this->assertSame(array('10100' => 'Mannheim'), self::namen($bezirk));

		$landesverband = API::BugfixVerbaende(self::antwort(array()), 'F0000', $bestand);
		$this->assertSame(array('F0000' => 'Schachverband Sachsen e.V.'), self::namen($landesverband));

		$verein = API::BugfixVerbaende(self::antwort(array(self::eintrag('10101', 'SK Mannheim-Lindenhof'))), '10101', $bestand);
		$this->assertSame(array('10101' => 'SK Mannheim-Lindenhof'), self::namen($verein));

		$unbekannt = API::BugfixVerbaende(self::antwort(array()), '99900', $bestand);
		$this->assertSame(array(), $unbekannt['body']['data']);

		// Numerisch gleich, aber eine andere Kennziffer: kein loser Vergleich
		$andere = API::BugfixVerbaende(self::antwort(array()), '1e4', $bestand);
		$this->assertSame(array(), $andere['body']['data']);
	}

	/**
	 * Fehlerantworten und Antworten ohne Liste kommen unverändert zurück —
	 * auch eine HTML-Fehlerseite, bei der `body` eine Zeichenkette ist.
	 *
	 * @dataProvider unbrauchbareAntworten
	 *
	 * @param mixed $antwort Antwort ohne verwertbare Liste
	 */
	public function testUnbrauchbareAntwortenBleibenUnveraendert($antwort): void
	{
		$this->assertSame($antwort, API::BugfixVerbaende($antwort, '', array(self::eintrag('10100', 'Mannheim'))));
	}

	/**
	 * Antworten, an denen nichts zu ergänzen ist.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function unbrauchbareAntworten(): array
	{
		return array(
			'keine Antwort' => array(null),
			'Fehler ohne body' => array(array('error' => true, 'http_code' => 0)),
			'HTML-Fehlerseite' => array(array('error' => true, 'http_code' => 503, 'body' => '<html>Wartung</html>')),
			'body ohne data' => array(array('error' => false, 'http_code' => 200, 'body' => array('metadata' => array()))),
			'data keine Liste' => array(array('error' => false, 'http_code' => 200, 'body' => array('data' => 'x'))),
		);
	}
}
