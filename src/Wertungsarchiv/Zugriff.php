<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv;

/**
 * Wer die Ausgabe des Wertungsarchivs sieht (ab 2.0.0).
 *
 * Solange das Archiv erprobt wird, ist es für Besucher abgeschaltet (Frank
 * Binding, 18.11.2026). Sehen darf es nur ein angemeldetes Mitglied, bei dem
 * in tl_member der Schalter „Zugriff auf das Wertungsarchiv" gesetzt ist —
 * und nur, wenn das Archiv eingerichtet ist (WERTUNGSARCHIV_DATABASE_URL).
 * Jede Stelle der Ausgabe fragt hier und nirgends sonst.
 */
class Zugriff
{
    /**
     * Prüft, ob der aktuelle Besucher das Archiv sehen darf.
     *
     * Das Mitglied kommt über den Security-Helper von Symfony; der Weg über
     * FrontendUser::getInstance() ist in Contao 5 entfallen.
     *
     * @return bool true nur für angemeldete Mitglieder mit gesetztem Schalter
     *              bei eingerichtetem Archiv
     */
    public static function erlaubt(): bool
    {
        $container = \Contao\System::getContainer();

        if (!$container->get(Verbindung::class)->verfuegbar() || !$container->has('security.helper')) {
            return false;
        }

        $mitglied = $container->get('security.helper')->getUser();

        return $mitglied instanceof \Contao\FrontendUser && self::schalter($mitglied->wertungsarchiv ?? '');
    }

    /**
     * Wertet den Schalter aus tl_member aus.
     *
     * @param mixed $wert Wert der Spalte wertungsarchiv
     *
     * @return bool true bei „1"
     */
    public static function schalter($wert): bool
    {
        return '1' === (string) $wert;
    }
}
