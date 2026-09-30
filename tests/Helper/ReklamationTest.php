<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation;

/**
 * Prüft den Teil der Reklamationen, der ohne Contao auskommt: Kontexte,
 * Empfänger, vorbelegte Texte, Bereinigung und Signatur.
 *
 * Die Turnierdaten stammen aus einer echten Antwort von nu (OWL U10
 * Verbandsklasse, Meldung vom 27.09.2026); Namen und Adresse des Auswerters
 * sind erfunden.
 */
class ReklamationTest extends TestCase
{
	/**
	 * Schlüssel für die Signatur — nur für diese Prüfungen.
	 */
	private const GEHEIMNIS = 'pruefschluessel-nur-fuer-tests';

	/**
	 * DSB-Admin, wie er in den Einstellungen stünde.
	 *
	 * @var array<string,string>
	 */
	private const ADMIN = array('name' => 'DSB-Wertungsreferat', 'email' => 'wertung@example.org');

	/**
	 * Turnierkopf, wie ihn nu liefert.
	 *
	 * @return array<string,mixed>
	 */
	private static function turnier(): array
	{
		return array(
			'uuid'              => 'b6739640-a6fb-4720-a384-0035413249eb',
			'label'             => 'OWL U10 Verbandsklasse',
			'vkz'               => '640',
			'startdate'         => '2026-02-28',
			'enddate'           => '2026-06-13',
			'referentFirstname' => 'Stefan',
			'referentLastname'  => 'Muster',
			'referentEmail'     => 'auswerter@example.org',
		);
	}

	/**
	 * Der Auswerter aus dem Turnierkopf wird Empfänger; Betreff und Angaben
	 * nennen Turnier, Zeitraum, Turniercode und — beim Bogen — den Spieler.
	 */
	public function testTurnierkontextMitAuswerter(): void
	{
		$k = Reklamation::fuerTurnier(self::turnier(), 'spielberichtsbogen', 'https://www.schachbund.de/dwz-turniere/b67/7ea.html', array('name' => 'Hendrik Pham', 'id' => 'NU4481210'));

		$this->assertSame(array(array('name' => 'Stefan Muster', 'email' => 'auswerter@example.org')), $k['referenten']);
		$this->assertSame('Reklamation zu OWL U10 Verbandsklasse – Spielberichtsbogen Hendrik Pham', Reklamation::betreff($k));
		$this->assertContains(array('Zeitraum', '28.02.2026 – 13.06.2026'), $k['angaben']);
		$this->assertContains(array('Turniercode', 'b6739640-a6fb-4720-a384-0035413249eb'), $k['angaben']);
		$this->assertContains(array('Spieler', 'Hendrik Pham (NU4481210)'), $k['angaben']);
		$this->assertSame(array('id' => 'NU4481210', 'name' => 'Hendrik Pham'), $k['spieler']);
		$this->assertSame('b6739640-a6fb-4720-a384-0035413249eb', $k['turnier']['uuid']);
	}

	/**
	 * „Ist kein Name vorhanden, wird der DSB-Admin als Empfänger verwendet":
	 * Ohne Nachnamen des Auswerters bleibt die Referentenliste leer — auch mit
	 * Adresse. Eine kaputte Adresse zählt ebenfalls nicht.
	 */
	public function testOhneAuswerterNameGehtEsAnDenAdmin(): void
	{
		$turnier = self::turnier();
		$turnier['referentLastname'] = '';
		$k = Reklamation::fuerTurnier($turnier, 'turnierauswertung', 'https://x.test/');
		$this->assertSame(array(), $k['referenten']);
		$this->assertSame(array(array('name' => 'DSB-Wertungsreferat', 'email' => 'wertung@example.org', 'art' => 'admin')), Reklamation::empfaenger($k['referenten'], self::ADMIN));

		$turnier = self::turnier();
		$turnier['referentEmail'] = 'keine adresse';
		$this->assertSame(array(), Reklamation::fuerTurnier($turnier, 'turnierauswertung', 'https://x.test/')['referenten']);

		unset($turnier['referentEmail']);
		$this->assertSame(array(), Reklamation::fuerTurnier($turnier, 'turnierauswertung', 'https://x.test/')['referenten']);
	}

