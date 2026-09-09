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
 * Von beiden wandert eine Kopie unter festem Namen ins Exportverzeichnis:
 *
 *     files/wertungsportal/downloads/export/swiss10/dsb-swiss10.zip
 *     files/wertungsportal/downloads/export/swiss/dsb-swiss.zip
 *
 * Das ist dieselbe Aufteilung, die der Converter für `export/csv/` und
 * `export/dos/` benutzt: Der Downloadlink auf der Website darf sich nicht
 * jeden Monat ändern.
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
                "Jedes Archiv enthält das Dateipaar LST und SWX. Eine Kopie unter\n".
                "festem Namen liegt zusätzlich in export/swiss10/ und export/swiss/.\n\n".
                "Ohne --quelle wird export/csv/LV-0-csv.zip entpackt; die Datei\n".
                "erzeugt der Befehl wertungsportal:converter.\n\n".
                "Die Sätze der DSB-Mitglieder stammen aus dieser CSV, ihre FIDE-Angaben\n".
                "(Schnell- und Blitzwertung, Partien, Titel) aus tl_wertungsportal_elo.\n".
                "Aus derselben Tabelle kommen alle weltweit von der FIDE geführten\n".
                "Spieler als zusätzliche Sätze — ohne sie hätte die Datei statt knapp\n".
                "zwei Millionen nur rund hunderttausend Einträge.\n\n".
                "Ist die Tabelle leer (XML-Import noch nie gelaufen), entstehen die\n".
                "Dateien trotzdem, dann eben nur mit den DSB-Mitgliedern."
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

                $output->writeln(sprintf(
                    '  %d Datensätze (%d DSB, %d FIDE), %d übersprungen',
                    $erg['saetze'], $erg['dsb'], $erg['fide'], $erg['uebersprungen']
                ));

                $ziel = $this->packe(
                    [$erg['lst'], $erg['swx']],
                    $archiv.'/'.$ordner,
                    $praefix.'_'.date('Ymd', $zeit).'.zip',
                    $wurzel
                );

                $output->writeln(sprintf('  <comment>%s</comment> (%s)', $ziel, $this->groesse($ziel)));

                $kopie = $this->exportiere($ziel, $wurzel, $ordner, $praefix.'.zip');

                if ($kopie !== '') {
                    $output->writeln(sprintf('  <comment>%s</comment>', $kopie));
                }
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

        $this->dbafs($ziel, $wurzel);

        return $ziel;
    }

    /**
     * Legt eine Kopie des Archivs unter festem Namen im Exportverzeichnis ab.
     *
     * Der Downloadlink auf der Website zeigt auf diese Kopie: Er soll gleich
     * bleiben, während das datierte Archiv daneben die Historie führt. Genau
     * so hält es der Converter mit `export/csv/` und `export/dos/`.
     *
     * Das Exportverzeichnis liegt neben dem Jahresarchiv, wird also aus dem
     * übergebenen Archivpfad abgeleitet. Wer den Befehl mit `--ziel` in ein
     * Prüfverzeichnis lenkt, bekommt die Kopie folglich auch dort und nicht
     * im echten Downloadbereich.
     *
     * @param string $archivdatei Vollständiger Pfad des datierten Archivs
     * @param string $wurzel      Wurzelverzeichnis der Installation
     * @param string $ordner      Unterordner im Export, `swiss` oder `swiss10`
     * @param string $name        Fester Dateiname der Kopie
     *
     * @return string Pfad der Kopie, oder leer wenn sie nicht angelegt werden
     *                konnte — der Lauf gilt deswegen nicht als gescheitert,
     *                das datierte Archiv steht ja
     */
    protected function exportiere(string $archivdatei, string $wurzel, string $ordner, string $name): string
    {
        // Das Jahresarchiv liegt in downloads/JJJJ/<ordner>, der Export in
        // downloads/export/<ordner> — also zwei Ebenen hoch und wieder runter
        $export = \dirname($archivdatei, 3).'/export/'.$ordner;

        if (!is_dir($export) && !@mkdir($export, 0777, true)) {
            return '';
        }

        $ziel = $export.'/'.$name;

        if (!@copy($archivdatei, $ziel)) {
            return '';
        }

        $this->dbafs($ziel, $wurzel);

        return $ziel;
    }

    /**
     * Trägt eine Datei in die Dateiverwaltung ein oder frischt ihren Eintrag
     * auf.
     *
     * Beim Ersetzen einer gleichnamigen Datei genügt `addResource()` nicht —
     * der Eintrag besteht ja schon, aber seine Prüfsumme zeigt auf den alten
     * Inhalt. Das betrifft die Exportkopien, die jeden Monat überschrieben
     * werden.
     *
     * @param string $datei  Vollständiger Pfad der Datei
     * @param string $wurzel Wurzelverzeichnis der Installation
     *
     * @return void
     */
    protected function dbafs(string $datei, string $wurzel): void
    {
        $relativ = ltrim(str_replace(str_replace('\\', '/', $wurzel), '', str_replace('\\', '/', $datei)), '/');

        try {
            $objDatei = \Contao\FilesModel::findByPath($relativ);

            if ($objDatei === null) {
                \Contao\Dbafs::addResource($relativ);
            } else {
                $objDatei->tstamp = time();
                $objDatei->hash = (string) md5_file($datei);
                $objDatei->save();
            }

            \Contao\Dbafs::updateFolderHashes(\dirname($relativ));
        } catch (\Throwable $e) {
            // Ohne Dateiverwaltung ist das Archiv trotzdem brauchbar — etwa
            // wenn der Befehl mit --ziel außerhalb von files/ geschrieben hat
        }
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
