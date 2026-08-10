<?php

/**
 * Wertungsportal-Ausgabe: Wertungsreferenten der Verbände
 *
 * Zeigt die im Backend gepflegten Referenten als Gliederung — DSB, darunter
 * die Landesverbände, darunter deren Bezirke. Die Aufbereitung übernimmt
 * Helper\Referentenbaum.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Referentenliste extends \Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_referenten';

	/**
	 * Zeigt im Backend einen Platzhalter statt der Ausgabe.
	 *
	 * @return string Markup des Platzhalters oder die normale Ausgabe
	 */
	public function generate()
	{
		if(TL_MODE == 'BE')
		{
			$objTemplate = new \BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL REFERENTEN ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Befüllt das Template.
	 *
	 * Es wird nichts abgefragt und nichts von der Schnittstelle geholt — die
	 * Daten stehen vollständig in der eigenen Tabelle. Die Seite kostet also
	 * eine Datenbankabfrage und belastet nu nicht.
	 *
	 * @return void
	 */
	protected function compile()
	{
		global $objPage;

		$this->Template->hl = 'h1';
		$this->Template->shl = 'h2';
		$this->Template->headline = 'Wertungsportal - Referenten';
		$this->Template->navigation = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Navigation();

		$titel = 'Wertungsreferenten';
		$objPage->pageTitle = $titel;
		$this->Template->subHeadline = $titel;

		$this->Template->zeilen = \Schachbulle\ContaoWertungsportalBundle\Helper\Referentenbaum::baum();
	}
}
