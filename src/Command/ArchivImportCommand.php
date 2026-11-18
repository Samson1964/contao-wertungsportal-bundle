<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Importeur;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Liste;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Schema;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Übernimmt ein Archiv der Übergabe in die Archivdatenbank (ab 2.0.0).
 *
 * Reihenfolge: Turniere, Personen (mit Kanten), Kartei — die Kartei braucht
 * die IDs der beiden anderen. Vorher werden Prüfergebnis und SHA-256 jeder
 * Liste gegen `uebergabe.json` geprüft; eine unvollständig übertragene
 * Datei soll nicht halb im Archiv landen.
 */
class ArchivImportCommand extends Command
{
    /**
     * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
     * Symfony 7 (Contao 5) nimmt ihn aus dem Attribut command am Tag in
     * services.yml; beide müssen gleich lauten
     * (tests/Contao5/KonsolenbefehleTest.php).
     */
    protected static $defaultName = 'wertungsportal:archiv-import';

    /**
     * Listen in der Reihenfolge der Übernahme.
     */
    private const TEILE = array('turniere', 'personen', 'kartei');

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
     * Beschreibt Argumente und Schalter für `--help`.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Übernimmt ein Archiv (elobase, dewis, wp) aus der Übergabe in die Archivdatenbank')
            ->addArgument('archiv', InputArgument::REQUIRED, 'elobase, dewis oder wp')
            ->addArgument('ordner', InputArgument::OPTIONAL, 'Ordner der Übergabe dieses Archivs (Standard: WERTUNGSARCHIV_PFAD/<archiv>)')
            ->addOption('nur', null, InputOption::VALUE_REQUIRED, 'Nur diese Teile, durch Kommas getrennt: turniere,personen,kartei')
            ->addOption('ohne-pruefsumme', null, InputOption::VALUE_NONE, 'SHA-256 nicht prüfen (spart bei 1 GB Listen einige Sekunden)')
            ->setHelp(
                "Reihenfolge der Archive: elobase, dewis, wp — die Kanten zeigen vom jüngeren\n"
                ."aufs ältere Archiv, die Personen müssen dafür nicht schon da sein.\n"
                ."Wiederholbar: Turniere und Personen werden aktualisiert, die Kartei des\n"
                ."Archivs ersetzt. Vorher wertungsportal:archiv-schema --force.\n\n"
                ."Monatlicher Abzug des Wertungsportals: neue Übergabe nach\n"
                ."WERTUNGSARCHIV_PFAD/wp, dann archiv-import wp und archiv-personen. Kanten,\n"
                ."die der neue Abzug nicht mehr führt, werden „entfallen“ (nicht gelöscht).\n\n"
                ."Beispiele:\n"
                ."  wertungsportal:archiv-import elobase\n"
                ."  wertungsportal:archiv-import wp /pfad/zur/uebergabe/wp --nur=kartei\n"
            )
        ;
    }

    /**
     * Prüft die Listen und übernimmt die gewählten Teile.
     *
     * @param InputInterface  $input  Eingabe
     * @param OutputInterface $output Ausgabe
     *
     * @return int 0 = übernommen, 2 = nicht eingerichtet, Prüfung gescheitert oder Fehler
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $archiv = (string) $input->getArgument('archiv');

        if (!\in_array($archiv, Importeur::ARCHIVE, true)) {
            $io->error('Unbekanntes Archiv „'.$archiv.'" — erlaubt: '.implode(', ', Importeur::ARCHIVE).'.');

            return 2;
        }

        if (!$this->verbindung->verfuegbar()) {
            $io->error('WERTUNGSARCHIV_DATABASE_URL fehlt in .env.local — das Wertungsarchiv ist nicht eingerichtet.');

            return 2;
        }

        $ordner = rtrim(str_replace('\\', '/', (string) ($input->getArgument('ordner') ?: $this->verbindung->pfad().'/'.$archiv)), '/');
        $teile = $input->getOption('nur') ? array_values(array_intersect(self::TEILE, array_map('trim', explode(',', (string) $input->getOption('nur'))))) : self::TEILE;
        $db = $this->verbindung->db();

        if (Schema::anweisungen($db)) {
            $io->error('Die Archivdatenbank entspricht nicht dem Schema — zuerst wertungsportal:archiv-schema --force.');

            return 2;
        }

        // Prüfung der Aufbereitung und Prüfsummen
        $uebergabe = json_decode((string) @file_get_contents($ordner.'/uebergabe.json'), true);

        if (!\is_array($uebergabe) || $archiv !== ($uebergabe['archiv'] ?? '')) {
            $io->error('In '.$ordner.' liegt keine uebergabe.json für „'.$archiv.'".');

            return 2;
        }

        if (!empty($uebergabe['fehler'])) {
            $io->error('Die Aufbereitung meldet Fehler (uebergabe.json) — so nicht übernehmen.');

            return 2;
        }

        foreach ($teile as $teil) {
            if ($archiv !== (Liste::kopf($ordner.'/'.$teil.'.jsonl')['archiv'] ?? '')) {
                $io->error($teil.'.jsonl gehört nicht zum Archiv „'.$archiv.'".');

                return 2;
            }

            if (!$input->getOption('ohne-pruefsumme') && '' !== ($fehler = Liste::pruefe($ordner, $teil.'.jsonl'))) {
                $io->error($fehler);

                return 2;
            }
        }

        $importeur = new Importeur($db, static function (string $text) use ($io): void {
            $io->writeln('  '.$text);
        });
        $ergebnis = array();
        $start = microtime(true);

        foreach ($teile as $teil) {
            $io->section(ucfirst($teil));
            $t = microtime(true);

            if ('turniere' === $teil) {
                $ergebnis[] = array('Turniere', $importeur->turniere($archiv, $ordner), round(microtime(true) - $t).' s');
            } elseif ('personen' === $teil) {
                $p = $importeur->personen($archiv, $ordner);
                $ergebnis[] = array('Personen', $p['personen'], round(microtime(true) - $t).' s');
                $ergebnis[] = array('Kanten (gelesen)', $p['kanten'], '');
                $ergebnis[] = array('Kanten entfallen', $p['entfallen'], '');
                $ergebnis[] = array('Kanten wieder da', $p['wieder'], '');
            } else {
                $ergebnis[] = array('Kartei', $importeur->kartei($archiv, $ordner), round(microtime(true) - $t).' s');
            }
        }

        $db->executeStatement('REPLACE INTO archiv_info (schluessel, wert) VALUES (?, ?)', array('import_'.$archiv, json_encode(array('erstellt' => $uebergabe['erstellt'] ?? null, 'importiert' => date('c'), 'teile' => $teile), JSON_UNESCAPED_UNICODE)));

        $io->table(array('Teil', 'Zeilen', 'Dauer'), $ergebnis);

        // Was der neue Abzug nicht mehr führt, bleibt stehen — nur melden
        $uebrig = $importeur->uebrig($archiv, $uebergabe);

        if ($uebrig['turniere'] > 0 || $uebrig['personen'] > 0) {
            $io->note(sprintf('Nicht mehr in der Übergabe, aber in der Datenbank: %d Turniere, %d Personen. Sie bleiben stehen (der Import löscht nichts).', $uebrig['turniere'], $uebrig['personen']));
        }

        if (\in_array('personen', $teile, true)) {
            $io->note('Jetzt wertungsportal:archiv-personen laufen lassen — erst danach gelten neue und entfallene Kanten in der Ausgabe (Gesamtpersonen).');
        }

        $io->success('Archiv „'.$archiv.'" übernommen in '.round(microtime(true) - $start).' s.');

        return 0;
    }
}
