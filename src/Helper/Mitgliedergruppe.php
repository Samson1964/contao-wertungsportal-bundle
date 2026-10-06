<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Ordnet Contao-Mitgliedern die „Mitgliedergruppe für DSB-Mitglieder" zu
 * (ab 1.54.0).
 *
 * Regel (Frank, 06.10.2026):
 * - Stimmt tl_member.email mit tl_wertungsportal_persons.email1 oder email2
 *   eines Spielers überein → das Mitglied bekommt die Gruppe.
 * - Keine Übereinstimmung → die Gruppe wird dem Mitglied genommen.
 *
 * Verglichen wird ohne Groß- und Kleinschreibung und ohne Leerraum am Rand.
 * Andere Gruppen des Mitglieds bleiben unberührt. Die Gruppe wählt man unter
 * Wertungsportal → Einstellungen; ohne Auswahl geschieht nichts.
 *
 * Gelaufen wird monatlich (Cron\Mitgliedergruppenabgleich) oder von Hand über
 * `wertungsportal:mitgliedergruppe` — dort auch mit --dry-run zum Ansehen.
 *
 * **Sicherung:** Enthält der Spielerbestand keine einzige E-Mail-Adresse
 * (leere Tabelle nach einem missglückten Import), wird nichts geändert. Sonst
 * verlöre mit einem Lauf jedes Mitglied die Gruppe.
 *
 * Aufteilung: plan() und gruppen() kommen ohne Contao aus
 * (tests/Helper/MitgliedergruppeTest.php); abgleichen() liest und schreibt.
 */
class Mitgliedergruppe
{
	/**
	 * Name der Einstellung mit der ID der Mitgliedergruppe.
	 */
	public const EINSTELLUNG = 'wertungsportal_mitgliedergruppe';

	/**
	 * So viele Adressen gehen je Abfrage an die Spielertabelle. Jede Adresse
	 * steht zweimal in der Abfrage (email1 und email2).
	 */
	public const STAPEL = 400;

	// ═════════════════════════════════════════════════════════════════════
	//  Ohne Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Entscheidet für jedes Mitglied, ob es die Gruppe bekommt, verliert oder
	 * behält.
	 *
	 * @param array<int,array<string,mixed>> $mitglieder  Zeilen aus tl_member mit id, email und
	 *                                                    groups (serialisiert oder als Liste)
	 * @param array<int,string>              $dsbAdressen E-Mail-Adressen, die im Spielerbestand
	 *                                                    vorkommen (Schreibweise beliebig)
	 * @param int                            $gruppe      ID der Mitgliedergruppe
	 *
	 * @return array{hinzu: array<int,list<string>>, weg: array<int,list<string>>, unveraendert: int}
	 *               `hinzu` und `weg`: Mitglieds-ID => neue Gruppenliste (als
	 *               Zeichenketten, wie Contao sie speichert)
	 */
	public static function plan(array $mitglieder, array $dsbAdressen, int $gruppe): array
	{
		$plan = array('hinzu' => array(), 'weg' => array(), 'unveraendert' => 0);
		$bekannt = array();

		foreach ($dsbAdressen as $adresse) {
			$adresse = self::normal((string) $adresse);

			if ('' !== $adresse) {
				$bekannt[$adresse] = true;
			}
		}

		$kennung = (string) $gruppe;

		foreach ($mitglieder as $mitglied) {
			$id = (int) ($mitglied['id'] ?? 0);

			if ($id < 1) {
				continue;
			}

			$liste = self::gruppen($mitglied['groups'] ?? null);
			$hat = in_array($kennung, $liste, true);
			$email = self::normal((string) ($mitglied['email'] ?? ''));
			$soll = '' !== $email && isset($bekannt[$email]);

			if ($soll && !$hat) {
				$liste[] = $kennung;
				$plan['hinzu'][$id] = $liste;
			} elseif (!$soll && $hat) {
				$plan['weg'][$id] = array_values(array_filter($liste, static function (string $eintrag) use ($kennung): bool {
					return $eintrag !== $kennung;
				}));
			} else {
				++$plan['unveraendert'];
			}
		}

		return $plan;
	}

