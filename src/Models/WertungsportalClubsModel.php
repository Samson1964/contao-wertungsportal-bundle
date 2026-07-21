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
     * Felder des Vereins-Stammdaten-CSV-Imports (DB-Namen, alle als String
     * behandelt; die ja/nein-Kennzeichen sind als '1'/'' gemappt).
     */
    public const CSV_STRING_FIELDS = [
        'clubName', 'verbandName', 'regionName', 'kurzname', 'druckname',
        'debitorNr', 'lsbNr', 'vereinsregisterNr', 'gruendungsjahr',
        'eintrittsdatum', 'austrittsdatum', 'zahlungsart',
        'istVerband', 'istReinerSchachverein', 'istMehrspartenverein',
        'istFreizeitverein', 'istSpielgemeinschaft', 'istVereinOhneSpielbetrieb',
        'personalisierteAnmeldung', 'istMitgliedsverein', 'istVeranstalter',
        'bic', 'bank', 'iban', 'kontoinhaber',
        'adresseName', 'adresseStrasse', 'adressePlz', 'adresseOrt',
        'adresseEmail1', 'adresseEmail2', 'adresseHomepage',
        'adresseTelefonPrivat', 'adresseTelefonGeschaeft', 'adresseTelefonMobil',
        'adresseFaxPrivat', 'adresseFaxGeschaeft',
        'sportstaette1Name', 'sportstaette1Strasse', 'sportstaette1Plz', 'sportstaette1Ort',
        'sportstaette1Email', 'sportstaette1Homepage', 'sportstaette1Telefon', 'sportstaette1Fax',
        'sportstaette2Name', 'sportstaette2Strasse', 'sportstaette2Plz', 'sportstaette2Ort',
        'sportstaette2Email', 'sportstaette2Homepage', 'sportstaette2Telefon', 'sportstaette2Fax',
        'sportstaette3Name', 'sportstaette3Strasse', 'sportstaette3Plz', 'sportstaette3Ort',
        'sportstaette3Email', 'sportstaette3Homepage', 'sportstaette3Telefon', 'sportstaette3Fax',
        'bemerkung', 'state',
    ];

    /**
     * Importiert Zeilen des Vereins-Stammdaten-CSV in einem Rutsch.
     * Schlüssel ist die VKZ; $arrClubs bildet clubVkz => Feld-Array ab
     * (bereits auf DB-Feldnamen gemappt, Felder aus CSV_STRING_FIELDS).
     * Bestehende Vereine werden nur bei tatsächlichen Änderungen
     * aktualisiert, neue per Batch-INSERT angelegt (published='1').
     *
     * Importregel wie beim Personen-Import: Die CSV-Daten überschreiben
     * abweichende Bestandsdaten unabhängig vom vorhandenen tstamp; über
     * $intTstamp (Datum aus dem Dateinamen) wird der tstamp der
     * geschriebenen Datensätze gesetzt (0 = aktuelle Zeit).
     *
     * @return array ['neu' => x, 'aktualisiert' => y, 'unveraendert' => z]
     */
    public static function importCsvRows(array $arrClubs, int $intTstamp = 0): array
    {
        $arrErgebnis = ['neu' => 0, 'aktualisiert' => 0, 'unveraendert' => 0];

        if (!$arrClubs) {
            return $arrErgebnis;
        }

        $objDatabase = Database::getInstance();
        $intTime = $intTstamp > 0 ? $intTstamp : time();
        $strFields = implode(', ', self::CSV_STRING_FIELDS);

        // Bestand blockweise laden (VKZ => Datensatz)
        $arrExisting = [];

        foreach (array_chunk(array_keys($arrClubs), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare('SELECT id, clubVkz, ' . $strFields . ' FROM ' . static::$strTable . ' WHERE clubVkz IN (' . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $arrExisting[(string) $objRows->clubVkz] = $objRows->row();
            }
        }

        $arrInsert = [];

        foreach ($arrClubs as $strVkz => $arrClub) {
            if (!isset($arrExisting[$strVkz])) {
                $arrRow = [$intTime, (string) $strVkz];

                foreach (self::CSV_STRING_FIELDS as $strField) {
                    $arrRow[] = (string) ($arrClub[$strField] ?? '');
                }

                $arrRow[] = '1';
                $arrInsert[] = $arrRow;
                continue;
            }

            $arrSet = [];

            foreach (self::CSV_STRING_FIELDS as $strField) {
                if (\array_key_exists($strField, $arrClub)) {
                    $arrSet[$strField] = (string) $arrClub[$strField];
                }
            }

            $arrSet = static::diffApiFields($arrExisting[$strVkz], $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrExisting[$strVkz]['id']);
                $arrErgebnis['aktualisiert']++;
            } else {
                $arrErgebnis['unveraendert']++;
            }
        }

        // Neue Vereine blockweise anlegen
        $strColumns = 'tstamp, clubVkz, ' . $strFields . ', published';
        $strTuple = '(' . implode(', ', array_fill(0, \count(self::CSV_STRING_FIELDS) + 3, '?')) . ')';

        foreach (array_chunk($arrInsert, 100) as $arrChunk) {
            $strValues = implode(', ', array_fill(0, \count($arrChunk), $strTuple));

            $objDatabase->prepare('INSERT INTO ' . static::$strTable . ' (' . $strColumns . ') VALUES ' . $strValues)
                        ->execute(array_merge(...$arrChunk));
        }

        $arrErgebnis['neu'] = \count($arrInsert);

        return $arrErgebnis;
    }

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