	/**
	 * Der Betreff unterscheidet die Ansichten; ein Turnier nur mit Enddatum
	 * zeigt dieses eine Datum.
	 */
	public function testBetreffJeAnsicht(): void
	{
		$turnier = self::turnier();
		$this->assertSame('Reklamation zu OWL U10 Verbandsklasse', Reklamation::betreff(Reklamation::fuerTurnier($turnier, 'turnierauswertung', '')));
		$this->assertSame('Reklamation zu OWL U10 Verbandsklasse (Ergebnisliste)', Reklamation::betreff(Reklamation::fuerTurnier($turnier, 'turnierergebnisse', '')));
		$this->assertSame('Reklamation zur DWZ-Karteikarte von Hendrik Pham', Reklamation::betreff(Reklamation::fuerKarteikarte('Hendrik Pham', 'NU4481210', '')));
		$this->assertSame('Reklamation zur DWZ-Liste von LSV Turm Lippstadt', Reklamation::betreff(Reklamation::fuerVerein('LSV Turm Lippstadt', '64231', '')));
		$this->assertSame('Reklamation zur Spielersuche „Müller“', Reklamation::betreff(Reklamation::fuerSuche('spielersuche', 'Müller', '')));
		$this->assertSame('Reklamation zur Turniersuche', Reklamation::betreff(Reklamation::fuerSuche('turniersuche', '', '')));

		unset($turnier['startdate']);
		$this->assertContains(array('Zeitraum', '13.06.2026'), Reklamation::fuerTurnier($turnier, 'turnierauswertung', '')['angaben']);
	}

	/**
	 * Mehrere Referenten, einer doppelt, einer ohne gültige Adresse: Es bleiben
	 * die gültigen, jeder einmal — und der Admin springt nicht ein.
	 */
	public function testEmpfaengerAusDerReferentenverwaltung(): void
	{
		$k = Reklamation::fuerRangliste('Westfalen Top 10', '600', array(
			array('name' => 'Anna Beispiel', 'email' => 'anna@example.org'),
			array('name' => 'Ohne Adresse', 'email' => ''),
			array('name' => 'Bernd Beispiel', 'email' => 'bernd@example.org'),
		), 'https://x.test/');

		$e = Reklamation::empfaenger($k['referenten'] + array(3 => array('name' => 'Anna doppelt', 'email' => 'ANNA@example.org')), self::ADMIN);

		$this->assertSame(array('anna@example.org', 'bernd@example.org'), array_column($e, 'email'));
		$this->assertSame(array('referent', 'referent'), array_column($e, 'art'));
	}

	/**
	 * Ohne gültige Admin-Adresse gibt es niemanden, der die Nachricht bekäme —
	 * dann erscheint auch kein Link.
	 */
	public function testOhneAdminKeinEmpfaenger(): void
	{
		$this->assertSame(array(), Reklamation::empfaenger(array(), array('name' => 'X', 'email' => '')));
	}

	/**
	 * „Der Admin bekommt auch von jeder Reklamation eine Kopie als Bcc" — außer
	 * er ist selbst Empfänger: als Ersatz für fehlende Referenten oder weil ein
	 * Referent dieselbe Adresse hat (in anderer Schreibweise).
	 */
	public function testBlindkopieNurWennDerAdminNichtEmpfaengerIst(): void
	{
		$referent = Reklamation::empfaenger(array(array('name' => 'Anna Beispiel', 'email' => 'anna@example.org')), self::ADMIN);
		$this->assertSame('wertung@example.org', Reklamation::blindkopie($referent, self::ADMIN));

		$admin = Reklamation::empfaenger(array(), self::ADMIN);
		$this->assertSame('', Reklamation::blindkopie($admin, self::ADMIN));

		$gleich = Reklamation::empfaenger(array(array('name' => 'Referat', 'email' => 'Wertung@Example.org')), self::ADMIN);
		$this->assertSame('', Reklamation::blindkopie($gleich, self::ADMIN));

		$this->assertSame('', Reklamation::blindkopie($referent, array('name' => 'X', 'email' => 'kaputt')));
	}

