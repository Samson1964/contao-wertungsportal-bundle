<?php

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

use Schachbulle\ContaoWertungsportalBundle\Helper\Adressverknuepfung;

/**
 * Rückrufe des Backend-Moduls „Referenten".
 *
 * Liefert die Auswahllisten der Verbände und der Adressen, baut die Zeilen
 * der Übersicht und hält bei einer zugeordneten Adresse den gespeicherten
 * Namen aktuell (ab 1.51.0, siehe Helper\Adressverknuepfung).
 */
class Referenten extends \Contao\Backend
{
	/**
	 * Zwischenspeicher der Verbandsliste für den laufenden Aufruf.
	 * @var array|null
	 */
	protected static $verbaende = null;

	/**
	 * Wurden die Adressen der Listenansicht schon auf einmal geladen?
	 * @var bool
	 */
	protected static $adressenGeladen = false;

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
	 * Der DSB selbst (00000) steht immer oben (ab 1.51.0). Die Zuständigkeit
	 * geht bis zu ihm hinauf (Helper::vkzKette) — ohne den Eintrag ließ sich
	 * kein Referent des Bundes zuordnen. Seine Beschriftung ist FEST: Auf
	 * schachbund.de stand im Vereinsbestand unter 00000 ein gewöhnlicher
	 * Verein (SC Lörzweiler, 1.53.0) und überschrieb sie. Ein Eintrag 00000 im
	 * Vereinsbestand wird deshalb nicht gelesen.
	 *
	 * @param  \DataContainer|null $dc Von Contao übergeben, hier ungenutzt
	 * @return array                   VKZ => „VKZ Name", nach VKZ geordnet
	 */
	public static function getVerbaende($dc = null)
	{
		if(self::$verbaende !== null) return self::$verbaende;

		self::$verbaende = array('00000' => '00000 Deutscher Schachbund');

		try
		{
			// LIKE '%00' entspricht Helper::istVerband(); die beiden
			// Sonderfälle L0001/M0001 kommen ausdrücklich dazu. 00000 ist
			// der DSB und kommt nie aus dem Vereinsbestand (siehe oben)
			$objVerbaende = \Contao\Database::getInstance()->execute("SELECT clubVkz, clubName FROM tl_wertungsportal_clubs WHERE (clubVkz LIKE '%00' OR clubVkz IN ('L0001','M0001')) AND clubVkz <> '00000' ORDER BY clubVkz");

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

		$name = \Contao\StringUtil::specialchars($name).self::adresshinweis((int) ($row['adresse'] ?? 0));
		$vkz = \Contao\StringUtil::deserialize($row['verbaende'] ?? null, true);

		if(!count($vkz)) return $name.' <span class="wp-meta">(kein Verband zugeordnet)</span>';

		$alle = self::getVerbaende();
		$namen = array();

		// Höchstens drei Namen ausschreiben, sonst wird die Zeile unlesbar
		foreach(array_slice($vkz, 0, 3) as $eine)
		{
			$namen[] = $alle[$eine] ?? $eine;
		}

		$text = implode(', ', $namen);
		if(count($vkz) > 3) $text .= ' und '.(count($vkz) - 3).' weitere';

		return $name.' <span class="wp-meta">'.\Contao\StringUtil::specialchars($text).'</span>';
	}

	/**
	 * Baut den Hinweis zur zugeordneten Adresse für eine Listenzeile.
	 *
	 * Beim ersten Aufruf werden alle Adressen der Tabelle mit einer Abfrage
	 * geladen, statt je Zeile eine zu stellen.
	 *
	 * @param  int    $id ID aus tl_wertungsportal_referenten.adresse, 0 für keine
	 * @return string     HTML: ' [Adressverwaltung]' in Grau, ein roter Hinweis,
	 *                    wenn die Adresse inaktiv ist oder fehlt, sonst ''
	 */
	protected static function adresshinweis($id)
	{
		if($id < 1 || !Adressverknuepfung::verfuegbar()) return '';

		if(!self::$adressenGeladen)
		{
			self::$adressenGeladen = true;

			try
			{
				$ids = \Contao\Database::getInstance()->execute("SELECT adresse FROM tl_wertungsportal_referenten WHERE adresse > 0")->fetchEach('adresse');
				Adressverknuepfung::lade($ids);
			}
			catch(\Throwable $e)
			{
				// Dann eben einzeln — lade() unten fragt nach
			}
		}

		$adresse = Adressverknuepfung::lade(array($id))[$id] ?? null;

		if($adresse === null) return ' <span class="tl_red" title="Die zugeordnete Adresse gibt es in der Adressverwaltung nicht mehr. Es gelten die eigenen Angaben.">[Adresse fehlt]</span>';
		if(!Adressverknuepfung::aktiv($adresse)) return ' <span class="tl_red" title="Die zugeordnete Adresse ist in der Adressverwaltung nicht aktiv. Es gelten die eigenen Angaben.">[Adresse inaktiv]</span>';

		return ' <span class="wp-meta" title="Name und Kontaktdaten kommen aus der Adressverwaltung">[Adressverwaltung]</span>';
	}

	/**
	 * options_callback des Feldes „Adresse": alle Adressen der Adressverwaltung.
	 *
	 * @param  \Contao\DataContainer|null $dc Von Contao übergeben, hier ungenutzt
	 * @return array                          ID => „Nachname, Vorname (Ort)"
	 */
	public static function getAdressen($dc = null)
	{
		return Adressverknuepfung::auswahl();
	}

	/**
	 * wizard des Feldes „Adresse": Link, der die gewählte Adresse in der
	 * Adressverwaltung in einem Fenster öffnet.
	 *
	 * Gedacht zum Nachsehen und Berichtigen — die Daten kommen ja von dort.
	 * Der Link zeigt auf die gespeicherte Adresse; wer die Auswahl ändert,
	 * sieht die neue erst nach dem Speichern. Das Anfragetoken (rt) braucht
	 * Contao 4.13, sonst landet der Aufruf auf der Bestätigungsseite.
	 *
	 * @param  \Contao\DataContainer $dc Datencontainer mit dem Feldwert in value
	 * @return string                    HTML des Links, '' ohne Adresse
	 */
	public static function adresseBearbeiten($dc)
	{
		$id = (int) ($dc->value ?? 0);

		if($id < 1 || !Adressverknuepfung::verfuegbar()) return '';

		try
		{
			$container = \Contao\System::getContainer();
			$url = $container->get('router')->generate('contao_backend', array
			(
				'do'    => 'adressen',
				'act'   => 'edit',
				'id'    => $id,
				'popup' => 1,
				'nb'    => 1,
				'rt'    => (string) $container->get('contao.csrf.token_manager')->getDefaultTokenValue(),
			));
		}
		catch(\Throwable $e)
		{
			return '';
		}

		$titel = 'Adresse in der Adressverwaltung öffnen (ID '.$id.')';

		return ' <a href="'.\Contao\StringUtil::specialchars($url).'" title="'.\Contao\StringUtil::specialchars($titel).'"'
			.' onclick="Backend.openModalIframe({\'title\':\'Adresse bearbeiten\',\'url\':this.href});return false">'
			.\Contao\Image::getHtml('edit.svg', $titel).'</a>';
	}

	/**
	 * onload_callback: Mit zugeordneter Adresse ist der Nachname kein Pflichtfeld.
	 *
	 * Der Name kommt dann aus der Adresse und wird beim Speichern eingetragen
	 * (nameUebernehmen). Maßgeblich ist beim Absenden der gerade gewählte
	 * Wert, sonst der gespeicherte. Nur in der Einzelbearbeitung — bei
	 * „Mehrere bearbeiten" tragen die Felder andere Namen, und dort steht der
	 * Name ohnehin schon im Datensatz.
	 *
	 * @param  \Contao\DataContainer|null $dc Datencontainer
	 * @return void
	 */
	public static function pflichtfelder($dc = null)
	{
		if(\Contao\Input::get('act') !== 'edit' || !Adressverknuepfung::verfuegbar()) return;

		if(\Contao\Input::post('FORM_SUBMIT') === 'tl_wertungsportal_referenten')
		{
			$adresse = (int) \Contao\Input::post('adresse');
		}
		else
		{
			try
			{
				$adresse = (int) \Contao\Database::getInstance()->prepare("SELECT adresse FROM tl_wertungsportal_referenten WHERE id = ?")->execute((int) ($dc->id ?? 0))->adresse;
			}
			catch(\Throwable $e)
			{
				$adresse = 0;
			}
		}

		if($adresse > 0)
		{
			$GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['fields']['nachname']['eval']['mandatory'] = false;
		}
	}

	/**
	 * onsubmit_callback: trägt den Namen aus der zugeordneten Adresse ein.
	 *
	 * Ausgegeben wird der Name ohnehin frisch aus der Adresse
	 * (Referentenbaum). Gespeichert wird er trotzdem, damit die Backend-Liste
	 * richtig sortiert und die Suche ihn findet. Eine inaktive oder fehlende
	 * Adresse ändert nichts.
	 *
	 * @param  \Contao\DataContainer|null $dc Datencontainer des gespeicherten Satzes
	 * @return void
	 */
	public static function nameUebernehmen($dc = null)
	{
		$id = (int) ($dc->id ?? 0);

		if($id < 1 || !Adressverknuepfung::verfuegbar()) return;

		$db = \Contao\Database::getInstance();
		$adresseId = (int) $db->prepare("SELECT adresse FROM tl_wertungsportal_referenten WHERE id = ?")->execute($id)->adresse;
		$adresse = Adressverknuepfung::lade(array($adresseId))[$adresseId] ?? null;

		if($adresse === null || !Adressverknuepfung::aktiv($adresse) || trim((string) $adresse['nachname']) === '') return;

		$db->prepare("UPDATE tl_wertungsportal_referenten SET nachname = ?, vorname = ? WHERE id = ?")
		   ->execute(trim((string) $adresse['nachname']), trim((string) $adresse['vorname']), $id);
	}
}
