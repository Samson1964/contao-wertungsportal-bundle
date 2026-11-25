<?php

declare(strict_types=1);

namespace Schachbulle\ContaoWertungsportalBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\ModuleModel;
use Contao\StringUtil;
use Schachbulle\ContaoWertungsportalBundle\DwzArchiv\Archivindex;
use Schachbulle\ContaoWertungsportalBundle\Helper\API;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frontend-Modul „DWZ-Archiv-Suche“ (ab 1.61.0, Typ `wertungsportal_dwzarchiv`).
 *
 * Der Besucher wählt Datum, Verband und Format; ausgegeben werden die Dateien
 * zu diesem Tag, sonst die nächsten davor und danach. Ersetzt die
 * Download-Elemente in den Akkordeons der Seiten „DWZ-Archiv“ auf
 * schachbund.de — neue Dateien erscheinen ohne Pflege.
 *
 * Anders als die übrigen Module des Bundles (alte `Module`-Klassen in
 * `$GLOBALS['FE_MOD']`) ist dies ein Fragment-Controller: Den Weg über
 * `FE_MOD` gibt es in Contao 5 nicht mehr. Registriert wird er über das Tag
 * `contao.frontend_module` in services.yml — das Attribut `#[AsFrontendModule]`
 * wirkte hier nicht, weil das Bundle ohne Autoconfigure arbeitet und PHP 7.4
 * Attribute überliest.
 *
 * Die Vorlage ist Twig (`@ContaoWertungsportal/dwz_archiv_suche.html.twig`)
 * und wird selbst gerendert; die von Contao vorbereitete Vorlage bleibt
 * ungenutzt, weil sie in 4.13 eine html5-Vorlage und in 5 eine
 * FragmentTemplate ist. Überschrift und CSS-ID kommen deshalb direkt aus dem
 * Modul.
 */
class DwzArchivSucheController extends AbstractFrontendModuleController
{
	/** Typ des Moduls in tl_module.type */
	public const TYP = 'wertungsportal_dwzarchiv';

	/** Obergrenze für die Zahl der Nachbardateien, gegen übergroße Seiten */
	private const HOECHSTE_NACHBARN = 10;

	/** @var Archivindex */
	private $index;

	/**
	 * @param Archivindex $index Verzeichnis der Archivdateien (Dienst aus services.yml)
	 */
	public function __construct(Archivindex $index)
	{
		$this->index = $index;
	}

	/**
	 * Liefert die Namen aller bekannten Verbandscodes.
	 *
	 * Die Landesverbände kommen aus `API::landesverbaende()`, damit die Namen
	 * nur an einer Stelle stehen. Ergänzt werden der DSB (0) sowie
	 * Blindenschachbund (L) und Schwalbe (M), die der Downloader ebenfalls
	 * lädt. Dient auch als Optionsliste in tl_module.
	 *
	 * @return array<string, string> Code => Name, Codes in der Reihenfolge 0–9, A–H, L, M
	 */
	public static function verbandsnamen(): array
	{
		$namen = array('0' => 'Deutscher Schachbund (gesamt)');

		foreach (API::landesverbaende() as $verband) {
			$namen[$verband['federation']] = $verband['clubName'];
		}

		$namen['L'] = 'Deutscher Blinden- und Sehbehinderten-Schachbund';
		$namen['M'] = 'Schwalbe, deutsche Vereinigung für Problemschach';

		return $namen;
	}

	/**
	 * Formatiert eine Dateigröße wie die Download-Elemente von Contao
	 * („3,0 MiB“).
	 *
	 * @param int $bytes Größe in Bytes
	 *
	 * @return string Größe mit einer Nachkommastelle ab KiB, deutsches Komma
	 */
	public static function groesse(int $bytes): string
	{
		$einheiten = array('Byte', 'KiB', 'MiB', 'GiB');
		$stufe = 0;
		$wert = (float) $bytes;

		while ($wert >= 1024 && $stufe < \count($einheiten) - 1) {
			$wert /= 1024;
			++$stufe;
		}

		return 0 === $stufe ? $bytes.' Byte' : number_format($wert, 1, ',', '.').' '.$einheiten[$stufe];
	}

