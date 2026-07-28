/**
 * Spaltensortierung der Wertungsportal-Tabellen
 *
 * Initialisiert jede Tabelle mit der Klasse "tablesorter" automatisch.
 * Die Behandlung einer Spalte wird im Spaltenkopf über data-sort gesteuert:
 *
 *   data-sort="zahl"   Erste Zahl im Text zählt (z. B. "1234 -  45" => 1234,
 *                      "3,5 / 7" => 3.5, "+12" => 12) — für DWZ, Ergebnis,
 *                      Erwartungswert, Leistung und Differenzen
 *   data-sort="datum"  Deutsches Datum TT.MM.JJJJ
 *   data-sort="woche"  Kalenderwoche der letzten Auswertung als WW/JJJJ
 *   data-sort="titel"  FIDE-Titel nach Wertigkeit (GM vor IM vor WGM …)
 *   data-sort="nein"   Spalte ist nicht sortierbar (z. B. Symbol-Spalten)
 *
 * Ohne Angabe entscheidet tablesorter selbst (Text bzw. reine Zahlen).
 *
 * WICHTIG: Parser-Registrierung UND Initialisierung laufen komplett in
 * $(document).ready(). Contao bringt unter assets/tablesorter eine eigene
 * tablesorter-Fassung mit, die viele Layouts laden — je nach Reihenfolge
 * überschreibt sie jQuery.tablesorter nachträglich. Wer seine Parser schon
 * beim Laden dieser Datei registriert, verliert sie dabei (im Livesystem
 * genau so beobachtet: keine Parser, keine Initialisierung, keine Pfeile).
 */
(function ($) {
	if (typeof $ === 'undefined') {
		return;
	}

	$(document).ready(function () {
		if (typeof $.tablesorter === 'undefined') {
			return;
		}

		// Erste Zahl aus dem Zelleninhalt lesen (Vorzeichen und Dezimalkomma
		// werden berücksichtigt, geschützte Leerzeichen entfernt)
		$.tablesorter.addParser({
			id: 'wpzahl',
			is: function () {
				return false;
			},
			format: function (s) {
				var t = String(s).replace(/ /g, ' ').replace(/,/g, '.');
				var m = t.match(/-?\d+(\.\d+)?/);
				return m ? parseFloat(m[0]) : -Number.MAX_VALUE;
			},
			type: 'numeric'
		});

		// Deutsches Datum TT.MM.JJJJ; leere oder unvollständige Werte ans Ende
		$.tablesorter.addParser({
			id: 'wpdatum',
			is: function () {
				return false;
			},
			format: function (s) {
				var a = String(s).replace(/ /g, ' ').trim().split('.');
				if (a.length < 3) {
					return 0;
				}
				var d = new Date(parseInt(a[2], 10), parseInt(a[1], 10) - 1, parseInt(a[0], 10));
				return isNaN(d.getTime()) ? 0 : d.getTime();
			},
			type: 'numeric'
		});

		// Letzte Auswertung als Kalenderwoche "WW/JJJJ" => JJJJWW
		$.tablesorter.addParser({
			id: 'wpwoche',
			is: function () {
				return false;
			},
			format: function (s) {
				var m = String(s).match(/(\d{1,2})\s*\/\s*(\d{4})/);
				return m ? parseInt(m[2], 10) * 100 + parseInt(m[1], 10) : 0;
			},
			type: 'numeric'
		});

		// FIDE-Titel nach Wertigkeit statt alphabetisch
		$.tablesorter.addParser({
			id: 'wptitel',
			is: function () {
				return false;
			},
			format: function (s) {
				var rang = { GM: 9, IM: 8, WGM: 7, FM: 6, WIM: 5, CM: 4, WFM: 3, WCM: 2 };
				return rang[String(s).trim().toUpperCase()] || 0;
			},
			type: 'numeric'
		});

		$('table.tablesorter').each(function () {
			var tabelle = $(this);

			// Ohne Kopf- oder Datenzeilen bricht tablesorter ab
			if (!tabelle.find('thead th').length || !tabelle.find('tbody tr').length) {
				return;
			}

			var headers = {};

			tabelle.find('thead tr').first().find('th').each(function (i) {
				var typ = $(this).attr('data-sort');

				if (typ === 'zahl') {
					headers[i] = { sorter: 'wpzahl' };
				} else if (typ === 'datum') {
					headers[i] = { sorter: 'wpdatum' };
				} else if (typ === 'woche') {
					headers[i] = { sorter: 'wpwoche' };
				} else if (typ === 'titel') {
					headers[i] = { sorter: 'wptitel' };
				} else if (typ === 'nein') {
					headers[i] = { sorter: false };
				}
			});

			try {
				tabelle.tablesorter({ headers: headers });
			} catch (e) {
				// Eine fremde tablesorter-Fassung soll die Seite nicht lahmlegen
				if (window.console) {
					console.warn('Wertungsportal: Tabelle nicht sortierbar', e);
				}
			}
		});
	});
})(jQuery);
