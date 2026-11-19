<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Wertungsarchiv;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Darstellung;

/**
 * Prüft die Anzeigeformen des Wertungsarchivs (Etappe 3).
 */
class DarstellungTest extends TestCase
{
    /**
     * Halbpunkte, We · 1000 und DWZ werden nur für die Anzeige umgerechnet.
     */
    public function testEinheiten(): void
    {
        $this->assertSame('4½', Darstellung::punkte(9));
        $this->assertSame('4', Darstellung::punkte(8));
        $this->assertSame('½', Darstellung::punkte(1));
        $this->assertSame('0', Darstellung::punkte(0));
        $this->assertSame('', Darstellung::punkte(null));

        $this->assertSame('7,215', Darstellung::we(7215));
        $this->assertSame('0,065', Darstellung::we(65));
        $this->assertSame('', Darstellung::we(null));

        $this->assertSame('2583 -&nbsp;&nbsp;13', Darstellung::dwz(2583, 13));
        $this->assertSame('', Darstellung::dwz(0, 0));
        $this->assertSame('&nbsp;&nbsp;799', Darstellung::dwz(799, null));
    }

    /**
     * Jahr aus dem Turnierende, sonst aus dem ELOBASE-Code (JJWW-…).
     */
    public function testJahr(): void
    {
        $this->assertSame('2005', Darstellung::jahr('2005-01-08', 'A501-000-BEZ'));
        $this->assertSame('1995', Darstellung::jahr(null, '9546-663-SXJ'));
        $this->assertSame('2002', Darstellung::jahr(null, 'A209-100-ASA'));
        $this->assertSame('1978', Darstellung::jahr(null, '7830-52.-BL '));
        $this->assertSame('', Darstellung::jahr(null, 'ungültig:0000'));
    }

    /**
     * Eine Karteizeile: Link aufs Archivturnier, Texte maskiert,
     * Erfolgszahl von ELOBASE NICHT als Leistung, Kennzeichen vor der
     * ersten Mitgliedschaft.
     */
    public function testKarteizeile(): void
    {
        $z = Darstellung::karteizeile(array(
            'archiv' => 'elobase', 'code' => '9546-663-SXJ', 'punkte_halbe' => 7, 'partien' => 5, 'we1000' => 2468,
            'dwz' => 1610, 'dwz_index' => 12, 'leistung' => 33596, 'turnierleistung' => null, 'vor_erster_anmeldung' => 1,
            't_id' => 42, 't_name' => 'Open <Test> & Co', 't_ende' => null, 't_json' => 'turniere/1995/9546-663-SXJ.json', 't_kennzeichen' => '',
        ), '/dwz-turniere', '.html');

        $this->assertStringContainsString('wp-archivsymbol', $z['nummer']);
        $this->assertStringEndsWith('</svg> ZDB</span>', $z['nummer']);
        $this->assertStringContainsString('title="Aus dem Wertungsarchiv: ELOBASE (ZDB)"', $z['nummer']);
        $this->assertSame('', $z['e']);
        $this->assertSame('', $z['gegner']);
        $this->assertSame('1995', $z['jahr']);
        $this->assertStringContainsString('href="/dwz-turniere/A42.html"', $z['turnier']);
        $this->assertStringContainsString('Open &lt;Test&gt; &amp; Co', $z['turnier']);
        $this->assertSame('3½', $z['punkte']);
        $this->assertSame('2,468', $z['we']);
        $this->assertSame('', $z['leistung']);
        $this->assertTrue($z['vor']);

        $ausgang = Darstellung::karteizeile(array('archiv' => 'dewis', 'code' => 'B053-000-DWZ', 't_id' => 7, 't_kennzeichen' => 'kein_turnier', 'dwz' => 799, 'dwz_index' => 1), '/t', '');
        $this->assertSame('DWZ-Ausgangswert (B053-000-DWZ)', $ausgang['turnier']);
        $this->assertStringEndsWith('</svg> DeWIS</span>', $ausgang['nummer']);
    }

