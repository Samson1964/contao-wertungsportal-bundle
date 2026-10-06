<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Reklamationen angemeldeter Mitglieder zu den angezeigten DWZ-Daten.
 *
 * Neben dem Wertungsreferenten einer Ansicht steht für angemeldete Mitglieder
 * ein Link „Reklamation". Er öffnet ein Formular in einer Lightbox (natives
 * `<dialog>`), dessen Betreff und Text schon alles enthalten, was der
 * Empfänger zur Einordnung braucht — Turnier, Spieler, Adresse der Seite.
 * Abgeschickt wird per `fetch` an die Route /wertungsportal-api/reklamation
 * (Controller\ReklamationController), gespeichert im Backend-Modul
 * „Reklamationen".
 *
 * Wer die Nachricht bekommt, steht schon beim Anzeigen der Seite fest und
 * geht SIGNIERT an den Browser (HMAC mit dem Kernel-Secret). Beim Absenden
 * wird nur diese Signatur geprüft — sonst ließe sich über das Formular jede
 * beliebige Adresse anschreiben, mit der Absenderadresse des DSB.
 *
 * Die Klasse zerfällt in zwei Teile:
 *
 * - **ohne Contao** (reine Funktionen, geprüft in
 *   tests/Helper/ReklamationTest.php): Kontexte bauen, Empfänger bestimmen,
 *   Betreff und Text vorbelegen, Eingaben bereinigen, signieren;
 * - **mit Contao**: den Link samt Dialog ausgeben, eine Reklamation
 *   verschicken und speichern.
 *
 * Beschreibung für Anwender und Betreiber: docs/reklamationen.md
 */
class Reklamation
{
	/**
	 * Höchstzahl der Reklamationen eines Mitglieds im Zeitfenster.
	 *
	 * Die Absenderadresse ist die des DSB. Ohne Bremse könnte ein einzelnes
	 * Konto mit ihr beliebig viele Nachrichten an Referenten auslösen.
	 */
	const HOECHSTZAHL = 5;

	/**
	 * Zeitfenster der Bremse in Sekunden (eine Stunde).
	 */
	const ZEITFENSTER = 3600;

	/**
	 * Längste zulässige Betreffzeile in Zeichen.
	 */
	const BETREFF_LAENGE = 200;

	/**
	 * Längster zulässiger Nachrichtentext in Zeichen.
	 */
	const TEXT_LAENGE = 10000;

	/**
	 * Mindestlänge des Nachrichtentextes. Verhindert versehentlich
	 * abgeschickte leere Formulare, nicht mehr.
	 */
	const TEXT_MINDESTENS = 20;

	/**
	 * Zeile der Vorlage, unter der das Mitglied sein Anliegen schreibt. Dort
	 * steht beim Öffnen des Formulars die Schreibmarke.
	 */
	const MARKE = 'Was stimmt nicht?';

	/**
	 * Name der Route in routing.yml.
	 */
	const ROUTE = 'wertungsportal_reklamation';

	/**
	 * Wurde der Dialog auf dieser Seite schon ausgegeben? Er wird nur einmal
	 * gebraucht, gleich wie viele Links die Seite trägt.
	 *
	 * @var bool
	 */
	protected static $dialogAusgegeben = false;

	// ═════════════════════════════════════════════════════════════════════
	//  Kontexte — ohne Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Baut den Kontext für eine Turnierseite: Auswertung, Ergebnisliste oder
	 * Spielberichtsbogen.
	 *
	 * Empfänger ist der Auswerter aus dem Turnierkopf von nu — aber nur, wenn
	 * ein Nachname UND eine gültige Adresse vorliegen. Sonst gelten die
	 * lokalen Referenten aus $ersatz (ab 1.53.0), und fehlen auch die, setzt
	 * link() den DSB-Admin ein.
	 *
	 * Den Turnierkopf liefert nu je nach Abfrage flach (Turnierinfo) oder
	 * unter `tournament` (Turnierauswertung). Bis 1.52.0 kam bei der
	 * Turnierauswertung nur der flache Fall an: Turniername, Zeitraum und
	 * Turniercode fehlten im vorbelegten Text, und die Reklamation ging an den
	 * Admin statt an den Auswerter.
	 *
	 * @param array<string,mixed>             $turnier Turnierkopf, wie ihn nu liefert
	 *                                                 (label, uuid, startdate, enddate,
	 *                                                 referentFirstname, -Lastname, -Email),
	 *                                                 flach oder unter `tournament`
	 * @param string                          $bereich turnierauswertung, turnierergebnisse
	 *                                                 oder spielberichtsbogen
	 * @param string                          $url     Vollständige Adresse der Seite
	 * @param array<string,string>|null        $spieler Nur beim Spielberichtsbogen:
	 *                                                 name und id (NU-Nummer, darf leer sein)
	 * @param array<int,array<string,string>> $ersatz  Lokale Referenten (name, email), falls
	 *                                                 nu keinen Auswerter nennt
	 *
	 * @return array<string,mixed> Kontext für link()
	 */
	public static function fuerTurnier(array $turnier, string $bereich, string $url, ?array $spieler = null, array $ersatz = array()): array
	{
		if (isset($turnier['tournament']) && is_array($turnier['tournament'])) {
			$turnier = $turnier['tournament'];
		}

		$name = self::zeile((string) ($turnier['label'] ?? ''));
		$uuid = self::zeile((string) ($turnier['uuid'] ?? ''));

		$angaben = array(array('Turnier', $name));

		$zeitraum = self::zeitraum((string) ($turnier['startdate'] ?? ''), (string) ($turnier['enddate'] ?? ''));

		if ('' !== $zeitraum) {
			$angaben[] = array('Zeitraum', $zeitraum);
		}

		$angaben[] = array('Turniercode', $uuid);

		$ansichten = array(
			'turnierauswertung'  => 'Turnierauswertung',
			'turnierergebnisse'  => 'Ergebnisliste',
			'spielberichtsbogen' => 'Spielberichtsbogen',
		);

		$gegenstand = 'zu '.$name;
		$spielerAngabe = array();

		if ('spielberichtsbogen' === $bereich && null !== $spieler) {
			$spielerName = self::zeile((string) ($spieler['name'] ?? ''));
			$spielerId = self::zeile((string) ($spieler['id'] ?? ''));
			$angaben[] = array('Spieler', '' !== $spielerId ? $spielerName.' ('.$spielerId.')' : $spielerName);
			$gegenstand .= ' – Spielberichtsbogen '.$spielerName;
			$spielerAngabe = array('id' => $spielerId, 'name' => $spielerName);
		} elseif ('turnierergebnisse' === $bereich) {
			$gegenstand .= ' (Ergebnisliste)';
		}

		$angaben[] = array('Ansicht', $ansichten[$bereich] ?? $bereich);

		// Auswerter nur mit Nachname und gültiger Adresse — „Ist kein Name
		// vorhanden, wird der DSB-Admin als Empfänger verwendet"
		$referenten = array();
		$nachname = trim((string) ($turnier['referentLastname'] ?? ''));
		$email = trim((string) ($turnier['referentEmail'] ?? ''));

		if ('' !== $nachname && self::adresseGueltig($email)) {
			$referenten[] = array(
				'name'  => trim(trim((string) ($turnier['referentFirstname'] ?? '')).' '.$nachname),
				'email' => $email,
			);
		} else {
			// Kein Auswerter von nu: die lokalen Referenten (empfaenger()
			// siebt ungültige Adressen aus)
			$referenten = array_values($ersatz);
		}

		return array(
			'bereich'    => $bereich,
			'gegenstand' => $gegenstand,
			'angaben'    => $angaben,
			'turnier'    => array('uuid' => $uuid, 'name' => $name),
			'spieler'    => $spielerAngabe,
			'referenten' => $referenten,
			'url'        => $url,
		);
	}

