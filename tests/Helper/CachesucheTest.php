<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Cachesuche;

/**
 * Hält fest, welche Schnittstellenfunktionen das gezielte Leeren des
 * Zwischenspeichers je Suchart erfaßt.
 *
 * Die Zuordnung ist ein Versprechen an den, der das Backend-Modul bedient:
 * Was hier nicht steht, bleibt nach „Gefundene Einträge löschen" im
 * Zwischenspeicher liegen — bei der Liste aller Verbände bis zu einer Woche.
 */
class CachesucheTest extends TestCase
{
	/**
	 * Unter „Verein" liegen Mitgliederliste und Name — und seit 1.54.1 die
	 * Liste aller Vereine und Verbände, die unter dem Schlüssel 00000 steht.
	 * Ohne sie ließ sich eine Änderung an den Verbänden nur sichtbar machen,
	 * indem man den ganzen Zwischenspeicher leerte.
	 */
	public function testVereinErfasstDieVerbandsliste(): void
	{
		$this->assertSame(
			array(
				'Vereinsliste' => array('typ' => 'exakt'),
				'Vereinsname' => array('typ' => 'exakt'),
				'Verbaende' => array('typ' => 'exakt'),
			),
			Cachesuche::regeln('verein')
		);
	}

	/**
	 * Die Sucharten werden ohne Rücksicht auf Schreibweise und Leerraum
	 * erkannt; eine unbekannte Art erfaßt nichts.
	 */
	public function testSuchartenUndUnbekanntes(): void
	{
		$this->assertSame(Cachesuche::regeln('verein'), Cachesuche::regeln(' Verein '));
		$this->assertSame(array('Turnierinfo', 'Turnierauswertung', 'Turnierergebnisse', 'Spielberichtsbogen'), array_keys(Cachesuche::regeln('turnier')));
		$this->assertSame(array('Karteikarte', 'Karteikarte_Turniere', 'Spielberichtsbogen'), array_keys(Cachesuche::regeln('spieler')));
		$this->assertSame(array(), Cachesuche::regeln('verband'));
	}
}
