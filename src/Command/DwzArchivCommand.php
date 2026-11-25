<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Command;

use Schachbulle\ContaoWertungsportalBundle\DwzArchiv\Archivindex;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Baut den Index der DWZ-Archivsuche auf und zeigt eine Übersicht
 * (ab 1.61.0): `wertungsportal:dwz-archiv`.
 *
 * Nötig ist der Befehl nicht — der Index entsteht beim ersten Seitenaufruf
 * von selbst. Er dient zum Prüfen nach dem Einspielen: Je Verband und
 * Formatgruppe stehen Anzahl, erster und letzter Tag, die größte Lücke und die
 * Jahre da, dazu die Dateien, die nicht ins Namensmuster passen. Nebenbei
 * füllt er den Cache, sodass der erste Besucher nicht wartet.
 */
class DwzArchivCommand extends Command
{
	/**
	 * Name des Befehls, wie ihn Symfony 5.4 (Contao 4.13) beim Erzeugen liest.
	 * Symfony 7 (Contao 5) nimmt ihn aus dem Attribut command am Tag in
	 * services.yml; beide müssen gleich lauten
	 * (tests/Contao5/KonsolenbefehleTest.php).
	 */
	protected static $defaultName = 'wertungsportal:dwz-archiv';

	/** @var Archivindex */
	private $index;

	/**
	 * @param Archivindex $index Verzeichnis der Archivdateien (Dienst aus services.yml)
	 */
	public function __construct(Archivindex $index)
	{
		$this->index = $index;

		parent::__construct();
	}

	/**
	 * Beschreibt den Befehl für `--help`.
	 */
	protected function configure(): void
	{
		$this->setDescription('Baut den Index der DWZ-Archivsuche auf und zeigt Bestand, Lücken und unpassende Dateien');
	}

	/**
	 * Liest alle Verbände ein und gibt die Übersicht aus.
	 *
	 * @param InputInterface  $input  Eingabe (keine Argumente)
	 * @param OutputInterface $output Ausgabe
	 *
	 * @return int 0 immer; unpassende Dateien sind ein Hinweis, kein Fehler
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$verbaende = $this->index->verbaende();

		if (array() === $verbaende) {
			$io->warning('Keine Archivordner gefunden.');

			return 0;
		}

		$zeilen = array();
		$ignoriert = array();

		foreach ($verbaende as $verband) {
			$bestand = $this->index->dateien($verband);
			$ignoriert = array_merge($ignoriert, $bestand['ignoriert']);

			foreach ($bestand['gruppen'] as $gruppe => $liste) {
				$jahre = array();
				$luecke = 0;
				$vorher = null;

				foreach ($liste as $datei) {
					$jahr = substr($datei['datum'], 0, 4);
					$jahre[$jahr] = ($jahre[$jahr] ?? 0) + 1;

					$tag = \DateTimeImmutable::createFromFormat('!Ymd', $datei['datum'], new \DateTimeZone('UTC'));
					if (null !== $vorher) {
						$luecke = max($luecke, (int) $vorher->diff($tag)->format('%a'));
					}
					$vorher = $tag;
				}

				$jahrestext = array();
				foreach ($jahre as $jahr => $anzahl) {
					$jahrestext[] = $jahr.': '.$anzahl;
				}

				$zeilen[] = array($verband, $gruppe, \count($liste), $liste[0]['datum'], end($liste)['datum'], $luecke, implode(', ', $jahrestext));
			}
		}

		$io->table(array('Verband', 'Gruppe', 'Dateien', 'erster Tag', 'letzter Tag', 'größte Lücke (Tage)', 'je Jahr'), $zeilen);

		if (array() !== $ignoriert) {
			$io->section('Nicht im Namensmuster oder im Ordner eines anderen Verbands (höchstens 50 je Verband)');
			$io->listing($ignoriert);
		}

		$io->success('Index aufgebaut.');

		return 0;
	}
}
