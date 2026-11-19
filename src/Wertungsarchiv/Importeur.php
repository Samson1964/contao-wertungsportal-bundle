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
     *
     * Kartei ELOBASE: `erg_turnierleistung`, `erg_gegnerschnitt` (und
     * `erg_e_koeffizient` in `extra`) liefert die Aufbereitung erst auf
     * Rückfrage R5 aus der Turnierdatei nach; fehlen sie, bleiben die
     * Spalten leer.
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
            'elobase' => array('code' => 'code', 'quelle' => 'quelle', 'partien' => 'partien', 'punkte_halbe' => 'punkte_halbe', 'dwz_vorher' => 'erg_dwz_vorher', 'index_vorher' => 'erg_index_vorher', 'dwz' => 'dwz', 'dwz_index' => 'dwz_index', 'we1000' => 'we', 'leistung' => 'erfolg', 'turnierleistung' => 'erg_turnierleistung', 'gegnerschnitt' => 'erg_gegnerschnitt', 'einzelpartie' => 'erg_einzelpartie', 'vor_erster_anmeldung' => 'erg_vor_erster_anmeldung'),
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
     * Liest die Kante einer Zeile aus `dewis_ersatz.jsonl` (ELOBASE, R1).
     *
     * Die Liste führt ELOBASE-Personen, deren DeWIS-ID (Tabelle pkz) in
     * DeWIS nicht mehr vorkommt, weil DeWIS Personen zusammengelegt hat.
     * Ist eine eindeutige Ersatz-ID gefunden (`erg_ersatz`), entsteht die
     * Kante DeWIS-Ersatzperson → ELOBASE-Person mit der Quelle
     * „dewis_ersatz" (Franks Entscheidung vom 19.11.2026). Über die Quelle
     * lässt sich die Kante gezielt ablehnen oder zurücknehmen. Zeigen zwei
     * ELOBASE-Personen auf dieselbe Ersatz-ID (`erg_ersatz_auch_fuer`), ist
     * das gewollt: Tippfehler-Dubletten der ZDB, die DeWIS zusammengelegt
     * hat. Schwache Kandidaten (`erg_kandidaten`) werden nie übernommen.
     *
     * @param array<string,mixed> $zeile Zeile der Ersatzliste
     *
     * @return array<int,array{von:string,nach:string,quelle:string,gewicht:int|null}> Keine oder eine Kante
     */
    public static function ersatzKanten(array $zeile): array
    {
        $d = self::kennung($zeile['erg_ersatz'] ?? null, 'D');

        if (null === $d || empty($zeile['person'])) {
            return array();
        }

        return array(array('von' => $d, 'nach' => (string) $zeile['person'], 'quelle' => self::QUELLE_ERSATZ, 'gewicht' => null));
    }

    /**
     * Quelle der Kanten aus `dewis_ersatz.jsonl`; Kanten dieser Quelle führt
     * allein der ELOBASE-Import nach (auch „entfallen").
     */
    public const QUELLE_ERSATZ = 'dewis_ersatz';

    /**
     * Prüft, ob ein Name ein Klarname ist.
     *
     * Kein Klarname sind: leere Werte, Werte ohne einen einzigen Buchstaben
     * (die Übergabe führt drei Portalpersonen mit dem Namen „,") und die
     * Hashnamen des Portals („0280F8ACBD6337FC, 3F54FDEBA5269772"). Die
     * Aufbereitung setzt Hashnamen schon auf null; geprüft wird trotzdem,
     * weil die Regel „ein Hashname überschreibt nie einen Klarnamen"
     * (UEBERGABE.md, 3) auch für jeden künftigen Abzug gelten muss.
     *
     * @param mixed $name Name aus der Personenliste
     *
     * @return string|null Der Name, oder null, wenn er kein Klarname ist
     */
    public static function klarname($name): ?string
    {
        if (!\is_string($name) || !preg_match('/\p{L}/u', $name) || preg_match('/^[0-9A-F]{16}\s*,\s*[0-9A-F]{16}$/', trim($name))) {
            return null;
        }

        return $name;
    }

    /**
     * Präfixe der Personenkennungen, deren Kanten ein Archiv selbst führt
     * (Kante „von" = Person dieses Archivs). Nur diese Kanten kann ein neuer
     * Abzug für entfallen erklären. Die PKZ-Kanten stehen in zwei Listen
     * (ELOBASE und DeWIS) und bleiben deshalb außen vor, ebenso die
     * Ersatzkanten (QUELLE_ERSATZ), die nur der ELOBASE-Import führt.
     */
    public const EIGENE_KANTEN = array('elobase' => array(), 'dewis' => array('D:'), 'wp' => array('N:', 'W:'));

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
     * keinen Klarnamen hat (klarname()).
     *
     * Kanten (monatlicher Abzug, Etappe 4): Jede gelesene Kante bekommt den
     * Zeitpunkt dieses Laufs in `gesehen`. Eigene Kanten des Archivs
     * (EIGENE_KANTEN), die der neue Abzug nicht mehr führt, werden danach
     * „entfallen" — sonst hielte eine geänderte Brücke (W:x → D:a, jetzt
     * W:x → D:b) über die alte Kante zwei fremde Personen zusammen. Eine
     * entfallene Kante, die wieder auftaucht, gilt wieder als bestätigt.
     * Von Hand abgelehnte Kanten bleiben abgelehnt. Gelöscht wird nichts.
     *
     * ELOBASE liest zusätzlich `dewis_ersatz.jsonl` (ersatzKanten()). Diese
     * Kanten beginnen bei einer DeWIS-Person, gehören aber dem ELOBASE-Import:
     * Der DeWIS-Import lässt sie (wie die pkz-Kanten) beim Nachführen aus,
     * der ELOBASE-Import erklärt nicht mehr geführte für entfallen.
     * Das Nachführen läuft erst, wenn die ganze Liste gelesen ist — bricht
     * der Import ab, bleibt der Kantenbestand, wie er war.
     *
     * @param string $archiv Kennung des Archivs
     * @param string $ordner Ordner der Übergabe dieses Archivs
     *
     * @return array{personen:int,kanten:int,entfallen:int,wieder:int}
     */
    public function personen(string $archiv, string $ordner): array
    {
        $felder = self::FELDER['personen'][$archiv];
        $personen = array();
        $kanten = array();
        $zahl = array('personen' => 0, 'kanten' => 0, 'entfallen' => 0, 'wieder' => 0);
        $jetzt = time();

        foreach (Liste::zeilen($ordner.'/personen.jsonl') as $zeile) {
            $w = self::spalten($felder, $zeile);
            $w['archiv'] = $archiv;
            $w['name'] = self::klarname($w['name'] ?? null);
            $w['geburtsjahr'] = is_numeric($w['geburtsjahr'] ?? null) ? (int) $w['geburtsjahr'] : null;
            $personen[] = $w;
            ++$zahl['personen'];

            foreach (self::kanten($archiv, $zeile) as $k) {
                $kanten[] = $k + array('status' => 'bestaetigt', 'geaendert' => $jetzt, 'gesehen' => $jetzt);
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

        // ELOBASE: Ersatz-IDs für in DeWIS gelöschte Personen (R1). Fehlt
        // die Liste (ältere Übergabe), bleibt es bei den pkz-Kanten.
        $mitErsatz = 'elobase' === $archiv && is_file($ordner.'/dewis_ersatz.jsonl');

        if ($mitErsatz) {
            foreach (Liste::zeilen($ordner.'/dewis_ersatz.jsonl') as $zeile) {
                foreach (self::ersatzKanten($zeile) as $k) {
                    $kanten[] = $k + array('status' => 'bestaetigt', 'geaendert' => $jetzt, 'gesehen' => $jetzt);
                    ++$zahl['kanten'];
                }
            }
        }

        $this->schreibe('archiv_kante', $kanten, true, array(), array('status', 'geaendert'));

        // Wieder aufgetauchte Kanten bestätigen, nicht mehr geführte eigene
        // Kanten für entfallen erklären
        $zahl['wieder'] = (int) $this->db->executeStatement(
            "UPDATE archiv_kante SET status = 'bestaetigt', geaendert = ? WHERE status = 'entfallen' AND gesehen = ?",
            array($jetzt, $jetzt)
        );

        $praefixe = self::EIGENE_KANTEN[$archiv];

        if ($praefixe) {
            $zahl['entfallen'] = (int) $this->db->executeStatement(
                "UPDATE archiv_kante SET status = 'entfallen', geaendert = ?
                 WHERE status = 'bestaetigt' AND gesehen < ? AND quelle NOT IN ('pkz', ?)
                   AND (".implode(' OR ', array_fill(0, \count($praefixe), 'von LIKE ?')).')',
                array_merge(array($jetzt, $jetzt, self::QUELLE_ERSATZ), array_map(static function (string $p): string { return $p.'%'; }, $praefixe))
            );
        }

        // Ersatzkanten, die die neue Liste nicht mehr führt, entfallen. Nur
        // wenn die Liste da war — sonst fielen alle bei einer älteren
        // Übergabe ohne die Datei weg.
        if ($mitErsatz) {
            $zahl['entfallen'] += (int) $this->db->executeStatement(
                "UPDATE archiv_kante SET status = 'entfallen', geaendert = ? WHERE status = 'bestaetigt' AND gesehen < ? AND quelle = ?",
                array($jetzt, $jetzt, self::QUELLE_ERSATZ)
            );
        }

        return $zahl;
    }

    /**
     * Zählt Turniere und Personen eines Archivs, die in der Datenbank stehen,
     * aber nicht mehr in der Übergabe (etwa ein bei nu gelöschtes Turnier).
     *
     * Der Import löscht nichts (Turniere und Personen werden nur
     * aktualisiert); diese Zahl zeigt, ob ein neuer Abzug etwas nicht mehr
     * führt. Gerechnet über die Zeilenzahlen aus `uebergabe.json`: Jede Zeile
     * der Liste steht nach dem Import in der Datenbank, der Rest ist übrig.
     *
     * @param string              $archiv    Kennung des Archivs
     * @param array<string,mixed> $uebergabe Inhalt von uebergabe.json
     *
     * @return array{turniere:int,personen:int} Übrige Zeilen, 0 wenn keine
     */
    public function uebrig(string $archiv, array $uebergabe): array
    {
        $ergebnis = array();

        foreach (array('turniere' => 'archiv_turnier', 'personen' => 'archiv_person') as $teil => $tabelle) {
            $liste = (int) ($uebergabe['dateien'][$teil.'.jsonl']['zeilen'] ?? 0);
            $bestand = (int) $this->db->fetchOne('SELECT COUNT(*) FROM '.$tabelle.' WHERE archiv = ?', array($archiv));
            $ergebnis[$teil] = max(0, $bestand - $liste);
        }

        return $ergebnis;
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
