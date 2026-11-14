<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Alte Turniercodes aus DeWIS und ihre UUID im Wertungsportal (ab 1.58.0).
 *
 * Bis zum Umzug ins Wertungsportal von nu hießen die Turnierseiten nach dem
 * DeWIS-Code: `turniere/C619-C05-WAM.html`. Seitdem tragen sie die UUID, und
 * die Verweise, die Verbände und Vereine über Jahrzehnte gesetzt haben,
 * endeten auf einer Fehlermeldung. Der Export aus portal64 (tournamentmaster,
 * Juli 2026) stellt beides gegenüber; er liegt in tl_wertungsportal_altcodes.
 *
 * Gemessen am Testbestand (14.11.2026): Turnierende und letzte Berechnung
 * liefert nu für alle übernommenen Turniere selbst, und zwar gleich. Neu sind
 * nur der alte Code und der Tag der ersten Berechnung (computedOn).
 *
 * Die Klasse liest den Export (latin-1 oder UTF-8), gleicht ihn mit der
 * Tabelle ab und schlägt nach. Fehlt die Tabelle noch (vor contao:migrate),
 * liefern alle Abfragen „nichts gefunden" — die Seiten arbeiten wie vorher.
 */
class Altcodes
{
    /**
     * Spalten des Exports und ihr Feld in der Tabelle.
     */
    public const SPALTEN = array(
        'tcode'        => 'tcode',
        'uuid'         => 'uuid',
        'tname'        => 'tname',
        'organisation' => 'organisation',
        'vkz'          => 'vkz',
        'acron'        => 'acron',
        'finishedOn'   => 'finishedOn',
        'computedOn'   => 'computedOn',
        'recomputedOn' => 'recomputedOn',
        'rounds'       => 'rounds',
    );

    /**
     * Zeilen je INSERT beim Import.
     */
    public const BLOCK = 500;

    /**
     * Bereits nachgeschlagene UUIDs dieses Seitenaufrufs.
     *
     * @var array<string,array<string,mixed>|null>
     */
    protected static $merker = array();

    /**
     * Ob die Tabelle existiert; null = noch nicht geprüft.
     *
     * @var bool|null
     */
    protected static $tabelle;

