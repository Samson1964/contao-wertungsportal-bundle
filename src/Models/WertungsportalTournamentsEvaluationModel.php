<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_tournaments_evaluation
 * (Kindtabelle von tl_wertungsportal_tournaments, DWZ-Auswertung).
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $tstamp
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
 * @method static WertungsportalTournamentsEvaluationModel|null findById($id, array $opt = [])
 * @method static WertungsportalTournamentsEvaluationModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalTournamentsEvaluationModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsEvaluationModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalTournamentsEvaluationModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsEvaluationModel[]|null findByPid($val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsEvaluationModel[]|null findByPlayerUuid($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 * @method static integer countByPid($val, array $opt = [])
 */
class WertungsportalTournamentsEvaluationModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_tournaments_evaluation';

    /**
     * Von der API gelieferte Felder eines Spieler-DTOs (playerUuid ist der
     * Schlüssel innerhalb des Turniers und steht deshalb nicht in der Liste).
     */
    private const DTO_STRING_FIELDS = ['nuLigaPersonId', 'firstname', 'lastname', 'vkz', 'memberNo', 'clubName'];
    private const DTO_INT_FIELDS = ['birthyear', 'fideId', 'playerNo', 'ratingOld', 'indexOld', 'ratingNew', 'indexNew', 'averageRatingCompetitors', 'numberOfGames', 'tournamentPerformance'];
    private const DTO_FLOAT_FIELDS = ['factorK', 'wins', 'winsExpected'];

    /**
     * Findet einen Auswertungseintrag anhand von Turnier-ID und Spieler-UUID.
     */
    public static function findByPidAndPlayerUuid(int $pid, string $playerUuid, array $arrOptions = []): ?self
    {
        $t = static::$strTable;

        return static::findOneBy(["$t.pid=?", "$t.playerUuid=?"], [$pid, $playerUuid], $arrOptions);
    }

    /**
     * Findet alle veröffentlichten Auswertungseinträge eines Turniers.
     *
     * @return Collection|WertungsportalTournamentsEvaluationModel[]|null
     */
    public static function findPublishedByPid(int $pid, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.playerNo";
        }

        return static::findBy(["$t.pid=?", "$t.published='1'"], [$pid], $arrOptions);
    }

    /**
     * Legt einen Spieler-DTO (z. B. aus players, whitePlayer oder blackPlayer)
     * anhand von Turnier-ID und Spieler-UUID an oder aktualisiert ihn.
     */
    public static function upsertPlayer(int $pid, array $player): ?self
    {
        if (empty($player['playerUuid'])) {
            return null;
        }

        // Person systemweit anhand der nuLigaPersonId in
        // tl_wertungsportal_persons anlegen bzw. aktualisieren
        $person = WertungsportalPersonsModel::upsertFromPlayerDto($player);

        // Turnierhistorie der Person (tl_wertungsportal_persons_tournaments)
        // mitführen; die Turnier-UUID liefert der Elterndatensatz
        if (null !== $person) {
            $tournament = WertungsportalTournamentsModel::findByPk($pid);

            if (null !== $tournament && $tournament->uuid) {
                WertungsportalPersonsTournamentsModel::upsertEntry((int) $person->id, (string) $tournament->uuid, $player);
            }
        }

        $model = static::findByPidAndPlayerUuid($pid, (string) $player['playerUuid']);
        $isNew = null === $model;

        if ($isNew) {
            $model = new static();
            $model->pid = $pid;
            $model->playerUuid = (string) $player['playerUuid'];
            // Nur beim Anlegen setzen, damit ein manuell deaktivierter
            // Eintrag beim Sync nicht wieder veröffentlicht wird
            $model->published = '1';
        }

        $set = [];

        foreach (['nuLigaPersonId', 'firstname', 'lastname', 'vkz', 'memberNo', 'clubName'] as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (string) $player[$field];
            }
        }

        foreach (['birthyear', 'fideId', 'playerNo', 'ratingOld', 'indexOld', 'ratingNew', 'indexNew', 'averageRatingCompetitors', 'numberOfGames', 'tournamentPerformance'] as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (int) $player[$field];
            }
        }

        foreach (['factorK', 'wins', 'winsExpected'] as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (float) $player[$field];
            }
        }

        if (\array_key_exists('eloPlayer', $player)) {
            $set['eloPlayer'] = !empty($player['eloPlayer']) ? '1' : '';
        }

        if (static::applyApiFields($model, $set) || $isNew) {
            $model->tstamp = time();
            $model->save();
        }

        return $model;
    }

    /**
     * Gleicht die komplette DWZ-Auswertung eines Turniers mit dem API-Array
     * "players" der Abfrage /dwz/tournaments/{uuid}/evaluation ab. Vorhandene
     * Einträge (per Spieler-UUID) werden aktualisiert, neue angelegt und
     * nicht mehr gemeldete gelöscht.
     */
    public static function syncForTournament(int $pid, array $players, string $tournamentUuid = ''): void
    {
        static::syncPlayers($pid, $tournamentUuid, $players, true);
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
     * Bulk-Abgleich der Spieler-DTOs eines Turniers: legt die Personen
     * systemweit an (WertungsportalPersonsModel::syncFromPlayerDtos), führt
     * die Turnierhistorie der Personen mit (syncEntries) und gleicht die
     * Auswertungstabelle in einem Rutsch ab. Mit $blnDelete=true werden
     * nicht mehr gemeldete Einträge entfernt (nur für die vollständige
     * Auswertung — Partien/Scoresheet sind unvollständige Ausschnitte).
     * Ist $tournamentUuid leer, wird sie einmalig über das Turnier geladen.
     */
    public static function syncPlayers(int $pid, string $tournamentUuid, array $players, bool $blnDelete = false): void
    {
        // Eindeutige Spieler nach playerUuid einsammeln
        $arrByUuid = [];

        foreach ($players as $player) {
            if (!\is_array($player) || empty($player['playerUuid'])) {
                continue;
            }

            $strUuid = (string) $player['playerUuid'];

            if (!isset($arrByUuid[$strUuid])) {
                $arrByUuid[$strUuid] = $player;
            }
        }

        if (!$arrByUuid && !$blnDelete) {
            return;
        }

        // Turnier-UUID für die Historien-Einträge ggf. nachladen
        if ('' === $tournamentUuid) {
            $tournament = WertungsportalTournamentsModel::findByPk($pid);
            $tournamentUuid = null !== $tournament ? (string) $tournament->uuid : '';
        }

        // Personen systemweit anlegen bzw. aktualisieren (nur Identitätsfelder)
        $arrPersonIds = WertungsportalPersonsModel::syncFromPlayerDtos($arrByUuid);

        // Turnierhistorie der Personen mitführen
        if ('' !== $tournamentUuid) {
            WertungsportalPersonsTournamentsModel::syncEntries($tournamentUuid, $arrByUuid, $arrPersonIds);
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand der Auswertung laden (playerUuid => Datensatz)
        $arrExisting = [];
        $strFields = implode(', ', array_merge(self::DTO_STRING_FIELDS, self::DTO_INT_FIELDS, self::DTO_FLOAT_FIELDS));
        $objRows = $objDatabase->prepare('SELECT id, playerUuid, eloPlayer, ' . $strFields . ' FROM ' . static::$strTable . ' WHERE pid=?')
                               ->execute($pid);

        while ($objRows->next()) {
            $arrExisting[(string) $objRows->playerUuid] = $objRows->row();
        }

        $arrInsert = [];

        foreach ($arrByUuid as $strUuid => $player) {
            $arrSet = static::buildDtoSet($player);

            if (!isset($arrExisting[$strUuid])) {
                $arrRow = [$pid, $intTime, $strUuid];

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

            $arrSet = static::diffApiFields($arrExisting[$strUuid], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$strUuid]['id']);
            }
        }

        // Neue Auswertungseinträge blockweise anlegen
        $strColumns = 'pid, tstamp, playerUuid, ' . $strFields . ', eloPlayer, published';
        $strTuple = '(' . implode(', ', array_fill(0, \count(self::DTO_STRING_FIELDS) + \count(self::DTO_INT_FIELDS) + \count(self::DTO_FLOAT_FIELDS) + 5, '?')) . ')';

        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        // Nicht mehr gemeldete Auswertungseinträge entfernen
        if ($blnDelete) {
            $arrDelete = [];

            foreach ($arrExisting as $strUuid => $arrRow) {
                if (!isset($arrByUuid[$strUuid])) {
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
}
