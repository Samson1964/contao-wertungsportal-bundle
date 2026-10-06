<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Der zuständige Wertungsreferent einer Ansicht (ab 1.53.0).
 *
 * Zwei Quellen:
 * - **API**: nu liefert einen Referenten nur im Turnierkopf (referentFirstname,
 *   referentLastname, referentEmail — der Auswerter). Bei Spielern, Vereinen
 *   und Verbänden liefert nu keinen (geprüft an den Antworten im
 *   Zwischenspeicher, 30.09.2026).
 * - **Lokal**: das Backend-Modul „Referenten" (Helper\Referentenbaum), über
 *   die Kennziffer des Vereins, Verbandes oder Turniers — bei fehlendem
 *   Eintrag die nächste Ebene darüber.
 *
 * Regeln (Frank, 30.09.2026):
 * - Liefert nu keinen Referenten, gilt der lokale.
 * - Liefert nu einen, der sich vom lokalen unterscheidet, steht der lokale
 *   zusätzlich da.
 * - Jeder Eintrag trägt seine Herkunft: „API" oder „Lokal".
 * - Für Reklamationen gilt der Referent von nu; fehlt er, die lokalen.
 *
 * Aufteilung: ausTurnier(), gleich(), eintraege() und empfaenger() kommen
 * ohne Contao aus (tests/Helper/ZustaendigkeitTest.php); lokal() und html()
 * brauchen Contao.
 */
class Zustaendigkeit
{
	/**
	 * Herkunft: nu (Wertungsportal-Schnittstelle).
	 */
	public const API = 'api';

	/**
	 * Herkunft: Backend-Modul „Referenten".
	 */
	public const LOKAL = 'lokal';

	// ═════════════════════════════════════════════════════════════════════
	//  Ohne Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Liest den Referenten aus einem Turnierkopf von nu.
	 *
	 * nu liefert den Kopf je nach Abfrage flach (Turnierinfo) oder unter
	 * `tournament` (Turnierauswertung); beides wird angenommen. Ohne
	 * Nachnamen gilt der Referent als nicht geliefert — so wie bisher bei
	 * den Reklamationen.
	 *
	 * @param array<string,mixed> $turnier Turnierkopf oder Antwort mit `tournament`
	 *
	 * @return array<string,mixed>|null Eintrag mit quelle, name, vorname,
	 *                                  nachname, emails (Klartext), versand;
	 *                                  null, wenn nu keinen Referenten nennt
	 */
	public static function ausTurnier(array $turnier): ?array
	{
		if (isset($turnier['tournament']) && is_array($turnier['tournament'])) {
			$turnier = $turnier['tournament'];
		}

		$nachname = trim((string) ($turnier['referentLastname'] ?? ''));

		if ('' === $nachname) {
			return null;
		}

		$vorname = trim((string) ($turnier['referentFirstname'] ?? ''));
		$email = trim((string) ($turnier['referentEmail'] ?? ''));
		$email = false !== filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

		return array(
			'quelle'   => self::API,
			'name'     => trim($vorname.' '.$nachname),
			'vorname'  => $vorname,
			'nachname' => $nachname,
			'emails'   => '' !== $email ? array($email) : array(),
			'versand'  => $email,
		);
	}

	/**
	 * Macht aus aufbereiteten Referenten (Referentenbaum) lokale Einträge.
	 *
	 * @param array<int,array<string,mixed>> $personen    Personen aus Referentenbaum::zustaendig()
	 * @param string                         $verband     Name des Verbandes, für den sie eingetragen sind
	 * @param bool                           $ersatzweise true, wenn sie über eine übergeordnete Ebene gelten
	 *
	 * @return list<array<string,mixed>> Einträge mit quelle, name, vorname,
	 *                                   nachname, emails (Klartext), html
	 *                                   (verschleierte Links), versand,
	 *                                   verband, ersatzweise
	 */
	public static function ausReferenten(array $personen, string $verband = '', bool $ersatzweise = false): array
	{
		$eintraege = array();

		foreach ($personen as $person) {
			$eintraege[] = array(
				'quelle'      => self::LOKAL,
				'name'        => trim((string) ($person['name'] ?? '')),
				'vorname'     => trim((string) ($person['vorname'] ?? '')),
				'nachname'    => trim((string) ($person['nachname'] ?? '')),
				'emails'      => array_values((array) ($person['klartext'] ?? array())),
				'abgleich'    => array_values((array) ($person['abgleich'] ?? $person['klartext'] ?? array())),
				'html'        => array_values((array) ($person['emails'] ?? array())),
				'versand'     => trim((string) ($person['adresse'] ?? '')),
				'verband'     => $verband,
				'ersatzweise' => $ersatzweise,
			);
		}

		return $eintraege;
	}

	/**
	 * Prüft, ob der Referent von nu derselbe ist wie ein lokaler.
	 *
	 * Gleich heißt: dieselbe E-Mail-Adresse (die Funktions-E-Mail, eine der
	 * Adressen aus der Adressverwaltung in `abgleich` oder die
	 * Versandadresse) oder derselbe Vor- und Nachname — jeweils ohne Groß-
	 * und Kleinschreibung und mit zusammengefasstem Leerraum. Ein Titel
	 * („Dr.") zählt nicht mit, er steht nur im lokalen Namen.
	 *
	 * @param array<string,mixed> $api   Eintrag aus ausTurnier()
	 * @param array<string,mixed> $lokal Eintrag aus ausReferenten()
	 *
	 * @return bool true, wenn es dieselbe Person ist
	 */
	public static function gleich(array $api, array $lokal): bool
	{
		$normal = static function ($wert): string {
			return self::normal((string) $wert);
		};

		// Lokal zählen alle bekannten Adressen der Person (`abgleich`), auch
		// die nie angezeigten privaten — nu nennt meist gerade die
		$apiMails = array_map($normal, array_merge((array) $api['emails'], array((string) $api['versand'])));
		$lokalMails = array_map($normal, array_merge((array) $lokal['emails'], (array) ($lokal['abgleich'] ?? array()), array((string) $lokal['versand'])));

		if (array_intersect(array_filter($apiMails), array_filter($lokalMails))) {
			return true;
		}

		$apiName = self::normal($api['vorname'].' '.$api['nachname']);

		return '' !== trim($apiName) && $apiName === self::normal($lokal['vorname'].' '.$lokal['nachname']);
	}

