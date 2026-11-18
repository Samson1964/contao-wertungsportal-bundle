<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema as DbalSchema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Types\Types;

/**
 * Tabellen der Archivdatenbank (ab 2.0.0).
 *
 * Die Tabellen stehen bewusst nicht im DCA: `contao:migrate` arbeitet nur auf
 * der Datenbank von Contao und würde sie dort anlegen. Angelegt und erweitert
 * werden sie mit `wertungsportal:archiv-schema`, der das Soll hier mit der
 * Datenbank vergleicht. Gelöscht wird dabei nie etwas — weder Tabellen noch
 * Spalten.
 *
 * In die Datenbank kommt, wonach gesucht oder verknüpft wird (Festlegung
 * 17.11.2026); die Turniere selbst bleiben JSON-Dateien. Jede Zeile trägt die
 * Kennung ihres Archivs (`elobase`, `dewis`, `wp`). Felder einer Liste, die
 * keine eigene Spalte haben, stehen unverändert in `extra` (JSON) — so geht
 * nichts aus der Übergabe verloren, auch wenn die Aufbereitung Felder ergänzt.
 */
class Schema
{
    /**
     * Fassung des Schemas; wird in archiv_info vermerkt.
     */
    public const FASSUNG = 3;

    /**
     * Baut das Soll-Schema.
     *
     * @return DbalSchema Alle Tabellen des Archivs
     */
    public static function soll(): DbalSchema
    {
        // Zeichensatz ausdrücklich: Ohne Angabe nimmt MySQL die Vorgabe der
        // Datenbank, und die ist oft noch utf8 (drei Byte)
        $einstellungen = new SchemaConfig();
        $einstellungen->setDefaultTableOptions(array('charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB', 'row_format' => 'DYNAMIC'));
        $schema = new DbalSchema(array(), array(), $einstellungen);

        // Schlüssel und Codes genau vergleichen: utf8mb4_unicode_ci setzt Ö = O
        // und übergeht Leerzeichen am Ende — „9218-000-DÖB“ und „9218-000-DOB“
        // sind aber verschiedene Turniere, „7830-52.-BL “ trägt sein Leerzeichen
        // (beim ersten Import fielen so 23 ELOBASE-Turniere zusammen)
        $genau = array('platformOptions' => array('collation' => 'utf8mb4_bin'));

        // Merkzettel: Schemafassung, Stand je Archiv (Zeilen, Prüfsummen)
        $t = $schema->createTable('archiv_info');
        $t->addColumn('schluessel', Types::STRING, array('length' => 64));
        $t->addColumn('wert', Types::TEXT, array('notnull' => false));
        $t->setPrimaryKey(array('schluessel'));

        // Turnierliste aller drei Archive
        $t = $schema->createTable('archiv_turnier');
        $t->addColumn('id', Types::INTEGER, array('unsigned' => true, 'autoincrement' => true));
        $t->addColumn('archiv', Types::STRING, array('length' => 8));
        $t->addColumn('quell_id', Types::STRING, array('length' => 64) + $genau);
        $t->addColumn('code', Types::STRING, array('length' => 32, 'notnull' => false) + $genau);
        $t->addColumn('name', Types::STRING, array('length' => 255, 'notnull' => false));
        $t->addColumn('art', Types::STRING, array('length' => 8, 'notnull' => false));
        $t->addColumn('ende', Types::STRING, array('length' => 10, 'notnull' => false));
        $t->addColumn('jahr', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('verband', Types::STRING, array('length' => 8, 'notnull' => false));
        $t->addColumn('spieler', Types::INTEGER, array('notnull' => false));
        $t->addColumn('partien', Types::INTEGER, array('notnull' => false));
        $t->addColumn('runden', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('json', Types::STRING, array('length' => 255, 'notnull' => false));
        $t->addColumn('rekonstruiert', Types::BOOLEAN, array('default' => false));
        $t->addColumn('kennzeichen', Types::STRING, array('length' => 255, 'default' => ''));
        // Gegenstück im älteren Archiv (Dublette): wp → dewis, dewis → elobase
        $t->addColumn('dublette_archiv', Types::STRING, array('length' => 8, 'notnull' => false));
        $t->addColumn('dublette_quell_id', Types::STRING, array('length' => 64, 'notnull' => false) + $genau);
        $t->addColumn('extra', Types::TEXT, array('notnull' => false));
        $t->setPrimaryKey(array('id'));
        $t->addUniqueIndex(array('archiv', 'quell_id'), 'turnier_quelle');
        $t->addIndex(array('code'), 'turnier_code');
        $t->addIndex(array('ende'), 'turnier_ende');
        $t->addIndex(array('dublette_archiv', 'dublette_quell_id'), 'turnier_dublette');

        // Personen je Archiv: Z:<PKZ>, D:<DeWIS-ID>, N:<NU-ID>, W:<Turnier>-<Nr>
        $t = $schema->createTable('archiv_person');
        $t->addColumn('id', Types::INTEGER, array('unsigned' => true, 'autoincrement' => true));
        $t->addColumn('archiv', Types::STRING, array('length' => 8));
        $t->addColumn('kennung', Types::STRING, array('length' => 48) + $genau);
        $t->addColumn('name', Types::STRING, array('length' => 255, 'notnull' => false));
        $t->addColumn('geschlecht', Types::STRING, array('length' => 4, 'notnull' => false));
        $t->addColumn('geburtsjahr', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('extra', Types::TEXT, array('notnull' => false));
        // Gesamtperson (Etappe 2), gesetzt von Gesamtpersonen::bilden()
        $t->addColumn('gesamt_id', Types::INTEGER, array('unsigned' => true, 'notnull' => false));
        $t->setPrimaryKey(array('id'));
        $t->addUniqueIndex(array('kennung'), 'person_kennung');
        $t->addIndex(array('name'), 'person_name');
        $t->addIndex(array('gesamt_id'), 'person_gesamt');

        // Gesamtpersonen: zusammenhängende Archivpersonen über bestätigte
        // Kanten. id = kleinste archiv_person.id der Gruppe, damit ein neuer
        // Lauf dieselben Nummern vergibt
        $t = $schema->createTable('archiv_gesamtperson');
        $t->addColumn('id', Types::INTEGER, array('unsigned' => true));
        $t->addColumn('nu_id', Types::STRING, array('length' => 16, 'notnull' => false) + $genau);
        $t->addColumn('name', Types::STRING, array('length' => 255, 'notnull' => false));
        $t->addColumn('geburtsjahr', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('personen', Types::INTEGER, array('unsigned' => true, 'default' => 0));
        $t->addColumn('archive', Types::STRING, array('length' => 32, 'default' => ''));
        $t->addColumn('pruefen', Types::STRING, array('length' => 128, 'default' => ''));
        $t->setPrimaryKey(array('id'));
        $t->addIndex(array('nu_id'), 'gesamt_nu');
        $t->addIndex(array('name'), 'gesamt_name');

        // Kanten zwischen Personen verschiedener Archive (Brücke). Alle Kanten
        // der Übergabe gelten als bestätigt (Frank, 18.11.2026); der Status
        // bleibt, damit sich eine Zusammenführung zurücknehmen lässt
        $t = $schema->createTable('archiv_kante');
        $t->addColumn('id', Types::INTEGER, array('unsigned' => true, 'autoincrement' => true));
        $t->addColumn('von', Types::STRING, array('length' => 48) + $genau);
        $t->addColumn('nach', Types::STRING, array('length' => 48) + $genau);
        $t->addColumn('quelle', Types::STRING, array('length' => 32));
        $t->addColumn('gewicht', Types::INTEGER, array('notnull' => false));
        $t->addColumn('status', Types::STRING, array('length' => 16, 'default' => 'bestaetigt'));
        $t->addColumn('geaendert', Types::INTEGER, array('unsigned' => true, 'default' => 0));
        $t->setPrimaryKey(array('id'));
        $t->addUniqueIndex(array('von', 'nach', 'quelle'), 'kante_paar');
        $t->addIndex(array('nach'), 'kante_nach');

        // Karteizeilen: Person × Turnier mit den Werten der Kartei
        $t = $schema->createTable('archiv_kartei');
        $t->addColumn('id', Types::INTEGER, array('unsigned' => true, 'autoincrement' => true));
        $t->addColumn('archiv', Types::STRING, array('length' => 8));
        $t->addColumn('person_id', Types::INTEGER, array('unsigned' => true));
        $t->addColumn('turnier_id', Types::INTEGER, array('unsigned' => true, 'notnull' => false));
        $t->addColumn('code', Types::STRING, array('length' => 32, 'notnull' => false) + $genau);
        $t->addColumn('quelle', Types::STRING, array('length' => 8, 'notnull' => false));
        $t->addColumn('ende', Types::STRING, array('length' => 10, 'notnull' => false));
        $t->addColumn('partien', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('punkte_halbe', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('dwz_vorher', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('index_vorher', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('dwz', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('dwz_index', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('we1000', Types::INTEGER, array('notnull' => false));
        $t->addColumn('leistung', Types::INTEGER, array('notnull' => false));
        $t->addColumn('turnierleistung', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('gegnerschnitt', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('einzelpartie', Types::BOOLEAN, array('default' => false));
        $t->addColumn('vor_erster_anmeldung', Types::SMALLINT, array('notnull' => false));
        $t->addColumn('extra', Types::TEXT, array('notnull' => false));
        $t->setPrimaryKey(array('id'));
        $t->addIndex(array('person_id'), 'kartei_person');
        $t->addIndex(array('turnier_id'), 'kartei_turnier');
        $t->addIndex(array('archiv'), 'kartei_archiv');

        return $schema;
    }

    /**
     * Liefert die SQL-Anweisungen, die die Datenbank auf das Soll bringen.
     *
     * Löschende Anweisungen (DROP TABLE, DROP COLUMN, DROP INDEX außerhalb
     * eines Umbaus) werden herausgefiltert: In der Archivdatenbank soll nie
     * ein Befehl etwas verschwinden lassen, das jemand von Hand angelegt hat.
     *
     * @param Connection $db Verbindung zur Archivdatenbank
     *
     * @return array<int,string> SQL-Anweisungen, leer wenn alles stimmt
     */
    public static function anweisungen(Connection $db): array
    {
        $verwalter = $db->createSchemaManager();
        $ist = $verwalter->introspectSchema();
        $soll = self::soll();

        // Fremde Tabellen bleiben unbeachtet, sonst schlüge der Vergleich ihr
        // Löschen vor
        foreach ($ist->getTables() as $tabelle) {
            if (!$soll->hasTable($tabelle->getName())) {
                $ist->dropTable($tabelle->getName());
            }
        }

        $unterschied = $verwalter->createComparator()->compareSchemas($ist, $soll);
        $sql = $db->getDatabasePlatform()->getAlterSchemaSQL($unterschied);

        return array_values(array_filter($sql, static function ($s) {
            return !preg_match('/^\s*DROP\s+(TABLE|DATABASE)\b|\bDROP\s+COLUMN\b/i', $s);
        }));
    }
}
