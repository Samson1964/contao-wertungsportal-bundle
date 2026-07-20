<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_tournaments_matches
 * (Kindtabelle von tl_wertungsportal_tournaments, Partien).
 *
 * Die Spielerdaten der Unter-Arrays whitePlayer/blackPlayer liegen
 * redundanzfrei in tl_wertungsportal_tournaments_evaluation und werden
 * über whitePlayerUuid/blackPlayerUuid referenziert.
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $tstamp
 * @property int    $round
 * @property string $result
 * @property float  $expected
 * @property string $restpartie
 * @property string $whitePlayerUuid
 * @property string $blackPlayerUuid
 * @property string $published
 *
 * @method static WertungsportalTournamentsMatchesModel|null findById($id, array $opt = [])
 * @method static WertungsportalTournamentsMatchesModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalTournamentsMatchesModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsMatchesModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalTournamentsMatchesModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTournamentsMatchesModel[]|null findByPid($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 * @method static integer countByPid($val, array $opt = [])
 */
class WertungsportalTournamentsMatchesModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_tournaments_matches';

    /**
     * Findet alle veröffentlichten Partien eines Turniers.
     *
     * @return Collection|WertungsportalTournamentsMatchesModel[]|null
     */
    public static function findPublishedByPid(int $pid, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.round";
        }

        return static::findBy(["$t.pid=?", "$t.published='1'"], [$pid], $arrOptions);
    }

    /**
     * Gleicht Partien eines Turniers mit einem API-Array von Match-DTOs ab
     * (z. B. "data" von /dwz/tournaments/{uuid}/matches oder "matches" bzw.
     * "restpartienAlt" des Scoresheets) — als Bulk-Verarbeitung: Der Bestand
     * wird mit einer Abfrage geladen, neue Partien werden per Batch-INSERT
     * angelegt, bestehende nur bei Änderungen aktualisiert.
     *
     * Die eingebetteten Spieler-DTOs whitePlayer/blackPlayer werden gesammelt
     * über WertungsportalTournamentsEvaluationModel::syncPlayers() in die
     * Auswertungstabelle (inkl. Personen und Turnierhistorie) übernommen.
     *
     * Es wird bewusst nichts gelöscht, da die Matches-Abfrage paginiert ist
     * und das Scoresheet nur die Partien eines Spielers enthält.
     */
    public static function syncForTournament(int $pid, array $matches, string $tournamentUuid = ''): void
    {
        // Spieler-DTOs und Partien einsammeln (Schlüssel round|weiß|schwarz)
        $arrPlayers = [];
        $arrReported = [];

        foreach ($matches as $match) {
            if (!\is_array($match)) {
                continue;
            }

            $whiteUuid = (string) ($match['whitePlayer']['playerUuid'] ?? '');
            $blackUuid = (string) ($match['blackPlayer']['playerUuid'] ?? '');

            if ('' === $whiteUuid && '' === $blackUuid) {
                continue;
            }

            foreach (['whitePlayer', 'blackPlayer'] as $field) {
                if (\is_array($match[$field] ?? null)) {
                    $arrPlayers[] = $match[$field];
                }
            }

            $round = (int) ($match['round'] ?? 0);
            $strKey = $round . '|' . $whiteUuid . '|' . $blackUuid;

            if (!isset($arrReported[$strKey])) {
                $arrReported[$strKey] = ['round' => $round, 'white' => $whiteUuid, 'black' => $blackUuid, 'data' => $match];
            }
        }

        if (!$arrReported) {
            return;
        }

        // Spielerdaten redundanzfrei in der Auswertungstabelle ablegen
        // (legt auch Personen und Turnierhistorie an, KEINE Löschung)
        WertungsportalTournamentsEvaluationModel::syncPlayers($pid, $tournamentUuid, $arrPlayers, false);

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand der Partien laden (round|weiß|schwarz => Datensatz)
        $arrExisting = [];
        $objRows = $objDatabase->prepare('SELECT id, round, whitePlayerUuid, blackPlayerUuid, result, expected, restpartie FROM ' . static::$strTable . ' WHERE pid=?')
                               ->execute($pid);

        while ($objRows->next()) {
            $arrExisting[$objRows->round . '|' . $objRows->whitePlayerUuid . '|' . $objRows->blackPlayerUuid] = $objRows->row();
        }

        $arrInsert = [];

        foreach ($arrReported as $strKey => $arrItem) {
            $match = $arrItem['data'];
            $arrSet = [];

            if (\array_key_exists('result', $match)) {
                $arrSet['result'] = (string) $match['result'];
            }

            if (\array_key_exists('expected', $match)) {
                $arrSet['expected'] = (float) $match['expected'];
            }

            if (\array_key_exists('restpartie', $match)) {
                $arrSet['restpartie'] = !empty($match['restpartie']) ? '1' : '';
            }

            if (!isset($arrExisting[$strKey])) {
                $arrInsert[] = [
                    $pid,
                    $intTime,
                    $arrItem['round'],
                    $arrItem['white'],
                    $arrItem['black'],
                    (string) ($arrSet['result'] ?? ''),
                    (float) ($arrSet['expected'] ?? 0),
                    (string) ($arrSet['restpartie'] ?? ''),
                    '1',
                ];
                continue;
            }

            $arrSet = static::diffApiFields($arrExisting[$strKey], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$strKey]['id']);
            }
        }

        // Neue Partien blockweise anlegen
        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?)'));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (pid, tstamp, round, whitePlayerUuid, blackPlayerUuid, result, expected, restpartie, published) VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }
    }
}
