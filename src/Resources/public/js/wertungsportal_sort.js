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
 *   data-sort="nein"   Spalte ist nicht sortierbar (z. B. Symbol-Spalten)
 *
 * Ohne Angabe entscheidet tablesorter selbst (Text bzw. reine Zahlen).
 */
(function ($) {
	if (typeof $ === 'undefined' || typeof $.tablesorter === 'undefined') {
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

	// Deutsches Datum TT.MM.JJJJ; leere oder unvollständige Werte wandern ans Ende
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

	$(document).ready(function () {
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
				} else if (typ === 'nein') {
					headers[i] = { sorter: false };
				}
			});

			tabelle.tablesorter({ headers: headers });
		});
	});
})(jQuery);