	/**
	 * Baut die Ausgabe des Moduls.
	 *
	 * Gelesen werden nur GET-Parameter (verlinkbare Ergebnisse): `datum`
	 * (JJJJ-MM-TT), `verband` (Code), `format` (Gruppe). Ungültige Werte
	 * fallen auf die Vorgabe zurück — heute, DSB, alle Formate. Das Datum wird
	 * auf den vorhandenen Bestand des Verbands begrenzt, sonst wiese der
	 * Browser das Formular wegen `min`/`max` ab, etwa morgens vor dem
	 * Erzeugen der Tagesdatei.
	 *
	 * Der Parametertyp von `$template` fehlt mit Absicht: 4.13 übergibt eine
	 * `Template`, 5 eine `FragmentTemplate`. Ohne Typ paßt die Methode zu
	 * beiden Elternklassen; der Rückgabetyp `Response` ist in beiden erlaubt.
	 *
	 * @param mixed       $template Von Contao vorbereitete Vorlage, ungenutzt
	 * @param ModuleModel $model    Das Modul mit den Feldern `wertungsportal_dwzarchiv_*`
	 * @param Request     $request  Die laufende Anfrage
	 *
	 * @return Response Das gerenderte Modul
	 */
	protected function getResponse($template, ModuleModel $model, Request $request): Response
	{
		$t = $GLOBALS['TL_LANG']['MSC']['wp_dwzarchiv'] ?? array();
		$namen = self::verbandsnamen();

		// Verband: fest im Modul oder aus der Anfrage, nur vorhandene Codes
		$vorhanden = $this->index->verbaende();
		$fest = (string) $model->wertungsportal_dwzarchiv_verband;
		$gewuenscht = '' !== $fest ? $fest : (string) $request->query->get('verband', '0');
		$verband = \in_array($gewuenscht, $vorhanden, true) ? $gewuenscht : ($vorhanden[0] ?? '0');

		// Angebotene Formate: Auswahl im Modul, leer = alle
		$erlaubt = array_values(array_intersect(Archivindex::GRUPPEN, StringUtil::deserialize($model->wertungsportal_dwzarchiv_formate, true) ?: Archivindex::GRUPPEN));
		$gruppen = array_intersect_key($this->index->dateien($verband)['gruppen'], array_flip($erlaubt));
		$format = (string) $request->query->get('format', '');
		if (!isset($gruppen[$format])) {
			$format = '';
		}

		// Erster und letzter Tag des Verbands über alle angebotenen Formate
		$erster = $letzter = null;
		foreach ($gruppen as $liste) {
			$erster = null === $erster ? $liste[0]['datum'] : min($erster, $liste[0]['datum']);
			$letzter = null === $letzter ? end($liste)['datum'] : max($letzter, end($liste)['datum']);
		}

		$datum = self::datum((string) $request->query->get('datum', ''));
		if (null !== $erster) {
			$datum = max($erster, min($letzter, $datum));
		}

		$nachbarn = max(0, min(self::HOECHSTE_NACHBARN, (int) $model->wertungsportal_dwzarchiv_nachbarn));

		$ergebnis = array();
		foreach ($gruppen as $gruppe => $liste) {
			if ('' === $format || $format === $gruppe) {
				$ergebnis[] = array('gruppe' => $gruppe, 'titel' => $t['gruppen'][$gruppe] ?? $gruppe, 'tage' => $this->tage(Archivindex::suche($liste, $datum, $nachbarn)));
			}
		}

		$neueste = array();
		if ($model->wertungsportal_dwzarchiv_neueste) {
			foreach ($gruppen as $gruppe => $liste) {
				$neueste[] = array('gruppe' => $gruppe, 'titel' => $t['gruppen'][$gruppe] ?? $gruppe, 'tage' => $this->tage(Archivindex::suche($liste, end($liste)['datum'])));
			}
		}

		$auswahl = array();
		foreach ($vorhanden as $code) {
			$auswahl[$code] = $namen[$code] ?? 'Verband '.$code;
		}

		$formate = array();
		foreach (array_keys($gruppen) as $gruppe) {
			$formate[$gruppe] = $t['gruppen'][$gruppe] ?? $gruppe;
		}

		$headline = StringUtil::deserialize($model->headline);
		$cssId = StringUtil::deserialize($model->cssID, true);

		$GLOBALS['TL_CSS'][] = 'bundles/contaowertungsportal/css/dwzarchiv.css|static';

		return $this->render('@ContaoWertungsportal/dwz_archiv_suche.html.twig', array
		(
			't'            => $t,
			'id'           => (int) $model->id,
			'cssId'        => $cssId[0] ?? '',
			'klasse'       => trim($cssId[1] ?? ''),
			'ueberschrift' => \is_array($headline) && '' !== ($headline['value'] ?? '') ? array('tag' => $headline['unit'] ?? 'h2', 'text' => $headline['value']) : null,
			'aktion'       => $request->getBaseUrl().$request->getPathInfo(),
			'datum'        => self::isoDatum($datum),
			'min'          => null !== $erster ? self::isoDatum($erster) : '',
			'max'          => null !== $letzter ? self::isoDatum($letzter) : '',
			'verbandswahl' => '' === $fest,
			'verband'      => $verband,
			'verbandsname' => $auswahl[$verband] ?? '',
			'verbaende'    => $auswahl,
			'format'       => $format,
			'formate'      => $formate,
			'ergebnis'     => $ergebnis,
			'neueste'      => $neueste,
		));
	}

