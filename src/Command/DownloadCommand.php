<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Classes\Downloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Holt die zwanzig Landesverbands-Zips der DWZ-Liste vom nu-Server.
 *
 * **Warum es diesen Befehl gibt:** Bis 1.35.2 lag die Arbeit in einem
 * eigenständigen Skript unter `src/Resources/public/`, das der Hoster per Curl
 * über eine URL angestoßen hat. Das Skript band `system/initialize.php` ein —
 * den Weg gibt es in Contao 5 nicht mehr, und der Aufruf über das Netz war
 * nur über einen Zugangsschlüssel abzusichern. Ein Konsolenbefehl braucht
 * beides nicht.
 *
 * **Für den Hoster-Cronjob** tritt an die Stelle des bisherigen Curl-Aufrufs:
 *
 *     vendor/bin/contao-console wertungsportal:download
 *
 * Der Befehl gibt den Fortschritt zeilenweise aus, damit bei einem langen Lauf
 * zu sehen ist, wo er steht. Der Rückgabewert ist 0, wenn alle Dateien
 * angekommen sind, sonst 1 — ein Cronjob kann daran also erkennen, ob etwas
 * schiefgegangen ist.
 */
class DownloadCommand extends Command
{
    protected static $defaultName = 'wertungsportal:download';

    /**
     * @var ContaoFramework Wird gebraucht, damit die Legacy-Klassen
     *                               (Einstellungen, Datenbank) bereitstehen
     */
    private $framework;

    /**
     * Nimmt das Contao-Framework entgegen.
     *
     * @param ContaoFramework $framework Wird in execute() gestartet
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        parent::__construct();
    }

    /**
     * Beschreibt den Befehl.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Lädt die DWZ-Listen aller Landesverbände vom nu-Server')
            ->setHelp(
                "Legt die zwanzig Landesverbands-Zips datiert unter\n".
                "files/wertungsportal/downloads/ ab.\n\n".
                "Ersetzt den früheren Curl-Aufruf von\n".
                "bundles/contaowertungsportal/Wertungsportal_Download.php."
            );
    }

    /**
     * Führt den Download aus.
     *
     * Die Klasse gibt ihren Fortschritt per `echo` aus — das ist so aus dem
     * alten Skript übernommen, dessen Ablauf im Livebetrieb geprüft ist. Statt
     * die Ausgabe umzubauen, wird sie hier abgefangen und zeilenweise
     * weitergereicht: Der Ausgabepuffer arbeitet mit Blockgröße 1, gibt also
     * sofort weiter, statt bis zum Ende zu sammeln.
     *
     * @param InputInterface  $input  Wird nicht ausgewertet, der Befehl hat
     *                                keine Schalter
     * @param OutputInterface $output Ziel für Fortschritt und Ergebnis
     *
     * @return int 0 bei vollständigem Lauf, 1 wenn eine Datei fehlt
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        ob_start(
            static function (string $puffer) use ($output): string {
                $text = str_replace(array('<br>', "\r"), '', $puffer);
                if ($text !== '') {
                    $output->write($text);
                }

                return '';
            },
            1
        );

        try {
            $downloader = new Downloader();
            $fehlschlaege = (int) $downloader->run();
        } finally {
            ob_end_flush();
        }

        $output->writeln('');

        return $fehlschlaege > 0 ? 1 : 0;
    }
}
