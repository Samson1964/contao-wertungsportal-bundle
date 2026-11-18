<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Wertungsarchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Importeur;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Liste;

/**
 * Prüft Listenleser, Feldzuordnung und Kanten des Wertungsarchivs (2.0.0)
 * mit Zeilen im Format der Übergabe (wertungsarchiv-uebergabe/1).
 */
class ImporteurTest extends TestCase
{
    /**
     * Schreibt eine Liste in eine temporäre Datei.
     *
     * @param array<int,string>            $felder Feldnamen der Kopfzeile
     * @param array<int,array<int,mixed>>  $zeilen Datenzeilen
     *
     * @return string Pfad der Datei
     */
    private static function liste(array $felder, array $zeilen): string
    {
        $text = json_encode(array('format' => Liste::FORMAT, 'archiv' => 'wp', 'inhalt' => 'test', 'felder' => $felder))."\n";

        foreach ($zeilen as $z) {
            $text .= json_encode($z, JSON_UNESCAPED_UNICODE)."\n";
        }

        $pfad = tempnam(sys_get_temp_dir(), 'archiv');
        file_put_contents($pfad, $text);

        return $pfad;
    }

    /**
     * Felder werden über ihren Namen gelesen; eine ergänzte Spalte an
     * beliebiger Stelle stört nicht.
     */
    public function testZeilenNachNamen(): void
    {
        $pfad = self::liste(array('person', 'erg_neu', 'name'), array(array('N:NU1', 7, 'Müller, Hans')));
        $zeilen = iterator_to_array(Liste::zeilen($pfad));
        unlink($pfad);

        $this->assertSame(array(2 => array('person' => 'N:NU1', 'erg_neu' => 7, 'name' => 'Müller, Hans')), $zeilen);
    }

    /**
     * Eine Zeile mit falscher Feldzahl bricht mit Zeilennummer ab, statt
     * Werte in falsche Spalten zu schieben.
     */
    public function testFalscheFeldzahl(): void
    {
        $pfad = self::liste(array('a', 'b'), array(array(1, 2), array(1)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Zeile 3/');

        try {
            iterator_to_array(Liste::zeilen($pfad));
        } finally {
            unlink($pfad);
        }
    }

    /**
     * Nichts geht verloren: Was keine Spalte hat, steht unverändert in
     * extra; null bleibt dort weg. Kennzeichen werden Text, Wahrheitswerte
     * 0/1, Zeitpunkte bei „ende" das Datum.
     */
    public function testSpaltenUndExtra(): void
    {
        $w = Importeur::spalten(
            array('quell_id' => 'turnier', 'kennzeichen' => 'kennzeichen', 'rekonstruiert' => 'rekonstruiert', 'ende' => 'ende'),
            array('turnier' => 2263372, 'kennzeichen' => array('auch_in_dewis', 'warnung'), 'rekonstruiert' => true, 'ende' => '2026-06-14 10:00:00', 'ort' => 'Lauffen', 'url' => null)
        );

        $this->assertSame(2263372, $w['quell_id']);
        $this->assertSame('auch_in_dewis,warnung', $w['kennzeichen']);
        $this->assertSame(1, $w['rekonstruiert']);
        $this->assertSame('2026-06-14', $w['ende']);
        $this->assertSame('{"ort":"Lauffen"}', $w['extra']);
    }

    /**
     * Kanten zeigen vom jüngeren aufs ältere Archiv. Die PKZ-Brücke steht in
     * ELOBASE (als Zahl) und DeWIS (als Kennung) — beide ergeben dieselbe
     * Kante, damit sie nur einmal entsteht.
     */
    public function testKanten(): void
    {
        $this->assertSame(
            array(array('von' => 'D:10162604', 'nach' => 'Z:1870081', 'quelle' => 'pkz', 'gewicht' => null)),
            Importeur::kanten('elobase', array('person' => 'Z:1870081', 'dewis_id' => 10162604))
        );
        $this->assertSame(
            array(array('von' => 'D:10162604', 'nach' => 'Z:1870081', 'quelle' => 'pkz', 'gewicht' => null)),
            Importeur::kanten('dewis', array('person' => 'D:10162604', 'erg_elobase_person' => 'Z:1870081'))
        );

        $wp = Importeur::kanten('wp', array('person' => 'N:NU4005028', 'erg_dewis_person' => 'D:10000004', 'erg_dewis_quelle' => 'turniere', 'erg_dewis_belege' => 13, 'erg_dewis_dublette' => '10000003'));
        $this->assertSame(array('von' => 'N:NU4005028', 'nach' => 'D:10000004', 'quelle' => 'turniere', 'gewicht' => 13), $wp[0]);
        $this->assertSame(array('von' => 'N:NU4005028', 'nach' => 'D:10000003', 'quelle' => 'dublette', 'gewicht' => null), $wp[1]);

        $this->assertSame(array(), Importeur::kanten('wp', array('person' => 'W:1-2', 'erg_dewis_person' => null)));
    }

    /**
     * Hashnamen und Namen ohne Buchstaben sind keine Klarnamen — sie dürfen
     * einen vorhandenen Namen beim monatlichen Abzug nicht überschreiben.
     */
    public function testKlarname(): void
    {
        $this->assertSame('Aanstoot, Gerd', Importeur::klarname('Aanstoot, Gerd'));
        $this->assertSame('Ли, Ан', Importeur::klarname('Ли, Ан'));
        $this->assertNull(Importeur::klarname('0280F8ACBD6337FC, 3F54FDEBA5269772'));
        $this->assertNull(Importeur::klarname('0280F8ACBD6337FC,3F54FDEBA5269772'));
        $this->assertNull(Importeur::klarname(','));
        $this->assertNull(Importeur::klarname(''));
        $this->assertNull(Importeur::klarname(null));
    }

    /**
     * Nur Kanten, deren Ausgangsperson zum Archiv gehört, kann ein neuer
     * Abzug für entfallen erklären; ELOBASE führt keine eigenen.
     */
    public function testEigeneKanten(): void
    {
        $this->assertSame(array('N:', 'W:'), Importeur::EIGENE_KANTEN['wp']);
        $this->assertSame(array(), Importeur::EIGENE_KANTEN['elobase']);

        foreach (Importeur::kanten('wp', array('person' => 'W:1-2', 'erg_dewis_person' => 'D:5', 'erg_dewis_dublette' => 'D:6')) as $k) {
            $this->assertSame('W:', substr($k['von'], 0, 2));
        }
    }
}
