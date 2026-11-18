<?php

declare(strict_types=1);

/**
 * Contao Open Source CMS
 *
 * @package   Wertungsportal
 * @file      Wertungsarchiv
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Modul „Wertungsarchiv" (ab 2.0.0): Überblick über die drei
 * Archive, Suche nach Gesamtpersonen, Ansicht einer Gesamtperson mit ihren
 * Archivpersonen und Kanten, Prüffälle. Eine Kante lässt sich ablehnen oder
 * wieder bestätigen; wirksam wird das beim nächsten Lauf von
 * `wertungsportal:archiv-personen`.
 *
 * Die Daten liegen in der Archivdatenbank, nicht in der von Contao — darum
 * kein DCA, sondern ein eigenes Modul. Die Abfragen stehen in
 * Wertungsarchiv\Ansicht.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Ansicht;
use Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Verbindung;

class Wertungsarchiv extends \Contao\BackendModule
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_wp_wertungsarchiv';

	/**
	 * Wählt die Ansicht nach den Parametern der Adresse und füllt die Vorlage.
	 *
	 * - `gesamt=<id>`: eine Gesamtperson
	 * - `ansicht=pruefen`: Prüffälle und abgelehnte Kanten
	 * - `q=<begriff>`: Suche
	 * - sonst: Überblick
	 *
	 * Ein abgeschicktes Formular (Status einer Kante) wird verarbeitet und
	 * dann auf dieselbe Seite umgeleitet, damit ein Neuladen es nicht noch
	 * einmal abschickt.
	 *
	 * @return void
	 *
	 * @throws \Contao\CoreBundle\Exception\RedirectResponseException nach einer Änderung
	 */
	protected function compile()
	{
		$verbindung = \Contao\System::getContainer()->get(Verbindung::class);

		$this->Template->eingerichtet = $verbindung->verfuegbar();
		$this->Template->token = (string) \Contao\System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
		$this->Template->ansicht = 'ueberblick';
		$this->Template->q = '';

		if (!$verbindung->verfuegbar()) {
			return;
		}

		// Nach einem Update des Bundles kann das Schema hinterherhinken; dann
		// fehlen Tabellen oder Spalten, und jede Abfrage scheiterte
		$this->Template->veraltet = \count(\Schachbulle\ContaoWertungsportalBundle\Wertungsarchiv\Schema::anweisungen($verbindung->db()));

		if ($this->Template->veraltet) {
			return;
		}

		$ansicht = new Ansicht($verbindung->db());

		if ('wp_archiv_kante' === $this->post('FORM_SUBMIT')) {
			$this->kante($ansicht);
		}

		$gesamt = (int) \Contao\Input::get('gesamt');
		$q = trim((string) $this->get('q'));
		$this->Template->q = $q;

		if ($gesamt > 0) {
			$this->Template->ansicht = 'gesamt';
			$this->Template->daten = $ansicht->gesamtperson($gesamt);
		} elseif ('pruefen' === \Contao\Input::get('ansicht')) {
			$this->Template->ansicht = 'pruefen';
			$this->Template->pruefliste = $ansicht->pruefliste();
			$this->Template->abgelehnt = $ansicht->abgelehnt();
		} elseif ('' !== $q) {
			$this->Template->ansicht = 'suche';
			$this->Template->treffer = $ansicht->suche($q);
		} else {
			$this->Template->ueberblick = $ansicht->ueberblick();
		}
	}

	/**
	 * Setzt den Status einer Kante, vermerkt es im Systemprotokoll und lädt
	 * die Seite neu.
	 *
	 * @param Ansicht $ansicht Abfragen der Archivdatenbank
	 *
	 * @return void
	 *
	 * @throws \Contao\CoreBundle\Exception\RedirectResponseException immer
	 */
	protected function kante(Ansicht $ansicht): void
	{
		$id = (int) $this->post('kante');
		$status = $this->post('status');

		if ($id > 0 && \in_array($status, Ansicht::STATUS, true)) {
			$vorher = $ansicht->setzeStatus($id, $status);

			if (null !== $vorher) {
				$benutzer = \Contao\BackendUser::getInstance();

				\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog(
					sprintf('Wertungsarchiv: Kante %d (%s → %s, %s) von „%s" auf „%s" gesetzt durch %s', $id, $vorher['von'], $vorher['nach'], $vorher['quelle'], $vorher['status'], $status, (string) $benutzer->username),
					__METHOD__,
					'GENERAL'
				);
			}
		}

		throw new \Contao\CoreBundle\Exception\RedirectResponseException(\Contao\Environment::get('base').\Contao\Environment::get('request'));
	}

	/**
	 * Liest ein Formularfeld unverändert (siehe Rohdaten::post()).
	 *
	 * @param string $feld Feldname
	 *
	 * @return string Wert, leer wenn nicht vorhanden oder kein Text
	 */
	protected function post(string $feld): string
	{
		$request = \Contao\System::getContainer()->get('request_stack')->getCurrentRequest();
		$wert = $request ? ($request->request->all()[$feld] ?? '') : '';

		return \is_scalar($wert) ? (string) $wert : '';
	}

	/**
	 * Liest einen Parameter der Adresse unverändert.
	 *
	 * Nicht über Input::get(): Das maskiert Zeichen wie Kommas nicht, aber
	 * Klammern und Anführungszeichen — ein Suchbegriff soll so ankommen, wie er
	 * eingegeben wurde; ausgegeben wird er nur maskiert.
	 *
	 * @param string $feld Parametername
	 *
	 * @return string Wert, leer wenn nicht vorhanden oder kein Text
	 */
	protected function get(string $feld): string
	{
		$request = \Contao\System::getContainer()->get('request_stack')->getCurrentRequest();
		$wert = $request ? ($request->query->all()[$feld] ?? '') : '';

		return \is_scalar($wert) ? (string) $wert : '';
	}
}
