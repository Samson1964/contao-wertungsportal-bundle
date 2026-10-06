<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Verbindet die Wertungsreferenten mit der Adressverwaltung (ab 1.51.0).
 *
 * Ein Referent in tl_wertungsportal_referenten wird einem Datensatz aus
 * tl_adressen zugeordnet (Paket schachbulle/contao-adressen-bundle). Seit
 * 1.52.0 kommen Anschrift und Telefonnummern AUSSCHLIESSLICH von dort,
 * jeweils nur, soweit die Sichtbarkeitsschalter der Adressverwaltung sie
 * freigeben.
 *
 * Eigene Angaben des Referenten sind nur noch Name, nu-ID und eine
 * Funktions-E-Mail des Wertungsreferats (etwa dwz@verband.de). Sie ist seit
 * 1.54.0 die EINZIGE E-Mail-Adresse, die in Ausgaben erscheint, und sie
 * empfängt die Reklamationen. Die E-Mail-Adressen der Adresse (meist
 * private) werden nie gezeigt; sie dienen nur als Notanker für den Versand
 * und zum Wiedererkennen des Referenten von nu. Die alten Spalten telefon,
 * strasse, plz und ort stehen noch in der Tabelle, werden aber nirgends
 * mehr ausgegeben.
 *
 * Die Zuständigkeit (welche Verbände) wird nur im Wertungsportal gepflegt;
 * das Feld tl_adressen.wertungsreferent wird nicht gelesen.
 *
 * Das Adressen-Bundle ist KEINE Abhängigkeit. Fehlt es, bleibt das Feld aus
 * der Eingabemaske, und die Referenten erscheinen mit Name und
 * Funktions-E-Mail.
 *
 * Aufteilung: zusammenfuehren(), aktiv(), name() und ohneLeere() kommen ohne
 * Contao aus und sind in tests/Helper/AdressverknuepfungTest.php geprüft;
 * verfuegbar(), lade() und auswahl() lesen aus Contao.
 */
class Adressverknuepfung
{
	/**
	 * Name des Adressen-Bundles im Parameter kernel.bundles.
	 */
	public const BUNDLE = 'ContaoAdressenBundle';

	/**
	 * Spalten, die aus tl_adressen gelesen werden.
	 *
	 * Bewusst nicht SELECT *: Die Tabelle hat Textfelder und ein Bild, und
	 * Contaos Database\Result behält jede Zeile im Speicher. Wer im
	 * Adressen-Bundle eine dieser Spalten umbenennt, muss sie hier nachziehen.
	 */
	public const SPALTEN = 'id, nachname, vorname, titel, firma, plz, ort, ort_view, strasse, strasse_view,'
		.' telefon1, telefon2, telefon3, telefon4, telefon_view,'
		.' email1, email2, email3, email4, email5, email6, email_view, aktiv';

	/**
	 * Ergebnis von verfuegbar(), einmal je Aufruf ermittelt.
	 *
	 * @var bool|null
	 */
	protected static $verfuegbar = null;

	/**
	 * Schon gelesene Adressen: ID => Zeile, oder null für „gibt es nicht".
	 *
	 * @var array<int,array<string,mixed>|null>
	 */
	protected static $zwischenspeicher = array();

