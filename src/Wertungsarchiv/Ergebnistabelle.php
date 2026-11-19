<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

/**
 * Baut aus den Partien einer Turnier-JSON die Ergebnisansicht (ab 2.0.0):
 * Kreuztabelle bei Rundenturnieren, sonst Rundenansicht.
 *
 * Die Partien der Übergabe stehen je Spieler aus seiner Sicht da (runde,
 * spieler, gegner, ergebnis …); `spieler` und `gegner` sind Nummern der
 * Teilnehmerliste. Die Farbe steht seit Rückfrage R3 in `farbe` (w/s): DeWIS
 * und Wertungsportal führen sie, ELOBASE nie (null). Die Ansicht zeigt sie,
 * wo sie bekannt ist, und kommt sonst ohne sie aus.
 *
 * Ob es ein Rundenturnier ist, steht nicht zuverlässig im Kopf: DeWIS führt
 * als Rundenzahl oft nur den Vorgabewert 5. Entschieden wird an den Partien
 * selbst (siehe istRundenturnier()).
 */
class Ergebnistabelle
{
    /**
     * Höchstzahl der Spieler für eine Kreuztabelle; darüber wird sie zu breit.
     */
    public const KREUZ_HOECHSTENS = 30;

    /**
     * Anteil der gespielten Paare, ab dem ein Turnier als Rundenturnier gilt
     * (ein, zwei kampflose oder fehlende Partien sollen nicht schaden).
     */
    public const KREUZ_ANTEIL = 0.9;

    /**
     * Baut die Ansicht.
     *
     * Reihenfolge der Spieler: nach Platz, sonst nach Teilnehmernummer. In der
     * Kreuztabelle stehen die Spalten in derselben Reihenfolge; `pos` ist die
     * Nummer, unter der ein Spieler in Zeilen und Spalten erscheint. Spieler
     * mit Partien, aber ohne Eintrag in der Teilnehmerliste, kommen ans Ende.
     *
     * @param array<int,array<string,mixed>> $teilnehmer Zeilen der Teilnehmerliste (Darstellung::zeilen())
     * @param array<int,array<string,mixed>> $partien    Zeilen der Partien (Darstellung::zeilen())
     *
     * @return array{runden:int,kreuz:bool,farben:bool,spieler:array<int,array<string,mixed>>}
     *         farben = mindestens eine Partie mit bekannter Farbe; spieler in
     *         Anzeigereihenfolge mit nr, pos, name, platz, dwz, index,
     *         punkte_halbe, partien, runden (Runde => Liste [gegner, gegner_pos,
     *         gegner_name, ergebnis, farbe]) und kreuz (Gegner-Position =>
     *         Liste [ergebnis, farbe]); farbe ist 'w', 's' oder null
     */
    public static function bauen(array $teilnehmer, array $partien): array
    {
        $spieler = array();

        foreach ($teilnehmer as $t) {
            $nr = (int) ($t['nr'] ?? 0);
            $spieler[$nr] = array(
                'nr'           => $nr,
                'name'         => $t['name'] ?? null,
                'platz'        => $t['platz'] ?? null,
                'dwz'          => $t['dwz_vorher'] ?? null,
                'index'        => $t['index_vorher'] ?? null,
                'punkte_halbe' => $t['punkte_halbe'] ?? null,
                'partien'      => $t['partien'] ?? null,
                'person'       => $t['person'] ?? null,
                'runden'       => array(),
                'kreuz'        => array(),
            );
        }

        $runden = 0;
        $farben = false;

        foreach ($partien as $p) {
            $nr = (int) ($p['spieler'] ?? 0);

            if (!isset($spieler[$nr])) {
                $spieler[$nr] = array('nr' => $nr, 'name' => null, 'platz' => null, 'dwz' => null, 'index' => null, 'punkte_halbe' => null, 'partien' => null, 'person' => null, 'runden' => array(), 'kreuz' => array());
            }

            $runde = (int) ($p['runde'] ?? 0);
            $runden = max($runden, $runde);
            // Nur w/s gelten; alles andere (auch ELOBASE: immer null) heißt unbekannt
            $farbe = \in_array($p['farbe'] ?? null, array('w', 's'), true) ? $p['farbe'] : null;
            $farben = $farben || null !== $farbe;
            $spieler[$nr]['runden'][$runde][] = array('gegner' => null === ($p['gegner'] ?? null) ? null : (int) $p['gegner'], 'ergebnis' => (string) ($p['ergebnis'] ?? ''), 'farbe' => $farbe);
        }

        // Anzeigereihenfolge und Positionen
        uasort($spieler, static function (array $a, array $b): int {
            $pa = (int) ($a['platz'] ?? 0) ?: PHP_INT_MAX;
            $pb = (int) ($b['platz'] ?? 0) ?: PHP_INT_MAX;

            return array($pa, $a['nr']) <=> array($pb, $b['nr']);
        });

        $pos = array();
        $i = 0;
        foreach ($spieler as $nr => $s) {
            $pos[$nr] = ++$i;
            $spieler[$nr]['pos'] = $i;
        }

        // Gegner auflösen: Position und Name für die Anzeige
        foreach ($spieler as $nr => $s) {
            foreach ($s['runden'] as $r => $liste) {
                foreach ($liste as $k => $erg) {
                    $g = $erg['gegner'];
                    $spieler[$nr]['runden'][$r][$k]['gegner_pos'] = null !== $g ? ($pos[$g] ?? null) : null;
                    $spieler[$nr]['runden'][$r][$k]['gegner_name'] = null !== $g ? ($spieler[$g]['name'] ?? null) : null;

                    if (null !== $g && isset($pos[$g])) {
                        $spieler[$nr]['kreuz'][$pos[$g]][] = array('ergebnis' => $erg['ergebnis'], 'farbe' => $erg['farbe']);
                    }
                }
            }
        }

        return array(
            'runden'  => $runden,
            'kreuz'   => self::istRundenturnier($spieler),
            'farben'  => $farben,
            'spieler' => array_values($spieler),
        );
    }

    /**
     * Entscheidet an den Partien, ob eine Kreuztabelle passt.
     *
     * Rundenturnier heißt: Jeder spielt gegen jeden. Gezählt werden die
     * verschiedenen Paare, die gegeneinander gespielt haben; ab
     * KREUZ_ANTEIL aller möglichen Paare und höchstens KREUZ_HOECHSTENS
     * Spielern gilt das Turnier als Rundenturnier. Doppelrunden zählen ein
     * Paar nur einmal.
     *
     * @param array<int,array<string,mixed>> $spieler Spieler mit kreuz (Gegner-Position => Ergebnisse)
     *
     * @return bool true für eine Kreuztabelle
     */
    public static function istRundenturnier(array $spieler): bool
    {
        $n = \count($spieler);

        if ($n < 3 || $n > self::KREUZ_HOECHSTENS) {
            return false;
        }

        $paare = 0;

        foreach ($spieler as $s) {
            $paare += \count($s['kreuz']);
        }

        // Jedes Paar steht bei beiden Spielern
        return $paare / 2 >= self::KREUZ_ANTEIL * $n * ($n - 1) / 2;
    }
}
