<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Helper\OAuth2Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Zeigt den Zustand der Zugangstoken — ohne die Schnittstelle zu belasten.
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
 * Seit 1.46.0 gibt es zwei Kennungen (siehe docs/zugang.md): eine für Turniere
 * und Personen, eine für die DWZ-Liste. Der Befehl zeigt beide getrennt — mit
 * eigener Tokendatei und eigener Zählung, denn das Kontingent gilt je Kennung.
 *
 * Er fragt standardmäßig **nichts** bei der Schnittstelle an und kostet damit
 * auch kein Token.
 */
class TokenCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) liest die Eigenschaft nicht mehr; maßgeblich ist
     * das Attribut command am Tag in services.yml, und beide müssen gleich
     * lauten (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:token';

    /**
     * Überschriften der beiden Zugänge.
     */
    private const ZUGAENGE = [
        OAuth2Client::ZUGANG_TURNIERE => 'Turniere und Personen (/dwz/tournaments, /dwz/persons)',
        OAuth2Client::ZUGANG_DWZLISTE => 'DWZ-Liste (/dwz/dwzliste, Zip-Downloads)',
    ];

    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        parent::__construct();
    }

    /**
     * Beschreibt den Befehl und seine Schalter.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Zeigt, ob die Zugangstoken der Schnittstelle richtig zwischengespeichert werden')
            ->addOption('pruefen', null, InputOption::VALUE_NONE, 'Zusätzlich je Zugang einen echten Abruf machen (kostet ggf. ein Token!)')
            ->addOption('auswertung', null, InputOption::VALUE_NONE, 'Die Tokenprotokolle auswerten: Anfragen, Wettläufe, neue Familien')
            ->setHelp(
                "Ohne Schalter fragt der Befehl NICHTS bei der Schnittstelle an und kostet\n"
                ."damit auch kein Token. Er sieht nur nach, was örtlich hinterlegt ist —\n"
                ."getrennt für die beiden Kennungen: Turniere und Personen, DWZ-Liste.\n\n"
                ."Die entscheidende Zeile ist \"Schreibbar\". Steht dort NEIN, holt sich jeder\n"
                ."Seitenaufruf und jeder Cronlauf ein eigenes Zugangstoken — dann ist das\n"
                ."Kontingent bei nu binnen Stunden erschöpft, und daran ändert auch\n"
                ."Abwarten nichts.\n\n"
                ."Mit --pruefen wird je Zugang ein einzelner Endpunkt abgerufen. Das kostet\n"
                ."im ungünstigen Fall je ein Token und sollte nicht wiederholt werden,\n"
                ."solange das Kontingent klemmt. Es ist der schnellste Weg, frisch\n"
                ."eingetragene Zugangsdaten der DWZ-Liste zu prüfen.\n"
            )
        ;
    }

    /**
     * Gibt den Zustand beider Zugänge aus.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return int 0 alles in Ordnung, 1 eine Tokendatei ist nicht brauchbar
     *             oder ein Probeabruf scheiterte
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $io->title('Wertungsportal: Zugangstoken');

        $brauchbar = true;

        foreach (self::ZUGAENGE as $zugang => $titel) {
            $io->section($titel);

            if (!$this->zustand($io, $zugang)) {
                $brauchbar = false;
            }
        }

        if (!$brauchbar) {
            return 1;
        }

        if ($input->getOption('auswertung')) {
            foreach (self::ZUGAENGE as $zugang => $titel) {
                $this->auswertung($io, OAuth2Client::tokenprotokoll($zugang), $titel);
            }
        }

        if ($input->getOption('pruefen')) {
            return $this->pruefen($io);
        }

        $io->success('Die Tokendateien sind brauchbar und werden verwendet. Kommt trotzdem „Too much access tokens", liegt die Grenze bei nu.');

        return 0;
    }

    /**
     * Gibt die Tabelle eines Zugangs aus: Zugangsdaten, Tokendatei, Token und
     * die Tokenanfragen des laufenden Tages.
     *
     * Für die DWZ-Liste gibt es zwei Sonderfälle ohne eigene Tokendatei: keine
     * Zugangsdaten (Abruf ohne Anmeldung, wie bis 1.45.1) und dieselbe
     * Client-ID wie beim Turnierzugang (gemeinsames Token).
     *
     * @param  SymfonyStyle $io     Ausgabe
     * @param  string       $zugang OAuth2Client::ZUGANG_…
     * @return bool false, wenn die Tokendatei nicht geschrieben werden kann
     */
    private function zustand(SymfonyStyle $io, string $zugang): bool
    {
        $felder = OAuth2Client::EINSTELLUNGEN[$zugang];
        $zeilen = [];

        if (OAuth2Client::ZUGANG_TURNIERE === $zugang) {
            $zeilen[] = ['Basisadresse', (string) ($GLOBALS['TL_CONFIG']['wertungsportal_apiBasisURL'] ?? '(nicht gepflegt)')];
        }

        if (OAuth2Client::ZUGANG_DWZLISTE === $zugang) {
            if (!OAuth2Client::eingerichtet($zugang)) {
                $io->table(['Angabe', 'Wert'], [
                    ['Zugangsdaten', '<fg=yellow>nicht eingetragen</>'],
                    ['Folge', 'Die DWZ-Liste wird ohne Anmeldung abgerufen — nur so lange möglich, wie nu sie frei ausliefert.'],
                ]);

                return true;
            }

            if (OAuth2Client::ZUGANG_TURNIERE === OAuth2Client::zugangFuer('/dwz/dwzliste/clubs')) {
                $io->table(['Angabe', 'Wert'], [
                    ['Zugangsdaten', 'dieselbe Client-ID wie bei Turnieren und Personen'],
                    ['Folge', 'Ein gemeinsames Token — Tokendatei und Zählung siehe oben.'],
                ]);

                return true;
            }
        }

        $scope = trim((string) ($GLOBALS['TL_CONFIG'][$felder['scope']] ?? ''));

        $datei = OAuth2Client::tokendatei($zugang);
        $verzeichnis = \dirname($datei);
        $vorhanden = is_file($datei);

        // Schreibbarkeit an der Datei selbst, sonst am Verzeichnis — eine noch
        // nicht angelegte Datei ist kein Mangel
        $schreibbar = $vorhanden ? is_writable($datei) : is_writable($verzeichnis);

        // Angefordert wird, was der Client tatsächlich schickt — bei der
        // DWZ-Liste also die Vorgabe `dwz_liste`, wenn nichts eingetragen ist
        $angefordert = (new OAuth2Client($zugang))->scope;
        $zeilen[] = ['Scope', '' !== $angefordert ? $angefordert.('' === $scope ? ' (Vorgabe, nichts eingetragen)' : '') : '(keiner — nu nimmt den der Kennung zugedachten)'];
        $zeilen[] = ['Zugangsdaten vollständig', OAuth2Client::eingerichtet($zugang) ? 'ja' : '<fg=red>NEIN</>'];
        $zeilen[] = ['Tokendatei', $datei];
        $zeilen[] = ['Vorhanden', $vorhanden ? 'ja' : 'nein (wird beim nächsten Abruf angelegt)'];
        $zeilen[] = ['<options=bold>Schreibbar</>', $schreibbar ? '<info>ja</info>' : '<fg=red>NEIN</>'];

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
        $protokoll = OAuth2Client::tokenprotokoll($zugang);

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

            return false;
        }

        return true;
    }

    /**
     * Ruft je Zugang einen Endpunkt ab und bewertet das Ergebnis.
     *
     * Die DWZ-Liste läuft dabei genau so, wie das Frontend sie abruft: mit
     * ihrem Token, mit dem gemeinsamen Token oder ohne Anmeldung — je nachdem,
     * was eingetragen ist. Ein 401 dort heißt deshalb je nach Lage „Zugangsdaten
     * fehlen" oder „Zugangsdaten stimmen nicht".
     *
     * @param  SymfonyStyle $io Ausgabe
     * @return int 0 beide antworten mit 200, sonst 1
     */
    private function pruefen(SymfonyStyle $io): int
    {
        $io->section('Abruf zur Probe');
        $io->text('Je ein Aufruf: DWZ-Liste und Turniere.');
        $io->newLine();

        $client = new OAuth2Client();
        $liste = $client->apiBaseUrl.'/dwz/dwzliste/persons?lastname=Muster&firstname=Max';
        $turniere = $client->apiBaseUrl.'/dwz/tournaments?searchString=x&fromDate=2026-01-01&toDate=2026-01-02';

        $weg = [
            OAuth2Client::ZUGANG_DWZLISTE => 'mit dem Token der DWZ-Liste',
            OAuth2Client::ZUGANG_TURNIERE => 'mit dem Token von Turnieren und Personen',
        ];
        $ergebnis = [];

        $ohneToken = '';

        foreach (['DWZ-Liste' => $liste, 'Turniere' => $turniere] as $was => $url) {
            $zugang = OAuth2Client::zugangFuer($url);
            $r = $client->callApiWithRefresh($url);
            $code = (int) ($r['http_code'] ?? 0);
            $ergebnis[$was] = $code;
            $wie = null === $zugang ? 'ohne Anmeldung' : $weg[$zugang];

            // Übergangsregel: Die DWZ-Liste kam trotz Zugangsdaten ohne Token
            if (!empty($r['ohne_anmeldung'])) {
                $ohneToken = (string) $r['ohne_anmeldung'];
                $wie = '<fg=yellow>OHNE Anmeldung — kein Token zu bekommen</>';
            }

            if (200 === $code) {
                $io->writeln(sprintf('  <info>HTTP 200</info>  %s (%s)', $was, $wie));
            } else {
                $io->writeln(sprintf('  <fg=red>HTTP %d</>  %s (%s)', $code, $was, $wie));
                $io->writeln('            '.trim((string) ($r['error_message'] ?? '(ohne Meldung)')));
            }

            if ('' !== $ohneToken && 'DWZ-Liste' === $was) {
                $io->writeln('            '.$ohneToken);
            }
        }

        $io->newLine();

        if ('' !== $ohneToken) {
            $io->warning(
                'Die Zugangsdaten der DWZ-Liste funktionieren nicht — die Antwort kam nur, weil nu die Liste noch '
                .'ohne Anmeldung ausliefert. Sobald nu umstellt, gehen die Besucher in den Notbetrieb. '
                .'Zugangsdaten und Scope prüfen (Wertungsportal → Einstellungen → Zugang zur DWZ-Liste). '
                .'Vor dem nächsten Versuch die Wartezeit von fünf Minuten abwarten.'
            );

            return 1;
        }

        if (200 === $ergebnis['DWZ-Liste'] && 200 === $ergebnis['Turniere']) {
            if (!OAuth2Client::eingerichtet(OAuth2Client::ZUGANG_DWZLISTE)) {
                $io->note('Die DWZ-Liste lief ohne Anmeldung, weil für sie keine Zugangsdaten eingetragen sind. Sobald nu die Anmeldung verlangt, gehören sie unter Wertungsportal → Einstellungen → Zugang zur DWZ-Liste.');
            }

            $io->success('Beide Zugänge antworten. Die Zugangstoken sind in Ordnung.');

            return 0;
        }

        if (0 === $ergebnis['DWZ-Liste'] && 0 === $ergebnis['Turniere']) {
            $io->error('Keiner der beiden Abrufe hat eine Antwort bekommen — dann steht die Verbindung selbst in Frage, nicht ein Token.');

            return 1;
        }

        if (200 !== $ergebnis['DWZ-Liste']) {
            $io->warning(OAuth2Client::eingerichtet(OAuth2Client::ZUGANG_DWZLISTE)
                ? 'Die DWZ-Liste antwortet nicht mit 200. Zugangsdaten und Scope der DWZ-Liste prüfen (Wertungsportal → Einstellungen → Zugang zur DWZ-Liste); die Meldung oben nennt den Grund.'
                : 'Die DWZ-Liste antwortet nicht mit 200, und für sie sind keine Zugangsdaten eingetragen. Verlangt nu inzwischen eine Anmeldung, gehören sie unter Wertungsportal → Einstellungen → Zugang zur DWZ-Liste.');
        }

        if (200 !== $ergebnis['Turniere']) {
            $io->warning(
                'Turniere und Personen antworten nicht mit 200. Liegt es am Token, ist die Grenze bei nu '
                .'anzusprechen (Kennung, Kontingent, Scope) — an der Tokendatei dieser Anlage liegt es nicht.'
            );
        }

        return 1;
    }

    /**
     * Wertet ein Tokenprotokoll aus.
     *
     * Beantwortet drei Fragen, die man ohne Aufzeichnung nur raten kann:
     * Wie oft wird überhaupt angefragt? Wie viele **neue Token-Familien**
     * entstehen dabei (jede `client_credentials`-Ausstellung ist eine)? Und
     * wie viele davon gehen auf einen **Wettlauf** zurück — mehrere Vorgänge,
     * die gleichzeitig erneuern, wobei nur der erste das Refresh-Token
     * einlösen kann und die übrigen auf `client_credentials` ausweichen?
     *
     * Genau diese Zahlen braucht ein Gespräch mit dem Betreiber der
     * Schnittstelle über das Kontingent — je Kennung, deshalb je Zugang ein
     * eigenes Protokoll.
     *
     * @param  SymfonyStyle $io        Ausgabe
     * @param  string       $protokoll Pfad der Monatsdatei, darf leer sein
     * @param  string       $titel     Überschrift des Zugangs
     * @return void
     */
    private function auswertung(SymfonyStyle $io, string $protokoll, string $titel): void
    {
        $io->section('Auswertung des Tokenprotokolls — '.$titel);

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
