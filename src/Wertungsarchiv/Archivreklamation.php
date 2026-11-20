<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

/**
 * Reklamationen zu Einträgen des Wertungsarchivs (ab 2.0.0).
 *
 * Wer das Archiv sehen darf (Zugriff::erlaubt()), kann jede Archivzeile der
 * Karteikarte reklamieren und unter der Tabelle ein fehlendes Turnier melden.
 * Anders als die Reklamation der nu-Daten (Helper\Reklamation) geht nichts
 * per Mail hinaus: Jede Meldung wird als eine JSON-Zeile an die Logdatei
 * `var/wertungsarchiv/reklamationen.jsonl` angehängt (Frank Binding,
 * 20.11.2026). Die Datei ist die Arbeitsliste für die Berichtigung des
 * Archivs; sie liegt außerhalb von `public/` und `files/` und übersteht
 * einen neuen Upload der Archivordner.
 *
 * Eine Archivzeile wird über einen fachlichen Schlüssel erkannt (schluessel()),
 * nicht über ihre Datenbanknummer: Die Kartei wird bei jedem Import ersetzt,
 * die Nummern ändern sich. Zeilen mit Meldungen erscheinen in der Karteikarte
 * als „reklamiert"; erneutes Reklamieren bleibt möglich, die bisherigen
 * Meldungen stehen dann im Dialog.
 *
 * Die reinen Teile (Schlüssel, Prüfung, Satzbau, Lesen und Schreiben der
 * Datei) kommen ohne Contao aus und sind in
 * tests/Wertungsarchiv/ArchivreklamationTest.php geprüft.
 */
class Archivreklamation
{
    /**
     * Gründe für eine Reklamation einer Archivzeile, in der Reihenfolge des
     * Dialogs. Die Schlüssel stehen in der Logdatei und dürfen sich nicht
     * ändern; die Texte schon.
     */
    public const GRUENDE = array(
        'fremd'       => 'Gehört nicht zu diesem Spieler (ein anderer, etwa ein Namensvetter, hat das Turnier gespielt)',
        'doppelt'     => 'Doppelt in der Kartei (dasselbe Turnier steht noch einmal da, oft mit anderem Code oder Datum)',
        'nie'         => 'Dieses Turnier hat es nicht gegeben',
        'ergebnis'    => 'Ergebnis falsch (Punkte oder Partienzahl)',
        'wertung'     => 'Wertungszahlen falsch (DWZ vorher/nachher, We, Leistung)',
        'datum'       => 'Datum oder Jahr falsch, Turnier steht an der falschen Stelle',
        'name'        => 'Turniername falsch oder nichtssagend',
        'nicht_dwz'   => 'Turnier hätte nicht DWZ-gewertet werden dürfen (z. B. Blitz, Schnellschach, Simultan)',
        'unvollstaendig' => 'Partien fehlen oder gehören zu einem anderen Turnier',
        'sonstiges'   => 'Etwas anderes (bitte unten beschreiben)',
    );

    /**
     * Art der Meldung „Turnier fehlt" (Knopf unter der Tabelle).
     */
    public const FEHLT = 'fehlt';

    /**
     * Felder der Meldung „Turnier fehlt" mit Beschriftung.
     */
    public const FEHLT_FELDER = array(
        'turnier'  => 'Turnier (Name, Veranstalter)',
        'zeitraum' => 'Zeitraum (Jahr oder Datum)',
        'ort'      => 'Ort',
        'ergebnis' => 'Ergebnis (Punkte aus Partien)',
    );

    /**
     * Höchstlänge der Bemerkung und der Felder.
     */
    public const TEXT_LAENGE = 2000;
    public const FELD_LAENGE = 200;

    /**
     * Mindestlänge der Bemerkung, wenn sie allein steht (ohne Grund) oder
     * „Etwas anderes" gewählt ist.
     */
    public const TEXT_MINDESTENS = 10;

    /**
     * Bremse: höchstens so viele Meldungen je Mitglied und ZEITFENSTER.
     * Großzügiger als bei den Mails, weil eine fremde Karteikarte leicht
     * zwanzig falsche Zeilen hat.
     */
    public const HOECHSTZAHL = 60;
    public const ZEITFENSTER = 3600;

    /**
     * Format der Logdatei (steht in jeder Zeile).
     */
    public const FORMAT = 'wertungsarchiv-reklamation/1';

    /**
     * Route des Controllers (routing.yml).
     */
    public const ROUTE = 'wertungsportal_archivreklamation';

    /**
     * Gelesene Logdateien je Pfad für den Seitenaufruf.
     *
     * @var array<string,array<string,array<int,array<string,mixed>>>>
     */
    private static $gelesen = array();

    /**
     * Wurde der Dialog für diese Seite schon ausgegeben?
     *
     * @var bool
     */
    private static $dialogAusgegeben = false;

