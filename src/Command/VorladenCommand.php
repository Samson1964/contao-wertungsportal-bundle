<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Schachbulle\ContaoWertungsportalBundle\Cron\TurnierVorlader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Stößt das nächtliche Vorladen von Hand an und zeigt dabei zu, was passiert.
 *
 * Warum ein eigener Befehl? `contao:cron` führt nur aus, was nach seinem
 * Zeitplan gerade **fällig** ist — der Vorlader steht auf „alle 10 Minuten
 * zwischen 1 und 3 Uhr" und rührt sich um 14 Uhr nicht. Ein „nur diesen einen
 * Job jetzt" kennt Contao 4.13 nicht, und ein Erzwingen gibt es auch nicht.
 *
 * Vor allem aber: Der Cronjob **schweigt**. Er fängt jeden Fehler ab, damit ein
 * einzelnes Turnier den Lauf nicht beendet, und schreibt am Ende eine einzige
 * Zeile ins Systemprotokoll. Auf der Kommandozeile sieht man deshalb nichts —
 * auch keine Ausnahmen, denn abgefangene Fehler erreichen die Ausgabe nie.
 * Dieser Befehl hängt sich als Beobachter ein und schreibt jeden Fehlschlag
 * sofort samt Grund heraus.
 */
class VorladenCommand extends Command
{
    protected static $defaultName = 'wertungsportal:vorladen';

    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    /**
     * Nimmt das Contao-Framework entgegen; ohne dessen Start gibt es weder
     * Einstellungen noch Datenbank.
     */
    public function __construct(ContaoFrameworkInterface $framework)
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
            ->setDescription('Lädt Turnierdaten und Karteikarten in den Zwischenspeicher (wie der nächtliche Cronjob, aber sofort und mit Ausgabe)')
            ->addOption('budget', 'b', InputOption::VALUE_REQUIRED, 'Laufzeit in Sekunden', (string) TurnierVorlader::ZEITBUDGET)
            ->addOption('alle', 'a', InputOption::VALUE_NONE, 'Erfolge einzeln ausgeben, nicht nur Fehlschläge')
            ->addOption('protokoll', 'p', InputOption::VALUE_REQUIRED, 'Alle Fehlschläge zusätzlich in diese Datei schreiben')
            ->setHelp(
                "Der Befehl macht dasselbe wie der nächtliche Cronjob, aber sofort und mit\n"
                ."sichtbarer Ausgabe. Fehlschläge erscheinen mit Grund, am Ende steht eine\n"
                ."Übersicht.\n\n"
                ."Die Ruhezeit nach dem Abschlusslauf (3:01 bis 3:59) gilt hier NICHT — wer\n"
                ."den Befehl eintippt, will ihn laufen sehen. Die Einstellungen im Backend\n"
                ."gelten weiter: Ist das Vorladen oder der Live-Abruf abgeschaltet oder fehlen\n"
                ."die Zugangsdaten, sagt der Befehl das und tut nichts.\n\n"
                ."Bei einem langen Lauf scrollen die ersten Meldungen aus dem Fenster. Dagegen\n"
                ."hilft --protokoll (nur die Fehlschläge, sauber untereinander) oder das\n"
                ."Mitschreiben der ganzen Ausgabe mit tee.\n\n"
                ."Beispiele:\n"
                ."  wertungsportal:vorladen                        einmal mit der üblichen Laufzeit\n"
                ."  wertungsportal:vorladen --budget=600           zehn Minuten lang\n"
                ."  wertungsportal:vorladen --alle                 jeden einzelnen Abruf zeigen\n"
                ."  wertungsportal:vorladen -p var/logs/vorlader.log   Fehlschläge in eine Datei\n"
                ."  wertungsportal:vorladen 2>&1 | tee vorlader.log    alles mitschreiben\n"
            )
        ;
    }

    /**
     * Führt den Lauf aus.
     *
     * Der Rückgabewert taugt für Skripte: 0, wenn der Lauf ordentlich zu Ende
     * kam, 1 nach einem Abbruch wegen anhaltender Fehlschläge.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 = in Ordnung, 1 = abgebrochen, 2 = gar nicht gelaufen
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $hindernis = $this->hindernis();

        if (null !== $hindernis) {
            $io->warning('Es wurde nichts vorgeladen: '.$hindernis);

            return 2;
        }

        $budget = max(5, (int) $input->getOption('budget'));
        $alle = (bool) $input->getOption('alle');
        $protokoll = (string) ($input->getOption('protokoll') ?? '');

        if ('' !== $protokoll) {
            // Gleich zu Beginn anlegen: Scheitert das Schreiben erst nach zwei
            // Minuten Lauf, ist die Zeit verloren und die Fehler sind es auch
            if (false === @file_put_contents($protokoll, '# Vorladen '.date('Y-m-d H:i:s')."\n", FILE_APPEND)) {
                $io->error('In die Datei '.$protokoll.' lässt sich nicht schreiben.');

                return 2;
            }

            $io->text('Fehlschläge werden zusätzlich nach '.$protokoll.' geschrieben.');
        }

        $io->title('Wertungsportal: Vorladen');
        $io->text('Laufzeit bis zu '.$budget.' Sekunden. Abbruch nach fünf Fehlschlägen hintereinander.');
        $io->newLine();

        $zaehler = [];
        $fehler = [];
        $start = microtime(true);

        $vorlader = new TurnierVorlader();
        $vorlader
            ->setAufAbruf()
            ->setBudget($budget)
            ->setMelder(
                static function (string $funktion, string $schluessel, bool $erfolg, string $grund) use ($io, $alle, $protokoll, &$zaehler, &$fehler): void {
                    if ($erfolg) {
                        $zaehler[$funktion] = ($zaehler[$funktion] ?? 0) + 1;

                        if ($alle) {
                            $io->writeln('  <info>ok</info>      '.$funktion.' '.$schluessel);
                        } elseif (0 === array_sum($zaehler) % 25) {
                            // Ohne ein Lebenszeichen sieht ein Lauf über
                            // Minuten wie ein Hänger aus
                            $io->writeln('  <comment>…</comment>       '.array_sum($zaehler).' geholt');
                        }

                        return;
                    }

                    $fehler[] = $funktion.' '.$schluessel.': '.$grund;

                    // Fehlschläge IMMER zeigen — sie sind der Grund, warum es
                    // diesen Befehl gibt
                    $io->writeln('  <fg=red>FEHLER</>  '.$funktion.' '.$schluessel);
                    $io->writeln('          '.$grund);

                    // ... und bei Bedarf zusätzlich wegschreiben. Bei einem
                    // langen Lauf scrollen die ersten Meldungen sonst aus dem
                    // Fenster, und der Anfang ist oft der aufschlussreichste Teil
                    if ('' !== $protokoll) {
                        @file_put_contents($protokoll, date('H:i:s').' '.$funktion.' '.$schluessel.': '.$grund."\n", FILE_APPEND);
                    }
                }
            )
        ;

        $vorlader('cli');

        $dauer = microtime(true) - $start;

        $io->newLine();
        $io->section('Ergebnis');

        if ($zaehler) {
            $zeilen = [];

            foreach ($zaehler as $funktion => $anzahl) {
                $zeilen[] = [$funktion, $anzahl];
            }

            $zeilen[] = ['<info>zusammen</info>', '<info>'.array_sum($zaehler).'</info>'];

            $io->table(['Abruf', 'Anzahl'], $zeilen);
        } else {
            $io->text('Nichts geholt — entweder liegt schon alles vor oder es ging nichts.');
        }

        $io->text(sprintf('Laufzeit: %.1f s von %d s', $dauer, $budget));

        if ($fehler) {
            $io->newLine();
            $io->text(\count($fehler).' Fehlschläge, zuletzt:');
            $io->text('  '.end($fehler));
        }

        // Fünf Fehlschläge hintereinander beenden den Lauf; das ist mehr als
        // ein Schönheitsfehler und gehört als Rückgabewert nach außen.
        // Gefragt wird der Vorlader selbst — aus Laufzeit und Fehlerzahl ließe
        // es sich nur raten
        if ($vorlader->abgebrochen()) {
            $io->error('Der Lauf wurde nach anhaltenden Fehlschlägen abgebrochen. Die Einzelheiten stehen oben.');

            return 1;
        }

        $io->success('Fertig. Die Zusammenfassung steht auch im Systemprotokoll.');

        return 0;
    }

    /**
     * Prüft die Schalter, die einen Lauf von vornherein verhindern.
     *
     * Dieselben Bedingungen prüft der Cronjob selbst — dort führen sie
     * kommentarlos zum Nichtstun, was auf der Kommandozeile ratlos machen
     * würde („er sagt nichts und tut nichts").
     *
     * @return string|null Klartext des Hindernisses, null wenn nichts im Weg steht
     */
    private function hindernis(): ?string
    {
        if (!empty($GLOBALS['TL_CONFIG']['wertungsportal_cron_aus'])) {
            return 'Das nächtliche Vorladen ist in den Einstellungen abgeschaltet (Wertungsportal → „Nächtliches Vorladen abschalten").';
        }

        if (!empty($GLOBALS['TL_CONFIG']['wertungsportal_api_aus'])) {
            return 'Der Live-Abruf ist in den Einstellungen abgeschaltet.';
        }

        if (!\Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client::eingerichtet()) {
            return 'Es sind keine vollständigen Zugangsdaten für die Schnittstelle hinterlegt (Basisadresse, Kennung, Geheimnis, Token-Adresse).';
        }

        if (!\Schachbulle\ContaoWertungsportalBundle\Helper\API::cacheAktiv('Turnierauswertung')) {
            return 'Der Zwischenspeicher für Turnierauswertungen steht auf „aus" — dann gäbe es nichts vorzuladen.';
        }

        return null;
    }
}
