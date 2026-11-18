<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Schema;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Legt die Tabellen der Archivdatenbank an oder ergänzt sie (ab 2.0.0).
 *
 * Gegenstück zu `contao:migrate` für die zweite Datenbank: vergleicht das
 * Soll aus Wertungsarchiv\Schema mit der Datenbank und zeigt bzw. führt die
 * Änderungen aus. Löschende Anweisungen gibt es nicht.
 */
class ArchivSchemaCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) nimmt ihn aus dem Attribut command am Tag in
     * services.yml; beide müssen gleich lauten
     * (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:archiv-schema';

    /**
     * @var Verbindung
     */
    private $verbindung;

    /**
     * Nimmt die Verbindung zur Archivdatenbank entgegen.
     *
     * @param Verbindung $verbindung Archivdatenbank
     */
    public function __construct(Verbindung $verbindung)
    {
        $this->verbindung = $verbindung;

        parent::__construct();
    }

    /**
     * Beschreibt den Befehl und den Schalter --force für `--help`.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Legt die Tabellen des Wertungsarchivs in seiner eigenen Datenbank an oder ergänzt sie')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Änderungen ausführen (ohne: nur anzeigen)')
            ->setHelp(
                "Die Archivdatenbank steht in .env.local unter WERTUNGSARCHIV_DATABASE_URL.\n"
                ."Ohne --force werden die nötigen SQL-Anweisungen nur gezeigt.\n"
                ."Es wird nie etwas gelöscht.\n"
            )
        ;
    }

    /**
     * Zeigt oder führt die Anweisungen aus.
     *
     * @param InputInterface  $input  Eingabe
     * @param OutputInterface $output Ausgabe
     *
     * @return int 0 = Datenbank entspricht dem Soll (bzw. Anweisungen gezeigt),
     *             2 = Archiv nicht eingerichtet oder Fehler
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->verbindung->verfuegbar()) {
            $io->error('WERTUNGSARCHIV_DATABASE_URL fehlt in .env.local — das Wertungsarchiv ist nicht eingerichtet.');

            return 2;
        }

        $db = $this->verbindung->db();
        $sql = Schema::anweisungen($db);

        if (!$sql) {
            $io->success('Die Archivdatenbank entspricht dem Schema (Fassung '.Schema::FASSUNG.').');

            return 0;
        }

        $io->listing($sql);

        if (!$input->getOption('force')) {
            $io->note(\count($sql).' Anweisung(en). Mit --force ausführen.');

            return 0;
        }

        foreach ($sql as $anweisung) {
            $db->executeStatement($anweisung);
        }

        $db->executeStatement('REPLACE INTO archiv_info (schluessel, wert) VALUES (?, ?)', array('schema', (string) Schema::FASSUNG));
        $io->success(\count($sql).' Anweisung(en) ausgeführt.');

        return 0;
    }
}