	// ═════════════════════════════════════════════════════════════════════
	//  Ohne Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Legt die Daten einer Adresse über einen Referenten.
	 *
	 * Das Ergebnis enthält die Kontaktdaten NUR aus der Adresse — die alten
	 * Spalten des Referenten (telefon, strasse, plz, ort) werden überschrieben,
	 * auch wenn keine Adresse zugeordnet ist, damit sie nirgends mehr
	 * durchsickern. Regeln im Einzelnen:
	 *
	 * - **Name**: aus der aktiven Adresse samt Titel, sofern dort ein Nachname
	 *   steht; sonst der eigene Name des Referenten.
	 * - **E-Mail-Adressen** (`emails`): AUSSCHLIESSLICH die Funktions-E-Mail
	 *   des Referenten (Spalte `email`). Die E-Mail-Adressen der Adresse
	 *   (email1…email6) erscheinen seit 1.54.0 in keiner Ausgabe mehr — es
	 *   sind meist private Adressen (Frank, 06.10.2026). Ohne Funktions-E-Mail
	 *   steht beim Referenten keine E-Mail-Adresse.
	 * - **Abgleich** (`abgleich`): Funktions-E-Mail und alle E-Mail-Adressen
	 *   der Adresse, auch nicht öffentliche — nur, um den Referenten von nu
	 *   als dieselbe Person zu erkennen (Helper\Zustaendigkeit::gleich). Nie
	 *   ausgeben.
	 * - **Telefonnummern** (`telefone`): alle belegten (telefon1…telefon4),
	 *   nur wenn `telefon_view` sie freigibt.
	 * - **Anschrift**: PLZ und Ort nur mit `ort_view`, die Straße nur, wenn
	 *   zusätzlich `strasse_view` gesetzt ist — wie im Adressen-Bundle
	 *   (Adressdaten::anschrift).
	 * - **Versandadresse** (`versandadresse`, für Reklamationen): die
	 *   Funktions-E-Mail; ohne sie die erste E-Mail-Adresse der Adresse, auch
	 *   eine nicht öffentliche. Sie erscheint nirgends auf der Seite.
	 * - **Inaktive oder fehlende Adresse**: keine Kontaktdaten außer der
	 *   Funktions-E-Mail; der Name bleibt der eigene.
	 *
	 * @param array<string,mixed>      $referent Zeile aus tl_wertungsportal_referenten
	 * @param array<string,mixed>|null $adresse  Zeile aus tl_adressen (Spalten wie
	 *                                           SPALTEN) oder null
	 *
	 * @return array<string,mixed> Der Referent mit `titel`, `emails` (Liste),
	 *                             `abgleich` (Liste), `telefone` (Liste),
	 *                             `strasse`, `plz`, `ort`, `versandadresse`
	 *                             und `ausAdressverwaltung` (true, wenn eine
	 *                             aktive Adresse eingeflossen ist)
	 */
	public static function zusammenfuehren(array $referent, ?array $adresse): array
	{
		$funktion = trim((string) ($referent['email'] ?? ''));

		$ergebnis = $referent;
		$ergebnis['titel'] = '';
		$ergebnis['emails'] = '' !== $funktion ? array($funktion) : array();
		$ergebnis['abgleich'] = $ergebnis['emails'];
		$ergebnis['telefone'] = array();
		$ergebnis['strasse'] = '';
		$ergebnis['plz'] = '';
		$ergebnis['ort'] = '';
		$ergebnis['versandadresse'] = $funktion;
		$ergebnis['ausAdressverwaltung'] = false;

		// Die alte Einzelspalte gibt es als Ausgabe nicht mehr
		unset($ergebnis['telefon']);

		if (null === $adresse || !self::aktiv($adresse)) {
			return $ergebnis;
		}

		$ergebnis['ausAdressverwaltung'] = true;

		if ('' !== self::wert($adresse, 'nachname')) {
			$ergebnis['nachname'] = self::wert($adresse, 'nachname');
			$ergebnis['vorname'] = self::wert($adresse, 'vorname');
			$ergebnis['titel'] = self::wert($adresse, 'titel');
		}

		// Die Adressen der Adresse dienen nur dem Abgleich und als Notanker
		// für den Versand — angezeigt wird allein die Funktions-E-Mail
		$mails = self::alle($adresse, 'email', 6);
		$ergebnis['abgleich'] = self::ohneDoppelte(array_merge($ergebnis['emails'], $mails));

		if ('' === $funktion && $mails) {
			$ergebnis['versandadresse'] = $mails[0];
		}

		if (self::oeffentlich($adresse, 'telefon_view')) {
			$ergebnis['telefone'] = self::alle($adresse, 'telefon', 4);
		}

		if (self::oeffentlich($adresse, 'ort_view')) {
			$ergebnis['plz'] = self::wert($adresse, 'plz');
			$ergebnis['ort'] = self::wert($adresse, 'ort');

			if (self::oeffentlich($adresse, 'strasse_view')) {
				$ergebnis['strasse'] = self::wert($adresse, 'strasse');
			}
		}

		return $ergebnis;
	}

	/**
	 * Prüft, ob eine Adresse in der Adressverwaltung als aktiv markiert ist.
	 *
	 * Die Spalte ist dort ein Boolean (tinyint); je nach Treiber kommt sie als
	 * 1, '1' oder true an.
	 *
	 * @param array<string,mixed> $adresse Zeile aus tl_adressen
	 *
	 * @return bool true bei aktiv; eine Zeile ohne die Spalte gilt als aktiv
	 */
	public static function aktiv(array $adresse): bool
	{
		if (!array_key_exists('aktiv', $adresse)) {
			return true;
		}

		return in_array($adresse['aktiv'], array(1, '1', true), true);
	}