	/**
	 * Liest die Gruppenliste eines Mitglieds.
	 *
	 * Contao speichert tl_member.groups als serialisierte Liste von IDs
	 * (Zeichenketten); das Feld kann aber auch NULL, leer oder kaputt sein.
	 * Objekte werden beim Entpacken nie erzeugt.
	 *
	 * @param mixed $wert Rohwert aus der Datenbank oder schon eine Liste
	 *
	 * @return list<string> IDs als Zeichenketten, ohne leere und doppelte
	 */
	public static function gruppen($wert): array
	{
		if (is_string($wert) && '' !== $wert) {
			$wert = @unserialize($wert, array('allowed_classes' => false));
		}

		if (!is_array($wert)) {
			return array();
		}

		$liste = array();

		foreach ($wert as $eintrag) {
			if (is_scalar($eintrag) && (int) $eintrag > 0) {
				$liste[] = (string) (int) $eintrag;
			}
		}

		return array_values(array_unique($liste));
	}

	/**
	 * Vereinheitlicht eine E-Mail-Adresse für den Vergleich.
	 *
	 * @param string $adresse E-Mail-Adresse
	 *
	 * @return string Kleinschreibung, ohne Leerraum am Rand
	 */
	public static function normal(string $adresse): string
	{
		return mb_strtolower(trim($adresse));
	}

	// ═════════════════════════════════════════════════════════════════════
	//  Mit Contao
	// ═════════════════════════════════════════════════════════════════════

	/**
	 * Gleicht die Mitgliedergruppe mit dem Spielerbestand ab.
	 *
	 * Ablauf: Gruppe aus den Einstellungen → gibt es sie? → alle Mitglieder
	 * lesen → ihre Adressen stapelweise im Spielerbestand suchen (email1 und
	 * email2; die Sortierfolge der Datenbank vergleicht ohne Groß- und
	 * Kleinschreibung) → plan() → schreiben.
	 *
	 * Zustände im Ergebnis (`status`):
	 * - `aus`     keine Gruppe eingestellt — nichts getan
	 * - `fehlt`   die eingestellte Gruppe gibt es nicht (mehr) — nichts getan
	 * - `leer`    der Spielerbestand enthält keine E-Mail-Adresse — nichts
	 *             getan (Sicherung, siehe Klassenkommentar)
	 * - `fehler`  eine Tabelle war nicht lesbar — nichts getan, `grund` nennt sie
	 * - `ok`      abgeglichen (bei $trocken nur berechnet)
	 *
	 * Geschrieben wird nur die Spalte groups der betroffenen Mitglieder; der
	 * Zeitstempel tstamp bleibt, wie er ist, damit der Abgleich nicht als
	 * Bearbeitung des Kontos erscheint.
	 *
	 * @param bool $trocken true: nur berechnen, nichts schreiben
	 *
	 * @return array{status:string, gruppe:int, name:string, mitglieder:int, hinzu:list<int>, weg:list<int>, unveraendert:int, grund:string}
	 */
	public static function abgleichen(bool $trocken = false): array
	{
		$ergebnis = array('status' => 'aus', 'gruppe' => 0, 'name' => '', 'mitglieder' => 0, 'hinzu' => array(), 'weg' => array(), 'unveraendert' => 0, 'grund' => '');
		$gruppe = (int) ($GLOBALS['TL_CONFIG'][self::EINSTELLUNG] ?? 0);

		if ($gruppe < 1) {
			return $ergebnis;
		}

		$ergebnis['gruppe'] = $gruppe;
		$db = \Contao\Database::getInstance();

		try {
			$objGruppe = $db->prepare('SELECT name FROM tl_member_group WHERE id = ?')->execute($gruppe);

			if ($objGruppe->numRows < 1) {
				$ergebnis['status'] = 'fehlt';

				return $ergebnis;
			}

			$ergebnis['name'] = (string) $objGruppe->name;

			$mitAdresse = (int) $db->execute("SELECT COUNT(*) AS anzahl FROM tl_wertungsportal_persons WHERE email1 <> '' OR email2 <> ''")->anzahl;

			if ($mitAdresse < 1) {
				$ergebnis['status'] = 'leer';

				return $ergebnis;
			}

			$mitglieder = array();
			$objMitglieder = $db->execute('SELECT id, email, `groups` FROM tl_member');

			while ($objMitglieder->next()) {
				$mitglieder[] = array('id' => (int) $objMitglieder->id, 'email' => (string) $objMitglieder->email, 'groups' => $objMitglieder->groups);
			}

			$ergebnis['mitglieder'] = count($mitglieder);

			// Nur nach den Adressen der Mitglieder suchen, nicht den ganzen
			// Spielerbestand laden — der hat ein Vielfaches an Zeilen
			$gesucht = array();

			foreach ($mitglieder as $mitglied) {
				$adresse = self::normal($mitglied['email']);

				if ('' !== $adresse) {
					$gesucht[$adresse] = true;
				}
			}

			$gefunden = array();

			foreach (array_chunk(array_map('strval', array_keys($gesucht)), self::STAPEL) as $stapel) {
				$platzhalter = implode(',', array_fill(0, count($stapel), '?'));
				$objSpieler = $db->prepare('SELECT email1, email2 FROM tl_wertungsportal_persons WHERE email1 IN ('.$platzhalter.') OR email2 IN ('.$platzhalter.')')
					->execute(...array_merge($stapel, $stapel));

				while ($objSpieler->next()) {
					$gefunden[] = (string) $objSpieler->email1;
					$gefunden[] = (string) $objSpieler->email2;
				}
			}
		} catch (\Throwable $e) {
			$ergebnis['status'] = 'fehler';
			$ergebnis['grund'] = $e->getMessage();

			return $ergebnis;
		}

		$plan = self::plan($mitglieder, $gefunden, $gruppe);

		if (!$trocken) {
			foreach ($plan['hinzu'] + $plan['weg'] as $id => $liste) {
				$db->prepare('UPDATE tl_member SET `groups` = ? WHERE id = ?')->execute(serialize($liste), (int) $id);
			}
		}

		$ergebnis['status'] = 'ok';
		$ergebnis['hinzu'] = array_map('intval', array_keys($plan['hinzu']));
		$ergebnis['weg'] = array_map('intval', array_keys($plan['weg']));
		$ergebnis['unveraendert'] = $plan['unveraendert'];

		return $ergebnis;
	}

