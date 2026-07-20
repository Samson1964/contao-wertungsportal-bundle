<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_persons_tournaments
 * (Kindtabelle von tl_wertungsportal_persons, Turnierhistorie).
 *
 * Die Turnierdaten selbst liegen in tl_wertungsportal_tournaments und
 * werden über tournamentUuid referenziert.
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $tstamp
 * @property string $tournamentUuid
 * @property string $playerUuid
 * @property string $nuLigaPersonId
 * @property string $firstname
 * @property string $lastname
 * @property int    $birthyear
 * @property string $vkz
 * @property string $memberNo
 * @property string $clubName
 * @property int    $fideId
 * @property int    $playerNo
 * @property string $eloPlayer
 * @property int    $ratingOld
 * @property int    $indexOld
 * @property int    $ratingNew
 * @property int    $indexNew
 * @property float  $factorK
 * @property int    $averageRatingCompetitors
 * @property float  $wins
 * @property int    $numberOfGames
 * @property float  $winsExpected
 * @property int    $tournamentPerformance
 * @property string $published
 *
 * @method static WertungsportalPersonsTournamentsModel|null findById($id, array $opt = [])
 * @method static WertungsportalPersonsTournamentsModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalPersonsTournamentsModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsTournamentsModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalPersonsTournamentsModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsTournamentsModel[]|null findByPid($val, array $opt = [])
 * @method static Collection|WertungsportalPersonsTournamentsModel[]|null findByTournamentUuid($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 * @method static integer countByPid($val, array $opt = [])
 */
class WertungsportalPersonsTournamentsModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_persons_tournaments';

    /**
     * Von der API gelieferte Felder eines Spieler-DTOs.
     */
    private const DTO_STRING_FIELDS = ['playerUuid', 'nuLigaPersonId', 'firstname', 'lastname', 'vkz', 'memberNo', 'clubName'];
    private const DTO_INT_FIELDS = ['birthyear', 'fideId', 'playerNo', 'ratingOld', 'indexOld', 'ratingNew', 'indexNew', 'averageRatingCompetitors', 'numberOfGames', 'tournamentPerformance'];
    private const DTO_FLOAT_FIELDS = ['factorK', 'wins', 'winsExpected'];

    /**
     * Findet alle veröffentlichten Turniereinträge einer Person.
     *
     * @return Collection|WertungsportalPersonsTournamentsModel[]|null
     */
    public static function findPublishedByPid(int $pid, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        return static::findBy(["$t.pid=?", "$t.published='1'"], [$pid], $arrOptions);
    }

    /**
     * Legt einen Turnierhistorien-Eintrag anhand von Personen-ID und
     * Turnier-UUID an oder aktualisiert ihn. $player ist ein Spieler-DTO
     * (z. B. entries.player der Karteikarte oder ein Eintrag aus
     * evaluation.players bzw. whitePlayer/blackPlayer der Partien).
     */
    public static function upsertEntry(int $pid, string $tournamentUuid, array $player): ?self
    {
        if ('' === $tournamentUuid) {
            return null;
        }

        $t = static::$strTable;

        $model = static::findOneBy(["$t.pid=?", "$t.tournamentUuid=?"], [$pid, $tournamentUuid]);
        $isNew = null === $model;

        if ($isNew) {
            $model = new static();
            $model->pid = $pid;
            $model->tournamentUuid = $tournamentUuid;
            // Nur beim Anlegen setzen, damit ein manuell deaktivierter
            // Eintrag beim Sync nicht wieder veröffentlicht wird
            $model->published = '1';
        }

        $set = static::buildDtoSet($player);

        if (static::applyApiFields($model, $set) || $isNew) {
            $model->tstamp = time();
            $model->save();
        }

        return $model;
    }

    /**
     * Baut aus einem Spieler-DTO das typisierte Feld-Array für den Abgleich
     * (nur die von der API tatsächlich gelieferten Felder).
     */
    private static function buildDtoSet(array $player): array
    {
        $set = [];

        foreach (self::DTO_STRING_FIELDS as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (string) $player[$field];
            }
        }

        foreach (self::DTO_INT_FIELDS as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (int) $player[$field];
            }
        }

        foreach (self::DTO_FLOAT_FIELDS as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (float) $player[$field];
            }
        }

        if (\array_key_exists('eloPlayer', $player)) {
            $set['eloPlayer'] = !empty($player['eloPlayer']) ? '1' : '';
        }

        return $set;
    }

    /**
     * Bulk-Variante für EIN Turnier über viele Personen: Gleicht die
     * Historien-Einträge (pid + tournamentUuid) für alle Spieler-DTOs in
     * einem Rutsch ab. $arrPersonIds bildet nuLigaPersonId => Personen-ID ab
     * (z. B. aus WertungsportalPersonsModel::syncFromPlayerDtos). Es wird
     * nichts gelöscht (die Quellen sind unvollständige Ausschnitte).
     */
    public static function syncEntries(string $tournamentUuid, array $arrPlayers, array $arrPersonIds): void
    {
        if ('' === $tournamentUuid || !$arrPlayers || !$arrPersonIds) {
            return;
        }

        // Eindeutige Einträge nach Personen-ID einsammeln
        $arrByPid = [];

        foreach ($arrPlayers as $arrPlayer) {
            if (!\is_array($arrPlayer) || empty($arrPlayer['nuLigaPersonId'])) {
                continue;
            }

            $intPid = $arrPersonIds[(string) $arrPlayer['nuLigaPersonId']] ?? 0;

            if ($intPid > 0 && !isset($arrByPid[$intPid])) {
                $arrByPid[$intPid] = $arrPlayer;
            }
        }

        if (!$arrByPid) {
            return;
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand für dieses Turnier blockweise laden (pid => Datensatz)
        $arrExisting = [];
        $strFields = implode(', ', array_merge(self::DTO_STRING_FIELDS, self::DTO_INT_FIELDS, self::DTO_FLOAT_FIELDS));

        foreach (array_chunk(array_keys($arrByPid), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, pid, eloPlayer, ' . $strFields . ' FROM ' . static::$strTable . ' WHERE tournamentUuid=? AND pid IN (' . $strPlaceholders . ')')
                                   ->execute(array_merge([$tournamentUuid], $arrChunk));

            while ($objRows->next()) {
                $arrExisting[(int) $objRows->pid] = $objRows->row();
            }
        }

        $arrInsert = [];

        foreach ($arrByPid as $intPid => $arrPlayer) {
            $arrSet = static::buildDtoSet($arrPlayer);

            if (!isset($arrExisting[$intPid])) {
                $arrRow = [$intPid, $intTime, $tournamentUuid];

                foreach (self::DTO_STRING_FIELDS as $strField) {
                    $arrRow[] = (string) ($arrSet[$strField] ?? '');
                }

                foreach (self::DTO_INT_FIELDS as $strField) {
                    $arrRow[] = (int) ($arrSet[$strField] ?? 0);
                }

                foreach (self::DTO_FLOAT_FIELDS as $strField) {
                    $arrRow[] = (float) ($arrSet[$strField] ?? 0);
                }

                $arrRow[] = (string) ($arrSet['eloPlayer'] ?? '');
                $arrRow[] = '1';
                $arrInsert[] = $arrRow;
                continue;
            }

            $arrSet = static::diffApiFields($arrExisting[$intPid], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$intPid]['id']);
            }
        }

        // Neue Einträge blockweise anlegen
        $strColumns = 'pid, tstamp, tournamentUuid, ' . $strFields . ', eloPlayer, published';
        $strTuple = '(' . implode(', ', array_fill(0, \count(self::DTO_STRING_FIELDS) + \count(self::DTO_INT_FIELDS) + \count(self::DTO_FLOAT_FIELDS) + 5, '?')) . ')';

        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }
    }

    /**
     * Gleicht die Turnierhistorie einer Person mit dem API-Array "entries"
     * der Abfrage /dwz/persons/{id}/history ab.
     *
     * Pro Eintrag wird das Unter-Array "tournament" redundanzfrei über
     * WertungsportalTournamentsModel::upsertByUuid() in die zentrale
     * Turniertabelle übernommen; hier wird nur die tournamentUuid sowie
     * das komplette Unter-Array "player" gespeichert. Nicht mehr gemeldete
     * Einträge werden gelöscht.
     */
    public static function syncForPerson(int $pid, array $entries): void
    {
        $arrUuids = [];

        foreach ($entries as $entry) {
            if (empty($entry['tournament']['uuid'])) {
                continue;
            }

            // Turnier zentral ablegen bzw. aktualisieren
            WertungsportalTournamentsModel::upsertByUuid($entry['tournament']);

            $uuid = (string) $entry['tournament']['uuid'];
            $arrUuids[] = $uuid;

            static::upsertEntry($pid, $uuid, \is_array($entry['player'] ?? null) ? $entry['player'] : []);
        }

        // Nicht mehr gemeldete Turniereinträge entfernen
        $collection = static::findBy('pid', $pid);

        if (null !== $collection) {
            foreach ($collection as $item) {
                if (!\in_array($item->tournamentUuid, $arrUuids, true)) {
                    $item->delete();
                }
            }
        }
    }
}
