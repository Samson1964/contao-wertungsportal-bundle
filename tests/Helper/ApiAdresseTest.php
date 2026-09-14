<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\API;

/**
 * Hält die Adressen fest, die `API::adresse()` für die zwölf
 * Schnittstellenfunktionen baut.
 *
 * Bis Fassung 1.41.0 standen die Adressen allein im Verteiler `getAPI()`,
 * mitten zwischen Abruf und Abgleich, und ließen sich nicht prüfen, ohne die
 * Schnittstelle aufzurufen. Beim Herauslösen durfte sich für das Frontend
 * nichts ändern — nicht einmal das abschließende `&`, das der Verteiler an
 * jede Abfragezeichenkette hängte. Die erwarteten Adressen hier sind aus dem
 * alten Verteiler abgelesen und mit den Parametern gebildet, die die
 * Frontend-Module tatsächlich übergeben.
 *
 * Geändert hat sich mit Absicht nur eines: Pfadteile werden kodiert.
 */
class ApiAdresseTest extends TestCase
{
	/**
	 * Eine Turnier-UUID in der Form, die nu im Frontend liefert.
	 */
	private const TURNIER = '019f75b9-af19-7de0-a772-7da0ebbd8f0c';

	/**
	 * Spielersuche mit Vor- und Nachname — mit abschließendem `&`.
	 */
	public function testSpielerliste(): void
	{
		$this->assertSame(
			'/dwz/dwzliste/persons?firstname=Karsten&lastname=M%C3%BCller&',
			API::adresse(array('funktion' => 'Spielerliste', 'vorname' => 'Karsten', 'nachname' => 'Müller'))
		);

		$this->assertSame(
			'/dwz/dwzliste/persons?lastname=Faber&',
			API::adresse(array('funktion' => 'Spielerliste', 'vorname' => '', 'nachname' => 'Faber'))
		);
	}

	/**
	 * Karteikarte und Turnierhistorie nehmen die NU-Nummer in den Pfad.
	 */
	public function testKarteikarte(): void
	{
		$this->assertSame('/dwz/dwzliste/persons/NU4338664', API::adresse(array('funktion' => 'Karteikarte', 'id' => 'NU4338664')));
		$this->assertSame('/dwz/persons/NU4338664/history', API::adresse(array('funktion' => 'Karteikarte_Turniere', 'id' => 'NU4338664')));
	}

	/**
	 * Der Spielberichtsbogen braucht Turnier und Spieler-UUID.
	 */
	public function testSpielberichtsbogen(): void
	{
		$this->assertSame(
			'/dwz/tournaments/'.self::TURNIER.'/players/019f75bb-20f8-7bd8-8c1f-80cc107e0047/scoresheet',
			API::adresse(array('funktion' => 'Spielberichtsbogen', 'turnier' => self::TURNIER, 'id' => '019f75bb-20f8-7bd8-8c1f-80cc107e0047'))
		);
	}

	/**
	 * Die drei Turnierfunktionen mit nur der UUID im Pfad.
	 */
	public function testTurnierfunktionen(): void
	{
		$this->assertSame('/dwz/tournaments/'.self::TURNIER, API::adresse(array('funktion' => 'Turnierinfo', 'turnier' => self::TURNIER)));
		$this->assertSame('/dwz/tournaments/'.self::TURNIER.'/evaluation', API::adresse(array('funktion' => 'Turnierauswertung', 'turnier' => self::TURNIER)));
		$this->assertSame('/dwz/tournaments/'.self::TURNIER.'/matches', API::adresse(array('funktion' => 'Turnierergebnisse', 'turnier' => self::TURNIER)));
	}

	/**
	 * Turniersuche: Die VKZ wird zum Präfix, abschließende Nullen fallen weg.
	 *
	 * So übergibt es `Classes\Turnier` für einen Landesverband (40000 → 4).
	 * Bleibt nach dem Kürzen nichts übrig (00000 = ganz Deutschland), entfällt
	 * die VKZ ganz.
	 */
	public function testTurnierliste(): void
	{
		$this->assertSame(
			'/dwz/tournaments?label=Fordopen&fromDate=2025-01-01&toDate=2026-12-31&vkz=4&',
			API::adresse(array('funktion' => 'Turnierliste', 'suche' => 'Fordopen', 'von' => '2025-01-01', 'bis' => '2026-12-31', 'zps' => '40000'))
		);

		$this->assertSame(
			'/dwz/tournaments?fromDate=2025-01-01&toDate=2026-12-31&',
			API::adresse(array('funktion' => 'Turnierliste', 'suche' => '', 'von' => '2025-01-01', 'bis' => '2026-12-31', 'zps' => '00000'))
		);

		$this->assertSame('/dwz/tournaments?', API::adresse(array('funktion' => 'Turnierliste')));
	}

