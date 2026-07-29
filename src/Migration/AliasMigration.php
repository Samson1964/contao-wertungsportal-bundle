<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;

/**
 * Füllt die Suchalias-Felder des Bestands (contao:migrate bzw. Install-Tool).
 *
 * Neu geschriebene Datensätze bekommen ihren Alias über die Models
 * (ApiSyncTrait::aliasFelder). Für die Datensätze, die schon vor der
 * Umstellung in der Datenbank standen, gibt es diese Migration — ohne sie
 * fänden die lokalen Suchen die Altbestände nicht.
 *
 * Die Migration arbeitet blockweise und mit Zeitbudget: Bei rund 96.000
 * Personen dauert allein die Alias-Erzeugung etwa 15 Sekunden, was im
 * Install-Tool (Zeitlimit des Webservers) knapp werden kann. Reicht die Zeit
 * nicht, meldet die Migration den Rest und bleibt fällig — ein weiterer
 * Durchlauf von contao:migrate setzt genau dort fort, weil immer nur noch
 * fehlende Aliase ausgewählt werden.
 */
class AliasMigration extends AbstractMigration
{
    /**
     * Tabelle => [Quellfeld => Aliasfeld].
     */
    private const TABELLEN = [
        'tl_wertungsportal_persons' => [
            'firstname' => 'firstnameAlias',
            'lastname'  => 'lastnameAlias',
        ],
        'tl_wertungsportal_clubs' => [
            'clubName' => 'clubNameAlias',
        ],
        'tl_wertungsportal_tournaments' => [
            'label' => 'labelAlias',
        ],
    ];

    /**
     * Datensätze je Block. Je Block und Aliasfeld geht genau EIN UPDATE mit
     * CASE-Zuordnung an die Datenbank — einzelne UPDATEs je Datensatz wären
     * bei 96.000 Personen um ein Vielfaches langsamer.
     */
    private const BLOCKGROESSE = 1000;

