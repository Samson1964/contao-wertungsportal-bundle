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
     * "memberships" ab: Vorhandene Einträge werden aktualisiert und neue
     * angelegt. Es wird NICHTS gelöscht (siehe syncForPersons()).
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
     * Entfernt Platzhalter-Mitgliedschaften mit der Mitgliedsnummer 0 aus
     * einer API-Antwort: Beim Anlegen einer Person vergibt nu zunächst die
     * Nummer 0000 und liefert diesen Eintrag auch dann noch mit, wenn die
     * endgültige Mitgliedsnummer längst vergeben ist — die Person erscheint
     * dadurch doppelt beim selben Verein.
     *
     * Entfernt wird nur, wenn für DENSELBEN Verein eine Mitgliedschaft mit
     * einer Nummer größer 0 vorliegt. Ist die 0 die einzige Angabe, bleibt
     * der Eintrag erhalten (sonst verschwände die Mitgliedschaft ganz).
     *
     * @param array $arrMemberships memberships-Array der API
     */
    public static function filtereNullnummern(array $arrMemberships): array
    {
        // Vereine mit echter Mitgliedsnummer vormerken
        $arrEcht = [];

        foreach ($arrMemberships as $arrMembership) {
            if (\is_array($arrMembership) && !empty($arrMembership['vkz']) && (int) ($arrMembership['memberNo'] ?? 0) > 0) {
                $arrEcht[(string) $arrMembership['vkz']] = true;
            }
        }

        if (!$arrEcht) {
            return $arrMemberships;
        }

        $arrReturn = [];

        foreach ($arrMemberships as $arrMembership) {
            if (\is_array($arrMembership) && (int) ($arrMembership['memberNo'] ?? 0) === 0 && isset($arrEcht[(string) ($arrMembership['vkz'] ?? '')])) {
                continue;
            }

            $arrReturn[] = $arrMembership;
        }

        return $arrReturn;
    }

    /**
     * Identität einer Mitgliedschaft: VKZ + Mitgliedsnummer + Lizenzstatus +
     * Beginn der Spielgenehmigung. Eine Person kann im SELBEN Verein mehrere
     * Mitgliedschaften mit derselben Mitgliedsnummer haben (aufeinander
     * folgende Zeiträume, Wechsel aktiv/passiv) — VKZ + Mitgliedsnummer
     * allein identifiziert deshalb keinen Datensatz, sondern eine ganze
     * Historie. Die Mitgliedsnummer wird normalisiert (führende Nullen und
     * Leerzeichen), damit unterschiedlich formatierte Quellen (API-Sync
     * liefert int, CSV liefert String) denselben Datensatz treffen.
     */
    public static function schluessel(string $strVkz, string $strMemberNo, string $strLicence = '', string $strVon = ''): string
    {
        return $strVkz . '|' . ltrim(trim($strMemberNo), '0') . '|' . $strLicence . '|' . $strVon;
    }

    /**
     * Importiert Mitgliedschaften aus den CSV-Exporten in einem Rutsch.
     * Schlüssel ist die volle Identität einer Mitgliedschaft (VKZ +
     * Mitgliedsnummer + Lizenzstatus + Beginn, siehe schluessel()):
     * Bestehende Datensätze werden aktualisiert (inkl. pid-Verknüpfung),
     * unbekannte per Batch-INSERT angelegt. Es wird NICHTS gelöscht.
     *
     * WICHTIG (Bugfix 1.0.9): Früher war der Schlüssel nur VKZ +
     * Mitgliedsnummer. Da eine Person im selben Verein unter derselben
     * Mitgliedsnummer mehrere aufeinander folgende Mitgliedschaften haben
     * kann (aktiv/passiv, verschiedene Zeiträume), fielen beim Import der
     * Spielgenehmigungen alle Zeiträume auf EINEN Datensatz zusammen —
     * die Historie ging verloren.
     *
     * Findet sich kein Datensatz mit exakt passendem Zeitraum, wird ein
     * vorhandener Platzhalter OHNE Zeitraum (vom API-Sync angelegt, der
     * keine Zeiträume liefert) mit gleicher VKZ/Mitgliedsnummer und
     * gleichem Lizenzstatus vervollständigt statt eine Dublette anzulegen.
     *
     * $arrMemberships: Schlüssel => ['pid' => Personen-ID, 'vkz' => ...,
     *                                'memberNo' => ..., 'felder' => [CSV_FIELDS]]
     *
     * Importregel: Die CSV-Daten haben höhere Priorität und überschreiben
     * abweichende Bestandsdaten unabhängig davon, ob der vorhandene tstamp
     * jünger ist. LEERE Importwerte überschreiben allerdings keine gefüllten
     * Bestandsfelder — ein Teilimport darf vorhandene Daten nicht leeren.
     * Über $intTstamp (Datum/Uhrzeit aus dem Dateinamen) wird der tstamp der
     * geschriebenen Datensätze gesetzt; 0 = aktuelle Zeit.
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

        // Bestand blockweise über (vkz, memberNo)-Paare laden — das sind ALLE
        // Zeiträume dieser Mitgliedsnummern, indiziert nach voller Identität.
        // Datensätze ohne Zeitraum kommen zusätzlich in eine Platzhalterliste
        $arrExisting = [];
        $arrPlatzhalter = [];

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
                $arrRow = $objRows->row();
                $arrExisting[static::schluessel((string) $arrRow['vkz'], (string) $arrRow['memberNo'], (string) $arrRow['licenceState'], (string) $arrRow['spielgenehmigungVon'])] = $arrRow;

                if ('' === (string) $arrRow['spielgenehmigungVon']) {
                    $arrPlatzhalter[static::schluessel((string) $arrRow['vkz'], (string) $arrRow['memberNo'], (string) $arrRow['licenceState'])] = $arrRow;
                }
            }
        }

        $arrInsert = [];

        foreach ($arrMemberships as $strKey => $arrItem) {
            $arrRow = $arrExisting[$strKey] ?? null;

            // Kein exakter Treffer: Platzhalter ohne Zeitraum vervollständigen
            if (null === $arrRow) {
                $strPlatzhalter = static::schluessel((string) $arrItem['vkz'], (string) $arrItem['memberNo'], (string) ($arrItem['felder']['licenceState'] ?? ''));

                if (isset($arrPlatzhalter[$strPlatzhalter])) {
                    $arrRow = $arrPlatzhalter[$strPlatzhalter];
                    unset($arrPlatzhalter[$strPlatzhalter]);
                }
            }

            if (null === $arrRow) {
                $arrNeu = [(int) $arrItem['pid'], $intTime, (string) $arrItem['vkz'], (string) $arrItem['memberNo']];

                foreach (self::CSV_FIELDS as $strField) {
                    $arrNeu[] = (string) ($arrItem['felder'][$strField] ?? '');
                }

                $arrNeu[] = '1';
                $arrInsert[] = $arrNeu;
                continue;
            }

            $arrSet = ['pid' => (int) $arrItem['pid']];

            foreach (self::CSV_FIELDS as $strField) {
                // Leere Importwerte lassen gefüllte Bestandsfelder unberührt
                if (\array_key_exists($strField, $arrItem['felder']) && ('' !== (string) $arrItem['felder'][$strField] || '' === (string) ($arrRow[$strField] ?? ''))) {
                    $arrSet[$strField] = (string) $arrItem['felder'][$strField];
                }
            }

            $arrSet = static::diffApiFields($arrRow, $arrSet);

            if ($arrSet) {
                $arrSet['tstamp'] = $intTime;
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrRow['id']);
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
     * Batch-INSERT angelegt, bestehende nur bei Änderungen aktualisiert.
     * Die Vereine der Mitgliedschaften werden gesammelt über
     * WertungsportalClubsModel::syncList() angelegt.
     *
     * WICHTIG (Bugfix 1.0.9): Der Sync LÖSCHT NICHTS mehr. Die nu-API liefert
     * ausschließlich die aktuellen Mitgliedschaften einer Person, nicht deren
     * Historie — die früher hier eingebaute Löschung "nicht mehr gemeldeter"
     * Einträge hat deshalb bei jedem Frontend-Seitenaufruf (Spielersuche,
     * Karteikarte, Vereins-/Verbandsliste) die per CSV importierten
     * historischen Mitgliedschaften abgeräumt.
     *
     * Ebenfalls Bugfix: Der Abgleich trifft nur noch Datensätze mit LAUFENDER
     * Genehmigung (Ende leer). Früher wurde per pid+VKZ gematcht, wodurch bei
     * mehreren Mitgliedschaften im selben Verein willkürlich ein historischer
     * Datensatz erwischt und mit den aktuellen Werten überschrieben wurde
     * (verfälschte Zeiträume und Dubletten).
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

            // Platzhalter mit der Mitgliedsnummer 0 gar nicht erst übernehmen,
            // wenn für denselben Verein die endgültige Nummer vorliegt
            $arrMemberships = static::filtereNullnummern($arrMemberships);

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

        // Bestand der betroffenen Personen blockweise laden. Indiziert wird
        // nur über pid+VKZ der LAUFENDEN Mitgliedschaften (Ende leer) — die
        // API meldet ausschließlich aktuelle Mitgliedschaften, abgeschlossene
        // Zeiträume dürfen von ihr nicht überschrieben werden
        $arrExisting = [];

        foreach (array_chunk(array_keys($arrByPid), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objRows = $objDatabase->prepare("SELECT id, pid, vkz, memberNo, clubName, licenceState, regionName, federationName FROM " . static::$strTable . " WHERE spielgenehmigungBis = '' AND pid IN (" . $strPlaceholders . ')')
                                   ->execute($arrChunk);

            while ($objRows->next()) {
                $strRowKey = $objRows->pid . '|' . $objRows->vkz;

                // Bei mehreren laufenden Einträgen im selben Verein gewinnt
                // der erste; die übrigen bleiben unangetastet
                if (!isset($arrExisting[$strRowKey])) {
                    $arrExisting[$strRowKey] = $objRows->row();
                }
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

        // KEINE Löschung: Was die API nicht meldet, ist nicht gelöscht,
        // sondern nur nicht aktuell (die Schnittstelle liefert keine
        // Historie). Beendete Mitgliedschaften kommen ausschließlich über
        // die CSV-Importe des Mitgliederportals herein.
    }

    /**
     * Räumt doppelte Mitgliedschaften auf: Datensätze derselben Person mit
     * gleicher VKZ, Mitgliedsnummer, Lizenzstatus und gleichem Zeitraum sind
     * ein und derselbe Eintrag. Behalten wird der Datensatz mit der
     * niedrigsten ID; von den Dubletten werden zuvor Feldwerte übernommen,
     * die im behaltenen Datensatz leer sind (kein Informationsverlust).
     *
     * Nötig für Bestände, die vor dem Bugfix 1.0.9 aufgebaut wurden.
     *
     * Zusätzlich werden Platzhalter-Mitgliedschaften mit der Nummer 0
     * entfernt, sofern dieselbe Person beim selben Verein eine echte
     * Mitgliedsnummer hat (siehe filtereNullnummern()).
     *
     * @return array ['geprueft' => x, 'entfernt' => y, 'ergaenzt' => z, 'nullnummern' => n]
     */
    public static function entdopple(): array
    {
        $arrErgebnis = ['geprueft' => 0, 'entfernt' => 0, 'ergaenzt' => 0, 'nullnummern' => 0];
        $objDatabase = Database::getInstance();
        $strFields = implode(', ', self::CSV_FIELDS);

        // Nur Gruppen mit mehr als einem Datensatz laden (die Normalisierung
        // der Mitgliedsnummer erfolgt in PHP über schluessel())
        $objRows = $objDatabase->execute('SELECT id, pid, vkz, memberNo, ' . $strFields . ' FROM ' . static::$strTable . ' ORDER BY pid, vkz, id');

        $arrGruppen = [];

        while ($objRows->next()) {
            $arrRow = $objRows->row();
            $strKey = $arrRow['pid'] . '|' . static::schluessel((string) $arrRow['vkz'], (string) $arrRow['memberNo'], (string) $arrRow['licenceState'], (string) $arrRow['spielgenehmigungVon']) . '|' . $arrRow['spielgenehmigungBis'];
            $arrGruppen[$strKey][] = $arrRow;
        }

        $arrDelete = [];

        foreach ($arrGruppen as $arrGruppe) {
            $arrErgebnis['geprueft']++;

            if (\count($arrGruppe) < 2) {
                continue;
            }

            $arrBehalten = array_shift($arrGruppe);
            $arrSet = [];

            foreach ($arrGruppe as $arrDublette) {
                $arrDelete[] = (int) $arrDublette['id'];

                // Leere Felder des behaltenen Datensatzes aus der Dublette füllen
                foreach (self::CSV_FIELDS as $strField) {
                    if ('' === (string) ($arrSet[$strField] ?? $arrBehalten[$strField] ?? '') && '' !== (string) ($arrDublette[$strField] ?? '')) {
                        $arrSet[$strField] = (string) $arrDublette[$strField];
                    }
                }
            }

            if ($arrSet) {
                $arrSet['tstamp'] = time();
                $objDatabase->prepare('UPDATE ' . static::$strTable . ' %s WHERE id=?')
                            ->set($arrSet)
                            ->execute($arrBehalten['id']);
                $arrErgebnis['ergaenzt']++;
            }
        }

        // Platzhalter mit Mitgliedsnummer 0 entfernen, wo dieselbe Person beim
        // selben Verein eine echte Mitgliedsnummer hat
        $objNull = $objDatabase->execute("SELECT n.id FROM " . static::$strTable . " n
            WHERE (n.memberNo = '' OR n.memberNo + 0 = 0)
              AND EXISTS (SELECT e.id FROM " . static::$strTable . " e WHERE e.pid = n.pid AND e.vkz = n.vkz AND e.memberNo + 0 > 0)");

        while ($objNull->next()) {
            $arrDelete[] = (int) $objNull->id;
            $arrErgebnis['nullnummern']++;
        }

        foreach (array_chunk(array_unique($arrDelete), 500) as $arrChunk) {
            $strPlaceholders = implode(',', array_fill(0, \count($arrChunk), '?'));
            $objDatabase->prepare('DELETE FROM ' . static::$strTable . ' WHERE id IN (' . $strPlaceholders . ')')
                        ->execute($arrChunk);
        }

        $arrErgebnis['entfernt'] = \count(array_unique($arrDelete));

        return $arrErgebnis;
    }
}