	/**
	 * Bildet den Anzeigenamen einer Adresse für Auswahllisten.
	 *
	 * „Nachname, Vorname"; ohne Nachnamen die Firma, ohne beides „Adresse
	 * {ID}", damit keine leere Zeile in der Liste steht.
	 *
	 * @param array<string,mixed> $adresse Zeile aus tl_adressen
	 *
	 * @return string Name, nie leer
	 */
	public static function name(array $adresse): string
	{
		$name = implode(', ', self::ohneLeere(array(self::wert($adresse, 'nachname'), self::wert($adresse, 'vorname'))));

		if ('' === $name) {
			$name = self::wert($adresse, 'firma');
		}

		return '' !== $name ? $name : 'Adresse '.(int) ($adresse['id'] ?? 0);
	}

	/**
	 * Entfernt leere Zeichenketten aus einer Liste.
	 *
	 * Ersatz für array_filter($liste, 'strlen'): strlen() liefert eine Zahl,
	 * array_filter() erwartet eine Ja/Nein-Prüfung. Eine „0" bleibt erhalten
	 * — anders als bei array_filter() ohne Prüfung.
	 *
	 * @param array<int|string,string> $liste Zeichenketten
	 *
	 * @return array<int|string,string> Dieselbe Liste ohne leere Einträge,
	 *                                  Schlüssel bleiben erhalten
	 */
	public static function ohneLeere(array $liste): array
	{
		return array_filter($liste, static function (string $eintrag): bool {
			return '' !== $eintrag;
		});
	}

	/**
	 * Liefert alle belegten Werte einer Feldreihe (email1…email6,
	 * telefon1…telefon4) in ihrer Reihenfolge.
	 *
	 * @param array<string,mixed> $adresse Zeile aus tl_adressen
	 * @param string              $praefix Feldname ohne Nummer
	 * @param int                 $anzahl  Anzahl der Felder
	 *
	 * @return list<string> Werte ohne leere und doppelte, leer wenn alle leer sind
	 */
	protected static function alle(array $adresse, string $praefix, int $anzahl): array
	{
		$werte = array();

		for ($i = 1; $i <= $anzahl; ++$i) {
			$werte[] = self::wert($adresse, $praefix.$i);
		}

		return self::ohneDoppelte(self::ohneLeere($werte));
	}

	/**
	 * Entfernt Doppelte aus einer Liste, ohne Groß- und Kleinschreibung zu
	 * unterscheiden; der erste Eintrag bleibt stehen.
	 *
	 * @param array<int|string,string> $liste Zeichenketten
	 *
	 * @return list<string> Liste ohne Doppelte, neu durchnummeriert
	 */
	protected static function ohneDoppelte(array $liste): array
	{
		$gesehen = array();
		$ergebnis = array();

		foreach ($liste as $eintrag) {
			$schluessel = mb_strtolower($eintrag);

			if (!isset($gesehen[$schluessel])) {
				$gesehen[$schluessel] = true;
				$ergebnis[] = $eintrag;
			}
		}

		return $ergebnis;
	}

	/**
	 * Prüft einen der Sichtbarkeitsschalter der Adressverwaltung.
	 *
	 * Die Schalter sind char(1) mit Vorgabe '1'; fehlt die Spalte, gilt der
	 * Wert als öffentlich — so wie die Vorgabe dort.
	 *
	 * @param array<string,mixed> $adresse Zeile aus tl_adressen
	 * @param string              $feld    email_view, telefon_view, ort_view oder strasse_view
	 *
	 * @return bool true, wenn der Wert veröffentlicht werden darf
	 */
	protected static function oeffentlich(array $adresse, string $feld): bool
	{
		if (!array_key_exists($feld, $adresse)) {
			return true;
		}

		return in_array($adresse[$feld], array(1, '1', true), true);
	}

	/**
	 * Liefert einen Wert einer Adresse als gekürzte Zeichenkette.
	 *
	 * @param array<string,mixed> $adresse Zeile aus tl_adressen
	 * @param string              $feld    Spaltenname
	 *
	 * @return string Wert ohne Leerraum am Rand, '' wenn er fehlt
	 */
	protected static function wert(array $adresse, string $feld): string
	{
		return trim((string) ($adresse[$feld] ?? ''));
	}

