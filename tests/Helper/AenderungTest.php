<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Aenderung;

/**
 * Prüft den Teil der Änderungsmeldungen („Foto ändern", „Logo/Infos ändern"),
 * der ohne Contao auskommt: Kontexte, Dateiprüfung, Homepage, die Säuberung
 * des HTML aus dem Editor, die Regel „nichts geändert, nichts senden" und die
 * Texte der Mails. Alle Namen und Adressen sind erfunden.
 */
class AenderungTest extends TestCase
{
	/**
	 * Höchstgröße in diesen Prüfungen (5 MB).
	 */
	private const MAX = 5242880;

	/**
	 * Eine einwandfreie Bilddatei, wie Aenderung::untersuche() sie beschreibt.
	 *
	 * @return array<string,mixed>
	 */
	private static function bild(): array
	{
		return array('pfad' => '/tmp/x', 'fehler' => UPLOAD_ERR_OK, 'groesse' => 1258291, 'mime' => 'image/jpeg', 'breite' => 800, 'hoehe' => 1000);
	}

	/**
	 * Die Kontexte nennen Bereich, Gegenstand (wird Betreff) und beim Verein
	 * die bisherige Homepage samt Prüfsumme der bisherigen Beschreibung.
	 */
	public function testKontexte(): void
	{
		$foto = Aenderung::fuerFoto('Hendrik Pham', 'NU4481210', 'https://x.test/k');
		$this->assertSame('foto', $foto['bereich']);
		$this->assertSame('Neues Foto für Hendrik Pham (NU4481210)', $foto['gegenstand']);
		$this->assertSame(array('id' => 'NU4481210', 'name' => 'Hendrik Pham'), $foto['spieler']);

		$verein = Aenderung::fuerVereinsdaten('LSV Turm Lippstadt', '64231', 'https://x.test/v', ' https://alt.example.org ', "<p>Alt</p>\n");
		$this->assertSame('vereinsdaten', $verein['bereich']);
		$this->assertSame('Änderung der Vereinsdaten: LSV Turm Lippstadt (64231)', $verein['gegenstand']);
		$this->assertSame('https://alt.example.org', $verein['homepage']);
		$this->assertSame(sha1('<p>Alt</p>'), $verein['info']);

		// Zeilenumbrüche in Namen (aus fremden Daten) werden eingeebnet
		$this->assertSame('Neues Foto für A B', Aenderung::fuerFoto("A\r\nB", '', '')['gegenstand']);
	}

	/**
	 * Dateiprüfung: Pflicht oder nicht, Größe, Fehlercodes des Servers und —
	 * am Inhalt erkannt — nur Bilder der vier Formate.
	 */
	public function testDateifehler(): void
	{
		$this->assertNull(Aenderung::dateifehler(self::bild(), true, self::MAX));
		$this->assertNull(Aenderung::dateifehler(null, false, self::MAX), 'Logo ist freiwillig');
		$this->assertSame('Bitte wählen Sie eine Bilddatei aus.', Aenderung::dateifehler(null, true, self::MAX));
		$this->assertSame('Bitte wählen Sie eine Bilddatei aus.', Aenderung::dateifehler(array('fehler' => UPLOAD_ERR_NO_FILE), true, self::MAX));

		$this->assertStringContainsString('zu groß — höchstens 5 MB', (string) Aenderung::dateifehler(array('groesse' => self::MAX + 1) + self::bild(), true, self::MAX));
		$this->assertStringContainsString('zu groß', (string) Aenderung::dateifehler(array('fehler' => UPLOAD_ERR_INI_SIZE) + self::bild(), true, self::MAX));
		$this->assertStringContainsString('nicht hochgeladen', (string) Aenderung::dateifehler(array('fehler' => UPLOAD_ERR_PARTIAL) + self::bild(), true, self::MAX));
		$this->assertSame('Die Datei ist leer.', Aenderung::dateifehler(array('groesse' => 0) + self::bild(), true, self::MAX));

		foreach (array('application/pdf', 'image/svg+xml', 'text/html', 'application/x-php', '') as $mime) {
			$this->assertStringContainsString('JPEG, PNG, GIF oder WebP', (string) Aenderung::dateifehler(array('mime' => $mime) + self::bild(), true, self::MAX), $mime);
		}

		// Richtiger Typ gemeldet, aber kein lesbares Bild (keine Abmessungen)
		$this->assertStringContainsString('JPEG, PNG, GIF oder WebP', (string) Aenderung::dateifehler(array('breite' => 0, 'hoehe' => 0) + self::bild(), true, self::MAX));
	}

