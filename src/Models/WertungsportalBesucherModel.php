<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_besucher.
 *
 * Zählt die Wertungsportal-Abrufe je IP-Adresse in drei laufenden Fenstern
 * (Minute, Stunde, Tag) — je Adresse EIN Datensatz, der fortgeschrieben wird.
 *
 * **Bewusst keine Einzelaufzeichnung:** Eine Zeile je Abruf wäre eine
 * vollständige Zugriffshistorie mit IP-Adressen und damit ein
 * Datenschutzproblem, das die Bremse gar nicht braucht. Gespeichert wird nur,
 * wie viele Abrufe im laufenden Fenster kamen; mit dem Fensterwechsel ist der
 * alte Wert weg. Wer tatsächlich gebremst wurde, steht dagegen in
 * tl_wertungsportal_sperren — dort ist die Aufzeichnung der Zweck.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $ip
 * @property int    $minuteStart   Beginn des laufenden Minutenfensters
 * @property int    $minuteAnzahl
 * @property int    $stundeStart
 * @property int    $stundeAnzahl
 * @property int    $tagStart
 * @property int    $tagAnzahl
 *
 * @method static WertungsportalBesucherModel|null findById($id, array $opt = [])
 * @method static WertungsportalBesucherModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalBesucherModel[]|null findAll(array $opt = [])
 */
class WertungsportalBesucherModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_besucher';

    /**
     * Länge der drei Fenster in Sekunden.
     */
    public const FENSTER = [
        'minute' => 60,
        'stunde' => 3600,
        'tag' => 86400,
    ];

    /**
     * Zählt einen Abruf der Adresse und liefert die Stände aller drei Fenster.
     *
     * Ein Fenster, dessen Länge abgelaufen ist, beginnt von vorn — es wird
     * also nicht rückwirkend über die letzten 60 Sekunden gezählt, sondern in
     * festen Abschnitten ab dem ersten Abruf. Das ist ungenauer als ein
     * gleitendes Fenster, kostet aber genau eine Zeile je Adresse statt einer
     * je Abruf.
     *
     * Fehler werden verschluckt: Eine fehlende Tabelle (vor contao:migrate)
     * darf das Frontend nicht lahmlegen — dann gibt es eben keine Bremse.
     *
     * @param string $strIp Adresse des Besuchers
     *
     * @return array minute|stunde|tag => Zahl der Abrufe im laufenden Fenster
     */
    public static function zaehle(string $strIp): array
    {
        $arrStand = ['minute' => 0, 'stunde' => 0, 'tag' => 0];

        if ('' === $strIp) {
            return $arrStand;
        }

        $intJetzt = time();

        try {
            $objRow = Database::getInstance()
                ->prepare('SELECT * FROM ' . static::$strTable . ' WHERE ip = ?')
                ->execute($strIp);

            if (!$objRow->numRows) {
                Database::getInstance()
                    ->prepare('INSERT INTO ' . static::$strTable . ' (tstamp, ip, minuteStart, minuteAnzahl, stundeStart, stundeAnzahl, tagStart, tagAnzahl) VALUES (?, ?, ?, 1, ?, 1, ?, 1)')
                    ->execute($intJetzt, $strIp, $intJetzt, $intJetzt, $intJetzt);

                return ['minute' => 1, 'stunde' => 1, 'tag' => 1];
            }

            $arrWerte = [];

            foreach (self::FENSTER as $strName => $intLaenge) {
                $intStart = (int) $objRow->{$strName . 'Start'};
                $intAnzahl = (int) $objRow->{$strName . 'Anzahl'};

                if ($intJetzt >= $intStart + $intLaenge) {
                    $intStart = $intJetzt;
                    $intAnzahl = 1;
                } else {
                    ++$intAnzahl;
                }

                $arrWerte[$strName . 'Start'] = $intStart;
                $arrWerte[$strName . 'Anzahl'] = $intAnzahl;
                $arrStand[$strName] = $intAnzahl;
            }

            Database::getInstance()
                ->prepare('UPDATE ' . static::$strTable . ' SET tstamp = ?, minuteStart = ?, minuteAnzahl = ?, stundeStart = ?, stundeAnzahl = ?, tagStart = ?, tagAnzahl = ? WHERE id = ?')
                ->execute(
                    $intJetzt,
                    $arrWerte['minuteStart'], $arrWerte['minuteAnzahl'],
                    $arrWerte['stundeStart'], $arrWerte['stundeAnzahl'],
                    $arrWerte['tagStart'], $arrWerte['tagAnzahl'],
                    (int) $objRow->id
                );
        } catch (\Throwable $e) {
            return ['minute' => 0, 'stunde' => 0, 'tag' => 0];
        }

        return $arrStand;
    }

    /**
     * Räumt Adressen weg, die seit einem Tag nichts mehr abgerufen haben.
     *
     * Ohne das wüchse die Tabelle mit jeder je gesehenen Adresse. Aufgerufen
     * wird das vom nächtlichen Cronjob, nicht bei jedem Seitenaufruf.
     *
     * @return int Zahl der gelöschten Zeilen
     */
    public static function aufraeumen(): int
    {
        try {
            $objResult = Database::getInstance()
                ->prepare('DELETE FROM ' . static::$strTable . ' WHERE tstamp < ?')
                ->execute(time() - 86400);

            return $objResult->affectedRows;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
