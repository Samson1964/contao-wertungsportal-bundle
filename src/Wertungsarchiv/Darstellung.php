<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

/**
 * Anzeigeformen für Daten des Wertungsarchivs (Etappe 3, ab 2.0.0).
 *
 * Die Archive führen die Einheiten von ELOBASE: Punkte als Halbpunkte,
 * Punkterwartung als We · 1000, DWZ und Index getrennt. Umgerechnet wird nur
 * für die Anzeige, nie in den Daten. Ohne Contao, damit prüfbar.
 *
 * Alle Texte aus dem Archiv werden hier maskiert: Die Vorlagen geben die
 * Zeilen der Karteikarte unverändert aus.
 */
class Darstellung
{
    /**
     * Jahrzehnt je erster Stelle eines ELOBASE-Codes (JJWW-…): 7 = 1970er,
     * A = 2000er, B = 2010er.
     */
    public const JAHRZEHNT = array('7' => 1970, '8' => 1980, '9' => 1990, 'A' => 2000, 'B' => 2010, 'C' => 2020);

    /**
     * Halbpunkte als Punktzahl: 9 → „4½", 8 → „4", 1 → „½".
     *
     * @param mixed $halbe Halbpunkte, null = keine Angabe
     *
     * @return string Anzeige, '' ohne Angabe
     */
    public static function punkte($halbe): string
    {
        if (null === $halbe || '' === $halbe) {
            return '';
        }

        $halbe = (int) $halbe;
        $ganz = intdiv($halbe, 2);

        return (0 === $ganz && 1 === $halbe % 2 ? '' : (string) $ganz).(1 === $halbe % 2 ? '½' : '');
    }

    /**
     * We · 1000 als Punkterwartung mit drei Stellen: 7215 → „7,215".
     *
     * @param mixed $we1000 We · 1000, null = keine Angabe
     *
     * @return string Anzeige, '' ohne Angabe
     */
    public static function we($we1000): string
    {
        return (null === $we1000 || '' === $we1000) ? '' : number_format((int) $we1000 / 1000, 3, ',', '');
    }

    /**
     * DWZ mit Index in der Form der Karteikarte („2583 - 13", Zahlen mit
     * geschützten Leerzeichen bündig wie Helper::DWZ()).
     *
     * @param mixed $dwz   DWZ, 0/null = keine
     * @param mixed $index Index, null = keiner
     *
     * @return string HTML, '' ohne DWZ
     */
    public static function dwz($dwz, $index): string
    {
        if (empty($dwz)) {
            return '';
        }

        $zahl = str_replace(' ', '&nbsp;&nbsp;', sprintf('%4d', (int) $dwz));

        return (null === $index || '' === $index) ? $zahl : $zahl.' -'.str_replace(' ', '&nbsp;&nbsp;', sprintf('%3d', (int) $index));
    }

    /**
     * Jahr eines Karteieintrags: aus dem Turnierende, sonst aus dem Code.
     *
     * @param mixed $ende Turnierende JJJJ-MM-TT
     * @param mixed $code Turniercode (ELOBASE JJWW-…, DeWIS B…/C…)
     *
     * @return string Jahr, '' wenn keins zu erkennen ist
     */
    public static function jahr($ende, $code): string
    {
        if (\is_string($ende) && preg_match('/^(\d{4})/', $ende, $m)) {
            return $m[1];
        }

        if (\is_string($code) && preg_match('/^([7-9A-C])(\d)\d\d-/', $code, $m)) {
            return (string) (self::JAHRZEHNT[$m[1]] + (int) $m[2]);
        }

        return '';
    }

    /**
     * Kürzt einen Turniernamen auf 60 Zeichen (wie Helper::Turnierkurzname).
     *
     * @param string $name Turniername
     *
     * @return string Gekürzter Name mit „ [...]"
     */
    public static function kurzname(string $name): string
    {
        return mb_strlen($name) > 60 ? mb_substr($name, 0, 60).' [...]' : $name;
    }

