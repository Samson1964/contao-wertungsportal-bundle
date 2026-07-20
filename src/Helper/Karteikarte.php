<?php

namespace Schachbulle\ContaoWertungsportalBundle\Helper;

// ─────────────────────────────────────────────
//  Karteikarte-Klasse
//  Gibt die Kopf-, Vereins- und Turnierdaten der Spieler-Karteikarte
//  formatiert zurück, so dass nur noch das Template gefüllt werden muß
// ─────────────────────────────────────────────

class Karteikarte
{
	// ─────────────────────────────────────────────
	//  Konfiguration (öffentliche Eigenschaften)
	// ─────────────────────────────────────────────
	public array $apiKarteikarte; // Enthält das Array von der API mit der Karteikarte
	public array $apiTurniere; // Enthält das Array von der API mit der Turnierhistorie
	private array $daten = array(); // Enthält die Daten formatiert für das Template

	// ─────────────────────────────────────────────
	//  Konstruktor – initialisiert alle Konfigurationswerte
	// ─────────────────────────────────────────────
	public function __construct($Karteikarte, $Turniere)
	{
		$this->apiKarteikarte = is_array($Karteikarte) ? $Karteikarte : array();
		$this->apiTurniere = is_array($Turniere) ? $Turniere : array();

		$this->compile(); // Weiter mit dieser Funktion
	}

	// ─────────────────────────────────────────────
	//  Funktion compile
	//  Erstellt die formatierten Kopfdaten, die Vereinsliste und
	//  die Turnierauswertungen (Kartei)
	// ─────────────────────────────────────────────
	public function compile()
	{
		$body = $this->apiKarteikarte['body'];

		// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
		if(!array_key_exists('rating', $body)) $body['rating'] = false;
		if(!array_key_exists('index', $body)) $body['index'] = false;

		/*********************************************************
		 * Kopfdaten
		*/

		$this->daten['Spielername']  = sprintf("%s,%s", $body['lastname'], $body['firstname']);
		$this->daten['Titelname']    = $body['firstname'].' '.$body['lastname'];
		$this->daten['Geburtsjahr']  = $GLOBALS['TL_CONFIG']['wertungsportal_geburtsjahr_ausblenden'] ? '****' : $body['birthyear'];
		$this->daten['Geschlecht']   = $GLOBALS['TL_CONFIG']['wertungsportal_geschlecht_ausblenden'] ? '*' : ($body['gender'] == 'MALE' ? 'M' : ($body['gender'] == 'FEMALE' ? 'W' : strtoupper($body['gender'])));
		$this->daten['NuId']         = $body['nuLigaPersonId'];
		$this->daten['DWZ']          = $body['rating'].' - '.$body['index'];
		$this->daten['FideId']       = isset($body['fideId']) ? sprintf('<a href="https://ratings.fide.com/profile/%s" target="_blank">%s</a>', $body['fideId'], $body['fideId']) : '-';
		$this->daten['Elo']          = isset($body['fideElo']) ? $body['fideElo'] : '';
		$this->daten['FideTitel']    = isset($body['fideTitle']) ? $body['fideTitle'] : '';
		$this->daten['FideNation']   = isset($body['fideNation']) ? $body['fideNation'] : '';
		$this->daten['Historie']     = '-';
		$this->daten['Referent']     = '-';

		/*********************************************************
		 * Vereinsdaten (sortiert nach Status und ZPS)
		*/

		$sortiert = array();
		if(!empty($body['memberships']))
		{
			foreach($body['memberships'] as $mitglied)
			{
				$status = substr($mitglied['licenceState'], 0, 1);
				$zps_nr = sprintf("%s-%04d", $mitglied['vkz'], $mitglied['memberNo']);
				$verein = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);

				$sortiert[$status.$zps_nr] = array
				(
					'name'      => $verein,
					'zps'       => $zps_nr,
					'status'    => $status,
					// Verbandszugehörigkeiten (DSB → ... → Verband) aus tl_wertungsportal_clubs
					'verbaende' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandskette($mitglied['vkz']),
				);
			}
		}
		ksort($sortiert);
		$this->daten['Vereine'] = $sortiert;

		/*********************************************************
		 * Turnierauswertungen (Kartei)
		*/

		$kartei = array();
		$diagramm = array(); // Rohdaten für das DWZ/Leistungs-Diagramm