	/**
	 * Baut den Kontext für die DWZ-Karteikarte eines Spielers.
	 *
	 * nu nennt bei Spielern keinen Wertungsreferenten. Empfänger sind
	 * deshalb die lokalen Referenten des Verbandes, zu dem der Verein des
	 * Spielers gehört (ab 1.53.0, Helper\Zustaendigkeit); ohne sie der
	 * DSB-Admin.
	 *
	 * @param string                           $name       Vor- und Nachname
	 * @param string                           $nuId       NU-Nummer
	 * @param string                           $url        Vollständige Adresse der Seite
	 * @param array<int,array<string,string>> $referenten Lokale Referenten (name, email)
	 *
	 * @return array<string,mixed> Kontext für link()
	 */
	public static function fuerKarteikarte(string $name, string $nuId, string $url, array $referenten = array()): array
	{
		$name = self::zeile($name);
		$nuId = self::zeile($nuId);

		return array(
			'bereich'    => 'karteikarte',
			'gegenstand' => 'zur DWZ-Karteikarte von '.$name,
			'angaben'    => array(array('Spieler', '' !== $nuId ? $name.' ('.$nuId.')' : $name), array('Ansicht', 'DWZ-Karteikarte')),
			'turnier'    => array(),
			'spieler'    => array('id' => $nuId, 'name' => $name),
			'referenten' => array_values($referenten),
			'url'        => $url,
		);
	}

	/**
	 * Baut den Kontext für die DWZ-Liste eines Vereins.
	 *
	 * Empfänger sind die lokalen Referenten des Verbandes (ab 1.53.0); ohne
	 * sie der DSB-Admin.
	 *
	 * @param string                           $name       Vereinsname
	 * @param string                           $vkz        Kennziffer des Vereins
	 * @param string                           $url        Vollständige Adresse der Seite
	 * @param array<int,array<string,string>> $referenten Lokale Referenten (name, email)
	 *
	 * @return array<string,mixed> Kontext für link()
	 */
	public static function fuerVerein(string $name, string $vkz, string $url, array $referenten = array()): array
	{
		$name = self::zeile($name);
		$vkz = self::zeile($vkz);

		return array(
			'bereich'    => 'verein',
			'gegenstand' => 'zur DWZ-Liste von '.$name,
			'angaben'    => array(array('Verein', '' !== $vkz ? $name.' ('.$vkz.')' : $name), array('Ansicht', 'DWZ-Liste des Vereins')),
			'turnier'    => array(),
			'spieler'    => array(),
			'referenten' => array_values($referenten),
			'url'        => $url,
		);
	}

	/**
	 * Baut den Kontext für die Rangliste eines Verbandes.
	 *
	 * Empfänger sind die zuständigen Wertungsreferenten aus der
	 * Referentenverwaltung (Helper\Referentenbaum) — dieselben, die unter der
	 * Rangliste stehen. Ist dort niemand eingetragen, der DSB-Admin.
	 *
	 * @param string                           $titel      Überschrift der Rangliste
	 * @param string                           $vkz        Kennziffer des Verbandes
	 * @param array<int,array<string,string>> $referenten Liste mit name und email
	 *                                                     (Klartextadresse)
	 * @param string                           $url        Vollständige Adresse der Seite
	 *
	 * @return array<string,mixed> Kontext für link()
	 */
	public static function fuerRangliste(string $titel, string $vkz, array $referenten, string $url): array
	{
		$titel = self::zeile($titel);
		$gueltig = array();

		foreach ($referenten as $referent) {
			$email = trim((string) ($referent['email'] ?? ''));

			if (self::adresseGueltig($email)) {
				$gueltig[] = array('name' => self::zeile((string) ($referent['name'] ?? '')), 'email' => $email);
			}
		}

		return array(
			'bereich'    => 'rangliste',
			'gegenstand' => 'zur Rangliste '.$titel,
			'angaben'    => array(array('Rangliste', $titel), array('Verband', self::zeile($vkz))),
			'turnier'    => array(),
			'spieler'    => array(),
			'referenten' => $gueltig,
			'url'        => $url,
		);
	}

