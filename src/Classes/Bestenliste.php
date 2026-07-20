<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Bestenliste
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Version 1.0 - 2026 - Frank Binding
 * --------------------------------------
 * DWZ-Bestenliste (Top-x alle Spieler oder nur Frauen).
 *
 * Portierung des Bestenliste-Moduls aus dem contao-dewis-bundle mit neuer
 * Datenquelle: Statt der nu-API (Verbandsliste mit Überabruf und einem
 * FIDE-Nation-Einzelabruf JE Spieler — der Grund für die lange Laufzeit vor
 * dem Caching) liest das Modul direkt die lokale Personentabelle
 * tl_wertungsportal_persons. Eine einzige SQL-Abfrage liefert die Topliste;
 * ein Cache ist nicht mehr nötig, die Ausgabe ist immer aktuell.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Bestenliste extends \Module
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_bestenliste';

	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL BESTENLISTE ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}

		return parent::generate(); // Weitermachen mit dem Modul
	}

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		$anzahl = (int) $this->dwz_topcount > 0 ? (int) $this->dwz_topcount : 100;
		$nurFrauen = ($this->dwz_gender == 'f');

		// Topliste direkt aus der lokalen Personentabelle:
		// - nur veröffentlichte, lebende Personen mit DWZ, ohne Blacklist
		// - nur Deutsche (Nation GER oder unbekannt, wie in DeWIS)
		// - nur Personen mit mindestens einer aktiven Mitgliedschaft
		//   (Passive wurden auch in DeWIS übersprungen)
		$sql = "SELECT p.id, p.nuLigaPersonId, p.firstname, p.lastname, p.rating, p.`index`, p.fideId
		        FROM tl_wertungsportal_persons p
		        WHERE p.published = '1' AND p.rating > 0 AND p.blocked != '1' AND p.verstorben != '1'
		          AND (p.nation = 'GER' OR p.nation = '')"
		        .($nurFrauen ? " AND p.gender = 'FEMALE'" : "")."
		          AND EXISTS (SELECT m.id FROM tl_wertungsportal_persons_memberships m WHERE m.pid = p.id AND m.licenceState = 'ACTIVE' AND m.published = '1')
		        ORDER BY p.rating DESC, p.`index` DESC, p.lastname, p.firstname";

		$objSpieler = \Database::getInstance()->prepare($sql)
		                                      ->limit($anzahl)
		                                      ->execute();

		// Zeilen einsammeln, IDs für die Bulk-Nachladungen merken
		$zeilen = array();
		$ids = array();
		$fideids = array();

		while($objSpieler->next())
		{
			$zeilen[] = $objSpieler->row();
			$ids[] = (int) $objSpieler->id;
			if($objSpieler->fideId) $fideids[] = $objSpieler->fideId;
		}

		// Aktive Mitgliedschaft (Vereinsname + VKZ) je Person in einem Rutsch laden
		$vereine = array();

		if(count($ids))
		{
			$platzhalter = implode(',', array_fill(0, count($ids), '?'));
			$objVerein = \Database::getInstance()->prepare("SELECT pid, vkz, clubName FROM tl_wertungsportal_persons_memberships WHERE licenceState = 'ACTIVE' AND published = '1' AND pid IN ($platzhalter) ORDER BY pid, vkz")
			                                     ->execute($ids);
			while($objVerein->next())
			{
				// Erste aktive Mitgliedschaft je Person gewinnt
				if(!isset($vereine[$objVerein->pid])) $vereine[$objVerein->pid] = $objVerein->row();
			}
		}

		// FIDE-Daten (Elo/Titel) gebündelt aus tl_wertungsportal_elo laden
		$fideliste = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getFIDEDatenListe($fideids);

		// Ausgabedaten aufbauen
		$daten = array();
		$platz = 0;

		foreach($zeilen as $spieler)
		{
			$platz++;
			$verein = isset($vereine[$spieler['id']]) ? $vereine[$spieler['id']] : null;
			$fide = ($spieler['fideId'] && isset($fideliste[$spieler['fideId']])) ? $fideliste[$spieler['fideId']] : array('land' => '', 'elo' => '', 'titel' => '');

			$daten[] = array
			(
				'Platz'       => $platz,
				'PKZ'         => $spieler['nuLigaPersonId'],
				'Spielername' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($spieler),
				'DWZ'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($spieler['rating'], $spieler['index']),
				'Elo'         => $fide['elo'],
				'FIDE-Titel'  => $fide['titel'],
				'Verein'      => $verein ? sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl().'/%s.html">%s</a>', $verein['vkz'], $verein['clubName']) : '',
			);
		}

		$this->Template->liste = $daten;
	}

}
