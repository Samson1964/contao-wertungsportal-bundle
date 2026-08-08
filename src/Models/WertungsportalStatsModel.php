<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_stats.
 *
 * Zählt die Abrufe der Wertungsportal-Schnittstelle: je Tag, Funktion und
 * Quelle (API oder lokaler Cache) genau EIN Datensatz mit einem Zähler.
 * Die Zählung läuft in API::autoQuery() und kostet damit eine Abfrage je
 * Seitenaufruf, der die Schnittstelle nutzt.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $datum      Tag der Zählung (JJJJ-MM-TT)
 * @property string $funktion   interne Funktion (z. B. Spielerliste)
 * @property string $endpunkt   API-Pfad (z. B. /dwz/dwzliste/persons)
 * @property string $quelle     api | cache | lokal | vorlader
 * @property int    $anzahl     Abrufe an diesem Tag
 *
 * @method static WertungsportalStatsModel|null findById($id, array $opt = [])
 * @method static WertungsportalStatsModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalStatsModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalStatsModel[]|null findBy($col, $val, array $opt = [])
 */
class WertungsportalStatsModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_stats';

    public const QUELLE_API = 'api';
    public const QUELLE_CACHE = 'cache';
    public const QUELLE_LOKAL = 'lokal';

    /**
     * Abrufe des nächtlichen Vorladers (Cron\TurnierVorlader).
     *
     * Bewusst eine eigene Quelle und nicht QUELLE_API: Der Vorlader macht in
     * einer Nacht ein Vielfaches dessen, was Besucher an einem Tag auslösen.
     * Zusammengezählt wäre nicht mehr zu erkennen, wie gut der
     * Zwischenspeicher die Besucher bedient — und genau diese Zahl war der
     * Anlass für den Vorlader.
     *
     * Der Wert ist genau 8 Zeichen lang; die Spalte fasst nicht mehr.
     */
    public const QUELLE_VORLADER = 'vorlader';

    /**
     * Zählt einen Abruf. Der Datensatz des Tages wird angelegt oder sein
     * Zähler erhöht — beides in einer einzigen Abfrage, damit die Zählung
     * die Seitenauslieferung nicht spürbar belastet und auch bei
     * gleichzeitigen Zugriffen nichts verloren geht.
     *
     * Fehler werden bewusst verschluckt: Eine fehlende Tabelle (vor
     * contao:migrate) darf das Frontend nicht lahmlegen.
     */
    public static function zaehle(string $strFunktion, string $strQuelle, string $strEndpunkt = ''): void
    {
        if ('' === $strFunktion) {
            return;
        }

        try {
            Database::getInstance()
                ->prepare('INSERT INTO ' . static::$strTable . ' (tstamp, datum, funktion, endpunkt, quelle, anzahl) VALUES (?, ?, ?, ?, ?, 1)
                           ON DUPLICATE KEY UPDATE anzahl = anzahl + 1, tstamp = VALUES(tstamp)')
                ->execute(time(), date('Y-m-d'), $strFunktion, $strEndpunkt, $strQuelle);
        } catch (\Throwable $e) {
            // Statistik ist Beiwerk — niemals die Auslieferung stören
        }
    }

    /**
     * Liefert die Summen je Funktion und Quelle für einen Zeitraum.
     *
     * `gesamt` zählt NUR die Besucherabrufe (api + cache + lokal). Der
     * Vorlader bleibt außen vor, damit die davon abgeleitete Quote weiter
     * beantwortet, wie gut der Zwischenspeicher die Besucher bedient.
     *
     * @return array funktion => ['api' => x, 'cache' => y, 'lokal' => l, 'vorlader' => v, 'gesamt' => z]
     */
    public static function summenNachFunktion(string $strVon, string $strBis): array
    {
        $arrReturn = [];

        try {
            $objRows = Database::getInstance()
                ->prepare('SELECT funktion, quelle, SUM(anzahl) AS summe FROM ' . static::$strTable . ' WHERE datum >= ? AND datum <= ? GROUP BY funktion, quelle')
                ->execute($strVon, $strBis);

            while ($objRows->next()) {
                $strFunktion = (string) $objRows->funktion;

                if (!isset($arrReturn[$strFunktion])) {
                    $arrReturn[$strFunktion] = ['api' => 0, 'cache' => 0, 'lokal' => 0, 'vorlader' => 0, 'gesamt' => 0];
                }

                $intSumme = (int) $objRows->summe;
                $strQuelle = (string) $objRows->quelle;
                $arrReturn[$strFunktion][$strQuelle] = $intSumme;

                if (self::QUELLE_VORLADER !== $strQuelle) {
                    $arrReturn[$strFunktion]['gesamt'] += $intSumme;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $arrReturn;
    }

    /**
     * Liefert den Tagesverlauf eines Zeitraums.
     *
     * @param string $strFunktion leer = alle Funktionen zusammen
     *
     * @return array datum => ['api' => x, 'cache' => y, 'lokal' => l, 'vorlader' => v]
     */
    public static function verlauf(string $strVon, string $strBis, string $strFunktion = ''): array
    {
        $arrReturn = [];

        try {
            $arrWerte = [$strVon, $strBis];
            $strWhere = 'datum >= ? AND datum <= ?';

            if ('' !== $strFunktion) {
                $strWhere .= ' AND funktion = ?';
                $arrWerte[] = $strFunktion;
            }

            $objRows = Database::getInstance()
                ->prepare('SELECT datum, quelle, SUM(anzahl) AS summe FROM ' . static::$strTable . ' WHERE ' . $strWhere . ' GROUP BY datum, quelle ORDER BY datum')
                ->execute($arrWerte);

            while ($objRows->next()) {
                $strDatum = (string) $objRows->datum;

                if (!isset($arrReturn[$strDatum])) {
                    $arrReturn[$strDatum] = ['api' => 0, 'cache' => 0, 'lokal' => 0, 'vorlader' => 0];
                }

                $arrReturn[$strDatum][(string) $objRows->quelle] = (int) $objRows->summe;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $arrReturn;
    }

    /**
     * Liefert die Summen je Kalenderwoche bzw. Monat eines Zeitraums.
     *
     * @param string $strRaster 'woche' oder 'monat'
     *
     * @return array bezeichnung => ['api' => x, 'cache' => y, 'lokal' => l, 'vorlader' => v, 'sortier' => s]
     */
    public static function summenNachRaster(string $strVon, string $strBis, string $strRaster = 'monat', string $strFunktion = ''): array
    {
        $arrReturn = [];

        // Gruppierung in SQL: Kalenderwoche nach ISO (Montag als Wochenstart)
        $strGruppe = 'woche' === $strRaster
            ? "CONCAT(YEARWEEK(datum, 3))"
            : "DATE_FORMAT(datum, '%Y%m')";

        try {
            $arrWerte = [$strVon, $strBis];
            $strWhere = 'datum >= ? AND datum <= ?';

            if ('' !== $strFunktion) {
                $strWhere .= ' AND funktion = ?';
                $arrWerte[] = $strFunktion;
            }

            $objRows = Database::getInstance()
                ->prepare('SELECT ' . $strGruppe . ' AS gruppe, quelle, SUM(anzahl) AS summe, MIN(datum) AS erster FROM ' . static::$strTable . ' WHERE ' . $strWhere . ' GROUP BY gruppe, quelle ORDER BY gruppe')
                ->execute($arrWerte);

            while ($objRows->next()) {
                $strGruppeWert = (string) $objRows->gruppe;

                if (!isset($arrReturn[$strGruppeWert])) {
                    $arrReturn[$strGruppeWert] = ['api' => 0, 'cache' => 0, 'lokal' => 0, 'vorlader' => 0, 'erster' => (string) $objRows->erster];
                }

                $arrReturn[$strGruppeWert][(string) $objRows->quelle] = (int) $objRows->summe;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $arrReturn;
    }

    /**
     * Liefert das Datum des ersten aufgezeichneten Abrufs (oder '').
     */
    public static function ersterTag(): string
    {
        try {
            $objRow = Database::getInstance()->execute('SELECT MIN(datum) AS erster FROM ' . static::$strTable);

            return $objRow->numRows ? (string) $objRow->erster : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