		if(empty($this->apiTurniere['error']) && isset($this->apiTurniere['body']['entries']) && is_array($this->apiTurniere['body']['entries']))
		{
			$entries = $this->apiTurniere['body']['entries'];
			$i = count($entries) + 1;

			foreach($entries as $turnier)
			{
				$i--;

				// Auf nichtexistierende Variablen prüfen, die aber benötigt werden:
				if(!array_key_exists('winsExpected', $turnier['player'])) $turnier['player']['winsExpected'] = false;
				if(!array_key_exists('factorK', $turnier['player'])) $turnier['player']['factorK'] = false;
				if(!array_key_exists('tournamentPerformance', $turnier['player'])) $turnier['player']['tournamentPerformance'] = false;
				if(!array_key_exists('ratingOld', $turnier['player'])) $turnier['player']['ratingOld'] = false;
				if(!array_key_exists('indexOld', $turnier['player'])) $turnier['player']['indexOld'] = false;
				if(!array_key_exists('ratingNew', $turnier['player'])) $turnier['player']['ratingNew'] = false;
				if(!array_key_exists('indexNew', $turnier['player'])) $turnier['player']['indexNew'] = false;
				if(!array_key_exists('averageRatingCompetitors', $turnier['player'])) $turnier['player']['averageRatingCompetitors'] = false;
				if(!array_key_exists('wins', $turnier['player'])) $turnier['player']['wins'] = false;
				if(!array_key_exists('numberOfGames', $turnier['player'])) $turnier['player']['numberOfGames'] = '';

				$dwz_neu = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($turnier['player']['ratingNew'], $turnier['player']['indexNew']);

				$kartei[] = array
				(
					'nummer'     => ($i == count($entries) && $dwz_neu != '&nbsp;') ? 'AKT' : $i,
					'jahr'       => substr($turnier['tournament']['enddate'], 0, 4),
					'turnier'    => sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getTurnierseiteUrl()."/%s/%s.html\" title=\"%s\">%s</a>", $turnier['tournament']['uuid'], $turnier['player']['playerUuid'], $turnier['tournament']['label'], \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Turnierkurzname($turnier['tournament']['label'])),
					'punkte'     => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Punkte($turnier['player']['wins']),
					'partien'    => $turnier['player']['numberOfGames'],
					'we'         => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Erwartungswert($turnier['player']['winsExpected']),
					'e'          => $turnier['player']['factorK'],
					'gegner'     => $turnier['player']['averageRatingCompetitors'] ? $turnier['player']['averageRatingCompetitors'] : '',
					'leistung'   => $turnier['player']['tournamentPerformance'] ? $turnier['player']['tournamentPerformance'] : '',
					'dwz-neu'    => $dwz_neu,
					'ungewertet' => '',
				);

				// Rohwerte für das Diagramm (0 = kein Wert vorhanden);
				// Gegnerschnitt/Punkte/Partien werden für die Leistungsschätzung
				// bei Turnieren unter 5 Partien gebraucht
				$diagramm[] = array
				(
					'jahr'     => substr($turnier['tournament']['enddate'], 0, 4),
					'dwz'      => (int) $turnier['player']['ratingNew'],
					'leistung' => (int) $turnier['player']['tournamentPerformance'],
					'gegner'   => (int) $turnier['player']['averageRatingCompetitors'],
					'punkte'   => (float) $turnier['player']['wins'],
					'partien'  => (int) $turnier['player']['numberOfGames'],
					'turnier'  => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Turnierkurzname($turnier['tournament']['label']),
				);
			}
		}

		$this->daten['Kartei'] = $kartei;

		// Diagramm DWZ und Leistung (chronologisch, die API liefert die
		// Einträge mit dem neuesten zuerst — deshalb umdrehen).
		// Bei mehr als 50 Turnieren wird nur das gekürzte Diagramm direkt
		// angezeigt; das komplette wandert ins Template-Overlay (Lightbox).
		$chronologisch = array_reverse($diagramm);

		if(count($chronologisch) > 50)
		{
			$this->daten['Diagramm'] = $this->erstelleDiagramm(array_slice($chronologisch, -50));
			$this->daten['DiagrammKomplett'] = $this->erstelleDiagramm($chronologisch, max(700, count($chronologisch) * 14));
		}
		else
		{
			$this->daten['Diagramm'] = $this->erstelleDiagramm($chronologisch);
			$this->daten['DiagrammKomplett'] = '';
		}
	}

	// ─────────────────────────────────────────────
	//  Funktion erstelleDiagramm
	//  Baut ein SVG-Liniendiagramm für DWZ und Leistung über die
	//  Turnierauswertungen (chronologisch von links nach rechts).
	//  Leistungslücken (keine Leistung unter 5 Partien) werden geschätzt,
	//  damit die Leistungskurve ohne Unterbrechungen durchläuft.
	//  Rückgabe '' bei weniger als 2 verwertbaren Punkten.
	// ─────────────────────────────────────────────
	private function erstelleDiagramm($punkte, $breite = 700)
	{
		// Fehlende Leistungen aus den Turnierdaten schätzen (lineare
		// Näherung: Gegnerschnitt + 800 × Score-Anteil − 400)
		foreach($punkte as $k => $p)
		{
			$punkte[$k]['geschaetzt'] = false;

			if($p['leistung'] <= 0 && $p['gegner'] > 0 && $p['partien'] > 0)
			{
				$punkte[$k]['leistung'] = (int) round($p['gegner'] + 800 * $p['punkte'] / $p['partien'] - 400);
				$punkte[$k]['geschaetzt'] = true;
			}
		}

		// Nur Einträge mit mindestens einem Wert verwenden
		$punkte = array_values(array_filter($punkte, function($p) { return $p['dwz'] > 0 || $p['leistung'] > 0; }));
		if(count($punkte) < 2) return '';

		// Verbleibende Leistungslücken linear zwischen den Nachbarwerten
		// interpolieren (Ränder: nächstliegender bekannter Wert)
		$bekannt = array();
		foreach($punkte as $k => $p)
		{
			if($p['leistung'] > 0) $bekannt[] = $k;
		}

		if(count($bekannt))
		{
			for($k = 0; $k < count($punkte); $k++)
			{
				if($punkte[$k]['leistung'] > 0) continue;

				$vor = null;
				$nach = null;
				foreach($bekannt as $b)
				{
					if($b < $k) $vor = $b;
					elseif($b > $k) { $nach = $b; break; }
				}

				if($vor !== null && $nach !== null) $wert = $punkte[$vor]['leistung'] + ($punkte[$nach]['leistung'] - $punkte[$vor]['leistung']) * ($k - $vor) / ($nach - $vor);
				elseif($vor !== null) $wert = $punkte[$vor]['leistung'];
				else $wert = $punkte[$nach]['leistung'];

				$punkte[$k]['leistung'] = (int) round($wert);
				$punkte[$k]['geschaetzt'] = true;
			}
		}

		// Wertebereich ermitteln (auf volle 100 gerundet, mit Puffer)
		$werte = array();
		foreach($punkte as $p)
		{
			if($p['dwz'] > 0) $werte[] = $p['dwz'];
			if($p['leistung'] > 0) $werte[] = $p['leistung'];
		}
		$min = (int) (floor((min($werte) - 50) / 100) * 100);
		$max = (int) (ceil((max($werte) + 50) / 100) * 100);
		if($min < 0) $min = 0;

		// Geometrie ($breite > 700 = breites Komplett-Diagramm für das Overlay,
		// dort sorgt der scrollbare Container für die Darstellung)
		$hoehe = 300;
		$links = 50; $rechts = 10; $oben = 10; $unten = 46;
		$innenB = $breite - $links - $rechts;
		$innenH = $hoehe - $oben - $unten;
		$n = count($punkte);
		$dx = ($n > 1) ? $innenB / ($n - 1) : 0;

		$xpos = function($i) use ($links, $dx) { return round($links + $i * $dx, 1); };
		$ypos = function($wert) use ($oben, $innenH, $min, $max) { return round($oben + $innenH - ($wert - $min) / ($max - $min) * $innenH, 1); };

		// Breite Komplett-Diagramme nicht auf Containerbreite stauchen —
		// der umgebende Overlay-Container scrollt horizontal
		$style = ($breite > 700) ? 'width:'.$breite.'px;max-width:none;height:auto;' : 'max-width:100%;height:auto;';
		$svg = '<svg class="dwz-diagramm" viewBox="0 0 '.$breite.' '.$hoehe.'" xmlns="http://www.w3.org/2000/svg" role="img" style="'.$style.'">';

		// Horizontale Gitterlinien mit Y-Beschriftung
		$schritt = (($max - $min) / 100 > 6) ? 200 : 100;
		for($w = $min; $w <= $max; $w += $schritt)
		{
			$y = $ypos($w);
			$svg .= '<line x1="'.$links.'" y1="'.$y.'" x2="'.($breite - $rechts).'" y2="'.$y.'" stroke="#cccccc" stroke-width="1"/>';
			$svg .= '<text x="'.($links - 6).'" y="'.($y + 4).'" text-anchor="end" font-size="11" fill="#666666">'.$w.'</text>';
		}

		// X-Beschriftung: Jahr (nur beim Jahreswechsel), um 45° gedreht,
		// damit sich die Jahreszahlen nicht überschneiden
		$jahr = '';
		for($i = 0; $i < $n; $i++)
		{
			if($punkte[$i]['jahr'] != $jahr)
			{
				$jahr = $punkte[$i]['jahr'];
				$lx = $xpos($i);
				$ly = $oben + $innenH + 14;
				$svg .= '<text x="'.$lx.'" y="'.$ly.'" transform="rotate(45 '.$lx.' '.$ly.')" text-anchor="start" font-size="11" fill="#666666">'.$jahr.'</text>';
			}
		}

		// Linien und Punkte je Serie zeichnen (fehlende Werte unterbrechen die Linie)
		foreach(array('dwz' => '#3465a4', 'leistung' => '#e0821e') as $serie => $farbe)
		{
			$pfad = '';
			$neu = true;
			for($i = 0; $i < $n; $i++)
			{
				if($punkte[$i][$serie] <= 0)
				{
					$neu = true;
					continue;
				}
				$pfad .= ($neu ? 'M' : 'L').$xpos($i).' '.$ypos($punkte[$i][$serie]).' ';
				$neu = false;
			}
			if($pfad == '') continue;

			$strich = ($serie == 'leistung') ? ' stroke-dasharray="4 3"' : '';
			$svg .= '<path d="'.trim($pfad).'" fill="none" stroke="'.$farbe.'" stroke-width="2"'.$strich.'/>';

			// Punkte mit Tooltip (Turnier, Jahr und Wert); geschätzte
			// Leistungswerte als hohle Kreise mit Hinweis im Tooltip
			for($i = 0; $i < $n; $i++)
			{
				if($punkte[$i][$serie] <= 0) continue;
				$geschaetzt = ($serie == 'leistung' && !empty($punkte[$i]['geschaetzt']));
				$kreis = $geschaetzt ? 'fill="#ffffff" stroke="'.$farbe.'" stroke-width="1.5"' : 'fill="'.$farbe.'"';
				$svg .= '<circle cx="'.$xpos($i).'" cy="'.$ypos($punkte[$i][$serie]).'" r="3" '.$kreis.'><title>'.\StringUtil::specialchars($punkte[$i]['turnier']).' ('.$punkte[$i]['jahr'].'): '.($serie == 'dwz' ? 'DWZ ' : 'Leistung ').$punkte[$i][$serie].($geschaetzt ? ' (geschätzt)' : '').'</title></circle>';
			}
		}

		// Legende oben rechts (bei breiten Overlay-Diagrammen links,
		// damit sie ohne Scrollen sichtbar ist)
		$lx = ($breite > 700) ? $links + 10 : $breite - $rechts - 160;
		$svg .= '<line x1="'.$lx.'" y1="'.($oben + 8).'" x2="'.($lx + 24).'" y2="'.($oben + 8).'" stroke="#3465a4" stroke-width="2"/>';
		$svg .= '<text x="'.($lx + 30).'" y="'.($oben + 12).'" font-size="11" fill="#333333">DWZ</text>';
		$svg .= '<line x1="'.($lx + 70).'" y1="'.($oben + 8).'" x2="'.($lx + 94).'" y2="'.($oben + 8).'" stroke="#e0821e" stroke-width="2" stroke-dasharray="4 3"/>';
		$svg .= '<text x="'.($lx + 100).'" y="'.($oben + 12).'" font-size="11" fill="#333333">Leistung</text>';
		$svg .= '</svg>';

		return $svg;
	}

	// ─────────────────────────────────────────────
	//  Magische Methode __set
	// ─────────────────────────────────────────────
	public function __set($name, $value)
	{
		$this->daten[$name] = $value;
	}

	// ─────────────────────────────────────────────
	//  Magische Methode __get
	// ─────────────────────────────────────────────
	public function __get($name)
	{
		return array_key_exists($name, $this->daten) ? $this->daten[$name] : null;
	}
}