    /**
     * Bildet den fachlichen Schlüssel einer Archivzeile.
     *
     * Bestandteile: Archiv, Kennung der Archivperson, Turniercode und — bei
     * ELOBASE — der ZDB-Abzug und die Satznummer der Kartei. So bleibt eine
     * Meldung derselben Zeile zugeordnet, auch wenn die Kartei neu importiert
     * wird und andere Datenbanknummern bekommt. DeWIS und Portal führen weder
     * Abzug noch Satz; dort reicht Person + Turnier.
     *
     * @param array<string,mixed> $zeile Karteizeile aus Archiv::altkartei()
     *                                   (archiv, kennung, code, quelle, extra,
     *                                   t_quell_id)
     *
     * @return string Schlüssel, z. B. „elobase|Z:1234567|9424-300-GB1|1997-07|844001"
     */
    public static function schluessel(array $zeile): string
    {
        $extra = \is_string($zeile['extra'] ?? null) ? (array) json_decode($zeile['extra'], true) : (array) ($zeile['extra'] ?? array());
        $code = trim((string) ($zeile['code'] ?? $zeile['t_quell_id'] ?? ''));

        return implode('|', array(
            (string) ($zeile['archiv'] ?? ''),
            (string) ($zeile['kennung'] ?? ''),
            '' !== $code ? $code : (string) ($zeile['t_quell_id'] ?? ''),
            (string) ($zeile['quelle'] ?? ''),
            (string) ($extra['satz'] ?? ''),
        ));
    }

    /**
     * Schlüssel der Meldungen „Turnier fehlt" einer Karteikarte.
     *
     * Die NU-Nummer bleibt über jeden Neuaufbau gleich, die Nummer der
     * Gesamtperson nicht — sie gilt nur für Archivkarten ohne NU-Nummer, dann
     * zusammen mit der Kennung der ersten Archivperson.
     *
     * @param string $nuId    NU-Nummer, '' ohne
     * @param string $kennung Kennung einer Archivperson der Karte, '' ohne
     *
     * @return string Schlüssel, z. B. „fehlt|NU0000001"
     */
    public static function fehltSchluessel(string $nuId, string $kennung): string
    {
        return self::FEHLT.'|'.('' !== $nuId ? $nuId : $kennung);
    }

    /**
     * Baut den Kontext einer Archivzeile für den Knopf.
     *
     * Er wird signiert in die Seite geschrieben (Helper\Reklamation::signiere())
     * und kommt beim Absenden unverändert zurück — so steht in der Logdatei,
     * was das Mitglied gesehen hat, und niemand kann fremde Schlüssel
     * einschmuggeln.
     *
     * @param array<string,mixed> $zeile   Karteizeile aus Archiv::altkartei()
     * @param string              $nuId    NU-Nummer der Karteikarte, '' bei einer Archivkarte
     * @param int                 $gesamt  Nummer der Gesamtperson (zum Zeitpunkt der Meldung)
     * @param string              $spieler Name auf der Karteikarte
     *
     * @return array<string,mixed> Kontext mit art = eintrag
     */
    public static function kontext(array $zeile, string $nuId, int $gesamt, string $spieler): array
    {
        $extra = \is_string($zeile['extra'] ?? null) ? (array) json_decode($zeile['extra'], true) : array();

        return array(
            'art'        => 'eintrag',
            'schluessel' => self::schluessel($zeile),
            'karte'      => array('nu' => $nuId, 'gesamt' => $gesamt, 'name' => $spieler),
            'eintrag'    => array(
                'archiv'     => (string) ($zeile['archiv'] ?? ''),
                'kennung'    => (string) ($zeile['kennung'] ?? ''),
                'code'       => trim((string) ($zeile['code'] ?? '')),
                'abzug'      => $zeile['quelle'] ?? null,
                'satz'       => $extra['satz'] ?? null,
                'nr'         => $extra['eintrag'] ?? null,
                'turnier_id' => isset($zeile['t_id']) ? (int) $zeile['t_id'] : null,
                'turnier'    => (string) ($zeile['t_name'] ?? ''),
                'ende'       => $zeile['t_ende'] ?? $zeile['ende'] ?? null,
                'partien'    => isset($zeile['partien']) ? (int) $zeile['partien'] : null,
                'punkte_halbe' => isset($zeile['punkte_halbe']) ? (int) $zeile['punkte_halbe'] : null,
                'dwz_vorher' => isset($zeile['dwz_vorher']) ? (int) $zeile['dwz_vorher'] : null,
                'dwz'        => isset($zeile['dwz']) ? (int) $zeile['dwz'] : null,
                'vor_erster_anmeldung' => !empty($zeile['vor_erster_anmeldung']),
            ),
        );
    }

    /**
     * Baut den Kontext der Meldung „Turnier fehlt".
     *
     * @param string $nuId    NU-Nummer, '' bei einer Archivkarte
     * @param int    $gesamt  Nummer der Gesamtperson
     * @param string $kennung Kennung der ersten Archivperson der Karte
     * @param string $spieler Name auf der Karteikarte
     *
     * @return array<string,mixed> Kontext mit art = fehlt
     */
    public static function fehltKontext(string $nuId, int $gesamt, string $kennung, string $spieler): array
    {
        return array(
            'art'        => self::FEHLT,
            'schluessel' => self::fehltSchluessel($nuId, $kennung),
            'karte'      => array('nu' => $nuId, 'gesamt' => $gesamt, 'kennung' => $kennung, 'name' => $spieler),
        );
    }

