<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Cron;

use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Archivreklamation;

/**
 * Nächtlicher Bericht über neue Reklamationen im Wertungsarchiv (ab 2.0.0).
 *
 * Schickt dem Webmaster (Contao-Einstellung „E-Mail-Adresse des
 * Systemadministrators", adminEmail) eine Textmail mit allen Meldungen, die
 * seit dem letzten Bericht in `var/wertungsarchiv/reklamationen.jsonl`
 * dazugekommen sind (Frank Binding, 20.11.2026). Ohne neue Meldungen geht
 * nichts hinaus.
 *
 * „Seit dem letzten Bericht" statt „vom Vortag": Der Zeitpunkt des zuletzt
 * gemeldeten Satzes steht in `var/wertungsarchiv/reklamationen.bericht`.
 * Fällt ein Lauf aus (ohne Cronjob beim Hoster löst nur ein Seitenaufruf den
 * Termin aus), kommt nichts abhanden; der nächste Bericht ist dann länger.
 * Scheitert der Versand, bleibt der Zeitpunkt stehen, und der nächste Lauf
 * versucht es mit denselben Meldungen noch einmal.
 */
class Archivreklamationsbericht
{
    /**
     * Rückblick des allerersten Laufs in Sekunden (ohne Zustandsdatei).
     */
    public const ERSTER_RUECKBLICK = 86400;

    /**
     * Führt den Lauf aus.
     *
     * Contao ruft die Methode über den Dienst-Tag contao.cronjob auf.
     *
     * @param string $scope Aufrufbereich ('cli' oder 'web'), nur fürs Protokoll
     *
     * @return int Anzahl der gemeldeten Reklamationen, 0 ohne neue oder bei Fehler
     */
    public function __invoke($scope = 'cli'): int
    {
        $datei = Archivreklamation::datei();
        $empfaenger = trim((string) \Contao\Config::get('adminEmail'));

        if ('' === $datei || !is_file($datei) || '' === $empfaenger) {
            return 0;
        }

        $zustand = $datei.'.bericht';
        $jetzt = time();
        $von = is_file($zustand) ? (int) trim((string) file_get_contents($zustand)) : $jetzt - self::ERSTER_RUECKBLICK;
        $saetze = Archivreklamation::zeitraum(Archivreklamation::lese($datei), $von, $jetzt);

        if (!$saetze) {
            return 0;
        }

        try {
            $absender = \Schachbulle\ContaoWertungsportalBundle\Helper\Reklamation::absender();
            $objEmail = new \Contao\Email();
            $objEmail->from = $absender['email'];
            $objEmail->fromName = $absender['name'];
            $objEmail->subject = 'Wertungsarchiv: '.\count($saetze).' neue '.(1 === \count($saetze) ? 'Reklamation' : 'Reklamationen');
            $seite = self::spielerseite();
            $objEmail->text = Archivreklamation::berichtText($saetze, $seite, '' !== $seite ? (string) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::urlSuffix() : '');

            if (!$objEmail->sendTo(array($empfaenger))) {
                throw new \RuntimeException('Der Mailversand meldete einen Fehler.');
            }
        } catch (\Throwable $e) {
            \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Reklamationsbericht nicht verschickt — '.$e->getMessage(), __METHOD__, 'ERROR');

            return 0;
        }

        // Bis zum letzten gemeldeten Satz — was während des Versands
        // dazukam, gehört in den nächsten Bericht
        file_put_contents($zustand, (string) strtotime((string) $saetze[\count($saetze) - 1]['zeit']), LOCK_EX);

        \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Wertungsarchiv: Bericht über '.\count($saetze).' Reklamationen an '.$empfaenger.' ('.$scope.')', __METHOD__, 'CRON');

        return \count($saetze);
    }

    /**
     * Adresse der Spielerseite für die Links im Bericht.
     *
     * Im Cronjob gibt es keine Anfrage; unter Contao 4.13 ist die Adresse
     * dann relativ. Eine relative Adresse hilft in einer Mail nicht — dann
     * stehen nur die Kennungen da.
     *
     * @return string Absolute Adresse ohne Suffix, '' wenn keine zu bekommen ist
     */
    private static function spielerseite(): string
    {
        try {
            $adresse = (string) \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getSpielerseiteUrl();
        } catch (\Throwable $e) {
            return '';
        }

        return preg_match('#^https?://[^/]+/#', $adresse) ? $adresse : '';
    }
}
