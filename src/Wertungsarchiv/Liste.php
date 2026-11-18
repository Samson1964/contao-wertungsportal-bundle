<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

/**
 * Liest eine Liste der Übergabe (`wertungsarchiv-uebergabe/1`, ab 2.0.0).
 *
 * Erste Zeile: {"format", "archiv", "inhalt", "felder": [...]}, jede weitere
 * ein Array in der Reihenfolge von `felder`. Die Felder werden über ihren
 * Namen gelesen, nie über die Position — die Aufbereitung darf Felder
 * ergänzen (UEBERGABE.md, Abschnitt 2).
 */
class Liste
{
    /**
     * Format, das dieser Leser kennt.
     */
    public const FORMAT = 'wertungsarchiv-uebergabe/1';

    /**
     * Liest den Kopf einer Liste.
     *
     * @param string $datei Pfad der .jsonl-Datei
     *
     * @throws \RuntimeException wenn die Datei fehlt oder der Kopf nicht passt
     *
     * @return array{format:string,archiv:string,inhalt:string,felder:array<int,string>}
     */
    public static function kopf(string $datei): array
    {
        if (!is_file($datei)) {
            throw new \RuntimeException('Liste fehlt: '.$datei);
        }

        $fp = fopen($datei, 'r');
        $zeile = fgets($fp);
        fclose($fp);

        $kopf = json_decode((string) $zeile, true);

        if (!\is_array($kopf) || self::FORMAT !== ($kopf['format'] ?? '') || !\is_array($kopf['felder'] ?? null)) {
            throw new \RuntimeException('Unbekannter Kopf in '.basename($datei).' — erwartet wird '.self::FORMAT.'.');
        }

        return $kopf;
    }

    /**
     * Liest die Datenzeilen als Felder nach Namen.
     *
     * @param string $datei Pfad der .jsonl-Datei
     *
     * @throws \RuntimeException bei einer Zeile, die kein JSON-Array mit der
     *                           Zahl der Felder ist (mit Zeilennummer)
     *
     * @return \Generator<int,array<string,mixed>> Zeilennummer (ab 2) => Felder
     */
    public static function zeilen(string $datei): \Generator
    {
        $felder = self::kopf($datei)['felder'];
        $anzahl = \count($felder);
        $fp = fopen($datei, 'r');
        fgets($fp);
        $nr = 1;

        try {
            while (false !== ($zeile = fgets($fp))) {
                ++$nr;

                if ('' === trim($zeile)) {
                    continue;
                }

                $werte = json_decode($zeile, true);

                if (!\is_array($werte) || \count($werte) !== $anzahl) {
                    throw new \RuntimeException(sprintf('%s, Zeile %d: kein Datensatz mit %d Feldern.', basename($datei), $nr, $anzahl));
                }

                yield $nr => array_combine($felder, $werte);
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Prüft eine Liste gegen `uebergabe.json` ihres Ordners: Prüfergebnis der
     * Aufbereitung und SHA-256 der Datei.
     *
     * @param string $ordner Ordner des Archivs (mit uebergabe.json)
     * @param string $name   Dateiname der Liste, z. B. „kartei.jsonl"
     *
     * @return string Fehlerbeschreibung, '' wenn alles passt
     */
    public static function pruefe(string $ordner, string $name): string
    {
        $uebergabe = json_decode((string) @file_get_contents($ordner.'/uebergabe.json'), true);

        if (!\is_array($uebergabe)) {
            return 'uebergabe.json fehlt oder ist kein JSON.';
        }

        $soll = $uebergabe['dateien'][$name]['sha256'] ?? null;

        if (null === $soll) {
            return $name.' steht nicht in uebergabe.json.';
        }

        $ist = hash_file('sha256', $ordner.'/'.$name);

        return hash_equals((string) $soll, (string) $ist) ? '' : $name.': Prüfsumme weicht ab (Datei geändert oder unvollständig übertragen).';
    }
}
