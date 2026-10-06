<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Helper\Mitgliedergruppe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gleicht die „Mitgliedergruppe für DSB-Mitglieder" von Hand ab — dasselbe
 * wie der monatliche Cronjob, aber sofort und mit Ausgabe (ab 1.54.0).
 *
 * Warum ein eigener Befehl? `contao:cron` führt nur aus, was gerade fällig
 * ist; der monatliche Lauf rührt sich sonst erst zum Monatswechsel. Und der
 * Lauf nimmt Mitgliedern eine Gruppe — vor dem ersten Mal will man sehen, wen
 * es trifft. Dafür gibt es --dry-run: Er rechnet, schreibt aber nichts.
 */
class MitgliedergruppeCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) nimmt ihn aus dem Attribut command am Tag in
     * services.yml; beide müssen gleich lauten
     * (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:mitgliedergruppe';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * Nimmt das Contao-Framework entgegen; ohne dessen Start gibt es weder
     * Einstellungen noch Datenbank.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        parent::__construct();
    }

    /**
     * Beschreibt den Befehl und seine Schalter für `--help`.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Ordnet Mitgliedern die Mitgliedergruppe für DSB-Mitglieder zu (wie der monatliche Cronjob, aber sofort und mit Ausgabe)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, wer die Gruppe bekäme oder verlöre — nichts schreiben')
            ->addOption('ids', null, InputOption::VALUE_NONE, 'Die IDs der betroffenen Mitglieder ausgeben')
            ->setHelp(
                "Wessen E-Mail-Adresse (tl_member.email) im Spielerbestand vorkommt\n"
                ."(tl_wertungsportal_persons.email1 oder email2), bekommt die Gruppe aus\n"
                ."Wertungsportal → Einstellungen → „Mitgliedergruppe für DSB-Mitglieder\";\n"
                ."wer nicht vorkommt, verliert sie. Andere Gruppen bleiben unberührt.\n\n"
                ."Ohne eingestellte Gruppe und bei leerem Spielerbestand geschieht nichts.\n\n"
                ."Beispiele:\n"
                ."  wertungsportal:mitgliedergruppe --dry-run         ansehen, was geschähe\n"
                ."  wertungsportal:mitgliedergruppe --dry-run --ids   dazu die Mitglieds-IDs\n"
                ."  wertungsportal:mitgliedergruppe                   abgleichen\n"
            )
        ;
    }

    /**
     * Führt den Abgleich aus und zeigt das Ergebnis.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 = abgeglichen (oder Probelauf gerechnet), 2 = nicht
     *             gelaufen (keine Gruppe, Gruppe fehlt, Bestand leer, Fehler)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $trocken = (bool) $input->getOption('dry-run');
        $ergebnis = Mitgliedergruppe::abgleichen($trocken);
        $meldung = Mitgliedergruppe::meldung($ergebnis, $trocken);

        if ('ok' !== $ergebnis['status']) {
            $io->warning($meldung);

            return 2;
        }

        if ($input->getOption('ids')) {
            $io->writeln(($trocken ? 'Bekämen die Gruppe: ' : 'Zugeordnet: ').($ergebnis['hinzu'] ? implode(', ', $ergebnis['hinzu']) : '–'));
            $io->writeln(($trocken ? 'Verlören die Gruppe: ' : 'Entfernt: ').($ergebnis['weg'] ? implode(', ', $ergebnis['weg']) : '–'));
        }

        $io->success($meldung);

        return 0;
    }
}
