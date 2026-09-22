<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client;

/**
 * Prüft die Anmeldung an der Schnittstelle mit zwei Kennungen: eine für
 * Turniere und Personen, eine für die DWZ-Liste (seit 1.46.0).
 *
 * Die Prüfungen mit echtem Abruf laufen gegen einen Nachbau der Schnittstelle
 * (NuSchnittstelleAttrappe.php) auf dem eingebauten PHP-Server — nie gegen nu:
 * Das Tokenkontingent dort ist knapp, und die echten Zugangsdaten gehören in
 * keinen Test. Jede dieser Prüfungen läuft in einem eigenen Prozess: Der
 * Client hält Token und Wartezeiten prozessweit, und die Tokendateien landen
 * unter einem eigenen TL_ROOT.
 */
class OAuth2ClientTest extends TestCase
{
	/**
	 * Zugangsdaten des Nachbaus. Frei erfunden.
	 */
	private const TURNIERE = array('id' => 'turnier-id', 'geheim' => 'turnier-geheim', 'scope' => 'dsb_tournament');
	private const LISTE = array('id' => 'liste-id', 'geheim' => 'liste-geheim');

	/**
	 * @var resource|null Prozess des PHP-Servers
	 */
	private $server;

	/**
	 * @var string Arbeitsverzeichnis des Nachbaus (Konfiguration, Anfragen)
	 */
	private $attrappe = '';

	/**
	 * @var string TL_ROOT dieses Prozesses (Tokendateien, Protokolle)
	 */
	private $wurzel = '';

	/**
	 * Räumt Einstellungen, Server und Arbeitsverzeichnisse wieder ab.
	 */
	protected function tearDown(): void
	{
		unset($GLOBALS['TL_CONFIG']);

		if (\is_resource($this->server)) {
			proc_terminate($this->server);
			proc_close($this->server);
		}

		foreach (array($this->attrappe, $this->wurzel) as $verzeichnis) {
			if ('' !== $verzeichnis && is_dir($verzeichnis)) {
				self::entferne($verzeichnis);
			}
		}
	}

	/**
	 * Welche Kennung zu welcher Adresse gehört — die zentrale Weiche.
	 */
	public function testZugangFuerAdressen(): void
	{
		$GLOBALS['TL_CONFIG'] = self::einstellungen('http://nu.invalid', false);

		$this->assertSame(OAuth2Client::ZUGANG_TURNIERE, OAuth2Client::zugangFuer('http://nu.invalid/rs/dwz/tournaments?searchString=x'));
		$this->assertSame(OAuth2Client::ZUGANG_TURNIERE, OAuth2Client::zugangFuer('/dwz/persons/NU123/history'));

		// Ohne Zugangsdaten der DWZ-Liste: ohne Anmeldung, wie bis 1.45.1
		$this->assertNull(OAuth2Client::zugangFuer('/dwz/dwzliste/persons/NU123'));
		$this->assertNull(OAuth2Client::zugangFuer('/dwz/dwzliste/download/LV-0-dwzliste.zip'));

		// Mit Zugangsdaten: eigener Zugang
		$GLOBALS['TL_CONFIG'] = self::einstellungen('http://nu.invalid', true);
		$this->assertSame(OAuth2Client::ZUGANG_DWZLISTE, OAuth2Client::zugangFuer('/dwz/dwzliste/persons/NU123'));
		$this->assertSame(OAuth2Client::ZUGANG_DWZLISTE, OAuth2Client::zugangFuer('/dwz/dwzliste/clubs'));

		// Kennung ohne Geheimnis zählt nicht als eingetragen
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientSecret'] = '';
		$this->assertNull(OAuth2Client::zugangFuer('/dwz/dwzliste/clubs'));

		// Dieselbe Kennung wie bei den Turnieren: ein gemeinsames Token
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientID'] = ' '.self::TURNIERE['id'].' ';
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientSecret'] = self::TURNIERE['geheim'];
		$this->assertSame(OAuth2Client::ZUGANG_TURNIERE, OAuth2Client::zugangFuer('/dwz/dwzliste/clubs'));

		// Alles andere ohne Anmeldung
		$this->assertNull(OAuth2Client::zugangFuer('/dwz/irgendwas'));
	}

