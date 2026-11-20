<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Wertungsarchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archivreklamation;

/**
 * Prüft die reinen Teile der Archivreklamation: Schlüssel, Eingabeprüfung,
 * Logdatei, Bremse, Anzeige und den nächtlichen Bericht.
 */
class ArchivreklamationTest extends TestCase
{
    /**
     * Karteizeile wie aus Archiv::altkartei() (Max Musters doppelter
     * Eintrag aus dem ZDB-Abzug 1997-07).
     *
     * @return array<string,mixed>
     */
    private static function zeile(): array
    {
        return array(
            'id' => 26867678, 'archiv' => 'elobase', 'kennung' => 'Z:1234567', 'code' => '9424-300-GB1', 'quelle' => '1997-07',
            'partien' => 7, 'punkte_halbe' => 10, 'dwz_vorher' => 1917, 'dwz' => 1928, 'vor_erster_anmeldung' => 0,
            'extra' => '{"eintrag":12,"satz":844001,"erg_e_koeffizient":28}',
            't_id' => 14656, 't_quell_id' => '9424-300-GB1', 't_name' => 'BEM Gruppe B1 Wilmersdorf', 't_ende' => '1994-06-14',
        );
    }

    /**
     * Der Schlüssel hängt an fachlichen Werten, nicht an der Datenbanknummer:
     * Nach einem neuen Import (andere id, andere t_id) trifft er dieselbe Zeile.
     */
    public function testSchluesselUeberstehtNeuimport(): void
    {
        $a = self::zeile();
        $b = array('id' => 1, 't_id' => 2) + $a;

        $this->assertSame('elobase|Z:1234567|9424-300-GB1|1997-07|844001', Archivreklamation::schluessel($a));
        $this->assertSame(Archivreklamation::schluessel($a), Archivreklamation::schluessel($b));

        // Derselbe Turniercode aus dem letzten Stand ist eine andere Zeile
        $c = array('quelle' => 'ZDB', 'extra' => '{"satz":2729404}') + $a;
        $this->assertNotSame(Archivreklamation::schluessel($a), Archivreklamation::schluessel($c));

        $this->assertSame('fehlt|NU0000001', Archivreklamation::fehltSchluessel('NU0000001', 'Z:1'));
        $this->assertSame('fehlt|Z:1', Archivreklamation::fehltSchluessel('', 'Z:1'));
    }

    /**
     * Unbekannte Gründe fallen weg; ohne Grund braucht es eine Bemerkung,
     * „Etwas anderes" immer; „Turnier fehlt" braucht einen Namen oder Text.
     */
    public function testEingabe(): void
    {
        $e = Archivreklamation::bereinige(array('fremd', 'gibtsnicht', 'doppelt'), "  Zeile 1\r\nZeile 2\x07 ", array('turnier' => " BEM\n 1994 ", 'unbekannt' => 'x'));

        $this->assertSame(array('fremd', 'doppelt'), $e['gruende']);
        $this->assertSame("Zeile 1\nZeile 2", $e['text']);
        $this->assertSame(array('turnier' => 'BEM  1994'), $e['felder']);
        $this->assertNull(Archivreklamation::eingabefehler('eintrag', $e));

        $this->assertNotNull(Archivreklamation::eingabefehler('eintrag', Archivreklamation::bereinige(array(), 'kurz', array())));
        $this->assertNull(Archivreklamation::eingabefehler('eintrag', Archivreklamation::bereinige(array(), 'Das war mein Namensvetter aus Lüneburg', array())));
        $this->assertNotNull(Archivreklamation::eingabefehler('eintrag', Archivreklamation::bereinige(array('sonstiges'), '', array())));
        $this->assertNotNull(Archivreklamation::eingabefehler('fehlt', Archivreklamation::bereinige(array(), '', array('ort' => 'Berlin'))));
        $this->assertNull(Archivreklamation::eingabefehler('fehlt', Archivreklamation::bereinige(array(), '', array('turnier' => 'BMM 1990/91'))));
    }

