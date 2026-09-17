<?php

declare(strict_types=1);

/**
 * Contao Open Source CMS
 *
 * @package   Wertungsportal
 * @file      Rohdaten
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Modul „Rohdaten": ruft eine Funktion der nu-Schnittstelle auf und
 * liefert die Antwort unverändert als Datei.
 *
 * Zweck: Rückfragen, bei denen jemand die Originalantwort braucht — etwa nu
 * selbst, wenn eine Auswertung strittig ist. Das Frontend zeigt nur die
 * aufbereitete Fassung, der Zwischenspeicher hält die bereits dekodierte.
 *
 * Die Arbeit erledigt `Helper\Rohabfrage`; hier stehen nur Formular, Prüfung
 * der Voraussetzungen und die Auslieferung der Datei.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Rohdaten extends \Contao\BackendModule
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_wp_rohdaten';

	/**
	 * Baut das Formular auf und liefert nach einem erfolgreichen Abruf die
	 * Datei aus.
	 *
	 * Die Auswahlliste schickt das Formular beim Wechsel ab, damit die Felder
	 * der neuen Funktion erscheinen. Abgerufen wird nur, wenn die Schaltfläche
	 * `aktion=abrufen` gedrückt wurde — ein Funktionswechsel löst also keinen
	 * Aufruf bei nu aus.
	 *
	 * Bei HTTP 200 verlässt die Methode den normalen Ablauf über eine
	 * `ResponseException`: Contao schickt dann die Datei statt der Seite. Jede
	 * andere Antwort erscheint samt Adresse, Meldung und Antworttext auf der
	 * Seite, statt eine Fehlerseite von nu als „Rohdaten" herunterzuladen.
	 *
	 * Jeder Abruf kommt ins Systemprotokoll. Die Antworten enthalten
	 * Personendaten; so bleibt nachvollziehbar, wer wann was geholt hat.
	 *
	 * @return void
	 *
	 * @throws \Contao\CoreBundle\Exception\ResponseException mit der Datei
	 */
	protected function compile()
	{
		$funktionen = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::FUNKTIONEN;

		$funktion = $this->post('funktion');
		if (!isset($funktionen[$funktion])) $funktion = 'Turnierauswertung';

		$eingabe = array();

		foreach (array_keys(\Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::PARAMETER) as $feld)
		{
			$eingabe[$feld] = $this->post($feld);
		}

		$lesbar = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::lesbar($this->post('FORM_SUBMIT') === 'wp_rohdaten', $this->post('lesbar'));

		$this->Template->funktionen = $funktionen;
		$this->Template->parameter = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::PARAMETER;
		$this->Template->funktion = $funktion;
		$this->Template->felder = $funktionen[$funktion]['felder'];
		$this->Template->pflicht = $funktionen[$funktion]['pflicht'];
		$this->Template->endpunkt = \Schachbulle\ContaoWertungsportalBundle\Helper\API::endpunkte()[$funktion] ?? '';
		$this->Template->eingabe = $eingabe;
		$this->Template->lesbar = $lesbar;
		$this->Template->fehler = array();
		$this->Template->ergebnis = null;
		// Den Token ueber den Dienst holen, nicht ueber die Konstante
		// REQUEST_TOKEN: Die gibt es nur in Contao 4.13
		$this->Template->token = (string) \Contao\System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();

		if ($this->post('FORM_SUBMIT') !== 'wp_rohdaten' || $this->post('aktion') !== 'abrufen') return;

		$pruefung = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::eingabe($funktion, $eingabe);

		// Die bereinigten Werte zurück ins Formular — eine klein eingegebene
		// NU-Nummer steht danach so da, wie sie abgefragt wurde
		$this->Template->eingabe = array_merge($eingabe, $pruefung['werte']);

		if (count($pruefung['fehler']))
		{
			$this->Template->fehler = $pruefung['fehler'];

			return;
		}

		$hindernis = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::hindernis();

		if ($hindernis !== '')
		{
			$this->Template->fehler = array($hindernis);

			return;
		}

		$ergebnis = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::abrufen(
			\Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::parameter($funktion, $pruefung['werte'])
		);

		\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog(
			'Wertungsportal: Rohdaten abgerufen: '.$funktion.' ('.\Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::beschreibung($pruefung['werte']).') - HTTP '.$ergebnis['http'].', '.strlen($ergebnis['roh']).' Byte',
			__METHOD__,
			'GENERAL'
		);

		if ($ergebnis['fehler'] !== '' || $ergebnis['http'] !== 200)
		{
			$ergebnis['hinweis'] = \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::hinweis($funktion, $ergebnis, $pruefung['werte']);
			$ergebnis['groesse'] = strlen($ergebnis['roh']);
			// Ausschnitt in gültiges UTF-8 bringen: Der Schnitt kann mitten in
			// ein Zeichen fallen, und eine Fehlerseite des Servers muss gar
			// kein UTF-8 sein
			$ergebnis['auszug'] = mb_scrub(substr($ergebnis['roh'], 0, \Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::AUSZUG), 'UTF-8');
			unset($ergebnis['roh']);

			$this->Template->ergebnis = $ergebnis;

			return;
		}

		$this->liefere(
			\Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::inhalt($ergebnis['roh'], $lesbar),
			\Schachbulle\ContaoWertungsportalBundle\Helper\Rohabfrage::dateiname($funktion, $pruefung['werte'], time())
		);
	}

	/**
	 * Schickt den Inhalt als Datei an den Browser.
	 *
	 * `Cache-Control: no-store`, weil die Antwort Personendaten enthält und in
	 * keinem Zwischenspeicher unterwegs liegen bleiben soll.
	 *
	 * @param string $inhalt    Dateiinhalt
	 * @param string $dateiname Dateiname, nur ASCII (siehe Rohabfrage::dateiname)
	 *
	 * @return void
	 *
	 * @throws \Contao\CoreBundle\Exception\ResponseException immer
	 */
	protected function liefere(string $inhalt, string $dateiname): void
	{
		$antwort = new \Symfony\Component\HttpFoundation\Response($inhalt);
		$antwort->headers->set('Content-Type', 'application/json; charset=utf-8');
		$antwort->headers->set('Content-Disposition', \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition(\Symfony\Component\HttpFoundation\HeaderUtils::DISPOSITION_ATTACHMENT, $dateiname));
		$antwort->headers->set('Cache-Control', 'no-store, private');
		$antwort->headers->set('X-Content-Type-Options', 'nosniff');

		throw new \Contao\CoreBundle\Exception\ResponseException($antwort);
	}

	/**
	 * Liest ein Formularfeld unverändert aus dem Request.
	 *
	 * **Nicht über `Input::post()`:** Das wandelt Zeichen wie Klammern in
	 * HTML-Entities. Ein Turniername „Open (A)" käme als `Open &#40;A&#41;` bei
	 * nu an und fände nichts. Hier wird der Wert roh gelesen und danach in
	 * `Rohabfrage::eingabe()` streng geprüft; in die Adresse kommt er nur
	 * kodiert, auf die Seite nur maskiert.
	 *
	 * Über `all()` statt `get()`: Symfony 7 wirft bei einem Array im Feld eine
	 * Ausnahme, und ein manipuliertes Formular soll keine Fehlerseite erzeugen.
	 *
	 * @param string $feld Feldname
	 *
	 * @return string Wert, oder leer wenn nicht vorhanden oder kein Text
	 */
	protected function post(string $feld): string
	{
		$request = \Contao\System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request === null) return '';

		$alle = $request->request->all();
		$wert = $alle[$feld] ?? '';

		return is_scalar($wert) ? (string) $wert : '';
	}
}
