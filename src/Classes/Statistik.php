<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @package   Wertungsportal
 * @file      Statistik
 * @author    Frank Binding
 * @license   GNU/LGPL
 * @copyright Frank Binding 2026
 *
 * Backend-Modul „Statistik": wertet die gezählten Abrufe der
 * Schnittstellenfunktionen aus (tl_wertungsportal_stats).
 *
 * Ansichten:
 *   - Übersicht aller Funktionen im gewählten Zeitraum
 *   - Verlauf nach Woche oder Monat (gestapelte Balken: Cache und API)
 *   - je Funktion eine eigene Ansicht mit eigenem Diagramm
 *
 * Die Diagramme sind serverseitig erzeugtes SVG — wie beim DWZ-Diagramm der
 * Karteikarte, also ohne zusätzliche Javascript-Bibliothek.
 */

namespace Schachbulle\ContaoWertungsportalBundle\Classes;

class Statistik extends \BackendModule
{
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_wp_statistik';

	/**
	 * Farben der beiden Quellen
	 */
	const FARBE_CACHE = '#7FB3D5';
	const FARBE_API   = '#1F618D';

	/**
	 * Generate the module
	 */
	protected function compile()
	{
		$raster = \Input::get('raster');
		if(!in_array($raster, array('woche', 'monat'), true)) $raster = 'woche';

		$funktion = (string) \Input::get('funktion');
		$zeitraum = (int) \Input::get('zeitraum');
		if(!in_array($zeitraum, array(30, 90, 180, 365), true)) $zeitraum = 90;

		$bis = date('Y-m-d');
		$von = date('Y-m-d', strtotime('-'.($zeitraum - 1).' days'));

		// Gültige Funktionen aus der API-Zuordnung
		$endpunkte = \Schachbulle\ContaoWertungsportalBundle\Helper\API::endpunkte();
		if($funktion !== '' && !isset($endpunkte[$funktion])) $funktion = '';

		/*********************************************************
		 * Übersicht aller Funktionen
		*/

		$summen = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::summenNachFunktion($von, $bis);

		$zeilen = array();
		$gesamt = array('api' => 0, 'cache' => 0, 'gesamt' => 0);

		foreach($endpunkte as $name => $pfad)
		{
			$werte = isset($summen[$name]) ? $summen[$name] : array('api' => 0, 'cache' => 0, 'gesamt' => 0);

			$zeilen[] = array
			(
				'funktion' => $name,
				'endpunkt' => $pfad,
				'api'      => $werte['api'],
				'cache'    => $werte['cache'],
				'gesamt'   => $werte['gesamt'],
				'quote'    => $werte['gesamt'] ? round($werte['cache'] * 100 / $werte['gesamt']) : 0,
				'aktiv'    => ($funktion === $name),
			);

			$gesamt['api'] += $werte['api'];
			$gesamt['cache'] += $werte['cache'];
			$gesamt['gesamt'] += $werte['gesamt'];
		}

		// Nach Gesamtabrufen sortieren, damit die stärksten oben stehen
		usort($zeilen, function($a, $b) { return $b['gesamt'] <=> $a['gesamt']; });

		$gesamt['quote'] = $gesamt['gesamt'] ? round($gesamt['cache'] * 100 / $gesamt['gesamt']) : 0;

		/*********************************************************
		 * Verlauf nach Woche/Monat für das Diagramm
		*/

		$raster_daten = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::summenNachRaster($von, $bis, $raster, $funktion);
		$balken = array();

		foreach($raster_daten as $gruppe => $werte)
		{
			$balken[] = array
			(
				'titel' => self::rasterTitel((string) $gruppe, $raster, isset($werte['erster']) ? $werte['erster'] : ''),
				'cache' => (int) $werte['cache'],
				'api'   => (int) $werte['api'],
			);
		}

		/*********************************************************
		 * Template füllen
		*/

		$this->Template->zeilen = $zeilen;
		$this->Template->gesamt = $gesamt;
		$this->Template->diagramm = self::diagramm($balken, $raster);
		$this->Template->raster = $raster;
		$this->Template->zeitraum = $zeitraum;
		$this->Template->funktion = $funktion;
		$this->Template->endpunkt = $funktion !== '' ? $endpunkte[$funktion] : '';
		$this->Template->von = \Date::parse(\Config::get('dateFormat'), strtotime($von));
		$this->Template->bis = \Date::parse(\Config::get('dateFormat'), strtotime($bis));
		$this->Template->erster = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalStatsModel::ersterTag();
		$this->Template->hatDaten = ($gesamt['gesamt'] > 0);
		$this->Template->basisUrl = \Backend::addToUrl('', true, array('raster', 'zeitraum', 'funktion'));
		$this->Template->farbeCache = self::FARBE_CACHE;
		$this->Template->farbeApi = self::FARBE_API;
	}

	/**
	 * Baut die Beschriftung einer Rastergruppe
	 *
	 * @param  string $gruppe  Gruppenschlüssel (JJJJWW bzw. JJJJMM)
	 * @param  string $raster  woche|monat
	 * @param  string $erster  erstes Datum der Gruppe
	 * @return string
	 */
	protected static function rasterTitel($gruppe, $raster, $erster)
	{
		if($raster == 'woche')
		{
			// YEARWEEK liefert JJJJWW
			$jahr = substr($gruppe, 0, 4);
			$woche = substr($gruppe, 4);

			return 'KW '.ltrim($woche, '0').'/'.$jahr;
		}

		// JJJJMM
		$monate = array('01' => 'Jan', '02' => 'Feb', '03' => 'Mär', '04' => 'Apr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Dez');
		$jahr = substr($gruppe, 0, 4);
		$monat = substr($gruppe, 4, 2);

		return (isset($monate[$monat]) ? $monate[$monat] : $monat).' '.$jahr;
	}

