<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Classes\Systemmeldungen;

/**
 * Prüft den Hinweis zum Spieler-Import auf der Backend-Startseite: Frist
 * (30 Tage, bis 1.53.0 erst ab dem 32.) und Wortlaut („Spieler" statt
 * „Personen").
 */
class SystemmeldungenTest extends TestCase
{
	/**
	 * Bis einschließlich 29 Tagen bleibt es still, ab 30 Tagen erscheint der
	 * Hinweis mit der Zahl der Tage.
	 */
	public function testFristVon30Tagen(): void
	{
		$jetzt = 1790000000;

		$this->assertSame('', Systemmeldungen::meldung($jetzt - 29 * 86400, $jetzt));
		$this->assertSame('', Systemmeldungen::meldung($jetzt - 30 * 86400 + 1, $jetzt));
		$this->assertStringContainsString('liegt 30 Tage zurück', Systemmeldungen::meldung($jetzt - 30 * 86400, $jetzt));
		$this->assertStringContainsString('liegt 45 Tage zurück', Systemmeldungen::meldung($jetzt - 45 * 86400, $jetzt));
	}

	/**
	 * Der Hinweis spricht von Spielern und nennt den Menüpunkt, wie er im
	 * Backend heißt; das Wort „Personen" kommt nicht mehr vor.
	 */
	public function testWortlaut(): void
	{
		$jetzt = 1790000000;

		foreach (array(Systemmeldungen::meldung(0, $jetzt), Systemmeldungen::meldung($jetzt - 40 * 86400, $jetzt)) as $meldung) {
			$this->assertStringContainsString('Spieler-Import', $meldung);
			$this->assertStringContainsString('Wertungsportal &rarr; Spieler &rarr; CSV-Import', $meldung);
			$this->assertStringContainsString('Vereinsmitglieder, Abgemeldete im Zeitraum, Angemeldete im Zeitraum', $meldung);
			$this->assertStringNotContainsString('Personen', $meldung);
		}

		$this->assertStringContainsString('noch kein Spieler-Import', Systemmeldungen::meldung(0, $jetzt));
	}
}
