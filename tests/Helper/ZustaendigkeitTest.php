<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Zustaendigkeit;

/**
 * Prüft Franks Regeln zum zuständigen Wertungsreferenten (1.53.0): Referent
 * von nu (API) und aus „Referenten" (Lokal), Vergleich, Ausgabereihenfolge
 * und Empfänger der Reklamation. Namen und Adressen sind erfunden.
 */
class ZustaendigkeitTest extends TestCase
{
	/**
	 * Turnierkopf, wie ihn nu flach liefert (Turnierinfo).
	 *
	 * @return array<string,mixed>
	 */
	private static function kopf(): array
	{
		return array(
			'uuid'              => 'b6739640-a6fb-4720-a384-0035413249eb',
			'label'             => 'OWL U10 Verbandsklasse',
			'vkz'               => '640',
			'referentFirstname' => 'Rita',
			'referentLastname'  => 'Referentin',
			'referentEmail'     => 'rita@example.org',
		);
	}

	/**
	 * Aufbereitete Person, wie sie Referentenbaum::zustaendig() liefert.
	 *
	 * @return array<string,mixed>
	 */
	private static function person(string $vorname, string $nachname, array $mails, string $versand, string $titel = ''): array
	{
		return array(
			'name'     => trim($titel.' '.$vorname.' '.$nachname),
			'vorname'  => $vorname,
			'nachname' => $nachname,
			'emails'   => array_map(static function ($m) { return '<a>'.$m.'</a>'; }, $mails),
			'klartext' => $mails,
			'adresse'  => $versand,
		);
	}

	/**
	 * nu liefert den Kopf flach oder unter „tournament" — beides wird
	 * gelesen. Ohne Nachnamen gibt es keinen Referenten; eine kaputte
	 * Adresse fällt weg, der Name bleibt.
	 */
	public function testAusTurnier(): void
	{
		$flach = Zustaendigkeit::ausTurnier(self::kopf());
		$this->assertSame(array('api', 'Rita Referentin', array('rita@example.org'), 'rita@example.org'), array($flach['quelle'], $flach['name'], $flach['emails'], $flach['versand']));

		$verschachtelt = Zustaendigkeit::ausTurnier(array('tournament' => self::kopf(), 'players' => array()));
		$this->assertSame($flach, $verschachtelt);

		$ohneNachname = self::kopf();
		$ohneNachname['referentLastname'] = '';
		$this->assertNull(Zustaendigkeit::ausTurnier($ohneNachname));

		$kaputt = self::kopf();
		$kaputt['referentEmail'] = 'keine adresse';
		$r = Zustaendigkeit::ausTurnier($kaputt);
		$this->assertSame(array(), $r['emails']);
		$this->assertSame('Rita Referentin', $r['name']);
	}

	/**
	 * Gleich über eine gemeinsame E-Mail-Adresse (auch die nicht angezeigte
	 * Versandadresse) oder über Vor- und Nachnamen — ohne Groß-/Klein-
	 * schreibung, doppelten Leerraum und Titel.
	 */
	public function testGleich(): void
	{
		$api = Zustaendigkeit::ausTurnier(self::kopf());

		$perMail = Zustaendigkeit::ausReferenten(array(self::person('Ria', 'Anders', array('RITA@example.org'), '')))[0];
		$perVersand = Zustaendigkeit::ausReferenten(array(self::person('Ria', 'Anders', array(), 'rita@example.org')))[0];
		$perName = Zustaendigkeit::ausReferenten(array(self::person('rita', 'REFERENTIN ', array('andere@example.org'), '', 'Dr.')))[0];
		$fremd = Zustaendigkeit::ausReferenten(array(self::person('Otto', 'Ohne', array('otto@example.org'), 'otto@example.org')))[0];

		$this->assertTrue(Zustaendigkeit::gleich($api, $perMail));
		$this->assertTrue(Zustaendigkeit::gleich($api, $perVersand));
		$this->assertTrue(Zustaendigkeit::gleich($api, $perName));
		$this->assertFalse(Zustaendigkeit::gleich($api, $fremd));
	}

	/**
	 * Franks Regeln für die Ausgabe: ohne nu die lokalen; nu gleich einem
	 * lokalen → nur nu; nu anders → nu und dahinter die lokalen.
	 */
	public function testEintraege(): void
	{
		$api = Zustaendigkeit::ausTurnier(self::kopf());
		$gleich = Zustaendigkeit::ausReferenten(array(self::person('Rita', 'Referentin', array('dwz@example.org', 'rita@example.org'), 'dwz@example.org')), 'Bezirk OWL');
		$anders = Zustaendigkeit::ausReferenten(array(self::person('Otto', 'Ohne', array('otto@example.org'), 'otto@example.org')), 'Bezirk OWL', true);

		$this->assertSame($anders, Zustaendigkeit::eintraege(null, $anders));
		$this->assertSame(array($api), Zustaendigkeit::eintraege($api, $gleich));
		$this->assertSame(array('api', 'lokal'), array_column(Zustaendigkeit::eintraege($api, $anders), 'quelle'));
		$this->assertSame(array($api), Zustaendigkeit::eintraege($api, array()));
		$this->assertSame(array(), Zustaendigkeit::eintraege(null, array()));

		// Lokale Einträge tragen Verband und Hinweis „über eine höhere Ebene"
		$this->assertSame(array('Bezirk OWL', true), array($anders[0]['verband'], $anders[0]['ersatzweise']));
	}

	/**
	 * Reklamationen gehen an den Referenten von nu; ohne ihn an die lokalen.
	 */
	public function testEmpfaenger(): void
	{
		$api = Zustaendigkeit::ausTurnier(self::kopf());
		$lokal = Zustaendigkeit::ausReferenten(array(
			self::person('Otto', 'Ohne', array('otto@example.org'), 'dwz-owl@example.org'),
			self::person('Dora', 'Dritte', array(), ''),
		));

		$this->assertSame(array(array('name' => 'Rita Referentin', 'email' => 'rita@example.org')), Zustaendigkeit::empfaenger(Zustaendigkeit::eintraege($api, $lokal)));
		$this->assertSame(array(array('name' => 'Otto Ohne', 'email' => 'dwz-owl@example.org'), array('name' => 'Dora Dritte', 'email' => '')), Zustaendigkeit::empfaenger($lokal));
		$this->assertSame(array(), Zustaendigkeit::empfaenger(array()));
	}
}