	/**
	 * Der Name des Anhangs kommt aus dem Kontext, nie vom Absender; fremde
	 * Zeichen in der Kennung fallen heraus.
	 */
	public function testAnhangname(): void
	{
		$this->assertSame('Foto-NU4481210.jpg', Aenderung::anhangname(Aenderung::fuerFoto('H', 'NU4481210', ''), 'image/jpeg'));
		$this->assertSame('Logo-64231.png', Aenderung::anhangname(Aenderung::fuerVereinsdaten('V', '64231', '', '', ''), 'image/png'));
		$this->assertSame('Foto-etcpasswd.webp', Aenderung::anhangname(Aenderung::fuerFoto('H', '../../etc/passwd', ''), 'image/webp'));
		$this->assertSame('Foto.gif', Aenderung::anhangname(Aenderung::fuerFoto('H', '', ''), 'image/gif'));
	}

	/**
	 * Homepage: leer ist erlaubt, fehlendes Protokoll wird ergänzt, nur http
	 * und https gelten.
	 */
	public function testHomepage(): void
	{
		$this->assertSame(array('wert' => '', 'fehler' => null), Aenderung::bereinigeHomepage('  '));
		$this->assertSame(array('wert' => 'https://www.verein.example.org', 'fehler' => null), Aenderung::bereinigeHomepage(' www.verein.example.org '));
		$this->assertSame(array('wert' => 'http://verein.example.org/schach', 'fehler' => null), Aenderung::bereinigeHomepage('http://verein.example.org/schach'));

		foreach (array('javascript:alert(1)', 'ftp://verein.example.org', 'https://ohnepunkt', 'das ist keine adresse', 'https://'.str_repeat('a', 260).'.example.org') as $falsch) {
			$this->assertNotNull(Aenderung::bereinigeHomepage($falsch)['fehler'], $falsch);
		}
	}

	/**
	 * Erlaubte Auszeichnung bleibt, Umlaute bleiben lesbar, Attribute
	 * verschwinden — bis auf href (http, https, mailto) und title an Verweisen.
	 */
	public function testHtmlErlaubtes(): void
	{
		$html = '<h2>Über uns</h2><p class="x" style="color:red">Wir spielen <strong>dienstags</strong> und <em>freitags</em>.</p>'
			.'<ul><li>Jugend</li><li>Senioren</li></ul>'
			.'<p><a href="https://verein.example.org/termine" title="Termine" target="_blank" onclick="x()">Termine</a> · <a href="mailto:info@example.org">Mail</a></p>';

		$sauber = Aenderung::bereinigeHtml($html);

		$this->assertStringContainsString('<h2>Über uns</h2>', $sauber);
		$this->assertStringContainsString('<p>Wir spielen <strong>dienstags</strong> und <em>freitags</em>.</p>', $sauber);
		$this->assertStringContainsString('<ul><li>Jugend</li><li>Senioren</li></ul>', $sauber);
		$this->assertStringContainsString('<a href="https://verein.example.org/termine" title="Termine">Termine</a>', $sauber);
		$this->assertStringContainsString('<a href="mailto:info@example.org">Mail</a>', $sauber);
		$this->assertStringNotContainsString('onclick', $sauber);
		$this->assertStringNotContainsString('style=', $sauber);
		$this->assertStringNotContainsString('target=', $sauber);
	}