	/**
	 * Stellt die auszugebenden Einträge zusammen.
	 *
	 * - kein Referent von nu → die lokalen
	 * - Referent von nu, der mit einem lokalen übereinstimmt → nur der von nu
	 * - Referent von nu, der mit keinem lokalen übereinstimmt → der von nu,
	 *   dahinter die lokalen
	 *
	 * @param array<string,mixed>|null        $api   Eintrag aus ausTurnier() oder null
	 * @param array<int,array<string,mixed>> $lokal Einträge aus ausReferenten()
	 *
	 * @return list<array<string,mixed>> Einträge in Ausgabereihenfolge, leer
	 *                                   wenn es weder den einen noch den anderen gibt
	 */
	public static function eintraege(?array $api, array $lokal): array
	{
		$lokal = array_values($lokal);

		if (null === $api) {
			return $lokal;
		}

		foreach ($lokal as $eintrag) {
			if (self::gleich($api, $eintrag)) {
				return array($api);
			}
		}

		return array_merge(array($api), $lokal);
	}

	/**
	 * Wählt die Empfänger einer Reklamation aus den Einträgen.
	 *
	 * Der Referent von nu hat Vorrang — er hat das Turnier ausgewertet. Ohne
	 * ihn gehen Reklamationen an die lokalen Referenten; ohne beide findet
	 * Reklamation::empfaenger() den DSB-Admin.
	 *
	 * @param array<int,array<string,mixed>> $eintraege Einträge aus eintraege()
	 *
	 * @return list<array{name:string,email:string}> Empfänger im Format von
	 *                                               Reklamation::fuer…()
	 */
	public static function empfaenger(array $eintraege): array
	{
		$api = array_values(array_filter($eintraege, static function (array $e): bool {
			return self::API === $e['quelle'];
		}));

		$wahl = $api ?: array_values($eintraege);

		return array_map(static function (array $e): array {
			return array('name' => (string) $e['name'], 'email' => (string) $e['versand']);
		}, $wahl);
	}

	/**
	 * Vereinheitlicht einen Namen oder eine Adresse für den Vergleich.
	 *
	 * @param string $wert Name oder E-Mail-Adresse
	 *
	 * @return string Kleinschreibung, Leerraum zusammengefasst und gekürzt
	 */
	protected static function normal(string $wert): string
	{
		return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($wert)));
	}

	// ═════════════════════════════════════════════════════════════════════
	//  Mit Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Liefert die lokalen Einträge für eine Kennziffer.
	 *
	 * @param string $vkz Kennziffer eines Vereins, Verbandes oder Turniers
	 *                    (ganz oder verkürzt, 640 wie 64000); '' für keine
	 *
	 * @return list<array<string,mixed>> Einträge aus ausReferenten(); leer,
	 *                                   wenn niemand eingetragen ist
	 */
	public static function lokal(string $vkz): array
	{
		if ('' === trim($vkz)) {
			return array();
		}

		$zustaendig = Referentenbaum::zustaendig($vkz);

		return self::ausReferenten((array) $zustaendig['referenten'], (string) $zustaendig['name'], (bool) $zustaendig['ersatzweise']);
	}

	/**
	 * Baut die Ausgabe: je Eintrag „Name / E-Mail" und die Herkunft.
	 *
	 * Die E-Mail-Adressen erscheinen verschleiert (StringUtil::encodeEmail),
	 * mehrere mit Komma. Die Herkunft steht als kleines Zeichen „API" oder
	 * „Lokal" dahinter, mit einer Erklärung im title — bei lokalen, die über
	 * eine übergeordnete Ebene gelten, samt deren Namen.
	 *
	 * @param array<int,array<string,mixed>> $eintraege Einträge aus eintraege()
	 *
	 * @return string HTML, '' wenn es keine Einträge gibt
	 */
	public static function html(array $eintraege): string
	{
		$teile = array();

		foreach ($eintraege as $eintrag) {
			$links = array();

			if (self::LOKAL === $eintrag['quelle'] && isset($eintrag['html'])) {
				$links = (array) $eintrag['html'];
			} else {
				foreach ((array) $eintrag['emails'] as $email) {
					$links[] = \Contao\StringUtil::encodeEmail('<a href="mailto:'.$email.'">'.$email.'</a>');
				}
			}

			if (self::API === $eintrag['quelle']) {
				$marke = '<span class="wp-quelle wp-quelle-api" title="Angabe von nu (Wertungsportal)">API</span>';
			} else {
				$titel = 'Aus der Referentenliste des DSB'
					.(!empty($eintrag['ersatzweise']) && '' !== (string) ($eintrag['verband'] ?? '') ? ' — zuständig über '.$eintrag['verband'] : '');
				$marke = '<span class="wp-quelle wp-quelle-lokal" title="'.\Contao\StringUtil::specialchars($titel).'">Lokal</span>';
			}

			$teile[] = '<span class="wp-zustaendig">'
				.\Contao\StringUtil::specialchars((string) $eintrag['name'])
				.($links ? ' / '.implode(', ', $links) : '')
				.' '.$marke
				.'</span>';
		}

		return implode('<br>', $teile);
	}
}