    /**
     * Bereinigt die Eingaben des Formulars.
     *
     * Unbekannte Gründe fallen weg, Texte werden auf ihre Höchstlänge
     * gekürzt, Steuerzeichen außer Zeilenumbrüchen entfernt.
     *
     * @param array<int|string,mixed> $gruende Gewählte Gründe (Schlüssel aus GRUENDE)
     * @param string                  $text    Bemerkung
     * @param array<string,mixed>     $felder  Felder der Meldung „Turnier fehlt"
     *
     * @return array{gruende:array<int,string>,text:string,felder:array<string,string>}
     */
    public static function bereinige(array $gruende, string $text, array $felder): array
    {
        $saeubern = static function (string $wert, int $laenge, bool $mehrzeilig): string {
            $wert = str_replace(array("\r\n", "\r"), "\n", $wert);
            $wert = (string) preg_replace($mehrzeilig ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', ' ', $wert);

            return trim(mb_substr($wert, 0, $laenge));
        };

        $sauber = array();

        foreach (self::FEHLT_FELDER as $name => $beschriftung) {
            $wert = $saeubern((string) ($felder[$name] ?? ''), self::FELD_LAENGE, false);

            if ('' !== $wert) {
                $sauber[$name] = $wert;
            }
        }

        return array(
            'gruende' => array_values(array_intersect(array_keys(self::GRUENDE), array_map('strval', $gruende))),
            'text'    => $saeubern($text, self::TEXT_LAENGE, true),
            'felder'  => $sauber,
        );
    }

    /**
     * Prüft eine bereinigte Eingabe.
     *
     * Eine Reklamation braucht mindestens einen Grund oder eine Bemerkung;
     * „Etwas anderes" braucht immer eine Bemerkung. Die Meldung „Turnier
     * fehlt" braucht den Turniernamen oder eine Bemerkung.
     *
     * @param string                                                             $art     eintrag oder fehlt
     * @param array{gruende:array<int,string>,text:string,felder:array<string,string>} $eingabe Ergebnis von bereinige()
     *
     * @return string|null Fehlermeldung für das Formular, null wenn alles passt
     */
    public static function eingabefehler(string $art, array $eingabe): ?string
    {
        $text = mb_strlen($eingabe['text']);

        if (self::FEHLT === $art) {
            if (!isset($eingabe['felder']['turnier']) && $text < self::TEXT_MINDESTENS) {
                return 'Bitte nennen Sie das fehlende Turnier oder beschreiben Sie es in der Bemerkung.';
            }

            return null;
        }

        if (!$eingabe['gruende'] && $text < self::TEXT_MINDESTENS) {
            return 'Bitte wählen Sie mindestens einen Grund oder beschreiben Sie, was nicht stimmt.';
        }

        if (\in_array('sonstiges', $eingabe['gruende'], true) && $text < self::TEXT_MINDESTENS) {
            return 'Bitte beschreiben Sie unter „Bemerkung", was nicht stimmt.';
        }

        return null;
    }

    /**
     * Baut die Zeile der Logdatei.
     *
     * Sie enthält alles, was die Berichtigung braucht, ohne die Seite noch
     * einmal aufrufen zu müssen: den Kontext (was das Mitglied sah), die
     * Eingabe und wer gemeldet hat. `status` ist anfangs „offen"; die
     * Berichtigung führt ihn in einer eigenen Datei, die Logdatei wird nur
     * angehängt, nie geändert.
     *
     * @param array<string,mixed>                                                $kontext  Entschlüsselter Kontext
     * @param array{gruende:array<int,string>,text:string,felder:array<string,string>} $eingabe  Ergebnis von bereinige()
     * @param array<string,mixed>                                                $mitglied id, name, email
     * @param int                                                                $zeit     Zeitpunkt (Unix)
     *
     * @return array<string,mixed> Satz der Logdatei
     */
    public static function satz(array $kontext, array $eingabe, array $mitglied, int $zeit): array
    {
        return array(
            'format'     => self::FORMAT,
            'id'         => date('YmdHis', $zeit).'-'.substr(hash('sha256', $zeit.'|'.($mitglied['id'] ?? 0).'|'.($kontext['schluessel'] ?? '').'|'.random_int(0, PHP_INT_MAX)), 0, 8),
            'zeit'       => date('c', $zeit),
            'art'        => (string) ($kontext['art'] ?? ''),
            'schluessel' => (string) ($kontext['schluessel'] ?? ''),
            'gruende'    => $eingabe['gruende'],
            'text'       => $eingabe['text'],
            'felder'     => $eingabe['felder'] ?: null,
            'karte'      => $kontext['karte'] ?? null,
            'eintrag'    => $kontext['eintrag'] ?? null,
            'mitglied'   => array('id' => (int) ($mitglied['id'] ?? 0), 'name' => (string) ($mitglied['name'] ?? ''), 'email' => (string) ($mitglied['email'] ?? '')),
            'status'     => 'offen',
        );
    }

    /**
     * Liest die Logdatei, nach Schlüssel geordnet.
     *
     * Kaputte Zeilen (etwa nach einem abgebrochenen Schreiben) werden
     * übergangen. Das Ergebnis bleibt für den Seitenaufruf gemerkt.
     *
     * @param string $datei Pfad der Logdatei; fehlt sie, gibt es nichts
     *
     * @return array<string,array<int,array<string,mixed>>> Schlüssel => Sätze, älteste zuerst
     */
    public static function lese(string $datei): array
    {
        if (isset(self::$gelesen[$datei])) {
            return self::$gelesen[$datei];
        }

        $alle = array();
        $griff = is_file($datei) ? @fopen($datei, 'r') : false;

        if (false !== $griff) {
            flock($griff, LOCK_SH);

            while (false !== ($zeile = fgets($griff))) {
                $satz = json_decode($zeile, true);

                if (\is_array($satz) && isset($satz['schluessel'])) {
                    $alle[(string) $satz['schluessel']][] = $satz;
                }
            }

            flock($griff, LOCK_UN);
            fclose($griff);
        }

        return self::$gelesen[$datei] = $alle;
    }

    /**
     * Hängt einen Satz an die Logdatei an.
     *
     * Das Verzeichnis wird bei Bedarf angelegt. Geschrieben wird unter einer
     * exklusiven Sperre in einem Zug, damit zwei gleichzeitige Meldungen
     * nicht ineinanderlaufen.
     *
     * @param string              $datei Pfad der Logdatei
     * @param array<string,mixed> $satz  Satz aus satz()
     *
     * @return bool true, wenn die Zeile geschrieben ist
     */
    public static function schreibe(string $datei, array $satz): bool
    {
        $ordner = \dirname($datei);

        if (!is_dir($ordner) && !@mkdir($ordner, 0775, true) && !is_dir($ordner)) {
            return false;
        }

        $zeile = json_encode($satz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (false === $zeile) {
            return false;
        }

        $ok = false !== @file_put_contents($datei, $zeile."\n", FILE_APPEND | LOCK_EX);
        unset(self::$gelesen[$datei]);

        return $ok;
    }

    /**
     * Zählt die Meldungen eines Mitglieds seit einem Zeitpunkt (Bremse).
     *
     * @param array<string,array<int,array<string,mixed>>> $alle     Ergebnis von lese()
     * @param int                                          $mitglied Nummer des Mitglieds
     * @param int                                          $seit     Zeitpunkt (Unix)
     *
     * @return int Anzahl
     */
    public static function zaehle(array $alle, int $mitglied, int $seit): int
    {
        $zahl = 0;

        foreach ($alle as $saetze) {
            foreach ($saetze as $satz) {
                if ((int) ($satz['mitglied']['id'] ?? 0) === $mitglied && strtotime((string) ($satz['zeit'] ?? '')) >= $seit) {
                    ++$zahl;
                }
            }
        }

        return $zahl;
    }

    /**
     * Macht aus einem Satz der Logdatei die Anzeige im Dialog.
     *
     * Wer gemeldet hat, erscheint nicht — nur, ob es das angemeldete
     * Mitglied selbst war. Die Logdatei enthält Namen und Adressen; die
     * Seite zeigt sie anderen nicht.
     *
     * @param array<string,mixed> $satz     Satz der Logdatei
     * @param int                 $mitglied Nummer des angemeldeten Mitglieds
     *
     * @return array{datum:string,gruende:array<int,string>,felder:array<string,string>,text:string,eigen:bool,status:string}
     */
    public static function anzeige(array $satz, int $mitglied): array
    {
        $zeit = strtotime((string) ($satz['zeit'] ?? ''));
        $gruende = array();

        foreach ((array) ($satz['gruende'] ?? array()) as $grund) {
            $gruende[] = self::GRUENDE[$grund] ?? (string) $grund;
        }

        $felder = array();

        foreach ((array) ($satz['felder'] ?? array()) as $name => $wert) {
            $felder[self::FEHLT_FELDER[$name] ?? (string) $name] = (string) $wert;
        }

        return array(
            'datum'   => false === $zeit ? '' : date('d.m.Y', $zeit),
            'gruende' => $gruende,
            'felder'  => $felder,
            'text'    => (string) ($satz['text'] ?? ''),
            'eigen'   => $mitglied > 0 && (int) ($satz['mitglied']['id'] ?? 0) === $mitglied,
            'status'  => (string) ($satz['status'] ?? 'offen'),
        );
    }

    /**
     * Baut den Knopf für eine Archivzeile oder für „Turnier fehlt".
     *
     * Der Knopf trägt den signierten Kontext und die bisherigen Meldungen
     * (data-bisher, JSON). Beim ersten Knopf der Seite kommen Dialog und
     * Skript dazu.
     *
     * @param array<string,mixed>              $kontext  Kontext aus kontext() oder fehltKontext()
     * @param array<int,array<string,mixed>>   $bisher   Bisherige Sätze dieses Schlüssels
     * @param int                              $mitglied Nummer des angemeldeten Mitglieds
     * @param string                           $titel    Beschreibung des Eintrags für den Dialog
     * @param string                           $geheimnis Schlüssel der Signatur (kernel.secret)
     *
     * @return string HTML des Knopfs
     */
    public static function knopf(array $kontext, array $bisher, int $mitglied, string $titel, string $geheimnis): string
    {
        $signiert = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::signiere($kontext, $geheimnis);
        $anzeige = array_map(static function (array $satz) use ($mitglied): array {
            return self::anzeige($satz, $mitglied);
        }, $bisher);
        $fehlt = self::FEHLT === ($kontext['art'] ?? '');

        return sprintf(
            '<button type="button" class="wp-archivreklamation-knopf%s" data-art="%s" data-kontext="%s" data-signatur="%s" data-titel="%s" data-bisher="%s" title="%s">%s</button>',
            $fehlt ? ' wp-archivreklamation-fehlt' : '',
            $fehlt ? self::FEHLT : 'eintrag',
            self::attribut($signiert['kontext']),
            self::attribut($signiert['signatur']),
            self::attribut($titel),
            self::attribut((string) json_encode($anzeige, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $fehlt ? 'Ein Turnier melden, das in der Kartei fehlt' : ($bisher ? 'Erneut reklamieren — bisherige Meldungen ansehen' : 'Diesen Eintrag reklamieren'),
            $fehlt ? 'Turnier fehlt?' : '<span aria-hidden="true">⚑</span><span class="wp-unsichtbar"> Reklamieren</span>'
        );
    }

    /**
     * Marke „reklamiert" für eine Zeile mit Meldungen.
     *
     * @param array<int,array<string,mixed>> $bisher Sätze dieses Schlüssels
     *
     * @return string HTML, '' ohne Meldungen
     */
    public static function marke(array $bisher): string
    {
        if (!$bisher) {
            return '';
        }

        $letzte = self::anzeige($bisher[\count($bisher) - 1], 0);
        $titel = 1 === \count($bisher) ? 'Reklamiert am '.$letzte['datum'] : \count($bisher).' Reklamationen, zuletzt am '.$letzte['datum'];

        return '<span class="wp-marke wp-marke-reklamiert" title="'.self::attribut($titel).'">reklamiert</span>';
    }

    /**
     * Pfad der Logdatei: `<Projekt>/var/wertungsarchiv/reklamationen.jsonl`.
     *
     * `var/` überstehen Updates im Contao Manager und neue Uploads der
     * Archivordner; ausgeliefert wird von dort nichts.
     *
     * @return string Pfad, '' wenn der Container nicht zu bekommen ist
     */
    public static function datei(): string
    {
        try {
            return \Contao\System::getContainer()->getParameter('kernel.project_dir').'/var/wertungsarchiv/reklamationen.jsonl';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Liefert den Schlüssel der Signatur: das Kernel-Secret.
     *
     * @return string Schlüssel, '' wenn er nicht zu bekommen ist
     */
    public static function geheimnis(): string
    {
        try {
            return (string) \Contao\System::getContainer()->getParameter('kernel.secret');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Knöpfe und Marken für die Archivzeilen einer Karteikarte.
     *
     * Hängt an jede aufbereitete Zeile (Darstellung::karteizeile()) die
     * Schlüssel `reklamation` (Knopf) und `reklamiert` (Marke). Ohne Zugriff
     * oder ohne Mitglied bleiben beide leer.
     *
     * @param array<int,array<string,mixed>> $roh      Karteizeilen aus Archiv::altkartei()
     * @param array<int,array<string,mixed>> $zeilen   Dieselben Zeilen, aufbereitet
     * @param string                         $nuId     NU-Nummer, '' bei einer Archivkarte
     * @param int                            $gesamt   Nummer der Gesamtperson
     * @param string                         $spieler  Name auf der Karteikarte
     *
     * @return array<int,array<string,mixed>> Die aufbereiteten Zeilen mit Knopf und Marke
     */
    public static function fuerZeilen(array $roh, array $zeilen, string $nuId, int $gesamt, string $spieler): array
    {
        $mitglied = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::mitglied();
        $geheimnis = self::geheimnis();

        if (null === $mitglied || '' === $geheimnis || !Zugriff::erlaubt()) {
            return $zeilen;
        }

        // Ohne Dialog keine Knöpfe — aber die Archivzeilen bleiben
        if (!self::dialog()) {
            return $zeilen;
        }

        $alle = self::lese(self::datei());

        foreach ($zeilen as $i => $zeile) {
            $bisher = $alle[self::schluessel($roh[$i])] ?? array();
            // Titel im Dialog: Jahr · Turnier [Code] · Herkunft
            $code = trim((string) ($roh[$i]['code'] ?? ''));
            $titel = trim(strip_tags((string) $zeile['jahr']).' · '.html_entity_decode(strip_tags((string) $zeile['turnier']), ENT_QUOTES, 'UTF-8').('' !== $code ? ' ['.$code.']' : '').' · '.trim(html_entity_decode(strip_tags((string) $zeile['nummer']), ENT_QUOTES, 'UTF-8')));
            $zeilen[$i]['reklamation'] = self::knopf(self::kontext($roh[$i], $nuId, $gesamt, $spieler), $bisher, $mitglied['id'], $titel, $geheimnis);
            $zeilen[$i]['reklamiert'] = self::marke($bisher);
        }

        return $zeilen;
    }

    /**
     * Knopf „Turnier fehlt?" für eine Karteikarte.
     *
     * @param string $nuId    NU-Nummer, '' bei einer Archivkarte
     * @param int    $gesamt  Nummer der Gesamtperson
     * @param string $kennung Kennung der ersten Archivperson der Karte
     * @param string $spieler Name auf der Karteikarte
     *
     * @return string HTML, '' ohne Zugriff oder ohne Mitglied
     */
    public static function fuerFehlt(string $nuId, int $gesamt, string $kennung, string $spieler): string
    {
        $mitglied = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::mitglied();
        $geheimnis = self::geheimnis();

        if (null === $mitglied || '' === $geheimnis || !Zugriff::erlaubt()) {
            return '';
        }

        if (!self::dialog()) {
            return '';
        }

        $bisher = self::lese(self::datei())[self::fehltSchluessel($nuId, $kennung)] ?? array();

        return self::knopf(self::fehltKontext($nuId, $gesamt, $kennung, $spieler), $bisher, $mitglied['id'], 'Fehlendes Turnier für '.$spieler, $geheimnis)
            .($bisher ? ' <span class="wp-archivreklamation-anzahl">('.\count($bisher).' '.(1 === \count($bisher) ? 'Meldung' : 'Meldungen').' bisher)</span>' : '');
    }

    /**
     * Nimmt eine Meldung entgegen: prüfen und in die Logdatei schreiben.
     *
     * Ablauf mit HTTP-Status: Kontext echt (Signatur)? Sonst 400. Eingabe
     * brauchbar? Sonst 422. Bremse? Sonst 429. Schreiben gescheitert? 500.
     * Die Anmeldung und den Zugriff prüft der Controller.
     *
     * @param array<string,mixed> $eingabe  kontext, signatur, gruende, text, felder
     * @param array<string,mixed> $mitglied id, name, email
     *
     * @return array{status:int,daten:array<string,mixed>} Antwort für den Controller;
     *                                                     bei Erfolg mit der Anzeige der neuen Meldung
     */
    public static function einreichen(array $eingabe, array $mitglied): array
    {
        $kontext = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::entschluessele((string) ($eingabe['kontext'] ?? ''), (string) ($eingabe['signatur'] ?? ''), self::geheimnis());

        if (null === $kontext || !\in_array($kontext['art'] ?? '', array('eintrag', self::FEHLT), true)) {
            return self::antwort(400, 'Das Formular ist ungültig. Bitte laden Sie die Seite neu und versuchen Sie es noch einmal.');
        }

        $sauber = self::bereinige((array) ($eingabe['gruende'] ?? array()), (string) ($eingabe['text'] ?? ''), (array) ($eingabe['felder'] ?? array()));
        $fehler = self::eingabefehler((string) $kontext['art'], $sauber);

        if (null !== $fehler) {
            return self::antwort(422, $fehler);
        }

        $datei = self::datei();
        $zeit = time();

        if (self::zaehle(self::lese($datei), (int) $mitglied['id'], $zeit - self::ZEITFENSTER) >= self::HOECHSTZAHL) {
            return self::antwort(429, 'Sie haben in der letzten Stunde schon '.self::HOECHSTZAHL.' Meldungen geschickt. Bitte versuchen Sie es später noch einmal.');
        }

        $satz = self::satz($kontext, $sauber, $mitglied, $zeit);

        if ('' === $datei || !self::schreibe($datei, $satz)) {
            \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Reklamation nicht gespeichert ('.$datei.')', __METHOD__, 'ERROR');

            return self::antwort(500, 'Ihre Meldung konnte nicht gespeichert werden. Bitte versuchen Sie es später noch einmal.');
        }

        return array(
            'status' => 200,
            'daten'  => array(
                'ok'      => true,
                'meldung' => 'Vielen Dank! Ihre Meldung ist gespeichert und wird bei der nächsten Berichtigung des Archivs berücksichtigt.',
                'satz'    => self::anzeige($satz, (int) $mitglied['id']),
            ),
        );
    }

    /**
     * Sucht die Meldungen eines Zeitraums für den nächtlichen Bericht.
     *
     * @param array<string,array<int,array<string,mixed>>> $alle Ergebnis von lese()
     * @param int                                          $von  Beginn (Unix, ausschließlich)
     * @param int                                          $bis  Ende (Unix, einschließlich)
     *
     * @return array<int,array<string,mixed>> Sätze, älteste zuerst
     */
    public static function zeitraum(array $alle, int $von, int $bis): array
    {
        $treffer = array();

        foreach ($alle as $saetze) {
            foreach ($saetze as $satz) {
                $zeit = strtotime((string) ($satz['zeit'] ?? ''));

                if (false !== $zeit && $zeit > $von && $zeit <= $bis) {
                    $treffer[] = $satz;
                }
            }
        }

        usort($treffer, static function (array $a, array $b): int {
            return strcmp((string) $a['zeit'], (string) $b['zeit']);
        });

        return $treffer;
    }

    /**
     * Baut den Text der nächtlichen Mail an den Webmaster.
     *
     * Je Meldung: Zeitpunkt, Karteikarte (Name, NU-Nummer bzw. Gesamtperson),
     * der Eintrag (Archiv, ZDB-Abzug, Code, Turnier, Jahr), die Gründe, die
     * Felder von „Turnier fehlt", die Bemerkung und wer gemeldet hat. Die
     * Adresse der Karteikarte steht dabei, damit man direkt nachsehen kann.
     *
     * @param array<int,array<string,mixed>> $saetze   Sätze aus zeitraum()
     * @param string                         $spieler  Adresse der Spielerseite ohne Suffix, '' ohne Links
     * @param string                         $suffix   Adresssuffix
     *
     * @return string Text der Mail
     */
    public static function berichtText(array $saetze, string $spieler, string $suffix): string
    {
        $zeilen = array(
            \count($saetze).' neue '.(1 === \count($saetze) ? 'Reklamation' : 'Reklamationen').' im Wertungsarchiv:',
            '',
        );

        foreach ($saetze as $i => $satz) {
            $karte = (array) ($satz['karte'] ?? array());
            $kennung = '' !== (string) ($karte['nu'] ?? '') ? (string) $karte['nu'] : 'G'.(int) ($karte['gesamt'] ?? 0);
            $zeit = strtotime((string) ($satz['zeit'] ?? ''));

            $zeilen[] = ($i + 1).'. '.(false === $zeit ? '' : date('d.m.Y H:i', $zeit)).' — '.(self::FEHLT === ($satz['art'] ?? '') ? 'Turnier fehlt' : 'Eintrag reklamiert');
            $zeilen[] = '   Karteikarte: '.($karte['name'] ?? '').' ('.$kennung.')'.('' !== $spieler ? ' '.$spieler.'/'.$kennung.$suffix : '');

            if (\is_array($satz['eintrag'] ?? null)) {
                $e = $satz['eintrag'];
                $herkunft = (Darstellung::KUERZEL[$e['archiv'] ?? ''] ?? ($e['archiv'] ?? '')).(!empty($e['abzug']) && 'ZDB' !== $e['abzug'] ? ' (Abzug '.$e['abzug'].')' : '');
                $zeilen[] = '   Eintrag: '.trim(($e['turnier'] ?? '').' '.('' !== (string) ($e['code'] ?? '') ? '['.$e['code'].']' : '')).', '.substr((string) ($e['ende'] ?? ''), 0, 10).', aus '.$herkunft.', Person '.($e['kennung'] ?? '');
            }

            $anzeige = self::anzeige($satz, 0);

            foreach ($anzeige['gruende'] as $grund) {
                $zeilen[] = '   - '.$grund;
            }

            foreach ($anzeige['felder'] as $name => $wert) {
                $zeilen[] = '   '.$name.': '.$wert;
            }

            if ('' !== $anzeige['text']) {
                $zeilen[] = '   Bemerkung: '.str_replace("\n", "\n              ", $anzeige['text']);
            }

            $zeilen[] = '   Gemeldet von: '.($satz['mitglied']['name'] ?? '').' <'.($satz['mitglied']['email'] ?? '').'>';
            $zeilen[] = '';
        }

        $zeilen[] = 'Alle Meldungen stehen in var/wertungsarchiv/reklamationen.jsonl.';

        return implode("\n", $zeilen)."\n";
    }

    /**
     * Gibt Dialog und Skript einmal je Seite aus (TL_BODY, TL_JAVASCRIPT).
     *
     * Scheitert das (etwa weil kein Anfragetoken zu bekommen ist), kommt der
     * Fehler ins Systemprotokoll, und die Karteikarte erscheint ohne Knöpfe —
     * eine Reklamation darf die Archivzeilen nicht mitreißen.
     *
     * @return bool true, wenn der Dialog auf der Seite steht
     */
    protected static function dialog(): bool
    {
        if (self::$dialogAusgegeben) {
            return true;
        }

        try {
            $container = \Contao\System::getContainer();

            try {
                $ziel = $container->get('router')->generate(self::ROUTE);
            } catch (\Throwable $e) {
                $ziel = '/wertungsportal-api/archiv-reklamation';
            }

            $token = (string) $container->get('contao.csrf.token_manager')->getDefaultTokenValue();
        } catch (\Throwable $e) {
            \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Reklamationsdialog nicht ausgegeben — '.$e->getMessage(), __METHOD__, 'ERROR');

            return false;
        }

        $GLOBALS['TL_BODY']['wertungsportal_archivreklamation'] = self::dialogHtml($ziel, $token);
        $GLOBALS['TL_JAVASCRIPT']['wertungsportal_archivreklamation'] = 'bundles/contaowertungsportal/js/archivreklamation.js';

        return self::$dialogAusgegeben = true;
    }

    /**
     * HTML des Dialogs.
     *
     * Er nutzt die Klassen des Reklamationsdialogs (wp-reklamation) und
     * erbt dessen Aussehen. Der Teil „Gründe" gilt für Archivzeilen, der
     * Teil „Turnier" für „Turnier fehlt"; das Skript blendet den jeweils
     * anderen aus und schaltet seine Felder ab.
     *
     * @param string $ziel  Adresse des Controllers
     * @param string $token Anfragetoken
     *
     * @return string HTML
     */
    public static function dialogHtml(string $ziel, string $token): string
    {
        $e = static function (string $wert): string {
            return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
        };

        $gruende = '';

        foreach (self::GRUENDE as $wert => $text) {
            $gruende .= '<label class="wp-archivreklamation-grund"><input type="checkbox" name="gruende[]" value="'.$e($wert).'"> '.$e($text).'</label>';
        }

        $felder = '';

        foreach (self::FEHLT_FELDER as $name => $text) {
            $felder .= '<label for="wp-archivreklamation-'.$name.'">'.$e($text).'</label>'
                .'<input type="text" id="wp-archivreklamation-'.$name.'" name="felder['.$name.']" maxlength="'.self::FELD_LAENGE.'">';
        }

        return '<dialog id="wp-archivreklamation" class="wp-reklamation wp-archivreklamation" aria-labelledby="wp-archivreklamation-titel">'
            .'<form class="wp-reklamation-form" method="post" action="'.$e($ziel).'" novalidate>'
            .'<button type="button" class="wp-reklamation-schliessen" aria-label="Schließen" title="Schließen">×</button>'
            .'<h2 id="wp-archivreklamation-titel" data-feld="ueberschrift">Archiveintrag reklamieren</h2>'
            .'<p class="wp-reklamation-hinweis" data-feld="titel"></p>'
            .'<div class="wp-archivreklamation-bisher" data-feld="bisher" hidden></div>'
            .'<input type="hidden" name="REQUEST_TOKEN" value="'.$e($token).'">'
            .'<input type="hidden" name="kontext" value="">'
            .'<input type="hidden" name="signatur" value="">'
            .'<fieldset class="wp-archivreklamation-teil" data-teil="eintrag"><legend>Was stimmt nicht?</legend>'.$gruende.'</fieldset>'
            .'<fieldset class="wp-archivreklamation-teil" data-teil="fehlt"><legend>Welches Turnier fehlt?</legend>'.$felder.'</fieldset>'
            .'<label for="wp-archivreklamation-text">Bemerkung</label>'
            .'<textarea id="wp-archivreklamation-text" name="text" rows="5" maxlength="'.self::TEXT_LAENGE.'" placeholder="Was wissen Sie darüber? Etwa: wem das Turnier gehört, welcher Eintrag der richtige ist, wo es belegt ist."></textarea>'
            .'<p class="wp-reklamation-hinweis">Ihre Meldung wird gespeichert, nicht verschickt. Sie hilft, das Archiv zu berichtigen; andere sehen nur, dass und warum reklamiert wurde, nicht von wem.</p>'
            .'<p class="wp-reklamation-meldung" role="status" aria-live="polite"></p>'
            .'<div class="wp-reklamation-knoepfe">'
            .'<button type="submit" class="wp-reklamation-senden">Absenden</button>'
            .'<button type="button" class="wp-reklamation-abbrechen">Abbrechen</button>'
            .'</div>'
            .'</form>'
            .'</dialog>';
    }

    /**
     * Bereitet einen Wert für ein HTML-Attribut vor — wie
     * Helper\Reklamation::attribut(): geschweifte Klammern als Entitäten
     * (Insert-Tags), Zeilenumbrüche als Zeichenreferenz (Contaos
     * Markup-Minimierung).
     *
     * @param string $wert Wert
     *
     * @return string Maskierter Wert
     */
    public static function attribut(string $wert): string
    {
        return str_replace(
            array('{', '}', "\r\n", "\r", "\n", "\t"),
            array('&#123;', '&#125;', '&#10;', '&#10;', '&#10;', '&#9;'),
            htmlspecialchars($wert, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Baut eine Fehlerantwort für den Controller.
     *
     * @param int    $status  HTTP-Status
     * @param string $meldung Meldung für das Formular
     *
     * @return array{status:int,daten:array<string,mixed>}
     */
    protected static function antwort(int $status, string $meldung): array
    {
        return array('status' => $status, 'daten' => array('ok' => false, 'fehler' => $meldung));
    }
}
