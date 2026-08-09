<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Rückrufe des Backend-Moduls „Referenten".
 *
 * Liefert die Auswahlliste der Verbände und baut die Zeilen der Übersicht.
 */
class Referenten extends \Backend
{
	/**
	 * Zwischenspeicher der Verbandsliste für den laufenden Aufruf.
	 * @var array|null
	 */
	protected static $verbaende = null;

	/**
	 * Liefert die Verbände als Auswahlliste für die Mehrfachauswahl.
	 *
	 * Die Liste wird NICHT fest eingetragen, sondern aus dem örtlichen
	 * Vereinsbestand gelesen: Verband ist, wessen Kennziffer auf „00" endet
	 * (siehe Helper::istVerband) — also Landesverbände wie 30000 ebenso wie
	 * Bezirke wie 10100. So wandert eine Umgliederung bei nu von selbst in die
	 * Auswahl, statt hier gepflegt werden zu müssen.
	 *
	 * Gelesen wird aus tl_wertungsportal_clubs und nicht über die
	 * Schnittstelle: Das Backend soll auch dann bedienbar bleiben, wenn nu
	 * gerade nicht antwortet.
	 *
	 * @param  \DataContainer|null $dc Von Contao übergeben, hier ungenutzt
	 * @return array                   VKZ => „VKZ Name", nach VKZ geordnet
	 */
	public static function getVerbaende($dc = null)
	{
		if(self::$verbaende !== null) return self::$verbaende;

		self::$verbaende = array();

		try
		{
			// LIKE '%00' entspricht Helper::istVerband(); die beiden
			// Sonderfälle L0001/M0001 kommen ausdrücklich dazu
			$objVerbaende = \Database::getInstance()->execute("SELECT clubVkz, clubName FROM tl_wertungsportal_clubs WHERE clubVkz LIKE '%00' OR clubVkz IN ('L0001','M0001') ORDER BY clubVkz");

			while($objVerbaende->next())
			{
				self::$verbaende[$objVerbaende->clubVkz] = $objVerbaende->clubVkz.' '.$objVerbaende->clubName;
			}
		}
		catch(\Throwable $e)
		{
			// Fehlt die Tabelle noch (vor contao:migrate), bleibt die Auswahl
			// leer — das Feld ist dann eben nicht befüllbar, statt daß das
			// ganze Backend-Modul abbricht
			return self::$verbaende;
		}

		return self::$verbaende;
	}

	/**
	 * Baut die Beschriftung einer Listenzeile.
	 *
	 * Neben dem Namen steht in Grau, für wie viele Verbände der Referent
	 * zuständig ist und welche das sind — die Zuordnung ist der eigentliche
	 * Inhalt dieses Moduls und soll ohne Öffnen des Datensatzes erkennbar sein.
	 *
	 * @param  array  $row   Datensatz
	 * @param  string $label Vorgabe von Contao
	 * @return string        Beschriftung der Zeile
	 */
	public static function zeile($row, $label)
	{
		$name = trim(($row['nachname'] ?? '').', '.($row['vorname'] ?? ''));
		if($name === ',') $name = '(ohne Namen)';

		$vkz = \StringUtil::deserialize($row['verbaende'] ?? null, true);

		if(!count($vkz)) return \StringUtil::specialchars($name).' <span class="wp-meta">(kein Verband zugeordnet)</span>';

		$alle = self::getVerbaende();
		$namen = array();

		// Höchstens drei Namen ausschreiben, sonst wird die Zeile unlesbar
		foreach(array_slice($vkz, 0, 3) as $eine)
		{
			$namen[] = $alle[$eine] ?? $eine;
		}

		$text = implode(', ', $namen);
		if(count($vkz) > 3) $text .= ' und '.(count($vkz) - 3).' weitere';

		return \StringUtil::specialchars($name).' <span class="wp-meta">'.\StringUtil::specialchars($text).'</span>';
	}
}