	/**
	 * Der vorbelegte Text: Anrede mit Namen bei genau einem Empfänger, alle
	 * Angaben untereinander ausgerichtet, die Adresse der Seite, die Zeile
	 * „Was stimmt nicht?" — und dort die Schreibmarke.
	 */
	public function testVorlage(): void
	{
		$k = Reklamation::fuerTurnier(self::turnier(), 'spielberichtsbogen', 'https://www.schachbund.de/bogen.html', array('name' => 'Hendrik Pham', 'id' => 'NU4481210'));
		$e = Reklamation::empfaenger($k['referenten'], self::ADMIN);
		$v = Reklamation::vorlage($k, $e, 'Torsten Kötteritzsch');

		$this->assertStringStartsWith("Guten Tag Stefan Muster,\n", $v['text']);
		$this->assertStringContainsString("Turnier:     OWL U10 Verbandsklasse\n", $v['text']);
		$this->assertStringContainsString("Spieler:     Hendrik Pham (NU4481210)\n", $v['text']);
		$this->assertStringContainsString("Seite:       https://www.schachbund.de/bogen.html\n", $v['text']);
		$this->assertStringEndsWith("Mit freundlichen Grüßen\nTorsten Kötteritzsch\n", $v['text']);

		// Die Schreibmarke steht auf der leeren Zeile unter „Was stimmt nicht?"
		$this->assertSame(Reklamation::MARKE."\n", mb_substr($v['text'], $v['cursor'] - mb_strlen(Reklamation::MARKE) - 1, mb_strlen(Reklamation::MARKE) + 1));
		$this->assertSame("\n\nMit", mb_substr($v['text'], $v['cursor'], 5));

		// Mehrere Empfänger: neutrale Anrede
		$zwei = array(array('name' => 'A', 'email' => 'a@example.org'), array('name' => 'B', 'email' => 'b@example.org'));
		$this->assertStringStartsWith("Guten Tag,\n", Reklamation::vorlage($k, $zwei, 'X')['text']);
	}

	/**
	 * Die Fußzeile nennt das Mitglied aus dem Konto, die Seite und die Zeit —
	 * sie ist der Teil, auf den sich der Empfänger verlassen kann.
	 */
	public function testFusszeile(): void
	{
		$zeit = mktime(14, 12, 0, 9, 30, 2026);
		$f = Reklamation::fusszeile(array('id' => 4711, 'name' => 'Max Mustermann', 'email' => 'max@example.org'), array('url' => 'https://www.schachbund.de/x.html'), 'www.schachbund.de', $zeit);

		$this->assertStringStartsWith("-- \n", $f);
		$this->assertStringContainsString('auf www.schachbund.de am 30.09.2026 um 14:12 Uhr', $f);
		$this->assertStringContainsString('Absender: Max Mustermann <max@example.org>, Mitglied Nr. 4711', $f);
		$this->assertStringContainsString('Seite: https://www.schachbund.de/x.html', $f);
		$this->assertStringContainsString('geht direkt an Max Mustermann', $f);
	}

	/**
	 * Ein Zeilenumbruch im Betreff wäre ein Einfallstor für untergeschobene
	 * Mailkopfzeilen („Bcc: …"). Er wird zum Leerzeichen, der Betreff bleibt
	 * einzeilig und höchstens 200 Zeichen lang.
	 */
	public function testBetreffOhneKopfzeilen(): void
	{
		$b = Reklamation::bereinigeBetreff("Reklamation\r\nBcc: fremd@example.org\n\tX");
		$this->assertSame('Reklamation Bcc: fremd@example.org X', $b);
		$this->assertStringNotContainsString("\n", $b);
		$this->assertSame(Reklamation::BETREFF_LAENGE, mb_strlen(Reklamation::bereinigeBetreff(str_repeat('ä', 500))));
	}

