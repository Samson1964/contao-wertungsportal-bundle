<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

/**
 * Wertungsangaben eines Turnierspielers für die Anzeige aufbereiten.
 *
 * Turnierauswertung, Turnierergebnisse und Spielberichtsbogen zeigen dieselben
 * Angaben eines Spielers — alte DWZ, neue DWZ, Erwartungswert. Bis 1.44.1 las
 * jede der drei Aufbereitungen die Zahlenfelder der Schnittstelle selbst aus.
 * Das ging gut, solange nur Mitglieder mitspielten. Für Nichtmitglieder
 * („Textuelle", also Teilnehmer, die im Wertungsportal nur als Text erfasst
 * und mit keiner Person verknüpft sind) gilt aber zweierlei:
 *
 * 1. Wertungsordnung 3.4.3: „Spieler, die nicht Mitglied eines DSB-Vereins
 *    sind (Vereinslose), werden zwar in die Berechnungen einbezogen, für diese
 *    werden aber keine DWZ und keine Restpartien ausgewiesen oder
 *    gespeichert." Die Schnittstelle liefert `ratingNew`/`indexNew` trotzdem
 *    mit — angezeigt werden dürfen sie nicht.
 * 2. Ihre Eingangswertung Ro (meist eine Elo) steht NICHT in `ratingOld`,
 *    sondern nur im Anzeigetext `ratingOldDisplayString`, bewusst ohne Index.
 *    Ohne sie bleibt die Auswertung der Mitglieder unverständlich, denn mit
 *    dieser Zahl zählen die Nichtmitglieder für ihre Gegner.
 *
 * Diese Klasse bündelt beide Regeln an einer Stelle. Sie kommt ohne Contao
 * aus und lässt sich deshalb ohne Installation prüfen
 * (tests/Helper/SpielerwertungTest.php).
 */
class Spielerwertung
{
	/**
	 * Standardabweichung der Gewinnerwartung: Die Wertungsordnung rechnet mit
	 * der Normalverteilung über die Wertungsdifferenz, Streuung 200 × √2.
	 */
	public const STREUUNG = 282.842712474619;

	/**
	 * Stellt fest, ob ein Turnierspieler KEIN Mitglied eines DSB-Vereins ist.
	 *
	 * Maßgeblich ist das Feld `member` der Schnittstelle. Nach Auskunft des
	 * DSB-Wertungsreferats reicht `member = false` aus — auch künftig, wenn
	 * ausgetretene Mitglieder noch fünf Jahre lang mit ihrer Person verknüpft
	 * bleiben: Unmittelbar nach dem Austritt soll keine neue DWZ mehr
	 * veröffentlicht werden.
	 *
	 * Fehlt das Feld (Zeilen der Spiegeltabelle aus der Zeit vor 1.45.0,
	 * Platzhalter ohne Spielerdaten), entscheidet die nuLiga-Personennummer:
	 * Ein textuell erfasster Teilnehmer hat keine.
	 *
	 * @param mixed $spieler Spieler-DTO der Schnittstelle (players, whitePlayer,
	 *                       blackPlayer) oder der örtlichen Spiegeltabelle;
	 *                       `member` darf bool, 0/1 oder '0'/'1' sein
	 *
	 * @return bool true = Nichtmitglied; bei etwas anderem als einem Array false
	 */
	public static function istNichtmitglied($spieler): bool
	{
		if (!\is_array($spieler)) {
			return false;
		}

		if (\array_key_exists('member', $spieler) && null !== $spieler['member'] && '' !== $spieler['member']) {
			return !filter_var($spieler['member'], FILTER_VALIDATE_BOOLEAN);
		}

		return empty($spieler['nuLigaPersonId']);
	}