	/**
	 * Mitgliederliste und Vereinsname — ohne abschließendes `&`.
	 */
	public function testVereinsfunktionen(): void
	{
		$this->assertSame('/dwz/dwzliste/persons?vkz=40039', API::adresse(array('funktion' => 'Vereinsliste', 'zps' => '40039')));
		$this->assertSame('/dwz/dwzliste/clubs?vkz=10614', API::adresse(array('funktion' => 'Vereinsname', 'zps' => '10614')));
		$this->assertSame('/dwz/dwzliste/clubs', API::adresse(array('funktion' => 'Verbaende')));
	}

	/**
	 * Verbandsrangliste mit den Werten, die `Classes\Verband` übergibt.
	 *
	 * Wichtig ist die Wahrheitsprüfung: Ein Alter von 0 und eine VKZ `false`
	 * (so steht „ganz Deutschland" dort) gelten als nicht angegeben. Mit einem
	 * strengen Vergleich auf den leeren Text kämen `minAge=0&` und `vkz=&` in
	 * die Adresse, und jede Deutschland-Rangliste hätte einen anderen
	 * Cacheschlüssel bei nu.
	 */
	public function testVerbandsliste(): void
	{
		$this->assertSame(
			'/dwz/dwzliste/persons?vkz=4&limit=550&gender=FEMALE&maxAge=20&',
			API::adresse(array('funktion' => 'Verbandsliste', 'zps' => '4', 'limit' => 550, 'geschlecht' => 'FEMALE', 'alter_von' => '', 'alter_bis' => 20))
		);

		$this->assertSame(
			'/dwz/dwzliste/persons?limit=60&',
			API::adresse(array('funktion' => 'Verbandsliste', 'zps' => false, 'limit' => 60, 'geschlecht' => '', 'alter_von' => 0, 'alter_bis' => ''))
		);
	}

	/**
	 * Unbekannte oder fehlende Funktion ergibt keine Adresse.
	 */
	public function testUnbekannteFunktion(): void
	{
		$this->assertNull(API::adresse(array('funktion' => 'Gibtsnicht')));
		$this->assertNull(API::adresse(array()));
		$this->assertNull(API::adresse('kein Array'));
	}

	/**
	 * Pfadteile werden kodiert — eine Eingabe bricht nicht aus dem Pfad aus.
	 *
	 * Ohne Kodierung spräche `../../tournaments` mit dem Zugangstoken des DSB
	 * einen ganz anderen Endpunkt an. Gültige Kennungen bleiben unverändert;
	 * das zeigen die Prüfungen oben.
	 */
	public function testPfadteileWerdenKodiert(): void
	{
		$this->assertSame(
			'/dwz/dwzliste/persons/..%2F..%2Ftournaments',
			API::adresse(array('funktion' => 'Karteikarte', 'id' => '../../tournaments'))
		);

		$this->assertSame(
			'/dwz/tournaments/x%3Flimit%3D1/evaluation',
			API::adresse(array('funktion' => 'Turnierauswertung', 'turnier' => 'x?limit=1'))
		);
	}

	/**
	 * Jede Funktion aus `endpunkte()` baut eine Adresse, und deren Pfad passt
	 * zum dort hinterlegten Muster.
	 *
	 * Die Liste in `endpunkte()` dient der Abrufstatistik. Kommt eine Funktion
	 * dazu und wird nur an einer der beiden Stellen eingetragen, fällt das
	 * hier auf.
	 */
	public function testPassendZuEndpunkte(): void
	{
		$muster = array('funktion' => '', 'id' => 'KENNUNG', 'turnier' => 'TURNIER', 'zps' => '12345', 'vorname' => 'V', 'nachname' => 'N', 'suche' => 'S', 'limit' => 1);

		foreach (API::endpunkte() as $funktion => $endpunkt)
		{
			$muster['funktion'] = $funktion;
			$adresse = API::adresse($muster);

			$this->assertNotNull($adresse, $funktion);

			$pfad = strtr((string) strtok($adresse, '?'), array('KENNUNG' => '{id}', 'TURNIER' => '{uuid}'));

			$this->assertSame($endpunkt, $pfad, $funktion);
		}
	}
}
