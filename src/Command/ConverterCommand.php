<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Classes\Converter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Baut aus der Deutschland-Datei des nu-Servers die Verbands-Archive.
 *
 * Lädt LV-0, reichert `spieler.csv` mit den FIDE-Daten aus
 * tl_wertungsportal_elo an, packt je Landesverband ein CSV-Zip ins
 * Jahresarchiv, kopiert die aktuellen Fassungen nach `export/csv/` und pflegt
 * die Dbafs.
 *
 * **Warum es diesen Befehl gibt:** Bis 1.35.2 lag die Arbeit in einem
 * eigenständigen Skript unter `src/Resources/public/`, das der Hoster per Curl
 * über eine URL angestoßen hat. Das Skript band `system/initialize.php` ein —
 * den Weg gibt es in Contao 5 nicht mehr, und der Aufruf über das Netz war nur
 * über einen Zugangsschlüssel abzusichern. Ein Konsolenbefehl braucht beides
 * nicht.
 *
 * **Für den Hoster-Cronjob** tritt an die Stelle des bisherigen Curl-Aufrufs:
 *
 *     vendor/bin/contao-console wertungsportal:converter
 *
 * Der Lauf ist unabhängig vom Download-Befehl: Er holt sich die
 * Deutschland-Datei selbst.
 */
class ConverterCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) liest die Eigenschaft nicht mehr; maßgeblich ist
     * das Attribut command am Tag in services.yml, und beide müssen gleich
     * lauten (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:converter';

    /**
     * @var ContaoFramework Wird gebraucht, damit die Legacy-Klassen
     *                               (Einstellungen, Datenbank, Dbafs)
     *                               bereitstehen
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
            ->setDescription('Erzeugt die CSV-Archive der Landesverbände aus der DWZ-Liste')
            ->setHelp(
                "Lädt LV-0 vom nu-Server, reichert spieler.csv mit den FIDE-Daten an\n".
                "und legt je Landesverband ein Zip im Jahresarchiv ab.\n\n".
                "Ersetzt den früheren Curl-Aufruf von\n".
                "bundles/contaowertungsportal/Wertungsportal_Converter.php.\n\n".
                "ACHTUNG: Ohne einmaligen FIDE-Elo-XML-Import ist tl_wertungsportal_elo\n".
                "leer — dann bleiben Elo, Titel und Land in den CSV-Dateien unverändert."
            );
    }

    /**
     * Führt den Umbau aus.
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
     * @return int 0 bei vollständigem Lauf, 1 bei Abbruch (Download oder
     *             Entpacken fehlgeschlagen)
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
            $converter = new Converter();
            $fehler = (int) $converter->run();
        } finally {
            ob_end_flush();
        }

        $output->writeln('');

        return $fehler;
    }
}
