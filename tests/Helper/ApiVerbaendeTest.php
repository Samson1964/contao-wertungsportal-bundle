<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\API;

/**
 * Prüft die Ergänzung der Verbände, die `/dwz/dwzliste/clubs` nicht liefert
 * (`API::ergaenzeVerbaende()`, `API::verbaendeAusStruktur()`).
 *
 * Vorgeschichte: Bis zum 06.10.2026 fehlten in der Vereinsliste nur 14
 * Landesverbände. Seit dem Release von nu am 07.10.2026 liefert sie
 * ausschließlich Vereine — gemessen am 08.10.2026: 2.190 Einträge, keiner mit
 * einer Kennziffer auf „00". Die Verbände aller Ebenen stellt nu seitdem
 * unter `/dwz/dwzliste/federations` bereit (Auskunft vom 12.10.2026, Vorgang
 * SPS-34590), gemessen am 13.10.2026: 218 Einträge mit dreistelliger Nummer.
 *
 * Quellen der Ergänzung, in dieser Rangfolge:
 *
 * 1. die Verbandsstruktur von nu — wenn sie vollständig ist (alle 17
 *    Landesverbände), gilt nur sie;
 * 2. sonst der örtliche Vereinsbestand,
 * 3. darunter die feste Liste der 17 Landesverbände.
 *
 * Beide Listen werden hier übergeben, damit die Prüfung ohne Datenbank und
 * ohne Schnittstelle läuft.
 */
class ApiVerbaendeTest extends TestCase
{
	/**
	 * Kennbuchstaben der 17 Landesverbände mit ihren Namen bei nu.
	 */
	private const LANDESVERBAENDE = array(
		'1' => 'Baden', '2' => 'Bayern', '3' => 'Berlin', '4' => 'Hamburg', '5' => 'Hessen', '6' => 'Nordrhein-Westfalen',
		'7' => 'Niedersachsen', '8' => 'Rheinland-Pfalz', '9' => 'Saarland', 'A' => 'Schleswig-Holstein', 'B' => 'Bremen',
		'C' => 'Württemberg', 'D' => 'Brandenburg', 'E' => 'Mecklenburg-Vorpommern', 'F' => 'Sachsen', 'G' => 'Sachsen-Anhalt',
		'H' => 'Thüringen',
	);

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
	 * Baut einen Vereins- oder Verbandseintrag in der Feldform von `/clubs`.
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
	 * Baut einen Eintrag in der Feldform von `/federations`.
	 *
	 * @param string      $nummer Dreistellige Verbandsnummer
	 * @param string      $name   Name des Verbandes
	 * @param string|null $eltern Nummer des übergeordneten Verbandes; null läßt
	 *                            das Feld weg (so liefert nu 000, 001 und K00)
	 *
	 * @return array Eintrag mit federationNumber, federation, federationName
	 *               und gegebenenfalls parentFederation
	 */
	private static function verband(string $nummer, string $name, ?string $eltern): array
	{
		$eintrag = array('federationNumber' => $nummer, 'federation' => substr($nummer, 0, 1));

		if (null !== $eltern) {
			$eintrag['parentFederation'] = $eltern;
		}

		$eintrag['federationName'] = $name;

		return $eintrag;
	}

	/**
	 * Eine verkleinerte Verbandsstruktur mit allem, was die echte Antwort an
	 * Besonderheiten hat: Bund und Schachjugend ohne übergeordneten Verband,
	 * „Ausland", DBSB und Schwalbe, Bezirke und Kreise auf allen Ebenen und
	 * die Württemberger Kreise, die die Nummer ihres Bezirks tragen.
	 *
	 * @return array Liste von Einträgen in der Feldform von `/federations`
	 */
	private static function struktur(): array
	{
		$liste = array(
			self::verband('000', 'Deutschland', null),
			self::verband('001', 'Deutsche Schachjugend', null),
		);

		foreach (self::LANDESVERBAENDE as $kennung => $name) {
			$liste[] = self::verband($kennung.'00', $name, '000');
		}

		return array_merge($liste, array(
			self::verband('101', 'Mannheim', '100'),
			self::verband('210', 'Mittelfranken', '200'),
			self::verband('211', 'Mittelfranken-Mitte', '210'),
			self::verband('C01', 'Bezirk Oberschwaben', 'C00'),
			self::verband('C01', 'Kreis Oberschwaben Nord', 'C01'),
			self::verband('C01', 'Kreis Oberschwaben Süd', 'C01'),
			self::verband('K00', 'Ausland', null),
			self::verband('L00', 'DBSB', '000'),
			self::verband('M00', 'Schwalbe', '000'),
		));
	}

