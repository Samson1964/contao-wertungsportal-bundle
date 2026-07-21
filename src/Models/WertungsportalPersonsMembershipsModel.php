<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_persons_memberships
 * (Kindtabelle von tl_wertungsportal_persons).
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $tstamp
 * @property string $vkz
 * @property string $memberNo
 * @property string $clubName
 * @property string $licenceState
 * @property string $regionName
 * @property string $federationName
 * @property string $published
 *
 * @method static WertungsportalPersonsMembershipsModel|null findById($id, array $opt = [])
 * @method static WertungsportalPersonsMembershipsModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalPersonsMembershipsModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsMembershipsModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalPersonsMembershipsModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsMembershipsModel[]|null findByPid($val, array $opt = [])
 * @method static Collection|WertungsportalPersonsMembershipsModel[]|null findByVkz($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 * @method static integer countByPid($val, array $opt = [])
 */
class WertungsportalPersonsMembershipsModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_persons_memberships';

    /**
     * Findet alle veröffentlichten Mitgliedschaften einer Person.
     *
     * @return Collection|WertungsportalPersonsMembershipsModel[]|null
     */
    public static function findPublishedByPid(int $pid, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.clubName";
        }

        return static::findBy(["$t.pid=?", "$t.published='1'"], [$pid], $arrOptions);
    }

    /**
     * Gleicht die Mitgliedschaften einer Person mit dem API-Array
     * "memberships" ab: Vorhandene Einträge (per VKZ) werden aktualisiert,
     * neue angelegt und nicht mehr gemeldete gelöscht.
     */
    public static function syncForPerson(int $pid, array $memberships): void
    {
        static::syncForPersons([$pid => $memberships]);
    }

    /**
     * String-Felder des Vereinsmitglieder-CSV-Imports (DB-Namen).
     */
    public const CSV_FIELDS = ['clubName', 'licenceState', 'regionName', 'federationName', 'spielgenehmigungVon', 'spielgenehmigungBis', 'antragstyp', 'antragszeitpunkt', 'antragsteller'];

    /**
     * Importiert Mitgliedschaften aus dem Vereinsmitglieder-CSV in einem
     * Rutsch. Schlüssel ist die Kombination VKZ + Mitgliedsnummer:
     * Bestehende Datensätze mit gleicher VKZ und Mitgliedsnummer werden
     * aktualisiert (inkl. pid-Verknüpfung zur Person), unbekannte per
     * Batch-INSERT angelegt. Es wird nichts gelöscht.
     *
     * $arrMemberships: "vkz|memberNo" => ['pid' => Personen-ID,
     *                                     'vkz' => ..., 'memberNo' => ...,
     *                                     'felder' => [CSV_FIELDS]]
     *
     * Importregel: Die CSV-Daten haben höhere Priorität und überschreiben
     * abweichende Bestandsdaten unabhängig davon, ob der vorhandene tstamp
     * jünger ist. Über $intTstamp (Datum/Uhrzeit aus dem Dateinamen) wird
     * der tstamp der geschriebenen Datensätze gesetzt; 0 = aktuelle Zeit.
     *
     * @return array ['neu' => x, 'aktualisiert' => y, 'unveraendert' => z]
     */
    public static function importCsvRows(array $arrMemberships, int $intTstamp = 0): array
    {
        $arrErgebnis = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0];

        if (!$arrMemberships) {
            return $arrErgebnis;
        }

        $objDatabase = Database::getInstance();
        $intTime = $intTstamp > 0 ? $intTstamp : time();
        $strFields = implode(', ', self::CSV_FIELDS);

        // Bestand blockweise über (vkz, memberNo)-Paare laden
        $arrExisting = [];

        foreach (array_chunk(array_values($arrMemberships), 250) as $arrChunk) {
            $arrParams = [];

            foreach ($arrChunk as $arrItem) {
                $arrParams[] = $arrItem['vkz'];
                $arrParams[] = $arrItem['memberNo'];
            }

            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '(?,?)'));
            $objRows = $objDatabase->prepare('SELECT id, pid, vkz, memberNo, ' . $strFields . ' FROM ' . static::$strTable . ' WHERE (vkz, memberNo) IN (' . $strPlaceholders . ')')
                                   ->execute($arrParams);

            while ($objRows->next()) {
                $arrExisting[$objRows->vkz . '|' . $objRows->memberNo] = $objRows->row();
            }
        }

        $arrInsert = [];

        foreach ($arrMemberships as $strKey => $arrItem) {
            if (!isset($arrExisting[$strKey])) {
                $arrRow = [(int) $arrItem['pid'], $intTime, (string) $arrItem['vkz'], (string) $arrItem['memberNo']];

                foreach (self::CSV_FIELDS as $strField) {
                    $arrRow[] = (string) ($arrItem['felder'][$strField] ?? '');
                }

                $arrRow[] = '1';
                $arrInsert[] = $arrRow;
                continue;
            }

            $arrSet = ['pid' => (int) $arrItem['pid']];

            foreach (self::CSV_FIELDS as $strField) {
                if (\array_key_exists($strField, $arrItem['felder'])) {
                    $arrSet[$strField] = (string) $arrItem['felder'][$strField];
                }
            }

            $arrSet = static::diffApiFields($arrExisting[$strKey], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$strKey]['id']);
                $arrErgebnis['aktualisiert']++;
            } else {
                $arrErgebnis['unveraendert']++;
            }
        }

        // Neue Mitgliedschaften blockweise anlegen
        $strColumns = 'pid, tstamp, vkz, memberNo, ' . $strFields . ', published';
        $strTuple = '(' . implode(', ', array_fill(0, \count(self::CSV_FIELDS) + 5, '?')) . ')';

        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        $arrErgebnis['neu'] = \count($arrInsert);

        return $arrErgebnis;
    }

    /**
     * Bulk-Variante: Gleicht die Mitgliedschaften mehrerer Personen in einem
     * Rutsch ab ($arrByPid = Personen-ID => memberships-Array der API).
     * Der Bestand wird blockweise geladen, neue Einträge werden per
     * Batch-INSERT angelegt, bestehende nur bei Änderungen aktualisiert und
     * nicht mehr gemeldete gelöscht. Die Vereine der Mitgliedschaften werden
     * gesammelt über WertungsportalClubsModel::syncList() angelegt.
     */
    public static function syncForPersons(array $arrByPid): void
    {
        if (!$arrByPid) {
            return;
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Gemeldete Mitgliedschaften einsammeln (Schlüssel pid|vkz) und die
        // zugehörigen Vereine für den gesammelten Vereins-Abgleich vormerken
        $arrReported = [];
        $arrClubs = [];

        foreach ($arrByPid as $intPid => $arrMemberships) {
            if (!\is_array($arrMemberships)) {
                continue;
            }

            foreach ($arrMemberships as $arrMembership) {
                if (!\is_array($arrMembership) || empty($arrMembership['vkz'])) {
                    continue;
                }

                $strVkz = (string) $arrMembership['vkz'];
                $strKey = $intPid . '|' . $strVkz;

                if (isset($arrReported[$strKey])) {
                    continue;
                }

                $arrReported[$strKey] = ['pid' => (int) $intPid, 'vkz' => $strVkz, 'data' => $arrMembership];

                // Verein anhand der VKZ vormerken (die Verbandsfelder bleiben
                // unberührt, da die Mitgliedschaft keine Verbands-VKZ liefert)
                $arrClub = ['clubVkz' => $strVkz];

                if (!empty($arrMembership['clubName'])) {
                    $arrClub['clubName'] = (string) $arrMembership['clubName'];
                }

                $arrClubs[] = $arrClub;
            }
        }

        if ($arrClubs) {
            WertungsportalClubsModel::syncList($arrClubs);
        }

        // Bestand der betroffenen Personen blockweise laden
        $arrExisting = [];

        foreach (array_chunk(array_keys($arrByPid), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, pid, vkz, memberNo, clubName, licenceState, regionName, federationName FROM ' . static::$strTable . ' WHERE pid IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $arrExisting[$objRows->pid . '|' . $objRows->vkz] = $objRows->row();
            }
        }

        $arrInsert = [];

        foreach ($arrReported as $strKey => $arrItem) {
            $arrMembership = $arrItem['data'];

            if (!isset($arrExisting[$strKey])) {
                $arrInsert[] = [
                    $arrItem['pid'],
                    $intTime,
                    $arrItem['vkz'],
                    (string) ($arrMembership['memberNo'] ?? ''),
                    (string) ($arrMembership['clubName'] ?? ''),
                    (string) ($arrMembership['licenceState'] ?? ''),
                    (string) ($arrMembership['regionName'] ?? ''),
                    (string) ($arrMembership['federationName'] ?? ''),
                    '1',
                ];
                continue;
            }

            $arrSet = [];

            foreach (['memberNo', 'clubName', 'licenceState', 'regionName', 'federationName'] as $strField) {
                if (\array_key_exists($strField, $arrMembership)) {
                    $arrSet[$strField] = (string) $arrMembership[$strField];
                }
            }

            $arrSet = static::diffApiFields($arrExisting[$strKey], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$strKey]['id']);
            }
        }

        // Neue Mitgliedschaften blockweise anlegen
        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?)'));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (pid, tstamp, vkz, memberNo, clubName, licenceState, regionName, federationName, published) VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        // Nicht mehr gemeldete Mitgliedschaften entfernen
        $arrDelete = [];

        foreach ($arrExisting as $strKey => $arrRow) {
            if (!isset($arrReported[$strKey])) {
                $arrDelete[] = (int) $arrRow['id'];
            }
        }

        foreach (array_chunk($arrDelete, 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objDatabase->prepare('DELETE FROM ' . static::$strTable . ' WHERE id IN (' . $strPlaceholders . ')')
                        ->execute($arrChunk);
        }
    }
}
