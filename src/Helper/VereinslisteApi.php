<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Örtliche Schnittstelle für Vereinslisten.
 *
 * Beantwortet Anfragen von außen mit der Mitgliederliste eines Vereins im
 * JSON-Format. Der Weg zu den Daten ist derselbe wie im Frontend
 * (API::autoQuery): erst der Zwischenspeicher, dann die Schnittstelle von nu,
 * im Notfall der örtliche Datenbestand. Der Vorteil gegenüber einem direkten
 * Zugriff auf nu: Die FIDE-Daten sind hier aktuell, weil sie bei jeder Anfrage
 * frisch aus der eigenen Elo-Tabelle geholt werden — und nu kennt neben Titel
 * und Nation ohnehin nur die Standard-Elo, nicht die für Schnell- und
 * Blitzschach.
 *
 * Zugang nur mit einem Schlüssel, der für genau einen Verein gilt
 * (tl_wertungsportal_tokens). Jede Anfrage wird mitgeschrieben.
 */
class VereinslisteApi
{
	/**
	 * Höchstzahl der Anfragen je IP-Adresse in der Stunde. Bremse gegen
	 * versehentliche Endlosschleifen und gegen absichtliches Fluten; ein
	 * normaler Abruf holt die Liste einmal am Tag.
	 */
	const ANFRAGEN_JE_STUNDE = 120;

	/**
	 * Voreinstellung für die erlaubten Abrufe je Schlüssel und Tag, solange in
	 * den Einstellungen nichts anderes steht. Ein Abruf je Stunde ist reichlich
	 * — die Daten ändern sich täglich, nicht stündlich.
	 */
	const ABRUFE_JE_TAG = 24;

	/**
	 * Liefert die erlaubten Abrufe je Schlüssel und Tag aus den Einstellungen.
	 *
	 * Dieselbe Zahl steht als Platzhalter in der Schlüssel-E-Mail — Text und
	 * Verhalten bleiben dadurch zwangsläufig beieinander.
	 *
	 * @return int Höchstzahl je Tag, 0 für unbegrenzt
	 */
	public static function abrufeJeTag()
	{
		$wert = $GLOBALS['TL_CONFIG']['wertungsportal_api_abrufe_tag'] ?? null;

		// Nicht gepflegt heißt Voreinstellung; eine ausdrückliche 0 heißt
		// unbegrenzt und darf nicht als „nicht gepflegt" durchgehen
		if($wert === null || trim((string) $wert) === '') return self::ABRUFE_JE_TAG;

		return max(0, (int) $wert);
	}

