<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Adressverknuepfung;

/**
 * Prüft den Teil der Verknüpfung mit der Adressverwaltung, der ohne Contao
 * auskommt: welche Daten aus der Adresse kommen, was verborgen bleibt, und
 * wie die Übernahme der Wertungsreferenten geplant wird.
 *
 * Alle Namen und Adressen sind erfunden.
 */
class AdressverknuepfungTest extends TestCase
{
	/**
	 * Referent, wie er in tl_wertungsportal_referenten stünde — mit eigenen
	 * Angaben, die bei zugeordneter Adresse nur Ersatz sind.
	 *
	 * @return array<string,mixed>
	 */
	private static function referent(): array
	{
		return array(
			'id'       => 3,
			'adresse'  => 17,
			'nachname' => 'Alt',
			'vorname'  => 'Anton',
			'nuId'     => 'NU1234567',
			'email'    => 'alt@example.org',
			'telefon'  => '030 111',
			'strasse'  => 'Alte Straße 1',
			'plz'      => '10115',
			'ort'      => 'Berlin',
		);
	}

	/**
	 * Adresse, wie sie in tl_adressen stünde: alles öffentlich, aktiv.
	 *
	 * @return array<string,mixed>
	 */
	private static function adresse(): array
	{
		return array(
			'id'           => 17,
			'nachname'     => 'Neu',
			'vorname'      => 'Nora',
			'titel'        => 'Dr.',
			'firma'        => '',
			'plz'          => '04109',
			'ort'          => 'Leipzig',
			'ort_view'     => '1',
			'strasse'      => 'Neue Straße 2',
			'strasse_view' => '1',
			'telefon1'     => '',
			'telefon2'     => '0341 222',
			'telefon3'     => '',
			'telefon4'     => '',
			'telefon_view' => '1',
			'email1'       => '',
			'email2'       => 'neu@example.org',
			'email3'       => '',
			'email4'       => '',
			'email5'       => '',
			'email6'       => '',
			'email_view'   => '1',
			'aktiv'        => 1,
		);
	}

	/**
	 * Ohne Adresse und mit inaktiver Adresse bleibt der Referent, wie er ist;
	 * die Versandadresse ist dann seine eigene.
	 */
	public function testOhneOderInaktiveAdresseGeltenDieEigenenAngaben(): void
	{
		foreach (array(null, array('aktiv' => 0) + self::adresse(), array('aktiv' => '') + self::adresse()) as $adresse) {
			$r = Adressverknuepfung::zusammenfuehren(self::referent(), $adresse);

			$this->assertFalse($r['ausAdressverwaltung']);
			$this->assertSame('Alt', $r['nachname']);
			$this->assertSame('alt@example.org', $r['email']);
			$this->assertSame('alt@example.org', $r['versandadresse']);
			$this->assertSame('030 111', $r['telefon']);
			$this->assertSame('', $r['titel']);
		}
	}

