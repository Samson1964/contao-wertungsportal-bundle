<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

use Doctrine\DBAL\Connection;

/**
 * Lesezugriff der Ausgabe auf das Wertungsarchiv (Etappe 3, ab 2.0.0).
 *
 * Regel „jüngste Quelle" (Frank Binding, 18.11.2026): Steht ein Turnier in
 * mehreren Archiven, zeigt die Ausgabe die jüngste Fassung. Für die
 * Karteikarte heißt das: Das Wertungsportal kommt live von nu; aus dem
 * Archiv kommen nur Turniere, die keine jüngere Quelle führt —
 *
 * - ein DeWIS-Turnier entfällt, wenn ein Portalturnier darauf als Dublette
 *   zeigt (archiv_turnier.dublette_archiv = 'dewis'),
 * - ein ELOBASE-Turnier entfällt, wenn ein DeWIS-Turnier darauf zeigt,
 * - ein ELOBASE-Turnier mit dem Kennzeichen `dublette` entfällt: Es ist eine
 *   ältere Fassung desselben Turniers unter anderem Code (Rückfrage R6,
 *   20.11.2026). Anders als oben steht der Verweis hier an der Dublette
 *   selbst (`erg_dublette_von` in `extra`, Code der gültigen ZDB-Fassung) —
 *   deshalb über das Kennzeichen, nicht über dublette_quell_id, sonst
 *   verschwände die gültige Fassung.
 *
 * Die Turniere selbst liegen als JSON-Dateien unter WERTUNGSARCHIV_PFAD;
 * gelesen wird nur innerhalb dieses Ordners.
 */
class Archiv
{
    /**
     * @var Connection
     */
    private $db;

    /**
     * @var string Ordner der Übergabe (je Archiv ein Unterordner)
     */
    private $pfad;

    /**
     * Nimmt Verbindung und Ordner entgegen.
     *
     * @param Connection $db   Archivdatenbank
     * @param string     $pfad WERTUNGSARCHIV_PFAD
     */
    public function __construct(Connection $db, string $pfad)
    {
        $this->db = $db;
        $this->pfad = rtrim(str_replace('\\', '/', $pfad), '/');
    }

    /**
     * Baut den Lesezugriff aus dem Dienst Verbindung.
     *
     * @return self|null null, wenn das Archiv nicht eingerichtet ist
     */
    public static function aus(Verbindung $verbindung): ?self
    {
        return $verbindung->verfuegbar() ? new self($verbindung->db(), $verbindung->pfad()) : null;
    }

    /**
     * Nummer der Gesamtperson zu einer NU-Nummer.
     *
     * @param string $nuId NU-Nummer, z. B. „NU4005028"
     *
     * @return int|null Nummer, null wenn unbekannt
     */
    public function gesamtNachNu(string $nuId): ?int
    {
        $id = $this->db->fetchOne('SELECT id FROM archiv_gesamtperson WHERE nu_id = ? LIMIT 1', array($nuId));

        return false === $id ? null : (int) $id;
    }

    /**
     * Eine Gesamtperson.
     *
     * @param int $id Nummer der Gesamtperson
     *
     * @return array<string,mixed>|null Zeile aus archiv_gesamtperson, null wenn unbekannt
     */
    public function gesamtperson(int $id): ?array
    {
        $zeile = $this->db->fetchAssociative('SELECT * FROM archiv_gesamtperson WHERE id = ?', array($id));

        return false === $zeile ? null : $zeile;
    }

