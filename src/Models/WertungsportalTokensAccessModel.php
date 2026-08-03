<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_tokens_access.
 *
 * Ein Datensatz je Zugriff auf die örtliche Vereinslisten-Schnittstelle.
 *
 * @property int    $id
 * @property int    $pid
 * @property int    $tstamp
 * @property int    $zeitpunkt
 * @property string $datum
 * @property string $vkz
 * @property string $ip
 * @property string $quelle
 * @property int    $status
 * @property int    $anzahl
 * @property int    $dauer
 *
 * @method static WertungsportalTokensAccessModel|null findByPk($id, array $opt = [])
 * @method static Collection|WertungsportalTokensAccessModel[]|null findBy($col, $val, array $opt = [])
 */
class WertungsportalTokensAccessModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_tokens_access';

    /**
     * Aufbewahrungsfrist der Zugriffe in Tagen. Danach lassen sie sich im
     * Backend über „Alte Zugriffe löschen" entfernen. Bewusst begrenzt: Die
     * Zeilen enthalten IP-Adressen.
     */
    public const AUFBEWAHRUNG = 90;

    /**
     * Schreibt einen Zugriff mit.
     *
     * Fehler werden verschluckt: Die Schnittstelle soll auch dann antworten,
     * wenn die Zählung klemmt (fehlende Tabelle vor contao:migrate).
     *
     * @param int    $intPid    Datensatz-ID des Schlüssels (0 = unbekannter Schlüssel)
     * @param array  $arrDaten  vkz, ip, quelle, status, anzahl, dauer
     */
    public static function schreibe(int $intPid, array $arrDaten): void
    {
        try {
            $intZeit = time();

            Database::getInstance()
                ->prepare('INSERT INTO ' . static::$strTable . ' (pid, tstamp, zeitpunkt, datum, vkz, ip, quelle, status, anzahl, dauer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(
                    $intPid,
                    $intZeit,
                    $intZeit,
                    date('Y-m-d', $intZeit),
                    (string) ($arrDaten['vkz'] ?? ''),
                    (string) ($arrDaten['ip'] ?? ''),
                    (string) ($arrDaten['quelle'] ?? ''),
                    (int) ($arrDaten['status'] ?? 0),
                    (int) ($arrDaten['anzahl'] ?? 0),
                    (int) ($arrDaten['dauer'] ?? 0)
                );

            // Zähler am Schlüssel fortschreiben, damit die Liste ihn ohne
            // Unterabfrage anzeigen kann
            if ($intPid > 0) {
                Database::getInstance()
                    ->prepare('UPDATE tl_wertungsportal_tokens SET zugriffe = zugriffe + 1, letzterZugriff = ? WHERE id = ?')
                    ->execute($intZeit, $intPid);
            }
        } catch (\Throwable $e) {
            // Zählung ist Beiwerk — niemals die Auslieferung stören
        }
    }

    /**
     * Zählt die Zugriffe einer IP-Adresse seit einem Zeitpunkt — Grundlage
     * der Bremse gegen massenhaftes Abfragen.
     */
    public static function zaehleFuerIp(string $strIp, int $intSeit): int
    {
        if ('' === $strIp) {
            return 0;
        }

        try {
            $objRow = Database::getInstance()
                ->prepare('SELECT COUNT(*) AS anzahl FROM ' . static::$strTable . ' WHERE ip = ? AND zeitpunkt >= ?')
                ->execute($strIp, $intSeit);

            return $objRow->next() ? (int) $objRow->anzahl : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Liefert die Gesamtübersicht für das Statistik-Modul: Zugriffe je
     * Schlüssel und Verein in einem Zeitraum.
     *
     * Anfragen ohne gültigen Schlüssel (pid = 0) bleiben außen vor — sie
     * gehören zu keinem Datensatz und stehen in abweisungen().
     *
     * @return array Liste aus pid, vkz, token, name, email, gesperrt, anzahl, erfolge, letzter
     */
    public static function uebersicht(string $strVon, string $strBis): array
    {
        $arrReturn = [];

        try {
            $objRows = Database::getInstance()
                ->prepare('SELECT a.pid, a.vkz, COUNT(*) AS anzahl, SUM(CASE WHEN a.status = 200 THEN 1 ELSE 0 END) AS erfolge, MAX(a.zeitpunkt) AS letzter, t.token, t.vorname, t.nachname, t.email, t.gesperrt
                           FROM ' . static::$strTable . ' a
                           LEFT JOIN tl_wertungsportal_tokens t ON t.id = a.pid
                           WHERE a.datum >= ? AND a.datum <= ? AND a.pid > 0
                           GROUP BY a.pid, a.vkz
                           ORDER BY anzahl DESC')
                ->execute($strVon, $strBis);

            while ($objRows->next()) {
                $arrReturn[] = [
                    'pid'      => (int) $objRows->pid,
                    'vkz'      => (string) $objRows->vkz,
                    'token'    => (string) $objRows->token,
                    'name'     => trim($objRows->vorname . ' ' . $objRows->nachname),
                    'email'    => (string) $objRows->email,
                    'gesperrt' => (string) $objRows->gesperrt === '1',
                    'anzahl'   => (int) $objRows->anzahl,
                    'erfolge'  => (int) $objRows->erfolge,
                    'letzter'  => (int) $objRows->letzter,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $arrReturn;
    }

    /**
     * Liefert die abgewiesenen Anfragen eines Zeitraums, gebündelt je
     * IP-Adresse.
     *
     * Zweck ist ausschließlich das Erkennen von Mißbrauch: Wer ohne gültigen
     * Schlüssel oder für fremde Vereine anklopft, fällt hier auf und läßt sich
     * anschließend über die Einstellungen sperren. Abgewiesen ist alles ab
     * HTTP-Status 400.
     *
     * @param string $strVon  erster Tag (JJJJ-MM-TT)
     * @param string $strBis  letzter Tag (JJJJ-MM-TT)
     * @param int    $intMax  Höchstzahl der Zeilen
     *
     * @return array Liste aus ip, anzahl, letzter, gruende
     */
    public static function abweisungen(string $strVon, string $strBis, int $intMax = 20): array
    {
        $arrReturn = [];

        try {
            $objRows = Database::getInstance()
                ->prepare('SELECT ip, COUNT(*) AS anzahl, MAX(zeitpunkt) AS letzter, GROUP_CONCAT(DISTINCT quelle ORDER BY quelle SEPARATOR ",") AS gruende
                           FROM ' . static::$strTable . '
                           WHERE datum >= ? AND datum <= ? AND status >= 400
                           GROUP BY ip
                           ORDER BY anzahl DESC')
                ->limit($intMax)
                ->execute($strVon, $strBis);

            while ($objRows->next()) {
                $arrReturn[] = [
                    'ip'      => (string) $objRows->ip,
                    'anzahl'  => (int) $objRows->anzahl,
                    'letzter' => (int) $objRows->letzter,
                    'gruende' => array_filter(explode(',', (string) $objRows->gruende)),
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $arrReturn;
    }

    /**
     * Liefert den Tagesverlauf aller Zugriffe eines Zeitraums.
     *
     * @return array datum => Anzahl
     */
    public static function verlauf(string $strVon, string $strBis): array
    {
        $arrReturn = [];

        try {
            $objRows = Database::getInstance()
                ->prepare('SELECT datum, COUNT(*) AS anzahl FROM ' . static::$strTable . ' WHERE datum >= ? AND datum <= ? GROUP BY datum ORDER BY datum')
                ->execute($strVon, $strBis);

            while ($objRows->next()) {
                $arrReturn[(string) $objRows->datum] = (int) $objRows->anzahl;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $arrReturn;
    }

    /**
     * Löscht Zugriffe, die älter als die Aufbewahrungsfrist sind.
     *
     * @return int Anzahl der gelöschten Zeilen
     */
    public static function aufraeumen(): int
    {
        try {
            $objResult = Database::getInstance()
                ->prepare('DELETE FROM ' . static::$strTable . ' WHERE zeitpunkt < ?')
                ->execute(time() - self::AUFBEWAHRUNG * 86400);

            return (int) $objResult->affectedRows;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