    /**
     * Bereitet eine Karteizeile aus Archiv::altkartei() für die Tabelle der
     * Karteikarte auf (gleiche Schlüssel wie die Zeilen von nu).
     *
     * - Turnier: Link auf `turniere/A<id>`, wenn es eine Turnierdatei gibt;
     *   Einträge ohne Turnier (Ausgangswerte, „ungültig:…") als
     *   „DWZ-Ausgangswert".
     * - Leistung: nur die Turnierleistung (DeWIS). ELOBASE führt in der Kartei
     *   die Erfolgszahl, die kein Leistungsmaß ist — sie bleibt weg.
     * - `vor`: Turnier vor der ersten Mitgliedschaft (Frank Binding,
     *   18.11.2026: kennzeichnen, nicht ausblenden).
     *
     * @param array<string,mixed> $zeile  Karteizeile mit t_…-Feldern
     * @param string              $basis  Adresse der Turnierseite ohne Suffix
     * @param string              $suffix Adresssuffix, z. B. „.html"
     *
     * @return array<string,mixed> nummer, jahr, turnier, punkte, partien, we, e,
     *                             gegner, leistung, dwz-neu, vor, archiv
     */
    public static function karteizeile(array $zeile, string $basis, string $suffix): array
    {
        $e = static function ($text): string {
            return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $extra = \is_string($zeile['extra'] ?? null) ? (array) json_decode($zeile['extra'], true) : array();
        $code = (string) ($zeile['code'] ?? $zeile['t_quell_id'] ?? '');
        $name = (string) ($zeile['t_name'] ?? '');

        if (null === ($zeile['t_id'] ?? null) || 0 === strpos($code, 'ungültig:') || false !== strpos((string) ($zeile['t_kennzeichen'] ?? ''), 'kein_turnier')) {
            // Ausgangseinträge der ZDB tragen nur JJWW, der Rest ist leer
            // („9200-  -  "); dann ohne Code
            $zeigen = '' !== $code && 0 !== strpos($code, 'ungültig:') && !preg_match('/^\d{4}[\s-]*$/', $code);
            $turnier = 'DWZ-Ausgangswert'.($zeigen ? ' ('.$e(trim($code)).')' : '');
        } else {
            $titel = '' !== $name ? $name : $code;
            $turnier = '' !== (string) ($zeile['t_json'] ?? '')
                ? sprintf('<a href="%s/A%d%s" title="%s">%s</a>', $e(rtrim($basis, '/')), (int) $zeile['t_id'], $e($suffix), $e($titel.('' !== $code ? ' ('.$code.')' : '')), $e(self::kurzname($titel)))
                : $e(self::kurzname($titel));
        }

        $archiv = (string) ($zeile['archiv'] ?? '');

        return array(
            'nummer'   => array('elobase' => 'ZDB', 'dewis' => 'DeWIS', 'wp' => 'Portal')[$archiv] ?? $archiv,
            'jahr'     => self::jahr($zeile['t_ende'] ?? $zeile['ende'] ?? null, $code),
            'turnier'  => $turnier,
            'punkte'   => self::punkte($zeile['punkte_halbe'] ?? null),
            'partien'  => null === ($zeile['partien'] ?? null) ? '' : (string) (int) $zeile['partien'],
            'we'       => self::we($zeile['we1000'] ?? null),
            'e'        => isset($extra['e_koeffizient']) ? $e($extra['e_koeffizient']) : '',
            'gegner'   => empty($zeile['gegnerschnitt']) ? '' : (string) (int) $zeile['gegnerschnitt'],
            'leistung' => empty($zeile['turnierleistung']) ? '' : (string) (int) $zeile['turnierleistung'],
            'dwz-neu'  => self::dwz($zeile['dwz'] ?? null, $zeile['dwz_index'] ?? null),
            'vor'      => 1 === (int) ($zeile['vor_erster_anmeldung'] ?? 0),
            'archiv'   => $archiv,
        );
    }

    /**
     * Ordnet eine Zeile einer Tabelle der Turnier-JSON ihren Feldnamen zu
     * (`{felder: [...], zeilen: [...]}` — Felder über den Namen lesen, nie
     * über die Position).
     *
     * @param array<string,mixed> $tabelle Teil der Turnier-JSON, z. B. teilnehmer
     *
     * @return array<int,array<string,mixed>> Zeilen als Feld => Wert
     */
    public static function zeilen(array $tabelle): array
    {
        $felder = (array) ($tabelle['felder'] ?? array());
        $anzahl = \count($felder);
        $ergebnis = array();

        foreach ((array) ($tabelle['zeilen'] ?? array()) as $z) {
            if (\is_array($z) && \count($z) === $anzahl) {
                $ergebnis[] = array_combine($felder, $z);
            }
        }

        return $ergebnis;
    }
}
