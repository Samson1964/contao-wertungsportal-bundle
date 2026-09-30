<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

/**
 * Rückrufe des Backend-Moduls „Reklamationen".
 */
class Reklamationen extends \Contao\Backend
{
	/**
	 * Baut die Spalten einer Zeile der Übersicht.
	 *
	 * Datum, Absender, Empfänger (mit Kennzeichen, wenn der Admin einsprang),
	 * Betreff und Stand. Ein gescheiterter Versand steht rot da — das sind die
	 * Fälle, um die sich jemand kümmern muß. Erledigte Reklamationen werden
	 * grau.
	 *
	 * @param array<string,mixed> $row   Datensatz
	 * @param string              $label Vorgabe von Contao (unbenutzt)
	 *
	 * @return array<int,string> Spalten in der Reihenfolge von list.label.fields
	 */
	public function zeile($row, $label)
	{
		$erledigt = !empty($row['erledigt']);
		$grau = static function (string $html) use ($erledigt): string {
			return $erledigt ? '<span style="color:#999">'.$html.'</span>' : $html;
		};

		$empfaenger = \Contao\StringUtil::specialchars((string) $row['empfaengerName']);

		if('admin' === (string) $row['empfaengerArt'])
		{
			$empfaenger .= ' <span class="wp-meta">(Admin)</span>';
		}

		if(empty($row['gesendet']))
		{
			$stand = '<span style="color:#c33;font-weight:bold" title="'.\Contao\StringUtil::specialchars((string) $row['fehler']).'">nicht verschickt</span>';
		}
		else
		{
			$stand = $erledigt ? 'erledigt' : 'offen';
		}

		return array
		(
			$grau(\Contao\Date::parse(\Contao\Config::get('datimFormat'), (int) $row['datum'])),
			$grau(\Contao\StringUtil::specialchars((string) $row['memberName'])),
			$grau($empfaenger),
			$grau(\Contao\StringUtil::specialchars((string) $row['betreff'])),
			$stand,
		);
	}
}