	/**
	 * Der Text: einheitliche Zeilenenden, keine Steuerzeichen, Tabulator und
	 * Umbruch bleiben, Längengrenze, kaputtes UTF-8 gilt als leer.
	 */
	public function testTextBereinigung(): void
	{
		$this->assertSame("Zeile 1\nZeile 2\n\tEingerückt", Reklamation::bereinigeText("Zeile 1\r\nZeile 2\r\t\x07Eingerückt\x00"));
		$this->assertSame('', Reklamation::bereinigeText("kaputt \xC3\x28"));
		$this->assertSame(Reklamation::TEXT_LAENGE, mb_strlen(Reklamation::bereinigeText(str_repeat('x', 20000))));
	}

	/**
	 * Leerer Betreff oder (fast) leerer Text werden abgewiesen.
	 */
	public function testEingabefehler(): void
	{
		$this->assertNotNull(Reklamation::eingabefehler('', str_repeat('x', 50)));
		$this->assertNotNull(Reklamation::eingabefehler('Betreff', 'zu kurz'));
		$this->assertNull(Reklamation::eingabefehler('Betreff', 'Die Partie in Runde 7 fehlt in meiner Auswertung.'));
	}

	/**
	 * Der Kontext übersteht die Reise durch den Browser nur unverändert: Wer
	 * darin den Empfänger austauscht, die Signatur fälscht oder einen anderen
	 * Schlüssel benutzt, bekommt null — sonst ließe sich über das Formular jede
	 * Adresse anschreiben, mit der Absenderadresse des DSB.
	 */
	public function testSignatur(): void
	{
		$k = Reklamation::fuerTurnier(self::turnier(), 'turnierauswertung', 'https://x.test/');
		$k['empfaenger'] = Reklamation::empfaenger($k['referenten'], self::ADMIN);
		$s = Reklamation::signiere($k, self::GEHEIMNIS);

		$this->assertSame($k, Reklamation::entschluessele($s['kontext'], $s['signatur'], self::GEHEIMNIS));
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $s['kontext'], 'ohne Anführungszeichen und Klammern, für das HTML-Attribut');

		// Empfänger ausgetauscht, alte Signatur behalten
		$gefaelscht = $k;
		$gefaelscht['empfaenger'] = array(array('name' => 'Fremd', 'email' => 'fremd@example.org', 'art' => 'referent'));
		$this->assertNull(Reklamation::entschluessele(Reklamation::signiere($gefaelscht, 'anderer schluessel')['kontext'], $s['signatur'], self::GEHEIMNIS));