	/**
	 * Stellt fest, ob ein Ergebniscode der Schnittstelle eine kampflose Partie
	 * bezeichnet (`PLUS_MINUS`, `MINUS_PLUS`, `MINUS_MINUS`, `ZERO_MINUS`,
	 * `MINUS_ZERO`).
	 *
	 * Kampflose Partien werden nicht ausgewertet. Die Schnittstelle liefert
	 * für sie trotzdem ein `expected` — am Bestand nachgemessen: Nur OHNE
	 * diese Werte ergibt die Summe der Einzelerwartungen das gelieferte
	 * `winsExpected`.
	 *
	 * @param mixed $ergebnis Ergebniscode, etwa `WHITE_WINS`
	 *
	 * @return bool true bei einer kampflosen Partie; false auch bei leerem
	 *              oder unbekanntem Code
	 */
	public static function istKampflos($ergebnis): bool
	{
		return \is_string($ergebnis) && 1 === preg_match('/PLUS|MINUS/', $ergebnis);
	}

	/**
	 * Zerlegt einen Anzeigetext der Schnittstelle in Wertungszahl und Index.
	 *
	 * Beobachtete Formen: `1887 - 44` (Mitglied, DWZ mit Index), `1905`
	 * (Nichtmitglied, Eingangswertung ohne Index) und die Klammerform: `(1537)`
	 * als alte Wertung eines Nichtmitglieds, das früher eine DWZ hatte, und
	 * `(1318)` als errechnete neue Wertung eines Teilnehmers ohne jede Zahl.
	 * Die Klammer stammt von nu und wird so weitergereicht.
	 *
	 * @param mixed $text Anzeigetext, etwa aus `ratingOldDisplayString`
	 *
	 * @return array{rating:int,index:int,klammer:bool}|null Bestandteile
	 *         (index 0 = keiner), null wenn der Text fehlt, leer ist oder keine
	 *         dieser Formen hat
	 */
	public static function zerlege($text): ?array
	{
		if (!\is_string($text) && !\is_int($text)) {
			return null;
		}

		if (1 !== preg_match('/^\s*(\(?)\s*(\d{1,4})\s*(?:-\s*(\d{1,4})\s*)?\)?\s*$/', (string) $text, $treffer)) {
			return null;
		}

		if (0 === (int) $treffer[2]) {
			return null;
		}

		return array
		(
			'rating'  => (int) $treffer[2],
			'index'   => isset($treffer[3]) ? (int) $treffer[3] : 0,
			'klammer' => '' !== $treffer[1],
		);
	}

