<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_elo (FIDE-Elo-Daten).
 *
 * Die Tabelle wird über den XML-Import (Classes/EloImport) aus der
 * FIDE-Ratingliste befüllt; die FIDE-Anreicherung der Frontend-Ausgaben
 * liest per Bulk-SQL (Helper::getFIDEDatenListe/getFIDEDatenLokal).
 *
 * @property int    $id
 * @property int    $tstamp
 * @property int    $elodate
 * @property int    $fideid
 * @property string $surname
 * @property string $prename
 * @property string $country
 * @property string $title
 * @property int    $rating
 * @property string $published
 *
 * @method static WertungsportalEloModel|null findById($id, array $opt = [])
 * @method static WertungsportalEloModel|null findByPk($id, array $opt = [])
 * @method static WertungsportalEloModel|null findOneBy($col, $val, array $opt = [])
 * @method static WertungsportalEloModel|null findOneByFideid($val, array $opt = [])
 * @method static Collection|WertungsportalEloModel[]|null findAll(array $opt = [])
 * @method static Collection|WertungsportalEloModel[]|null findBy($col, $val, array $opt = [])
 * @method static integer countAll()
 * @method static integer countBy($col, $val, array $opt = [])
 */
class WertungsportalEloModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_elo';
}
