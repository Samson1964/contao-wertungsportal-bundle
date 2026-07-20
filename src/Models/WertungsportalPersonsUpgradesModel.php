<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

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
     * Einträge (per Stichtag und Name) werden aktualisiert, neue angelegt
     * und nicht mehr gemeldete gelöscht.
     */
    public static function syncForPerson(int $pid, array $upgrades): void
    {
        $arrKeys = [];
        $t = static::$strTable;

        foreach ($upgrades as $upgrade) {
            $referenceDate = (string) ($upgrade['referenceDate'] ?? '');
            $name          = (string) ($upgrade['name'] ?? '');

            if ('' === $referenceDate && '' === $name) {
                continue;
            }

            $arrKeys[] = $referenceDate . '|' . $name;

            $model = static::findOneBy(["$t.pid=?", "$t.referenceDate=?", "$t.name=?"], [$pid, $referenceDate, $name]);
            $isNew = null === $model;

            if ($isNew) {
                $model = new static();
                $model->pid = $pid;
                $model->referenceDate = $referenceDate;
                $model->name = $name;
                // Nur beim Anlegen setzen, damit eine manuell deaktivierte
                // Hochstufung beim Sync nicht wieder veröffentlicht wird
                $model->published = '1';
            }

            $set = [];

            foreach (['ratingOld', 'indexOld', 'ratingNew', 'indexNew'] as $field) {
                if (\array_key_exists($field, $upgrade)) {
                    $set[$field] = (int) $upgrade[$field];
                }
            }

            if (static::applyApiFields($model, $set) || $isNew) {
                $model->tstamp = time();
                $model->save();
            }
        }

        // Nicht mehr gemeldete Hochstufungen entfernen
        $collection = static::findBy('pid', $pid);

        if (null !== $collection) {
            foreach ($collection as $item) {
                if (!\in_array($item->referenceDate . '|' . $item->name, $arrKeys, true)) {
                    $item->delete();
                }
            }
        }
    }
}
