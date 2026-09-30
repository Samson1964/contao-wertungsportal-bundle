<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;
use Schachbulle\ContaoWertungsportalBundle\Helper\Referentenbaum;

/**
 * Frontend-Modul „Wertungsreferenten (Tabelle)" (ab 1.52.0).
 *
 * Ersetzt das Frontend-Modul „Wertungsreferenten" des Adressen-Bundles
 * (schachbund.de/adressen_wertungen.html): die Verbände in der Reihenfolge
 * ihrer Kennziffer, je Verband die veröffentlichten Referenten mit Name,
 * Funktions-E-Mail und den Daten ihrer Adresse aus der Adressverwaltung.
 *
 * Anders als das Modul „Wertungsreferenten" (Referentenliste) ist es für
 * allgemeine Seiten gedacht: ohne Linkleiste des Wertungsportals, mit der
 * Überschrift aus dem Modul, und nur mit Verbänden, unter denen jemand
 * steht — die übergeordneten Ebenen ohne eigenen Referenten, die die
 * Gliederung dort zum Einrücken braucht, fallen weg.
 *
 * Die Daten stehen vollständig in den eigenen Tabellen und in der
 * Adressverwaltung; nu wird nicht gefragt.
 */
class Referententabelle extends \Contao\Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_referententabelle';

	/**
	 * Zeigt im Backend einen Platzhalter statt der Ausgabe.
	 *
	 * @return string Markup des Platzhalters oder die normale Ausgabe
	 */
	public function generate()
	{
		if(Helper::istBackend())
		{
			$objTemplate = new \Contao\BackendTemplate('be_wertungsportal');

			$objTemplate->wildcard = '### WERTUNGSPORTAL REFERENTEN (TABELLE) ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Befüllt das Template mit den Verbänden, unter denen veröffentlichte
	 * Referenten stehen.
	 *
	 * Die Überschrift setzt Module::generate() aus dem Modulfeld; hier wird
	 * keine eigene vergeben. Die Reihenfolge kommt aus Referentenbaum::baum()
	 * (nach Kennziffer sortiert, der DSB mit 00000 zuerst).
	 *
	 * @return void
	 */
	protected function compile()
	{
		$zeilen = array();

		foreach(Referentenbaum::baum() as $zeile)
		{
			if(!empty($zeile['referenten']))
			{
				$zeilen[] = $zeile;
			}
		}

		$this->Template->zeilen = $zeilen;
	}
}