    /**
     * Wandelt eine Zeile des Exports in die Felder der Tabelle um.
     *
     * Leere Werte schreibt der Export als "" oder (bei NULL ohne nvl) als
     * \N; beides wird leer. Datumswerte bleiben JJJJ-MM-TT, alles andere
     * wird leer — ein kaputtes Datum soll nicht als „Erste Berechnung"
     * erscheinen. Der Zeichensatz wird vorher bestimmt (siehe leseDatei()).
     *
     * @param array<int,string>   $kopf  Spaltennamen aus der Kopfzeile
     * @param array<int,string>   $zeile Werte der Zeile
     * @param bool                $latin true: Werte sind latin-1 und werden nach UTF-8 gewandelt
     *
     * @return array<string,mixed>|null Felder der Tabelle, null ohne gültige UUID
     */
    public static function zeile(array $kopf, array $zeile, bool $latin = false): ?array
    {
        $felder = array();

        foreach (self::SPALTEN as $spalte => $feld) {
            $index = array_search($spalte, $kopf, true);
            $wert = false !== $index ? trim((string) ($zeile[$index] ?? '')) : '';

            if ('\N' === $wert) {
                $wert = '';
            }

            if ($latin) {
                $wert = mb_convert_encoding($wert, 'UTF-8', 'ISO-8859-1');
            }

            $felder[$feld] = $wert;
        }

        $felder['uuid'] = strtolower($felder['uuid']);

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $felder['uuid'])) {
            return null;
        }

        foreach (array('finishedOn', 'computedOn', 'recomputedOn') as $feld) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $felder[$feld])) {
                $felder[$feld] = '';
            }
        }

        $felder['rounds'] = ctype_digit($felder['rounds']) ? min((int) $felder['rounds'], 65535) : 0;
        $felder['tcode'] = mb_substr($felder['tcode'], 0, 32);
        $felder['tname'] = mb_substr($felder['tname'], 0, 255);
        $felder['organisation'] = mb_substr($felder['organisation'], 0, 255);
        $felder['vkz'] = mb_substr($felder['vkz'], 0, 8);
        $felder['acron'] = mb_substr($felder['acron'], 0, 16);

        return $felder;
    }

    /**
     * Liest den Export zeilenweise.
     *
     * Der Export aus portal64 ist latin-1 mit Semikolon, Anführungszeichen um
     * jeden Wert und "" als Maskierung; Andreas Filmann bot andere
     * Zeichensätze an. Ist die ganze Datei gültiges UTF-8, wird sie so
     * gelesen, sonst als latin-1. Ein BOM am Anfang wird übergangen.
     *
     * @param string $datei Pfad zur CSV-Datei
     *
     * @throws \RuntimeException wenn die Datei fehlt oder keine Spalte uuid hat
     *
     * @return \Generator<int,array<string,mixed>|null> je Datenzeile die Felder,
     *                                                  null für Zeilen ohne gültige UUID
     */
    public static function leseDatei(string $datei): \Generator
    {
        if (!is_file($datei) || !is_readable($datei)) {
            throw new \RuntimeException('Datei nicht gefunden oder nicht lesbar: '.$datei);
        }

        $latin = !mb_check_encoding((string) file_get_contents($datei), 'UTF-8');
        $fp = fopen($datei, 'r');
        $kopf = fgetcsv($fp, 0, ';', '"', '"');

        if (!\is_array($kopf)) {
            fclose($fp);

            throw new \RuntimeException('Die Kopfzeile ließ sich nicht lesen.');
        }

        $kopf[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $kopf[0]);
        $kopf = array_map('trim', $kopf);

        if (!\in_array('uuid', $kopf, true) || !\in_array('tcode', $kopf, true)) {
            fclose($fp);

            throw new \RuntimeException('Spalten uuid und tcode fehlen — ist das der Export tcode_uui.csv?');
        }

        try {
            while (false !== ($zeile = fgetcsv($fp, 0, ';', '"', '"'))) {
                if (array(null) === $zeile) {
                    continue; // Leerzeile
                }

                yield self::zeile($kopf, $zeile, $latin);
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Gleicht die Zeilen des Exports mit der Tabelle ab.
     *
     * Schlüssel ist die UUID: Neue Turniere werden blockweise angelegt,
     * vorhandene nur geändert, wenn sich ein Wert unterscheidet. Gelöscht
     * wird nichts — eine im Backend berichtigte Zeile überschreibt ein
     * zweiter Import allerdings wieder mit dem Wert aus der Datei.
     *
     * @param iterable<array<string,mixed>|null> $zeilen   Ergebnis von leseDatei()
     * @param bool                               $trocken  true: nur zählen, nichts schreiben
     *
     * @return array{neu:int,geaendert:int,gleich:int,ohneUuid:int,doppelteUuid:int}
     */
    public static function importiere(iterable $zeilen, bool $trocken = false): array
    {
        $db = \Contao\System::getContainer()->get('database_connection');
        $felder = array_values(self::SPALTEN);

        // Bestand auf einmal: 66.685 kurze Zeilen passen bequem in den Speicher
        // und sparen eine Abfrage je Zeile
        $bestand = array();

        foreach ($db->fetchAllAssociative('SELECT id, '.implode(', ', $felder).' FROM tl_wertungsportal_altcodes') as $z) {
            $bestand[(string) $z['uuid']] = $z;
        }

        $ergebnis = array('neu' => 0, 'geaendert' => 0, 'gleich' => 0, 'ohneUuid' => 0, 'doppelteUuid' => 0);
        $gesehen = array();
        $neu = array();
        $jetzt = time();

        foreach ($zeilen as $z) {
            if (null === $z) {
                ++$ergebnis['ohneUuid'];
                continue;
            }

            if (isset($gesehen[$z['uuid']])) {
                ++$ergebnis['doppelteUuid'];
                continue;
            }

            $gesehen[$z['uuid']] = true;

            if (!isset($bestand[$z['uuid']])) {
                ++$ergebnis['neu'];
                $neu[] = $z;

                if (!$trocken && \count($neu) >= self::BLOCK) {
                    self::schreibeBlock($db, $neu, $jetzt);
                    $neu = array();
                }

                continue;
            }

            $alt = $bestand[$z['uuid']];
            $aenderung = array();

            foreach ($felder as $feld) {
                if ((string) $alt[$feld] !== (string) $z[$feld]) {
                    $aenderung[$feld] = $z[$feld];
                }
            }

            if (!$aenderung) {
                ++$ergebnis['gleich'];
                continue;
            }

            ++$ergebnis['geaendert'];

            if (!$trocken) {
                $db->update('tl_wertungsportal_altcodes', $aenderung + array('tstamp' => $jetzt), array('id' => (int) $alt['id']));
            }
        }

        if (!$trocken && $neu) {
            self::schreibeBlock($db, $neu, $jetzt);
        }

        self::$merker = array();
        self::$tabelle = null;

        return $ergebnis;
    }

    /**
     * Legt einen Block neuer Zeilen mit einem einzigen INSERT an.
     *
     * @param \Doctrine\DBAL\Connection          $db     Datenbankverbindung
     * @param array<int,array<string,mixed>>     $zeilen Felder je Zeile (siehe zeile())
     * @param int                                $tstamp Zeitstempel für alle Zeilen
     *
     * @return void
     */
    protected static function schreibeBlock($db, array $zeilen, int $tstamp): void
    {
        $felder = array_values(self::SPALTEN);
        $platz = '('.implode(', ', array_fill(0, \count($felder) + 1, '?')).')';
        $werte = array();

        foreach ($zeilen as $z) {
            $werte[] = $tstamp;

            foreach ($felder as $feld) {
                $werte[] = $z[$feld];
            }
        }

        $db->executeStatement(
            'INSERT INTO tl_wertungsportal_altcodes (tstamp, '.implode(', ', $felder).') VALUES '.implode(', ', array_fill(0, \count($zeilen), $platz)),
            $werte
        );
    }

    /**
     * Liefert alle Turniere mit diesem alten Code.
     *
     * Verglichen wird genau: Die Datenbank behandelt „Ö" und „O" je nach
     * Sortierfolge gleich, die Codes „B443-615-BÖ1" und „B443-615-BO1" sind
     * aber verschiedene Turniere. Sortiert nach Turnierende, neuestes zuerst.
     *
     * @param string $tcode Alter Turniercode, z. B. „C619-C05-WAM"
     *
     * @return array<int,array<string,mixed>> Zeilen der Tabelle; leer, wenn
     *                                        unbekannt oder die Tabelle fehlt
     */
    public static function nachCode(string $tcode): array
    {
        $tcode = trim($tcode);

        if ('' === $tcode || !self::tabelleDa()) {
            return array();
        }

        $treffer = \Contao\System::getContainer()->get('database_connection')
            ->fetchAllAssociative('SELECT * FROM tl_wertungsportal_altcodes WHERE tcode = ? ORDER BY finishedOn DESC, id', array($tcode));

        return array_values(array_filter($treffer, static function ($z) use ($tcode) {
            return (string) $z['tcode'] === $tcode;
        }));
    }

    /**
     * Liefert die Zeile zu einer UUID — einmal je Seitenaufruf aus der
     * Datenbank, danach aus dem Merker.
     *
     * @param string $uuid UUID des Turniers bei nu
     *
     * @return array<string,mixed>|null Zeile der Tabelle, null wenn unbekannt
     */
    public static function nachUuid(string $uuid): ?array
    {
        $uuid = strtolower(trim($uuid));

        if ('' === $uuid || !self::tabelleDa()) {
            return null;
        }

        if (!\array_key_exists($uuid, self::$merker)) {
            $zeile = \Contao\System::getContainer()->get('database_connection')
                ->fetchAssociative('SELECT * FROM tl_wertungsportal_altcodes WHERE uuid = ?', array($uuid));
            self::$merker[$uuid] = false === $zeile ? null : $zeile;
        }

        return self::$merker[$uuid];
    }

    /**
     * Tag der ersten Berechnung für „Erste Berechnung am".
     *
     * @param string $uuid UUID des Turniers
     *
     * @return string TT.MM.JJJJ, '' wenn unbekannt (dann blenden die Vorlagen
     *                die Zeile aus)
     */
    public static function ersteBerechnung(string $uuid): string
    {
        $zeile = self::nachUuid($uuid);

        return $zeile ? self::datum((string) $zeile['computedOn']) : '';
    }

    /**
     * Alter Turniercode für die Kopfzeile der Turnierseiten.
     *
     * @param string $uuid UUID des Turniers
     *
     * @return string Code, '' wenn unbekannt
     */
    public static function code(string $uuid): string
    {
        $zeile = self::nachUuid($uuid);

        return $zeile ? (string) $zeile['tcode'] : '';
    }

    /**
     * Wandelt JJJJ-MM-TT in TT.MM.JJJJ.
     *
     * @param string $datum Datum aus der Tabelle
     *
     * @return string TT.MM.JJJJ, '' bei leerem oder ungültigem Wert
     */
    public static function datum(string $datum): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $m) ? $m[3].'.'.$m[2].'.'.$m[1] : '';
    }

    /**
     * Baut die neue Adresse einer Turnierseite.
     *
     * Die alte Ergebnisseite (`<code>/Ergebnisse`) führt auf die neue
     * Ergebnisseite. Der alte Spielberichtsbogen (`<code>/<DeWIS-Nummer>`)
     * führt auf die Auswertung: Die alten Spielernummern kennt nu nicht, und
     * die Spieler-UUID im Turnier steht nicht im Export.
     *
     * @param string $seite      Adresse der Turnierseite ohne Suffix, z. B.
     *                           „https://www.schachbund.de/dwz-turniere"
     * @param string $uuid       UUID des Turniers
     * @param bool   $ergebnisse true: auf die Ergebnisse statt auf die Auswertung
     * @param string $suffix     Adresssuffix der Seite, z. B. „.html"
     *
     * @return string Neue Adresse
     */
    public static function ziel(string $seite, string $uuid, bool $ergebnisse, string $suffix): string
    {
        return rtrim($seite, '/').'/'.$uuid.($ergebnisse ? '/Ergebnisse' : '').$suffix;
    }

    /**
     * Prüft einmal je Seitenaufruf, ob die Tabelle schon angelegt ist.
     *
     * @return bool true, wenn tl_wertungsportal_altcodes existiert
     */
    protected static function tabelleDa(): bool
    {
        if (null === self::$tabelle) {
            try {
                self::$tabelle = \Contao\Database::getInstance()->tableExists('tl_wertungsportal_altcodes');
            } catch (\Throwable $e) {
                self::$tabelle = false;
            }
        }

        return self::$tabelle;
    }
}
