<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Classes\SwissChess;
use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Erzeugt die Hintergrunddateien für Swiss-Chess aus der Deutschland-Datei
 * des Wertungsportals und legt sie im Jahresarchiv ab.
 *
 * Es entstehen zwei Archive, aufgeteilt wie die CSV- und DOS-Pakete daneben:
 *
 *     files/wertungsportal/downloads/JJJJ/swiss10/dsb-swiss10_JJJJMMTT.zip
 *     files/wertungsportal/downloads/JJJJ/swiss/dsb-swiss_JJJJMMTT.zip
 *
 * Das erste ist für **Swiss-Chess ab 10.0** (alle Felder im Klartext,
 * Spielerkennung ist die nuLiga-ID), das zweite für **ältere Fassungen**
 * (Zahlenfelder binär kodiert). Jedes Archiv enthält das Dateipaar LST und
 * SWX.
 *
 * Ohne Angabe wird die aktuelle `export/csv/LV-0-csv.zip` benutzt, die
 * `wertungsportal:converter` erzeugt hat. Mit `--quelle` läßt sich statt
 * dessen ein bereits entpacktes Verzeichnis angeben — praktisch, um eine
 * ältere Fassung nachzubauen.
 */
class SwissChessCommand extends Command
{
    protected static $defaultName = 'wertungsportal:swisschess';

    /**
     * Die beiden Fassungen: Fassungskennung => [Ordner, Namensteil des
     * Archivs, Zusatz am Namen der LST].
     *
     * Der Zusatz „a" beim alten Format sorgt dafür, daß die beiden Dateipaare
     * sich nicht überschreiben, wenn jemand beide Archive in denselben Ordner
     * entpackt.
     */
    private const FASSUNGEN = [
        SwissChess::FASSUNG_NEU => ['swiss10', 'dsb-swiss10', '', 'Swiss-Chess ab 10.0'],
        SwissChess::FASSUNG_ALT => ['swiss', 'dsb-swiss', 'a', 'Ältere Swiss-Chess-Fassungen'],
    ];