	/**
	 * Skripte, Stile, Rahmen, Bilder und Formulare verschwinden samt Inhalt;
	 * gefährliche Verweise verlieren ihr Ziel; Unbekanntes wird ausgepackt.
	 */
	public function testHtmlGefaehrliches(): void
	{
		$html = '<p>Text<script>alert(1)</script></p><style>p{color:red}</style><iframe src="https://boese.example.org"></iframe>'
			.'<img src="x" onerror="alert(1)"><form action="/x"><input name="a"></form>'
			.'<p><a href="javascript:alert(1)">Klick</a> <a href="data:text/html,x">Daten</a> <a href=" JaVaScRiPt:alert(1)">Trick</a></p>'
			.'<div><span onmouseover="x()">In <font color="red">Kästen</font></span></div><!-- Kommentar -->'
			.'<svg><script>alert(2)</script></svg><table><tr><td>Zelle</td></tr></table>';

		$sauber = Aenderung::bereinigeHtml($html);

		foreach (array('<script', 'alert(', '<style', 'color:red', '<iframe', '<img', 'onerror', '<form', '<input', 'javascript:', 'JaVaScRiPt', 'data:text', 'onmouseover', '<span', '<div', '<font', 'Kommentar', '<svg', '<table', '<td') as $verboten) {
			$this->assertStringNotContainsString($verboten, $sauber, $verboten);
		}

		$this->assertStringContainsString('<p>Text</p>', $sauber);
		$this->assertStringContainsString('<a>Klick</a>', $sauber, 'der Verweis bleibt als Text, ohne Ziel');
		$this->assertStringContainsString('In Kästen', $sauber, 'unbekannte Elemente werden ausgepackt');
		$this->assertStringContainsString('Zelle', $sauber);
	}

	/**
	 * Leeres, Kaputtes und Überlanges.
	 */
	public function testHtmlRandfaelle(): void
	{
		$this->assertSame('', Aenderung::bereinigeHtml(''));
		$this->assertSame('', Aenderung::bereinigeHtml("  \n "));
		$this->assertSame('', Aenderung::bereinigeHtml("\xC3\x28 kaputt"));
		$this->assertSame('Nur Text &amp; Zeichen &lt;3', Aenderung::bereinigeHtml('Nur Text & Zeichen <3'));
		$this->assertSame('<p>Offen<strong>fett</strong></p>', Aenderung::bereinigeHtml('<p>Offen<strong>fett'));
		$this->assertLessThanOrEqual(Aenderung::INFO_LAENGE + 20, strlen(Aenderung::bereinigeHtml('<p>'.str_repeat('a', 40000).'</p>')));
	}

	/**
	 * „Wird nichts verändert, wird auch nichts abgeschickt": Jede einzelne
	 * Änderung genügt; ohne eine einzige ist die Liste leer.
	 */
	public function testVereinsaenderungen(): void
	{
		$k = Aenderung::fuerVereinsdaten('V', '64231', '', 'https://alt.example.org/', '<p>Alt</p>');

		$this->assertSame(array(), Aenderung::vereinsaenderungen($k, 'https://alt.example.org', '<p>Alt</p>', false, false, ''));
		$this->assertSame(array(), Aenderung::vereinsaenderungen($k, 'https://alt.example.org/', '<p>vom Editor umgeschrieben</p>', false, false, ''), 'ohne Bearbeitung zählt umgeschriebenes HTML nicht');
		$this->assertSame(array(), Aenderung::vereinsaenderungen($k, 'https://alt.example.org', "<p>Alt</p>\n", true, false, ''), 'angefasst, aber gleicher Inhalt');

		$this->assertSame(array('logo'), Aenderung::vereinsaenderungen($k, 'https://alt.example.org', '<p>Alt</p>', false, true, ''));
		$this->assertSame(array('homepage'), Aenderung::vereinsaenderungen($k, 'https://neu.example.org', '<p>Alt</p>', false, false, ''));
		$this->assertSame(array('homepage'), Aenderung::vereinsaenderungen($k, '', '<p>Alt</p>', false, false, ''), 'Homepage entfernen ist eine Änderung');
		$this->assertSame(array('info'), Aenderung::vereinsaenderungen($k, 'https://alt.example.org', '<p>Neu</p>', true, false, ''));
		$this->assertSame(array('nachricht'), Aenderung::vereinsaenderungen($k, 'https://alt.example.org', '<p>Alt</p>', false, false, 'Bitte melden'));
		$this->assertSame(array('logo', 'homepage', 'info', 'nachricht'), Aenderung::vereinsaenderungen($k, 'https://neu.example.org', '<p>Neu</p>', true, true, 'x'));
	}