    /**
     * Schreiben, Lesen, Bremse und Anzeige über eine echte Datei; die
     * Anzeige nennt nie den Melder, nur ob es das Mitglied selbst war.
     */
    public function testLogdatei(): void
    {
        $datei = sys_get_temp_dir().'/archivreklamation-'.uniqid().'/reklamationen.jsonl';
        $kontext = Archivreklamation::kontext(self::zeile(), 'NU0000001', 4711, 'Muster, Max');
        $eingabe = Archivreklamation::bereinige(array('doppelt'), 'Gleiches Turnier wie 9424-300-B1W', array());
        $mitglied = array('id' => 7, 'name' => 'Max Muster', 'email' => 'test@example.org');

        $this->assertTrue(Archivreklamation::schreibe($datei, Archivreklamation::satz($kontext, $eingabe, $mitglied, 1763600000)));
        $this->assertTrue(Archivreklamation::schreibe($datei, Archivreklamation::satz($kontext, $eingabe, array('id' => 8) + $mitglied, 1763600100)));
        file_put_contents($datei, "kaputt\n", FILE_APPEND);

        $alle = Archivreklamation::lese($datei);
        $schluessel = Archivreklamation::schluessel(self::zeile());

        $this->assertCount(2, $alle[$schluessel]);
        $this->assertSame('offen', $alle[$schluessel][0]['status']);
        $this->assertSame(844001, $alle[$schluessel][0]['eintrag']['satz']);
        $this->assertSame(1, Archivreklamation::zaehle($alle, 7, 1763599000));
        $this->assertSame(0, Archivreklamation::zaehle($alle, 7, 1763600001));

        $anzeige = Archivreklamation::anzeige($alle[$schluessel][0], 7);
        $this->assertTrue($anzeige['eigen']);
        $this->assertFalse(Archivreklamation::anzeige($alle[$schluessel][1], 7)['eigen']);
        $this->assertSame(array(Archivreklamation::GRUENDE['doppelt']), $anzeige['gruende']);
        $this->assertStringNotContainsString('example.org', (string) json_encode($anzeige));

        $this->assertStringContainsString('reklamiert', Archivreklamation::marke($alle[$schluessel]));
        $this->assertSame('', Archivreklamation::marke(array()));

        unlink($datei);
        rmdir(\dirname($datei));
    }

    /**
     * Der Knopf trägt einen Kontext, den Reklamation::entschluessele() mit
     * demselben Geheimnis zurückholt — und mit einem anderen nicht.
     */
    public function testKnopfSigniert(): void
    {
        $kontext = Archivreklamation::kontext(self::zeile(), 'NU0000001', 4711, 'Muster, Max');
        $html = Archivreklamation::knopf($kontext, array(), 7, 'Titel {{insert}}', 'geheim');

        $this->assertSame(1, preg_match('/data-kontext="([^"]+)" data-signatur="([^"]+)"/', $html, $m));
        $this->assertSame($kontext, Reklamation::entschluessele($m[1], $m[2], 'geheim'));
        $this->assertNull(Reklamation::entschluessele($m[1], $m[2], 'anders'));
        $this->assertStringNotContainsString('{{', $html);
    }

    /**
     * Der Bericht nimmt nur Meldungen nach dem letzten Bericht und nennt
     * Karteikarte, Eintrag mit Abzug, Gründe, Bemerkung und Melder.
     */
    public function testBericht(): void
    {
        $kontext = Archivreklamation::kontext(self::zeile(), 'NU0000001', 4711, 'Muster, Max');
        $mitglied = array('id' => 7, 'name' => 'Max Muster', 'email' => 'test@example.org');
        $alt = Archivreklamation::satz($kontext, Archivreklamation::bereinige(array('fremd'), '', array()), $mitglied, 1000);
        $neu = Archivreklamation::satz($kontext, Archivreklamation::bereinige(array('doppelt'), 'wie B1W', array()), $mitglied, 2000);
        $alle = array($kontext['schluessel'] => array($alt, $neu));

        $saetze = Archivreklamation::zeitraum($alle, 1000, 3000);
        $this->assertCount(1, $saetze);

        $text = Archivreklamation::berichtText($saetze, 'https://example.org/spieler', '.html');
        $this->assertStringContainsString('1 neue Reklamation im Wertungsarchiv', $text);
        $this->assertStringContainsString('https://example.org/spieler/NU0000001.html', $text);
        $this->assertStringContainsString('[9424-300-GB1], 1994-06-14, aus ZDB (Abzug 1997-07), Person Z:1234567', $text);
        $this->assertStringContainsString(Archivreklamation::GRUENDE['doppelt'], $text);
        $this->assertStringContainsString('Bemerkung: wie B1W', $text);
        $this->assertStringContainsString('Gemeldet von: Max Muster <test@example.org>', $text);
    }
}