	/**
	 * Bereitet die Suchtreffer einer Gruppe für die Vorlage auf.
	 *
	 * Ein Eintrag je Tag; mehrere Dateien desselben Tags (`_v2`, ws822 und
	 * ws833) bleiben beisammen, damit die Vorlage Stand und Abstand nur einmal
	 * ausgibt (rowspan) statt sie je Datei zu wiederholen.
	 *
	 * @param list<array{datum: string, abstand: int, dateien: list<array>}> $treffer Ergebnis von Archivindex::suche()
	 *
	 * @return list<array> Je Tag: datum (TT.MM.JJJJ), abstand (Text, leer bei genauem Treffer),
	 *                     genau (bool), dateien (je Datei name, url, groesse als Text, quelle als Text)
	 */
	private function tage(array $treffer): array
	{
		$t = $GLOBALS['TL_LANG']['MSC']['wp_dwzarchiv'] ?? array();
		$tage = array();

		foreach ($treffer as $tag) {
			$abstand = '';
			if (0 !== $tag['abstand']) {
				$schluessel = ($tag['abstand'] < 0 ? 'frueher' : 'spaeter').(1 === abs($tag['abstand']) ? '1' : '');
				$abstand = sprintf($t[$schluessel] ?? '%d', abs($tag['abstand']));
			}

			$dateien = array();
			foreach ($tag['dateien'] as $datei) {
				$dateien[] = array
				(
					'name'    => $datei['name'],
					'url'     => $this->generateUrl('wertungsportal_dwzarchiv_download', array('pfad' => $datei['pfad'])),
					'groesse' => self::groesse($datei['groesse']),
					'quelle'  => $t['quellen'][$datei['quelle']] ?? $datei['quelle'],
				);
			}

			$tage[] = array
			(
				'datum'   => substr($tag['datum'], 6, 2).'.'.substr($tag['datum'], 4, 2).'.'.substr($tag['datum'], 0, 4),
				'abstand' => $abstand,
				'genau'   => 0 === $tag['abstand'],
				'dateien' => $dateien,
			);
		}

		return $tage;
	}

	/**
	 * Liest das Datum aus dem Formular.
	 *
	 * @param string $eingabe Wert von `datum`, erwartet JJJJ-MM-TT
	 *
	 * @return string Der Tag als JJJJMMTT; bei leerer oder ungültiger Eingabe heute
	 */
	private static function datum(string $eingabe): string
	{
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $eingabe, $teile) && checkdate((int) $teile[2], (int) $teile[3], (int) $teile[1])) {
			return $teile[1].$teile[2].$teile[3];
		}

		return date('Ymd');
	}

	/**
	 * Wandelt JJJJMMTT in die Form des Datumsfelds (JJJJ-MM-TT).
	 *
	 * @param string $datum JJJJMMTT
	 *
	 * @return string JJJJ-MM-TT
	 */
	private static function isoDatum(string $datum): string
	{
		return substr($datum, 0, 4).'-'.substr($datum, 4, 2).'-'.substr($datum, 6, 2);
	}
}