	/**
	 * Fasst ein Ergebnis von abgleichen() in einem Satz zusammen — für das
	 * Systemprotokoll und die Kommandozeile.
	 *
	 * @param array<string,mixed> $ergebnis Ergebnis von abgleichen()
	 * @param bool                $trocken  true: als Probelauf formulieren
	 *
	 * @return string Meldung
	 */
	public static function meldung(array $ergebnis, bool $trocken = false): string
	{
		switch ($ergebnis['status']) {
			case 'aus':
				return 'Keine Mitgliedergruppe für DSB-Mitglieder eingestellt — nichts getan.';

			case 'fehlt':
				return 'Die eingestellte Mitgliedergruppe (ID '.$ergebnis['gruppe'].') gibt es nicht — nichts getan.';

			case 'leer':
				return 'Der Spielerbestand enthält keine E-Mail-Adressen — nichts getan, damit niemand die Gruppe verliert.';

			case 'fehler':
				return 'Abgleich der Mitgliedergruppe gescheitert: '.$ergebnis['grund'];
		}

		return sprintf(
			'Mitgliedergruppe „%s" (ID %d)%s: %d Mitglieder geprüft, %d %s, %d %s, %d unverändert.',
			$ergebnis['name'],
			$ergebnis['gruppe'],
			$trocken ? ' — Probelauf, nichts geschrieben' : '',
			$ergebnis['mitglieder'],
			count($ergebnis['hinzu']),
			$trocken ? 'bekämen die Gruppe' : 'zugeordnet',
			count($ergebnis['weg']),
			$trocken ? 'verlören sie' : 'entfernt',
			$ergebnis['unveraendert']
		);
	}
}
