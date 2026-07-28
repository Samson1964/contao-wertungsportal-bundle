<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_persons_upgrades
 * (Kindtabelle von tl_wertungsportal_persons, DWZ-Hochstufungen).
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $tstamp
 * @property string $referenceDate
 * @property string $name
 * @property int    $ratingOld
 * @property int    $indexOld
 * @property int    $ratingNew
 * @property int    $indexNew
 * @property string $published
 *
 * @method static WertungsportalPersonsUpgradesModel|null findById($id, array $opt = [])
 * @method static WertungsportalPersonsUpgradesModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalPersonsUpgradesModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsUpgradesModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalPersonsUpgradesModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsUpgradesModel[]|null findByPid($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 * @method static integer countByPid($val, array $opt = [])
 */
class WertungsportalPersonsUpgradesModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_persons_upgrades';

    /**
     * Findet alle veröffentlichten Hochstufungen einer Person.
     *
     * @return Collection|WertungsportalPersonsUpgradesModel[]|null
     */
    public static function findPublishedByPid(int $pid, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.referenceDate DESC";
        }

        return static::findBy(["$t.pid=?", "$t.published='1'"], [$pid], $arrOptions);
    }

    /**
     * Gleicht die DWZ-Hochstufungen einer Person mit dem API-Array
     * "upgrades" der Abfrage /dwz/persons/{id}/history ab. Vorhandene
     * Einträge (per Stichtag und Name) werden aktualisiert, neue angelegt.
     * Es wird nichts gelöscht.
     *
     * Läuft als Bulk (eine Bestandsabfrage, Batch-INSERT) statt mit einer
     * Einzelabfrage je Hochstufung.
     */
    public static function syncForPerson(int $pid, array $upgrades): void
    {
        if (!$upgrades) {
            return;
        }

        $arrFields = ['ratingOld', 'indexOld', 'ratingNew', 'indexNew'];
        $arrReported = [];

        foreach ($upgrades as $upgrade) {
            $referenceDate = (string) ($upgrade['referenceDate'] ?? '');
            $name          = (string) ($upgrade['name'] ?? '');

            if ('' === $referenceDate && '' === $name) {
                continue;
            }

            $arrSet = [];

            foreach ($arrFields as $field) {
                if (\array_key_exists($field, $upgrade)) {
                    $arrSet[$field] = (int) $upgrade[$field];
                }
            }

            $arrReported[$referenceDate . '|' . $name] = ['referenceDate' => $referenceDate, 'name' => $name, 'set' => $arrSet];
        }

        if (!$arrReported) {
            return;
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand der Person in einer Abfrage laden
        $arrExisting = [];
        $objRows = $objDatabase->prepare('SELECT id, referenceDate, name, ' . implode(', ', $arrFields) . ' FROM ' . static::$strTable . ' WHERE pid=?')
                               ->execute($pid);

        while ($objRows->next()) {
            $arrExisting[$objRows->referenceDate . '|' . $objRows->name] = $objRows->row();
        }

        $arrInsert = [];

        foreach ($arrReported as $strKey => $arrItem) {
            if (!isset($arrExisting[$strKey])) {
                $arrRow = [$pid, $intTime, $arrItem['referenceDate'], $arrItem['name'], '1'];

                foreach ($arrFields as $field) {
                    $arrRow[] = (int) ($arrItem['set'][$field] ?? 0);
                }

                $arrInsert[] = $arrRow;
                continue;
            }

            $arrDiff = static::diffApiFields($arrExisting[$strKey], $arrItem['set']);

            if ($arrDiff) {
                $arrDiff['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrDiff)
                            ->execute($arrExisting[$strKey]['id']);
            }
        }

        // Neue Hochstufungen blockweise anlegen
        if ($arrInsert) {
            $strColumns = 'pid, tstamp, referenceDate, name, published, ' . implode(', ', $arrFields);
            $strTuple = '(' . implode(', ', array_fill(0, \count($arrInsert[0]), '?')) . ')';

            foreach (array_chunk($arrInsert, 100) as $arrChunk) {
                $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

                $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                            ->execute(array_merge(...$arrChunk));
            }
        }
    }
}