	/**
	 * Baut den Kontext für eine Trefferliste: Spieler-, Vereins- oder
	 * Turniersuche.
	 *
	 * @param string $was         spielersuche, vereinssuche oder turniersuche
	 * @param string $suchbegriff Was gesucht wurde
	 * @param string $url         Vollständige Adresse der Seite
	 *
	 * @return array<string,mixed> Kontext für link()
	 */
	public static function fuerSuche(string $was, string $suchbegriff, string $url): array
	{
		$namen = array('spielersuche' => 'Spielersuche', 'vereinssuche' => 'Vereinssuche', 'turniersuche' => 'Turniersuche');
		$bezeichnung = $namen[$was] ?? 'Suche';
		$suchbegriff = self::zeile($suchbegriff);

		return array(
			'bereich'    => $was,
			'gegenstand' => 'zur '.$bezeichnung.('' !== $suchbegriff ? ' „'.$suchbegriff.'“' : ''),
			'angaben'    => array(array($bezeichnung, '' !== $suchbegriff ? $suchbegriff : '(ohne Suchbegriff)')),
			'turnier'    => array(),
			'spieler'    => array(),
			'referenten' => array(),
			'url'        => $url,
		);
	}

	/**
	 * Sucht im Spielberichtsbogen die NU-Nummer des Bogeninhabers.
	 *
	 * Der Bogen kennt den Spieler nur über seine playerUuid, und die gilt nur
	 * in diesem einen Turnier. Die NU-Nummer steht in jeder seiner Partien
	 * beim Weiß- oder Schwarzspieler.
	 *
	 * @param array<string,mixed> $bogen      Antwort von nu (body mit matches)
	 * @param string              $playerUuid Kennung des Spielers im Turnier
	 *
	 * @return string NU-Nummer, '' wenn sie nicht zu finden ist
	 */
	public static function nuIdAusBogen(array $bogen, string $playerUuid): string
	{
		foreach ((array) ($bogen['matches'] ?? array()) as $partie) {
			foreach (array('whitePlayer', 'blackPlayer') as $seite) {
				if ($playerUuid === (string) ($partie[$seite]['playerUuid'] ?? '')) {
					return (string) ($partie[$seite]['nuLigaPersonId'] ?? '');
				}
			}
		}

		return '';
	}

	// ═════════════════════════════════════════════════════════════════════
	//  Empfänger, Texte, Prüfungen — ohne Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Bestimmt die Empfänger einer Reklamation.
	 *
	 * Die Referenten des Kontexts, sofern sie eine gültige Adresse haben; sonst
	 * der DSB-Admin. Doppelte Adressen fallen heraus.
	 *
	 * @param array<int,array<string,string>> $referenten name und email
	 * @param array<string,string>             $admin      name und email des DSB-Admins
	 *
	 * @return array<int,array<string,string>> Liste mit name, email und art
	 *                                          (referent oder admin); leer, wenn
	 *                                          auch der Admin keine gültige
	 *                                          Adresse hat
	 */
	public static function empfaenger(array $referenten, array $admin): array
	{
		$liste = array();

		foreach ($referenten as $referent) {
			$email = trim((string) ($referent['email'] ?? ''));

			if (self::adresseGueltig($email) && !isset($liste[strtolower($email)])) {
				$liste[strtolower($email)] = array('name' => self::name((string) ($referent['name'] ?? '')), 'email' => $email, 'art' => 'referent');
			}
		}

		if (!$liste && self::adresseGueltig(trim((string) ($admin['email'] ?? '')))) {
			$liste[] = array('name' => self::name((string) ($admin['name'] ?? '')), 'email' => trim((string) $admin['email']), 'art' => 'admin');
		}

		return array_values($liste);
	}

	/**
	 * Bestimmt die Adresse für die Blindkopie an den DSB-Admin.
	 *
	 * Der Admin bekommt jede Reklamation in Kopie — außer er ist ohnehin
	 * Empfänger, sei es als Ersatz für fehlende Referenten oder weil seine
	 * Adresse zugleich die eines Referenten ist. Sonst läge die Mail bei ihm
	 * doppelt im Postfach. Groß- und Kleinschreibung zählen dabei nicht.
	 *
	 * Dieselbe Regel entscheidet im Dialog, ob der Hinweis „Eine Kopie erhält
	 * der Deutsche Schachbund" erscheint; deshalb steht sie an einer Stelle.
	 *
	 * @param array<int,array<string,string>> $empfaenger Liste aus empfaenger()
	 * @param array<string,string>             $admin      name und email des DSB-Admins
	 *
	 * @return string Adresse des Admins, '' wenn keine Blindkopie geht (Admin ist
	 *                Empfänger oder hat keine gültige Adresse)
	 */
	public static function blindkopie(array $empfaenger, array $admin): string
	{
		$adresse = trim((string) ($admin['email'] ?? ''));

		if (!self::adresseGueltig($adresse)) {
			return '';
		}

		$vorhanden = array_map('strtolower', array_map('strval', array_column($empfaenger, 'email')));

		return in_array(strtolower($adresse), $vorhanden, true) ? '' : $adresse;
	}

	/**
	 * Bildet die vorbelegte Betreffzeile: „Reklamation zu …".
	 *
	 * @param array<string,mixed> $kontext Kontext aus einer der fuer…()-Methoden
	 *
	 * @return string Betreff, bereinigt und gekürzt
	 */
	public static function betreff(array $kontext): string
	{
		return self::bereinigeBetreff('Reklamation '.(string) ($kontext['gegenstand'] ?? ''));
	}

	/**
	 * Bildet den vorbelegten Nachrichtentext.
	 *
	 * Er nennt alle Angaben des Kontexts und die Adresse der Seite, damit der
	 * Empfänger ohne Rückfrage sieht, worum es geht. Darunter steht die Zeile
	 * self::MARKE mit einer Leerzeile — dort steht beim Öffnen die
	 * Schreibmarke. Das Mitglied darf alles überschreiben.
	 *
	 * @param array<string,mixed>              $kontext      Kontext
	 * @param array<int,array<string,string>> $empfaenger   Ergebnis von empfaenger()
	 * @param string                           $mitgliedName Vor- und Nachname für den Gruß
	 *
	 * @return array{text:string,cursor:int} Text und Position der Schreibmarke
	 *                                        (in Zeichen, nicht Bytes)
	 */
	public static function vorlage(array $kontext, array $empfaenger, string $mitgliedName): array
	{
		// Anrede mit Namen nur bei genau einem Empfänger
		$anrede = 1 === \count($empfaenger) && '' !== ($empfaenger[0]['name'] ?? '') ? 'Guten Tag '.$empfaenger[0]['name'].',' : 'Guten Tag,';

		$zeilen = array();

		foreach ((array) ($kontext['angaben'] ?? array()) as $angabe) {
			$zeilen[] = array((string) ($angabe[0] ?? ''), (string) ($angabe[1] ?? ''));
		}

		if ('' !== (string) ($kontext['url'] ?? '')) {
			$zeilen[] = array('Seite', (string) $kontext['url']);
		}

		$breite = 0;

		foreach ($zeilen as $zeile) {
			$breite = max($breite, mb_strlen($zeile[0]) + 1);
		}

		$block = '';

		foreach ($zeilen as $zeile) {
			$block .= self::fuelle($zeile[0].':', $breite + 1).$zeile[1]."\n";
		}

		$kopf = $anrede."\n\n"
			."zu folgenden Daten im DWZ-System möchte ich eine Reklamation einreichen:\n\n"
			.$block."\n"
			.self::MARKE."\n";

		$text = $kopf."\n\n"
			."Mit freundlichen Grüßen\n"
			.self::name($mitgliedName)."\n";

		return array('text' => $text, 'cursor' => mb_strlen($kopf));
	}

