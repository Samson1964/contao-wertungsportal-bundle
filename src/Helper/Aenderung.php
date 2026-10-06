<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Änderungsmeldungen angemeldeter Mitglieder an den DSB-Admin (ab 1.54.0):
 *
 * - **Foto ändern** (Karteikarte): Betreff, Bilddatei, Nachricht (optional).
 * - **Logo/Infos ändern** (Verein): Betreff, Logo (optional), Homepage,
 *   „Über den Verein" (HTML aus TinyMCE), Nachricht (optional).
 *
 * Beide ersetzen die früheren mailto-Links „Foto senden" und „Logo senden".
 * Sie erscheinen nur für angemeldete Mitglieder, öffnen ein Formular wie die
 * Reklamation und gehen per fetch an `POST /wertungsportal-api/aenderung`.
 * Empfänger ist immer der DSB-Admin; das Mitglied bekommt auf Wunsch eine
 * Kopie (Cc) an die Adresse aus seinem Konto. Der Admin überträgt die
 * Änderung von Hand — das Bundle ändert an Spielern und Vereinen nichts.
 *
 * Geerbt von Reklamation: der signierte Kontext (das Formular kann weder
 * Empfänger noch Spieler oder Verein fälschen), Mitglied, Admin, Absender,
 * Bremse (HOECHSTZAHL je ZEITFENSTER, gemeinsam mit den Reklamationen) und
 * die Ablage im Backend-Modul „Reklamationen".
 *
 * Zur Datei:
 * - Nur Bilder (DATEITYPEN), erkannt am INHALT (finfo, getimagesize), nicht
 *   an Endung oder Angabe des Browsers; höchstens DATEI_GROESSE.
 * - Sie wird nirgends abgelegt, sondern aus dem Upload-Zwischenspeicher an
 *   die Mail gehängt — unter einem Namen, den das Bundle bildet
 *   (Foto-NU….jpg), nicht unter dem des Absenders.
 *
 * Zum HTML: „Über den Verein" läuft durch bereinigeHtml() (feste Liste
 * erlaubter Elemente, keine Skripte, Verweise nur http/https/mailto) und geht
 * als QUELLTEXT in eine reine Textmail — es wird nirgends als HTML gerendert.
 *
 * „Wird nichts verändert, wird auch nichts abgeschickt" (Frank, 06.10.2026):
 * siehe vereinsaenderungen().
 *
 * Aufteilung: Kontexte, Prüfungen, Bereinigung und Texte kommen ohne Contao
 * aus (tests/Helper/AenderungTest.php); Links, Dialoge, Versand brauchen es.
 */
class Aenderung extends Reklamation
{
	/**
	 * Name der Route des Controllers.
	 */
	public const ROUTE_AENDERUNG = 'wertungsportal_aenderung';

	/**
	 * Größte zulässige Datei in Bytes (5 MB). Gilt zusätzlich zur Grenze des
	 * Servers (upload_max_filesize), siehe hoechstgroesse().
	 */
	public const DATEI_GROESSE = 5242880;