		$this->assertNull(Reklamation::entschluessele($s['kontext'], $s['signatur'], 'falscher schluessel'));
		$this->assertNull(Reklamation::entschluessele($s['kontext'], str_repeat('0', 64), self::GEHEIMNIS));
		$this->assertNull(Reklamation::entschluessele($s['kontext'], '', self::GEHEIMNIS));
		$this->assertNull(Reklamation::entschluessele('', $s['signatur'], self::GEHEIMNIS));
		$this->assertNull(Reklamation::entschluessele($s['kontext'], $s['signatur'], ''), 'ohne Schlüssel wird nichts angenommen');
	}

	/**
	 * Steuerzeichen und Zeilenumbrüche in Daten von nu kommen nicht in den
	 * Kontext — sie landeten sonst im Betreff.
	 */
	public function testDatenVonNuWerdenEinzeilig(): void
	{
		$turnier = self::turnier();
		$turnier['label'] = "OWL\nU10\r\n Verbandsklasse\x07";
		$this->assertSame('Reklamation zu OWL U10 Verbandsklasse', Reklamation::betreff(Reklamation::fuerTurnier($turnier, 'turnierauswertung', '')));
	}

	/**
	 * Die NU-Nummer des Bogeninhabers steht in seinen Partien — mal bei Weiß,
	 * mal bei Schwarz.
	 */
	public function testNuIdAusBogen(): void
	{
		$bogen = array('matches' => array(
			array('whitePlayer' => array('playerUuid' => 'a', 'nuLigaPersonId' => 'NU1'), 'blackPlayer' => array('playerUuid' => 'b', 'nuLigaPersonId' => 'NU2')),
			array('whitePlayer' => array('playerUuid' => 'c', 'nuLigaPersonId' => 'NU3'), 'blackPlayer' => array('playerUuid' => 'a', 'nuLigaPersonId' => 'NU1')),
		));

		$this->assertSame('NU2', Reklamation::nuIdAusBogen($bogen, 'b'));
		$this->assertSame('NU1', Reklamation::nuIdAusBogen($bogen, 'a'));
		$this->assertSame('', Reklamation::nuIdAusBogen($bogen, 'gibt-es-nicht'));
		$this->assertSame('', Reklamation::nuIdAusBogen(array(), 'a'));
	}

	/**
	 * Fehler bis 1.52.0: Die Turnierauswertung liefert den Kopf unter
	 * „tournament". Er muss genauso gelesen werden wie der flache — mit
	 * Turniername, Zeitraum, Turniercode und dem Auswerter als Empfänger.
	 */
	public function testTurnierkopfUnterTournament(): void
	{
		$flach = Reklamation::fuerTurnier(self::turnier(), 'turnierauswertung', 'https://x.test/');
		$verschachtelt = Reklamation::fuerTurnier(array('tournament' => self::turnier(), 'players' => array()), 'turnierauswertung', 'https://x.test/');

		$this->assertSame($flach, $verschachtelt);
		$this->assertSame('Reklamation zu OWL U10 Verbandsklasse', Reklamation::betreff($verschachtelt));
		$this->assertContains(array('Turniercode', 'b6739640-a6fb-4720-a384-0035413249eb'), $verschachtelt['angaben']);
		$this->assertSame(array(array('name' => 'Stefan Muster', 'email' => 'auswerter@example.org')), $verschachtelt['referenten']);
	}

	/**
	 * Lokale Referenten (1.53.0): beim Turnier nur, wenn nu keinen Auswerter
	 * nennt; bei Karteikarte und Verein immer, wenn welche übergeben werden.
	 */
	public function testLokaleReferentenAlsEmpfaenger(): void
	{
		$lokal = array(array('name' => 'Otto Ohne', 'email' => 'dwz-owl@example.org'));

		$mitAuswerter = Reklamation::fuerTurnier(self::turnier(), 'turnierergebnisse', '', null, $lokal);
		$this->assertSame('auswerter@example.org', $mitAuswerter['referenten'][0]['email'], 'der Auswerter von nu hat Vorrang');

		$turnier = self::turnier();
		$turnier['referentLastname'] = '';
		$this->assertSame($lokal, Reklamation::fuerTurnier($turnier, 'turnierergebnisse', '', null, $lokal)['referenten']);
		$this->assertSame(array(), Reklamation::fuerTurnier($turnier, 'turnierergebnisse', '')['referenten'], 'ohne beide: Admin');

		$this->assertSame($lokal, Reklamation::fuerKarteikarte('Hendrik Pham', 'NU4481210', '', $lokal)['referenten']);
		$this->assertSame($lokal, Reklamation::fuerVerein('LSV Turm Lippstadt', '64231', '', $lokal)['referenten']);
		$this->assertSame(array(), Reklamation::fuerVerein('LSV Turm Lippstadt', '64231', '')['referenten']);

		$e = Reklamation::empfaenger($lokal, self::ADMIN);
		$this->assertSame(array('dwz-owl@example.org', 'referent'), array($e[0]['email'], $e[0]['art']));
		$this->assertSame('wertung@example.org', Reklamation::blindkopie($e, self::ADMIN));
	}
}