	/**
	 * `eingerichtet()` ohne Angabe prüft wie vor 1.46.0 den Turnierzugang.
	 */
	public function testEingerichtet(): void
	{
		$GLOBALS['TL_CONFIG'] = self::einstellungen('http://nu.invalid', false);

		$this->assertTrue(OAuth2Client::eingerichtet());
		$this->assertTrue(OAuth2Client::eingerichtet(OAuth2Client::ZUGANG_TURNIERE));
		$this->assertFalse(OAuth2Client::eingerichtet(OAuth2Client::ZUGANG_DWZLISTE));

		$GLOBALS['TL_CONFIG'] = self::einstellungen('http://nu.invalid', true);
		$this->assertTrue(OAuth2Client::eingerichtet(OAuth2Client::ZUGANG_DWZLISTE));

		// Die Basisadresse gilt für beide
		$GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'] = '';
		$this->assertFalse(OAuth2Client::eingerichtet());
		$this->assertFalse(OAuth2Client::eingerichtet(OAuth2Client::ZUGANG_DWZLISTE));
	}

	/**
	 * Je Zugang eine eigene Tokendatei; der Turnierzugang behält den alten
	 * Namen, sonst holte jede Anlage nach dem Einspielen eine neue
	 * Token-Familie. (Ohne Contao und ohne TL_ROOT: Systemverzeichnis.)
	 */
	public function testTokendateienGetrennt(): void
	{
		$this->assertSame('oauth2_token_cache.json', basename(OAuth2Client::tokendatei()));
		$this->assertSame('oauth2_token_cache.json', basename(OAuth2Client::tokendatei(OAuth2Client::ZUGANG_TURNIERE)));
		$this->assertSame('oauth2_token_cache_dwzliste.json', basename(OAuth2Client::tokendatei(OAuth2Client::ZUGANG_DWZLISTE)));

		// Ohne Projektverzeichnis kein Protokoll
		$this->assertSame('', OAuth2Client::tokenprotokoll(OAuth2Client::ZUGANG_DWZLISTE));
	}

	/**
	 * Die Download-Adresse folgt der Basisadresse der Einstellungen; ohne sie
	 * bleibt es bei der Produktivschnittstelle wie bis 1.45.1.
	 */
	public function testDownloadAdresse(): void
	{
		$GLOBALS['TL_CONFIG'] = array('wertungsportal_apiBasisURL' => 'https://schachde-appsdemo.liga.nu/dsbwertungsportal/rs/');
		$this->assertSame('https://schachde-appsdemo.liga.nu/dsbwertungsportal/rs/dwz/dwzliste/download/LV-A-dwzliste.zip', OAuth2Client::downloadAdresse('LV-A-dwzliste.zip'));

		$GLOBALS['TL_CONFIG'] = array();
		$this->assertSame('https://schachde-apps.liga.nu/dsbwertungsportal/rs/dwz/dwzliste/download/LV-0-dwzliste.zip', OAuth2Client::downloadAdresse('LV-0-dwzliste.zip'));
	}

