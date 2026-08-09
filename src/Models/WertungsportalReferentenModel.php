<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;

/**
 * Model für die Tabelle tl_wertungsportal_referenten.
 *
 * Die Wertungsreferenten der Verbände: Wer ist für welchen Verband zuständig?
 * Gepflegt wird die Zuordnung als Mehrfachauswahl über die Verbands-VKZ,
 * gespeichert als serialisierte Liste im Feld `verbaende`.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $nachname
 * @property string $vorname
 * @property string $email
 * @property string $nuId       nu-Nummer der Person (z. B. NU4093214)
 * @property string $strasse
 * @property string $plz
 * @property string $ort
 * @property string $telefon
 * @property mixed  $verbaende  serialisierte Liste der Verbands-VKZ
 * @property string $published
 *
 * @method static WertungsportalReferentenModel|null findById($id, array $opt = [])
 * @method static WertungsportalReferentenModel|null findOneBy($col, $val, array $opt = [])
 * @method static Collection|WertungsportalReferentenModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalReferentenModel[]|null findBy($col, $val, array $opt = [])
 */
class WertungsportalReferentenModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_referenten';

    /**
     * Liefert die veröffentlichten Referenten eines Verbandes.
     *
     * Gesucht wird in der serialisierten Liste — deshalb per LIKE auf den in
     * Anführungszeichen eingeschlossenen Wert. Ohne die Anführungszeichen
     * fände „100" auch „10000"; der Wert steht in der Serialisierung immer
     * zwischen Doppelanführungszeichen.
     *
     * @param string $strVkz Verbands-VKZ, wie sie in der Auswahl steht
     *
     * @return Collection|WertungsportalReferentenModel[]|null
     */
    public static function findByVerband(string $strVkz)
    {
        if ('' === $strVkz) {
            return null;
        }

        return static::findBy(
            ["verbaende LIKE ?", "published = '1'"],
            ['%"' . $strVkz . '"%'],
            ['order' => 'nachname, vorname']
        );
    }

    /**
     * Liefert die Verbände, für die ein Referent zuständig ist — mit Namen.
     *
     * @return array VKZ => Bezeichnung („30000 Berliner Schachverband")
     */
    public function verbandsliste(): array
    {
        $arrVkz = StringUtil::deserialize($this->verbaende, true);

        if (!\count($arrVkz)) {
            return [];
        }

        $arrAlle = \Schachbulle\ContaoWertungsportalBundle\Classes\Referenten::getVerbaende();
        $arrReturn = [];

        foreach ($arrVkz as $strVkz) {
            $arrReturn[$strVkz] = $arrAlle[$strVkz] ?? $strVkz;
        }

        return $arrReturn;
    }

    /**
     * Meldet, ob die Tabelle bereits angelegt ist.
     *
     * Gedacht für Aufrufer, die vor dem contao:migrate laufen können — ein
     * fehlender Tabellenzugriff soll dort keinen Fehler auslösen.
     */
    public static function tabelleVorhanden(): bool
    {
        try {
            return Database::getInstance()->tableExists(static::$strTable);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
