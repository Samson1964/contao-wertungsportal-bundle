<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Mitgliedergruppe;

/**
 * Prüft die Regel der „Mitgliedergruppe für DSB-Mitglieder" (1.54.0): Wessen
 * E-Mail-Adresse im Spielerbestand vorkommt, bekommt die Gruppe; wer nicht
 * vorkommt, verliert sie. Alle Adressen sind erfunden.
 */
class MitgliedergruppeTest extends TestCase
{
	/**
	 * ID der Gruppe in diesen Prüfungen.
	 */
	private const GRUPPE = 7;

	/**
	 * Zuordnen, entfernen, behalten — und die übrigen Gruppen des Mitglieds
	 * bleiben, wie sie sind.
	 */
	public function testPlan(): void
	{
		$mitglieder = array(
			array('id' => 1, 'email' => 'neu@example.org', 'groups' => serialize(array('3'))),
			array('id' => 2, 'email' => 'schon@example.org', 'groups' => serialize(array('7', '3'))),
			array('id' => 3, 'email' => 'weg@example.org', 'groups' => serialize(array('3', '7', '9'))),
			array('id' => 4, 'email' => 'nie@example.org', 'groups' => serialize(array('3'))),
			array('id' => 5, 'email' => 'ohnegruppen@example.org', 'groups' => null),
		);

		$plan = Mitgliedergruppe::plan($mitglieder, array('neu@example.org', 'schon@example.org', 'ohnegruppen@example.org'), self::GRUPPE);

		$this->assertSame(array(1 => array('3', '7'), 5 => array('7')), $plan['hinzu']);
		$this->assertSame(array(3 => array('3', '9')), $plan['weg']);
		$this->assertSame(2, $plan['unveraendert']);
	}

	/**
	 * Groß- und Kleinschreibung und Leerraum am Rand zählen nicht; ein
	 * Mitglied ohne E-Mail-Adresse gehört nie dazu, auch wenn im
	 * Spielerbestand leere Adressen stehen.
	 */
	public function testVergleichDerAdressen(): void
	{
		$mitglieder = array(
			array('id' => 1, 'email' => ' Max.Muster@Example.ORG ', 'groups' => ''),
			array('id' => 2, 'email' => '', 'groups' => serialize(array('7'))),
			array('id' => 3, 'email' => 'ö@example.org', 'groups' => ''),
		);

		$plan = Mitgliedergruppe::plan($mitglieder, array('max.muster@example.org', '', '  ', 'Ö@EXAMPLE.ORG'), self::GRUPPE);

		$this->assertSame(array(1, 3), array_keys($plan['hinzu']));
		$this->assertSame(array(2 => array()), $plan['weg'], 'ohne Adresse verliert das Mitglied die Gruppe');
	}

	/**
	 * Ein zweiter Lauf mit dem Ergebnis des ersten ändert nichts mehr.
	 */
	public function testZweiterLaufAendertNichts(): void
	{
		$mitglieder = array(
			array('id' => 1, 'email' => 'a@example.org', 'groups' => serialize(array('3'))),
			array('id' => 2, 'email' => 'b@example.org', 'groups' => serialize(array('7'))),
		);
		$adressen = array('a@example.org');

		$erst = Mitgliedergruppe::plan($mitglieder, $adressen, self::GRUPPE);
		$mitglieder[0]['groups'] = serialize($erst['hinzu'][1]);
		$mitglieder[1]['groups'] = serialize($erst['weg'][2]);
		$dann = Mitgliedergruppe::plan($mitglieder, $adressen, self::GRUPPE);

		$this->assertSame(array(), $dann['hinzu']);
		$this->assertSame(array(), $dann['weg']);
		$this->assertSame(2, $dann['unveraendert']);
	}

	/**
	 * Das Feld groups wird ohne Objekte entpackt und verträgt NULL, Leeres,
	 * Kaputtes, Zahlen und Doppelte.
	 */
	public function testGruppenlisteLesen(): void
	{
		$this->assertSame(array('3', '7'), Mitgliedergruppe::gruppen(serialize(array('3', 7, '3', '', 0, '7'))));
		$this->assertSame(array('3'), Mitgliedergruppe::gruppen(array(3)));
		$this->assertSame(array(), Mitgliedergruppe::gruppen(null));
		$this->assertSame(array(), Mitgliedergruppe::gruppen(''));
		$this->assertSame(array(), Mitgliedergruppe::gruppen('kaputt'));
		$this->assertSame(array('3'), Mitgliedergruppe::gruppen(serialize(array(new \ArrayObject(), '3'))));
	}

	/**
	 * Die Meldung nennt je Zustand, was geschah — im Probelauf im Konjunktiv.
	 */
	public function testMeldung(): void
	{
		$ok = array('status' => 'ok', 'gruppe' => 7, 'name' => 'DSB-Mitglieder', 'mitglieder' => 10, 'hinzu' => array(1, 5), 'weg' => array(3), 'unveraendert' => 7, 'grund' => '');

		$this->assertSame('Mitgliedergruppe „DSB-Mitglieder" (ID 7): 10 Mitglieder geprüft, 2 zugeordnet, 1 entfernt, 7 unverändert.', Mitgliedergruppe::meldung($ok));
		$this->assertStringContainsString('Probelauf, nichts geschrieben', Mitgliedergruppe::meldung($ok, true));
		$this->assertStringContainsString('2 bekämen die Gruppe, 1 verlören sie', Mitgliedergruppe::meldung($ok, true));
		$this->assertStringContainsString('nichts getan', Mitgliedergruppe::meldung(array('status' => 'aus') + $ok));
		$this->assertStringContainsString('gibt es nicht', Mitgliedergruppe::meldung(array('status' => 'fehlt') + $ok));
		$this->assertStringContainsString('damit niemand die Gruppe verliert', Mitgliedergruppe::meldung(array('status' => 'leer') + $ok));
	}
}
