<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_sperren.
 *
 * Hält fest, wer wegen Massenabfragen gebremst wurde: Zeitpunkt, IP-Adresse,
 * Browserkennung und — sofern angemeldet — Kennung und Name des Mitglieds.
 *
 * Der Mitgliedsbezug ist der eigentliche Grund für diese Tabelle: Manche Bots
 * rufen angemeldet ab, und dann sagt die IP-Adresse allein wenig. Die Kennung
 * steht neben dem Namen, weil Namen doppelt vorkommen und sich ändern.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property int    $zeitpunkt
 * @property string $datum       Tag der Sperre (JJJJ-MM-TT), für die Filterung
 * @property string $ip
 * @property string $useragent   Browserkennung, gekürzt
 * @property int    $memberId    tl_member.id, 0 = nicht angemeldet
 * @property string $memberName  Anmeldename, leer wenn nicht angemeldet
 * @property string $grund       minute | stunde | tag
 * @property int    $anzahl      Abrufe im überschrittenen Fenster
 * @property int    $grenze      Eingestellter Höchstwert
 *
 * @method static WertungsportalSperrenModel|null findById($id, array $opt = [])
 * @method static Collection|WertungsportalSperrenModel[]|null findAll(array $opt = [])
 */
class WertungsportalSperrenModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_sperren';

    /**
     * Länge, auf die die Browserkennung gekürzt wird. Sie kann beliebig lang
     * sein; für die Zuordnung reicht der Anfang.
     */
    public const USERAGENT_MAX = 250;

    /**
     * Schreibt eine Sperre ins Protokoll.
     *
     * Mehrfach hintereinander gesperrte Abrufe derselben Adresse erzeugen
     * bewusst NICHT je einen Eintrag: Ein blockierter Bot rennt sonst weiter
     * und flutet das Protokoll mit demselben Vorfall. Innerhalb desselben
     * Fensters wird deshalb nur der erste Fall festgehalten und danach der
     * Zähler des vorhandenen Eintrags erhöht.
     *
     * Fehler werden verschluckt — ein Protokollproblem darf die Sperre nicht
     * aushebeln.
     *
     * @param string $strIp
     * @param string $strGrund   minute | stunde | tag
     * @param int    $intAnzahl  Abrufe im Fenster
     * @param int    $intGrenze  Eingestellter Höchstwert
     * @param string $strAgent   Browserkennung
     * @param int    $intMember  tl_member.id (0 = nicht angemeldet)
     * @param string $strName    Anmeldename des Mitglieds
     *
     * @return void
     */
    public static function protokolliere(string $strIp, string $strGrund, int $intAnzahl, int $intGrenze, string $strAgent, int $intMember, string $strName): void
    {
        $intJetzt = time();
        $intSeit = $intJetzt - (WertungsportalBesucherModel::FENSTER[$strGrund] ?? 3600);

        try {
            // Läuft der Vorfall noch? Dann nur den Zähler fortschreiben
            $objRow = Database::getInstance()
                ->prepare('SELECT id FROM ' . static::$strTable . ' WHERE ip = ? AND grund = ? AND zeitpunkt >= ? ORDER BY zeitpunkt DESC')
                ->limit(1)
                ->execute($strIp, $strGrund, $intSeit);

            if ($objRow->numRows) {
                Database::getInstance()
                    ->prepare('UPDATE ' . static::$strTable . ' SET tstamp = ?, anzahl = ? WHERE id = ?')
                    ->execute($intJetzt, $intAnzahl, (int) $objRow->id);

                return;
            }

            Database::getInstance()
                ->prepare('INSERT INTO ' . static::$strTable . ' (tstamp, zeitpunkt, datum, ip, useragent, memberId, memberName, grund, anzahl, grenze) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(
                    $intJetzt,
                    $intJetzt,
                    date('Y-m-d', $intJetzt),
                    $strIp,
                    substr($strAgent, 0, self::USERAGENT_MAX),
                    $intMember,
                    $strName,
                    $strGrund,
                    $intAnzahl,
                    $intGrenze
                );
        } catch (\Throwable $e) {
            // Protokoll ist Beiwerk
        }
    }
}
