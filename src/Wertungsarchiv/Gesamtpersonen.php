<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

use Doctrine\DBAL\Connection;

/**
 * Bildet Gesamtpersonen aus den Kanten der Brücke (Etappe 2, ab 2.0.0).
 *
 * Jedes Archiv hat eigene Personenkennungen (Z:, D:, N:, W:). Die Kanten in
 * archiv_kante verbinden sie; jede zusammenhängende Gruppe über BESTÄTIGTE
 * Kanten ist eine Gesamtperson. Alle Kanten der Übergabe gelten als
 * bestätigt (Frank Binding, 18.11.2026); wird eine abgelehnt, löst der
 * nächste Lauf die Gruppe wieder auf — deshalb wird nie etwas fest
 * zusammengeführt, sondern jedes Mal neu gebildet.
 *
 * Die Nummer einer Gesamtperson ist die kleinste archiv_person.id ihrer
 * Gruppe: Personen behalten ihre ID beim erneuten Import, also behält auch
 * die Gesamtperson ihre Nummer, solange sich die Gruppe nicht ändert.
 *
 * Wer keine Gesamtperson bekommt: `W:`-Kennungen ohne Kante. Sie gelten nur
 * für eine einzige Turnierzeile des Portals (meist ein Hashname), eine
 * Gesamtperson lohnt dort nicht (UEBERGABE.md, Abschnitt 3).
 */
class Gesamtpersonen
{
    /**
     * Zeilen je Seite beim Lesen und Schreiben. PDO-MySQL puffert jedes
     * Ergebnis vollständig — 1,9 Mio. Zeilen auf einmal kosteten mehrere
     * hundert MB.
     */
    public const SEITE = 100000;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var callable|null Rückmeldung (Text) für den Fortschritt
     */
    private $melder;

    /**
     * @var array<int,int> Union-Find: Person-ID => Eltern-ID (nur Personen mit Kante)
     */
    private $eltern = array();

    /**
     * Nimmt die Verbindung zur Archivdatenbank entgegen.
     *
     * @param Connection    $db     Archivdatenbank
     * @param callable|null $melder Erhält Fortschrittstexte
     */
    public function __construct(Connection $db, ?callable $melder = null)
    {
        $this->db = $db;
        $this->melder = $melder;
    }

    /**
     * Sucht die Wurzel einer Person und kürzt den Weg dorthin.
     *
     * @param int $id Person-ID
     *
     * @return int ID der Wurzel
     */
    public function wurzel(int $id): int
    {
        $w = $id;

        while (isset($this->eltern[$w]) && $this->eltern[$w] !== $w) {
            $w = $this->eltern[$w];
        }

        // Weg verkürzen, damit spätere Suchen kurz bleiben
        while (isset($this->eltern[$id]) && $this->eltern[$id] !== $w) {
            $naechste = $this->eltern[$id];
            $this->eltern[$id] = $w;
            $id = $naechste;
        }

        return $w;
    }

    /**
     * Verbindet zwei Personen. Die kleinere ID wird Wurzel — so ist die
     * Wurzel einer Gruppe stets ihre kleinste ID.
     *
     * @param int $a Person-ID
     * @param int $b Person-ID
     *
     * @return void
     */
    public function verbinde(int $a, int $b): void
    {
        $this->eltern[$a] = $this->eltern[$a] ?? $a;
        $this->eltern[$b] = $this->eltern[$b] ?? $b;
        $wa = $this->wurzel($a);
        $wb = $this->wurzel($b);

        if ($wa === $wb) {
            return;
        }

        if ($wa < $wb) {
            $this->eltern[$wb] = $wa;
        } else {
            $this->eltern[$wa] = $wb;
        }
    }

