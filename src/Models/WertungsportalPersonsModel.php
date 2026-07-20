<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_persons.
 *
 * Hinweis: Die Spalte "index" ist ein reserviertes Wort in MySQL.
 * Der Zugriff über $model->index und save() ist sicher (Contao quotiert
 * beim Schreiben), aber keine eigenen Finder wie findByIndex() verwenden!
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $uuid
 * @property string $nuLigaPersonId
 * @property string $firstname
 * @property string $lastname
 * @property string $birthyear
 * @property string $gender
 * @property int    $fideId
 * @property int    $rating
 * @property int    $index
 * @property string $weekOfLastTournamentEvaluation
 * @property string $published
 *
 * @method static WertungsportalPersonsModel|null findById($id, array $opt = [])
 * @method static WertungsportalPersonsModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalPersonsModel|null findOneBy($col, $val, array $opt = [])
 * @method static WertungsportalPersonsModel|null findOneByFideId($val, array $opt = [])
 * @method static WertungsportalPersonsModel|null findOneByNuLigaPersonId($val, array $opt = [])
 * @method static Collection|WertungsportalPersonsModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalPersonsModel[]|null findBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalPersonsModel[]|null findByLastname($val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 */
class WertungsportalPersonsModel extends Model
{
    use ApiSyncTrait;

    protected static $strTable = 'tl_wertungsportal_persons';

    /**
     * String-Felder des Vereinsmitglieder-CSV-Imports (DB-Namen).
     * fideId (int) wird separat behandelt.
     */
    public const CSV_STRING_FIELDS = [
        'externeNr', 'anrede', 'titel', 'firstname', 'lastname', 'geburtsname',
        'birthyear', 'geburtsort', 'verstorben', 'verstorbenAm', 'nation',
        'fideNation', 'gender', 'geschlechtSpielbetrieb', 'strasse', 'plz',
        'ort', 'land', 'email1', 'email2', 'telPrivat', 'telMobil',
        'telGeschaeft', 'faxPrivat', 'faxGeschaeft', 'datenschutzDatum',
        'datenschutzBenutzer', 'datenschutzInfoDatum', 'datenschutzInfoBenutzer',
    ];

    /**
     * Findet eine Person anhand ihrer UUID.
     */
    public static function findByUuid(string $uuid, array $arrOptions = []): ?self
    {
        return static::findOneBy('uuid', $uuid, $arrOptions);
    }

    /**
     * Findet alle veröffentlichten Personen.
     *
     * @return Collection|WertungsportalPersonsModel[]|null
     */
    public static function findPublished(array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.lastname, $t.firstname";
        }

        return static::findBy(["$t.published='1'"], null, $arrOptions);
    }

    /**
     * Sucht Personen per LIKE in Vor- und Nachname.
     *
     * @return Collection|WertungsportalPersonsModel[]|null
     */
    public static function searchByName(string $term, bool $onlyPublished = true, array $arrOptions = []): ?Collection
    {
        $t = static::$strTable;

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $arrColumns = ["($t.lastname LIKE ? OR $t.firstname LIKE ?)"];
        $arrValues  = [$like, $like];

        if ($onlyPublished) {
            $arrColumns[] = "$t.published='1'";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.lastname, $t.firstname";
        }

        return static::findBy($arrColumns, $arrValues, $arrOptions);
    }

    /**
     * Legt eine Person anhand ihrer UUID an oder aktualisiert sie –
     * praktisch für den Import aus der Wertungsportal-API.
     *
     * $data enthält die Felder aus dem API-Array "data"; das Unter-Array
     * "memberships" wird hier ignoriert und separat über
     * WertungsportalPersonsMembershipsModel::syncForPerson() abgeglichen.
     */
    public static function upsertByUuid(array $data): self
    {
        $model = static::findByUuid((string) ($data['uuid'] ?? ''));

        // Fallback auf die systemweite nuLigaPersonId, falls die Person
        // zuvor ohne UUID angelegt wurde (z. B. per upsertFromPlayerDto)
        if (null === $model && !empty($data['nuLigaPersonId'])) {
            $model = static::findOneBy('nuLigaPersonId', (string) $data['nuLigaPersonId']);
        }

        $isNew = null === $model;

        if ($isNew) {
            $model = new static();
            // Nur beim Anlegen setzen, damit eine manuell deaktivierte
            // Person beim Sync nicht wieder veröffentlicht wird
            $model->published = '1';
        }

        $set = ['uuid' => (string) ($data['uuid'] ?? '')];

        foreach (['nuLigaPersonId', 'firstname', 'lastname', 'gender', 'weekOfLastTournamentEvaluation'] as $field) {
            if (\array_key_exists($field, $data)) {
                $set[$field] = (string) $data[$field];
            }
        }

        foreach (['fideId', 'rating', 'index'] as $field) {
            if (\array_key_exists($field, $data)) {
                $set[$field] = (int) $data[$field];
            }
        }

        $changed = static::applyApiFields($model, $set);
        $changed = static::applyBirthyear($model, $data) || $changed;

        if ($changed || $isNew) {
            $model->tstamp = time();
            $model->save();
        }

        return $model;
    }

    /**
     * Gleicht eine komplette Personenliste aus der API (z. B. Spielerliste,
     * Vereins- oder Verbandsliste) in einem Rutsch ab: Der Bestand wird per
     * UUID-Liste (mit Fallback auf die nuLigaPersonId) blockweise geladen,
     * neue Personen werden per Batch-INSERT angelegt und bestehende nur bei
     * tatsächlichen Änderungen aktualisiert. Der Veröffentlichungsstatus
     * bestehender Datensätze bleibt unberührt.
     *
     * @return array UUID => Datensatz-ID (für den Mitgliedschafts-Abgleich)
     */
    public static function syncList(array $arrPersons): array
    {
        // Eindeutige Personen nach UUID einsammeln
        $arrByUuid = [];

        foreach ($arrPersons as $arrPerson) {
            if (!\is_array($arrPerson) || empty($arrPerson['uuid'])) {
                continue;
            }

            $strUuid = (string) $arrPerson['uuid'];

            if (!isset($arrByUuid[$strUuid])) {
                $arrByUuid[$strUuid] = $arrPerson;
            }
        }

        if (!$arrByUuid) {
            return [];
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand blockweise per UUID laden
        $arrExisting = [];

        foreach (array_chunk(array_keys($arrByUuid), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, uuid, nuLigaPersonId, firstname, lastname, gender, weekOfLastTournamentEvaluation, fideId, rating, `index`, birthyear FROM ' . static::$strTable . ' WHERE uuid IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $arrExisting[(string) $objRows->uuid] = $objRows->row();
            }
        }

        // Fallback: Personen ohne UUID-Treffer über die nuLigaPersonId suchen
        // (z. B. zuvor per upsertFromPlayerDto ohne UUID angelegt)
        $arrFallback = [];

        foreach ($arrByUuid as $strUuid => $arrPerson) {
            if (!isset($arrExisting[$strUuid]) && !empty($arrPerson['nuLigaPersonId'])) {
                $arrFallback[(string) $arrPerson['nuLigaPersonId']] = $strUuid;
            }
        }

        foreach (array_chunk(array_keys($arrFallback), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, uuid, nuLigaPersonId, firstname, lastname, gender, weekOfLastTournamentEvaluation, fideId, rating, `index`, birthyear FROM ' . static::$strTable . ' WHERE nuLigaPersonId IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $strUuid = $arrFallback[(string) $objRows->nuLigaPersonId] ?? '';

                if ('' !== $strUuid && !isset($arrExisting[$strUuid])) {
                    $arrExisting[$strUuid] = $objRows->row();
                }
            }
        }

        $arrInsert = [];
        $arrMap = [];

        foreach ($arrByUuid as $strUuid => $arrPerson) {
            if (!isset($arrExisting[$strUuid])) {
                $arrInsert[] = [
                    $intTime,
                    $strUuid,
                    (string) ($arrPerson['nuLigaPersonId'] ?? ''),
                    (string) ($arrPerson['firstname'] ?? ''),
                    (string) ($arrPerson['lastname'] ?? ''),
                    (string) ($arrPerson['gender'] ?? ''),
                    (string) ($arrPerson['weekOfLastTournamentEvaluation'] ?? ''),
                    (int) ($arrPerson['fideId'] ?? 0),
                    (int) ($arrPerson['rating'] ?? 0),
                    (int) ($arrPerson['index'] ?? 0),
                    (string) ($arrPerson['birthyear'] ?? ''),
                    '1',
                ];
                continue;
            }

            $arrRow = $arrExisting[$strUuid];
            $arrMap[$strUuid] = (int) $arrRow['id'];

            $arrSet = ['uuid' => $strUuid];

            foreach (['nuLigaPersonId', 'firstname', 'lastname', 'gender', 'weekOfLastTournamentEvaluation'] as $strField) {
                if (\array_key_exists($strField, $arrPerson)) {
                    $arrSet[$strField] = (string) $arrPerson[$strField];
                }
            }

            foreach (['fideId', 'rating', 'index'] as $strField) {
                if (\array_key_exists($strField, $arrPerson)) {
                    $arrSet[$strField] = (int) $arrPerson[$strField];
                }
            }

            $arrSet = static::diffApiFields($arrRow, $arrSet);

            // Geburtsjahr-Regel: manuell gepflegtes Datum nicht überschreiben,
            // solange es das gelieferte Jahr enthält
            if (\array_key_exists('birthyear', $arrPerson)) {
                $strYear = (string) $arrPerson['birthyear'];
                $strCurrent = (string) $arrRow['birthyear'];

                if ($strCurrent !== $strYear && ('' === $strCurrent || false === strpos($strCurrent, $strYear))) {
                    $arrSet['birthyear'] = $strYear;
                }
            }

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrRow['id']);
            }
        }

        // Neue Personen blockweise anlegen
        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (tstamp, uuid, nuLigaPersonId, firstname, lastname, gender, weekOfLastTournamentEvaluation, fideId, rating, `index`, birthyear, published) VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        // IDs der neu angelegten Personen nachladen
        if ($arrInsert) {
            foreach (array_chunk(array_column($arrInsert, 1), 500) as $arrChunk) {
                $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
                $objRows = $objDatabase->prepare('SELECT id, uuid FROM ' . static::$strTable . ' WHERE uuid IN (' . $strPlaceholders . ')')
                                       ->execute($arrChunk);

                while ($objRows->next()) {
                    $arrMap[(string) $objRows->uuid] = (int) $objRows->id;
                }
            }
        }

        return $arrMap;
    }

    /**
     * Gleicht Spieler-DTOs (z. B. aus evaluation.players oder den Partien)
     * in einem Rutsch mit tl_wertungsportal_persons ab. Referenz ist die
     * systemweite nuLigaPersonId; es werden nur die Identitätsfelder
     * übernommen (wie upsertFromPlayerDto, aber als Bulk-Verarbeitung).
     *
     * @return array nuLigaPersonId => Datensatz-ID
     */
    public static function syncFromPlayerDtos(array $arrPlayers): array
    {
        // Eindeutige Spieler nach nuLigaPersonId einsammeln
        $arrById = [];

        foreach ($arrPlayers as $arrPlayer) {
            if (!\is_array($arrPlayer) || empty($arrPlayer['nuLigaPersonId'])) {
                continue;
            }

            $strId = (string) $arrPlayer['nuLigaPersonId'];

            if (!isset($arrById[$strId])) {
                $arrById[$strId] = $arrPlayer;
            }
        }

        if (!$arrById) {
            return [];
        }

        $objDatabase = Database::getInstance();
        $intTime = time();

        // Bestand blockweise laden
        $arrExisting = [];

        foreach (array_chunk(array_keys($arrById), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, nuLigaPersonId, firstname, lastname, fideId, birthyear FROM ' . static::$strTable . ' WHERE nuLigaPersonId IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $arrExisting[(string) $objRows->nuLigaPersonId] = $objRows->row();
            }
        }

        $arrInsert = [];
        $arrMap = [];

        foreach ($arrById as $strId => $arrPlayer) {
            if (!isset($arrExisting[$strId])) {
                $arrInsert[] = [
                    $intTime,
                    $strId,
                    (string) ($arrPlayer['firstname'] ?? ''),
                    (string) ($arrPlayer['lastname'] ?? ''),
                    (int) ($arrPlayer['fideId'] ?? 0),
                    (string) ($arrPlayer['birthyear'] ?? ''),
                    '1',
                ];
                continue;
            }

            $arrRow = $arrExisting[$strId];
            $arrMap[$strId] = (int) $arrRow['id'];

            $arrSet = [];

            foreach (['firstname', 'lastname'] as $strField) {
                if (\array_key_exists($strField, $arrPlayer)) {
                    $arrSet[$strField] = (string) $arrPlayer[$strField];
                }
            }

            if (\array_key_exists('fideId', $arrPlayer)) {
                $arrSet['fideId'] = (int) $arrPlayer['fideId'];
            }

            $arrSet = static::diffApiFields($arrRow, $arrSet);

            // Geburtsjahr-Regel wie in applyBirthyear()
            if (\array_key_exists('birthyear', $arrPlayer)) {
                $strYear = (string) $arrPlayer['birthyear'];
                $strCurrent = (string) $arrRow['birthyear'];

                if ($strCurrent !== $strYear && ('' === $strCurrent || false === strpos($strCurrent, $strYear))) {
                    $arrSet['birthyear'] = $strYear;
                }
            }

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrRow['id']);
            }
        }

        // Neue Personen blockweise anlegen
        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), '(?, ?, ?, ?, ?, ?, ?)'));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (tstamp, nuLigaPersonId, firstname, lastname, fideId, birthyear, published) VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        // IDs der neu angelegten Personen nachladen
        if ($arrInsert) {
            foreach (array_chunk(array_column($arrInsert, 1), 500) as $arrChunk) {
                $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
                $objRows = $objDatabase->prepare('SELECT id, nuLigaPersonId FROM ' . static::$strTable . ' WHERE nuLigaPersonId IN (' . $strPlaceholders . ')')
                                       ->execute($arrChunk);

                while ($objRows->next()) {
                    $arrMap[(string) $objRows->nuLigaPersonId] = (int) $objRows->id;
                }
            }
        }

        return $arrMap;
    }

    /**
     * Importiert Zeilen des Vereinsmitglieder-CSV in einem Rutsch. Schlüssel
     * ist die nuLigaPersonId; $arrPersons bildet nuLigaPersonId => Feld-Array
     * ab (bereits auf DB-Feldnamen gemappt, String-Felder aus
     * CSV_STRING_FIELDS plus fideId als int). Bestehende Personen werden nur
     * bei tatsächlichen Änderungen aktualisiert, neue per Batch-INSERT
     * angelegt (mit published='1'). Der Veröffentlichungsstatus bestehender
     * Datensätze bleibt unberührt.
     *
     * Importregel: Die CSV-Daten haben höhere Priorität und überschreiben
     * abweichende Bestandsdaten unabhängig davon, ob der vorhandene tstamp
     * jünger ist. Über $intTstamp (z. B. Datum/Uhrzeit aus dem Dateinamen
     * Vereine__Vereinsmitglieder__JJJJMMTTHHIISS) wird der tstamp der
     * geschriebenen Datensätze gesetzt; 0 = aktuelle Zeit.
     *
     * @return array ['neu' => x, 'aktualisiert' => y, 'unveraendert' => z,
     *                'ids' => [nuLigaPersonId => Datensatz-ID]]
     */
    public static function importCsvRows(array $arrPersons, int $intTstamp = 0): array
    {
        $arrErgebnis = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0, 'ids' => []];

        if (!$arrPersons) {
            return $arrErgebnis;
        }

        $objDatabase = Database::getInstance();
        $intTime = $intTstamp > 0 ? $intTstamp : time();
        $strFields = implode(', ', self::CSV_STRING_FIELDS);

        // Bestand blockweise laden (nuLigaPersonId => Datensatz)
        $arrExisting = [];

        foreach (array_chunk(array_keys($arrPersons), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, nuLigaPersonId, fideId, ' . $strFields . ' FROM ' . static::$strTable . ' WHERE nuLigaPersonId IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $arrExisting[(string) $objRows->nuLigaPersonId] = $objRows->row();
            }
        }

        $arrInsert = [];

        foreach ($arrPersons as $strId => $arrPerson) {
            if (!isset($arrExisting[$strId])) {
                $arrRow = [$intTime, (string) $strId];

                foreach (self::CSV_STRING_FIELDS as $strField) {
                    $arrRow[] = (string) ($arrPerson[$strField] ?? '');
                }

                $arrRow[] = (int) ($arrPerson['fideId'] ?? 0);
                $arrRow[] = '1';
                $arrInsert[] = $arrRow;
                continue;
            }

            $arrErgebnis['ids'][(string) $strId] = (int) $arrExisting[$strId]['id'];

            $arrSet = [];

            foreach (self::CSV_STRING_FIELDS as $strField) {
                if (\array_key_exists($strField, $arrPerson)) {
                    $arrSet[$strField] = (string) $arrPerson[$strField];
                }
            }

            if (\array_key_exists('fideId', $arrPerson)) {
                $arrSet['fideId'] = (int) $arrPerson['fideId'];
            }

            $arrSet = static::diffApiFields($arrExisting[$strId], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$strId]['id']);
                $arrErgebnis['aktualisiert']++;
            } else {
                $arrErgebnis['unveraendert']++;
            }
        }

        // Neue Personen blockweise anlegen
        $strColumns = 'tstamp, nuLigaPersonId, ' . $strFields . ', fideId, published';
        $strTuple = '(' . implode(', ', array_fill(0, \count(self::CSV_STRING_FIELDS) + 4, '?')) . ')';

        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        $arrErgebnis['neu'] = \count($arrInsert);

        // IDs der neu angelegten Personen nachladen (für den Mitgliedschafts-Import)
        if ($arrInsert) {
            foreach (array_chunk(array_column($arrInsert, 1), 500) as $arrChunk) {
                $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
                $objRows = $objDatabase->prepare('SELECT id, nuLigaPersonId FROM ' . static::$strTable . ' WHERE nuLigaPersonId IN (' . $strPlaceholders . ')')
                                       ->execute($arrChunk);

                while ($objRows->next()) {
                    $arrErgebnis['ids'][(string) $objRows->nuLigaPersonId] = (int) $objRows->id;
                }
            }
        }

        return $arrErgebnis;
    }

    /**
     * Übernimmt das Geburtsjahr aus einem API-Datensatz. Die API liefert nur
     * das Jahr (JJJJ); ein manuell gepflegtes vollständiges Geburtsdatum wird
     * nicht überschrieben, solange es das gelieferte Jahr enthält.
     * Liefert true zurück, wenn der Wert geändert wurde.
     */
    protected static function applyBirthyear(self $model, array $data): bool
    {
        if (!\array_key_exists('birthyear', $data)) {
            return false;
        }

        $year = (string) $data['birthyear'];
        $current = (string) $model->birthyear;

        if ($current !== $year && ('' === $current || false === strpos($current, $year))) {
            $model->birthyear = $year;

            return true;
        }

        return false;
    }

    /**
     * Legt eine Person anhand eines Spieler-DTOs (z. B. aus evaluation.players
     * oder matches whitePlayer/blackPlayer) an oder aktualisiert sie.
     *
     * Referenz ist die systemweite nuLigaPersonId - die playerUuid der
     * Turnier-DTOs gilt nur innerhalb des jeweiligen Turniers und wird
     * deshalb NICHT als Personen-UUID übernommen. Es werden nur die
     * Identitätsfelder gesetzt; turnierspezifische Werte wie ratingOld/-New
     * bleiben außen vor, um die aktuellen Personendaten nicht zu verfälschen.
     */
    public static function upsertFromPlayerDto(array $player): ?self
    {
        $nuLigaPersonId = (string) ($player['nuLigaPersonId'] ?? '');

        if ('' === $nuLigaPersonId) {
            return null;
        }

        $model = static::findOneBy('nuLigaPersonId', $nuLigaPersonId);
        $isNew = null === $model;

        if ($isNew) {
            $model = new static();
            $model->nuLigaPersonId = $nuLigaPersonId;
            // Nur beim Anlegen setzen, damit eine manuell deaktivierte
            // Person beim Sync nicht wieder veröffentlicht wird
            $model->published = '1';
        }

        $set = [];

        foreach (['firstname', 'lastname'] as $field) {
            if (\array_key_exists($field, $player)) {
                $set[$field] = (string) $player[$field];
            }
        }

        if (\array_key_exists('fideId', $player)) {
            $set['fideId'] = (int) $player['fideId'];
        }

        $changed = static::applyApiFields($model, $set);
        $changed = static::applyBirthyear($model, $player) || $changed;

        if ($changed || $isNew) {
            $model->tstamp = time();
            $model->save();
        }

        return $model;
    }
}
