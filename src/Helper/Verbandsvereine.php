<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Bereitet die Vereine und Untergliederungen eines Verbandes für die Ausgabe
 * auf.
 *
 * Hintergrund: Der Parameter `vkz` der Schnittstelle ist eine PRÄFIXSUCHE.
 * Eine Vereinsseite mit „300" bekam deshalb die Mitglieder aller 50 Berliner
 * Vereine geliefert und stellte sie unter den Namen des ersten Treffers —
 * gemeldet als „VKZ 300 zeigt den Verein Eckbauer". Bei „100" traf der Präfix
 * den Verband selbst, der keine eigenen Mitglieder hat: leere Rangliste unter
 * dem Namen des Badischen Schachverbands.
 *
 * Für eine Verbands-VKZ gehört deshalb keine Mitgliederliste auf die Seite,
 * sondern die Liste der Vereine darunter. Diese Klasse baut sie.
 */
class Verbandsvereine
{
	/**
	 * Vollständige Aufstellung aus API::Verbandsliste(), also
	 * array('verbaende' => …, 'vereine' => …).
	 * @var array
	 */
	protected $liste;

	/**
	 * Angefragte Verbands-VKZ, so wie sie in der Adresse stand.
	 * @var string
	 */
	protected $vkz;

	/**
	 * Aufbereitete Ausgabe, erreichbar über das magische __get.
	 * @var array
	 */
	protected $daten = array();

	/**
	 * @param array  $liste Ergebnis von API::Verbandsliste()
	 * @param string $vkz   Verbands-VKZ, ganz (30000) oder verkürzt (300)
	 */
	public function __construct($liste, $vkz)
	{
		$this->liste = is_array($liste) ? $liste : array('verbaende' => array(), 'vereine' => array());
		$this->vkz = (string) $vkz;

		$this->compile();
	}

	/**
	 * Stellt Name, Untergliederungen und Vereine des Verbandes zusammen.
	 *
	 * Maßgeblich ist der Präfix ohne nachlaufende Nullen: „300" und „30000"
	 * werden beide zu „3" und fassen damit 30001, 30002 … zusammen; der
	 * Bezirk 10100 wird zu „101". Der Verband selbst bleibt außen vor, sonst
	 * verwiese die Liste auf die Seite, auf der man gerade steht.
	 *
	 * @return void
	 */
	public function compile()
	{
		$praefix = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::vkzPraefix($this->vkz);
		$voll = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::vkzVoll($this->vkz);

		$this->daten['Name'] = '';
		$this->daten['Verbaende'] = array();
		$this->daten['Vereine'] = array();

		foreach($this->liste['verbaende'] as $item)
		{
			$item_vkz = (string) ($item['clubVkz'] ?? '');

			// Der angefragte Verband liefert die Überschrift
			if($item_vkz === $voll || $item_vkz === $this->vkz)
			{
				$this->daten['Name'] = (string) ($item['clubName'] ?? '');
				continue;
			}

			if($praefix === '' || strpos($item_vkz, $praefix) === 0)
			{
				// Untergliederungen verweisen auf die Vereinsseite und nicht
				// auf die Verbandsrangliste: So führt ein Klick eine Ebene
				// tiefer in die Vereine statt in eine Spielerliste
				$this->daten['Verbaende'][] = array
				(
					'zps'  => $item_vkz,
					'name' => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl().'/%s.html">%s</a>', $item_vkz, $item['clubName'] ?? ''),
				);
			}
		}

		foreach($this->liste['vereine'] as $item)
		{
			$item_vkz = (string) ($item['clubVkz'] ?? '');

			if($praefix === '' || strpos($item_vkz, $praefix) === 0)
			{
				$this->daten['Vereine'][] = array
				(
					'zps'  => $item_vkz,
					'name' => sprintf('<a href="'.\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl().'/%s.html">%s</a>', $item_vkz, $item['clubName'] ?? ''),
				);
			}
		}

		// Nach Kennziffer ordnen — die Schnittstelle liefert sie unsortiert,
		// und die Kennziffer bildet die Gliederung des Verbandes ab
		usort($this->daten['Verbaende'], function($a, $b) { return strcmp($a['zps'], $b['zps']); });
		usort($this->daten['Vereine'], function($a, $b) { return strcmp($a['zps'], $b['zps']); });

		// Ohne Namen UND ohne Inhalt ist die Kennziffer unbekannt
		$this->daten['Gefunden'] = ($this->daten['Name'] !== '' || count($this->daten['Verbaende']) || count($this->daten['Vereine']));
	}

	/**
	 * Setzt einen Wert der Ausgabe.
	 *
	 * @param  string $name  Schlüssel
	 * @param  mixed  $value Wert
	 * @return void
	 */
	public function __set($name, $value)
	{
		$this->daten[$name] = $value;
	}

	/**
	 * Liefert einen Wert der Ausgabe.
	 *
	 * @param  string $name Schlüssel (Name, Verbaende, Vereine, Gefunden)
	 * @return mixed        Wert, oder null wenn nicht vorhanden
	 */
	public function __get($name)
	{
		return $this->daten[$name] ?? null;
	}
}