	/**
	 * Eine aktive Adresse liefert Name mit Titel, die erste belegte E-Mail und
	 * Telefonnummer und die Anschrift. Die nu-ID bleibt die eigene.
	 */
	public function testAktiveAdresseLiefertDieDaten(): void
	{
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), self::adresse());

		$this->assertTrue($r['ausAdressverwaltung']);
		$this->assertSame(array('Neu', 'Nora', 'Dr.'), array($r['nachname'], $r['vorname'], $r['titel']));
		$this->assertSame('neu@example.org', $r['email']);
		$this->assertSame('neu@example.org', $r['versandadresse']);
		$this->assertSame('0341 222', $r['telefon']);
		$this->assertSame(array('Neue Straße 2', '04109', 'Leipzig'), array($r['strasse'], $r['plz'], $r['ort']));
		$this->assertSame('NU1234567', $r['nuId']);
	}

	/**
	 * Was in der Adressverwaltung nicht öffentlich ist, bleibt leer — auch
	 * wenn der Referent selbst etwas hätte. Die E-Mail-Adresse bleibt aber
	 * Versandadresse für Reklamationen.
	 */
	public function testNichtOeffentlichesBleibtVerborgen(): void
	{
		$a = array('email_view' => '0', 'telefon_view' => '') + self::adresse();
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), $a);

		$this->assertSame('', $r['email']);
		$this->assertSame('neu@example.org', $r['versandadresse']);
		$this->assertSame('', $r['telefon']);

		// Ohne öffentlichen Ort auch keine Straße
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), array('ort_view' => '0') + self::adresse());
		$this->assertSame(array('', '', ''), array($r['strasse'], $r['plz'], $r['ort']));

		// Nur die Straße verborgen: PLZ und Ort bleiben
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), array('strasse_view' => '0') + self::adresse());
		$this->assertSame(array('', '04109', 'Leipzig'), array($r['strasse'], $r['plz'], $r['ort']));
	}

	/**
	 * Wo die Adresse gar nichts hat, springen die eigenen Felder ein. Die
	 * Anschrift wird dabei nie aus beiden Quellen gemischt.
	 */
	public function testEigeneFelderSpringenNurBeiLeererAdresseEin(): void
	{
		$a = self::adresse();
		$a['email2'] = '';
		$a['telefon2'] = '';
		$a['plz'] = $a['ort'] = $a['strasse'] = '';

		$r = Adressverknuepfung::zusammenfuehren(self::referent(), $a);

		$this->assertSame('alt@example.org', $r['email']);
		$this->assertSame('alt@example.org', $r['versandadresse']);
		$this->assertSame('030 111', $r['telefon']);
		$this->assertSame(array('Alte Straße 1', '10115', 'Berlin'), array($r['strasse'], $r['plz'], $r['ort']));

		// Nur ein Ort in der Adresse: die eigene Straße fällt weg, statt neben
		// einem fremden Ort zu stehen
		$a['ort'] = 'Leipzig';
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), $a);
		$this->assertSame(array('', '', 'Leipzig'), array($r['strasse'], $r['plz'], $r['ort']));

		// Adresse ohne Nachnamen (etwa eine Geschäftsstelle): Name bleibt der eigene
		$a['nachname'] = '';
		$a['firma'] = 'Geschäftsstelle';
		$this->assertSame('Alt', Adressverknuepfung::zusammenfuehren(self::referent(), $a)['nachname']);
	}

	/**
	 * Die Spalte aktiv ist in der Adressverwaltung ein Boolean; je nach
	 * Treiber kommt sie unterschiedlich an.
	 */
	public function testAktiv(): void
	{
		foreach (array(1, '1', true) as $wert) {
			$this->assertTrue(Adressverknuepfung::aktiv(array('aktiv' => $wert)), var_export($wert, true));
		}

		foreach (array(0, '0', '', false, null) as $wert) {
			$this->assertFalse(Adressverknuepfung::aktiv(array('aktiv' => $wert)), var_export($wert, true));
		}

		$this->assertTrue(Adressverknuepfung::aktiv(array()));
	}

	/**
	 * Anzeigename: „Nachname, Vorname", sonst Firma, sonst die ID.
	 */
	public function testName(): void
	{
		$this->assertSame('Neu, Nora', Adressverknuepfung::name(self::adresse()));
		$this->assertSame('Geschäftsstelle', Adressverknuepfung::name(array('id' => 5, 'nachname' => '', 'firma' => 'Geschäftsstelle')));
		$this->assertSame('Adresse 5', Adressverknuepfung::name(array('id' => 5)));
	}

	/**
	 * Leere Einträge fallen weg, eine „0" nicht (anders als bei array_filter
	 * ohne Prüfung); die Schlüssel bleiben.
	 */
	public function testOhneLeere(): void
	{
		$this->assertSame(array(0 => 'Dr.', 2 => '0', 3 => 'Neu'), Adressverknuepfung::ohneLeere(array('Dr.', '', '0', 'Neu')));
	}

	/**
	 * Die Übernahme: passende Verbände werden übernommen, fremde Schlüssel
	 * gemeldet, schon verknüpfte und inaktive Adressen übergangen.
	 */
	public function testUebernahmeplan(): void
	{
		// So liefert Referenten::getVerbaende() die Liste: PHP hat die
		// numerischen Kennziffern zu Ganzzahlen gemacht
		$verbaende = array('00000' => '00000 Deutscher Schachbund', 10000 => '10000 Baden', 10100 => '10100 Mannheim', 'C0000' => 'C0000 Niedersachsen');

		$adressen = array(
			array('id' => 1, 'nachname' => 'Eins', 'vorname' => 'Ella', 'aktiv' => '1', 'wertungsreferent' => serialize(array('10000', '10100'))),
			array('id' => 2, 'nachname' => 'Zwei', 'vorname' => 'Zoe', 'aktiv' => '1', 'wertungsreferent' => serialize(array('C0000', 'L0000', 'C0000'))),
			array('id' => 3, 'nachname' => 'Drei', 'vorname' => 'Dirk', 'aktiv' => '1', 'wertungsreferent' => serialize(array('61600'))),
			array('id' => 4, 'nachname' => 'Vier', 'vorname' => 'Vera', 'aktiv' => '0', 'wertungsreferent' => serialize(array('10000'))),
			array('id' => 5, 'nachname' => 'Fünf', 'vorname' => 'Fritz', 'aktiv' => '1', 'wertungsreferent' => serialize(array('00000'))),
			array('id' => 6, 'nachname' => 'Sechs', 'vorname' => 'Sina', 'aktiv' => '1', 'wertungsreferent' => serialize(array('10000'))),
			array('id' => 7, 'nachname' => 'Sieben', 'aktiv' => '1', 'wertungsreferent' => null),
			array('id' => 8, 'nachname' => 'Acht', 'aktiv' => '1', 'wertungsreferent' => 'kaputt'),
			array('id' => 9, 'nachname' => 'Neun', 'aktiv' => '1', 'wertungsreferent' => serialize(array())),
		);

		$plan = Adressverknuepfung::uebernahmeplan($adressen, $verbaende, array('6', 99));

		$this->assertSame(array(1, 2, 5), array_column($plan['neu'], 'adresse'));
		$this->assertSame(array('10000', '10100'), $plan['neu'][0]['verbaende']);
		$this->assertSame(array('C0000'), $plan['neu'][1]['verbaende'], 'doppelter Schlüssel nur einmal');
		$this->assertSame(array('L0000'), $plan['neu'][1]['unbekannt']);
		$this->assertSame(array('00000'), $plan['neu'][2]['verbaende'], 'der DSB selbst');
		$this->assertSame(array('Eins', 'Ella'), array($plan['neu'][0]['nachname'], $plan['neu'][0]['vorname']));

		$this->assertSame(array(array('adresse' => 3, 'name' => 'Drei, Dirk', 'unbekannt' => array('61600'))), $plan['ohneVerband']);
		$this->assertSame(array(array('adresse' => 6, 'name' => 'Sechs, Sina')), $plan['vorhanden']);
		$this->assertSame(1, $plan['inaktiv']);
		$this->assertSame(array('61600' => 1, 'L0000' => 1), $plan['unbekannt']);
	}

	/**
	 * Ein zweiter Lauf, nachdem alle angelegt sind, plant nichts Neues.
	 */
	public function testZweiterLaufLegtNichtsDoppeltAn(): void
	{
		$adressen = array(array('id' => 1, 'nachname' => 'Eins', 'aktiv' => '1', 'wertungsreferent' => serialize(array('10000'))));
		$erst = Adressverknuepfung::uebernahmeplan($adressen, array(10000 => 'Baden'), array());
		$dann = Adressverknuepfung::uebernahmeplan($adressen, array(10000 => 'Baden'), array_column($erst['neu'], 'adresse'));

		$this->assertCount(1, $erst['neu']);
		$this->assertSame(array(), $dann['neu']);
		$this->assertCount(1, $dann['vorhanden']);
	}

	/**
	 * Das Feld wird ohne Objekte entpackt: Ein untergeschobenes Objekt
	 * erzeugt keine Instanz und zählt nicht als Schlüssel.
	 */
	public function testKeineObjekteAusDemFeld(): void
	{
		$adressen = array(array('id' => 1, 'nachname' => 'Eins', 'aktiv' => '1', 'wertungsreferent' => serialize(array(new \ArrayObject(), '10000'))));
		$plan = Adressverknuepfung::uebernahmeplan($adressen, array(10000 => 'Baden'), array());

		$this->assertSame(array('10000'), $plan['neu'][0]['verbaende']);
		$this->assertSame(array(), $plan['unbekannt']);
	}
}
