<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;
use Schachbulle\ContaoWertungsportalBundle\Helper\Referentenbaum;

/**
 * Frontend-Modul „Wertungsreferenten (Baum)" (ab 1.53.0).
 *
 * Die Wertungsreferenten als verschachtelte Liste: Landesverbände, darunter
 * ihre Bezirke, darunter deren Untergliederungen — der DSB (00000) wird
 * weggelassen (Franks Vorgabe). Je Verband stehen nur Kennziffer und Name,
 * je Referent nur Vor- und Nachname und die E-Mail-Adressen (die
 * Funktions-E-Mail zuerst, wie in allen Ausgaben).
 *
 * Wie beim Tabellen-Modul: ohne Linkleiste, Überschrift aus dem Modul, nur
 * veröffentlichte Referenten. Aufgeführt werden Verbände mit Referenten und
 * ihre übergeordneten Ebenen, damit der Baum zusammenhängt.
 */
class Referentengliederung extends \Contao\Module
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'wertungsportal_referentenbaum';

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

			$objTemplate->wildcard = '### WERTUNGSPORTAL REFERENTEN (BAUM) ###';
			$objTemplate->title = $this->name;
			$objTemplate->id = $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}

	/**
	 * Befüllt das Template mit dem Baum der Verbände und ihrer Referenten.
	 *
	 * Die Überschrift setzt Module::generate() aus dem Modulfeld.
	 *
	 * @return void
	 */
	protected function compile()
	{
		$this->Template->baum = Referentenbaum::verschachtelt(Referentenbaum::baum());
	}
}
