<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Wertungsarchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Ergebnistabelle;

/**
 * Prüft Kreuztabelle und Rundenansicht der Archivturniere.
 */
class ErgebnistabelleTest extends TestCase
{
    /**
     * Partien eines Rundenturniers mit n Spielern: jeder gegen jeden, je
     * Partie zwei Zeilen (aus Sicht beider Spieler), Spieler 1 gewinnt alles.
     *
     * @param int $n Spielerzahl
     *
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    private static function rundenturnier(int $n): array
    {
        $teilnehmer = array();
        $partien = array();

        for ($i = 1; $i <= $n; ++$i) {
            // Platz absichtlich umgekehrt zur Nummer
            $teilnehmer[] = array('nr' => $i, 'name' => 'S'.$i, 'platz' => $n + 1 - $i);
        }

        $runde = 0;
        for ($a = 1; $a <= $n; ++$a) {
            for ($b = $a + 1; $b <= $n; ++$b) {
                ++$runde;
                $partien[] = array('runde' => $runde, 'spieler' => $a, 'gegner' => $b, 'ergebnis' => '1');
                $partien[] = array('runde' => $runde, 'spieler' => $b, 'gegner' => $a, 'ergebnis' => '0');
            }
        }

        return array($teilnehmer, $partien);
    }

    /**
     * Jeder gegen jeden → Kreuztabelle; Reihenfolge nach Platz, Spalten mit
     * denselben Positionen; Ergebnisse unter der Position des Gegners.
     */
    public function testKreuztabelle(): void
    {
        [$t, $p] = self::rundenturnier(4);
        $e = Ergebnistabelle::bauen($t, $p);

        $this->assertTrue($e['kreuz']);
        $this->assertSame(array(4, 3, 2, 1), array_column($e['spieler'], 'nr'));

        // Spieler 1 steht auf Platz 4 (Position 4) und hat gegen alle gewonnen
        $s1 = $e['spieler'][3];
        $this->assertSame(4, $s1['pos']);
        // Die Vorlage liest über die Position; die Reihenfolge der Schlüssel folgt den Runden
        ksort($s1['kreuz']);
        $sieg = array(array('ergebnis' => '1', 'farbe' => null));
        $this->assertSame(array(1 => $sieg, 2 => $sieg, 3 => $sieg), $s1['kreuz']);
        // Ohne Farbangaben (wie ELOBASE) keine Farben
        $this->assertFalse($e['farben']);
    }

    /**
     * Schweizer System (nicht jeder gegen jeden) → Rundenansicht; die
     * Rundenzahl kommt aus den Partien, nicht aus dem Kopf.
     */
    public function testRundenansicht(): void
    {
        $t = array();
        for ($i = 1; $i <= 6; ++$i) {
            $t[] = array('nr' => $i, 'name' => 'S'.$i, 'platz' => $i);
        }
        $p = array(
            array('runde' => 1, 'spieler' => 1, 'gegner' => 4, 'ergebnis' => '1'),
            array('runde' => 1, 'spieler' => 4, 'gegner' => 1, 'ergebnis' => '0'),
            array('runde' => 2, 'spieler' => 1, 'gegner' => 2, 'ergebnis' => '½'),
            array('runde' => 2, 'spieler' => 2, 'gegner' => 1, 'ergebnis' => '½'),
            array('runde' => 3, 'spieler' => 3, 'gegner' => null, 'ergebnis' => '+'),
        );

        $e = Ergebnistabelle::bauen($t, $p);

        $this->assertFalse($e['kreuz']);
        $this->assertSame(3, $e['runden']);
        $this->assertSame(array(array('gegner' => 4, 'ergebnis' => '1', 'farbe' => null, 'gegner_pos' => 4, 'gegner_name' => 'S4')), $e['spieler'][0]['runden'][1]);
        $this->assertNull($e['spieler'][2]['runden'][3][0]['gegner_pos']);
    }

    /**
     * Farbe (R3): w/s erscheinen in Runden und Kreuz, unbekannte Werte werden
     * null; farben meldet, ob das Turnier überhaupt Farben führt.
     */
    public function testFarben(): void
    {
        $t = array(array('nr' => 1, 'name' => 'A', 'platz' => 1), array('nr' => 2, 'name' => 'B', 'platz' => 2), array('nr' => 3, 'name' => 'C', 'platz' => 3));
        $p = array(
            array('runde' => 1, 'spieler' => 1, 'gegner' => 2, 'ergebnis' => '1', 'farbe' => 'w'),
            array('runde' => 1, 'spieler' => 2, 'gegner' => 1, 'ergebnis' => '0', 'farbe' => 's'),
            array('runde' => 2, 'spieler' => 3, 'gegner' => null, 'ergebnis' => '+', 'farbe' => null),
            array('runde' => 2, 'spieler' => 1, 'gegner' => 3, 'ergebnis' => '1', 'farbe' => 'x'),
        );

        $e = Ergebnistabelle::bauen($t, $p);

        $this->assertTrue($e['farben']);
        $this->assertSame('w', $e['spieler'][0]['runden'][1][0]['farbe']);
        $this->assertSame('s', $e['spieler'][1]['runden'][1][0]['farbe']);
        $this->assertNull($e['spieler'][2]['runden'][2][0]['farbe']);
        $this->assertNull($e['spieler'][0]['runden'][2][0]['farbe']);
        $this->assertSame(array(array('ergebnis' => '1', 'farbe' => 'w')), $e['spieler'][0]['kreuz'][2]);
    }

    /**
     * Ein, zwei fehlende Paare kippen die Kreuztabelle nicht; mehr als 30
     * Spieler werden nie eine.
     */
    public function testGrenzen(): void
    {
        [$t, $p] = self::rundenturnier(10);
        $this->assertTrue(Ergebnistabelle::bauen($t, array_slice($p, 2))['kreuz']);

        [$t, $p] = self::rundenturnier(31);
        $this->assertFalse(Ergebnistabelle::bauen($t, $p)['kreuz']);
    }
}