	/**
	 * Zulässige Bildformate: MIME-Typ (am Inhalt erkannt) => Dateiendung.
	 * SVG fehlt mit Absicht — es kann Skripte enthalten.
	 */
	public const DATEITYPEN = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);

	/**
	 * Höchstlänge der freien Nachricht.
	 */
	public const NACHRICHT_LAENGE = 4000;

	/**
	 * Höchstlänge von „Über den Verein" (HTML-Quelltext).
	 */
	public const INFO_LAENGE = 20000;

	/**
	 * In „Über den Verein" erlaubte Elemente. Alles andere wird ausgepackt
	 * (der Text bleibt) oder, wenn es in VERBOTEN steht, samt Inhalt entfernt.
	 */
	public const ERLAUBT = array('p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote', 'sub', 'sup', 'hr');

	/**
	 * Elemente, die samt Inhalt verschwinden.
	 */
	public const VERBOTEN = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'svg', 'math', 'template', 'noscript', 'video', 'audio', 'img', 'base', 'head', 'title');

	/**
	 * Welche Dialoge auf dieser Seite schon ausgegeben wurden.
	 *
	 * @var array<string,bool>
	 */
	protected static $dialoge = array();

	// ═════════════════════════════════════════════════════════════════════
	//  Kontexte, Prüfungen, Texte — ohne Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Baut den Kontext für „Foto ändern" auf der Karteikarte.
	 *
	 * @param string $name Vor- und Nachname des Spielers
	 * @param string $nuId NU-Nummer
	 * @param string $url  Vollständige Adresse der Seite
	 *
	 * @return array<string,mixed> Kontext zum Signieren
	 */
	public static function fuerFoto(string $name, string $nuId, string $url): array
	{
		$name = self::zeile($name);
		$nuId = self::zeile($nuId);

		return array(
			'bereich'    => 'foto',
			'gegenstand' => 'Neues Foto für '.$name.('' !== $nuId ? ' ('.$nuId.')' : ''),
			'turnier'    => array(),
			'spieler'    => array('id' => $nuId, 'name' => $name),
			'url'        => $url,
		);
	}

	/**
	 * Baut den Kontext für „Logo/Infos ändern" auf der Vereinsseite.
	 *
	 * Die bisherige Homepage und eine Prüfsumme der bisherigen Vereinsinfo
	 * reisen signiert mit — daran erkennt der Server, ob sich etwas geändert
	 * hat, ohne dem Formular zu glauben.
	 *
	 * @param string $name     Vereinsname
	 * @param string $vkz      Kennziffer des Vereins
	 * @param string $url      Vollständige Adresse der Seite
	 * @param string $homepage Bisherige Homepage, '' wenn keine hinterlegt ist
	 * @param string $info     Bisheriger Text „Über den Verein" (HTML)
	 *
	 * @return array<string,mixed> Kontext zum Signieren
	 */
	public static function fuerVereinsdaten(string $name, string $vkz, string $url, string $homepage, string $info): array
	{
		$name = self::zeile($name);
		$vkz = self::zeile($vkz);

		return array(
			'bereich'    => 'vereinsdaten',
			'gegenstand' => 'Änderung der Vereinsdaten: '.$name.('' !== $vkz ? ' ('.$vkz.')' : ''),
			'turnier'    => array(),
			'spieler'    => array(),
			'verein'     => array('vkz' => $vkz, 'name' => $name),
			'homepage'   => trim($homepage),
			'info'       => sha1(self::normalHtml($info)),
			'url'        => $url,
		);
	}

	/**
	 * Prüft eine hochgeladene Datei.
	 *
	 * @param array<string,mixed>|null $datei      Angaben zur Datei: fehler (UPLOAD_ERR_*),
	 *                                             groesse (Bytes), mime (am Inhalt erkannt),
	 *                                             breite, hoehe (Pixel); null = keine Datei
	 * @param bool                     $pflicht    true: ohne Datei gibt es einen Fehler
	 * @param int                      $hoechstens Zulässige Größe in Bytes
	 *
	 * @return string|null Fehlermeldung für das Formular, null wenn alles
	 *                     stimmt (auch: keine Datei, wo keine nötig ist)
	 */
	public static function dateifehler(?array $datei, bool $pflicht, int $hoechstens): ?string
	{
		$fehler = null === $datei ? UPLOAD_ERR_NO_FILE : (int) ($datei['fehler'] ?? UPLOAD_ERR_NO_FILE);

		if (UPLOAD_ERR_NO_FILE === $fehler) {
			return $pflicht ? 'Bitte wählen Sie eine Bilddatei aus.' : null;
		}

		$zuGross = 'Die Datei ist zu groß — höchstens '.self::groesse($hoechstens).'.';

		if (UPLOAD_ERR_INI_SIZE === $fehler || UPLOAD_ERR_FORM_SIZE === $fehler) {
			return $zuGross;
		}

		if (UPLOAD_ERR_OK !== $fehler) {
			return 'Die Datei konnte nicht hochgeladen werden. Bitte versuchen Sie es noch einmal.';
		}

		$groesse = (int) ($datei['groesse'] ?? 0);

		if ($groesse < 1) {
			return 'Die Datei ist leer.';
		}

		if ($groesse > $hoechstens) {
			return $zuGross;
		}

		if (!isset(self::DATEITYPEN[(string) ($datei['mime'] ?? '')]) || (int) ($datei['breite'] ?? 0) < 1 || (int) ($datei['hoehe'] ?? 0) < 1) {
			return 'Bitte wählen Sie ein Bild im Format JPEG, PNG, GIF oder WebP aus.';
		}

		return null;
	}

	/**
	 * Bildet den Dateinamen des Anhangs — aus dem Kontext, nicht aus dem Namen
	 * des Absenders: „Foto-NU4481210.jpg" bzw. „Logo-64231.png".
	 *
	 * @param array<string,mixed> $kontext Kontext (bereich, spieler oder verein)
	 * @param string              $mime    Am Inhalt erkannter MIME-Typ
	 *
	 * @return string Dateiname aus Buchstaben, Ziffern, Binde- und Unterstrich
	 */
	public static function anhangname(array $kontext, string $mime): string
	{
		$endung = self::DATEITYPEN[$mime] ?? 'bin';

		if ('vereinsdaten' === ($kontext['bereich'] ?? '')) {
			$kennung = (string) ($kontext['verein']['vkz'] ?? '');
			$art = 'Logo';
		} else {
			$kennung = (string) ($kontext['spieler']['id'] ?? '');
			$art = 'Foto';
		}

		$kennung = (string) preg_replace('/[^A-Za-z0-9_-]/', '', $kennung);

		return $art.('' !== $kennung ? '-'.$kennung : '').'.'.$endung;
	}

	/**
	 * Prüft und vereinheitlicht eine Homepage-Adresse.
	 *
	 * Leer ist erlaubt (die Homepage soll verschwinden). Fehlt das Protokoll,
	 * wird https:// ergänzt — „www.verein.de" tippt jeder so ein. Zugelassen
	 * sind nur http und https.
	 *
	 * @param string $wert Eingabe
	 *
	 * @return array{wert:string,fehler:string|null} Bereinigte Adresse und
	 *                                               Fehlermeldung oder null
	 */
	public static function bereinigeHomepage(string $wert): array
	{
		$wert = trim(self::zeile($wert));

		if ('' === $wert) {
			return array('wert' => '', 'fehler' => null);
		}

		if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $wert)) {
			$wert = 'https://'.$wert;
		}

		$schema = strtolower((string) parse_url($wert, PHP_URL_SCHEME));

		if (mb_strlen($wert) > 255 || !in_array($schema, array('http', 'https'), true) || false === filter_var($wert, FILTER_VALIDATE_URL) || false === strpos((string) parse_url($wert, PHP_URL_HOST), '.')) {
			return array('wert' => $wert, 'fehler' => 'Bitte geben Sie die Homepage als vollständige Adresse an, zum Beispiel https://www.verein.de.');
		}

		return array('wert' => $wert, 'fehler' => null);
	}

	/**
	 * Säubert HTML aus dem Editor „Über den Verein".
	 *
	 * Erlaubt sind die Elemente aus ERLAUBT ohne Attribute; an Verweisen
	 * bleiben href (nur http, https, mailto) und title. Elemente aus VERBOTEN
	 * verschwinden samt Inhalt, Kommentare ebenso; unbekannte Elemente werden
	 * ausgepackt, ihr Text bleibt. Gearbeitet wird am Dokumentbaum
	 * (DOMDocument), nicht mit regulären Ausdrücken — verschachtelte oder
	 * kaputte Auszeichnung lässt sich nur so verlässlich behandeln.
	 *
	 * @param string $html HTML aus dem Formular
	 *
	 * @return string Gesäubertes HTML, '' bei leerer oder unlesbarer Eingabe
	 */
	public static function bereinigeHtml(string $html): string
	{
		if (!mb_check_encoding($html, 'UTF-8')) {
			return '';
		}

		$html = trim(mb_substr((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html), 0, self::INFO_LAENGE));

		if ('' === $html) {
			return '';
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$vorher = libxml_use_internal_errors(true);
		// Die XML-Angabe vorn sorgt dafür, dass libxml die Eingabe als UTF-8 liest
		$geladen = $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($vorher);

		$wurzel = $geladen ? $dom->getElementsByTagName('div')->item(0) : null;

		if (null === $wurzel) {
			return '';
		}

		self::saeubere($wurzel);

		$ergebnis = '';

		foreach ($wurzel->childNodes as $kind) {
			$ergebnis .= $dom->saveHTML($kind);
		}

		return trim($ergebnis);
	}

	/**
	 * Säubert die Kinder eines Knotens nach den Regeln von bereinigeHtml().
	 *
	 * @param \DOMNode $knoten Knoten, dessen Kinder geprüft werden
	 *
	 * @return void
	 */
	protected static function saeubere(\DOMNode $knoten): void
	{
		// Erst einsammeln: Die Liste ändert sich, während Knoten verschwinden
		$kinder = array();

		foreach ($knoten->childNodes as $kind) {
			$kinder[] = $kind;
		}

		foreach ($kinder as $kind) {
			if ($kind instanceof \DOMText) {
				continue;
			}

			if (!$kind instanceof \DOMElement) {
				// Kommentare, Verarbeitungsanweisungen, CDATA
				$knoten->removeChild($kind);

				continue;
			}

			$name = strtolower($kind->nodeName);

			if (in_array($name, self::VERBOTEN, true)) {
				$knoten->removeChild($kind);

				continue;
			}

			self::saeubere($kind);

			if (!in_array($name, self::ERLAUBT, true)) {
				// Auspacken: die Kinder an die Stelle des Elements setzen
				while (null !== $kind->firstChild) {
					$knoten->insertBefore($kind->firstChild, $kind);
				}

				$knoten->removeChild($kind);

				continue;
			}

			$attribute = array();

			foreach ($kind->attributes as $attribut) {
				$attribute[] = $attribut->nodeName;
			}

			foreach ($attribute as $attribut) {
				$wert = trim($kind->getAttribute($attribut));
				$behalten = 'a' === $name && (
					('href' === strtolower($attribut) && (bool) preg_match('#^(https?://|mailto:)#i', $wert))
					|| 'title' === strtolower($attribut)
				);

				if (!$behalten) {
					$kind->removeAttribute($attribut);
				}
			}
		}
	}

	/**
	 * Vereinheitlicht HTML für den Vergleich „hat sich etwas geändert?":
	 * Leerraum zusammengefasst, Rand gekürzt.
	 *
	 * @param string $html HTML
	 *
	 * @return string Vergleichsform
	 */
	public static function normalHtml(string $html): string
	{
		return trim((string) preg_replace('/\s+/u', ' ', $html));
	}

	/**
	 * Stellt fest, was an den Vereinsdaten geändert werden soll.
	 *
	 * „Wird nichts verändert, wird auch nichts abgeschickt": Eine leere Liste
	 * heißt, dass das Formular unverändert kam.
	 *
	 * - Homepage: verglichen mit der bisherigen aus dem signierten Kontext.
	 * - Über den Verein: Der Editor schreibt HTML beim Laden um, auch wenn
	 *   niemand tippt. Deshalb meldet das Skript, ob das Mitglied den Text
	 *   wirklich angefasst hat ($infoAngefasst); zusätzlich muss er sich von
	 *   der bisherigen Fassung unterscheiden (Prüfsumme im Kontext).
	 * - Logo: eine Datei liegt bei.
	 * - Nachricht: nicht leer — auch eine bloße Mitteilung ist eine Änderung
	 *   des Formulars.
	 *
	 * @param array<string,mixed> $kontext       Entschlüsselter Kontext
	 * @param string              $homepage      Bereinigte Homepage aus dem Formular
	 * @param string              $info          Bereinigtes HTML aus dem Formular
	 * @param bool                $infoAngefasst Angabe des Skripts, ob der Text bearbeitet wurde
	 * @param bool                $mitDatei      true, wenn ein Logo beiliegt
	 * @param string              $nachricht     Bereinigte Nachricht
	 *
	 * @return list<string> Geänderte Teile: 'logo', 'homepage', 'info', 'nachricht'
	 */
	public static function vereinsaenderungen(array $kontext, string $homepage, string $info, bool $infoAngefasst, bool $mitDatei, string $nachricht): array
	{
		$teile = array();

		if ($mitDatei) {
			$teile[] = 'logo';
		}

		if (rtrim($homepage, '/') !== rtrim((string) ($kontext['homepage'] ?? ''), '/')) {
			$teile[] = 'homepage';
		}

		if ($infoAngefasst && sha1(self::normalHtml($info)) !== (string) ($kontext['info'] ?? '')) {
			$teile[] = 'info';
		}

		if ('' !== $nachricht) {
			$teile[] = 'nachricht';
		}

		return $teile;
	}

	/**
	 * Schreibt die Mail zu „Foto ändern".
	 *
	 * @param array<string,mixed> $kontext   Kontext
	 * @param array<string,mixed> $datei     Geprüfte Datei (groesse, breite, hoehe)
	 * @param string              $anhang    Name des Anhangs
	 * @param string              $nachricht Nachricht des Mitglieds, '' für keine
	 * @param string              $adminName Name des Admins für die Anrede, '' für keine
	 *
	 * @return string Text ohne Fußzeile
	 */
	public static function textFoto(array $kontext, array $datei, string $anhang, string $nachricht, string $adminName): string
	{
		$spieler = (string) ($kontext['spieler']['name'] ?? '');
		$nuId = (string) ($kontext['spieler']['id'] ?? '');

		$text = ('' !== $adminName ? 'Guten Tag '.$adminName.',' : 'Guten Tag,')."\n\n"
			."für die DWZ-Karteikarte wird ein neues Foto eingereicht:\n\n"
			.self::fuelle('Spieler:', 9).$spieler.('' !== $nuId ? ' ('.$nuId.')' : '')."\n"
			.self::fuelle('Seite:', 9).(string) ($kontext['url'] ?? '')."\n"
			.self::fuelle('Foto:', 9).'liegt als Anhang bei ('.$anhang.', '.self::groesse((int) ($datei['groesse'] ?? 0)).', '.(int) ($datei['breite'] ?? 0).' × '.(int) ($datei['hoehe'] ?? 0)." Pixel)\n";

		if ('' !== $nachricht) {
			$text .= "\nNachricht des Mitglieds:\n".$nachricht."\n";
		}

		return $text;
	}

	/**
	 * Schreibt die Mail zu „Logo/Infos ändern". Genannt wird nur, was sich
	 * ändern soll.
	 *
	 * „Über den Verein" steht als HTML-Quelltext darin (zum Einfügen in die
	 * Quelltextansicht des Backends) und darunter als reiner Text zum Lesen.
	 *
	 * @param array<string,mixed>      $kontext   Kontext
	 * @param list<string>             $teile     Geänderte Teile aus vereinsaenderungen()
	 * @param string                   $homepage  Neue Homepage
	 * @param string                   $info      Neues HTML „Über den Verein"
	 * @param array<string,mixed>|null $datei     Geprüfte Datei oder null
	 * @param string                   $anhang    Name des Anhangs
	 * @param string                   $nachricht Nachricht des Mitglieds
	 * @param string                   $adminName Name des Admins für die Anrede
	 *
	 * @return string Text ohne Fußzeile
	 */
	public static function textVereinsdaten(array $kontext, array $teile, string $homepage, string $info, ?array $datei, string $anhang, string $nachricht, string $adminName): string
	{
		$verein = (string) ($kontext['verein']['name'] ?? '');
		$vkz = (string) ($kontext['verein']['vkz'] ?? '');

		$text = ('' !== $adminName ? 'Guten Tag '.$adminName.',' : 'Guten Tag,')."\n\n"
			."zu den Vereinsdaten im DWZ-Portal wird folgende Änderung gewünscht:\n\n"
			.self::fuelle('Verein:', 8).$verein.('' !== $vkz ? ' ('.$vkz.')' : '')."\n"
			.self::fuelle('Seite:', 8).(string) ($kontext['url'] ?? '')."\n";

		if (in_array('logo', $teile, true) && null !== $datei) {
			$text .= "\nLogo: liegt als Anhang bei (".$anhang.', '.self::groesse((int) ($datei['groesse'] ?? 0)).', '.(int) ($datei['breite'] ?? 0).' × '.(int) ($datei['hoehe'] ?? 0)." Pixel)\n";
		}

		if (in_array('homepage', $teile, true)) {
			$bisher = (string) ($kontext['homepage'] ?? '');
			$text .= "\nHomepage bisher: ".('' !== $bisher ? $bisher : '(keine)')."\n"
				.'Homepage neu:    '.('' !== $homepage ? $homepage : '(soll entfallen)')."\n";
		}

		if (in_array('info', $teile, true)) {
			$strich = str_repeat('-', 60);
			$lesbar = trim(html_entity_decode(strip_tags((string) preg_replace('#<(br|/p|/li|/h[2-4]|/blockquote)\b[^>]*>#i', "$0\n", $info)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

			$text .= "\n„Über den Verein\" neu — als HTML-Quelltext zum Einfügen:\n".$strich."\n"
				.('' !== $info ? $info : '(soll leer werden)')."\n".$strich."\n";

			if ('' !== $lesbar) {
				$text .= "\nDerselbe Text zum Lesen:\n".$strich."\n".(string) preg_replace("/\n{3,}/", "\n\n", $lesbar)."\n".$strich."\n";
			}
		}

		if ('' !== $nachricht) {
			$text .= "\nNachricht des Mitglieds:\n".$nachricht."\n";
		}

		return $text;
	}

	/**
	 * Schreibt eine Dateigröße lesbar: „850 KB", „1,2 MB".
	 *
	 * @param int $bytes Größe in Bytes
	 *
	 * @return string Größe mit Einheit, deutsch formatiert
	 */
	public static function groesse(int $bytes): string
	{
		if ($bytes >= 1048576) {
			return str_replace('.', ',', (string) round($bytes / 1048576, 1)).' MB';
		}

		return max(1, (int) round($bytes / 1024)).' KB';
	}

	// ═════════════════════════════════════════════════════════════════════
	//  Mit Contao: Links, Dialoge, Versand
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Liefert den Link „Foto ändern" — oder nichts.
	 *
	 * Er erscheint unter denselben Bedingungen wie der Reklamationslink:
	 * Mitglied angemeldet, gültige Adresse im Konto, DSB-Admin eingestellt.
	 *
	 * @param string                   $name     Vor- und Nachname des Spielers
	 * @param string                   $nuId     NU-Nummer
	 * @param string                   $url      Vollständige Adresse der Seite
	 * @param array<string,mixed>|null $mitglied Für Prüfstände; sonst das angemeldete Mitglied
	 *
	 * @return string HTML des Links, '' wenn er nicht erscheinen soll
	 */
	public static function linkFoto(string $name, string $nuId, string $url, ?array $mitglied = null): string
	{
		$mitglied = self::berechtigt($mitglied);

		if (null === $mitglied) {
			return '';
		}

		$kontext = self::fuerFoto($name, $nuId, $url);
		$signiert = self::signiere($kontext, self::geheimnis());

		self::dialogEinbinden('foto', $mitglied);

		return sprintf(
			'<button type="button" class="wp-aenderung-link" data-dialog="wp-foto" data-kontext="%s" data-signatur="%s" data-betreff="%s" title="%s">Foto ändern</button>',
			self::attribut($signiert['kontext']),
			self::attribut($signiert['signatur']),
			self::attribut((string) $kontext['gegenstand']),
			self::attribut('Ein neues Foto für '.$kontext['spieler']['name'].' an den Deutschen Schachbund schicken')
		);
	}

	/**
	 * Liefert den Link „Logo/Infos ändern" — oder nichts.
	 *
	 * Homepage und Vereinsinfo reisen im Klartext als data-Attribute mit, um
	 * das Formular vorzubelegen; maßgeblich für den Vergleich sind aber die
	 * Werte im signierten Kontext.
	 *
	 * @param string                   $name     Vereinsname
	 * @param string                   $vkz      Kennziffer des Vereins
	 * @param string                   $url      Vollständige Adresse der Seite
	 * @param string                   $homepage Bisherige Homepage
	 * @param string                   $info     Bisheriger Text „Über den Verein" (HTML)
	 * @param array<string,mixed>|null $mitglied Für Prüfstände; sonst das angemeldete Mitglied
	 *
	 * @return string HTML des Links, '' wenn er nicht erscheinen soll
	 */
	public static function linkVereinsdaten(string $name, string $vkz, string $url, string $homepage, string $info, ?array $mitglied = null): string
	{
		$mitglied = self::berechtigt($mitglied);

		if (null === $mitglied) {
			return '';
		}

		$kontext = self::fuerVereinsdaten($name, $vkz, $url, $homepage, $info);
		$signiert = self::signiere($kontext, self::geheimnis());

		self::dialogEinbinden('vereinsdaten', $mitglied);

		return sprintf(
			'<button type="button" class="wp-aenderung-link" data-dialog="wp-vereinsdaten" data-kontext="%s" data-signatur="%s" data-betreff="%s" data-homepage="%s" data-info="%s" title="%s">Logo/Infos ändern</button>',
			self::attribut($signiert['kontext']),
			self::attribut($signiert['signatur']),
			self::attribut((string) $kontext['gegenstand']),
			self::attribut(trim($homepage)),
			self::attribut($info),
			self::attribut('Logo, Homepage oder Vereinsbeschreibung von '.$kontext['verein']['name'].' ändern lassen')
		);
	}

	/**
	 * Prüft, ob die Links erscheinen dürfen, und liefert das Mitglied.
	 *
	 * @param array<string,mixed>|null $mitglied Vorgabe für Prüfstände oder null
	 *
	 * @return array<string,mixed>|null Mitglied, oder null: nicht angemeldet,
	 *                                  keine gültige Adresse im Konto oder kein
	 *                                  DSB-Admin eingestellt
	 */
	protected static function berechtigt(?array $mitglied): ?array
	{
		$mitglied = $mitglied ?? self::mitglied();

		if (null === $mitglied || !self::adresseGueltig((string) ($mitglied['email'] ?? '')) || !self::adresseGueltig(self::admin()['email'])) {
			return null;
		}

		return $mitglied;
	}

	/**
	 * Hängt den Dialog einer Art einmal je Seite an deren Ende und bindet das
	 * Skript ein.
	 *
	 * @param string              $art      'foto' oder 'vereinsdaten'
	 * @param array<string,mixed> $mitglied Angemeldetes Mitglied
	 *
	 * @return void
	 */
	protected static function dialogEinbinden(string $art, array $mitglied): void
	{
		if (!empty(self::$dialoge[$art])) {
			return;
		}

		self::$dialoge[$art] = true;
		$GLOBALS['TL_BODY']['wertungsportal_aenderung_'.$art] = 'foto' === $art ? self::dialogFoto($mitglied) : self::dialogVereinsdaten($mitglied);
		$GLOBALS['TL_JAVASCRIPT']['wertungsportal_aenderung'] = 'bundles/contaowertungsportal/js/aenderung.js';
	}

	/**
	 * Nimmt eine Änderungsmeldung entgegen: prüfen, verschicken, ablegen.
	 *
	 * Ablauf mit HTTP-Status: 503 ohne DSB-Admin · 422 ohne gültige Adresse im
	 * Konto · 400 bei falscher Signatur oder fremdem Kontext · 422 bei
	 * fehlendem Betreff, unzulässiger Datei, falscher Homepage oder
	 * unverändertem Formular · 429 an der Bremse · 502, wenn der Versand
	 * scheitert (dann ist nichts angekommen — die Datei wird nicht aufbewahrt).
	 *
	 * @param array<string,string>     $eingabe  kontext, signatur, betreff, nachricht, kopie,
	 *                                           homepage, info, info_angefasst
	 * @param array<string,mixed>|null $datei    Upload: pfad, groesse, fehler; null ohne Datei
	 * @param array<string,mixed>      $mitglied id, name, email des angemeldeten Mitglieds
	 * @param string                   $host     Name der Website für die Fußzeile
	 *
	 * @return array{status:int,daten:array<string,mixed>} Antwort für den Controller
	 */
	public static function melden(array $eingabe, ?array $datei, array $mitglied, string $host): array
	{
		$admin = self::admin();

		if (!self::adresseGueltig($admin['email'])) {
			return self::antwort(503, 'Diese Funktion ist zurzeit nicht eingerichtet.');
		}

		if (!self::adresseGueltig((string) ($mitglied['email'] ?? ''))) {
			return self::antwort(422, 'In Ihrem Mitgliedskonto ist keine gültige E-Mail-Adresse hinterlegt. An sie gehen die Antworten — bitte ergänzen Sie sie zuerst.');
		}

		$kontext = self::entschluessele((string) ($eingabe['kontext'] ?? ''), (string) ($eingabe['signatur'] ?? ''), self::geheimnis());
		$bereich = (string) ($kontext['bereich'] ?? '');

		if (null === $kontext || !in_array($bereich, array('foto', 'vereinsdaten'), true)) {
			return self::antwort(400, 'Das Formular ist ungültig. Bitte laden Sie die Seite neu und versuchen Sie es noch einmal.');
		}

		$betreff = self::bereinigeBetreff((string) ($eingabe['betreff'] ?? ''));

		if ('' === $betreff) {
			return self::antwort(422, 'Bitte geben Sie einen Betreff an.');
		}

		$nachricht = mb_substr(self::bereinigeText((string) ($eingabe['nachricht'] ?? '')), 0, self::NACHRICHT_LAENGE);
		$datei = self::untersuche($datei);
		$dateifehler = self::dateifehler($datei, 'foto' === $bereich, self::hoechstgroesse());

		if (null !== $dateifehler) {
			return self::antwort(422, $dateifehler);
		}

		$anhang = null !== $datei ? self::anhangname($kontext, (string) $datei['mime']) : '';
		$adminName = self::name((string) $admin['name']);

		if ('foto' === $bereich) {
			$text = self::textFoto($kontext, (array) $datei, $anhang, $nachricht, $adminName);
		} else {
			$homepage = self::bereinigeHomepage((string) ($eingabe['homepage'] ?? ''));

			if (null !== $homepage['fehler']) {
				return self::antwort(422, $homepage['fehler']);
			}

			$info = self::bereinigeHtml((string) ($eingabe['info'] ?? ''));
			$teile = self::vereinsaenderungen($kontext, $homepage['wert'], $info, '1' === (string) ($eingabe['info_angefasst'] ?? ''), null !== $datei, $nachricht);

			if (!$teile) {
				return self::antwort(422, 'Sie haben nichts geändert. Bitte ändern Sie Logo, Homepage oder Vereinsbeschreibung — oder schreiben Sie uns eine Nachricht.');
			}

			$text = self::textVereinsdaten($kontext, $teile, $homepage['wert'], $info, $datei, $anhang, $nachricht, $adminName);
		}

		$bisher = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalReklamationenModel::countBy(
			array('memberId=?', 'datum>?'),
			array((int) $mitglied['id'], time() - self::ZEITFENSTER)
		);

		if ($bisher >= self::HOECHSTZAHL) {
			return self::antwort(429, 'Sie haben in der letzten Stunde schon '.self::HOECHSTZAHL.' Nachrichten geschickt. Bitte versuchen Sie es später noch einmal.');
		}

		$zeit = time();
		$kopie = '1' === (string) ($eingabe['kopie'] ?? '');
		$volltext = $text."\n".self::fusszeile($mitglied, $kontext, $host, $zeit);
		$empfaenger = array(array('name' => $adminName, 'email' => $admin['email'], 'art' => 'admin'));

		$versandfehler = self::verschickeMeldung($empfaenger, $mitglied, $kopie, $betreff, $volltext, $datei, $anhang);

		self::speichere($zeit, $mitglied, $kontext, $empfaenger, $kopie ? (string) $mitglied['email'] : '', $betreff, $volltext, $versandfehler);

		if ('' !== $versandfehler) {
			return self::antwort(502, 'Ihre Nachricht konnte nicht verschickt werden. Bitte versuchen Sie es später noch einmal.');
		}

		return array(
			'status' => 200,
			'daten'  => array(
				'ok'      => true,
				'meldung' => 'Vielen Dank! Ihre Nachricht ist an den Deutschen Schachbund unterwegs.'.($kopie ? ' Eine Kopie geht an '.$mitglied['email'].'.' : ''),
			),
		);
	}

	/**
	 * Liest Typ und Abmessungen einer hochgeladenen Datei aus ihrem Inhalt.
	 *
	 * Der vom Browser gemeldete Typ und die Dateiendung zählen nicht — beide
	 * bestimmt der Absender. Fehlt die Datei oder kam sie nicht fehlerfrei an,
	 * bleiben die Angaben, wie sie sind; dateifehler() meldet das dann.
	 *
	 * @param array<string,mixed>|null $datei Upload mit pfad, groesse, fehler
	 *
	 * @return array<string,mixed>|null Dieselben Angaben, ergänzt um mime,
	 *                                  breite und hoehe; null ohne Datei
	 */
	protected static function untersuche(?array $datei): ?array
	{
		if (null === $datei || UPLOAD_ERR_NO_FILE === (int) ($datei['fehler'] ?? UPLOAD_ERR_NO_FILE)) {
			return null;
		}

		$datei += array('mime' => '', 'breite' => 0, 'hoehe' => 0);
		$pfad = (string) ($datei['pfad'] ?? '');

		if (UPLOAD_ERR_OK !== (int) $datei['fehler'] || '' === $pfad || !is_file($pfad)) {
			return $datei;
		}

		$datei['groesse'] = (int) filesize($pfad);
		$datei['mime'] = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($pfad);
		$masse = @getimagesize($pfad);

		if (is_array($masse)) {
			$datei['breite'] = (int) $masse[0];
			$datei['hoehe'] = (int) $masse[1];
		}

		return $datei;
	}

	/**
	 * Liefert die größte Datei, die angenommen wird: DATEI_GROESSE, höchstens
	 * aber, was der Server je Datei zulässt (upload_max_filesize).
	 *
	 * @return int Größe in Bytes
	 */
	public static function hoechstgroesse(): int
	{
		$grenze = trim((string) ini_get('upload_max_filesize'));
		$bytes = (int) $grenze;

		switch (strtolower(substr($grenze, -1))) {
			case 'g':
				$bytes *= 1024;
				// no break
			case 'm':
				$bytes *= 1024;
				// no break
			case 'k':
				$bytes *= 1024;
		}

		return $bytes > 0 ? min(self::DATEI_GROESSE, $bytes) : self::DATEI_GROESSE;
	}

	/**
	 * Verschickt die Meldung an den DSB-Admin.
	 *
	 * Reine Textmail. Absender ist der Absender der Reklamationen, Antworten
	 * gehen an das Mitglied, die Kopie (Cc) auf Wunsch an dessen Adresse aus
	 * dem Konto — nie an eine Adresse aus dem Formular. Die Datei wird aus dem
	 * Upload-Zwischenspeicher gelesen und unter dem gebildeten Namen angehängt.
	 *
	 * @param array<int,array<string,string>> $empfaenger Empfänger (der Admin)
	 * @param array<string,mixed>              $mitglied   Mitglied
	 * @param bool                             $kopie      true: Kopie an das Mitglied
	 * @param string                           $betreff    Betreff
	 * @param string                           $text       Text samt Fußzeile
	 * @param array<string,mixed>|null         $datei      Geprüfte Datei oder null
	 * @param string                           $anhang     Name des Anhangs
	 *
	 * @return string Fehlermeldung, '' bei Erfolg
	 */
	protected static function verschickeMeldung(array $empfaenger, array $mitglied, bool $kopie, string $betreff, string $text, ?array $datei, string $anhang): string
	{
		try {
			$absender = self::absender();
			$objEmail = new \Contao\Email();
			$objEmail->from = $absender['email'];
			$objEmail->fromName = $absender['name'];
			$objEmail->subject = $betreff;
			$objEmail->text = $text;
			$objEmail->replyTo(array(self::name((string) $mitglied['name']).' <'.$mitglied['email'].'>'));

			if ($kopie) {
				$objEmail->sendCc(array(self::name((string) $mitglied['name']).' <'.$mitglied['email'].'>'));
			}

			if (null !== $datei) {
				$inhalt = file_get_contents((string) $datei['pfad']);

				if (false === $inhalt) {
					return 'Die hochgeladene Datei war nicht lesbar.';
				}

				$objEmail->attachFileFromString($inhalt, $anhang, (string) $datei['mime']);
			}

			$liste = array();

			foreach ($empfaenger as $e) {
				$liste[] = ('' !== $e['name'] ? $e['name'].' ' : '').'<'.$e['email'].'>';
			}

			if (!$objEmail->sendTo($liste)) {
				return 'Der Mailversand meldete einen Fehler.';
			}
		} catch (\Throwable $e) {
			Helper::systemlog('Änderungsmeldung konnte nicht verschickt werden: '.$e->getMessage(), __METHOD__, 'ERROR');

			return mb_substr($e->getMessage(), 0, 1000);
		}

		return '';
	}

	/**
	 * Baut den Dialog „Foto ändern".
	 *
	 * Ein natives `<dialog>` wie bei der Reklamation, modal geöffnet.
	 *
	 * @param array<string,mixed> $mitglied Angemeldetes Mitglied
	 *
	 * @return string HTML des Dialogs
	 */
	protected static function dialogFoto(array $mitglied): string
	{
		$e = static function (string $wert): string {
			return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
		};

		return '<dialog id="wp-foto" class="wp-reklamation wp-aenderung" aria-labelledby="wp-foto-titel" data-pflichtdatei="1" data-max="'.self::hoechstgroesse().'">'
			.self::formularkopf('wp-foto', 'Foto ändern', 'Das Foto geht an den Deutschen Schachbund, der es in die Karteikarte einsetzt.', $mitglied)
			.'<label for="wp-foto-betreff">Betreff</label>'
			.'<input type="text" id="wp-foto-betreff" name="betreff" maxlength="'.self::BETREFF_LAENGE.'" required>'
			.'<label for="wp-foto-datei">Foto</label>'
			.'<input type="file" id="wp-foto-datei" name="datei" accept="image/jpeg,image/png,image/gif,image/webp" required>'
			.'<p class="wp-aenderung-hilfe">JPEG, PNG, GIF oder WebP, höchstens '.$e(self::groesse(self::hoechstgroesse())).'. Bitte nur Fotos einreichen, an denen Sie die Rechte haben.</p>'
			.'<label for="wp-foto-nachricht">Nachricht <span class="wp-aenderung-optional">(optional)</span></label>'
			.'<textarea id="wp-foto-nachricht" name="nachricht" rows="4" maxlength="'.self::NACHRICHT_LAENGE.'"></textarea>'
			.self::formularfuss('wp-foto', $mitglied)
			.'</dialog>';
	}

	/**
	 * Baut den Dialog „Logo/Infos ändern".
	 *
	 * NICHT modal geöffnet (data-nichtmodal): Der Editor TinyMCE hängt seine
	 * Menüs und Fenster an das Ende der Seite — unter einem modalen Dialog
	 * lägen sie unsichtbar dahinter. Die Abdunklung übernimmt deshalb ein
	 * eigenes Element, das das Skript anlegt.
	 *
	 * Der Editor wird erst beim Öffnen nachgeladen (data-tinymce nennt das
	 * Skript aus Contaos assets-Ordner). Fehlt es, bleibt ein schlichtes
	 * Textfeld mit dem HTML-Quelltext.
	 *
	 * @param array<string,mixed> $mitglied Angemeldetes Mitglied
	 *
	 * @return string HTML des Dialogs
	 */
	protected static function dialogVereinsdaten(array $mitglied): string
	{
		$e = static function (string $wert): string {
			return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
		};

		$editor = self::editor();

		return '<dialog id="wp-vereinsdaten" class="wp-reklamation wp-aenderung wp-aenderung-breit" aria-labelledby="wp-vereinsdaten-titel" data-nichtmodal="1" data-max="'.self::hoechstgroesse().'"'
			.' data-tinymce="'.$e($editor['skript']).'" data-tinymce-basis="'.$e($editor['basis']).'" data-tinymce-sprache="'.$e($editor['sprache']).'">'
			.self::formularkopf('wp-vereinsdaten', 'Logo/Infos ändern', 'Ändern Sie, was nicht mehr stimmt. Der Deutsche Schachbund prüft die Angaben und übernimmt sie.', $mitglied)
			.'<input type="hidden" name="info_angefasst" value="">'
			.'<label for="wp-vereinsdaten-betreff">Betreff</label>'
			.'<input type="text" id="wp-vereinsdaten-betreff" name="betreff" maxlength="'.self::BETREFF_LAENGE.'" required>'
			.'<label for="wp-vereinsdaten-datei">Neues Logo <span class="wp-aenderung-optional">(optional)</span></label>'
			.'<input type="file" id="wp-vereinsdaten-datei" name="datei" accept="image/jpeg,image/png,image/gif,image/webp">'
			.'<p class="wp-aenderung-hilfe">JPEG, PNG, GIF oder WebP, höchstens '.$e(self::groesse(self::hoechstgroesse())).'.</p>'
			.'<label for="wp-vereinsdaten-homepage">Homepage</label>'
			.'<input type="url" id="wp-vereinsdaten-homepage" name="homepage" maxlength="255" placeholder="https://www.verein.de" inputmode="url" autocomplete="off">'
			.'<label for="wp-vereinsdaten-info">Über den Verein</label>'
			.'<textarea id="wp-vereinsdaten-info" name="info" rows="8" maxlength="'.self::INFO_LAENGE.'"></textarea>'
			.'<label for="wp-vereinsdaten-nachricht">Nachricht <span class="wp-aenderung-optional">(optional)</span></label>'
			.'<textarea id="wp-vereinsdaten-nachricht" name="nachricht" rows="3" maxlength="'.self::NACHRICHT_LAENGE.'"></textarea>'
			.self::formularfuss('wp-vereinsdaten', $mitglied)
			.'</dialog>';
	}

	/**
	 * Baut den gemeinsamen Anfang beider Dialoge: Formular, Schließen-Knopf,
	 * Überschrift, Hinweis und die versteckten Felder.
	 *
	 * @param string              $id       ID des Dialogs
	 * @param string              $titel    Überschrift
	 * @param string              $hinweis  Erklärender Satz
	 * @param array<string,mixed> $mitglied Angemeldetes Mitglied
	 *
	 * @return string HTML
	 */
	protected static function formularkopf(string $id, string $titel, string $hinweis, array $mitglied): string
	{
		$container = \Contao\System::getContainer();

		try {
			$ziel = $container->get('router')->generate(self::ROUTE_AENDERUNG);
		} catch (\Throwable $e) {
			$ziel = '/wertungsportal-api/aenderung';
		}

		$token = (string) $container->get('contao.csrf.token_manager')->getDefaultTokenValue();
		$e = static function (string $wert): string {
			return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
		};

		return '<form class="wp-reklamation-form" method="post" action="'.$e($ziel).'" enctype="multipart/form-data" novalidate>'
			.'<button type="button" class="wp-reklamation-schliessen" aria-label="Schließen" title="Schließen">×</button>'
			.'<h2 id="'.$e($id).'-titel">'.$e($titel).'</h2>'
			.'<p class="wp-reklamation-hinweis">'.$e($hinweis).'<br>Antworten gehen an Ihre Adresse <strong>'.$e((string) $mitglied['email']).'</strong>.</p>'
			.'<input type="hidden" name="REQUEST_TOKEN" value="'.$e($token).'">'
			.'<input type="hidden" name="kontext" value="">'
			.'<input type="hidden" name="signatur" value="">';
	}

	/**
	 * Baut das gemeinsame Ende beider Dialoge: Kopie-Häkchen, Meldungszeile
	 * und Knöpfe.
	 *
	 * @param string              $id       ID des Dialogs
	 * @param array<string,mixed> $mitglied Angemeldetes Mitglied
	 *
	 * @return string HTML
	 */
	protected static function formularfuss(string $id, array $mitglied): string
	{
		$e = static function (string $wert): string {
			return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
		};

		return '<p class="wp-aenderung-kopie"><input type="checkbox" id="'.$e($id).'-kopie" name="kopie" value="1">'
			.' <label for="'.$e($id).'-kopie">Kopie an mich ('.$e((string) $mitglied['email']).')</label></p>'
			.'<p class="wp-reklamation-meldung" role="status" aria-live="polite"></p>'
			.'<div class="wp-reklamation-knoepfe">'
			.'<button type="submit" class="wp-reklamation-senden">Absenden</button>'
			.'<button type="button" class="wp-reklamation-abbrechen">Abbrechen</button>'
			.'</div>'
			.'</form>';
	}

	/**
	 * Sucht den Editor TinyMCE in Contaos assets-Ordner.
	 *
	 * Contao liefert ihn mit (4.13: TinyMCE 5, Contao 5: neuere Fassungen),
	 * in beiden unter assets/tinymce4/js. Der Sprachsatz „de" wird nur
	 * genannt, wenn er vorhanden ist — sonst spricht der Editor Englisch.
	 *
	 * @return array{skript:string,basis:string,sprache:string} Adressen für das
	 *                                                          Skript; leer, wenn
	 *                                                          der Editor fehlt
	 */
	protected static function editor(): array
	{
		$leer = array('skript' => '', 'basis' => '', 'sprache' => '');

		try {
			$webDir = (string) \Contao\System::getContainer()->getParameter('contao.web_dir');
			$ordner = '/assets/tinymce4/js';

			if (!is_file($webDir.$ordner.'/tinymce.min.js')) {
				return $leer;
			}

			$basis = rtrim((string) \Contao\Environment::get('path'), '/').$ordner;

			return array(
				'skript'  => $basis.'/tinymce.min.js',
				'basis'   => $basis,
				'sprache' => is_file($webDir.$ordner.'/langs/de.js') ? 'de' : '',
			);
		} catch (\Throwable $e) {
			return $leer;
		}
	}
}
