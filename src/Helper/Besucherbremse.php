<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Bremst Massenabfragen einzelner Besucher.
 *
 * Anlass: Die Wertungsportal-Seiten sind für Menschen gedacht. Wer sie
 * seitenweise abklappert, belastet die Schnittstelle des DSB und zieht sich
 * nebenbei den ganzen Personenbestand — dafür gibt es die
 * Vereinslisten-Schnittstelle mit Zugangsschlüssel.
 *
 * Gezählt wird je IP-Adresse in drei Fenstern (Minute, Stunde, Tag); die
 * Höchstwerte stehen in den Contao-Einstellungen, 0 schaltet das jeweilige
 * Fenster ab. Wer eine Grenze reißt, bekommt für den Rest des Fensters keine
 * Daten mehr und wird protokolliert — mit Adresse, Browserkennung und, falls
 * angemeldet, Kennung und Namen des Mitglieds. Der Mitgliedsbezug ist der
 * Punkt: Manche Bots rufen angemeldet ab.
 *
 * **Gezählt wird je Seitenaufruf, nicht je Schnittstellenabfrage.** Eine
 * Turnierseite löst mehrere Abfragen aus (Kopfdaten, Ergebnisse …) — würde
 * jede einzeln zählen, träfe die Bremse ausgerechnet die aufwendigen Seiten
 * zuerst, und die Grenzen wären für einen Menschen nicht mehr abschätzbar.
 */
class Besucherbremse
{
	/**
	 * Merker, dass dieser Seitenaufruf bereits gezählt wurde.
	 * @var bool
	 */
	protected static $gezaehlt = false;

	/**
	 * Ergebnis der Prüfung für diesen Seitenaufruf: false = frei, sonst der
	 * Name des gerissenen Fensters.
	 * @var false|string
	 */
	protected static $gesperrt = false;

	/**
	 * Meldung, die der Besucher statt der Daten zu sehen bekommt.
	 */
	const MELDUNG = 'Es wurden zu viele Abfragen von dieser Adresse gestellt. Bitte versuchen Sie es später erneut.';

	/**
	 * Prüft den laufenden Seitenaufruf und meldet, ob er gesperrt ist.
	 *
	 * Beim ersten Aufruf innerhalb eines Seitenaufrufs wird gezählt und
	 * entschieden, danach steht das Ergebnis fest. Genau deshalb darf die
	 * Methode beliebig oft aufgerufen werden — etwa aus jeder Abfrage heraus.
	 *
	 * @return bool true = dieser Besucher ist gerade gebremst
	 */
	public static function gesperrt()
	{
		if(self::$gezaehlt) return self::$gesperrt !== false;

		self::$gezaehlt = true;

		$grenzen = self::grenzen();

		// Ohne eingestellte Grenzen bleibt alles wie bisher — dann wird auch
		// nichts gezählt und nichts gespeichert
		if(!count($grenzen)) return false;

		$ip = self::adresse();

		if($ip === '') return false;

		$stand = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalBesucherModel::zaehle($ip);

		foreach($grenzen as $fenster => $grenze)
		{
			if(($stand[$fenster] ?? 0) <= $grenze) continue;

			self::$gesperrt = $fenster;

			$mitglied = self::mitglied();

			\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalSperrenModel::protokolliere(
				$ip,
				$fenster,
				(int) $stand[$fenster],
				(int) $grenze,
				self::browserkennung(),
				$mitglied['id'],
				$mitglied['name']
			);

			return true;
		}

		return false;
	}

	/**
	 * Liefert die eingestellten Höchstwerte je Fenster.
	 *
	 * Fenster ohne Wert oder mit 0 bleiben außen vor — so lässt sich die
	 * Bremse einzeln oder ganz abschalten, ohne Code zu ändern.
	 *
	 * @return array fenster => Höchstwert (nur eingeschaltete)
	 */
	public static function grenzen()
	{
		$grenzen = array();

		foreach(array('minute', 'stunde', 'tag') as $fenster)
		{
			$wert = (int) ($GLOBALS['TL_CONFIG']['wertungsportal_limit_'.$fenster] ?? 0);

			if($wert > 0) $grenzen[$fenster] = $wert;
		}

		return $grenzen;
	}

	/**
	 * Liefert die IP-Adresse des Besuchers.
	 *
	 * Contao kürzt die Adresse je nach Einstellung (Datenschutz); genau die
	 * gekürzte Fassung wird auch hier verwendet, damit nicht an Contao vorbei
	 * eine genauere Adresse gespeichert wird als der Betreiber zulässt.
	 *
	 * @return string
	 */
	protected static function adresse()
	{
		try
		{
			return (string) \Environment::get('ip');
		}
		catch(\Throwable $e)
		{
			return '';
		}
	}

	/**
	 * Liefert die Browserkennung des Besuchers.
	 *
	 * @return string
	 */
	protected static function browserkennung()
	{
		try
		{
			return (string) \Environment::get('httpUserAgent');
		}
		catch(\Throwable $e)
		{
			return '';
		}
	}

	/**
	 * Ermittelt das angemeldete Mitglied.
	 *
	 * @return array id (0 = nicht angemeldet) und name
	 */
	protected static function mitglied()
	{
		try
		{
			$objUser = \FrontendUser::getInstance();

			if($objUser === null || !$objUser->id) return array('id' => 0, 'name' => '');

			return array('id' => (int) $objUser->id, 'name' => (string) $objUser->username);
		}
		catch(\Throwable $e)
		{
			return array('id' => 0, 'name' => '');
		}
	}

	/**
	 * Setzt den Merker zurück. Nur für Prüfstände gedacht — im Betrieb gilt
	 * die Entscheidung für den ganzen Seitenaufruf.
	 *
	 * @return void
	 */
	public static function zuruecksetzen()
	{
		self::$gezaehlt = false;
		self::$gesperrt = false;
	}
}
