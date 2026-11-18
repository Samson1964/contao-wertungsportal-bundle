<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

use Doctrine\DBAL\Connection;

/**
 * Übernimmt die Listen eines Archivs in die Archivdatenbank (ab 2.0.0).
 *
 * Gelesen wird nur die Übergabe in `<WERTUNGSARCHIV_PFAD>/<archiv>/`
 * (UEBERGABE.md): `turniere.jsonl`, `personen.jsonl`, `kartei.jsonl`. Die
 * Turnier-JSON bleiben, wo sie sind; die Datenbank merkt sich nur ihren Pfad.
 *
 * Grundsatz (Frank Binding, 17.11.2026): Die Daten bleiben unverändert, sie
 * werden höchstens ergänzt. Der Import rechnet nichts um. Jedes Feld kommt
 * entweder in eine gleichnamige bzw. in FELDER benannte Spalte oder
 * unverändert in `extra`.
 *
 * Wiederholbar: Turniere und Personen über ihren Schlüssel
 * (INSERT … ON DUPLICATE KEY UPDATE, die IDs bleiben), die Kartei eines
 * Archivs wird ersetzt (sie hat in den Quellen keinen gemeinsamen Schlüssel,
 * und ein neuer Abzug des Wertungsportals bringt sie ohnehin ganz neu).
 */
class Importeur
{
    /**
     * Die drei Archive in der Reihenfolge ihres Alters.
     */
    public const ARCHIVE = array('elobase', 'dewis', 'wp');

    /**
     * Zeilen je INSERT.
     */
    public const BLOCK = 1000;

    /**
     * Spalte der Tabelle => Feld der Liste, je Archiv und Liste. Was hier
     * nicht steht, wandert nach `extra`. Felder, die nur zum Verknüpfen
     * dienen (Schlüssel, Brücke), stehen in SCHLUESSEL bzw. werden eigens
     * behandelt und ebenfalls nach `extra` geschrieben.
     */
    public const FELDER = array(
        'turniere' => array(
            'elobase' => array('quell_id' => 'code', 'code' => 'code', 'name' => 'name', 'art' => 'art', 'ende' => 'ende', 'jahr' => 'jahr', 'spieler' => 'spieler', 'partien' => 'partien', 'runden' => 'runden', 'json' => 'json', 'rekonstruiert' => 'rekonstruiert', 'kennzeichen' => 'kennzeichen'),
            'dewis'   => array('quell_id' => 'turnier', 'code' => 'code', 'name' => 'name', 'art' => 'art', 'ende' => 'ende', 'jahr' => 'jahr', 'verband' => 'verband', 'spieler' => 'spieler', 'partien' => 'partien', 'json' => 'json', 'kennzeichen' => 'kennzeichen'),
            'wp'      => array('quell_id' => 'turnier', 'code' => 'erg_dewis_code', 'name' => 'name', 'art' => 'art', 'ende' => 'ende', 'jahr' => 'jahr', 'verband' => 'verband', 'spieler' => 'spieler', 'partien' => 'partien', 'runden' => 'runden', 'json' => 'json', 'kennzeichen' => 'kennzeichen'),
        ),
        'personen' => array(
            'elobase' => array('kennung' => 'person', 'name' => 'name', 'geschlecht' => 'geschlecht'),
            'dewis'   => array('kennung' => 'person', 'name' => 'name', 'geschlecht' => 'geschlecht', 'geburtsjahr' => 'geburtsjahr'),
            'wp'      => array('kennung' => 'person', 'name' => 'name', 'geburtsjahr' => 'geburtsjahr'),
        ),
        'kartei' => array(
            'elobase' => array('code' => 'code', 'quelle' => 'quelle', 'partien' => 'partien', 'punkte_halbe' => 'punkte_halbe', 'dwz_vorher' => 'erg_dwz_vorher', 'index_vorher' => 'erg_index_vorher', 'dwz' => 'dwz', 'dwz_index' => 'dwz_index', 'we1000' => 'we', 'leistung' => 'erfolg', 'einzelpartie' => 'erg_einzelpartie', 'vor_erster_anmeldung' => 'erg_vor_erster_anmeldung'),
            'dewis'   => array('code' => 'code', 'ende' => 'ende', 'partien' => 'partien', 'punkte_halbe' => 'punkte_halbe', 'dwz_vorher' => 'dwz_vorher', 'index_vorher' => 'index_vorher', 'dwz' => 'dwz', 'dwz_index' => 'dwz_index', 'we1000' => 'we', 'turnierleistung' => 'turnierleistung', 'gegnerschnitt' => 'gegnerschnitt', 'vor_erster_anmeldung' => 'erg_vor_erster_anmeldung'),
            'wp'      => array('ende' => 'ende', 'partien' => 'partien', 'punkte_halbe' => 'punkte_halbe', 'dwz_vorher' => 'dwz_vorher', 'index_vorher' => 'index_vorher', 'dwz' => 'dwz', 'dwz_index' => 'dwz_index', 'we1000' => 'we1000', 'turnierleistung' => 'turnierleistung', 'gegnerschnitt' => 'gegnerschnitt'),
        ),
    );