    /**
     * @var ContaoFramework Wird gebraucht, damit die Legacy-Klassen für
     *                      Pfadermittlung und Dateiverwaltung bereitstehen
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
     * Beschreibt den Befehl und seine Schalter.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Erzeugt die Swiss-Chess-Hintergrunddateien aus der DWZ-Liste')
            ->addOption('quelle', null, InputOption::VALUE_REQUIRED, 'Verzeichnis mit entpackter spieler.csv und vereine.csv')
            ->addOption('ziel', null, InputOption::VALUE_REQUIRED, 'Archivverzeichnis (Vorgabe: files/wertungsportal/downloads/JJJJ)')
            ->setHelp(
                "Legt zwei Archive im Jahresarchiv ab:\n".
                "  JJJJ/swiss10/dsb-swiss10_JJJJMMTT.zip   Swiss-Chess ab 10.0\n".
                "  JJJJ/swiss/dsb-swiss_JJJJMMTT.zip       ältere Fassungen\n\n".
                "Jedes Archiv enthält das Dateipaar LST und SWX.\n\n".
                "Ohne --quelle wird export/csv/LV-0-csv.zip entpackt; die Datei\n".
                "erzeugt der Befehl wertungsportal:converter.\n\n".
                "Enthalten sind nur die DSB-Mitglieder. Die Originaldateien des DSB\n".
                "führen zusätzlich alle weltweit von der FIDE erfaßten Spieler sowie\n".
                "Schnell- und Blitzwertungen; beides steht nicht in der LV-0-csv."
            );
    }

    /**
     * Erzeugt beide Archive.
     *
     * @param InputInterface  $input  Schalter --quelle und --ziel
     * @param OutputInterface $output Ziel für Fortschritt und Ergebnis
     *
     * @return int 0 bei Erfolg, 1 wenn die Quelldateien fehlen oder die
     *             Kopfzeile der CSV nicht paßt
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        $wurzel = Helper::projektpfad();
        $quelle = (string) ($input->getOption('quelle') ?? '');
        $aufraeumen = [];

        try {
            if ($quelle === '') {
                $quelle = $this->entpacke($wurzel, $output);
                $aufraeumen[] = $quelle;
            }

            // Das Datum der CSV, nicht das heutige: Die Datei kann älter sein
            $zeit = (int) filemtime($quelle.'/spieler.csv');

            $archiv = (string) ($input->getOption('ziel') ?? '');
            if ($archiv === '') {
                $archiv = $wurzel.'/files/wertungsportal/downloads/'.date('Y', $zeit);
            }

            // Die Dateipaare entstehen in einem Arbeitsverzeichnis und wandern
            // erst gepackt ins Archiv
            $arbeit = $wurzel.'/system/tmp/wp-swisschess-bau-'.getmypid();
            $aufraeumen[] = $arbeit;

            foreach (self::FASSUNGEN as $fassung => [$ordner, $praefix, $zusatz, $ueberschrift]) {
                $output->writeln('');
                $output->writeln('<info>'.$ueberschrift.'</info>');

                $erzeuger = new SwissChess();
                $erg = $erzeuger->erzeuge(
                    $quelle,
                    $arbeit.'/'.$ordner,
                    $fassung,
                    'fdsb'.date('ymd', $zeit).$zusatz,
                    static function (string $t) use ($output): void { $output->writeln('  '.$t); }
                );

                $output->writeln(sprintf('  %d Datensätze, %d übersprungen', $erg['saetze'], $erg['uebersprungen']));

                $ziel = $this->packe(
                    [$erg['lst'], $erg['swx']],
                    $archiv.'/'.$ordner,
                    $praefix.'_'.date('Ymd', $zeit).'.zip',
                    $wurzel
                );

                $output->writeln(sprintf('  <comment>%s</comment> (%s)', $ziel, $this->groesse($ziel)));
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return 1;
        } finally {
            foreach ($aufraeumen as $verzeichnis) {
                $this->raeumeAuf($verzeichnis);
            }
        }

        $output->writeln('');
        $output->writeln('<info>Fertig.</info>');

        return 0;
    }

    /**
     * Packt die erzeugten Dateien ins Archiv und trägt sie in die
     * Dateiverwaltung ein.
     *
     * @param array  $dateien Vollständige Pfade der zu packenden Dateien
     * @param string $ordner  Zielordner, wird bei Bedarf angelegt
     * @param string $name    Dateiname des Archivs
     * @param string $wurzel  Wurzelverzeichnis der Installation, für die Dbafs
     *
     * @return string Vollständiger Pfad des Archivs
     *
     * @throws \RuntimeException wenn sich das Archiv nicht anlegen läßt
     */
    protected function packe(array $dateien, string $ordner, string $name, string $wurzel): string
    {
        if (!is_dir($ordner) && !@mkdir($ordner, 0777, true)) {
            throw new \RuntimeException('Zielverzeichnis nicht anlegbar: '.$ordner);
        }

        $ziel = $ordner.'/'.$name;
        $zip = new \ZipArchive();

        if ($zip->open($ziel, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Archiv nicht anlegbar: '.$ziel);
        }

        foreach ($dateien as $datei) {
            $zip->addFile((string) realpath($datei), basename($datei));
        }

        $zip->close();

        // Eintrag in die Dateiverwaltung, damit das Archiv im Backend und in
        // den Downloadlisten auftaucht — dasselbe macht der Converter mit
        // seinen CSV-Paketen
        $relativ = ltrim(str_replace(str_replace('\\', '/', $wurzel), '', str_replace('\\', '/', $ziel)), '/');

        try {
            if (\Contao\FilesModel::findByPath($relativ) === null) {
                \Contao\Dbafs::addResource($relativ);
            }
            \Contao\Dbafs::updateFolderHashes(\dirname($relativ));
        } catch (\Throwable $e) {
            // Ohne Dateiverwaltung ist das Archiv trotzdem brauchbar — etwa
            // wenn der Befehl mit --ziel außerhalb von files/ geschrieben hat
        }

        return $ziel;
    }

    /**
     * Entpackt die aktuelle LV-0-csv.zip in ein Arbeitsverzeichnis.
     *
     * @param string          $wurzel Wurzelverzeichnis der Installation
     * @param OutputInterface $output Für die Fortschrittsmeldung
     *
     * @return string Pfad des Arbeitsverzeichnisses
     *
     * @throws \RuntimeException wenn das Archiv fehlt oder sich nicht öffnen läßt
     */
    protected function entpacke(string $wurzel, OutputInterface $output): string
    {
        $archiv = $wurzel.'/files/wertungsportal/downloads/export/csv/LV-0-csv.zip';

        if (!is_file($archiv)) {
            throw new \RuntimeException(
                'Das Archiv '.$archiv." fehlt.\n".
                'Erst wertungsportal:converter laufen lassen, oder --quelle angeben.'
            );
        }

        $arbeit = $wurzel.'/system/tmp/wp-swisschess-'.getmypid();

        if (!is_dir($arbeit) && !@mkdir($arbeit, 0777, true)) {
            throw new \RuntimeException('Arbeitsverzeichnis nicht anlegbar: '.$arbeit);
        }

        $zip = new \ZipArchive();

        if ($zip->open($archiv) !== true) {
            throw new \RuntimeException('Archiv nicht lesbar: '.$archiv);
        }

        $zip->extractTo($arbeit);
        $zip->close();

        $output->writeln('Entpackt: '.basename($archiv));

        return $arbeit;
    }

    /**
     * Löscht ein Arbeitsverzeichnis samt Inhalt, auch mit Unterordnern.
     *
     * @param string $verzeichnis Pfad
     *
     * @return void
     */
    protected function raeumeAuf(string $verzeichnis): void
    {
        if (!is_dir($verzeichnis)) {
            return;
        }

        foreach ((array) glob($verzeichnis.'/*') as $eintrag) {
            is_dir($eintrag) ? $this->raeumeAuf($eintrag) : @unlink($eintrag);
        }

        @rmdir($verzeichnis);
    }

    /**
     * Formatiert eine Dateigröße lesbar.
     *
     * @param string $pfad Dateiname
     *
     * @return string Etwa „4,2 MB"
     */
    protected function groesse(string $pfad): string
    {
        $bytes = (int) @filesize($pfad);

        if ($bytes > 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return number_format($bytes / 1024, 1, ',', '.').' kB';
    }
}