	/**
	 * Ohne Zugangsdaten der DWZ-Liste läuft alles wie bisher, solange nu die
	 * Liste frei ausliefert. Schaltet nu die Anmeldung scharf, wird aus dem 401
	 * ein Tokenfehler mit klarer Meldung — der schickt die Besucher in den
	 * Notbetrieb statt auf eine Fehlerseite.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testDwzListeOhneZugangsdaten(): void
	{
		$basis = $this->starte(array('dwzliste_offen' => true), false);
		$client = new OAuth2Client();

		$antwort = $client->callApiWithRefresh($basis.'/dwz/dwzliste/persons?lastname=Muster');
		$this->assertSame(200, $antwort['http_code']);
		$this->assertSame(array(), $this->tokenanfragen(), 'ohne Zugangsdaten keine Tokenanfrage');
		$this->assertSame(array(''), $this->benutzteToken('/rs/dwz/dwzliste'));

		// nu schaltet die Anmeldung scharf
		$this->konfig(array('dwzliste_offen' => false));

		$antwort = $client->callApiWithRefresh($basis.'/dwz/dwzliste/persons?lastname=Muster');
		$this->assertTrue($antwort['error']);
		$this->assertTrue($antwort['tokenfehler']);
		$this->assertSame(401, $antwort['http_code']);
		$this->assertStringContainsString('keine Zugangsdaten eingetragen', $antwort['error_message']);
	}

	/**
	 * Mit eigener Kennung: eigenes Token, eigene Tokendatei, eigenes
	 * Protokoll — und der Turnierzugang bleibt unberührt.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testDwzListeMitEigenerKennung(): void
	{
		$basis = $this->starte(array('dwzliste_offen' => false), true);
		$client = new OAuth2Client();

		$antwort = $client->callApiWithRefresh($basis.'/dwz/dwzliste/persons/NU1');
		$this->assertSame(200, $antwort['http_code']);
		$this->assertArrayNotHasKey('ohne_anmeldung', $antwort);
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs?vkz=10614')['http_code']);
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/tournaments?searchString=x')['http_code']);

		// Je Kennung genau EINE Tokenanfrage: das Token der DWZ-Liste wird
		// wiederverwendet, das der Turniere eigens geholt
		$anfragen = $this->tokenanfragen();
		$this->assertCount(2, $anfragen);
		$this->assertSame(array('client_credentials', self::LISTE['id'], null), array($anfragen[0]['grant'], $anfragen[0]['client'], $anfragen[0]['scope']), 'ohne eingetragenen Scope wird keiner angefordert');
		$this->assertSame(array('client_credentials', self::TURNIERE['id'], self::TURNIERE['scope']), array($anfragen[1]['grant'], $anfragen[1]['client'], $anfragen[1]['scope']));

		// Jede Adresse mit dem Token ihrer Kennung
		foreach ($this->benutzteToken('/rs/dwz/dwzliste') as $token) {
			$this->assertStringStartsWith(self::LISTE['id'].'-zugang-', $token);
		}
		$this->assertStringStartsWith(self::TURNIERE['id'].'-zugang-', $this->benutzteToken('/rs/dwz/tournaments')[0]);

		// Getrennte Tokendateien und Protokolle unter TL_ROOT
		$liste = json_decode((string) file_get_contents($this->wurzel.'/system/tmp/wertungsportal-token-dwzliste.json'), true);
		$turniere = json_decode((string) file_get_contents($this->wurzel.'/system/tmp/wertungsportal-token.json'), true);
		$this->assertStringStartsWith(self::LISTE['id'].'-zugang-', $liste['access_token']);
		$this->assertStringStartsWith(self::TURNIERE['id'].'-zugang-', $turniere['access_token']);

		$monat = date('Y-m');
		$this->assertCount(2, file($this->wurzel.'/var/logs/wertungsportal-token-dwzliste-'.$monat.'.log', FILE_IGNORE_NEW_LINES), 'Kopfzeile und eine Anfrage');
		$this->assertCount(2, file($this->wurzel.'/var/logs/wertungsportal-token-'.$monat.'.log', FILE_IGNORE_NEW_LINES));
	}

	/**
	 * Dieselbe Kennung für beides ergibt EIN Token — zwei Token-Familien
	 * derselben Kennung belasteten das Kontingent doppelt.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testGleicheKennungEinToken(): void
	{
		$basis = $this->starte(array('dwzliste_offen' => false, 'zugelassen' => array('dwzliste' => array(self::TURNIERE['id']), 'turniere' => array(self::TURNIERE['id']))), true);
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientID'] = self::TURNIERE['id'];
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientSecret'] = self::TURNIERE['geheim'];

		$client = new OAuth2Client();
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs')['http_code']);
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/tournaments?searchString=x')['http_code']);

		$this->assertCount(1, $this->tokenanfragen());
		$this->assertFileDoesNotExist($this->wurzel.'/system/tmp/wertungsportal-token-dwzliste.json');
	}

	/**
	 * Falsche Zugangsdaten der DWZ-Liste: Die Wartezeit trifft nur diese
	 * Kennung. Solange nu die Liste frei ausliefert, merkt kein Besucher etwas
	 * (Abruf ohne Token); danach wird daraus ein Tokenfehler mit dem Grund.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFalscheZugangsdatenImUebergang(): void
	{
		$basis = $this->starte(array('dwzliste_offen' => true), true);
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientSecret'] = 'falsch';

		$client = new OAuth2Client();
		$antwort = $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs');
		$this->assertSame(200, $antwort['http_code'], 'Übergang: ohne Token');
		$this->assertStringContainsString('Bad client credentials', $antwort['ohne_anmeldung'], 'vermerkt, damit die Prüfwerkzeuge den Fehler sehen');
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/tournaments?searchString=x')['http_code'], 'Turnierzugang nicht mitgesperrt');
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs')['http_code']);

		// Eine abgelehnte Anfrage für die DWZ-Liste, dann Wartezeit — keine zweite
		$liste = array_values(array_filter($this->tokenanfragen(), static function (array $a): bool { return self::LISTE['id'] === $a['client']; }));
		$this->assertCount(1, $liste);

		$datei = json_decode((string) file_get_contents($this->wurzel.'/system/tmp/wertungsportal-token-dwzliste.json'), true);
		$this->assertGreaterThan(time(), $datei['gesperrt_bis']);
		$this->assertStringContainsString('Token-Anfrage für die DWZ-Liste fehlgeschlagen (HTTP 401)', $datei['sperrgrund']);

		// nu schaltet scharf
		$this->konfig(array('dwzliste_offen' => false));
		$antwort = $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs');
		$this->assertTrue($antwort['tokenfehler']);
		$this->assertSame(401, $antwort['http_code']);
		$this->assertStringContainsString('Bad client credentials', $antwort['error_message']);
	}

	/**
	 * Weist nu ein noch gültiges Token ab, wird es über das Refresh-Token
	 * erneuert — beim Zugang, dem es gehört.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testAbgewiesenesTokenWirdErneuert(): void
	{
		$basis = $this->starte(array('dwzliste_offen' => false), true);

		$client = new OAuth2Client();
		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs')['http_code']);

		$erstes = $this->benutzteToken('/rs/dwz/dwzliste')[0];
		$this->konfig(array('dwzliste_offen' => false, 'widerrufen' => array($erstes)));

		$this->assertSame(200, $client->callApiWithRefresh($basis.'/dwz/dwzliste/clubs')['http_code']);

		$anfragen = $this->tokenanfragen();
		$this->assertSame(array('client_credentials', 'refresh_token'), array_column($anfragen, 'grant'));
		$this->assertSame(array(self::LISTE['id'], self::LISTE['id']), array_column($anfragen, 'client'));
		$this->assertFileDoesNotExist($this->wurzel.'/system/tmp/wertungsportal-token.json', 'der Turnierzugang wurde nicht angefasst');
	}

	/**
	 * Die Zip-Downloads der DWZ-Liste mit Token; ohne Zugangsdaten eine klare
	 * Meldung und kein zweiter Versuch.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testHerunterladen(): void
	{
		$this->starte(array('dwzliste_offen' => false), true);
		$ziel = $this->wurzel.'/LV-0.zip';

		$ergebnis = OAuth2Client::herunterladen(OAuth2Client::downloadAdresse('LV-0-dwzliste.zip'), $ziel);
		$this->assertTrue($ergebnis['success'], $ergebnis['error']);
		$this->assertSame(1, $ergebnis['versuche']);
		$this->assertStringStartsWith(self::LISTE['id'].'-zugang-', $this->benutzteToken('/rs/dwz/dwzliste/download/LV-0-dwzliste.zip')[0]);

		$zip = new \ZipArchive();
		$this->assertTrue($zip->open($ziel, \ZipArchive::CHECKCONS));
		$this->assertSame('spieler.csv', $zip->getNameIndex(0));
		$zip->close();

		// Ohne Zugangsdaten: 401 und Schluss
		$GLOBALS['TL_CONFIG']['wertungsportal_dwzliste_clientID'] = '';
		$ergebnis = OAuth2Client::herunterladen(OAuth2Client::downloadAdresse('LV-1-dwzliste.zip'), $this->wurzel.'/LV-1.zip');
		$this->assertFalse($ergebnis['success']);
		$this->assertSame(1, $ergebnis['versuche']);
		$this->assertStringContainsString('HTTP-Status 401', $ergebnis['error']);
		$this->assertStringContainsString('keine Zugangsdaten eingetragen', $ergebnis['error']);
		$this->assertFileDoesNotExist($this->wurzel.'/LV-1.zip');
	}

	/**
	 * Legt TL_ROOT und das Verzeichnis des Nachbaus an, startet den Server und
	 * setzt die Einstellungen.
	 *
	 * @param array $konfig        Steuerung des Nachbaus (siehe dort)
	 * @param bool  $mitDwzListe   Zugangsdaten der DWZ-Liste eintragen
	 *
	 * @return string Basisadresse der Schnittstelle
	 */
	private function starte(array $konfig, bool $mitDwzListe): string
	{
		$this->wurzel = self::tempverzeichnis('wp-wurzel');
		$this->attrappe = self::tempverzeichnis('wp-attrappe');

		\define('TL_ROOT', $this->wurzel);

		$this->konfig($konfig);

		$port = self::freierPort();
		$this->server = proc_open(
			array(PHP_BINARY, '-S', '127.0.0.1:'.$port, __DIR__.'/NuSchnittstelleAttrappe.php'),
			array(0 => array('pipe', 'r'), 1 => array('file', $this->attrappe.'/server.log', 'a'), 2 => array('file', $this->attrappe.'/server.log', 'a')),
			$rohre,
			null,
			array('NU_ATTRAPPE' => $this->attrappe) + getenv()
		);

		$this->assertIsResource($this->server, 'PHP-Server nicht gestartet');

		// Warten, bis der Server Verbindungen annimmt
		for ($i = 0; $i < 50; ++$i) {
			$verbindung = @fsockopen('127.0.0.1', $port, $nr, $text, 0.2);

			if (false !== $verbindung) {
				fclose($verbindung);
				break;
			}

			usleep(100000);
		}

		$GLOBALS['TL_CONFIG'] = self::einstellungen('http://127.0.0.1:'.$port, $mitDwzListe);

		return 'http://127.0.0.1:'.$port.'/rs';
	}