	/**
	 * Bereitet die Wertungsangaben eines Turnierspielers für die Anzeige auf.
	 *
	 * Regeln:
	 * - Alte Wertung eines Mitglieds: `ratingOld`/`indexOld`; fehlt die Zahl,
	 *   der Anzeigetext `ratingOldDisplayString`.
	 * - Alte Wertung eines Nichtmitglieds: der Anzeigetext, so wie nu ihn
	 *   formt — „1905" (Eingangswertung ohne Index, meist eine Elo) oder
	 *   „(1537)" (frühere DWZ eines Ausgetretenen, in Klammern). Nur wenn er
	 *   fehlt (ältere Zeilen der Spiegeltabelle), die Zahlenfelder. Ohne Index
	 *   erscheint nur die Zahl — früher stand dort „1905 - 0".
	 * - Neue Wertung und Differenz: bei Nichtmitgliedern IMMER leer, auch wenn
	 *   die Schnittstelle `ratingNew` liefert (Wertungsordnung 3.4.3).
	 * - Entwicklungskoeffizient und Erwartungswert: die Zahlenfelder; fehlen
	 *   sie, die Anzeigetexte `factorKDisplayString` und
	 *   `winsExpectedDisplayString`.
	 *
	 * Die Texte sind fertig für die Vorlage: Sie enthalten nur Ziffern,
	 * Klammern, Vorzeichen und geschützte Leerzeichen, also nichts, was noch
	 * maskiert werden müsste.
	 *
	 * @param mixed $spieler Spieler-DTO; fehlende Felder und die Platzhalter
	 *                       `false` aus Helper::PlayerDefaults() sind erlaubt,
	 *                       alles andere als ein Array zählt als leerer Spieler
	 *
	 * @return array{nichtmitglied:bool,ratingOld:int,indexOld:int,ratingNew:int,indexNew:int,dwzAlt:string,dwzAltKurz:string,dwzNeu:string,differenz:string,factorK:int|float|string,winsExpected:float|false}
	 *         `dwzAlt`/`dwzNeu` in der Tabellenform „1887 - 44", `dwzAltKurz`
	 *         nur die Zahl; `winsExpected` ist false, wenn es keinen gibt
	 *         (passend zu Helper::Erwartungswert())
	 */
	public static function aufbereiten($spieler): array
	{
		if (!\is_array($spieler)) {
			$spieler = array();
		}

		$nichtmitglied = self::istNichtmitglied($spieler);

		// Alte Wertung. Bei Mitgliedern gelten die Zahlenfelder, der Anzeigetext
		// springt nur ein, wenn sie fehlen. Bei Nichtmitgliedern ist es
		// umgekehrt: Den Text formt nu für sie mit Absicht („1905" ohne Index,
		// „(1537)" für die frühere DWZ eines Ausgetretenen), und die
		// Spiegeltabelle kann daneben noch Zahlen aus einer Zeit tragen, als
		// der Spieler Mitglied war — nicht mehr gelieferte Felder setzt der
		// Abgleich nie zurück. Notbetrieb und Schnittstelle zeigen so dasselbe
		$alt = self::ganzzahl($spieler['ratingOld'] ?? 0);
		$altIndex = self::ganzzahl($spieler['indexOld'] ?? 0);
		$klammer = false;

		if ($nichtmitglied || 0 === $alt) {
			$teile = self::zerlege($spieler['ratingOldDisplayString'] ?? null);

			if (null !== $teile) {
				$alt = $teile['rating'];
				$altIndex = $teile['index'];
				$klammer = $teile['klammer'];
			} elseif (0 === $alt) {
				$altIndex = 0;
			}
		}

		// Neue Wertung: für Nichtmitglieder wird keine ausgewiesen
		$neu = $nichtmitglied ? 0 : self::ganzzahl($spieler['ratingNew'] ?? 0);
		$neuIndex = $nichtmitglied ? 0 : self::ganzzahl($spieler['indexNew'] ?? 0);

		$differenz = '';

		if ($neu > 0 && $alt > 0) {
			$differenz = ($neu > $alt ? '+' : '').($neu - $alt);
		}

		// Entwicklungskoeffizient: Zahl vor Anzeigetext. Der Text trägt bei
		// Nichtmitgliedern Klammern („(56.9)"), die Zahl nicht
		$faktor = $spieler['factorK'] ?? '';

		if (!\is_int($faktor) && !\is_float($faktor) && !(\is_string($faktor) && is_numeric($faktor))) {
			$faktor = self::anzeigezahl($spieler['factorKDisplayString'] ?? null);
		}

		// Erwartungswert: Zahl vor Anzeigetext („1.879")
		$erwartung = $spieler['winsExpected'] ?? false;

		if (\is_int($erwartung) || \is_float($erwartung) || (\is_string($erwartung) && is_numeric($erwartung))) {
			$erwartung = (float) $erwartung;
		} else {
			$text = trim(self::anzeigezahl($spieler['winsExpectedDisplayString'] ?? null), '()');
			$erwartung = is_numeric($text) ? (float) $text : false;
		}

		return array
		(
			'nichtmitglied' => $nichtmitglied,
			'ratingOld'     => $alt,
			'indexOld'      => $altIndex,
			'ratingNew'     => $neu,
			'indexNew'      => $neuIndex,
			'dwzAlt'        => self::dwz($alt, $altIndex, $klammer),
			'dwzAltKurz'    => $alt > 0 ? ($klammer ? '('.$alt.')' : (string) $alt) : '',
			'dwzNeu'        => self::dwz($neu, $neuIndex),
			'differenz'     => $differenz,
			'factorK'       => $faktor,
			'winsExpected'  => $erwartung,
		);
	}

