<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Wertungsarchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Ansicht;

/**
 * Prüft, wie die Backend-Ansicht einen Suchbegriff deutet.
 */
class AnsichtTest extends TestCase
{
    /**
     * Nummer, Kennung, NU-Nummer und Name werden auseinandergehalten; ein
     * Name wird mit und ohne Leerzeichen nach dem Komma gesucht, weil ELOBASE
     * „Mueller,Klaus" schreibt und das Portal „Müller, Klaus".
     */
    public function testDeute(): void
    {
        $this->assertSame(array('art' => 'gesamt', 'werte' => array('123')), Ansicht::deute('#123'));
        $this->assertSame(array('art' => 'gesamt', 'werte' => array('64970')), Ansicht::deute(' 64970 '));
        $this->assertSame(array('art' => 'kennung', 'werte' => array('Z:1870081')), Ansicht::deute('z:1870081'));
        $this->assertSame(array('art' => 'kennung', 'werte' => array('W:2760462-36')), Ansicht::deute('W:2760462-36'));
        $this->assertSame(array('art' => 'nu', 'werte' => array('NU4005028')), Ansicht::deute('nu4005028'));
        $this->assertSame(array('art' => 'name', 'werte' => array('Müller, Klaus', 'Müller,Klaus')), Ansicht::deute('Müller ,Klaus'));
        $this->assertSame(array('art' => 'name', 'werte' => array('Aanstoot')), Ansicht::deute('Aanstoot'));
        $this->assertSame(array('art' => 'leer', 'werte' => array()), Ansicht::deute('  '));
    }
}
