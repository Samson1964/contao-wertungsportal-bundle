<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Tests\Classes;

use Schachbulle\ContaoWertungsportalBundle\Classes\InsertTags;

/**
 * InsertTags ohne Datenbank und ohne Contao.
 *
 * Ersetzt werden genau die drei Stellen, die nach außen greifen: Sperrprüfung,
 * Lesen der Person und Lesen der Einstellung. Alles andere — Zerlegen, Prüfen
 * der NU-Nummer, Merker, Ausgabe — läuft unverändert. Jeder Zugriff wird
 * mitgeschrieben, damit die Prüfungen auch zählen können, WIE OFT gelesen
 * wurde.
 */
class InsertTagsAttrappe extends InsertTags
{
	/**
	 * Personenbestand: NU-Nummer => Datensatz in der Form von laden().
	 *
	 * @var array
	 */
	public static $bestand = array();

	/**
	 * Gesperrte NU-Nummern als Schlüssel.
	 *
	 * @var array
	 */
	public static $sperrliste = array();

	/**
	 * Ersetzungen, die ersetzungen() liefert.
	 *
	 * @var array
	 */
	public static $einstellung = array();

	/**
	 * Ausnahme, die die Sperrprüfung wirft (Datenbank weg), oder null.
	 *
	 * @var \Throwable|null
	 */
	public static $fehler;

	/**
	 * Mitschrift der Sperrprüfungen (NU-Nummern in Aufrufreihenfolge).
	 *
	 * @var array
	 */
	public static $sperrpruefungen = array();

	/**
	 * Mitschrift der Lesezugriffe (NU-Nummern in Aufrufreihenfolge).
	 *
	 * @var array
	 */
	public static $lesezugriffe = array();

	/**
	 * Setzt Bestand, Mitschriften und den Merker des Seitenaufrufs zurück.
	 *
	 * Der Merker gehört der Elternklasse; ohne das Zurücksetzen sähe jede
	 * Prüfung die Personen der vorigen.
	 *
	 * @return void
	 */
	public static function zuruecksetzen(): void
	{
		self::$personen = array();
		self::$bestand = array();
		self::$sperrliste = array();
		self::$einstellung = InsertTags::VEREIN_ERSETZUNGEN;
		self::$fehler = null;
		self::$sperrpruefungen = array();
		self::$lesezugriffe = array();
	}

	/**
	 * Macht zerlegen() für die Prüfung der Tagzerlegung zugänglich.
	 *
	 * @param string $strTag Tag ohne Klammern
	 *
	 * @return array|null Ergebnis von zerlegen()
	 */
	public static function zerlegenOeffentlich($strTag)
	{
		return static::zerlegen($strTag);
	}

	/**
	 * Macht vereinsname() für die Prüfung von Ersetzung und Kürzung zugänglich.
	 *
	 * @param string $name        Vereinsname
	 * @param array  $ersetzungen Zeilen mit 'search' und 'replace'
	 * @param string $laenge      Höchstzahl der Zeichen
	 *
	 * @return string Ergebnis von vereinsname()
	 */
	public static function vereinsnameOeffentlich($name, $ersetzungen, $laenge)
	{
		return static::vereinsname($name, $ersetzungen, $laenge);
	}

	/**
	 * Sperrprüfung gegen die Sperrliste; wirft den eingestellten Fehler.
	 *
	 * @param string $nu Geprüfte NU-Nummer
	 *
	 * @return bool
	 */
	protected static function gesperrt($nu)
	{
		self::$sperrpruefungen[] = $nu;

		if(null !== self::$fehler) throw self::$fehler;

		return isset(self::$sperrliste[$nu]);
	}

	/**
	 * Liest die Person aus dem Bestand der Attrappe.
	 *
	 * @param string $nu Geprüfte NU-Nummer
	 *
	 * @return array|null
	 */
	protected static function laden($nu)
	{
		self::$lesezugriffe[] = $nu;

		return self::$bestand[$nu] ?? null;
	}

	/**
	 * Liefert die eingestellten Ersetzungen.
	 *
	 * @return array
	 */
	protected static function ersetzungen()
	{
		return self::$einstellung;
	}
}