    /**
     * Zeitbudget in Sekunden, bewusst unter den üblichen 30 Sekunden
     * Ausführungszeit eines Webservers.
     */
    private const ZEITBUDGET = 20;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'Wertungsportal: Suchaliase für Personen, Vereine und Turniere erzeugen';
    }

    public function shouldRun(): bool
    {
        foreach (self::TABELLEN as $strTabelle => $arrFelder) {
            if ($this->tabelleBereit($strTabelle, $arrFelder) && $this->offen($strTabelle, $arrFelder) > 0) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $intStart = time();
        $arrMeldungen = [];
        $blnFertig = true;

        foreach (self::TABELLEN as $strTabelle => $arrFelder) {
            if (!$this->tabelleBereit($strTabelle, $arrFelder)) {
                continue;
            }

            $intGeschrieben = 0;
            $strWhere = $this->bedingung($arrFelder);
            $strSpalten = implode(', ', array_map([$this->connection, 'quoteIdentifier'], array_keys($arrFelder)));

            while (true) {
                // Zeitbudget aufgebraucht: hier abbrechen, der nächste Lauf
                // setzt an derselben Stelle fort
                if (time() - $intStart >= self::ZEITBUDGET) {
                    $blnFertig = false;
                    break;
                }

                $arrZeilen = $this->connection->fetchAllAssociative(
                    sprintf('SELECT id, %s FROM %s WHERE %s LIMIT %d', $strSpalten, $strTabelle, $strWhere, self::BLOCKGROESSE)
                );

                if (!$arrZeilen) {
                    break;
                }

                foreach ($arrFelder as $strQuelle => $strAlias) {
                    $this->schreibeBlock($strTabelle, $strQuelle, $strAlias, $arrZeilen);
                }

                $intGeschrieben += \count($arrZeilen);

                // Weniger Zeilen als angefordert: Der Rest ist erledigt
                if (\count($arrZeilen) < self::BLOCKGROESSE) {
                    break;
                }
            }

            if ($intGeschrieben > 0) {
                $arrMeldungen[] = sprintf('%s: %d Datensätze mit Suchalias versehen', $strTabelle, $intGeschrieben);
            }

            $intRest = $this->offen($strTabelle, $arrFelder);

            if ($intRest > 0) {
                $blnFertig = false;
                $arrMeldungen[] = sprintf('%s: noch %d Datensätze offen', $strTabelle, $intRest);
            }
        }

        if (!$arrMeldungen) {
            $arrMeldungen[] = 'Keine Suchaliase zu erzeugen';
        }

        if (!$blnFertig) {
            $arrMeldungen[] = 'Zeitbudget erreicht — contao:migrate bitte erneut ausführen, damit der Rest erzeugt wird.';
        }

        return $this->createResult(true, implode("\n", $arrMeldungen));
    }

    /**
     * Schreibt die Aliase eines Blocks mit einem einzigen UPDATE
     * (SET alias = CASE id WHEN … THEN … END WHERE id IN (…)).
     */
    private function schreibeBlock(string $strTabelle, string $strQuelle, string $strAlias, array $arrZeilen): void
    {
        $arrCases = [];
        $arrIds = [];

        foreach ($arrZeilen as $arrZeile) {
            $strWert = Helper::alias((string) $arrZeile[$strQuelle]);

            // Leeres Quellfeld: Der Alias bleibt leer, hier ist nichts zu tun
            if ('' === $strWert) {
                continue;
            }

            $intId = (int) $arrZeile['id'];
            $arrCases[] = sprintf('WHEN %d THEN %s', $intId, $this->connection->quote($strWert));
            $arrIds[] = $intId;
        }

        if (!$arrIds) {
            return;
        }

        $this->connection->executeStatement(
            sprintf(
                'UPDATE %s SET %s = CASE id %s END WHERE id IN (%s)',
                $strTabelle,
                $this->connection->quoteIdentifier($strAlias),
                implode(' ', $arrCases),
                implode(', ', $arrIds)
            )
        );
    }

    /**
     * Bedingung "mindestens ein Alias fehlt, obwohl das Quellfeld gefüllt ist".
     */
    private function bedingung(array $arrFelder): string
    {
        $arrTeile = [];

        foreach ($arrFelder as $strQuelle => $strAlias) {
            $arrTeile[] = sprintf(
                '(%s != %s AND %s = %s)',
                $this->connection->quoteIdentifier($strQuelle),
                $this->connection->quote(''),
                $this->connection->quoteIdentifier($strAlias),
                $this->connection->quote('')
            );
        }

        return implode(' OR ', $arrTeile);
    }

    /**
     * Zählt die Datensätze, denen noch ein Alias fehlt.
     */
    private function offen(string $strTabelle, array $arrFelder): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s', $strTabelle, $this->bedingung($arrFelder))
        );
    }

    /**
     * Prüft, ob Tabelle und Spalten vorhanden sind. Beim allerersten
     * contao:migrate nach dem Upload legt Contao die Alias-Spalten erst an;
     * bis dahin darf die Migration nichts tun, sonst bricht sie mit einem
     * SQL-Fehler ab.
     */
    private function tabelleBereit(string $strTabelle, array $arrFelder): bool
    {
        // createSchemaManager gibt es erst ab DBAL 3.1; Contao 4.13 läuft
        // je nach Installation noch mit DBAL 2.13
        $objSchema = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();

        if (!$objSchema->tablesExist([$strTabelle])) {
            return false;
        }

        $arrSpalten = array_keys($objSchema->listTableColumns($strTabelle));

        foreach ($arrFelder as $strQuelle => $strAlias) {
            if (!\in_array(strtolower($strQuelle), $arrSpalten, true) || !\in_array(strtolower($strAlias), $arrSpalten, true)) {
                return false;
            }
        }

        return true;
    }
}