    /**
     * Karteizeilen einer Gesamtperson, die keine jüngere Quelle führt —
     * zeitlich geordnet, neueste zuerst wie die Karteikarte von nu.
     *
     * Für die Karteikarte einer Person MIT NU-Nummer kommt das Portal live
     * von nu, aus dem Archiv also nur ELOBASE und DeWIS. Für eine Person OHNE
     * NU-Nummer (Karteikarte G<Nummer>) gibt es keine Live-Daten; dann gehört
     * das Portalarchiv dazu (`$mitPortal`) — als jüngste Quelle vollständig.
     *
     * Einzelpartiesätze (ELOBASE: Bit 15 der Erfolgszahl) sind Bausteine
     * einer Auswertung, keine eigene Zeile der Karteikarte, und entfallen.
     *
     * @param int  $gesamtId  Nummer der Gesamtperson
     * @param bool $mitPortal true: auch die Karteizeilen des Portalarchivs
     *
     * @return array<int,array<string,mixed>> Karteizeile mit Turnierkopf (t_…)
     */
    public function altkartei(int $gesamtId, bool $mitPortal = false): array
    {
        $archive = $mitPortal ? "'elobase', 'dewis', 'wp'" : "'elobase', 'dewis'";

        // Die „Datenübernahme nach DeWIS" (Ausgangswerte, kein_turnier) zeigt
        // nu auf der Karteikarte selbst; ohne Portal stünde sie doppelt da
        $ohneAusgang = $mitPortal ? '' : "AND NOT (k.archiv = 'dewis' AND t.kennzeichen LIKE '%kein_turnier%')";

        $zeilen = $this->db->fetchAllAssociative(
            "SELECT k.*, p.kennung,
                    t.id AS t_id, t.quell_id AS t_quell_id, t.name AS t_name, t.ende AS t_ende, t.json AS t_json, t.kennzeichen AS t_kennzeichen
             FROM archiv_person p
             JOIN archiv_kartei k ON k.person_id = p.id
             LEFT JOIN archiv_turnier t ON t.id = k.turnier_id
             WHERE p.gesamt_id = ? AND p.archiv IN ($archive) AND k.einzelpartie = 0 $ohneAusgang
               AND NOT (k.archiv = 'dewis' AND t.id IS NOT NULL AND EXISTS (
                     SELECT 1 FROM archiv_turnier j WHERE j.dublette_archiv = 'dewis' AND j.dublette_quell_id = t.quell_id))
               AND NOT (k.archiv = 'elobase' AND t.id IS NOT NULL AND EXISTS (
                     SELECT 1 FROM archiv_turnier j WHERE j.dublette_archiv = 'elobase' AND j.dublette_quell_id = t.quell_id))
               AND NOT (k.archiv = 'elobase' AND t.id IS NOT NULL AND FIND_IN_SET('dublette', t.kennzeichen) > 0)
             ORDER BY k.id",
            array($gesamtId)
        );

        usort($zeilen, static function (array $a, array $b): int {
            return self::zeitpunkt($b) <=> self::zeitpunkt($a);
        });

        return $zeilen;
    }

    /**
     * Sortierschlüssel einer Karteizeile: Turnierende, sonst das Jahr aus dem
     * Code (ELOBASE-Einträge ohne Turnierkopf), dazu Code und ID, damit
     * gleiche Tage stabil bleiben.
     *
     * @param array<string,mixed> $zeile Karteizeile mit t_ende
     *
     * @return string Schlüssel, aufsteigend sortierbar
     */
    public static function zeitpunkt(array $zeile): string
    {
        $ende = (string) ($zeile['t_ende'] ?? $zeile['ende'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $ende)) {
            $jahr = Darstellung::jahr(null, $zeile['code'] ?? null);
            $ende = '' !== $jahr ? $jahr.'-00-00' : '0000-00-00';
        }

        return substr($ende, 0, 10).'|'.($zeile['code'] ?? '').'|'.str_pad((string) ($zeile['id'] ?? 0), 10, '0', STR_PAD_LEFT);
    }

    /**
     * Suchmuster (LIKE, Namensanfang) für einen Namen in allen Schreibweisen
     * der Archive: Portal „Müller, Klaus", DeWIS „Müller,Klaus", ELOBASE
     * „Mueller,Klaus" (Umlaute umschrieben, ß als ss).
     *
     * @param string $nachname Nachname oder sein Anfang
     * @param string $vorname  Vorname oder sein Anfang, '' = ohne
     *
     * @return array<int,string> Muster mit „%" am Ende, ohne Doppel
     */
    public static function namensmuster(string $nachname, string $vorname): array
    {
        $nachname = trim($nachname);
        $vorname = trim($vorname);

        if ('' === $nachname) {
            return array();
        }

        $umschrift = static function (string $text): string {
            return strtr($text, array('ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss'));
        };
        $maske = static function (string $text): string {
            return addcslashes($text, '%_\\');
        };

        $muster = array();

        foreach (array_unique(array($nachname, $umschrift($nachname))) as $n) {
            foreach (array_unique(array($vorname, $umschrift($vorname))) as $v) {
                if ('' === $v) {
                    $muster[] = $maske($n).'%';
                } else {
                    $muster[] = $maske($n).', '.$maske($v).'%';
                    $muster[] = $maske($n).','.$maske($v).'%';
                }
            }
        }

        return array_values(array_unique($muster));
    }

