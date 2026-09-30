<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Verbindet die Wertungsreferenten mit der Adressverwaltung (ab 1.51.0).
 *
 * Ein Referent in tl_wertungsportal_referenten kann einem Datensatz aus
 * tl_adressen zugeordnet werden (Paket schachbulle/contao-adressen-bundle).
 * Dann liefert die Adresse Name, E-Mail, Telefon und Anschrift — bei jeder
 * Ausgabe frisch, damit nichts doppelt gepflegt wird. Die eigenen Felder des
 * Referenten springen nur ein, wo die Adresse nichts liefert.
 *
 * Die Zuständigkeit (welche Verbände) wird dagegen NUR im Wertungsportal
 * gepflegt. Das Feld tl_adressen.wertungsreferent samt Frontend-Modul des
 * Adressen-Bundles ist veraltet (Frank, 30.09.2026) und wird nur einmal
 * gelesen: bei der Übernahme über uebernahmeplan().
 *
 * Das Adressen-Bundle ist KEINE Abhängigkeit. Fehlt es, bleibt das Feld aus
 * der Eingabemaske, und alles läuft wie vorher mit den eigenen Feldern.
 *
 * Aufteilung: zusammenfuehren(), aktiv(), name() und uebernahmeplan() kommen
 * ohne Contao aus und sind in tests/Helper/AdressverknuepfungTest.php
 * geprüft; verfuegbar(), lade() und auswahl() lesen aus Contao.
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
	 * Contaos Database\Result behält jede Zeile im Speicher.
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
	 * Regeln, Feld für Feld:
	 *
	 * - **Nicht aktiv** (Häkchen „aktiv" in der Adressverwaltung fehlt) oder
	 *   keine Adresse: Es bleibt alles, wie es im Referenten steht.
	 * - **Name**: aus der Adresse, sofern dort ein Nachname steht; der Titel
	 *   kommt dazu (Feld `titel`).
	 * - **E-Mail, Telefon, Anschrift**: aus der Adresse, sofern dort etwas
	 *   steht. Ist ein Wert in der Adressverwaltung als nicht öffentlich
	 *   markiert (`email_view`, `telefon_view`, `ort_view`, `strasse_view`),
	 *   bleibt er in der Ausgabe LEER — auch wenn im Referenten selbst etwas
	 *   stünde. Wer seine Nummer dort verbirgt, soll sie nicht über einen
	 *   Umweg doch veröffentlicht finden. Die eigenen Felder springen also nur
	 *   ein, wo die Adresse gar nichts hat.
	 * - **Versandadresse** (`versandadresse`): die erste E-Mail-Adresse der
	 *   Adresse, auch eine nicht öffentliche. Sie dient nur dem Versand von
	 *   Reklamationen (Helper\Reklamation) und erscheint nirgends auf der
	 *   Seite; der Referent erfährt so von der Reklamation, ohne daß seine
	 *   Adresse veröffentlicht wird.
	 * - **Anschrift** (Straße, PLZ, Ort) als Ganzes: Hat die Adresse irgendeinen
	 *   Teil davon, kommen alle drei von dort, damit sich keine Anschrift aus
	 *   zwei Quellen zusammensetzt. Die Straße erscheint nur, wenn auch PLZ
	 *   und Ort öffentlich sind — wie im Adressen-Bundle
	 *   (Adressdaten::anschrift).
	 *
	 * @param array<string,mixed>      $referent Zeile aus tl_wertungsportal_referenten
	 * @param array<string,mixed>|null $adresse  Zeile aus tl_adressen (Spalten wie
	 *                                           SPALTEN) oder null
	 *
	 * @return array<string,mixed> Der Referent mit überlagerten Feldern, dazu
	 *                             `titel`, `versandadresse` und
	 *                             `ausAdressverwaltung` (true, wenn eine aktive
	 *                             Adresse eingeflossen ist)
	 */
	public static function zusammenfuehren(array $referent, ?array $adresse): array
	{
		$ergebnis = $referent;
		$ergebnis['titel'] = '';
		$ergebnis['versandadresse'] = trim((string) ($referent['email'] ?? ''));
		$ergebnis['ausAdressverwaltung'] = false;

		if (null === $adresse || !self::aktiv($adresse)) {
			return $ergebnis;
		}

		$ergebnis['ausAdressverwaltung'] = true;

		if ('' !== self::wert($adresse, 'nachname')) {
			$ergebnis['nachname'] = self::wert($adresse, 'nachname');
			$ergebnis['vorname'] = self::wert($adresse, 'vorname');
			$ergebnis['titel'] = self::wert($adresse, 'titel');
		}

		$email = self::erster($adresse, 'email', 6);

		if ('' !== $email) {
			$ergebnis['versandadresse'] = $email;
			$ergebnis['email'] = self::oeffentlich($adresse, 'email_view') ? $email : '';
		}

		$telefon = self::erster($adresse, 'telefon', 4);

		if ('' !== $telefon) {
			$ergebnis['telefon'] = self::oeffentlich($adresse, 'telefon_view') ? $telefon : '';
		}

		// Die Anschrift kommt als Ganzes aus EINER Quelle — sonst stünde am Ende
		// die eigene Straße neben der PLZ aus der Adressverwaltung
		if ('' !== self::wert($adresse, 'plz') || '' !== self::wert($adresse, 'ort') || '' !== self::wert($adresse, 'strasse')) {
			$ortSichtbar = self::oeffentlich($adresse, 'ort_view');
			$ergebnis['plz'] = $ortSichtbar ? self::wert($adresse, 'plz') : '';
			$ergebnis['ort'] = $ortSichtbar ? self::wert($adresse, 'ort') : '';
			$ergebnis['strasse'] = $ortSichtbar && self::oeffentlich($adresse, 'strasse_view') ? self::wert($adresse, 'strasse') : '';
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
	 * Bildet den Anzeigenamen einer Adresse für Auswahllisten und Meldungen.
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
	 * Plant die einmalige Übernahme der Wertungsreferenten aus der
	 * Adressverwaltung.
	 *
	 * Grundlage ist das (veraltete) Feld tl_adressen.wertungsreferent, eine
	 * serialisierte Liste von Verbandsschlüsseln. Diese Schlüssel sind — bis
	 * auf wenige Ausnahmen — genau die Kennziffern von nu; übernommen wird
	 * nur, was unter den bekannten Verbänden steht.
	 *
	 * Regeln:
	 * - Inaktive Adressen und solche ohne Verband bleiben außen vor.
	 * - Eine Adresse, die schon einem Referenten zugeordnet ist, wird nicht
	 *   noch einmal angelegt — ein zweiter Lauf verdoppelt nichts. An
	 *   bestehenden Referenten ändert die Übernahme nichts.
	 * - Schlüssel, die es im Wertungsportal nicht gibt, werden gezählt und
	 *   gemeldet. Bleibt einer Person dadurch kein Verband, landet sie unter
	 *   `ohneVerband` — sie muss von Hand zugeordnet werden.
	 *
	 * @param array<int,array<string,mixed>> $adressen   Zeilen aus tl_adressen mit id, nachname,
	 *                                                   vorname, firma, aktiv, wertungsreferent
	 * @param array<int|string,string>       $verbaende  Bekannte Verbände: Kennziffer => Bezeichnung
	 *                                                   (rein numerische Kennziffern macht PHP zu
	 *                                                   Ganzzahlschlüsseln; isset() findet sie trotzdem)
	 * @param array<int,int|string>          $verknuepft IDs von Adressen, die schon einem
	 *                                                   Referenten zugeordnet sind
	 *
	 * @return array{neu: list<array{adresse:int,name:string,nachname:string,vorname:string,verbaende:list<string>,unbekannt:list<string>}>,
	 *               vorhanden: list<array{adresse:int,name:string}>,
	 *               ohneVerband: list<array{adresse:int,name:string,unbekannt:list<string>}>,
	 *               inaktiv: int,
	 *               unbekannt: array<string,int>}
	 *               `unbekannt` zählt je nicht passendem Schlüssel die Personen
	 */
	public static function uebernahmeplan(array $adressen, array $verbaende, array $verknuepft): array
	{
		$plan = array('neu' => array(), 'vorhanden' => array(), 'ohneVerband' => array(), 'inaktiv' => 0, 'unbekannt' => array());
		$verknuepft = array_flip(array_map('intval', $verknuepft));

		foreach ($adressen as $adresse) {
			$schluessel = self::schluessel($adresse['wertungsreferent'] ?? null);

			if (!$schluessel) {
				continue;
			}

			if (!self::aktiv($adresse)) {
				++$plan['inaktiv'];

				continue;
			}

			$id = (int) ($adresse['id'] ?? 0);
			$name = self::name($adresse);

			if (isset($verknuepft[$id])) {
				$plan['vorhanden'][] = array('adresse' => $id, 'name' => $name);

				continue;
			}

			$passend = array();
			$unbekannt = array();

			foreach ($schluessel as $vkz) {
				if (isset($verbaende[$vkz])) {
					$passend[] = $vkz;
				} else {
					$unbekannt[] = $vkz;
					$plan['unbekannt'][$vkz] = ($plan['unbekannt'][$vkz] ?? 0) + 1;
				}
			}

			if (!$passend) {
				$plan['ohneVerband'][] = array('adresse' => $id, 'name' => $name, 'unbekannt' => $unbekannt);

				continue;
			}

			$plan['neu'][] = array(
				'adresse'   => $id,
				'name'      => $name,
				// Für Sortierung und Suche der Backend-Liste; ausgegeben wird der
				// Name ohnehin aus der Adresse
				'nachname'  => '' !== self::wert($adresse, 'nachname') ? self::wert($adresse, 'nachname') : $name,
				'vorname'   => self::wert($adresse, 'vorname'),
				'verbaende' => $passend,
				'unbekannt' => $unbekannt,
			);
		}

		ksort($plan['unbekannt'], SORT_STRING);

		return $plan;
	}

	/**
	 * Liest die Verbandsschlüssel aus tl_adressen.wertungsreferent.
	 *
	 * Das Feld ist eine serialisierte Liste (checkboxWizard), kann aber auch
	 * NULL, leer oder kaputt sein. Objekte werden beim Entpacken nie erzeugt.
	 *
	 * @param mixed $wert Rohwert aus der Datenbank
	 *
	 * @return list<string> Schlüssel ohne leere und doppelte Einträge, als
	 *                      Zeichenketten (führende Nullen bleiben erhalten)
	 */
	protected static function schluessel($wert): array
	{
		if (!is_string($wert) || '' === $wert) {
			return array();
		}

		$liste = @unserialize($wert, array('allowed_classes' => false));

		if (!is_array($liste)) {
			return array();
		}

		$liste = array_map(static function ($eintrag): string {
			return trim((string) $eintrag);
		}, array_filter($liste, 'is_scalar'));

		return array_values(array_unique(self::ohneLeere($liste)));
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
	 * Liefert den ersten nicht leeren Wert einer Feldreihe (email1…email6,
	 * telefon1…telefon4).
	 *
	 * @param array<string,mixed> $adresse Zeile aus tl_adressen
	 * @param string              $praefix Feldname ohne Nummer
	 * @param int                 $anzahl  Anzahl der Felder
	 *
	 * @return string Wert, '' wenn alle leer sind
	 */
	protected static function erster(array $adresse, string $praefix, int $anzahl): string
	{
		for ($i = 1; $i <= $anzahl; ++$i) {
			$wert = self::wert($adresse, $praefix.$i);

			if ('' !== $wert) {
				return $wert;
			}
		}

		return '';
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
	 * zurück — die Referenten laufen dann mit ihren eigenen Feldern.
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
