<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Adressverknuepfung;

/**
 * Prüft den Teil der Verknüpfung mit der Adressverwaltung, der ohne Contao
 * auskommt: welche Kontaktdaten aus der Adresse kommen, was verborgen bleibt,
 * und dass die alten Kontaktspalten des Referenten nirgends mehr erscheinen.
 *
 * Alle Namen und Adressen sind erfunden.
 */
class AdressverknuepfungTest extends TestCase
{
	/**
	 * Referent, wie er in tl_wertungsportal_referenten stünde — mit der
	 * Funktions-E-Mail und Resten in den alten Spalten, die seit 1.52.0 nicht
	 * mehr ausgegeben werden dürfen.
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
			'email'    => 'dwz@verband.example.org',
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
			'telefon3'     => '0171 333',
			'telefon4'     => '',
			'telefon_view' => '1',
			'email1'       => '',
			'email2'       => 'neu@example.org',
			'email3'       => 'nora@example.org',
			'email4'       => 'DWZ@verband.example.org',
			'email5'       => '',
			'email6'       => '',
			'email_view'   => '1',
			'aktiv'        => 1,
		);
	}

	/**
	 * Ohne Adresse und mit inaktiver Adresse: nur Name und Funktions-E-Mail.
	 * Die alten Spalten telefon, strasse, plz und ort erscheinen NICHT.
	 */
	public function testOhneOderInaktiveAdresseNurNameUndFunktionsadresse(): void
	{
		foreach (array(null, array('aktiv' => 0) + self::adresse(), array('aktiv' => '') + self::adresse()) as $adresse) {
			$r = Adressverknuepfung::zusammenfuehren(self::referent(), $adresse);

			$this->assertFalse($r['ausAdressverwaltung']);
			$this->assertSame(array('Alt', 'Anton', ''), array($r['nachname'], $r['vorname'], $r['titel']));
			$this->assertSame(array('dwz@verband.example.org'), $r['emails']);
			$this->assertSame(array('dwz@verband.example.org'), $r['abgleich']);
			$this->assertSame('dwz@verband.example.org', $r['versandadresse']);
			$this->assertSame(array(), $r['telefone']);
			$this->assertSame(array('', '', ''), array($r['strasse'], $r['plz'], $r['ort']));
			$this->assertArrayNotHasKey('telefon', $r, 'die alte Einzelspalte bleibt draußen');
		}
	}

	/**
	 * Eine aktive Adresse liefert Name mit Titel, alle Telefonnummern und die
	 * Anschrift. Angezeigt wird NUR die Funktions-E-Mail (1.54.0); die
	 * Adressen der Adresse stehen allein in `abgleich` (Doppelte ohne
	 * Rücksicht auf Großschreibung entfernt).
	 */
	public function testAktiveAdresseLiefertAlleKontaktdaten(): void
	{
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), self::adresse());

		$this->assertTrue($r['ausAdressverwaltung']);
		$this->assertSame(array('Neu', 'Nora', 'Dr.'), array($r['nachname'], $r['vorname'], $r['titel']));
		$this->assertSame(array('dwz@verband.example.org'), $r['emails']);
		$this->assertSame(array('dwz@verband.example.org', 'neu@example.org', 'nora@example.org'), $r['abgleich']);
		$this->assertSame('dwz@verband.example.org', $r['versandadresse'], 'Reklamationen an die Funktions-E-Mail');
		$this->assertSame(array('0341 222', '0171 333'), $r['telefone']);
		$this->assertSame(array('Neue Straße 2', '04109', 'Leipzig'), array($r['strasse'], $r['plz'], $r['ort']));
		$this->assertSame('NU1234567', $r['nuId']);
	}

	/**
	 * Ohne Funktions-E-Mail wird KEINE E-Mail-Adresse angezeigt — die
	 * privaten Adressen der Adresse erscheinen nie. Reklamationen gehen
	 * trotzdem an die erste davon.
	 */
	public function testOhneFunktionsadresse(): void
	{
		$referent = array('email' => '') + self::referent();
		$r = Adressverknuepfung::zusammenfuehren($referent, self::adresse());

		$this->assertSame(array(), $r['emails']);
		$this->assertSame(array('neu@example.org', 'nora@example.org', 'DWZ@verband.example.org'), $r['abgleich']);
		$this->assertSame('neu@example.org', $r['versandadresse']);
	}

	/**
	 * Was in der Adressverwaltung nicht öffentlich ist, bleibt leer. Die
	 * Funktions-E-Mail bleibt sichtbar; ohne sie geht eine Reklamation an die
	 * erste Adresse der Adresse, die aber nie angezeigt wird — auf den
	 * Schalter email_view kommt es dafür nicht mehr an.
	 */
	public function testNichtOeffentlichesBleibtVerborgen(): void
	{
		$a = array('email_view' => '0', 'telefon_view' => '') + self::adresse();
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), $a);

		$this->assertSame(array('dwz@verband.example.org'), $r['emails']);
		$this->assertSame(array(), $r['telefone']);

		$r = Adressverknuepfung::zusammenfuehren(array('email' => '') + self::referent(), $a);
		$this->assertSame(array(), $r['emails']);
		$this->assertSame('neu@example.org', $r['versandadresse']);

		// Ohne öffentlichen Ort auch keine Straße
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), array('ort_view' => '0') + self::adresse());
		$this->assertSame(array('', '', ''), array($r['strasse'], $r['plz'], $r['ort']));

		// Nur die Straße verborgen: PLZ und Ort bleiben
		$r = Adressverknuepfung::zusammenfuehren(self::referent(), array('strasse_view' => '0') + self::adresse());
		$this->assertSame(array('', '04109', 'Leipzig'), array($r['strasse'], $r['plz'], $r['ort']));
	}

	/**
	 * Hat die Adresse selbst keine Kontaktdaten, springen die alten Spalten
	 * des Referenten NICHT ein — die Adressverwaltung ist die einzige Quelle.
	 */
	public function testKeinRueckfallAufDieAltenSpalten(): void
	{
		$a = self::adresse();
		$a['email2'] = $a['email3'] = $a['email4'] = '';
		$a['telefon2'] = $a['telefon3'] = '';
		$a['plz'] = $a['ort'] = $a['strasse'] = '';

		$r = Adressverknuepfung::zusammenfuehren(self::referent(), $a);

		$this->assertSame(array('dwz@verband.example.org'), $r['emails']);
		$this->assertSame(array(), $r['telefone']);
		$this->assertSame(array('', '', ''), array($r['strasse'], $r['plz'], $r['ort']));

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
	 * Anzeigename für die Auswahl: „Nachname, Vorname", sonst Firma, sonst
	 * die ID.
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
}