    /**
     * E, Gegnerschnitt und Turnierleistung: DeWIS und Portal aus Spalten
     * bzw. extra.e_koeffizient, ELOBASE (nach Rückfrage R5) aus
     * erg_e_koeffizient in extra.
     */
    public function testKoeffizientGegnerLeistung(): void
    {
        $d = Darstellung::karteizeile(array('archiv' => 'dewis', 'code' => 'B145-K00-ECO', 'gegnerschnitt' => 2499, 'turnierleistung' => 2523, 'extra' => '{"e_koeffizient":30}'), '/t', '');
        $this->assertSame(array('30', '2499', '2523'), array($d['e'], $d['gegner'], $d['leistung']));

        $w = Darstellung::karteizeile(array('archiv' => 'wp', 'extra' => '{"e_koeffizient":39.3}'), '/t', '');
        $this->assertSame('39,3', $w['e']);

        $z = Darstellung::karteizeile(array('archiv' => 'elobase', 'gegnerschnitt' => 1850, 'turnierleistung' => 1990, 'leistung' => 2060, 'extra' => '{"erg_e_koeffizient":17}'), '/t', '');
        $this->assertSame(array('17', '1850', '1990'), array($z['e'], $z['gegner'], $z['leistung']));
    }

    /**
     * Entwicklungskoeffizient: ganzzahlig ohne, sonst mit Komma; ohne Wert leer.
     */
    public function testE(): void
    {
        $this->assertSame('28', Darstellung::e('28.0'));
        $this->assertSame('30', Darstellung::e(30));
        $this->assertSame('39,3', Darstellung::e(39.3));
        $this->assertSame('', Darstellung::e(0));
        $this->assertSame('', Darstellung::e(null));
        $this->assertSame('', Darstellung::e('x'));
    }

    /**
     * Diagrammpunkt: DWZ nach dem Turnier, Leistung nur aus der
     * Turnierleistung (nie die ELOBASE-Erfolgszahl), nichts zum Schätzen.
     */
    public function testDiagrammpunkt(): void
    {
        $p = Darstellung::diagrammpunkt(array(
            'archiv' => 'elobase', 'code' => '9546-663-SXJ', 'partien' => 5, 'dwz' => 1610,
            'leistung' => 33596, 'turnierleistung' => null, 't_name' => 'Open', 't_ende' => null,
        ));

        $this->assertSame('ZDB', $p['nummer']);
        $this->assertSame('1995', $p['jahr']);
        $this->assertSame(1610, $p['dwz']);
        $this->assertSame(0, $p['leistung']);
        $this->assertSame(0, $p['gegner']);
        $this->assertSame('Open', $p['bezeichnung']);
        $this->assertTrue($p['archiv']);

        $d = Darstellung::diagrammpunkt(array('archiv' => 'dewis', 'code' => 'B053-000-DWZ', 'dwz' => 1800, 'turnierleistung' => 1925, 't_ende' => '2010-05-02'));
        $this->assertSame('DeWIS', $d['nummer']);
        $this->assertSame('2010', $d['jahr']);
        $this->assertSame(1925, $d['leistung']);
        $this->assertSame('B053-000-DWZ', $d['bezeichnung']);
    }

    /**
     * Suchmuster für alle Schreibweisen der Archive, Platzhalter maskiert.
     */
    public function testNamensmuster(): void
    {
        $this->assertSame(
            array('Müller, Kla%', 'Müller,Kla%', 'Mueller, Kla%', 'Mueller,Kla%'),
            \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::namensmuster('Müller', 'Kla')
        );
        $this->assertSame(array('Li%'), \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::namensmuster('Li', ''));
        $this->assertSame(array('50\\%%'), \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::namensmuster('50%', ''));
        $this->assertSame(array(), \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::namensmuster(' ', 'X'));
    }

    /**
     * Sortierschlüssel: Turnierende, ohne Ende das Jahr aus dem Code —
     * ein ELOBASE-Eintrag ohne Turnierkopf landet so im richtigen Jahr.
     */
    public function testZeitpunkt(): void
    {
        $a = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::zeitpunkt(array('t_ende' => '2010-12-31', 'code' => 'B053-000-DWZ', 'id' => 5));
        $b = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::zeitpunkt(array('t_ende' => null, 'code' => '9200-  -  ', 'id' => 7));
        $c = \Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archiv::zeitpunkt(array('t_ende' => '1994-05-01', 'code' => '9418-123-BZL', 'id' => 6));

        $this->assertStringStartsWith('1992-00-00', $b);
        $this->assertTrue($a > $c && $c > $b);
    }

    /**
     * Tabellen der Turnier-JSON über die Feldnamen lesen.
     */
    public function testZeilen(): void
    {
        $this->assertSame(
            array(array('nr' => 1, 'name' => 'A'), array('nr' => 2, 'name' => 'B')),
            Darstellung::zeilen(array('felder' => array('nr', 'name'), 'zeilen' => array(array(1, 'A'), array(2, 'B'), array(3))))
        );
    }
}
