<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Gesamtpersonen;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Schema;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bildet die Gesamtpersonen des Wertungsarchivs neu (Etappe 2, ab 2.0.0).
 *
 * Nach jedem Import und nach jeder Änderung an einer Kante laufen lassen:
 * Die Gesamtpersonen werden nicht fortgeschrieben, sondern jedes Mal aus den
 * bestätigten Kanten neu gebildet.
 */
class ArchivPersonenCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) nimmt ihn aus dem Attribut command am Tag in
     * services.yml; beide müssen gleich lauten
     * (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:archiv-personen';

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
     * Beschreibt den Befehl für `--help`.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Bildet die Gesamtpersonen des Wertungsarchivs aus den bestätigten Kanten neu')
            ->setHelp(
                "Verbindet die Personen der drei Archive über die bestätigten Kanten der Brücke.\n"
                ."Nach jedem wertungsportal:archiv-import und nach jeder abgelehnten Kante laufen lassen.\n"
            )
        ;
    }

    /**
     * Bildet die Gesamtpersonen und zeigt Zahlen und Prüffälle.
     *
     * @param InputInterface  $input  Eingabe
     * @param OutputInterface $output Ausgabe
     *
     * @return int 0 = gebildet, 2 = nicht eingerichtet oder Schema veraltet
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->verbindung->verfuegbar()) {
            $io->error('WERTUNGSARCHIV_DATABASE_URL fehlt in .env.local — das Wertungsarchiv ist nicht eingerichtet.');

            return 2;
        }

        $db = $this->verbindung->db();

        if (Schema::anweisungen($db)) {
            $io->error('Die Archivdatenbank entspricht nicht dem Schema — zuerst wertungsportal:archiv-schema --force.');

            return 2;
        }

        $start = microtime(true);
        $e = (new Gesamtpersonen($db, static function (string $text) use ($io): void {
            $io->writeln('  '.$text);
        }))->bilden();

        $io->table(array('Wert', 'Zahl'), array(
            array('bestätigte Kanten', $e['kanten']),
            array('Gesamtpersonen', $e['gesamtpersonen']),
            array('W:-Zeilen ohne Kante (keine Gesamtperson)', $e['ohne']),
        ));

        if ($e['pruefen']) {
            $io->table(array('Prüfvermerk', 'Gesamtpersonen'), array_map(null, array_keys($e['pruefen']), array_values($e['pruefen'])));
        }

        $io->writeln('Größte Gruppen: '.implode(', ', array_map(static function ($g) {
            return '#'.$g['id'].' ('.$g['personen'].')';
        }, $e['groesste'])));

        $io->success('Gesamtpersonen gebildet in '.round(microtime(true) - $start).' s.');

        return 0;
    }
}
