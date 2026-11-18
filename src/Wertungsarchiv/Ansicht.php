<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

use Doctrine\DBAL\Connection;

/**
 * Abfragen für die Backend-Ansicht des Wertungsarchivs (ab 2.0.0).
 *
 * Getrennt vom Backend-Modul (Classes\Wertungsarchiv), damit die Abfragen
 * ohne Formular und Vorlage prüfbar bleiben. Alles liest aus der
 * Archivdatenbank; geschrieben wird nur der Status einer Kante.
 */
class Ansicht
{
    /**
     * Höchstzahl der Treffer einer Suche.
     */
    public const TREFFER = 100;

    /**
     * Erlaubte Status einer Kante.
     */
    public const STATUS = array('bestaetigt', 'abgelehnt');

    /**
     * @var Connection
     */
    private $db;

    /**
     * Nimmt die Verbindung zur Archivdatenbank entgegen.
     *
     * @param Connection $db Archivdatenbank
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Zahlen für den Überblick.
     *
     * @return array{archive:array<string,array<string,int>>,kanten:array<int,array{quelle:string,status:string,zahl:int}>,gesamt:int,pruefen:int,importe:array<string,array<string,mixed>>}
     */
    public function ueberblick(): array
    {
        $archive = array();

        foreach (Importeur::ARCHIVE as $a) {
            $archive[$a] = array('turniere' => 0, 'personen' => 0, 'kartei' => 0);
        }

        foreach (array('turniere' => 'archiv_turnier', 'personen' => 'archiv_person', 'kartei' => 'archiv_kartei') as $art => $tabelle) {
            foreach ($this->db->fetchAllNumeric('SELECT archiv, COUNT(*) FROM '.$tabelle.' GROUP BY archiv') as $z) {
                $archive[(string) $z[0]][$art] = (int) $z[1];
            }
        }

        $kanten = array();
        foreach ($this->db->fetchAllNumeric('SELECT quelle, status, COUNT(*) FROM archiv_kante GROUP BY quelle, status ORDER BY quelle, status') as $z) {
            $kanten[] = array('quelle' => (string) $z[0], 'status' => (string) $z[1], 'zahl' => (int) $z[2]);
        }

        $importe = array();
        foreach ($this->db->fetchAllNumeric("SELECT schluessel, wert FROM archiv_info WHERE schluessel LIKE 'import\\_%'") as $z) {
            $importe[substr((string) $z[0], 7)] = (array) json_decode((string) $z[1], true);
        }

        return array(
            'archive' => $archive,
            'kanten'  => $kanten,
            'gesamt'  => (int) $this->db->fetchOne('SELECT COUNT(*) FROM archiv_gesamtperson'),
            'pruefen' => (int) $this->db->fetchOne("SELECT COUNT(*) FROM archiv_gesamtperson WHERE pruefen != ''"),
            'importe' => $importe,
        );
    }

    /**
     * Deutet einen Suchbegriff.
     *
     * - „#123" oder eine reine Zahl: Nummer der Gesamtperson
     * - „Z:…", „D:…", „N:…", „W:…": Kennung einer Archivperson
     * - „NU4005028": NU-Nummer
     * - sonst: Anfang des Namens. Die Archive schreiben „Müller, Klaus"
     *   (Portal, DeWIS) und „Mueller,Klaus" (ELOBASE); gesucht wird mit und
     *   ohne Leerzeichen nach dem Komma.
     *
     * @param string $begriff Eingabe
     *
     * @return array{art:string,werte:array<int,string>} art: gesamt, kennung, nu, name, leer
     */
    public static function deute(string $begriff): array
    {
        $begriff = trim($begriff);

        if ('' === $begriff) {
            return array('art' => 'leer', 'werte' => array());
        }

        if (preg_match('/^#?(\d+)$/', $begriff, $m)) {
            return array('art' => 'gesamt', 'werte' => array($m[1]));
        }

        if (preg_match('/^[ZDNW]:\S+$/i', $begriff)) {
            return array('art' => 'kennung', 'werte' => array(strtoupper($begriff[0]).substr($begriff, 1)));
        }

        if (preg_match('/^NU\d+$/i', $begriff)) {
            return array('art' => 'nu', 'werte' => array(strtoupper($begriff)));
        }

        $mit = preg_replace('/\s*,\s*/', ', ', $begriff);
        $ohne = preg_replace('/\s*,\s*/', ',', $begriff);

        return array('art' => 'name', 'werte' => array_values(array_unique(array($mit, $ohne))));
    }

    /**
     * Sucht Gesamtpersonen.
     *
     * Namen werden in den Archivpersonen gesucht (Index auf name), nicht nur
     * im Namen der Gesamtperson: Wer in ELOBASE „Mueller,Klaus" heißt, soll
     * auch so gefunden werden.
     *
     * @param string $begriff Eingabe (siehe deute())
     *
     * @return array<int,array<string,mixed>> Gesamtpersonen, höchstens TREFFER
     */
    public function suche(string $begriff): array
    {
        $d = self::deute($begriff);
        $ids = array();

        if ('gesamt' === $d['art']) {
            $ids = array((int) $d['werte'][0]);
        } elseif ('kennung' === $d['art']) {
            $ids = $this->db->fetchFirstColumn('SELECT gesamt_id FROM archiv_person WHERE kennung = ? AND gesamt_id IS NOT NULL', $d['werte']);
        } elseif ('nu' === $d['art']) {
            $ids = $this->db->fetchFirstColumn('SELECT id FROM archiv_gesamtperson WHERE nu_id = ?', $d['werte']);
        } elseif ('name' === $d['art']) {
            foreach ($d['werte'] as $name) {
                $ids = array_merge($ids, $this->db->fetchFirstColumn(
                    'SELECT DISTINCT gesamt_id FROM archiv_person WHERE name LIKE ? AND gesamt_id IS NOT NULL LIMIT '.self::TREFFER,
                    array(addcslashes($name, '%_\\').'%')
                ));
            }
        }

        $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, self::TREFFER);