	/**
	 * Liefert die Einträge einer Liste als Zuordnung Kennziffer → Name.
	 *
	 * @param array $eintraege Liste von Einträgen mit clubVkz und clubName
	 *
	 * @return array<string, string> Kennziffer (als Zeichenkette) → Name
	 */
	private static function namen(array $eintraege): array
	{
		// array_column() machte aus „10000" einen Ganzzahlschlüssel — für den
		// Vergleich egal, assertSame auf die Schlüssel schlüge aber fehl
		$namen = array();

		foreach ($eintraege as $eintrag) {
			$namen['#'.$eintrag['clubVkz']] = $eintrag['clubName'];
		}

		return $namen;
	}

	/**
	 * Aus der Struktur von nu werden Einträge in der Feldform von `/clubs`:
	 * Kennziffer = Nummer + „00", der Name von nu, als `parentFederation` wie
	 * früher die eigenen drei Stellen.
	 */
	public function testStrukturWirdZuVerbandseintraegen(): void
	{
		$verbaende = API::verbaendeAusStruktur(self::struktur());
		$namen = self::namen($verbaende);

		$this->assertCount(17 + 4, $verbaende, '17 Landesverbände, Mannheim, Mittelfranken, Mittelfranken-Mitte, Bezirk Oberschwaben');
		$this->assertSame('Baden', $namen['#10000']);
		$this->assertSame('Sachsen', $namen['#F0000']);
		$this->assertSame('Mannheim', $namen['#10100']);
		$this->assertSame('Mittelfranken-Mitte', $namen['#21100']);

		$mannheim = array_values(array_filter($verbaende, static function ($e) { return '10100' === $e['clubVkz']; }))[0];
		$this->assertSame(
			array('clubVkz' => '10100', 'clubName' => 'Mannheim', 'federation' => '1', 'parentFederation' => '101', 'state' => 'DELETE_STATE_FALSE'),
			$mannheim
		);
	}

	/**
	 * Die Württemberger Kreise tragen die Nummer ihres Bezirks und nennen
	 * sich selbst als übergeordnet. Eine eigene Kennziffer haben sie nicht —
	 * unter C0100 steht deshalb allein der Bezirk.
	 */
	public function testKreiseOhneEigeneNummerFallenWeg(): void
	{
		$namen = self::namen(API::verbaendeAusStruktur(self::struktur()));

		$this->assertSame('Bezirk Oberschwaben', $namen['#C0100']);
		$this->assertNotContains('Kreis Oberschwaben Nord', $namen);
		$this->assertNotContains('Kreis Oberschwaben Süd', $namen);

		// Auch wenn der Kreis VOR dem Bezirk in der Liste steht
		$gedreht = array_reverse(self::struktur());
		$this->assertSame('Bezirk Oberschwaben', self::namen(API::verbaendeAusStruktur($gedreht))['#C0100']);
	}

	/**
	 * Übernommen werden nur die 17 Landesverbände und was darunter hängt.
	 * Bund, Schachjugend, „Ausland", DBSB und Schwalbe bleiben draußen: Der
	 * DSB ist fest eingebaut, DBSB und Schwalbe laufen weiter über ihre
	 * Vereinseinträge L0001 und M0001.
	 */
	public function testNurDieSiebzehnLandesverbaendeUndIhreGliederung(): void
	{
		$namen = self::namen(API::verbaendeAusStruktur(self::struktur()));

		foreach (array('#00000', '#00100', '#K0000', '#L0000', '#M0000') as $schluessel) {
			$this->assertArrayNotHasKey($schluessel, $namen);
		}
	}

	/**
	 * Unbrauchbare Einträge überspringt die Umwandlung, statt abzubrechen.
	 */
	public function testUnbrauchbareStruktureintraege(): void
	{
		$verbaende = API::verbaendeAusStruktur(array(
			'kein Feld',
			array('federationName' => 'ohne Nummer', 'federation' => '1', 'parentFederation' => '100'),
			array('federationNumber' => '1011', 'federation' => '1', 'parentFederation' => '100', 'federationName' => 'vierstellig'),
			array('federationNumber' => '102', 'federation' => '1', 'parentFederation' => '100', 'federationName' => ' '),
			self::verband('103', 'Odenwald', '100'),
		));

		$this->assertSame(array('#10300' => 'Odenwald'), self::namen($verbaende));
		$this->assertSame(array(), API::verbaendeAusStruktur(array()));
	}