    /**
     * Feld der Kartei, das auf das Turnier zeigt (quell_id in archiv_turnier).
     */
    public const KARTEI_TURNIER = array('elobase' => 'code', 'dewis' => 'turnier', 'wp' => 'turnier');

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var callable|null Rückmeldung (Text) für den Fortschritt
     */
    private $melder;

    /**
     * Nimmt die Verbindung zur Archivdatenbank entgegen.
     *
     * @param Connection    $db     Archivdatenbank (Verbindung::db())
     * @param callable|null $melder Erhält Fortschrittstexte, z. B. für die Konsole
     */
    public function __construct(Connection $db, ?callable $melder = null)
    {
        $this->db = $db;
        $this->melder = $melder;
    }

    /**
     * Ordnet die Werte einer Zeile den Spalten zu.
     *
     * Kennzeichen-Listen werden durch Kommas getrennter Text, Wahrheitswerte
     * 0/1, Datumsangaben auf JJJJ-MM-TT gekürzt (die Übergabe schreibt
     * „… 16:08:24" nur bei Zeitpunkten, die hier keine Spalte haben). Alles
     * Übrige — auch die Felder, die eine Spalte haben, aber anders heißen —
     * bleibt vollständig erhalten: nicht zugeordnete Felder in `extra`.
     *
     * @param array<string,string>   $felder Spalte => Feld (aus FELDER)
     * @param array<string,mixed>    $zeile  Feld => Wert der Liste
     * @param array<int,string>      $ohne   Felder, die weder Spalte noch extra werden
     *
     * @return array<string,mixed> Spalte => Wert, dazu 'extra' (JSON oder null)
     */
    public static function spalten(array $felder, array $zeile, array $ohne = array()): array
    {
        $werte = array();
        $benutzt = array_flip($ohne);

        foreach ($felder as $spalte => $feld) {
            $wert = $zeile[$feld] ?? null;
            $benutzt[$feld] = true;

            if (\is_array($wert)) {
                $wert = implode(',', array_map('strval', $wert));
            } elseif (\is_bool($wert)) {
                $wert = $wert ? 1 : 0;
            }

            if (('ende' === $spalte) && \is_string($wert)) {
                $wert = substr($wert, 0, 10);
            }

            $werte[$spalte] = $wert;
        }

        $extra = array();

        foreach ($zeile as $feld => $wert) {
            if (!isset($benutzt[$feld]) && null !== $wert) {
                $extra[$feld] = $wert;
            }
        }

        $werte['extra'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        return $werte;
    }

    /**
     * Bildet die Personenkennung aus einem Brückenwert der Übergabe.
     *
     * Die Listen schreiben Brücken als Kennung („D:10000004", „Z:6988807"),
     * ELOBASE die DeWIS-ID aber als Zahl (`dewis_id`).
     *
     * @param mixed  $wert    Wert aus der Liste
     * @param string $praefix Präfix für reine Zahlen, z. B. „D"
     *
     * @return string|null Kennung, null ohne Wert
     */
    public static function kennung($wert, string $praefix): ?string
    {
        if (null === $wert || '' === $wert) {
            return null;
        }

        $wert = (string) $wert;

        return preg_match('/^[A-Z]:/', $wert) ? $wert : $praefix.':'.$wert;
    }

    /**
     * Liest die Kanten einer Personenzeile: jüngeres Archiv → älteres.
     *
     * - ELOBASE `dewis_id` und DeWIS `erg_elobase_person`: DeWIS → ELOBASE,
     *   Quelle „pkz" (Tabelle pkz, 1:1). Beide Listen führen dieselbe Kante;
     *   über den eindeutigen Schlüssel entsteht sie nur einmal.
     * - Wertungsportal `erg_dewis_person`: Portal → DeWIS, Quelle laut
     *   `erg_dewis_quelle` („turniere", „externenr"), Gewicht = Belege.
     * - Wertungsportal `erg_dewis_dublette`: Portal → zweite DeWIS-Person,
     *   Quelle „dublette" (dieselbe Person, in DeWIS doppelt angelegt).
     *
     * @param string              $archiv Kennung des Archivs
     * @param array<string,mixed> $zeile  Personenzeile
     *
     * @return array<int,array{von:string,nach:string,quelle:string,gewicht:int|null}>
     */
    public static function kanten(string $archiv, array $zeile): array
    {
        $kanten = array();
        $person = (string) $zeile['person'];

        if ('elobase' === $archiv && null !== ($d = self::kennung($zeile['dewis_id'] ?? null, 'D'))) {
            $kanten[] = array('von' => $d, 'nach' => $person, 'quelle' => 'pkz', 'gewicht' => null);
        }

        if ('dewis' === $archiv && null !== ($z = self::kennung($zeile['erg_elobase_person'] ?? null, 'Z'))) {
            $kanten[] = array('von' => $person, 'nach' => $z, 'quelle' => 'pkz', 'gewicht' => null);
        }

        if ('wp' === $archiv) {
            if (null !== ($d = self::kennung($zeile['erg_dewis_person'] ?? null, 'D'))) {
                $kanten[] = array('von' => $person, 'nach' => $d, 'quelle' => (string) ($zeile['erg_dewis_quelle'] ?? 'turniere'), 'gewicht' => isset($zeile['erg_dewis_belege']) ? (int) $zeile['erg_dewis_belege'] : null);
            }

            if (null !== ($d2 = self::kennung($zeile['erg_dewis_dublette'] ?? null, 'D'))) {
                $kanten[] = array('von' => $person, 'nach' => $d2, 'quelle' => 'dublette', 'gewicht' => null);
            }
        }

        return $kanten;
    }

    /**
     * Übernimmt die Turnierliste eines Archivs.
     *
     * Dubletten werden als Verweis auf das ältere Archiv eingetragen:
     * DeWIS → ELOBASE über den Code (`erg_auch_in_elobase`), Portal → DeWIS
     * über `erg_dewis_turnier`.
     *
     * @param string $archiv Kennung des Archivs
     * @param string $ordner Ordner der Übergabe dieses Archivs
     *
     * @return int Zahl der übernommenen Zeilen
     */
    public function turniere(string $archiv, string $ordner): int
    {
        $felder = self::FELDER['turniere'][$archiv];
        $block = array();
        $zahl = 0;

        foreach (Liste::zeilen($ordner.'/turniere.jsonl') as $zeile) {
            $w = self::spalten($felder, $zeile);
            $w['archiv'] = $archiv;
            $w['quell_id'] = (string) $w['quell_id'];
            $w['rekonstruiert'] = (int) ($w['rekonstruiert'] ?? 0);
            $w['kennzeichen'] = (string) ($w['kennzeichen'] ?? '');
            $w['dublette_archiv'] = null;
            $w['dublette_quell_id'] = null;

            if ('dewis' === $archiv && !empty($zeile['erg_auch_in_elobase']) && null !== $zeile['code']) {
                $w['dublette_archiv'] = 'elobase';
                $w['dublette_quell_id'] = (string) $zeile['code'];
            } elseif ('wp' === $archiv && null !== ($zeile['erg_dewis_turnier'] ?? null)) {
                $w['dublette_archiv'] = 'dewis';
                $w['dublette_quell_id'] = (string) $zeile['erg_dewis_turnier'];
            }

            $block[] = $w;
            ++$zahl;

            if (\count($block) >= self::BLOCK) {
                $this->schreibe('archiv_turnier', $block, true);
                $block = array();
                $this->melde('Turniere: '.$zahl);
            }
        }

        $this->schreibe('archiv_turnier', $block, true);

        return $zahl;
    }

    /**
     * Übernimmt die Personen eines Archivs samt ihren Kanten.
     *
     * Ein Hashname überschreibt nie einen Klarnamen (UEBERGABE.md, 3): Die
     * Übergabe führt in `personen.name` schon den jüngsten Klarnamen; beim
     * Aktualisieren bleibt ein vorhandener Name stehen, wenn die neue Zeile
     * keinen hat.
     *
     * @param string $archiv Kennung des Archivs
     * @param string $ordner Ordner der Übergabe dieses Archivs
     *
     * @return array{personen:int,kanten:int}
     */
    public function personen(string $archiv, string $ordner): array
    {
        $felder = self::FELDER['personen'][$archiv];
        $personen = array();
        $kanten = array();
        $zahl = array('personen' => 0, 'kanten' => 0);
        $jetzt = time();

        foreach (Liste::zeilen($ordner.'/personen.jsonl') as $zeile) {
            $w = self::spalten($felder, $zeile);
            $w['archiv'] = $archiv;
            $w['geburtsjahr'] = is_numeric($w['geburtsjahr'] ?? null) ? (int) $w['geburtsjahr'] : null;
            $personen[] = $w;
            ++$zahl['personen'];

            foreach (self::kanten($archiv, $zeile) as $k) {
                $kanten[] = $k + array('status' => 'bestaetigt', 'geaendert' => $jetzt);
                ++$zahl['kanten'];
            }

            if (\count($personen) >= self::BLOCK) {
                $this->schreibe('archiv_person', $personen, true, array('name'));
                $personen = array();
                $this->melde('Personen: '.$zahl['personen']);
            }

            if (\count($kanten) >= self::BLOCK) {
                $this->schreibe('archiv_kante', $kanten, true, array(), array('status', 'geaendert'));
                $kanten = array();
            }
        }

        $this->schreibe('archiv_person', $personen, true, array('name'));
        $this->schreibe('archiv_kante', $kanten, true, array(), array('status', 'geaendert'));

        return $zahl;
    }

    /**
     * Ersetzt die Kartei eines Archivs.
     *
     * Person und Turnier werden über ihre Kennung bzw. quell_id in IDs der
     * Archivtabellen übersetzt; Personen und Turniere müssen also vorher
     * übernommen sein. Eine Zeile ohne bekannte Person bricht ab (die
     * Prüfung der Aufbereitung schließt das aus); ein unbekanntes Turnier
     * bleibt null (DeWIS: 47 Auswertungszeilen ohne Turnier).
     *
     * @param string $archiv Kennung des Archivs
     * @param string $ordner Ordner der Übergabe dieses Archivs
     *
     * @throws \RuntimeException wenn eine Person der Kartei unbekannt ist
     *
     * @return int Zahl der übernommenen Zeilen
     */
    public function kartei(string $archiv, string $ordner): int
    {
        $felder = self::FELDER['kartei'][$archiv];
        $turnierFeld = self::KARTEI_TURNIER[$archiv];

        // Turniere des Archivs auf einmal: höchstens rund 100.000 kurze Zeilen
        $turniere = array();
        foreach ($this->db->fetchAllNumeric('SELECT quell_id, id FROM archiv_turnier WHERE archiv = ?', array($archiv)) as $t) {
            $turniere[(string) $t[0]] = (int) $t[1];
        }

        $this->leereKartei($archiv);

        $block = array();
        $zahl = 0;

        foreach (Liste::zeilen($ordner.'/kartei.jsonl') as $zeile) {
            $w = self::spalten($felder, $zeile, array('person'));
            $w['archiv'] = $archiv;
            $w['einzelpartie'] = (int) ($w['einzelpartie'] ?? 0);
            $w['turnier_id'] = $turniere[(string) ($zeile[$turnierFeld] ?? '')] ?? null;
            $w['_person'] = (string) $zeile['person'];
            $block[] = $w;
            ++$zahl;

            if (\count($block) >= self::BLOCK) {
                $this->schreibeKartei($block);
                $block = array();

                if (0 === $zahl % 100000) {
                    $this->melde('Kartei: '.$zahl);
                }
            }
        }

        $this->schreibeKartei($block);

        return $zahl;
    }

    /**
     * Löscht die Kartei eines Archivs in Paketen — ein einziges DELETE über
     * Millionen Zeilen hielte die Datenbank minutenlang in einer Transaktion.
     *
     * @param string $archiv Kennung des Archivs
     *
     * @return void
     */
    private function leereKartei(string $archiv): void
    {
        do {
            $weg = $this->db->executeStatement('DELETE FROM archiv_kartei WHERE archiv = ? LIMIT 50000', array($archiv));
        } while ($weg > 0);
    }

    /**
     * Übersetzt die Personenkennungen eines Karteiblocks und schreibt ihn.
     *
     * @param array<int,array<string,mixed>> $block Zeilen mit `_person`
     *
     * @throws \RuntimeException wenn eine Kennung unbekannt ist
     *
     * @return void
     */
    private function schreibeKartei(array $block): void
    {
        if (!$block) {
            return;
        }

        $kennungen = array_values(array_unique(array_column($block, '_person')));
        $ids = array();

        foreach ($this->db->fetchAllNumeric('SELECT kennung, id FROM archiv_person WHERE kennung IN (?'.str_repeat(', ?', \count($kennungen) - 1).')', $kennungen) as $p) {
            $ids[(string) $p[0]] = (int) $p[1];
        }

        foreach ($block as $i => $w) {
            if (!isset($ids[$w['_person']])) {
                throw new \RuntimeException('Person der Kartei unbekannt: '.$w['_person'].' — zuerst die Personen übernehmen.');
            }

            $block[$i]['person_id'] = $ids[$w['_person']];
            unset($block[$i]['_person']);
        }

        $this->schreibe('archiv_kartei', $block, false);
    }

    /**
     * Schreibt Zeilen mit einem einzigen INSERT.
     *
     * @param string                         $tabelle  Zieltabelle
     * @param array<int,array<string,mixed>> $zeilen   Spalte => Wert, alle mit denselben Spalten
     * @param bool                           $upsert   true: vorhandene Schlüssel aktualisieren
     * @param array<int,string>              $behalten Spalten, deren alter Wert bleibt, wenn der neue NULL ist
     * @param array<int,string>              $nurNeu   Spalten, die beim Aktualisieren unberührt bleiben
     *
     * @return void
     */
    private function schreibe(string $tabelle, array $zeilen, bool $upsert, array $behalten = array(), array $nurNeu = array()): void
    {
        if (!$zeilen) {
            return;
        }

        $spalten = array_keys($zeilen[0]);
        $platz = '('.implode(', ', array_fill(0, \count($spalten), '?')).')';
        $werte = array();

        foreach ($zeilen as $z) {
            foreach ($spalten as $s) {
                $werte[] = $z[$s] ?? null;
            }
        }

        $sql = 'INSERT INTO '.$tabelle.' ('.implode(', ', $spalten).') VALUES '.implode(', ', array_fill(0, \count($zeilen), $platz));

        if ($upsert) {
            $aktualisieren = array();

            foreach ($spalten as $s) {
                if (\in_array($s, $nurNeu, true)) {
                    continue;
                }

                $aktualisieren[] = \in_array($s, $behalten, true) ? $s.' = COALESCE(VALUES('.$s.'), '.$s.')' : $s.' = VALUES('.$s.')';
            }

            $sql .= ' ON DUPLICATE KEY UPDATE '.implode(', ', $aktualisieren);
        }

        $this->db->executeStatement($sql, $werte);
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
