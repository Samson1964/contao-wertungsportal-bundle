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
            ->addOption('auswertung', null, InputOption::VALUE_NONE, 'Das Tokenprotokoll auswerten: Anfragen, Wettläufe, neue Familien')
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

        // Wieviele Token die Anlage tatsächlich anfordert — die Zahl, die der
        // Gegenseite fehlt, wenn man über das Kontingent sprechen will
        $protokoll = OAuth2Client::tokenprotokoll();

        if ('' !== $protokoll && is_file($protokoll)) {
            $heute = date('Y-m-d');
            $ausgestellt = 0;
            $abgelehnt = 0;
            $ersteHeute = '';

            foreach (file($protokoll, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
                if (0 !== strpos($zeile, $heute)) {
                    continue;
                }

                $teile = explode(';', $zeile);

                if ('' === $ersteHeute) {
                    $ersteHeute = substr($teile[0] ?? '', 11, 5);
                }

                if ('abgelehnt' === ($teile[2] ?? '')) {
                    ++$abgelehnt;
                } else {
                    ++$ausgestellt;
                }
            }

            $zeilen[] = ['Tokenanfragen heute', $ausgestellt.' ausgestellt'.($abgelehnt > 0 ? ', <fg=red>'.$abgelehnt.' abgelehnt</>' : '').($ersteHeute !== '' ? ' (seit '.$ersteHeute.' Uhr)' : '')];
            $zeilen[] = ['Aufzeichnung', basename($protokoll)];
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

        if ($input->getOption('auswertung')) {
            $this->auswertung($io, $protokoll);
        }

        if ($input->getOption('pruefen')) {
            $io->section('Abruf zur Probe');
            $io->text('Je ein Aufruf: einmal öffentlich, einmal mit Token.');
            $io->newLine();

            $client = new OAuth2Client();
            $ergebnis = [];

            foreach ([
                'öffentlich (ohne Token)' => $client->apiBaseUrl.'/dwz/dwzliste/persons?lastname=Muster&firstname=Max',
                'geschützt (mit Token)' => $client->apiBaseUrl.'/dwz/tournaments?searchString=x&fromDate=2026-01-01&toDate=2026-01-02',
            ] as $was => $url) {
                $r = $client->callApiWithRefresh($url);
                $code = (int) ($r['http_code'] ?? 0);
                $ergebnis[$was] = $code;

                if (200 === $code) {
                    $io->writeln(sprintf('  <info>HTTP 200</info>  %s', $was));
                } else {
                    $io->writeln(sprintf('  <fg=red>HTTP %d</>  %s', $code, $was));
                    $io->writeln('            '.trim((string) ($r['error_message'] ?? '(ohne Meldung)')));
                }
            }

            $io->newLine();

            $oeffentlich = $ergebnis['öffentlich (ohne Token)'] ?? 0;
            $geschuetzt = $ergebnis['geschützt (mit Token)'] ?? 0;

            if (200 === $oeffentlich && 200 !== $geschuetzt) {
                $io->warning(
                    'Die Verbindung zu nu steht — der öffentliche Endpunkt antwortet. Nur das '
                    ."Zugangstoken ist nicht zu bekommen.\nAn dieser Anlage liegt es nicht: Die "
                    .'Tokendatei ist schreibbar und wird verwendet. Die Grenze liegt bei nu und '
                    .'gehört dort angesprochen (Kennung, Kontingent, Scope).'
                );

                return 1;
            }

            if (200 !== $oeffentlich) {
                $io->error('Schon der öffentliche Endpunkt antwortet nicht — dann steht die Verbindung selbst in Frage, nicht das Token.');

                return 1;
            }

            $io->success('Beide Endpunkte antworten. Das Zugangstoken ist in Ordnung.');

            return 0;
        }

        $io->success('Die Tokendatei ist brauchbar und wird verwendet. Kommt trotzdem „Too much access tokens", liegt die Grenze bei nu.');

        return 0;
    }

    /**
     * Wertet das Tokenprotokoll aus.
     *
     * Beantwortet drei Fragen, die man ohne Aufzeichnung nur raten kann:
     * Wie oft wird überhaupt angefragt? Wie viele **neue Token-Familien**
     * entstehen dabei (jede `client_credentials`-Ausstellung ist eine)? Und
     * wie viele davon gehen auf einen **Wettlauf** zurück — mehrere Vorgänge,
     * die gleichzeitig erneuern, wobei nur der erste das Refresh-Token
     * einlösen kann und die übrigen auf `client_credentials` ausweichen?
     *
     * Genau diese Zahlen braucht ein Gespräch mit dem Betreiber der
     * Schnittstelle über das Kontingent.
     *
     * @param  SymfonyStyle $io        Ausgabe
     * @param  string       $protokoll Pfad der Monatsdatei, darf leer sein
     * @return void
     */
    private function auswertung(SymfonyStyle $io, string $protokoll): void
    {
        $io->section('Auswertung des Tokenprotokolls');

        if ('' === $protokoll || !is_file($protokoll)) {
            $io->text('Noch keine Aufzeichnung vorhanden.');

            return;
        }

        $saetze = [];

        foreach (file($protokoll, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
            $teile = explode(';', $zeile);

            if (\count($teile) < 4 || 'Zeitpunkt' === $teile[0]) {
                continue;
            }

            $saetze[] = ['zeit' => (int) strtotime($teile[0]), 'roh' => $teile[0], 'art' => $teile[1], 'erg' => $teile[2]];
        }

        if (!$saetze) {
            $io->text('Die Aufzeichnung enthält noch keine Anfragen.');

            return;
        }

        $von = $saetze[0];
        $bis = end($saetze);
        $stunden = max(0.01, ($bis['zeit'] - $von['zeit']) / 3600);

        $arten = [];

        foreach ($saetze as $s) {
            $schluessel = $s['art'].' '.$s['erg'];
            $arten[$schluessel] = ($arten[$schluessel] ?? 0) + 1;
        }

        arsort($arten);

        $zeilen = [];

        foreach ($arten as $was => $anzahl) {
            $zeilen[] = [$was, $anzahl];
        }

        $io->text(sprintf('%s bis %s (%.1f Stunden)', $von['roh'], $bis['roh'], $stunden));
        $io->newLine();
        $io->table(['Art und Ergebnis', 'Anzahl'], $zeilen);

        // Wettläufe: mehrere Anfragen in derselben Sekunde. Gröber als eine
        // echte Gleichzeitigkeitsmessung, aber die Aufzeichnung hat nur
        // Sekundenauflösung — und für die Größenordnung reicht es
        $proSekunde = [];

        foreach ($saetze as $s) {
            $proSekunde[$s['roh']][] = $s;
        }

        $wettlaeufe = 0;
        $ausWettlauf = 0;

        foreach ($proSekunde as $gruppe) {
            if (\count($gruppe) < 2) {
                continue;
            }

            ++$wettlaeufe;

            foreach ($gruppe as $s) {
                if ('client_credentials' === $s['art'] && 'ausgestellt' === $s['erg']) {
                    ++$ausWettlauf;
                }
            }
        }

        $familien = 0;

        foreach ($saetze as $s) {
            if ('client_credentials' === $s['art'] && 'ausgestellt' === $s['erg']) {
                ++$familien;
            }
        }

        $io->text(sprintf('Anfragen: <info>%d</info> (%.1f je Stunde, hochgerechnet %d am Tag)', \count($saetze), \count($saetze) / $stunden, (int) round(\count($saetze) / $stunden * 24)));
        $io->text(sprintf('Neue Token-Familien: <info>%d</info>', $familien));
        $io->text(sprintf('Wettläufe (mehrere Anfragen in derselben Sekunde): <info>%d</info>', $wettlaeufe));
        $io->text(sprintf('Familien, die daraus entstanden: <info>%d</info> von %d', $ausWettlauf, $familien));
        $io->newLine();

        if ($ausWettlauf > 0) {
            $io->text('Ohne Wettläufe wären es '.($familien - $ausWettlauf).' Familien gewesen.');
            $io->text('Die Dateisperre ab Fassung 1.30.0 zieht genau diese Vorgänge auf einen zusammen.');
        } else {
            $io->text('Keine Wettläufe — jede Erneuerung lief für sich.');
        }
    }
}