	/**
	 * Beantwortet eine Anfrage.
	 *
	 * Gibt IMMER ein Array zurück, nie eine Ausnahme — der Aufrufer macht
	 * daraus die HTTP-Antwort.
	 *
	 * @param  string $token  Zugangsschlüssel
	 * @param  string $vkz    Vereinskennziffer, fünfstellig
	 * @param  string $ip     IP-Adresse des Aufrufers (für Sperre und Protokoll)
	 * @return array          ['status' => HTTP-Code, 'daten' => Array]
	 */
	public static function anfrage($token, $vkz, $ip)
	{
		$start = microtime(true);
		$token = trim((string) $token);
		$vkz = strtoupper(trim((string) $vkz));
		$ip = (string) $ip;

		// ── Gesperrte IP-Adressen ─────────────────────────────────────
		// Zuerst, damit eine gesperrte Adresse gar nichts mehr auslöst
		if(self::ipGesperrt($ip))
		{
			self::protokolliere(0, $vkz, $ip, 'ipsperre', 403, 0, $start);

			return self::fehler(403, 'Zugriff gesperrt.');
		}

		// ── Bremse ────────────────────────────────────────────────────
		// Steht bewusst VOR der Parameterprüfung: Sonst ließe sich die Bremse
		// umgehen, indem man mit fehlerhaften Parametern flutet
		if(\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensAccessModel::zaehleFuerIp($ip, time() - 3600) >= self::ANFRAGEN_JE_STUNDE)
		{
			self::protokolliere(0, $vkz, $ip, 'limit', 429, 0, $start);

			return self::fehler(429, 'Zu viele Anfragen. Bitte später erneut versuchen.');
		}

		// ── Eingaben prüfen ───────────────────────────────────────────
		if($token === '' || $vkz === '')
		{
			self::protokolliere(0, $vkz, $ip, 'parameter', 400, 0, $start);

			return self::fehler(400, 'Es fehlen Angaben. Erwartet werden die Parameter token und vkz.');
		}

		if(!preg_match('/^[0-9A-Z]{5}$/', $vkz))
		{
			self::protokolliere(0, $vkz, $ip, 'parameter', 400, 0, $start);

			return self::fehler(400, 'Die Vereinskennziffer muss fünfstellig sein (Ziffern und Großbuchstaben).');
		}

		// ── Schlüssel prüfen ──────────────────────────────────────────
		$objToken = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensModel::findByToken($token);

		if($objToken === null)
		{
			// Unbekannter Schlüssel wird trotzdem protokolliert (ohne pid),
			// sonst bliebe systematisches Raten unsichtbar
			self::protokolliere(0, $vkz, $ip, 'unbekannt', 403, 0, $start);

			return self::fehler(403, 'Der Zugangsschlüssel ist unbekannt.');
		}

		if($objToken->gesperrt || !$objToken->published)
		{
			self::protokolliere((int) $objToken->id, $vkz, $ip, 'gesperrt', 403, 0, $start);

			return self::fehler(403, 'Der Zugangsschlüssel ist gesperrt.');
		}

		// Der Schlüssel gilt für genau einen Verein
		if(strtoupper((string) $objToken->vkz) !== $vkz)
		{
			self::protokolliere((int) $objToken->id, $vkz, $ip, 'fremd', 403, 0, $start);

			return self::fehler(403, 'Der Zugangsschlüssel gilt nicht für diesen Verein.');
		}

		// ── Tagesgrenze je Schlüssel ──────────────────────────────────
		// Anders als die Stundenbremse hängt sie am Schlüssel, nicht an der
		// IP: Sie ist die Zusage aus der Schlüssel-E-Mail („erlaubt sind x
		// Abrufe am Tag") und muss deshalb auch dann greifen, wenn jemand von
		// wechselnden Adressen aus abruft
		$grenze = self::abrufeJeTag();

		if($grenze > 0 && \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensAccessModel::zaehleFuerTag((int) $objToken->id, date('Y-m-d')) >= $grenze)
		{
			self::protokolliere((int) $objToken->id, $vkz, $ip, 'limit', 429, 0, $start);

			return self::fehler(429, 'Die zulässige Zahl der Abrufe für heute ist erreicht ('.$grenze.' je Tag). Bitte morgen erneut versuchen.');
		}

		// ── Daten holen (Zwischenspeicher, nu, örtlicher Bestand) ──────
		$result = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery(array
		(
			'funktion' => 'Vereinsliste',
			'cachekey' => $vkz,
			'zps'      => $vkz,
		));

		if(!is_array($result) || !empty($result['error']) || !isset($result['body']['data']) || !is_array($result['body']['data']))
		{
			$meldung = !empty($result['keine_livedaten'])
				? 'Die Daten sind zurzeit nicht abrufbar.'
				: 'Der Verein ist unbekannt oder liefert keine Daten.';

			self::protokolliere((int) $objToken->id, $vkz, $ip, 'fehler', 502, 0, $start);

			return self::fehler(502, $meldung);
		}

		$spieler = self::spielerliste($result['body']['data'], $vkz);
		$quelle = self::quelle($result);

		self::protokolliere((int) $objToken->id, $vkz, $ip, $quelle, 200, count($spieler), $start);

		return array
		(
			'status' => 200,
			'daten'  => array
			(
				'vkz'     => $vkz,
				'verein'  => self::vereinsname($vkz),
				'quelle'  => $quelle,
				'stand'   => date('c'),
				'anzahl'  => count($spieler),
				'spieler' => $spieler,
			),
		);
	}