	/**
	 * Bildet die Fußzeile, die an jede Reklamation angehängt wird.
	 *
	 * Sie ist nicht im Formular zu sehen und nicht zu ändern: Weil Betreff und
	 * Text frei bearbeitet werden dürfen, muß der Empfänger an anderer Stelle
	 * erkennen können, wer die Nachricht wirklich geschickt hat und von welcher
	 * Seite aus. Die Angaben stammen aus dem Mitgliedskonto, nicht aus dem
	 * Formular.
	 *
	 * @param array<string,mixed> $mitglied id, name, email des Mitglieds
	 * @param array<string,mixed> $kontext  Kontext (für die Adresse der Seite)
	 * @param string              $host     Name der Website, etwa www.schachbund.de
	 * @param int                 $zeit     Zeitpunkt des Absendens
	 *
	 * @return string Fußzeile, beginnend mit dem Signaturtrenner „-- "
	 */
	public static function fusszeile(array $mitglied, array $kontext, string $host, int $zeit): string
	{
		$name = self::name((string) ($mitglied['name'] ?? ''));

		$zeilen = array(
			'-- ',
			'Gesendet über das DWZ-Portal auf '.$host.' am '.date('d.m.Y', $zeit).' um '.date('H:i', $zeit).' Uhr.',
			'Absender: '.$name.' <'.(string) ($mitglied['email'] ?? '').'>, Mitglied Nr. '.(int) ($mitglied['id'] ?? 0),
		);

		if ('' !== (string) ($kontext['url'] ?? '')) {
			$zeilen[] = 'Seite: '.(string) $kontext['url'];
		}

		$zeilen[] = 'Eine Antwort auf diese E-Mail geht direkt an '.$name.'.';

		return implode("\n", $zeilen);
	}

	/**
	 * Bereinigt eine Betreffzeile.
	 *
	 * Zeilenumbrüche und Steuerzeichen fallen weg — in einem Mailkopf wären
	 * sie ein Einfallstor, um weitere Kopfzeilen unterzuschieben. Danach wird
	 * auf self::BETREFF_LAENGE Zeichen gekürzt.
	 *
	 * @param string $betreff Rohwert aus dem Formular
	 *
	 * @return string Einzeilig, ohne Steuerzeichen, höchstens 200 Zeichen
	 */
	public static function bereinigeBetreff(string $betreff): string
	{
		$betreff = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $betreff);
		$betreff = trim((string) preg_replace('/\s{2,}/u', ' ', $betreff));

