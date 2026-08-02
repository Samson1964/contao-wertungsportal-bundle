<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Spielersuche-Klasse
//  Gibt die Trefferliste einer Spielersuche formatiert zurück,
//  so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Spielersuche
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiErgebnisse; // Enthält das Array von der API mit den Suchergebnissen
	public string $quelle; // Herkunft der Trefferliste ('API' oder 'Lokal')
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	//  $Quelle steht später in der Spalte „Quelle" der Trefferliste. Die
	//  Klasse kann das nicht selbst erkennen: Die örtliche Suche liefert
	//  bewusst dieselbe Antwortform wie die Schnittstelle.
	// ─────────────────────────────────────────────
	public function __construct($Ergebnisse, $Quelle = 'API')
	{
		$this->apiErgebnisse = is_array($Ergebnisse) ? $Ergebnisse : array();
		$this->quelle = (string) $Quelle;

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die formatierte Trefferliste (sortiert nach Spielername)
	// ─────────────────────────────────────────────
	public function compile()
	{
		$this->daten['Spielerliste'] = array(); // Wird nachfolgend befüllt

		if(isset($this->apiErgebnisse['body']['data']) && is_array($this->apiErgebnisse['body']['data']))
		{
			// Gesperrte Personen (Blacklist) in einem Rutsch ermitteln
			$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($this->apiErgebnisse['body']['data'], 'nuLigaPersonId'));

			foreach($this->apiErgebnisse['body']['data'] as $person)
			{
				// Blacklist-Personen nicht anzeigen
				if(!empty($person['nuLigaPersonId']) && isset($blacklist[$person['nuLigaPersonId']])) continue;

				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('rating', $person)) $person['rating'] = false;
				if(!array_key_exists('index', $person)) $person['index'] = false;

				// Aktiv-Status suchen in Mitgliedschaften
				$verein = '';
				if(isset($person['memberships']))
				{
					foreach($person['memberships'] as $mitglied)
					{
						$verein = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);
						if($mitglied['licenceState'] == 'ACTIVE') break; // Abbruch wenn A-Status gefunden
					}
				}

				// Daten schreiben
				$this->daten['Spielerliste'][] = array
				(
					'PKZ'             => 'x',
					'Verein'          => $verein,
					'Quelle'          => $this->quelle,
					'NuId'            => isset($person['nuLigaPersonId']) ? (string) $person['nuLigaPersonId'] : '',
					'Spielername'     => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Spielername($person),
					'Spielername_RAW' => $person['lastname'].' '.$person['firstname'],
					'KW'              => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Kalenderwoche($person),
					'DWZ'             => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($person['rating'], $person['index']),
					'Elo'             => isset($person['fideElo']) ? $person['fideElo'] : '',
					'Titel'           => isset($person['fideTitle']) ? $person['fideTitle'] : '',
				);
			}

			self::sortiere($this->daten['Spielerliste']);
		}

		$this->daten['Anzahl'] = count($this->daten['Spielerliste']);
	}

	// ─────────────────────────────────────────────
	//  Funktion zusammenfuehren
	//  Führt die Treffer der Schnittstelle und der örtlichen Suche zu einer
	//  Liste zusammen.
	//
	//  Nötig, weil die Schnittstelle nachweislich unvollständig antwortet:
	//  Eine Suche nach „Eschen" fand dort „Eschen, Alexander", aber nicht
	//  „Eschenauer, Frank"; „Esch" fand wiederum drei ganz andere Spieler.
	//  Die örtliche Suche läuft deshalb IMMER mit, nicht mehr nur als
	//  Rückfallebene bei null Treffern.
	//
	//  Bei Dubletten gewinnt der Datensatz der Schnittstelle — er ist der
	//  aktuellere. Erkannt werden sie über die nuLigaPersonId; Datensätze
	//  ohne diese Nummer werden nicht zusammengelegt, sonst fielen sie alle
	//  auf einen zusammen.
	//
	//  @param  array $api    Trefferliste der Schnittstelle
	//  @param  array $lokal  Trefferliste der örtlichen Suche
	//  @return array         Gemeinsame, nach Namen sortierte Liste
	// ─────────────────────────────────────────────
	public static function zusammenfuehren($api, $lokal)
	{
		$liste = is_array($api) ? $api : array();
		$bekannt = array();

		foreach($liste as $eintrag)
		{
			if($eintrag['NuId'] !== '') $bekannt[$eintrag['NuId']] = true;
		}

		foreach((is_array($lokal) ? $lokal : array()) as $eintrag)
		{
			if($eintrag['NuId'] !== '' && isset($bekannt[$eintrag['NuId']])) continue;

			$liste[] = $eintrag;
			if($eintrag['NuId'] !== '') $bekannt[$eintrag['NuId']] = true;
		}

		self::sortiere($liste);

		return $liste;
	}

	// ─────────────────────────────────────────────
	//  Funktion sortiere
	//  Sortiert eine Trefferliste nach Spielername (A → Z), umlautsicher.
	//  Die Liste wird an Ort und Stelle geändert.
	//
	//  Sortiert wird über einen eigenen Schlüssel statt über strcoll mit
	//  gesetztem Locale: Ist de_DE.UTF-8 auf dem System nicht vorhanden,
	//  fällt setlocale stillschweigend auf "C" zurück und strcoll vergleicht
	//  dann Bytes — „Ärmel" landete so hinter „Zander".
	//
	//  Der Schlüssel folgt DERSELBEN Regel wie die Aliasfelder der Datenbank
	//  (ä → ae, ö → oe, ü → ue, ß → ss). Damit stimmt die Reihenfolge der
	//  angezeigten Liste mit der Reihenfolge überein, in der die örtliche
	//  Suche ihre Treffer liefert (ORDER BY lastnameAlias, firstnameAlias).
	//
	//  Warum hier nicht einfach das Aliasfeld selbst? Weil es die Aliase nur
	//  in den örtlichen Spiegeltabellen gibt. Die Treffer der Schnittstelle
	//  bringen keines mit, und beide Quellen stehen in derselben Liste.
	// ─────────────────────────────────────────────
	protected static function sortiere(&$liste)
	{
		usort($liste, function($a, $b)
		{
			return strcmp(self::sortierschluessel($a['Spielername_RAW']), self::sortierschluessel($b['Spielername_RAW']));
		});
	}

	// ─────────────────────────────────────────────
	//  Funktion sortierschluessel
	//  Wandelt einen Namen in einen vergleichbaren Schlüssel um — nach
	//  denselben Regeln, die auch Helper::alias auf die Aliasfelder anwendet.
	// ─────────────────────────────────────────────
	protected static function sortierschluessel($name)
	{
		$name = mb_strtolower((string) $name, 'UTF-8');

		return strtr($name, array
		(
			'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
			'æ' => 'ae', 'œ' => 'oe', 'å' => 'a', 'ø' => 'o',
			'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u',
			'ç' => 'c', 'ñ' => 'n', 'ý' => 'y',
		));
	}

	// ─────────────────────────────────────────────
	//  Magische Methode __set
	// ─────────────────────────────────────────────
	public function __set($name, $value)
	{
		$this->daten[$name] = $value;
	}

	// ─────────────────────────────────────────────
	//  Magische Methode __get
	// ─────────────────────────────────────────────
	public function __get($name)
	{
		return array_key_exists($name, $this->daten) ? $this->daten[$name] : null;
	}
}