	/**
	 * Die Mail zum Foto nennt Spieler, Seite, Anhang mit Größe und Maßen und
	 * die Nachricht.
	 */
	public function testTextFoto(): void
	{
		$k = Aenderung::fuerFoto('Hendrik Pham', 'NU4481210', 'https://x.test/k');
		$text = Aenderung::textFoto($k, self::bild(), 'Foto-NU4481210.jpg', 'Aktuelles Bild vom Turnier.', 'DSB-Admin');

		$this->assertStringStartsWith("Guten Tag DSB-Admin,\n\n", $text);
		$this->assertStringContainsString('Spieler: Hendrik Pham (NU4481210)', $text);
		$this->assertStringContainsString('Seite:   https://x.test/k', $text);
		$this->assertStringContainsString('Foto:    liegt als Anhang bei (Foto-NU4481210.jpg, 1,2 MB, 800 × 1000 Pixel)', $text);
		$this->assertStringContainsString("Nachricht des Mitglieds:\nAktuelles Bild vom Turnier.", $text);
		$this->assertStringNotContainsString('Nachricht des Mitglieds', Aenderung::textFoto($k, self::bild(), 'x.jpg', '', ''));
		$this->assertStringStartsWith("Guten Tag,\n", Aenderung::textFoto($k, self::bild(), 'x.jpg', '', ''));
	}

	/**
	 * Die Mail zu den Vereinsdaten nennt nur, was sich ändern soll — die
	 * Beschreibung als Quelltext und zum Lesen.
	 */
	public function testTextVereinsdaten(): void
	{
		$k = Aenderung::fuerVereinsdaten('LSV Turm Lippstadt', '64231', 'https://x.test/v', 'https://alt.example.org', '<p>Alt</p>');
		$info = '<p>Wir spielen <strong>dienstags</strong>.</p><ul><li>Jugend</li></ul>';

		$alles = Aenderung::textVereinsdaten($k, array('logo', 'homepage', 'info', 'nachricht'), 'https://neu.example.org', $info, array('groesse' => 204800, 'breite' => 300, 'hoehe' => 300), 'Logo-64231.png', 'Danke!', 'DSB-Admin');

		$this->assertStringContainsString('Verein: LSV Turm Lippstadt (64231)', $alles);
		$this->assertStringContainsString('Logo: liegt als Anhang bei (Logo-64231.png, 200 KB, 300 × 300 Pixel)', $alles);
		$this->assertStringContainsString("Homepage bisher: https://alt.example.org\nHomepage neu:    https://neu.example.org", $alles);
		$this->assertStringContainsString($info, $alles, 'der Quelltext steht unverändert darin');
		$this->assertStringContainsString("Wir spielen dienstags.\nJugend", $alles, 'und darunter lesbar');
		$this->assertStringContainsString("Nachricht des Mitglieds:\nDanke!", $alles);

		$nurHomepage = Aenderung::textVereinsdaten($k, array('homepage'), '', $info, null, '', '', '');
		$this->assertStringContainsString('Homepage neu:    (soll entfallen)', $nurHomepage);
		$this->assertStringNotContainsString('Über den Verein', $nurHomepage);
		$this->assertStringNotContainsString('Logo:', $nurHomepage);
	}

	/**
	 * Dateigrößen in deutscher Schreibweise.
	 */
	public function testGroesse(): void
	{
		$this->assertSame('1 KB', Aenderung::groesse(10));
		$this->assertSame('850 KB', Aenderung::groesse(870400));
		$this->assertSame('1,2 MB', Aenderung::groesse(1258291));
		$this->assertSame('5 MB', Aenderung::groesse(5242880));
	}
}
