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
 * Funktions-E-Mail und Anschrift/Telefon ihrer Adresse aus der
 * Adressverwaltung.
 *
 * Anders als das Modul „Wertungsreferenten" (Referentenliste) ist es für
 * allgemeine Seiten gedacht: ohne Linkleiste des Wertungsportals und mit der
 * Überschrift aus dem Modul.
 *
 * Seit 1.54.0 stehen auch die unbesetzten Verbände in der Tabelle, mit dem
 * Vermerk „nicht besetzt" (Frank, 06.10.2026) — bis 1.53.0 nur die Verbände,
 * unter denen jemand eingetragen war.
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
	 * Befüllt das Template mit allen Verbänden und ihren veröffentlichten
	 * Referenten.
	 *
	 * Die Überschrift setzt Module::generate() aus dem Modulfeld; hier wird
	 * keine eigene vergeben. Reihenfolge und Auswahl der Verbände kommen aus
	 * Referentenbaum::baum() (nach Kennziffer sortiert, der DSB mit 00000
	 * zuerst, unbesetzte eingeschlossen).
	 *
	 * @return void
	 */
	protected function compile()
	{
		$this->Template->zeilen = Referentenbaum::baum();
	}
}
