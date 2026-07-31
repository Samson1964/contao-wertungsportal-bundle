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
		$this->daten['FideNation']   = isset($body['fideNation']) && $body['fideNation'] != '' ? sprintf('<a href="https://ratings.fide.com/rankings.phtml?country=%s" target="_blank">%s</a>', rawurlencode($body['fideNation']), $body['fideNation']) : '';
		$this->daten['Historie']     = '-';
		$this->daten['Referent']     = '-';

		/*********************************************************
		 * Historie: Link zur alten EloBase-Karteikarte (altdwz),
		 * wenn die Anzeige in den Einstellungen aktiviert ist.
		 * Als zps wird VKZ-Mitgliedsnummer übergeben (aktive
		 * Mitgliedschaft bevorzugt, sonst die erste)
		*/

		if(!empty($GLOBALS['TL_CONFIG']['wertungsportal_historie']) && !empty($body['memberships']))
		{
			$url = !empty($GLOBALS['TL_CONFIG']['wertungsportal_elobase_url']) ? $GLOBALS['TL_CONFIG']['wertungsportal_elobase_url'] : 'http://altdwz.schachbund.net/db/spieler.html?zps=';

			$mitglied = $body['memberships'][0];
			foreach($body['memberships'] as $m)
			{
				if($m['licenceState'] == 'ACTIVE')
				{
					$mitglied = $m;
					break;
				}
			}

			$this->daten['Historie'] = sprintf('<a href="%s%s-%04d" target="_blank">Alte Karteikarte</a> (Benutzer/Passwort: dwz)', $url, $mitglied['vkz'], $mitglied['memberNo']);
		}

		/*********************************************************
		 * Vereinsdaten (sortiert nach Status und ZPS)
		*/

		$sortiert = array();
		if(!empty($body['memberships']))
		{
			// Hinweis: Platzhalter-Mitgliedschaften mit der Nummer 0000 sind
			// hier bereits entfernt — der Filter läuft zentral für alle
			// Ausgaben in API::autoQuery (Helper::filterMitgliedsnummern)
			foreach($body['memberships'] as $mitglied)
			{
				$status = substr($mitglied['licenceState'], 0, 1);
				$zps_nr = sprintf("%s-%04d", $mitglied['vkz'], $mitglied['memberNo']);
				$verein = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVereinseiteUrl()."/%s.html\">%s</a>", $mitglied['vkz'], $mitglied['clubName']);

				// Direkt übergeordneter Verband: spezifischster lokal existierender
				// Verband über der Vereins-VKZ (Kreis → Bezirk → Landesverband →
				// DSB als Fallback). Liefert VKZ und Namen für den Link.
				$verbandInfo = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerband($mitglied['vkz']);
				$verband = sprintf("<a href=\"".\Schachbulle\ContaoWertungsportalBundle\Helper\Helper::getVerbandseiteUrl()."/%s.html\">%s</a>", $verbandInfo['vkz'], $verbandInfo['name']);

				$sortiert[$status.$zps_nr] = array
				(
					'name'    => $verein,
					'zps'     => $zps_nr,
					'status'  => $status,
					'verband' => $verband,
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

		if(empty($this->apiTurniere['error']) && isset($this->apiTurniere['body']) && is_array($this->apiTurniere['body']))
		{
			$entries = (isset($this->apiTurniere['body']['entries']) && is_array($this->apiTurniere['body']['entries'])) ? $this->apiTurniere['body']['entries'] : array();
			$upgrades = (isset($this->apiTurniere['body']['upgrades']) && is_array($this->apiTurniere['body']['upgrades'])) ? $this->apiTurniere['body']['upgrades'] : array();

			// Turniere (entries) und DWZ-Umstufungen (upgrades) zu einer
			// gemeinsamen, chronologisch absteigend sortierten Liste
			// zusammenführen (Turnier: enddate, Umstufung: referenceDate)
			$eintraege = array();
			foreach($entries as $turnier)
			{
				$eintraege[] = array('typ' => 'turnier', 'datum' => (string) ($turnier['tournament']['enddate'] ?? ''), 'data' => $turnier);
			}
			foreach($upgrades as $up)
			{
				$eintraege[] = array('typ' => 'upgrade', 'datum' => (string) ($up['referenceDate'] ?? ''), 'data' => $up);
			}

			// Absteigend nach Datum (neueste zuerst); bei gleichem Datum
			// gewinnt das Turnier (das Upgrade baut darauf auf)
			usort($eintraege, function($a, $b)
			{
				if($a['datum'] === $b['datum']) return ($a['typ'] === 'turnier') ? -1 : 1;
				return strcmp($b['datum'], $a['datum']);
			});

			// Durchgehende Nummerierung über ALLE Einträge (Turniere UND
			// Umstufungen): der neueste Eintrag (oben) bekommt AKT, die
			// übrigen laufende Nummern absteigend
			$gesamt = count($eintraege);
			$laufNr = $gesamt;

			foreach($eintraege as $index => $eintrag)
			{
				if($eintrag['typ'] == 'turnier')
				{
					$turnier = $eintrag['data'];

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
					$nummer = ($index == 0 && $dwz_neu != '&nbsp;') ? 'AKT' : $laufNr;

					$kartei[] = array
					(
						'typ'        => 'turnier',
						'nummer'     => $nummer,
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

					// Rohwerte für das Diagramm — Turniere ohne Auswertung
					// (0 Partien) werden übersprungen. Gegnerschnitt/Punkte/
					// Partien werden für die Leistungsschätzung gebraucht.
					if((int) $turnier['player']['numberOfGames'] > 0)
					{
						$diagramm[] = array
						(
							'nummer'   => $nummer,
							'jahr'     => substr($turnier['tournament']['enddate'], 0, 4),
							'dwz'      => (int) $turnier['player']['ratingNew'],
							'leistung' => (int) $turnier['player']['tournamentPerformance'],
							'gegner'   => (int) $turnier['player']['averageRatingCompetitors'],
							'punkte'   => (float) $turnier['player']['wins'],
							'partien'  => (int) $turnier['player']['numberOfGames'],
							'bezeichnung' => \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::Turnierkurzname($turnier['tournament']['label']),
						);
					}
				}
				else
				{
					// DWZ-Umstufung (administrativ, kein Turnier) — als
					// hervorgehobene Zeile mit Name und resultierender DWZ
					$up = $eintrag['data'];
					$dwz_neu = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::DWZ($up['ratingNew'] ?? 0, $up['indexNew'] ?? 0);
					$nummer = ($index == 0 && $dwz_neu != '&nbsp;') ? 'AKT' : $laufNr;

					$kartei[] = array
					(
						'typ'        => 'upgrade',
						'nummer'     => $nummer,
						'jahr'       => substr((string) ($up['referenceDate'] ?? ''), 0, 4),
						// Datum der Umstufung hinter den Namen: Der Name nennt
						// oft nur das Jahr ("Umstufung 2026"), das Stichdatum
						// steht aber im referenceDate. Fehlt es, bleibt der
						// Name für sich stehen (keine leere Klammer)
						'turnier'    => \StringUtil::specialchars((string) ($up['name'] ?? 'DWZ-Umstufung'))
						                .(($datum = \Schachbulle\ContaoWertungsportalBundle\Helper\Helper::ApiDatum($up['referenceDate'] ?? null, 'Y-m-d', 'd.m.Y', '')) ? ' ('.$datum.')' : ''),
						'punkte'     => '',
						'partien'    => '',
						'we'         => '',
						'e'          => '',
						'gegner'     => '',
						'leistung'   => '',
						'dwz-neu'    => $dwz_neu,
						'ungewertet' => '',
					);

					// Umstufung als DWZ-Punkt ins Diagramm (keine Leistung/Partien)
					$diagramm[] = array
					(
						'nummer'   => $nummer,
						'jahr'     => substr((string) ($up['referenceDate'] ?? ''), 0, 4),
						'dwz'      => (int) ($up['ratingNew'] ?? 0),
						'leistung' => 0,
						'gegner'   => 0,
						'punkte'   => 0,
						'partien'  => 0,
						'bezeichnung' => (string) ($up['name'] ?? 'DWZ-Umstufung'),
					);
				}

				$laufNr--;
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
		// dort sorgt der scrollbare Container für die Darstellung).
		// $oben lässt oberhalb der Skala Platz für die Legende (damit hohe
		// Leistungswerte sie nicht überschreiben), $unten für die steil
		// gedrehten Jahreszahlen.
		$hoehe = 300;
		$links = 50; $rechts = 10; $oben = 28; $unten = 54;
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

		// X-Beschriftung: Jahr (nur beim Jahreswechsel), senkrecht (90°),
		// damit sich die Jahreszahlen nicht überschneiden
		$jahr = '';
		for($i = 0; $i < $n; $i++)
		{
			if($punkte[$i]['jahr'] != $jahr)
			{
				$jahr = $punkte[$i]['jahr'];
				$lx = $xpos($i);
				$ly = $oben + $innenH + 12;
				$svg .= '<text x="'.$lx.'" y="'.$ly.'" transform="rotate(90 '.$lx.' '.$ly.')" text-anchor="start" font-size="11" fill="#666666">'.$jahr.'</text>';
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

			// Punkte mit Tooltip (Nummer des Eintrags, Bezeichnung, Jahr und
			// Wert); geschätzte Leistungswerte als hohle Kreise mit Hinweis
			for($i = 0; $i < $n; $i++)
			{
				if($punkte[$i][$serie] <= 0) continue;
				$geschaetzt = ($serie == 'leistung' && !empty($punkte[$i]['geschaetzt']));
				$kreis = $geschaetzt ? 'fill="#ffffff" stroke="'.$farbe.'" stroke-width="1.5"' : 'fill="'.$farbe.'"';
				$nummer = isset($punkte[$i]['nummer']) && $punkte[$i]['nummer'] !== '' ? 'Nr. '.$punkte[$i]['nummer'].': ' : '';
				$svg .= '<circle cx="'.$xpos($i).'" cy="'.$ypos($punkte[$i][$serie]).'" r="3" '.$kreis.'><title>'.$nummer.\StringUtil::specialchars($punkte[$i]['bezeichnung']).' ('.$punkte[$i]['jahr'].'): '.($serie == 'dwz' ? 'DWZ ' : 'Leistung ').$punkte[$i][$serie].($geschaetzt ? ' (geschätzt)' : '').'</title></circle>';
			}
		}

		// Legende oberhalb der Skala (im freien oberen Rand), damit sie von
		// hohen Leistungswerten nicht überschrieben wird
		$lx = $links;
		$ly = 12;
		$svg .= '<line x1="'.$lx.'" y1="'.$ly.'" x2="'.($lx + 24).'" y2="'.$ly.'" stroke="#3465a4" stroke-width="2"/>';
		$svg .= '<text x="'.($lx + 30).'" y="'.($ly + 4).'" font-size="11" fill="#333333">DWZ</text>';
		$svg .= '<line x1="'.($lx + 70).'" y1="'.$ly.'" x2="'.($lx + 94).'" y2="'.$ly.'" stroke="#e0821e" stroke-width="2" stroke-dasharray="4 3"/>';
		$svg .= '<text x="'.($lx + 100).'" y="'.($ly + 4).'" font-size="11" fill="#333333">Leistung</text>';
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
