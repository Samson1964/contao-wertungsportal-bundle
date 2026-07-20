<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_tournaments.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $uuid
 * @property string $label
 * @property string $vkz
 * @property string $startdate
 * @property string $enddate
 * @property string $processingState
 * @property string $ratingState
 * @property string $referentFirstname
 * @property string $referentLastname
 * @property string $referentEmail
 * @property string $additionalReferentFirstname
 * @property string $additionalReferentLastname
 * @property string $additionalReferentEmail
 * @property string $location
 * @property string $url
 * @property string $lastCalculated
 * @property int    $rounds
 * @property int    $playerCount
 * @property int    $matchCount
 * @property string $published
 *
 * @method static WertungsportalTournamentsModel|null findById($id, array $opt = [])
 * @method static WertungsportalTournamentsModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalTournamentsModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalTournamentsModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsModel[]|null findByVkz($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 */
class WertungsportalTournamentsModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_tournaments';

    /**
     * Von der API gelieferte Felder eines DSBTournamentDTO.
     */
    private const API_STRING_FIELDS = [
        'label', 'vkz', 'startdate', 'enddate', 'processingState', 'ratingState',
        'referentFirstname', 'referentLastname', 'referentEmail',
        'additionalReferentFirstname', 'additionalReferentLastname', 'additionalReferentEmail',
        'location', 'url', 'lastCalculated',
    ];

    private const API_INT_FIELDS = ['rounds', 'playerCount', 'matchCount'];

    /**
     * Findet ein Turnier anhand seiner UUID.
     */
    public static function findByUuid(string $uuid, array $arrOptions = []): ?self
    {
        return static::findOneBy('uuid', $uuid, $arrOptions);
    }

    /**
     * Findet alle veröffentlichten Turniere.
     *
     * @return Collection|WertungsportalTournamentsModel[]|null
     */
    public static function findPublished(array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.startdate DESC";
        }

        return static::findBy(["$t.published='1'"], null, $arrOptions);
    }

    /**
     * Legt ein Turnier anhand seiner UUID an oder aktualisiert es –
     * praktisch für den Import aus der Wertungsportal-API.
     *
     * $data enthält die Felder aus dem Unter-Array "entries.tournament"
     * der Karteikarten-Abfrage /dwz/persons/{id}/history.
     */
    public static function upsertByUuid(array $data): self
    {
        $model = static::findByUuid((string) ($data['uuid'] ?? ''));
        $isNew = null === $model;

        if ($isNew) {
            $model = new static();
            $model->uuid = (string) ($data['uuid'] ?? '');
            // Nur beim Anlegen setzen, damit ein manuell deaktiviertes
            // Turnier beim Sync nicht wieder veröffentlicht wird
            $model->published = '1';
        }

        $set = [];

        foreach (self::API_STRING_FIELDS as $field) {
            if (\array_key_exists($field, $data)) {
                $set[$field] = (string) $data[$field];
            }
        }

        foreach (self::API_INT_FIELDS as $field) {
            if (\array_key_exists($field, $data)) {
                $set[$field] = (int) $data[$field];
            }
        }

        if (static::applyApiFields($model, $set) || $isNew) {
            $model->tstamp = time();
            $model->save();
        }

        return $model;
    }

    /**
     * Gleicht eine Turnierliste aus der API in einem Rutsch ab: Der Bestand
     * wird per UUID-Liste mit wenigen Abfragen geladen, neue Turniere werden
     * gesammelt per Batch-INSERT angelegt und bestehende nur bei
     * tatsächlichen Änderungen aktualisiert. Der Veröffentlichungsstatus
     * bestehender Datensätze bleibt unberührt.
     */
    public static function syncList(array $arrTournaments): void
    {
        $arrUuids = [];

        foreach ($arrTournaments as $arrTournament) {
            if (\is_array($arrTournament) && !empty($arrTournament['uuid'])) {
                $arrUuids[] = (string) $arrTournament['uuid'];
            }
        }

        if (!$arrUuids) {
            return;
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand blockweise laden (UUID => Datensatz)
        $arrExisting = [];

        foreach (array_chunk(array_unique($arrUuids), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, uuid, ' . implode(', ', self::API_STRING_FIELDS) . ', ' . implode(', ', self::API_INT_FIELDS) . ' FROM ' . static::$strTable . ' WHERE uuid IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $arrExisting[(string) $objRows->uuid] = $objRows->row();
            }
        }

        $arrInsert = [];
        $arrSeen = [];

        foreach ($arrTournaments as $arrTournament) {
            if (!\is_array($arrTournament) || empty($arrTournament['uuid'])) {
                continue;
            }

            $strUuid = (string) $arrTournament['uuid'];

            // Doppelte UUID innerhalb einer API-Antwort ignorieren
            if (isset($arrSeen[$strUuid])) {
                continue;
            }

            $arrSeen[$strUuid] = true;

            if (!isset($arrExisting[$strUuid])) {
                $arrRow = [$intTime, $strUuid];

                foreach (self::API_STRING_FIELDS as $strField) {
                    $arrRow[] = (string) ($arrTournament[$strField] ?? '');
                }

                foreach (self::API_INT_FIELDS as $strField) {
                    $arrRow[] = (int) ($arrTournament[$strField] ?? 0);
                }

                $arrRow[] = '1';
                $arrInsert[] = $arrRow;
                continue;
            }

            $arrRow = $arrExisting[$strUuid];
            $arrSet = [];

            foreach (self::API_STRING_FIELDS as $strField) {
                if (\array_key_exists($strField, $arrTournament) && (string) $arrRow[$strField] !== (string) $arrTournament[$strField]) {
                    $arrSet[$strField] = (string) $arrTournament[$strField];
                }
            }

            foreach (self::API_INT_FIELDS as $strField) {
                if (\array_key_exists($strField, $arrTournament) && (int) $arrRow[$strField] !== (int) $arrTournament[$strField]) {
                    $arrSet[$strField] = (int) $arrTournament[$strField];
                }
            }

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrRow['id']);
            }
        }

        // Neue Turniere blockweise anlegen
        $strColumns = 'tstamp, uuid, ' . implode(', ', self::API_STRING_FIELDS) . ', ' . implode(', ', self::API_INT_FIELDS) . ', published';
        $strTuple = '(' . implode(', ', array_fill(0, \count(self::API_STRING_FIELDS) + \count(self::API_INT_FIELDS) + 3, '?')) . ')';

        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }
    }
}