	/**
	 * Baut die Spielerliste für die Ausgabe: nur die Angaben zur Person, die
	 * Mitgliedschaft ausschließlich für den angefragten Verein.
	 *
	 * Die Antwort der Schnittstelle führt zu jeder Person ALLE Vereine, in
	 * denen sie je gemeldet war. Für eine Vereinsliste ist das Ballast — und
	 * es wären Daten über andere Vereine, die hier niemanden angehen.
	 *
	 * Die FIDE-Werte werden hier NEU aus der örtlichen Elo-Tabelle geholt und
	 * nicht aus der Antwort übernommen, obwohl Helper::setFIDEDaten sie dort
	 * schon eingetragen hat. Grund: Jene Werte wandern mit in den
	 * Zwischenspeicher und sind so alt wie der Eintrag — nach einem
	 * Elo-Import stünden bis zu 24 Stunden lang die alten Zahlen in der
	 * Schnittstelle, und ein neu hinzugekommenes Feld fehlte in allen älteren
	 * Einträgen ganz. Der Aufwand ist eine einzige Sammelabfrage je Anfrage.
	 *
	 * @param  array  $daten  body.data der Antwort
	 * @param  string $vkz    angefragter Verein
	 * @return array
	 */
	protected static function spielerliste($daten, $vkz)
	{
		// Gesperrte Personen (Blacklist) wie im Frontend aussortieren
		$blacklist = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getBlacklist(array_column($daten, 'nuLigaPersonId'));

		// FIDE-Daten aller Spieler in einem Rutsch, taufrisch aus der Elo-Tabelle
		$fideliste = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getFIDEDatenListe(array_column($daten, 'fideId'));
		$fideleer = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::leererFIDESatz();

		$liste = array();

		foreach($daten as $person)
		{
			$nuId = (string) ($person['nuLigaPersonId'] ?? '');
			if($nuId !== '' && isset($blacklist[$nuId])) continue;

			// Mitgliedschaft im angefragten Verein heraussuchen
			$status = '';
			$mitgliedsnummer = '';

			foreach(($person['memberships'] ?? array()) as $mitglied)
			{
				if(strtoupper((string) ($mitglied['vkz'] ?? '')) !== $vkz) continue;

				$mitgliedsnummer = (string) ($mitglied['memberNo'] ?? '');
				$status = self::status((string) ($mitglied['licenceState'] ?? ''));

				// Aktive Mitgliedschaft gewinnt, danach nicht weitersuchen
				if($status === 'A') break;
			}

			// FIDE-Satz der Person; ohne Eintrag in der Elo-Tabelle bleibt alles leer
			$fideId = !empty($person['fideId']) ? (int) $person['fideId'] : null;
			$fide = $fideId && isset($fideliste[$fideId]) ? $fideliste[$fideId] : $fideleer;

			$eintrag = array
			(
				'id'              => $nuId,
				'nachname'        => (string) ($person['lastname'] ?? ''),
				'vorname'         => (string) ($person['firstname'] ?? ''),
				'geburtsjahr'     => (string) ($person['birthyear'] ?? ''),
				'geschlecht'      => self::geschlecht((string) ($person['gender'] ?? '')),
				'mitgliedsnummer' => $mitgliedsnummer,
				'status'          => $status,
				'dwz'             => !empty($person['rating']) ? (int) $person['rating'] : null,
				'dwzIndex'        => !empty($person['index']) ? (int) $person['index'] : null,
				'letzteAuswertung'=> (string) ($person['weekOfLastTournamentEvaluation'] ?? ''),
				'fideId'          => $fideId,
				'elo'             => !empty($fide['elo']) ? (int) $fide['elo'] : null,
				'eloSchnell'      => !empty($fide['eloSchnell']) ? (int) $fide['eloSchnell'] : null,
				'eloBlitz'        => !empty($fide['eloBlitz']) ? (int) $fide['eloBlitz'] : null,
				'titel'           => (string) $fide['titel'],
				'nation'          => (string) $fide['land'],
			);

			$liste[] = $eintrag;
		}

		// Nach Namen sortieren, umlautsicher (dieselbe Regel wie die Aliase)
		usort($liste, function($a, $b)
		{
			return strcmp(
				\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::alias($a['nachname'].' '.$a['vorname']),
				\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::alias($b['nachname'].' '.$b['vorname'])
			);
		});

		return $liste;
	}