    /**
     * Bildet alle Gesamtpersonen neu.
     *
     * 1. Bestätigte Kanten seitenweise lesen und verbinden.
     * 2. Jeder Person ihre Gesamtperson zuschreiben (archiv_person.gesamt_id):
     *    Personen mit Kante die Wurzel ihrer Gruppe, Personen ohne Kante sich
     *    selbst — außer `W:` ohne Kante (null).
     * 3. archiv_gesamtperson neu aufbauen: Name und Geburtsjahr zuerst aus dem
     *    Wertungsportal (`N:`), dann DeWIS, dann ELOBASE; Prüfvermerke bei
     *    mehreren NU-IDs, ZDB- oder DeWIS-Personen in einer Gruppe.
     *
     * @return array{kanten:int,gesamtpersonen:int,ohne:int,pruefen:array<string,int>,groesste:array<int,array{id:int,personen:int}>}
     */
    public function bilden(): array
    {
        $this->eltern = array();
        $kanten = 0;
        $letzte = 0;

        do {
            $seite = $this->db->fetchAllNumeric(
                "SELECT k.id, p1.id, p2.id FROM archiv_kante k
                 JOIN archiv_person p1 ON p1.kennung = k.von
                 JOIN archiv_person p2 ON p2.kennung = k.nach
                 WHERE k.status = 'bestaetigt' AND k.id > ? ORDER BY k.id LIMIT ".self::SEITE,
                array($letzte)
            );

            foreach ($seite as $k) {
                $this->verbinde((int) $k[1], (int) $k[2]);
                $letzte = (int) $k[0];
                ++$kanten;
            }

            $this->melde('Kanten: '.$kanten);
        } while (\count($seite) === self::SEITE);

        // Zuordnung über eine Hilfstabelle: 1,9 Mio. einzelne UPDATEs dauerten
        // Stunden, ein UPDATE mit JOIN Sekunden
        $this->db->executeStatement('DROP TABLE IF EXISTS archiv_tmp_gesamt');
        $this->db->executeStatement('CREATE TABLE archiv_tmp_gesamt (person_id INT UNSIGNED NOT NULL PRIMARY KEY, gesamt_id INT UNSIGNED DEFAULT NULL) ENGINE = InnoDB');

        $letzte = 0;
        $ohne = 0;

        do {
            $seite = $this->db->fetchAllNumeric('SELECT id, kennung FROM archiv_person WHERE id > ? ORDER BY id LIMIT '.self::SEITE, array($letzte));
            $werte = array();

            foreach ($seite as $p) {
                $id = (int) $p[0];
                $letzte = $id;

                if (isset($this->eltern[$id])) {
                    $gesamt = $this->wurzel($id);
                } elseif (0 === strncmp((string) $p[1], 'W:', 2)) {
                    $gesamt = null;
                    ++$ohne;
                } else {
                    $gesamt = $id;
                }

                $werte[] = $id;
                $werte[] = $gesamt;
            }

            foreach (array_chunk($werte, 2000) as $teil) {
                $this->db->executeStatement('INSERT INTO archiv_tmp_gesamt (person_id, gesamt_id) VALUES '.implode(', ', array_fill(0, \count($teil) / 2, '(?, ?)')), $teil);
            }

            $this->melde('Personen: '.$letzte);
        } while (\count($seite) === self::SEITE);

        $this->db->executeStatement('UPDATE archiv_person p JOIN archiv_tmp_gesamt t ON t.person_id = p.id SET p.gesamt_id = t.gesamt_id');
        $this->db->executeStatement('DROP TABLE archiv_tmp_gesamt');
        $this->eltern = array();

        $this->db->executeStatement('DELETE FROM archiv_gesamtperson');
        $this->db->executeStatement(
            "INSERT INTO archiv_gesamtperson (id, nu_id, name, geburtsjahr, personen, archive, pruefen)
             SELECT gesamt_id,
                    MAX(CASE WHEN kennung LIKE 'N:%' THEN SUBSTRING(kennung, 3) END),
                    COALESCE(MAX(CASE WHEN kennung LIKE 'N:%' THEN name END), MAX(CASE WHEN archiv = 'dewis' THEN name END),
                             MAX(CASE WHEN archiv = 'elobase' THEN name END), MAX(CASE WHEN archiv = 'wp' THEN name END)),
                    COALESCE(MAX(CASE WHEN kennung LIKE 'N:%' THEN geburtsjahr END), MAX(CASE WHEN archiv = 'dewis' THEN geburtsjahr END)),
                    COUNT(*),
                    GROUP_CONCAT(DISTINCT archiv ORDER BY FIELD(archiv, 'elobase', 'dewis', 'wp') SEPARATOR ','),
                    CONCAT_WS(',',
                        IF(SUM(kennung LIKE 'N:%') > 1, 'mehrere_nu', NULL),
                        IF(SUM(archiv = 'elobase') > 1, 'mehrere_zdb', NULL),
                        IF(SUM(archiv = 'dewis') > 1, 'mehrere_dewis', NULL))
             FROM archiv_person WHERE gesamt_id IS NOT NULL GROUP BY gesamt_id"
        );

        $pruefen = array();
        foreach ($this->db->fetchAllNumeric("SELECT pruefen, COUNT(*) FROM archiv_gesamtperson WHERE pruefen != '' GROUP BY pruefen") as $z) {
            $pruefen[(string) $z[0]] = (int) $z[1];
        }

        $groesste = array();
        foreach ($this->db->fetchAllNumeric('SELECT id, personen FROM archiv_gesamtperson ORDER BY personen DESC LIMIT 5') as $z) {
            $groesste[] = array('id' => (int) $z[0], 'personen' => (int) $z[1]);
        }

        return array(
            'kanten'         => $kanten,
            'gesamtpersonen' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM archiv_gesamtperson'),
            'ohne'           => $ohne,
            'pruefen'        => $pruefen,
            'groesste'       => $groesste,
        );
    }

    /**
     * Gibt einen Fortschrittstext an den Melder weiter.
     *
     * @param string $text Text
     *
     * @return void
     */
    private function melde(string $text): void
    {
        if (null !== $this->melder) {
            ($this->melder)($text);
        }
    }
}
