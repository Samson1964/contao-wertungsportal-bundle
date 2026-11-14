<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Schachbulle\ContaoWertungsportalBundle\Helper\Altcodes;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Übernimmt die alten Turniercodes aus DeWIS in tl_wertungsportal_altcodes
 * (ab 1.58.0).
 *
 * Quelle ist der Export tcode_uui.csv aus portal64 (Spalten ID, tname, tcode,
 * organisation, vkz, acron, finishedOn, uuid, computedOn, recomputedOn,
 * rounds; latin-1 oder UTF-8). Der Befehl darf beliebig oft laufen: Er legt
 * Neues an und ändert nur, was sich unterscheidet.
 *
 * Warum ein Befehl und kein Formular im Backend? Es ist ein einmaliger
 * Vorgang mit einer Datei von 11 MB; der Befehl braucht keinen Upload in
 * Häppchen und meldet am Ende genau, was geschah.
 */
class TurniercodesCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) nimmt ihn aus dem Attribut command am Tag in
     * services.yml; beide müssen gleich lauten
     * (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:turniercodes';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * Nimmt das Contao-Framework entgegen; ohne dessen Start gibt es keine
     * Datenbank.
     *
     * @param ContaoFramework $framework Contao-Framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        parent::__construct();
    }

    /**
     * Beschreibt den Befehl, sein Argument und den Probelauf für `--help`.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Übernimmt die alten Turniercodes aus DeWIS (tcode ↔ UUID) für Weiterleitung und „Erste Berechnung am"')
            ->addArgument('datei', InputArgument::REQUIRED, 'Pfad zum Export tcode_uui.csv')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur zählen, was neu wäre oder sich änderte — nichts schreiben')
            ->setHelp(
                "Liest den Export aus portal64 und gleicht ihn über die UUID mit der Tabelle\n"
                ."tl_wertungsportal_altcodes ab (Backend: Wertungsportal → Alte Turniercodes).\n"
                ."Gelöscht wird nichts; von Hand berichtigte Zeilen überschreibt ein\n"
                ."erneuter Lauf mit dem Wert aus der Datei.\n\n"
                ."Beispiele:\n"
                ."  wertungsportal:turniercodes files/tcode_uui.csv --dry-run\n"
                ."  wertungsportal:turniercodes files/tcode_uui.csv\n"
            )
        ;
    }

    /**
     * Liest die Datei, gleicht ab und zeigt das Ergebnis.
     *
     * @param InputInterface  $input  Eingabe mit Datei und Schaltern
     * @param OutputInterface $output Ausgabe
     *
     * @return int 0 = abgeglichen (oder Probelauf gerechnet), 2 = Datei
     *             unbrauchbar oder Tabelle fehlt (contao:migrate nicht gelaufen)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        if (!\Contao\Database::getInstance()->tableExists('tl_wertungsportal_altcodes')) {
            $io->error('Die Tabelle tl_wertungsportal_altcodes fehlt — zuerst contao:migrate ausführen.');

            return 2;
        }

        $trocken = (bool) $input->getOption('dry-run');

        try {
            $e = Altcodes::importiere(Altcodes::leseDatei((string) $input->getArgument('datei')), $trocken);
        } catch (\RuntimeException $fehler) {
            $io->error($fehler->getMessage());

            return 2;
        }

        $io->table(
            array('neu', 'geändert', 'unverändert', 'ohne gültige UUID', 'UUID doppelt in der Datei'),
            array(array($e['neu'], $e['geaendert'], $e['gleich'], $e['ohneUuid'], $e['doppelteUuid']))
        );

        $mehrfach = (int) \Contao\Database::getInstance()
            ->query("SELECT COUNT(*) AS n FROM (SELECT tcode FROM tl_wertungsportal_altcodes WHERE tcode != '' GROUP BY tcode HAVING COUNT(*) > 1) x")
            ->n;

        $io->success(($trocken ? 'Probelauf, nichts geschrieben. ' : 'Übernommen. ').'Mehrfach vergebene Codes im Bestand: '.$mehrfach.' (dort bietet die Weiterleitung eine Auswahl an).');

        return 0;
    }
}
