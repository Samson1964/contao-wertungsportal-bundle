<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Model;

/**
 * Model für die Tabelle tl_wertungsportal_altcodes (ab 1.58.0).
 *
 * Eine Zeile je Turnier aus DeWIS: alter Turniercode und UUID bei nu, dazu
 * die Daten, die nu nicht liefert (erste Berechnung). Gefüllt vom Befehl
 * `wertungsportal:turniercodes`; gelesen über Helper\Altcodes.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property string $tcode        Alter Turniercode, z. B. „C619-C05-WAM"
 * @property string $uuid         UUID des Turniers bei nu
 * @property string $tname        Turniername in DeWIS
 * @property string $organisation Ausrichtender Verband laut DeWIS
 * @property string $vkz          Kennziffer des Verbandes
 * @property string $acron        Kürzel des Turniers
 * @property int    $rounds       Runden
 * @property string $finishedOn   Turnierende, JJJJ-MM-TT
 * @property string $computedOn   Erste Berechnung, JJJJ-MM-TT
 * @property string $recomputedOn Letzte Nachberechnung in DeWIS, JJJJ-MM-TT
 */
class WertungsportalAltcodesModel extends Model
{
    /**
     * Name der Tabelle.
     *
     * @var string
     */
    protected static $strTable = 'tl_wertungsportal_altcodes';
}