	/**
	 * Gibt Wertungszahl und Index in der Tabellenform „1834 - 42" zurück —
	 * dieselbe Form wie Helper::DWZ(), damit Mitglieder und Nichtmitglieder in
	 * einer Spalte bündig untereinander stehen. Die geschützten Leerzeichen
	 * halten die Breite; ein normales Leerzeichen ginge im HTML verloren.
	 *
	 * Anders als Helper::DWZ() lässt diese Fassung einen fehlenden Index weg,
	 * statt „1905 - 0" zu schreiben: Eine Elo als Eingangswertung hat keinen.
	 *
	 * @param int  $wertung Wertungszahl, 0 = keine
	 * @param int  $index   Wertungsindex, 0 = keiner
	 * @param bool $klammer true setzt die Zahl in Klammern (rechnerischer Wert)
	 *
	 * @return string Leer, wenn es keine Wertungszahl gibt
	 */
	public static function dwz(int $wertung, int $index = 0, bool $klammer = false): string
	{
		if ($wertung <= 0) {
			return '';
		}

		if ($klammer) {
			return '('.$wertung.')';
		}

		$zahl = str_replace(' ', '&nbsp;&nbsp;', sprintf('%4d', $wertung));

		if ($index <= 0) {
			return $zahl;
		}

		return sprintf('%s -%s', $zahl, str_replace(' ', '&nbsp;&nbsp;', sprintf('%3d', $index)));
	}

	/**
	 * Liefert die Gewinnerwartung eines Spielers in EINER Partie.
	 *
	 * Die Schnittstelle gibt im Spielberichtsbogen zu jeder Partie `expected`
	 * mit — und zwar immer aus Sicht von WEISS, gleichgültig, wessen Bogen
	 * abgerufen wurde. Für den Schwarzspieler gilt deshalb 1 − expected.
	 * Nachgemessen am Bogen aus dem TODO vom 17.09.2026: Erst so ergibt die
	 * Summe der sieben Partien (3,890983) das gelieferte `winsExpected`
	 * (3,89098); dieselben Werte zeigt das Wertungsportal selbst.
	 *
	 * Fehlt `expected` oder ist es 0 (Notbetrieb mit Partien, die nur über die
	 * Ergebnisliste gespiegelt wurden — dort liefert nu den Wert nicht, in der
	 * Spiegeltabelle steht dann der Vorgabewert 0), wird aus den beiden alten
	 * Wertungen gerechnet. Das Ergebnis ist dann als Schätzung
	 * gekennzeichnet: Hat nu für einen Teilnehmer ohne Wertung eine errechnete
	 * Zahl eingesetzt, kennt diese Rechnung sie nicht.
	 *
	 * @param mixed $partie        Match-DTO mit `result` und `expected`
	 * @param bool  $alsWeiss      true = Erwartung des Weißspielers, false = des
	 *                             Schwarzspielers
	 * @param int   $eigeneWertung alte Wertung des Spielers, 0 = keine
	 * @param int   $gegnerWertung alte Wertung des Gegners, 0 = keine
	 *
	 * @return array{wert:float|null,geschaetzt:bool} `wert` ist null bei einer
	 *         kampflosen Partie oder wenn weder `expected` noch beide
	 *         Wertungen vorliegen
	 */
	public static function partieerwartung($partie, bool $alsWeiss, int $eigeneWertung = 0, int $gegnerWertung = 0): array
	{
		$ohne = array('wert' => null, 'geschaetzt' => false);

		if (!\is_array($partie) || self::istKampflos($partie['result'] ?? '')) {
			return $ohne;
		}

		$erwartung = $partie['expected'] ?? null;

		if ((\is_int($erwartung) || \is_float($erwartung) || (\is_string($erwartung) && is_numeric($erwartung))) && (float) $erwartung > 0.0 && (float) $erwartung <= 1.0) {
			$erwartung = (float) $erwartung;

			return array('wert' => $alsWeiss ? $erwartung : 1.0 - $erwartung, 'geschaetzt' => false);
		}

		$geschaetzt = self::gewinnerwartung($eigeneWertung, $gegnerWertung);

		return null === $geschaetzt ? $ohne : array('wert' => $geschaetzt, 'geschaetzt' => true);
	}

