<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes;

/**
 * Prüft das Einlesen des DeWIS-Exports (tcode_uui.csv) und den Bau der
 * neuen Adressen. Die Zeilen sind dem Export nachgebildet.
 */
class AltcodesTest extends TestCase
{
    /**
     * Kopfzeile des Exports aus portal64.
     */
    private const KOPF = array('ID', 'tname', 'tcode', 'organisation', 'vkz', 'acron', 'finishedOn', 'uuid', 'computedOn', 'recomputedOn', 'rounds');

    /**
     * Schreibt eine Exportdatei in einen temporären Pfad.
     *
     * @param array<int,array<int,string>> $zeilen Datenzeilen
     * @param bool                         $latin  true: in latin-1 schreiben wie der Export
     *
     * @return string Pfad der Datei
     */
    private static function datei(array $zeilen, bool $latin): string
    {
        $text = '';

        foreach (array_merge(array(self::KOPF), $zeilen) as $z) {
            $text .= implode(';', array_map(static function ($w) { return '"'.str_replace('"', '""', $w).'"'; }, $z))."\r\n";
        }

        $pfad = tempnam(sys_get_temp_dir(), 'altcodes');
        file_put_contents($pfad, $latin ? mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8') : $text);

        return $pfad;
    }

    /**
     * Der Export ist latin-1: Umlaute in Code und Namen kommen als UTF-8 an,
     * leere Daten bleiben leer, die Runden werden Zahl.
     */
    public function testLatin1MitUmlauten(): void
    {
        $pfad = self::datei(array(
            array('1', 'NSV-Dähnepokal 2014', 'B437-700-DÄH', 'Niedersächsischer Schachverband e. V.', '700', 'DÄH', '2014-09-14', '019f75b9-aed9-72ec-951f-96489dc02c42', '2014-10-15', '', '7'),
        ), true);

        $zeilen = iterator_to_array(Altcodes::leseDatei($pfad), false);
        unlink($pfad);

        $this->assertCount(1, $zeilen);
        $this->assertSame('B437-700-DÄH', $zeilen[0]['tcode']);
        $this->assertSame('NSV-Dähnepokal 2014', $zeilen[0]['tname']);
        $this->assertSame('', $zeilen[0]['recomputedOn']);
        $this->assertSame(7, $zeilen[0]['rounds']);
    }

    /**
     * Eine UTF-8-Datei (Andreas Filmann bot andere Zeichensätze an) wird
     * nicht ein zweites Mal umgewandelt.
     */
    public function testUtf8BleibtUtf8(): void
    {
        $pfad = self::datei(array(
            array('1', 'NSV-Dähnepokal 2014', 'B437-700-DÄH', 'NSV', '700', 'DÄH', '2014-09-14', '019f75b9-aed9-72ec-951f-96489dc02c42', '2014-10-15', '', '7'),
        ), false);

        $zeilen = iterator_to_array(Altcodes::leseDatei($pfad), false);
        unlink($pfad);

        $this->assertSame('B437-700-DÄH', $zeilen[0]['tcode']);
    }

    /**
     * Ohne gültige UUID gibt es keine Zuordnung; \N und kaputte Daten werden
     * leer, damit kein Unsinn als „Erste Berechnung" erscheint.
     */
    public function testUngueltigeWerte(): void
    {
        $this->assertNull(Altcodes::zeile(self::KOPF, array('1', 'X', 'B101-000-AV4', '', '000', 'AV4', '2011-01-09', 'keine-uuid', '', '', '5')));

        $z = Altcodes::zeile(self::KOPF, array('1', 'X', 'B101-000-AV4', '', '000', 'AV4', '\N', '019F75B9-AEC1-7C04-8974-0354B1D0C641', '27.07.2013', '\N', ''));
        $this->assertSame('019f75b9-aec1-7c04-8974-0354b1d0c641', $z['uuid']);
        $this->assertSame('', $z['finishedOn']);
        $this->assertSame('', $z['computedOn']);
        $this->assertSame('', $z['recomputedOn']);
        $this->assertSame(0, $z['rounds']);
    }

    /**
     * Alte Ergebnisseite → neue Ergebnisseite; alles andere (auch der alte
     * Spielberichtsbogen) → Auswertung.
     */
    public function testZiel(): void
    {
        $uuid = '019f75b9-af1a-7c34-ae8f-b3f278013b38';

        $this->assertSame('https://www.schachbund.de/dwz-turniere/'.$uuid.'.html', Altcodes::ziel('https://www.schachbund.de/dwz-turniere/', $uuid, false, '.html'));
        $this->assertSame('https://www.schachbund.de/dwz-turniere/'.$uuid.'/Ergebnisse.html', Altcodes::ziel('https://www.schachbund.de/dwz-turniere', $uuid, true, '.html'));
        $this->assertSame('/turniere/'.$uuid, Altcodes::ziel('/turniere', $uuid, false, ''));
    }

    /**
     * Datum aus der Tabelle für die Anzeige.
     */
    public function testDatum(): void
    {
        $this->assertSame('12.05.2026', Altcodes::datum('2026-05-12'));
        $this->assertSame('', Altcodes::datum(''));
        $this->assertSame('', Altcodes::datum('12.05.2026'));
    }

    /**
     * Eine Datei ohne die Spalten uuid und tcode wird abgewiesen, statt
     * lautlos nichts zu übernehmen.
     */
    public function testFalscheDateiWirdAbgewiesen(): void
    {
        $pfad = tempnam(sys_get_temp_dir(), 'altcodes');
        file_put_contents($pfad, "\"Nachname\";\"Vorname\"\r\n\"A\";\"B\"\r\n");

        $this->expectException(\RuntimeException::class);

        try {
            iterator_to_array(Altcodes::leseDatei($pfad));
        } finally {
            unlink($pfad);
        }
    }
}
