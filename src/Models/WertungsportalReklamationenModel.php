<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Model;
use Contao\Model\Collection;

/**
 * Model für die Tabelle tl_wertungsportal_reklamationen.
 *
 * Eine Zeile je Reklamation, die ein angemeldetes Mitglied über das Formular
 * im Frontend abgeschickt hat — auch dann, wenn der Mailversand scheiterte.
 * Angelegt wird sie ausschließlich von Helper\Reklamation::einreichen(); im
 * Backend lassen sich nur „erledigt" und eine Notiz pflegen.
 *
 * @property int    $id
 * @property int    $tstamp
 * @property int    $datum            Zeitpunkt des Absendens
 * @property int    $memberId         tl_member.id des Absenders
 * @property string $memberName       Vor- und Nachname zum Zeitpunkt des Absendens
 * @property string $memberEmail      Antwortadresse
 * @property string $empfaengerName   Namen der Empfänger, durch Komma getrennt
 * @property string $empfaengerEmail  Adressen der Empfänger, durch Komma getrennt
 * @property string $empfaengerArt    referent | admin
 * @property string $bcc              Adresse der Blindkopie, leer wenn keine
 * @property string $bereich          Ansicht, aus der die Reklamation kam
 * @property string $url              Adresse der Seite
 * @property string $turnierUuid      Turnier-UUID, leer außerhalb von Turnierseiten
 * @property string $turnierName
 * @property string $spielerId        NU-Nummer, leer wenn kein Spieler betroffen ist
 * @property string $spielerName
 * @property string $betreff
 * @property string $text             Text wie verschickt, samt Fußzeile
 * @property string $gesendet         '1' wenn der Versand geklappt hat
 * @property string $fehler           Meldung des Mailversands bei einem Fehlschlag
 * @property string $erledigt         '1' wenn im Backend als erledigt markiert
 * @property string $notiz            Notiz der Bearbeitung
 *
 * @method static WertungsportalReklamationenModel|null findById($id, array $opt = [])
 * @method static Collection|WertungsportalReklamationenModel[]|null findAll(array $opt = [])
 */
class WertungsportalReklamationenModel extends Model
{
    protected static $strTable = 'tl_wertungsportal_reklamationen';
}