		return mb_substr($betreff, 0, self::BETREFF_LAENGE);
	}

	/**
	 * Bereinigt einen Nachrichtentext.
	 *
	 * Zeilenenden werden vereinheitlicht, Steuerzeichen außer Zeilenumbruch
	 * und Tabulator entfernt, der Text auf self::TEXT_LAENGE Zeichen gekürzt.
	 * Ungültiges UTF-8 gilt als leer.
	 *
	 * @param string $text Rohwert aus dem Formular
	 *
	 * @return string Bereinigter Text
	 */
	public static function bereinigeText(string $text): string
	{
		if (!mb_check_encoding($text, 'UTF-8')) {
			return '';
		}

		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		$text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

		return trim(mb_substr($text, 0, self::TEXT_LAENGE));
	}

	/**
	 * Prüft Betreff und Text nach dem Bereinigen.
	 *
	 * @param string $betreff Bereinigter Betreff
	 * @param string $text    Bereinigter Text
	 *
	 * @return string|null Fehlermeldung für das Formular, null wenn alles paßt
	 */
	public static function eingabefehler(string $betreff, string $text): ?string
	{
		if ('' === $betreff) {
			return 'Bitte geben Sie einen Betreff an.';
		}

		if (mb_strlen($text) < self::TEXT_MINDESTENS) {
			return 'Bitte beschreiben Sie kurz, was nicht stimmt.';
		}

		return null;
	}

	/**
	 * Prüft, ob das Formular unverändert abgeschickt wurde (ab 1.54.0).
	 *
	 * „Wird nichts im Formular verändert, dann soll auch nichts abgesendet
	 * werden" (Frank, 06.10.2026): Stimmen Betreff UND Text mit der Vorbelegung
	 * überein, enthält die Nachricht nichts, was der Empfänger nicht schon
	 * wüsste. Die Vorbelegung wird hier aus dem signierten Kontext neu
	 * gebildet — so wie link() sie erzeugt hat —, statt sie aus dem Formular
	 * zu übernehmen. Verglichen wird nach derselben Bereinigung wie beim
	 * Versand; Zeilenenden und Leerraum am Rand zählen also nicht.
	 *
	 * Das Skript prüft dasselbe schon im Browser; hier steht die Regel, damit
	 * sie sich nicht umgehen lässt.
	 *
	 * @param array<string,mixed> $kontext      Entschlüsselter Kontext (mit `empfaenger`)
	 * @param string              $mitgliedName Name des Mitglieds, wie in der Grußzeile
	 * @param string              $betreff      Abgeschickter Betreff
	 * @param string              $text         Abgeschickter Text
	 *
	 * @return bool true, wenn weder Betreff noch Text geändert wurden
	 */
	public static function unveraendert(array $kontext, string $mitgliedName, string $betreff, string $text): bool
	{
		$empfaenger = array_values(array_filter((array) ($kontext['empfaenger'] ?? array()), 'is_array'));
		$vorlage = self::vorlage($kontext, $empfaenger, $mitgliedName);

		return self::bereinigeBetreff($betreff) === self::bereinigeBetreff(self::betreff($kontext))
			&& self::bereinigeText($text) === self::bereinigeText($vorlage['text']);
	}

	/**
	 * Signiert einen Kontext für die Reise durch den Browser.
	 *
	 * Der Kontext wird als JSON in Base64 (URL-sicher) verpackt und mit HMAC-
	 * SHA256 unterschrieben. Base64 dient nicht der Geheimhaltung, sondern
	 * hält Anführungszeichen und Klammern aus dem HTML-Attribut heraus.
	 *
	 * @param array<string,mixed> $kontext   Kontext samt Empfängern
	 * @param string              $geheimnis Schlüssel (kernel.secret)
	 *
	 * @return array{kontext:string,signatur:string}
	 */
	public static function signiere(array $kontext, string $geheimnis): array
	{
		$daten = rtrim(strtr(base64_encode((string) json_encode($kontext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

		return array('kontext' => $daten, 'signatur' => hash_hmac('sha256', $daten, $geheimnis));
	}

	/**
	 * Prüft die Signatur eines Kontexts und packt ihn aus.
	 *
	 * Verglichen wird mit hash_equals(), also in konstanter Zeit.
	 *
	 * @param string $kontext   Base64-Wert aus dem Formular
	 * @param string $signatur  Signatur aus dem Formular
	 * @param string $geheimnis Schlüssel (kernel.secret)
	 *
	 * @return array<string,mixed>|null Der Kontext, null bei falscher oder
	 *                                  fehlender Signatur oder kaputten Daten
	 */
	public static function entschluessele(string $kontext, string $signatur, string $geheimnis): ?array
	{
		if ('' === $kontext || '' === $signatur || '' === $geheimnis) {
			return null;
		}

		if (!hash_equals(hash_hmac('sha256', $kontext, $geheimnis), $signatur)) {
			return null;
		}

		$json = base64_decode(strtr($kontext, '-_', '+/'), true);
		$daten = false === $json ? null : json_decode($json, true);

		return \is_array($daten) ? $daten : null;
	}

	/**
	 * Prüft eine E-Mail-Adresse auf die Form.
	 *
	 * @param string $adresse Adresse
	 *
	 * @return bool true, wenn sie als Adresse taugt
	 */
	public static function adresseGueltig(string $adresse): bool
	{
		return '' !== $adresse && false !== filter_var($adresse, FILTER_VALIDATE_EMAIL);
	}

	/**
	 * Macht aus einem Text eine einzelne, bereinigte Zeile.
	 *
	 * Für alles, was aus der Schnittstelle in den Kontext wandert:
	 * Turniernamen, Spielernamen, Kennziffern.
	 *
	 * @param string $text Rohwert
	 *
	 * @return string Einzeilig, ohne Steuerzeichen, getrimmt
	 */
	protected static function zeile(string $text): string
	{
		return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text)));
	}

	/**
	 * Bereinigt einen Namen für den Mailkopf.
	 *
	 * Spitze Klammern, Anführungszeichen und Kommas fallen weg: Contao\Email
	 * zerlegt „Name <adresse>" an der Klammer, und ein Komma trennt dort
	 * Empfänger voneinander.
	 *
	 * @param string $name Rohwert
	 *
	 * @return string Name ohne <>",; und Steuerzeichen
	 */
	protected static function name(string $name): string
	{
		return self::zeile(str_replace(array('<', '>', '"', ',', ';'), ' ', $name));
	}

	/**
	 * Füllt einen Text mit Leerzeichen auf eine Breite in Zeichen auf.
	 *
	 * str_pad() zählt Bytes — ein Umlaut im Etikett verschöbe die Spalte.
	 *
	 * @param string $text   Text
	 * @param int    $breite Breite in Zeichen
	 *
	 * @return string Aufgefüllter Text
	 */
	protected static function fuelle(string $text, int $breite): string
	{
		return $text.str_repeat(' ', max(1, $breite - mb_strlen($text)));
	}

	/**
	 * Formt Beginn und Ende eines Turniers zu „TT.MM.JJJJ – TT.MM.JJJJ".
	 *
	 * @param string $von Beginn im Format JJJJ-MM-TT, darf leer sein
	 * @param string $bis Ende im Format JJJJ-MM-TT, darf leer sein
	 *
	 * @return string Zeitraum, ein einzelnes Datum oder leer
	 */
	protected static function zeitraum(string $von, string $bis): string
	{
		$datum = static function (string $wert): string {
			return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $wert, $t) ? $t[3].'.'.$t[2].'.'.$t[1] : '';
		};

		$von = $datum($von);
		$bis = $datum($bis);

		if ('' !== $von && '' !== $bis && $von !== $bis) {
			return $von.' – '.$bis;
		}

		return '' !== $bis ? $bis : $von;
	}

	// ═════════════════════════════════════════════════════════════════════
	//  Mit Contao: Link, Dialog, Versand
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Liefert den Link „Reklamation" für eine Ansicht — oder nichts.
	 *
	 * Ausgegeben wird er nur, wenn
	 * - ein Mitglied angemeldet ist und eine gültige E-Mail-Adresse hat
	 *   (an sie gehen die Antworten), und
	 * - in den Einstellungen eine Adresse für den DSB-Admin steht (er bekommt
	 *   jede Reklamation in Blindkopie und springt ein, wo kein Referent
	 *   genannt ist).
	 *
	 * Beim ersten Link der Seite wird außerdem der Dialog mit dem Formular an
	 * das Ende der Seite gehängt ($GLOBALS['TL_BODY']) und das Skript
	 * eingebunden. Alles, was der Dialog für diesen Link braucht, trägt der
	 * Link selbst in data-Attributen.
	 *
	 * @param array<string,mixed>      $kontext  Kontext aus einer der fuer…()-Methoden
	 * @param array<string,mixed>|null $mitglied Für Prüfstände; ohne Angabe das
	 *                                           angemeldete Mitglied
	 *
	 * @return string HTML des Links, '' wenn er nicht erscheinen soll
	 */
	public static function link(array $kontext, ?array $mitglied = null): string
	{
		$mitglied = $mitglied ?? self::mitglied();
		$admin = self::admin();

		if (null === $mitglied || !self::adresseGueltig((string) ($mitglied['email'] ?? '')) || !self::adresseGueltig($admin['email'])) {
			return '';
		}

		$kontext['empfaenger'] = self::empfaenger((array) ($kontext['referenten'] ?? array()), $admin);
		unset($kontext['referenten']);

		if (!$kontext['empfaenger']) {
			return '';
		}

		$vorlage = self::vorlage($kontext, $kontext['empfaenger'], (string) $mitglied['name']);
		$signiert = self::signiere($kontext, self::geheimnis());
		$namen = implode(', ', array_column($kontext['empfaenger'], 'name'));

		if (!self::$dialogAusgegeben) {
			self::$dialogAusgegeben = true;
			$GLOBALS['TL_BODY']['wertungsportal_reklamation'] = self::dialog($mitglied);
			$GLOBALS['TL_JAVASCRIPT']['wertungsportal_reklamation'] = 'bundles/contaowertungsportal/js/reklamation.js';
		}

		// data-kopie steuert im Dialog den Hinweis auf die Blindkopie — geht die
		// Reklamation direkt an den Admin, gibt es keine
		return sprintf(
			'<button type="button" class="wp-reklamation-link" data-kontext="%s" data-signatur="%s" data-betreff="%s" data-text="%s" data-cursor="%d" data-empfaenger="%s" data-kopie="%d" title="%s">Reklamation</button>',
			self::attribut($signiert['kontext']),
			self::attribut($signiert['signatur']),
			self::attribut(self::betreff($kontext)),
			self::attribut($vorlage['text']),
			$vorlage['cursor'],
			self::attribut($namen),
			'' !== self::blindkopie($kontext['empfaenger'], $admin) ? 1 : 0,
			self::attribut('Eine Reklamation zu diesen Daten an '.$namen.' schicken')
		);
	}

	/**
	 * Nimmt eine Reklamation entgegen: prüfen, verschicken, speichern.
	 *
	 * Der Ablauf, jeweils mit HTTP-Status und Meldung für das Formular:
	 *
	 * 1. Admin-Adresse eingestellt? Sonst 503.
	 * 2. Kontext echt (Signatur)? Sonst 400.
	 * 3. Betreff und Text brauchbar und gegenüber der Vorbelegung verändert?
	 *    Sonst 422.
	 * 4. Bremse: höchstens self::HOECHSTZAHL je self::ZEITFENSTER? Sonst 429.
	 * 5. Verschicken — an die Empfänger aus dem Kontext, Blindkopie an den
	 *    Admin (sofern er nicht selbst Empfänger ist), Antwort an das Mitglied.
	 * 6. Speichern, auch wenn der Versand scheiterte — das Backend soll
	 *    gerade die gescheiterten Fälle zeigen. Dann 502.
	 *
	 * Die Anmeldung prüft der Controller; hier kommt nur noch ein Mitglied an.
	 *
	 * @param array<string,string> $eingabe  kontext, signatur, betreff, text
	 * @param array<string,mixed>  $mitglied id, name, email des angemeldeten Mitglieds
	 * @param string               $host     Name der Website für die Fußzeile
	 *
	 * @return array{status:int,daten:array<string,mixed>} Antwort für den Controller
	 */
	public static function einreichen(array $eingabe, array $mitglied, string $host): array
	{
		$admin = self::admin();

		if (!self::adresseGueltig($admin['email'])) {
			return self::antwort(503, 'Reklamationen sind zurzeit nicht eingerichtet.');
		}

		if (!self::adresseGueltig((string) ($mitglied['email'] ?? ''))) {
			return self::antwort(422, 'In Ihrem Mitgliedskonto ist keine gültige E-Mail-Adresse hinterlegt. An sie gehen die Antworten — bitte ergänzen Sie sie zuerst.');
		}

		$kontext = self::entschluessele((string) ($eingabe['kontext'] ?? ''), (string) ($eingabe['signatur'] ?? ''), self::geheimnis());

		if (null === $kontext) {
			return self::antwort(400, 'Das Formular ist ungültig. Bitte laden Sie die Seite neu und versuchen Sie es noch einmal.');
		}

		$betreff = self::bereinigeBetreff((string) ($eingabe['betreff'] ?? ''));
		$text = self::bereinigeText((string) ($eingabe['text'] ?? ''));
		$fehler = self::eingabefehler($betreff, $text);

		if (null !== $fehler) {
			return self::antwort(422, $fehler);
		}

		// Unverändert abgeschickt: nichts senden (ab 1.54.0)
		if (self::unveraendert($kontext, (string) ($mitglied['name'] ?? ''), $betreff, $text)) {
			return self::antwort(422, 'Sie haben das Formular nicht verändert. Bitte ergänzen Sie unter „'.self::MARKE.'“, was nicht stimmt.');
		}

		$bisher = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalReklamationenModel::countBy(
			array('memberId=?', 'datum>?'),
			array((int) $mitglied['id'], time() - self::ZEITFENSTER)
		);

		if ($bisher >= self::HOECHSTZAHL) {
			return self::antwort(429, 'Sie haben in der letzten Stunde schon '.self::HOECHSTZAHL.' Reklamationen geschickt. Bitte versuchen Sie es später noch einmal.');
		}

		// Die Empfänger stehen signiert im Kontext. Sollte die Liste leer sein
		// (ältere Seite, geänderte Einstellungen), springt der Admin ein
		$empfaenger = self::empfaenger(array_filter((array) ($kontext['empfaenger'] ?? array()), 'is_array'), $admin);
		$zeit = time();
		$volltext = $text."\n\n".self::fusszeile($mitglied, $kontext, $host, $zeit);

		$bcc = self::blindkopie($empfaenger, $admin);

		$versandfehler = self::verschicke($empfaenger, $bcc, $mitglied, $betreff, $volltext);

		self::speichere($zeit, $mitglied, $kontext, $empfaenger, $bcc, $betreff, $volltext, $versandfehler);

		if ('' !== $versandfehler) {
			return self::antwort(502, 'Ihre Reklamation konnte nicht verschickt werden. Sie ist aber gespeichert, und der DSB wird sich darum kümmern.');
		}

		return array(
			'status' => 200,
			'daten'  => array(
				'ok'      => true,
				'meldung' => 'Vielen Dank! Ihre Reklamation ist an '.implode(', ', array_column($empfaenger, 'name')).' unterwegs. Antworten gehen an '.$mitglied['email'].'.',
			),
		);
	}

	/**
	 * Liefert das angemeldete Mitglied — oder null.
	 *
	 * @return array<string,mixed>|null id, name, email, username; null ohne Anmeldung
	 */
	public static function mitglied(): ?array
	{
		try {
			$objUser = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getMitglied();
		} catch (\Throwable $e) {
			return null;
		}

		if (!$objUser instanceof \Contao\FrontendUser || (int) $objUser->id <= 0) {
			return null;
		}

		return array(
			'id'       => (int) $objUser->id,
			'name'     => trim((string) $objUser->firstname.' '.(string) $objUser->lastname),
			'email'    => trim((string) $objUser->email),
			'username' => (string) $objUser->username,
		);
	}

	/**
	 * Liefert Name und Adresse des DSB-Admins aus den Einstellungen.
	 *
	 * Ohne Namen gilt der Absendername der Reklamationen (absender()).
	 *
	 * @return array{name:string,email:string}
	 */
	public static function admin(): array
	{
		$email = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_reklamation_email'] ?? ''));
		$name = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_reklamation_name'] ?? ''));

		if ('' === $name && '' !== $email) {
			$name = self::absender()['name'];
		}

		return array('name' => $name, 'email' => $email);
	}

	/**
	 * Liefert Absenderadresse und -namen der Reklamationsmails (ab 1.53.0).
	 *
	 * Vorrang haben die eigenen Einstellungen unter „Reklamationen"
	 * (wertungsportal_reklamation_absender, _absendername). Jedes leere Feld
	 * fällt einzeln auf die Einstellung „E-Mail-Versand" zurück — die gilt
	 * sonst für alle Mails des Bundles, etwa die Schlüssel-Mail der
	 * Vereinslisten-Schnittstelle („DSB | Registrierung DWZ-Abfrage"), und
	 * passt deshalb nicht zu einer Reklamation.
	 *
	 * @return array{email:string,name:string} Adresse und Name, nie leer, wenn
	 *                                         wenigstens die allgemeine
	 *                                         Einstellung oder adminEmail gesetzt ist
	 */
	public static function absender(): array
	{
		$email = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_reklamation_absender'] ?? ''));
		$name = trim((string) ($GLOBALS['TL_CONFIG']['wertungsportal_reklamation_absendername'] ?? ''));

		return array(
			'email' => '' !== $email ? $email : \Schachbulle\ContaoWertungsportalBundle\Classes\TokenRegistrierung::absenderadresse(),
			'name'  => '' !== $name ? $name : \Schachbulle\ContaoWertungsportalBundle\Classes\TokenRegistrierung::absendername(),
		);
	}

	/**
	 * Liefert den Schlüssel für die Signatur: das Kernel-Secret.
	 *
	 * Es ist in jeder Installation gesetzt, bleibt über Neustarts gleich und
	 * verläßt den Server nie.
	 *
	 * @return string Schlüssel, '' wenn er nicht zu bekommen ist
	 */
	protected static function geheimnis(): string
	{
		try {
			return (string) \Contao\System::getContainer()->getParameter('kernel.secret');
		} catch (\Throwable $e) {
			return '';
		}
	}

	/**
	 * Verschickt die Reklamation.
	 *
	 * Absender ist der Absender der Reklamationen (absender(): eigene
	 * Einstellung, sonst „E-Mail-Versand"), Antworten gehen an das Mitglied.
	 * Die Empfänger gehen als Liste an Contao\Email: So werden Namen nicht an
	 * Kommas zerlegt.
	 *
	 * @param array<int,array<string,string>> $empfaenger Empfänger
	 * @param string                           $bcc        Adresse für die Blindkopie, '' für keine
	 * @param array<string,mixed>              $mitglied   Mitglied
	 * @param string                           $betreff    Betreff
	 * @param string                           $text       Text samt Fußzeile
	 *
	 * @return string Fehlermeldung, '' bei Erfolg
	 */
	protected static function verschicke(array $empfaenger, string $bcc, array $mitglied, string $betreff, string $text): string
	{
		try {
			$absender = self::absender();
			$objEmail = new \Contao\Email();
			$objEmail->from = $absender['email'];
			$objEmail->fromName = $absender['name'];
			$objEmail->subject = $betreff;
			$objEmail->text = $text;
			$objEmail->replyTo(array(self::name((string) $mitglied['name']).' <'.$mitglied['email'].'>'));

			if ('' !== $bcc) {
				$objEmail->sendBcc(array($bcc));
			}

			$liste = array();

			foreach ($empfaenger as $e) {
				$liste[] = ('' !== $e['name'] ? $e['name'].' ' : '').'<'.$e['email'].'>';
			}

			if (!$objEmail->sendTo($liste)) {
				return 'Der Mailversand meldete einen Fehler.';
			}
		} catch (\Throwable $e) {
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Reklamation konnte nicht verschickt werden: '.$e->getMessage(), __METHOD__, 'ERROR');

			return mb_substr($e->getMessage(), 0, 1000);
		}

		return '';
	}

	/**
	 * Legt die Reklamation im Backend-Modul „Reklamationen" ab.
	 *
	 * Ein Fehler beim Speichern darf die Antwort an das Mitglied nicht
	 * verhindern — die Nachricht ist dann ja schon unterwegs. Er landet im
	 * Systemprotokoll.
	 *
	 * @param int                              $zeit          Zeitpunkt
	 * @param array<string,mixed>              $mitglied      Mitglied
	 * @param array<string,mixed>              $kontext       Kontext
	 * @param array<int,array<string,string>> $empfaenger    Empfänger
	 * @param string                           $bcc           Blindkopie
	 * @param string                           $betreff       Betreff
	 * @param string                           $text          Text samt Fußzeile
	 * @param string                           $versandfehler Fehlermeldung, '' bei Erfolg
	 *
	 * @return void
	 */
	protected static function speichere(int $zeit, array $mitglied, array $kontext, array $empfaenger, string $bcc, string $betreff, string $text, string $versandfehler): void
	{
		try {
			$objReklamation = new \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalReklamationenModel();
			$objReklamation->tstamp = $zeit;
			$objReklamation->datum = $zeit;
			$objReklamation->memberId = (int) $mitglied['id'];
			$objReklamation->memberName = mb_substr((string) $mitglied['name'], 0, 255);
			$objReklamation->memberEmail = mb_substr((string) $mitglied['email'], 0, 255);
			$objReklamation->empfaengerName = mb_substr(implode(', ', array_column($empfaenger, 'name')), 0, 255);
			$objReklamation->empfaengerEmail = mb_substr(implode(', ', array_column($empfaenger, 'email')), 0, 1000);
			$objReklamation->empfaengerArt = (string) ($empfaenger[0]['art'] ?? 'admin');
			$objReklamation->bcc = $bcc;
			$objReklamation->bereich = mb_substr((string) ($kontext['bereich'] ?? ''), 0, 32);
			$objReklamation->url = mb_substr((string) ($kontext['url'] ?? ''), 0, 2000);
			$objReklamation->turnierUuid = mb_substr((string) ($kontext['turnier']['uuid'] ?? ''), 0, 64);
			$objReklamation->turnierName = mb_substr((string) ($kontext['turnier']['name'] ?? ''), 0, 255);
			$objReklamation->spielerId = mb_substr((string) ($kontext['spieler']['id'] ?? ''), 0, 64);
			$objReklamation->spielerName = mb_substr((string) ($kontext['spieler']['name'] ?? ''), 0, 255);
			$objReklamation->betreff = $betreff;
			$objReklamation->text = $text;
			$objReklamation->gesendet = '' === $versandfehler ? '1' : '';
			$objReklamation->fehler = $versandfehler;
			$objReklamation->save();
		} catch (\Throwable $e) {
			\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::systemlog('Reklamation konnte nicht gespeichert werden: '.$e->getMessage(), __METHOD__, 'ERROR');
		}
	}

	/**
	 * Baut den Dialog mit dem Formular.
	 *
	 * Ein natives `<dialog>`: Es bringt Abdunklung, Fokusfang und Schließen per
	 * Escape selbst mit und braucht keine Bibliothek — das Bundle muß unter
	 * Contao 4.13 wie 5 und mit jedem Theme laufen.
	 *
	 * Betreff, Text und Empfänger füllt das Skript beim Öffnen aus dem
	 * geklickten Link. Das Anfragetoken gehört in das Formular, sonst weist
	 * Contao das Absenden als Fälschung ab.
	 *
	 * @param array<string,mixed> $mitglied Angemeldetes Mitglied (für den Hinweis
	 *                                      auf die Antwortadresse)
	 *
	 * @return string HTML des Dialogs
	 */
	protected static function dialog(array $mitglied): string
	{
		$container = \Contao\System::getContainer();

		try {
			$ziel = $container->get('router')->generate(self::ROUTE);
		} catch (\Throwable $e) {
			$ziel = '/wertungsportal-api/reklamation';
		}

		$token = (string) $container->get('contao.csrf.token_manager')->getDefaultTokenValue();
		$e = static function (string $wert): string {
			return htmlspecialchars($wert, ENT_QUOTES, 'UTF-8');
		};

		return '<dialog id="wp-reklamation" class="wp-reklamation" aria-labelledby="wp-reklamation-titel">'
			.'<form class="wp-reklamation-form" method="post" action="'.$e($ziel).'" novalidate>'
			.'<button type="button" class="wp-reklamation-schliessen" aria-label="Schließen" title="Schließen">×</button>'
			.'<h2 id="wp-reklamation-titel">Reklamation</h2>'
			.'<p class="wp-reklamation-hinweis">An: <strong data-feld="empfaenger"></strong><br>'
			.'Antworten gehen an Ihre Adresse <strong>'.$e((string) $mitglied['email']).'</strong>.<span data-feld="kopie"> Eine Kopie erhält der Deutsche Schachbund.</span></p>'
			.'<input type="hidden" name="REQUEST_TOKEN" value="'.$e($token).'">'
			.'<input type="hidden" name="kontext" value="">'
			.'<input type="hidden" name="signatur" value="">'
			.'<label for="wp-reklamation-betreff">Betreff</label>'
			.'<input type="text" id="wp-reklamation-betreff" name="betreff" maxlength="'.self::BETREFF_LAENGE.'" required>'
			.'<label for="wp-reklamation-text">Nachricht</label>'
			.'<textarea id="wp-reklamation-text" name="text" rows="16" maxlength="'.self::TEXT_LAENGE.'" required></textarea>'
			.'<p class="wp-reklamation-meldung" role="status" aria-live="polite"></p>'
			.'<div class="wp-reklamation-knoepfe">'
			.'<button type="submit" class="wp-reklamation-senden">Absenden</button>'
			.'<button type="button" class="wp-reklamation-abbrechen">Abbrechen</button>'
			.'</div>'
			.'</form>'
			.'</dialog>';
	}

	/**
	 * Bereitet einen Wert für ein HTML-Attribut vor.
	 *
	 * Neben dem üblichen Maskieren werden geschweifte Klammern als Entitäten
	 * geschrieben: Contao ersetzt in der fertigen Seite Insert-Tags, und ein
	 * „{{" in einem Turniernamen würde sonst als solcher gelesen.
	 *
	 * @param string $wert Wert
	 *
	 * @return string Maskierter Wert
	 */
	protected static function attribut(string $wert): string
	{
		return str_replace(array('{', '}'), array('&#123;', '&#125;'), htmlspecialchars($wert, ENT_QUOTES, 'UTF-8'));
	}

	/**
	 * Baut eine Fehlerantwort für den Controller.
	 *
	 * @param int    $status  HTTP-Status
	 * @param string $meldung Meldung für das Formular
	 *
	 * @return array{status:int,daten:array<string,mixed>}
	 */
	protected static function antwort(int $status, string $meldung): array
	{
		return array('status' => $status, 'daten' => array('ok' => false, 'fehler' => $meldung));
	}
}