    /**
     * Sucht Gesamtpersonen OHNE NU-Nummer — Spieler, die heute nicht mehr
     * gemeldet sind und nur noch im Archiv stehen. Wer eine NU-Nummer hat,
     * erscheint in der normalen Spielersuche.
     *
     * @param string $nachname Nachname oder sein Anfang
     * @param string $vorname  Vorname oder sein Anfang
     * @param int    $grenze   Höchstzahl der Treffer
     *
     * @return array<int,array<string,mixed>> Gesamtpersonen, nach Name geordnet
     */
    public function sucheOhneNu(string $nachname, string $vorname, int $grenze = 100): array
    {
        $ids = array();

        foreach (self::namensmuster($nachname, $vorname) as $m) {
            $ids = array_merge($ids, $this->db->fetchFirstColumn(
                'SELECT DISTINCT p.gesamt_id FROM archiv_person p WHERE p.name LIKE ? AND p.gesamt_id IS NOT NULL LIMIT '.(4 * $grenze),
                array($m)
            ));
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return array();
        }

        return $this->db->fetchAllAssociative(
            'SELECT * FROM archiv_gesamtperson WHERE nu_id IS NULL AND id IN ('.implode(', ', $ids).') ORDER BY name, geburtsjahr, id LIMIT '.$grenze
        );
    }

    /**
     * NU-Nummern zu Personenkennungen eines Turniers — für die Links der
     * Teilnehmer auf ihre Karteikarte. Eine Abfrage für alle.
     *
     * @param array<int,string> $kennungen Kennungen (Z:…, D:…, N:…, W:…)
     *
     * @return array<string,string> Kennung => NU-Nummer, nur bekannte
     */
    public function nuNachKennung(array $kennungen): array
    {
        $kennungen = array_values(array_unique(array_filter(array_map('strval', $kennungen))));

        if (!$kennungen) {
            return array();
        }

        $ergebnis = array();

        foreach (array_chunk($kennungen, 1000) as $teil) {
            $zeilen = $this->db->fetchAllNumeric(
                'SELECT p.kennung, g.nu_id FROM archiv_person p JOIN archiv_gesamtperson g ON g.id = p.gesamt_id
                 WHERE g.nu_id IS NOT NULL AND p.kennung IN ('.implode(', ', array_fill(0, \count($teil), '?')).')',
                $teil
            );

            foreach ($zeilen as $z) {
                $ergebnis[(string) $z[0]] = (string) $z[1];
            }
        }

        return $ergebnis;
    }

    /**
     * Turnier nach seiner Nummer in archiv_turnier.
     *
     * @param int $id archiv_turnier.id
     *
     * @return array<string,mixed>|null Zeile, null wenn unbekannt
     */
    public function turnier(int $id): ?array
    {
        $zeile = $this->db->fetchAssociative('SELECT * FROM archiv_turnier WHERE id = ?', array($id));

        return false === $zeile ? null : $zeile;
    }

    /**
     * ELOBASE-Turnier nach seinem Code — für alte Adressen
     * `turniere/<code>.html` aus der Zeit vor DeWIS. Genau verglichen (die
     * Spalte ist utf8mb4_bin).
     *
     * @param string $code Turniercode, z. B. „9218-000-DÖB"
     *
     * @return array<string,mixed>|null Zeile, null wenn unbekannt
     */
    public function elobaseTurnier(string $code): ?array
    {
        $zeile = $this->db->fetchAssociative("SELECT * FROM archiv_turnier WHERE archiv = 'elobase' AND quell_id = ?", array($code));

        return false === $zeile ? null : $zeile;
    }

    /**
     * Liest die JSON-Datei eines Turniers.
     *
     * Der Pfad kommt aus der Datenbank; gelesen wird trotzdem nur, was nach
     * Auflösung von „..", Verknüpfungen usw. innerhalb von
     * WERTUNGSARCHIV_PFAD/<archiv>/ liegt.
     *
     * @param array<string,mixed> $turnier Zeile aus archiv_turnier
     *
     * @return array<string,mixed>|null Turnier (wertungsarchiv-turnier/1),
     *                                  null ohne JSON oder bei unlesbarer Datei
     */
    public function json(array $turnier): ?array
    {
        $relativ = (string) ($turnier['json'] ?? '');

        if ('' === $relativ || '' === $this->pfad) {
            return null;
        }

        $basis = realpath($this->pfad.'/'.$turnier['archiv']);
        $datei = realpath($this->pfad.'/'.$turnier['archiv'].'/'.$relativ);

        if (false === $basis || false === $datei || 0 !== strpos(str_replace('\\', '/', $datei), str_replace('\\', '/', $basis).'/')) {
            return null;
        }

        $daten = json_decode((string) file_get_contents($datei), true);

        return \is_array($daten) ? $daten : null;
    }
}
