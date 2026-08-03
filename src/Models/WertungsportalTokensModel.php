<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_tokens.
 *
 * Zugangsschlüssel für die örtliche Vereinslisten-Schnittstelle.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $token
 * @property string $vkz
 * @property string $vorname
 * @property string $nachname
 * @property string $email
 * @property string $gesperrt
 * @property string $grund
 * @property string $ip
 * @property int    $zugriffe
 * @property int    $letzterZugriff
 * @property string $published
 *
 * @method static WertungsportalTokensModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalTokensModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalTokensModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalTokensModel[]|null findBy($col, $val, array $opt = [])
 */
class WertungsportalTokensModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_tokens';

    /**
     * Länge des erzeugten Schlüssels in Zeichen (Hexdarstellung).
     * 40 Zeichen entsprechen 20 Zufallsbytes — reichlich gegen Raten und
     * trotzdem noch von Hand übertragbar.
     */
    public const LAENGE = 40;

    /**
     * Erzeugt einen neuen, in der Tabelle noch nicht vergebenen Schlüssel.
     *
     * Verwendet random_bytes (kryptographisch sicher) statt uniqid oder rand:
     * Ein erratbarer Schlüssel wäre ein offenes Scheunentor, weil die
     * Schnittstelle sonst keine Anmeldung kennt.
     *
     * @return string Schlüssel in Kleinbuchstaben und Ziffern
     */
    public static function neuerSchluessel(): string
    {
        do {
            $strToken = bin2hex(random_bytes((int) (self::LAENGE / 2)));
        } while (null !== static::findByToken($strToken));

        return $strToken;
    }

    /**
     * Findet einen Schlüssel anhand seiner Zeichenkette.
     */
    public static function findByToken(string $strToken): ?self
    {
        if ('' === $strToken) {
            return null;
        }

        return static::findOneBy('token', $strToken);
    }

    /**
     * Legt einen Schlüssel für eine Registrierung an.
     *
     * Meldet sich dieselbe Person (E-Mail) für denselben Verein noch einmal
     * an, wird KEIN zweiter Schlüssel erzeugt, sondern der vorhandene erneut
     * verschickt — sonst sammelten sich mit jeder vergessenen Mail neue
     * Schlüssel an, die alle gültig blieben.
     *
     * Ist die Einstellung „Neue Schlüssel erst nach Freigabe" gesetzt, wird der
     * Schlüssel unveröffentlicht angelegt und wirkt damit wie gesperrt, bis ihn
     * jemand im Backend freischaltet.
     *
     * @param array $arrDaten vkz, email, vorname, nachname
     *
     * @return array [Model, bool $blnNeu]
     */
    public static function registriere(array $arrDaten): array
    {
        $strEmail = strtolower(trim((string) ($arrDaten['email'] ?? '')));
        $strVkz = trim((string) ($arrDaten['vkz'] ?? ''));

        $objToken = static::findOneBy(['email=?', 'vkz=?'], [$strEmail, $strVkz]);

        if (null !== $objToken) {
            return [$objToken, false];
        }

        $objToken = new static();
        $objToken->tstamp = time();
        $objToken->token = static::neuerSchluessel();
        $objToken->vkz = $strVkz;
        $objToken->email = $strEmail;
        $objToken->vorname = trim((string) ($arrDaten['vorname'] ?? ''));
        $objToken->nachname = trim((string) ($arrDaten['nachname'] ?? ''));
        $objToken->published = empty($GLOBALS['TL_CONFIG']['wertungsportal_api_freigabe']) ? '1' : '';
        $objToken->save();

        return [$objToken, true];
    }

    /**
     * Zählt die Schlüssel, die eine E-Mail-Adresse seit einem Zeitpunkt
     * angefordert hat — Grundlage der Bremse gegen massenhaftes Anfordern.
     *
     * @param string $strEmail E-Mail-Adresse (leer = nicht prüfen)
     * @param int    $intSeit  Unix-Zeitstempel
     */
    public static function zaehleRegistrierungen(string $strEmail, int $intSeit): int
    {
        return static::zaehleSeit('email', strtolower($strEmail), $intSeit);
    }

    /**
     * Zählt die Schlüssel, die von einer IP-Adresse aus angefordert wurden.
     *
     * Ohne diese zweite Bremse ließe sich die erste umgehen, indem für jede
     * Anforderung eine andere E-Mail-Adresse eingetragen wird.
     *
     * @param string $strIp   IP-Adresse (leer = nicht prüfen)
     * @param int    $intSeit Unix-Zeitstempel
     */
    public static function zaehleRegistrierungenIp(string $strIp, int $intSeit): int
    {
        return static::zaehleSeit('ip', $strIp, $intSeit);
    }

    /**
     * Zählt Datensätze mit einem Feldwert ab einem Zeitpunkt.
     *
     * Fehler werden verschluckt und als 0 gewertet: Eine klemmende Zählung
     * darf die Registrierung nicht blockieren (fehlende Tabelle oder Spalte
     * vor dem contao:migrate).
     *
     * @param string $strFeld  Spaltenname, ausschließlich aus dem Bundle heraus gesetzt
     * @param string $strWert  Vergleichswert
     * @param int    $intSeit  Unix-Zeitstempel
     */
    protected static function zaehleSeit(string $strFeld, string $strWert, int $intSeit): int
    {
        if ('' === $strWert) {
            return 0;
        }

        try {
            $objRow = Database::getInstance()
                ->prepare('SELECT COUNT(*) AS anzahl FROM ' . static::$strTable . ' WHERE ' . $strFeld . ' = ? AND tstamp >= ?')
                ->execute($strWert, $intSeit);

            return $objRow->next() ? (int) $objRow->anzahl : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