	/**
	 * Übersetzt den Lizenzstatus der Schnittstelle in A/P.
	 * Andere Werte (SONDER, OHNE) werden unverändert durchgereicht, damit
	 * nichts stillschweigend unter den Tisch fällt.
	 */
	protected static function status($licenceState)
	{
		switch($licenceState)
		{
			case 'ACTIVE':  return 'A';
			case 'PASSIVE': return 'P';
			case '':        return '';
			default:        return $licenceState;
		}
	}

	/**
	 * Übersetzt das Geschlecht in M/W/D.
	 */
	protected static function geschlecht($gender)
	{
		switch($gender)
		{
			case 'MALE':    return 'M';
			case 'FEMALE':  return 'W';
			case 'DIVERSE': return 'D';
			default:        return '';
		}
	}

	/**
	 * Ermittelt den Vereinsnamen zur VKZ. Schlägt das fehl, bleibt das Feld
	 * leer — die Spielerliste ist deswegen nicht weniger brauchbar.
	 */
	protected static function vereinsname($vkz)
	{
		$result = \Schachbulle\ContaoWertungsportalBundle\Helper\API::autoQuery(array
		(
			'funktion' => 'Vereinsname',
			'cachekey' => $vkz,
			'zps'      => $vkz,
		));

		return (string) ($result['body']['data'][0]['clubName'] ?? '');
	}

	/**
	 * Benennt die Herkunft der Daten für Protokoll und Ausgabe.
	 */
	protected static function quelle($result)
	{
		if(!empty($result['lokalquelle'])) return 'lokal';
		if(!empty($result['cachequelle'])) return 'cache';

		return 'api';
	}

	/**
	 * Prüft, ob eine IP-Adresse in den Einstellungen gesperrt ist.
	 * Die Liste steht dort als Text, eine Adresse je Zeile; Leerzeilen und
	 * mit # beginnende Zeilen gelten als Kommentar.
	 */
	protected static function ipGesperrt($ip)
	{
		$liste = (string) ($GLOBALS['TL_CONFIG']['wertungsportal_api_sperren'] ?? '');
		if($liste === '' || $ip === '') return false;

		foreach(preg_split('/\r\n|\r|\n/', $liste) as $zeile)
		{
			$zeile = trim($zeile);
			if($zeile === '' || strpos($zeile, '#') === 0) continue;
			if($zeile === $ip) return true;
		}

		return false;
	}

	/**
	 * Schreibt den Zugriff mit.
	 */
	protected static function protokolliere($pid, $vkz, $ip, $quelle, $status, $anzahl, $start)
	{
		\Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTokensAccessModel::schreibe($pid, array
		(
			'vkz'    => $vkz,
			'ip'     => $ip,
			'quelle' => $quelle,
			'status' => $status,
			'anzahl' => $anzahl,
			'dauer'  => (int) round((microtime(true) - $start) * 1000),
		));
	}

	/**
	 * Baut eine Fehlerantwort.
	 */
	protected static function fehler($status, $meldung)
	{
		return array
		(
			'status' => $status,
			'daten'  => array('fehler' => true, 'meldung' => $meldung),
		);
	}
}