	/**
	 * Schreibt die Steuerung des Nachbaus. Ohne Angabe sind je Bereich die
	 * passenden Kennungen zugelassen.
	 *
	 * @param array $konfig Teilangaben, siehe NuSchnittstelleAttrappe.php
	 */
	private function konfig(array $konfig): void
	{
		$konfig += array(
			'kennungen' => array(
				self::TURNIERE['id'] => array('geheim' => self::TURNIERE['geheim'], 'scope' => self::TURNIERE['scope']),
				self::LISTE['id']    => array('geheim' => self::LISTE['geheim']),
			),
			'zugelassen' => array('dwzliste' => array(self::LISTE['id']), 'turniere' => array(self::TURNIERE['id'])),
		);

		file_put_contents($this->attrappe.'/konfig.json', json_encode($konfig));
	}

	/**
	 * Einstellungen wie in der localconfig.php.
	 *
	 * @param string $server      Adresse des Servers ohne Pfad
	 * @param bool   $mitDwzListe Zugangsdaten der DWZ-Liste eintragen
	 *
	 * @return array Inhalt für $GLOBALS['TL_CONFIG']
	 */
	private static function einstellungen(string $server, bool $mitDwzListe): array
	{
		return array(
			'wertungsportal_apiBasisURL'           => $server.'/rs',
			'wertungsportal_tokenURL'              => $server.'/auth/token',
			'wertungsportal_clientID'              => self::TURNIERE['id'],
			'wertungsportal_clientSecret'          => self::TURNIERE['geheim'],
			'wertungsportal_scopeListe'            => self::TURNIERE['scope'],
			'wertungsportal_dwzliste_clientID'     => $mitDwzListe ? self::LISTE['id'] : '',
			'wertungsportal_dwzliste_clientSecret' => $mitDwzListe ? self::LISTE['geheim'] : '',
			'wertungsportal_api_timeout'           => '5',
		);
	}

