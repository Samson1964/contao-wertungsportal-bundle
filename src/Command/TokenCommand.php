<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Zeigt den Zustand des Zugangstokens — ohne die Schnittstelle zu belasten.
 *
 * Hintergrund: Die Schnittstelle von nu gibt je Kennung nur eine begrenzte Zahl
 * Zugangstoken aus und antwortet danach mit „Too much access tokens". Ein Token
 * lebt dabei nur wenige Minuten, es wird also laufend erneuert. Wieviele dabei
 * anfallen, hängt an einer einzigen Frage: **Wird das Token zwischen den
 * Aufrufen tatsächlich wiederverwendet?**
 *
 * Läßt sich die Tokendatei nicht schreiben — verschiedene Benutzer für Web und
 * Kommandozeile, ein eigenes /tmp je Dienst, ein Aufräumer dazwischen —, dann
 * holt sich JEDER Seitenaufruf und JEDER Cronlauf ein eigenes Token, und das
 * Kontingent ist in Stunden aufgebraucht. Von außen ist das nicht zu sehen,
 * deshalb dieser Befehl.
 *
 * Er fragt standardmäßig **nichts** bei der Schnittstelle an und kostet damit
 * auch kein Token.
 */
class TokenCommand extends Command
{
    protected static $defaultName = 'wertungsportal:token';

    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;

    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;

        parent::__construct();
    }

    /**
     * Beschreibt den Befehl und seinen einen Schalter.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Zeigt, ob das Zugangstoken der Schnittstelle richtig zwischengespeichert wird')
            ->addOption('pruefen', null, InputOption::VALUE_NONE, 'Zusätzlich einen echten Abruf machen (kostet ein Token!)')
            ->setHelp(
                "Ohne Schalter fragt der Befehl NICHTS bei der Schnittstelle an und kostet\n"
                ."damit auch kein Token. Er sieht nur nach, was örtlich hinterlegt ist.\n\n"
                ."Die entscheidende Zeile ist \"Schreibbar\". Steht dort NEIN, holt sich jeder\n"
                ."Seitenaufruf und jeder Cronlauf ein eigenes Zugangstoken — dann ist das\n"
                ."Kontingent bei nu binnen Stunden erschöpft, und daran ändert auch\n"
                ."Abwarten nichts.\n\n"
                ."Mit --pruefen wird ein einzelner öffentlicher und ein einzelner geschützter\n"
                ."Endpunkt abgerufen. Das kostet im ungünstigen Fall ein Token und sollte\n"
                ."nicht wiederholt werden, solange das Kontingent klemmt.\n"
            )
        ;
    }

    /**
     * Gibt den Zustand aus.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 alles in Ordnung, 1 die Tokendatei ist nicht brauchbar
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $io->title('Wertungsportal: Zugangstoken');

        $datei = OAuth2Client::tokendatei();
        $verzeichnis = \dirname($datei);
        $vorhanden = is_file($datei);

        // Schreibbarkeit an der Datei selbst, sonst am Verzeichnis — eine noch
        // nicht angelegte Datei ist kein Mangel
        $schreibbar = $vorhanden ? is_writable($datei) : is_writable($verzeichnis);

        $zeilen = [
            ['Basisadresse', (string) ($GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'] ?? '(nicht gepflegt)')],
            ['Scope', (string) ($GLOBALS['TL_CONFIG']['wertungsportal_scopeListe'] ?? '(nicht gepflegt)')],
            ['Zugangsdaten vollständig', OAuth2Client::eingerichtet() ? 'ja' : '<fg=red>NEIN</>'],
            ['Tokendatei', $datei],
            ['Vorhanden', $vorhanden ? 'ja' : 'nein (wird beim nächsten Abruf angelegt)'],
            ['<options=bold>Schreibbar</>', $schreibbar ? '<info>ja</info>' : '<fg=red>NEIN</>'],
        ];

        // Ein Ausweichen ins Systemverzeichnis ist der eigentliche Warnfall
        if (false === strpos($datei, 'system'.\DIRECTORY_SEPARATOR.'tmp') && false === strpos($datei, 'system/tmp')) {
            $zeilen[] = ['<fg=yellow>Ablageort</>', '<fg=yellow>Ausweichpfad — system/tmp war nicht nutzbar</>'];
        }

        if ($vorhanden) {
            $inhalt = json_decode((string) @file_get_contents($datei), true);

            if (!\is_array($inhalt)) {
                $zeilen[] = ['Inhalt', '<fg=red>nicht lesbar</>'];
            } else {
                $zeilen[] = ['Geändert', date('d.m.Y H:i:s', (int) filemtime($datei))];

                if (!empty($inhalt['gesperrt_bis'])) {
                    $bis = (int) $inhalt['gesperrt_bis'];
                    $zeilen[] = ['<fg=yellow>Wartezeit</>', $bis > time()
                        ? '<fg=yellow>läuft noch bis '.date('H:i:s', $bis).' ('.($bis - time()).' s)</>'
                        : 'abgelaufen um '.date('H:i:s', $bis)];
                    $zeilen[] = ['Letzter Grund', (string) ($inhalt['sperrgrund'] ?? '')];
                } elseif (!empty($inhalt['expires_at'])) {
                    $ablauf = (int) $inhalt['expires_at'];
                    $rest = $ablauf - time();
                    $zeilen[] = ['Token gültig bis', date('H:i:s', $ablauf).($rest > 0 ? ' (noch '.$rest.' s)' : ' <fg=yellow>(abgelaufen)</>')];
                    $zeilen[] = ['Refresh-Token', empty($inhalt['refresh_token']) ? '<fg=yellow>keins</>' : 'vorhanden'];
                }
            }
        }

        $io->table(['Angabe', 'Wert'], $zeilen);

        if (!$schreibbar) {
            $io->error(
                'Die Tokendatei läßt sich nicht schreiben. Damit holt sich jeder Seitenaufruf und '
                .'jeder Cronlauf ein eigenes Zugangstoken — das Kontingent bei nu ist dann binnen '
                .'Stunden erschöpft, und Abwarten hilft nicht. Bitte die Schreibrechte auf '
                .$verzeichnis.' prüfen.'
            );

            return 1;
        }

        if ($input->getOption('pruefen')) {
            $io->section('Abruf zur Probe');
            $io->text('Je ein Aufruf: einmal öffentlich, einmal mit Token.');
            $io->newLine();

            $client = new OAuth2Client();

            foreach ([
                'öffentlich (ohne Token)' => $client->apiBaseUrl.'/dwz/dwzliste/persons?lastname=Muster&firstname=Max',
                'geschützt (mit Token)' => $client->apiBaseUrl.'/dwz/tournaments?searchString=x&fromDate=2026-01-01&toDate=2026-01-02',
            ] as $was => $url) {
                $r = $client->callApiWithRefresh($url);
                $code = (int) ($r['http_code'] ?? 0);

                if (200 === $code) {
                    $io->writeln(sprintf('  <info>HTTP 200</info>  %s', $was));
                } else {
                    $io->writeln(sprintf('  <fg=red>HTTP %d</>  %s', $code, $was));
                    $io->writeln('            '.trim((string) ($r['error_message'] ?? '(ohne Meldung)')));
                }
            }

            $io->newLine();
            $io->text('Antwortet der öffentliche Endpunkt mit 200 und der geschützte mit 403, liegt es');
            $io->text('am Zugangstoken und nicht an der Verbindung.');
        }

        $io->success('Die Tokendatei ist brauchbar. Kommt trotzdem „Too much access tokens", liegt die Grenze bei nu.');

        return 0;
    }
}
