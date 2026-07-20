<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_clubs.
 *
 * Neben den hier definierten Findern stehen alle Standardmethoden
 * von Contao\Model zur Verfügung (findByPk, findAll, findBy, findOneBy,
 * countAll, countBy, save, delete usw.) sowie die magischen Finder
 * wie findByClubName() oder findOneByClubVkz().
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $clubVkz
 * @property string $clubName
 * @property string $federation
 * @property string $parentFederation
 * @property string $state
 * @property string $published
 *
 * @method static WertungsportalClubsModel|null findById($id, array $opt = [])
 * @method static WertungsportalClubsModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalClubsModel|null findOneBy($col, $val, array $opt = [])
 * @method static WertungsportalClubsModel|null findOneByClubVkz($val, array $opt = [])
 * @method static WertungsportalClubsModel|null findOneByClubName($val, array $opt = [])
 * @method static Collection|WertungsportalClubsModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalClubsModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalClubsModel[]|null findByFederation($val, array $opt = [])
 * @method static Collection|WertungsportalClubsModel[]|null findByParentFederation($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 * @method static integer countByFederation($val, array $opt = [])
 */
class WertungsportalClubsModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_clubs';

    /**
     * Findet einen Verein anhand seiner Vereinskennziffer (VKZ).
     */
    public static function findByVkz(string $vkz, array $arrOptions = []): ?self
    {
        return static::findOneBy('clubVkz', $vkz, $arrOptions);
    }

    /**
     * Findet alle veröffentlichten Vereine.
     *
     * @return Collection|WertungsportalClubsModel[]|null
     */
    public static function findPublished(array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.clubName";
        }

        return static::findBy(["$t.published='1'"], null, $arrOptions);
    }

    /**
     * Findet alle veröffentlichten Vereine eines Verbands.
     *
     * @return Collection|WertungsportalClubsModel[]|null
     */
    public static function findPublishedByFederation(string $federation, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.clubName";
        }

        return static::findBy(
            ["$t.published='1'", "$t.federation=?"],
            [$federation],
            $arrOptions
        );
    }

    /**
     * Findet alle aktiven (nicht gelöschten) Vereine.
     *
     * @return Collection|WertungsportalClubsModel[]|null
     */
    public static function findActive(array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.clubName";
        }

        return static::findBy(["$t.state!='DELETE_STATE_TRUE'"], null, $arrOptions);
    }

    /**
     * Sucht Vereine per LIKE im Vereinsnamen.
     *
     * @return Collection|WertungsportalClubsModel[]|null
     */
    public static function searchByName(string $term, bool $onlyPublished = true, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        $arrColumns = ["$t.clubName LIKE ?"];
        $arrValues  = ['%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%'];

        if ($onlyPublished) {
            $arrColumns[] = "$t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.clubName";
        }

        return static::findBy($arrColumns, $arrValues, $arrOptions);
    }

    /**
     * Legt einen Verein anhand seiner VKZ an oder aktualisiert ihn –
     * praktisch für den Import aus der Wertungsportal-API.
     *
     * $data enthält die Felder aus dem API-Array "data", z. B.:
     * ['clubVkz' => 'C0327', 'clubName' => '…', 'federation' => '…', 'parentFederation' => '…', 'state' => 'DELETE_STATE_FALSE']
     */
    public static function upsertByVkz(array $data): self
    {
        $model = static::findByVkz($data['clubVkz'] ?? '');
        $isNew = null === $model;

        if ($isNew) {
            $model = new static();
            $model->clubVkz = (string) ($data['clubVkz'] ?? '');
            // Nur beim Anlegen setzen, damit ein manuell deaktivierter
            // Verein beim Sync nicht wieder veröffentlicht wird
            $model->published = '1';
        }

        $set = [];

        foreach (['clubName', 'federation', 'parentFederation', 'state'] as $field) {
            if (\array_key_exists($field, $data)) {
                $set[$field] = (string) $data[$field];
            }
        }

        if (static::applyApiFields($model, $set) || $isNew) {
            $model->tstamp = time();
            $model->save();
        }

        return $model;
    }

    /**
     * Gleicht eine komplette Vereinsliste aus der API in einem Rutsch ab:
     * Der Bestand wird mit einer einzigen Abfrage geladen, neue Vereine
     * werden gesammelt per Batch-INSERT angelegt und bestehende nur bei
     * tatsächlichen Änderungen aktualisiert. Der Veröffentlichungsstatus
     * bestehender Datensätze bleibt unberührt.
     */
    public static function syncList(array $arrClubs): void
    {
        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand einmalig laden (VKZ => Datensatz)
        $arrExisting = [];
        $objRows = $objDatabase->execute('SELECT id, clubVkz, clubName, federation, parentFederation, state FROM ' . static::$strTable);

        while ($objRows->next()) {
            $arrExisting[(string) $objRows->clubVkz] = $objRows->row();
        }

        $arrInsert = [];
        $arrSeen = [];

        foreach ($arrClubs as $arrClub) {
            if (!\is_array($arrClub) || empty($arrClub['clubVkz'])) {
                continue;
            }

            $strVkz = (string) $arrClub['clubVkz'];

            // Doppelte VKZ innerhalb einer API-Antwort ignorieren
            if (isset($arrSeen[$strVkz])) {
                continue;
            }

            $arrSeen[$strVkz] = true;

            if (!isset($arrExisting[$strVkz])) {
                $arrInsert[] = [
                    $intTime,
                    $strVkz,
                    (string) ($arrClub['clubName'] ?? ''),
                    (string) ($arrClub['federation'] ?? ''),
                    (string) ($arrClub['parentFederation'] ?? ''),
                    (string) ($arrClub['state'] ?? ''),
                    '1',
                ];
                continue;
            }

            $arrRow = $arrExisting[$strVkz];
            $arrSet = [];

            foreach (['clubName', 'federation', 'parentFederation', 'state'] as $strField) {
                if (\array_key_exists($strField, $arrClub) && (string) $arrRow[$strField] !== (string) $arrClub[$strField]) {
                    $arrSet[$strField] = (string) $arrClub[$strField];
                }
            }

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrRow['id']);
            }
        }

        // Neue Vereine blockweise anlegen
        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), '(?, ?, ?, ?, ?, ?, ?)'));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (tstamp, clubVkz, clubName, federation, parentFederation, state, published) VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }
    }
}