	/**
	 * Mit der vollständigen Struktur von nu kommen alle Verbände von dort —
	 * mit den Namen von nu (Franks Entscheidung vom 13.10.2026). Der örtliche
	 * Bestand zählt dann nicht mehr: Ein Verband, den nu aufgelöst hat, kommt
	 * nicht über den Bestand zurück, und ein dort geänderter Name gilt nicht.
	 */
	public function testVollstaendigeStrukturGiltAllein(): void
	{
		$bestand = array(self::eintrag('10000', 'Badischer Schachverband e.V.'), self::eintrag('10900', 'Aufgelöster Bezirk'));
		$antwort = API::ergaenzeVerbaende(self::antwort(array(self::eintrag('10101', 'SK Mannheim-Lindenhof'))), '', self::struktur(), $bestand);
		$namen = self::namen($antwort['body']['data']);

		$this->assertCount(1 + 21, $antwort['body']['data']);
		$this->assertSame('Baden', $namen['#10000'], 'Name von nu, nicht aus dem Bestand');
		$this->assertSame('Mannheim', $namen['#10100']);
		$this->assertArrayNotHasKey('#10900', $namen, 'nicht mehr bei nu → nicht ergänzt');

		$vkz = array_column($antwort['body']['data'], 'clubVkz');
		$sortiert = $vkz;
		sort($sortiert, SORT_STRING);
		$this->assertSame($sortiert, $vkz, 'nach Kennziffer sortiert');
		$this->assertSame(array_values(array_unique($vkz)), $vkz, 'keine doppelten Kennziffern');
	}

	/**
	 * Eine Struktur, in der Landesverbände fehlen, ist verdächtig (abgebrochene
	 * oder fehlerhafte Antwort). Sie läge sonst bis zu einer Woche im
	 * Zwischenspeicher und nähme dem Frontend die Verbände. Dann gilt die
	 * Rückfallebene: örtlicher Bestand, darunter die feste Liste.
	 */
	public function testUnvollstaendigeStrukturWirdNichtVerwendet(): void
	{
		$luecke = array_values(array_filter(self::struktur(), static function ($e) { return 'F00' !== $e['federationNumber']; }));
		$bestand = array(self::eintrag('10100', 'Mannheim (Bestand)'));
		$antwort = API::ergaenzeVerbaende(self::antwort(array()), '', $luecke, $bestand);
		$namen = self::namen($antwort['body']['data']);

		$this->assertSame('Mannheim (Bestand)', $namen['#10100']);
		$this->assertSame('Badischer Schachverband e.V.', $namen['#10000'], 'feste Liste');
		$this->assertSame('Schachverband Sachsen e.V.', $namen['#F0000']);
		$this->assertArrayNotHasKey('#21000', $namen, 'aus der verdächtigen Struktur kommt nichts');
		$this->assertCount(17 + 1, $antwort['body']['data']);
	}

	/**
	 * Ohne Struktur (Schnittstelle nicht erreichbar) und ohne Bestand stehen
	 * die 17 Landesverbände aus der festen Liste da — auch Baden, Hessen und
	 * Sachsen, die bis 1.54.0 darin fehlten.
	 */
	public function testFesteListeAlsLetztesNetz(): void
	{
		$antwort = API::ergaenzeVerbaende(self::antwort(array(self::eintrag('10101', 'SK Mannheim-Lindenhof'))), '', array(), array());

		$this->assertSame(
			array('10000', '10101', '20000', '30000', '40000', '50000', '60000', '70000', '80000', '90000', 'A0000', 'B0000', 'C0000', 'D0000', 'E0000', 'F0000', 'G0000', 'H0000'),
			array_column($antwort['body']['data'], 'clubVkz')
		);

		$this->assertCount(17, API::landesverbaende());
		$hessen = array_values(array_filter($antwort['body']['data'], static function ($e) { return '50000' === $e['clubVkz']; }))[0];
		$this->assertSame(
			array('clubVkz' => '50000', 'clubName' => 'Hessischer Schachverband e. V.', 'federation' => '5', 'parentFederation' => '500', 'state' => 'DELETE_STATE_FALSE'),
			$hessen
		);
	}

	/**
	 * Ohne Struktur geht der örtliche Bestand der festen Liste vor (wie in
	 * 1.54.1); aus ihm kommt nur, was ein Verband ist — 00000 nie.
	 */
	public function testBestandAlsRueckfall(): void
	{
		$antwort = API::ergaenzeVerbaende(
			self::antwort(array()),
			'',
			array(),
			array(
				self::eintrag('00000', 'SC Lörzweiler'),
				self::eintrag('10101', 'SK Mannheim-Lindenhof'),
				self::eintrag('10200', ''),
				array('clubName' => 'ohne Kennziffer'),
				'kein Feld',
				self::eintrag('10300', 'Odenwald'),
				self::eintrag('50000', 'Hessischer Schachverband (im Backend berichtigt)'),
			)
		);
		$namen = self::namen($antwort['body']['data']);

		$this->assertArrayNotHasKey('#00000', $namen);
		$this->assertArrayNotHasKey('#10101', $namen, 'Vereine werden nicht ergänzt');
		$this->assertArrayNotHasKey('#10200', $namen, 'ohne Namen nicht ergänzen');
		$this->assertSame('Odenwald', $namen['#10300']);
		$this->assertSame('Hessischer Schachverband (im Backend berichtigt)', $namen['#50000'], 'Bestand vor fester Liste');
		$this->assertCount(17 + 1, $antwort['body']['data']);
	}