        if (!$ids) {
            return array();
        }

        return $this->db->fetchAllAssociative(
            'SELECT * FROM archiv_gesamtperson WHERE id IN ('.implode(', ', $ids).') ORDER BY name, id'
        );
    }

    /**
     * Gesamtpersonen mit Prüfvermerk.
     *
     * @return array<int,array<string,mixed>> höchstens 500
     */
    public function pruefliste(): array
    {
        return $this->db->fetchAllAssociative("SELECT * FROM archiv_gesamtperson WHERE pruefen != '' ORDER BY pruefen, name LIMIT 500");
    }

    /**
     * Abgelehnte Kanten mit den Namen beider Seiten.
     *
     * @return array<int,array<string,mixed>>
     */
    public function abgelehnt(): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT k.*, p1.name AS von_name, p1.gesamt_id AS von_gesamt, p2.name AS nach_name, p2.gesamt_id AS nach_gesamt
             FROM archiv_kante k
             LEFT JOIN archiv_person p1 ON p1.kennung = k.von
             LEFT JOIN archiv_person p2 ON p2.kennung = k.nach
             WHERE k.status = 'abgelehnt' ORDER BY k.geaendert DESC LIMIT 500"
        );
    }

    /**
     * Eine Gesamtperson mit ihren Archivpersonen und allen Kanten.
     *
     * Kanten werden über die Kennungen der Mitglieder gesucht — auch solche,
     * die aus der Gruppe hinausführen (eine abgelehnte Kante verbindet zwei
     * Gesamtpersonen nicht mehr, soll hier aber sichtbar bleiben).
     *
     * @param int $id Nummer der Gesamtperson
     *
     * @return array{gesamt:array<string,mixed>,personen:array<int,array<string,mixed>>,kanten:array<int,array<string,mixed>>}|null
     *         null, wenn es die Nummer nicht gibt
     */
    public function gesamtperson(int $id): ?array
    {
        $gesamt = $this->db->fetchAssociative('SELECT * FROM archiv_gesamtperson WHERE id = ?', array($id));

        if (false === $gesamt) {
            return null;
        }

        $personen = $this->db->fetchAllAssociative(
            "SELECT p.id, p.archiv, p.kennung, p.name, p.geschlecht, p.geburtsjahr,
                    COUNT(k.id) AS kartei, MIN(t.ende) AS erstes, MAX(t.ende) AS letztes
             FROM archiv_person p
             LEFT JOIN archiv_kartei k ON k.person_id = p.id
             LEFT JOIN archiv_turnier t ON t.id = k.turnier_id
             WHERE p.gesamt_id = ?
             GROUP BY p.id
             ORDER BY FIELD(p.archiv, 'wp', 'dewis', 'elobase'), p.kennung",
            array($id)
        );

        $kennungen = array_column($personen, 'kennung');
        $kanten = array();

        if ($kennungen) {
            $platz = implode(', ', array_fill(0, \count($kennungen), '?'));
            $kanten = $this->db->fetchAllAssociative(
                "SELECT k.*, p1.name AS von_name, p1.gesamt_id AS von_gesamt, p2.name AS nach_name, p2.gesamt_id AS nach_gesamt
                 FROM archiv_kante k
                 LEFT JOIN archiv_person p1 ON p1.kennung = k.von
                 LEFT JOIN archiv_person p2 ON p2.kennung = k.nach
                 WHERE k.von IN ($platz) OR k.nach IN ($platz)
                 ORDER BY k.status DESC, k.quelle, k.von",
                array_merge($kennungen, $kennungen)
            );
        }

        return array('gesamt' => $gesamt, 'personen' => $personen, 'kanten' => $kanten);
    }

    /**
     * Setzt den Status einer Kante.
     *
     * @param int    $id     ID der Kante
     * @param string $status „bestaetigt" oder „abgelehnt"
     *
     * @throws \InvalidArgumentException bei unbekanntem Status
     *
     * @return array<string,mixed>|null Die Kante vor der Änderung, null wenn es sie nicht gibt
     */
    public function setzeStatus(int $id, string $status): ?array
    {
        if (!\in_array($status, self::STATUS, true)) {
            throw new \InvalidArgumentException('Unbekannter Status: '.$status);
        }

        $kante = $this->db->fetchAssociative('SELECT * FROM archiv_kante WHERE id = ?', array($id));

        if (false === $kante) {
            return null;
        }

        $this->db->executeStatement('UPDATE archiv_kante SET status = ?, geaendert = ? WHERE id = ?', array($status, time(), $id));

        return $kante;
    }
}