	/**
	 * Tokenanfragen, die beim Nachbau ankamen.
	 *
	 * @return array[] je Anfrage grant, client, scope (null = nicht mitgeschickt)
	 */
	private function tokenanfragen(): array
	{
		return array_values(array_filter($this->anfragen(), static function (array $a): bool { return 'token' === $a['art']; }));
	}

	/**
	 * Mitgeschickte Token der Abrufe, deren Pfad so beginnt.
	 *
	 * @param string $pfad Anfang des Pfads, etwa `/rs/dwz/dwzliste`
	 *
	 * @return string[] Token je Abruf, '' = ohne Anmeldung
	 */
	private function benutzteToken(string $pfad): array
	{
		$token = array();

		foreach ($this->anfragen() as $a) {
			if ('abruf' === $a['art'] && 0 === strpos($a['pfad'], $pfad)) {
				$token[] = $a['token'];
			}
		}

		return $token;
	}

	/**
	 * Alle Anfragen beim Nachbau, in der Reihenfolge ihres Eingangs.
	 *
	 * @return array[]
	 */
	private function anfragen(): array
	{
		$datei = $this->attrappe.'/anfragen.jsonl';

		if (!is_file($datei)) {
			return array();
		}

		return array_map(static function (string $zeile): array { return (array) json_decode($zeile, true); }, file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
	}

	/**
	 * Liefert einen freien Port auf der Schleifenschnittstelle.
	 */
	private static function freierPort(): int
	{
		$sockel = stream_socket_server('tcp://127.0.0.1:0', $nr, $text);
		$name = stream_socket_get_name($sockel, false);
		fclose($sockel);

		return (int) substr((string) strrchr((string) $name, ':'), 1);
	}

	/**
	 * Legt ein leeres Verzeichnis im Systemtemp an.
	 */
	private static function tempverzeichnis(string $praefix): string
	{
		$verzeichnis = sys_get_temp_dir().'/'.$praefix.'-'.bin2hex(random_bytes(4));
		mkdir($verzeichnis, 0777, true);

		return $verzeichnis;
	}

	/**
	 * Löscht ein Verzeichnis samt Inhalt.
	 */
	private static function entferne(string $verzeichnis): void
	{
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($verzeichnis, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $eintrag) {
			$eintrag->isDir() ? @rmdir($eintrag->getPathname()) : @unlink($eintrag->getPathname());
		}

		@rmdir($verzeichnis);
	}
}
