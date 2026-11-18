<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Wertungsarchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Gesamtpersonen;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Zugriff;

/**
 * Prüft die Gruppenbildung der Gesamtpersonen (Union-Find) und den Schalter
 * für den Zugriff aufs Archiv.
 */
class GesamtpersonenTest extends TestCase
{
    /**
     * Baut die Gruppenbildung ohne Datenbank — bilden() braucht sie, wurzel()
     * und verbinde() nicht.
     *
     * @return Gesamtpersonen Instanz ohne Verbindung
     */
    private static function gruppen(): Gesamtpersonen
    {
        return (new \ReflectionClass(Gesamtpersonen::class))->newInstanceWithoutConstructor();
    }

    /**
     * Ketten über mehrere Archive landen in einer Gruppe; die Wurzel ist die
     * kleinste ID — sie wird die Nummer der Gesamtperson und bleibt stabil,
     * egal in welcher Reihenfolge die Kanten kommen.
     */
    public function testKetteUndKleinsteId(): void
    {
        $g = self::gruppen();
        $g->verbinde(900, 50);   // N → D
        $g->verbinde(50, 7);     // D → Z
        $g->verbinde(901, 50);   // zweite Portalzeile (W) → dieselbe DeWIS-Person

        foreach (array(900, 50, 7, 901) as $id) {
            $this->assertSame(7, $g->wurzel($id));
        }

        $h = self::gruppen();
        $h->verbinde(901, 50);
        $h->verbinde(50, 7);
        $h->verbinde(900, 50);
        $this->assertSame(7, $h->wurzel(900));
    }

    /**
     * Getrennte Gruppen bleiben getrennt; eine Person ohne Kante ist ihre
     * eigene Wurzel.
     */
    public function testGetrennteGruppen(): void
    {
        $g = self::gruppen();
        $g->verbinde(10, 11);
        $g->verbinde(20, 21);

        $this->assertSame(10, $g->wurzel(11));
        $this->assertSame(20, $g->wurzel(21));
        $this->assertSame(30, $g->wurzel(30));
    }

    /**
     * Nur „1" in tl_member.wertungsarchiv schaltet frei.
     */
    public function testSchalter(): void
    {
        $this->assertTrue(Zugriff::schalter('1'));
        $this->assertTrue(Zugriff::schalter(1));
        $this->assertFalse(Zugriff::schalter(''));
        $this->assertFalse(Zugriff::schalter(null));
        $this->assertFalse(Zugriff::schalter('0'));
    }
}