	/**
	 * Was nu in der Vereinsliste selbst liefert, bleibt unangetastet — kein
	 * zweiter Eintrag, kein überschriebener Name.
	 */
	public function testGelieferteEintraegeBleiben(): void
	{
		$geliefert = self::antwort(array(self::eintrag('10000', 'Baden (aus /clubs)'), self::eintrag('10100', 'Mannheim (aus /clubs)')));

		$mitStruktur = self::namen(API::ergaenzeVerbaende($geliefert, '', self::struktur(), array())['body']['data']);
		$this->assertSame('Baden (aus /clubs)', $mitStruktur['#10000']);
		$this->assertSame('Mannheim (aus /clubs)', $mitStruktur['#10100']);
		$this->assertCount(21, $mitStruktur);

		$ohneStruktur = API::ergaenzeVerbaende($geliefert, '', array(), array(self::eintrag('10100', 'Mannheim (Bestand)')));
		$this->assertSame('Mannheim (aus /clubs)', self::namen($ohneStruktur['body']['data'])['#10100']);
		$this->assertCount(2 + 16, $ohneStruktur['body']['data']);
	}

	/**
	 * Bei der Einzelabfrage (`clubs?vkz=…`) kommt nur der angefragte Verband
	 * dazu, sonst nichts — und für einen Verein gar nichts.
	 */
	public function testEinzelabfrage(): void
	{
		$leer = self::antwort(array());
		$bestand = array(self::eintrag('10100', 'Mannheim (Bestand)'), self::eintrag('10200', 'Heidelberg'));

		$this->assertSame(array('#10100' => 'Mannheim'), self::namen(API::ergaenzeVerbaende($leer, '10100', self::struktur(), $bestand)['body']['data']));
		$this->assertSame(array('#F0000' => 'Sachsen'), self::namen(API::ergaenzeVerbaende($leer, 'F0000', self::struktur(), $bestand)['body']['data']));
		$this->assertSame(array('#C0100' => 'Bezirk Oberschwaben'), self::namen(API::ergaenzeVerbaende($leer, 'C0100', self::struktur(), $bestand)['body']['data']));

		// Ohne Struktur: Bestand, dann feste Liste
		$this->assertSame(array('#10100' => 'Mannheim (Bestand)'), self::namen(API::ergaenzeVerbaende($leer, '10100', array(), $bestand)['body']['data']));
		$this->assertSame(array('#F0000' => 'Schachverband Sachsen e.V.'), self::namen(API::ergaenzeVerbaende($leer, 'F0000', array(), $bestand)['body']['data']));

		// Verein, unbekannter Verband, DSB, „numerisch gleich": nichts
		$verein = self::antwort(array(self::eintrag('10101', 'SK Mannheim-Lindenhof')));
		$this->assertSame($verein, API::ergaenzeVerbaende($verein, '10101', self::struktur(), $bestand));

		foreach (array('99900', '00000', 'L0000', '1e4') as $vkz) {
			$this->assertSame(array(), API::ergaenzeVerbaende($leer, $vkz, self::struktur(), $bestand)['body']['data'], $vkz);
		}
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
		$this->assertSame($antwort, API::ergaenzeVerbaende($antwort, '', self::struktur(), array(self::eintrag('10100', 'Mannheim'))));
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

	/**
	 * Die echte Antwort von `/dwz/dwzliste/federations` vom 13.10.2026 in
	 * Zahlen: 218 Einträge ergeben 196 Verbände — alle 195 des früheren
	 * Bestands und den neuen 61600. Die Rohantwort liegt unverändert unter
	 * `tests/fixtures/` (nur Verbandsnamen, keine Personendaten).
	 */
	public function testEchteAntwortVomDreizehntenOktober(): void
	{
		$struktur = json_decode((string) file_get_contents(__DIR__.'/../fixtures/federations_20261013.json'), true)['data'];
		$verbaende = API::verbaendeAusStruktur($struktur);
		$namen = self::namen($verbaende);

		$this->assertCount(218, $struktur);
		$this->assertCount(196, $verbaende);
		$this->assertCount(196, $namen, 'keine doppelten Kennziffern');
		$this->assertSame('Bezirk Unterland', $namen['#C0600']);
		$this->assertSame('Mülheim an der Ruhr 1922 e. V.', $namen['#61600']);

		foreach (API::landesverbaende() as $landesverband) {
			$this->assertArrayHasKey('#'.$landesverband['clubVkz'], $namen);
		}
	}
}