	/**
	 * Erzeugt ein gestapeltes Balkendiagramm als SVG (unten Cache, oben API).
	 * Ohne Werte wird ein leerer String geliefert — das Template zeigt dann
	 * einen Hinweis statt eines leeren Rahmens.
	 *
	 * @param  array  $balken  Liste aus titel, cache, api
	 * @param  string $raster  woche|monat (nur für die Beschriftung)
	 * @return string          SVG oder ''
	 */
	protected static function diagramm($balken, $raster)
	{
		if(!count($balken)) return '';

		// Nur die letzten 26 Gruppen zeigen, sonst wird es unleserlich
		if(count($balken) > 26) $balken = array_slice($balken, -26);

		$hoehe = 260;
		$randLinks = 55;
		$randUnten = 54;
		$randOben = 14;
		$abstand = 12;

		// Balkenbreite so wählen, dass die Zeichenfläche gefüllt wird: wenige
		// Gruppen ergeben breitere Balken, viele schmalere (Grenzen 18–64 px).
		// Ohne das säßen zwölf Wochen als schmaler Streifen links im Fenster
		$zielBreite = 1000;
		$balkenBreite = (int) round(($zielBreite - $randLinks - 20) / max(1, count($balken)) - $abstand);
		if($balkenBreite > 64) $balkenBreite = 64;
		if($balkenBreite < 18) $balkenBreite = 18;

		$breite = $randLinks + count($balken) * ($balkenBreite + $abstand) + 20;

		// Maximalwert und runde Skala bestimmen
		$max = 0;
		foreach($balken as $b) $max = max($max, $b['cache'] + $b['api']);
		if($max < 1) $max = 1;

		$schritt = pow(10, max(0, strlen((string) (int) $max) - 2));
		if($schritt < 1) $schritt = 1;
		$maxSkala = ceil($max / ($schritt * 5)) * $schritt * 5;
		if($maxSkala < 5) $maxSkala = 5;

		$nutzHoehe = $hoehe - $randOben - $randUnten;

		$svg = array();
		$svg[] = '<svg viewBox="0 0 '.$breite.' '.$hoehe.'" width="100%" height="'.$hoehe.'" role="img" aria-label="Abrufe je '.($raster == 'woche' ? 'Kalenderwoche' : 'Monat').'" xmlns="http://www.w3.org/2000/svg" style="max-width:'.$breite.'px">';
		$svg[] = '<style>.wpst-achse{font:11px sans-serif;fill:#666}.wpst-wert{font:10px sans-serif;fill:#333}</style>';

		// Waagerechte Hilfslinien mit Beschriftung
		for($i = 0; $i <= 5; $i++)
		{
			$wert = $maxSkala / 5 * $i;
			$y = $randOben + $nutzHoehe - ($nutzHoehe / 5 * $i);
			$svg[] = '<line x1="'.$randLinks.'" y1="'.round($y, 1).'" x2="'.($breite - 10).'" y2="'.round($y, 1).'" stroke="#e2e2e2" stroke-width="1"/>';
			$svg[] = '<text x="'.($randLinks - 8).'" y="'.round($y + 4, 1).'" text-anchor="end" class="wpst-achse">'.round($wert).'</text>';
		}

		// Balken
		$x = $randLinks + $abstand / 2;

		foreach($balken as $b)
		{
			$summe = $b['cache'] + $b['api'];
			$hCache = $summe ? round($nutzHoehe * $b['cache'] / $maxSkala, 1) : 0;
			$hApi = $summe ? round($nutzHoehe * $b['api'] / $maxSkala, 1) : 0;

			$yCache = $randOben + $nutzHoehe - $hCache;
			$yApi = $yCache - $hApi;

			if($hCache > 0)
			{
				$svg[] = '<rect x="'.$x.'" y="'.$yCache.'" width="'.$balkenBreite.'" height="'.$hCache.'" fill="'.self::FARBE_CACHE.'"><title>'.$b['titel'].': '.$b['cache'].' aus dem Cache</title></rect>';
			}

			if($hApi > 0)
			{
				$svg[] = '<rect x="'.$x.'" y="'.$yApi.'" width="'.$balkenBreite.'" height="'.$hApi.'" fill="'.self::FARBE_API.'"><title>'.$b['titel'].': '.$b['api'].' von der API</title></rect>';
			}

			// Summe über dem Balken
			if($summe > 0)
			{
				$svg[] = '<text x="'.($x + $balkenBreite / 2).'" y="'.round($yApi - 4, 1).'" text-anchor="middle" class="wpst-wert">'.$summe.'</text>';
			}

			// Beschriftung schräg unter dem Balken
			$xt = $x + $balkenBreite / 2;
			$yt = $randOben + $nutzHoehe + 14;
			$svg[] = '<text x="'.$xt.'" y="'.$yt.'" text-anchor="end" class="wpst-achse" transform="rotate(-45 '.$xt.' '.$yt.')">'.$b['titel'].'</text>';

			$x += $balkenBreite + $abstand;
		}

		// Grundlinie
		$svg[] = '<line x1="'.$randLinks.'" y1="'.($randOben + $nutzHoehe).'" x2="'.($breite - 10).'" y2="'.($randOben + $nutzHoehe).'" stroke="#999" stroke-width="1"/>';
		$svg[] = '</svg>';

		return implode("\n", $svg);
	}
}