	// ═════════════════════════════════════════════════════════════════════
	//  Mit Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Prüft, ob das Adressen-Bundle in dieser Installation geladen ist.
	 *
	 * Maßgeblich ist der Parameter kernel.bundles, nicht class_exists(): Das
	 * Paket kann im vendor-Ordner liegen, ohne registriert zu sein. Ob die
	 * Tabelle schon angelegt ist, prüfen die lesenden Methoden selbst.
	 *
	 * @return bool true, wenn das Bundle registriert ist; false auch dann,
	 *              wenn kein Container zur Verfügung steht
	 */
	public static function verfuegbar(): bool
	{
		if (null === self::$verfuegbar) {
			try {
				$bundles = \Contao\System::getContainer()->getParameter('kernel.bundles');
				self::$verfuegbar = is_array($bundles) && isset($bundles[self::BUNDLE]);
			} catch (\Throwable $e) {
				self::$verfuegbar = false;
			}
		}

		return self::$verfuegbar;
	}

	/**
	 * Liest Adressen zu einer Liste von IDs, mit Zwischenspeicher je Aufruf.
	 *
	 * Eine Abfrage für alle noch nicht bekannten IDs; auch „gibt es nicht"
	 * wird gemerkt. Fehlt das Bundle oder die Tabelle, kommt eine leere Liste
	 * zurück — die Referenten erscheinen dann mit Name und Funktions-E-Mail.
	 *
	 * @param array<int,int|string> $ids IDs aus tl_wertungsportal_referenten.adresse;
	 *                                   0 und Doppelte werden übergangen
	 *
	 * @return array<int,array<string,mixed>> ID => Zeile (Spalten wie SPALTEN),
	 *                                        nur für gefundene Adressen
	 */
	public static function lade(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if (!$ids || !self::verfuegbar()) {
			return array();
		}

		$fehlend = array_values(array_diff($ids, array_keys(self::$zwischenspeicher)));

		if ($fehlend) {
			try {
				// Nur Ganzzahlen, deshalb ohne Platzhalter ungefährlich
				$objAdressen = \Contao\Database::getInstance()->execute('SELECT '.self::SPALTEN.' FROM tl_adressen WHERE id IN ('.implode(',', $fehlend).')');

				while ($objAdressen->next()) {
					self::$zwischenspeicher[(int) $objAdressen->id] = $objAdressen->row();
				}
			} catch (\Throwable $e) {
				// Tabelle fehlt (Bundle registriert, aber noch kein migrate)
			}

			foreach ($fehlend as $id) {
				self::$zwischenspeicher[$id] = self::$zwischenspeicher[$id] ?? null;
			}
		}

		return array_filter(array_intersect_key(self::$zwischenspeicher, array_flip($ids)));
	}

	/**
	 * Liefert die Auswahlliste der Adressen für das Feld „Adresse".
	 *
	 * Alle Adressen, die aktiven zuerst, je nach Nachname und Vorname; der
	 * Ort hilft bei Namensgleichheit. Inaktive stehen mit dem Zusatz
	 * „inaktiv" dabei, damit eine schon gewählte Adresse auch dann noch in
	 * der Liste steht, wenn sie inzwischen abgeschaltet wurde.
	 *
	 * @return array<int,string> ID => Beschriftung; leer, wenn das Bundle oder
	 *                           die Tabelle fehlt
	 */
	public static function auswahl(): array
	{
		if (!self::verfuegbar()) {
			return array();
		}

		$optionen = array();

		try {
			$objAdressen = \Contao\Database::getInstance()->execute('SELECT id, nachname, vorname, firma, ort, aktiv FROM tl_adressen ORDER BY aktiv DESC, nachname, vorname, id');

			while ($objAdressen->next()) {
				$zeile = $objAdressen->row();
				$ort = trim((string) $zeile['ort']);

				$optionen[(int) $zeile['id']] = self::name($zeile)
					.('' !== $ort ? ' ('.$ort.')' : '')
					.(self::aktiv($zeile) ? '' : ' – inaktiv');
			}
		} catch (\Throwable $e) {
			return array();
		}

		return $optionen;
	}

	/**
	 * Setzt die Zwischenspeicher zurück. Nur für Prüfstände gedacht.
	 */
	public static function zuruecksetzen(): void
	{
		self::$verfuegbar = null;
		self::$zwischenspeicher = array();
	}
}
