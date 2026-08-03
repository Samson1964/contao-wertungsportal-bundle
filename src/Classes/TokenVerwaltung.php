<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Rückrufe für die Backend-Verwaltung der Zugangsschlüssel.
 *
 * Enthält die Listendarstellung, die Zeilen der Zugriffs-Kindtabelle und die
 * globale Operation zum Aufräumen alter Zugriffe.
 */
class TokenVerwaltung extends \Backend
{
	/**
	 * Stellt eine Zeile der Schlüsselliste dar.
	 *
	 * Gesperrte Schlüssel werden durchgestrichen, damit sie in der Liste
	 * sofort auffallen; der Sperrgrund steht als Titel am Eintrag.
	 *
	 * @param  array  $row      Datensatz
	 * @param  string $label    Von Contao vorbereitete Beschriftung
	 * @return string
	 */
	public function zeile($row, $label)
	{
		if(empty($row['gesperrt'])) return $label;

		$grund = $row['grund'] ? ' ('.$row['grund'].')' : '';

		return '<span style="text-decoration:line-through" title="Gesperrt'.\StringUtil::specialchars($grund).'">'.$label.'</span>';
	}

	/**
	 * Stellt eine Zeile der Zugriffsliste dar: Zeitpunkt, Herkunft der Daten,
	 * Trefferzahl, Dauer und IP-Adresse.
	 *
	 * @param  array  $row  Datensatz
	 * @return string
	 */
	public function zugriffszeile($row)
	{
		$format = !empty($GLOBALS['TL_CONFIG']['datimFormat']) ? $GLOBALS['TL_CONFIG']['datimFormat'] : 'd.m.Y H:i';
		$quellen = $GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quellen'] ?? array();
		$quelle = $quellen[$row['quelle']] ?? $row['quelle'];

		$zusatz = array();
		if((int) $row['status'] !== 200) $zusatz[] = 'HTTP '.$row['status'];
		if((int) $row['anzahl'] > 0) $zusatz[] = $row['anzahl'].' Spieler';
		if((int) $row['dauer'] > 0) $zusatz[] = $row['dauer'].' ms';
		$zusatz[] = 'IP '.$row['ip'];

		return '<div class="tl_content_left">'.\Date::parse($format, (int) $row['zeitpunkt']).' Uhr &ndash; '.\StringUtil::specialchars((string) $quelle)
			.' <span class="wp-meta" style="color:#999">'.\StringUtil::specialchars(implode(', ', $zusatz)).'</span></div>';
	}

	/**
	 * Hält den Zugriffszähler der Schlüssel aktuell.
	 *
	 * Der Zähler in tl_wertungsportal_tokens wird beim Zugriff fortgeschrieben.
	 * Werden Zugriffe gelöscht (Aufräumen, einzelne Datensätze), stimmt er
	 * nicht mehr — deshalb wird er beim Öffnen der Liste einmal nachgezogen.
	 * Das ist eine einzige Abfrage und passiert nur im Backend.
	 *
	 * @param  \DataContainer $dc
	 * @return void
	 */
	public function zaehlerAktualisieren($dc = null)
	{
		// Nur in der Listenansicht, nicht bei jedem Bearbeiten-Aufruf
		if(\Input::get('act') != '') return;

		try
		{
			\Database::getInstance()->execute("UPDATE tl_wertungsportal_tokens t SET t.zugriffe = (SELECT COUNT(*) FROM tl_wertungsportal_tokens_access a WHERE a.pid = t.id)");
		}
		catch(\Throwable $e)
		{
			// Fehlende Tabelle darf das Backend nicht lahmlegen
		}
	}

	/**
	 * Globale Operation „Alte Zugriffe löschen": entfernt Zugriffe, die älter
	 * sind als die Aufbewahrungsfrist, und kehrt zur Liste zurück.
	 *
	 * Die Zugriffe enthalten IP-Adressen; sie dauerhaft aufzubewahren wäre
	 * weder nötig noch zulässig.
	 *
	 * @param  \DataContainer $dc
	 * @return void
	 */
	public function aufraeumen($dc = null)
	{
		$anzahl = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensAccessModel::aufraeumen();

		\Message::addConfirmation(sprintf(
			'%s Zugriffe gelöscht, die älter als %s Tage waren.',
			$anzahl,
			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensAccessModel::AUFBEWAHRUNG
		));

		$this->redirect(str_replace('&key=aufraeumen', '', \Environment::get('request')));
	}
}
