<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

use Schachbulle\ContaoWertungsportalBundle\Helper\Adressverknuepfung;
use Schachbulle\ContaoWertungsportalBundle\Helper\Helper;

/**
 * Globale Operation „Aus der Adressverwaltung übernehmen" im Modul Referenten
 * (key=uebernehmen, ab 1.51.0).
 *
 * Legt die Wertungsreferenten, die in der Adressverwaltung eingetragen sind
 * (tl_adressen.wertungsreferent), als Referenten des Wertungsportals an —
 * jeden mit seiner Adresse verknüpft und mit den Verbänden, für die er dort
 * zuständig ist. Danach wird die Zuständigkeit nur noch hier gepflegt; das
 * Referenten-Modul des Adressen-Bundles ist veraltet (Frank, 30.09.2026).
 *
 * Ablauf in zwei Schritten: Der Knopf zeigt zuerst, was geschähe; erst ein
 * zweiter Knopf (POST mit Anfragetoken) legt an. Beim Absenden wird der Plan
 * neu berechnet, nicht aus der Vorschau übernommen — ein doppelter Klick oder
 * eine veraltete Seite legt deshalb nichts doppelt an.
 *
 * Die Regeln stehen in Adressverknuepfung::uebernahmeplan().
 */
class Referentenuebernahme extends \Contao\Backend
{
	/**
	 * Kennung des Formulars (FORM_SUBMIT).
	 */
	public const FORMULAR = 'wp_referentenuebernahme';

	/**
	 * Zeigt die Vorschau oder führt die Übernahme aus.
	 *
	 * Ohne Adressverwaltung gibt es nichts zu übernehmen; dann geht es mit
	 * einer Fehlermeldung zurück zur Liste (der Knopf erscheint dort
	 * eigentlich gar nicht). Nach der Übernahme ebenfalls zurück zur Liste,
	 * mit einer Bestätigung und einer Zeile im Systemprotokoll.
	 *
	 * @param  \Contao\DataContainer|null $dc Von Contao übergeben, hier ungenutzt
	 * @return string                         HTML der Vorschau
	 */
	public function run($dc = null)
	{
		$zurueck = str_replace('&key=uebernehmen', '', \Contao\Environment::get('request'));

		if(!Adressverknuepfung::verfuegbar())
		{
			\Contao\Message::addError('Die Adressverwaltung (schachbulle/contao-adressen-bundle) ist in dieser Installation nicht vorhanden.');
			$this->redirect($zurueck);
		}

		$plan = self::plan();

		if(\Contao\Input::post('FORM_SUBMIT') === self::FORMULAR)
		{
			$veroeffentlichen = \Contao\Input::post('veroeffentlichen') === '1';
			$anzahl = self::ausfuehren($plan['neu'], $veroeffentlichen);

			\Contao\Message::addConfirmation(sprintf(
				'%d Referenten aus der Adressverwaltung übernommen%s.',
				$anzahl,
				$veroeffentlichen ? ' und veröffentlicht' : ' — noch nicht veröffentlicht'
			));

			if(count($plan['ohneVerband']))
			{
				\Contao\Message::addInfo(sprintf('%d Personen ohne passenden Verband bitte von Hand anlegen — die Liste steht in der Vorschau des Knopfes.', count($plan['ohneVerband'])));
			}

			Helper::systemlog(sprintf('%d Wertungsreferenten aus der Adressverwaltung übernommen (%s)', $anzahl, $veroeffentlichen ? 'veröffentlicht' : 'unveröffentlicht'), __METHOD__);

			$this->redirect($zurueck);
		}

		$objTemplate = new \Contao\BackendTemplate('be_wp_referentenuebernahme');
		$objTemplate->zurueck = $zurueck;
		$objTemplate->aktion = \Contao\Environment::get('request');
		$objTemplate->formular = self::FORMULAR;
		$objTemplate->token = (string) \Contao\System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
		$objTemplate->plan = $plan;
		$objTemplate->verbaende = Referenten::getVerbaende();

		return $objTemplate->parse();
	}

	/**
	 * Berechnet den Plan aus dem aktuellen Datenbestand.
	 *
	 * Gelesen werden nur Adressen mit Eintrag im Feld wertungsreferent, und
	 * nur die Spalten, die der Plan braucht. Fehlt eine Tabelle, bleibt die
	 * jeweilige Liste leer — die Vorschau meldet dann „nichts zu übernehmen".
	 *
	 * @return array Plan wie Adressverknuepfung::uebernahmeplan()
	 */
	public static function plan()
	{
		$db = \Contao\Database::getInstance();
		$adressen = array();
		$verknuepft = array();

		try
		{
			$objAdressen = $db->execute("SELECT id, nachname, vorname, firma, aktiv, wertungsreferent FROM tl_adressen WHERE wertungsreferent IS NOT NULL ORDER BY nachname, vorname, id");

			while($objAdressen->next())
			{
				$adressen[] = $objAdressen->row();
			}
		}
		catch(\Throwable $e)
		{
			// Tabelle fehlt (Bundle registriert, aber noch kein migrate)
		}

		try
		{
			$verknuepft = $db->execute("SELECT adresse FROM tl_wertungsportal_referenten WHERE adresse > 0")->fetchEach('adresse');
		}
		catch(\Throwable $e)
		{
			// Spalte fehlt noch (vor contao:migrate) — dann ist auch nichts verknüpft
		}

		// Bekannt ist, was im Feld „Zuständig für" zur Wahl steht. Daß PHP aus
		// „10000" einen Ganzzahlschlüssel macht, stört nicht: isset() mit der
		// Zeichenkette findet ihn trotzdem
		return Adressverknuepfung::uebernahmeplan($adressen, Referenten::getVerbaende(), $verknuepft);
	}

	/**
	 * Legt die geplanten Referenten an.
	 *
	 * Jeder bekommt die Adresse, die Verbände und — nur für Sortierung und
	 * Suche im Backend — den Namen aus der Adresse. E-Mail, Telefon und
	 * Anschrift bleiben leer: Sie kommen bei der Ausgabe aus der Adresse.
	 *
	 * @param  array $neu              Einträge aus dem Plan (Schlüssel `neu`)
	 * @param  bool  $veroeffentlichen true: gleich veröffentlichen
	 * @return int                     Anzahl der angelegten Referenten
	 */
	protected static function ausfuehren(array $neu, $veroeffentlichen)
	{
		$db = \Contao\Database::getInstance();
		$zeit = time();
		$anzahl = 0;

		foreach($neu as $eintrag)
		{
			$db->prepare("INSERT INTO tl_wertungsportal_referenten (tstamp, adresse, nachname, vorname, verbaende, published) VALUES (?, ?, ?, ?, ?, ?)")
			   ->execute($zeit, (int) $eintrag['adresse'], (string) $eintrag['nachname'], (string) $eintrag['vorname'], serialize(array_values($eintrag['verbaende'])), $veroeffentlichen ? '1' : '');

			++$anzahl;
		}

		return $anzahl;
	}
}