	/**
	 * Berechnet die Gewinnerwartung aus zwei Wertungszahlen nach der
	 * Wertungsordnung: Normalverteilung über die Differenz, Streuung 200 × √2.
	 *
	 * Bis 1.44.1 rechnete das Bundle mit der logistischen Elo-Formel
	 * 1 / (1 + 10^(−D/400)). Die weicht bei großen Differenzen sichtbar ab:
	 * 1887 gegen 1318 ergab 0,964 statt der 0,978 der Auswertung. Mit der
	 * Normalverteilung stimmen die Werte auf vier Stellen mit denen der
	 * Schnittstelle überein.
	 *
	 * @param int $wertung       eigene Wertungszahl
	 * @param int $gegnerWertung Wertungszahl des Gegners
	 *
	 * @return float|null Erwartung zwischen 0 und 1, null wenn eine der beiden
	 *                    Zahlen fehlt (0 oder kleiner)
	 */
	public static function gewinnerwartung(int $wertung, int $gegnerWertung): ?float
	{
		if ($wertung <= 0 || $gegnerWertung <= 0) {
			return null;
		}

		// Gleiche Wertung: genau die Hälfte. Die Näherung der Fehlerfunktion
		// träfe sie nur auf neun Stellen (0,5000000005)
		if ($wertung === $gegnerWertung) {
			return 0.5;
		}

		return self::normalverteilung(($wertung - $gegnerWertung) / self::STREUUNG);
	}

	/**
	 * Verteilungsfunktion Φ der Standardnormalverteilung.
	 *
	 * PHP bringt keine Fehlerfunktion mit. Gerechnet wird deshalb mit der
	 * Näherung 7.1.26 aus Abramowitz/Stegun; ihr Fehler bleibt unter
	 * 1,5 × 10⁻⁷ und damit weit unter der dritten Nachkommastelle, die
	 * angezeigt wird.
	 *
	 * @param float $x Abstand vom Mittelwert in Standardabweichungen
	 *
	 * @return float Wahrscheinlichkeit zwischen 0 und 1
	 */
	protected static function normalverteilung(float $x): float
	{
		$z = abs($x) / M_SQRT2;
		$t = 1.0 / (1.0 + 0.3275911 * $z);
		$fehlerfunktion = 1.0 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$z * $z);

		return $x >= 0 ? 0.5 * (1.0 + $fehlerfunktion) : 0.5 * (1.0 - $fehlerfunktion);
	}

	/**
	 * Wandelt ein Zahlenfeld der Schnittstelle in eine ganze Zahl. Fehlende
	 * Felder tragen nach Helper::PlayerDefaults() den Wert false; die
	 * Spiegeltabelle liefert Zeichenketten.
	 *
	 * @param mixed $wert Zahl, Ziffernfolge, false oder null
	 *
	 * @return int 0 bei allem, was keine Zahl ist
	 */
	protected static function ganzzahl($wert): int
	{
		return (\is_int($wert) || \is_float($wert) || (\is_string($wert) && is_numeric($wert))) ? (int) $wert : 0;
	}

	/**
	 * Lässt von einem Anzeigetext nur durch, was eine Zahl in der Schreibweise
	 * von nu sein kann: Ziffern, Punkt, Komma, Vorzeichen und die Klammern um
	 * rechnerische Werte. Alles andere wird verworfen statt maskiert — der Text
	 * landet ungeprüft in der Vorlage.
	 *
	 * @param mixed $text Anzeigetext der Schnittstelle
	 *
	 * @return string Der getrimmte Text, leer wenn er fehlt oder etwas anderes
	 *                enthält
	 */
	protected static function anzeigezahl($text): string
	{
		if (!\is_string($text) && !\is_int($text) && !\is_float($text)) {
			return '';
		}

		$text = trim((string) $text);

		return 1 === preg_match('/^\(?[-+]?\d+(?:[.,]\d+)?\)?$/', $text) ? str_replace(',', '.', $text) : '';
	}
}
