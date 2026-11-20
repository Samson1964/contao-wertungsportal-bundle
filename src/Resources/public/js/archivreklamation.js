/**
 * Reklamationen zu Einträgen des Wertungsarchivs (ab 2.0.0).
 *
 * Öffnet den Dialog #wp-archivreklamation für einen Knopf
 * .wp-archivreklamation-knopf (je Archivzeile der Karteikarte, dazu
 * „Turnier fehlt?" unter der Tabelle). Der Knopf trägt den signierten
 * Kontext, eine Beschreibung des Eintrags (data-titel) und die bisherigen
 * Meldungen (data-bisher, JSON). Abgeschickt wird per fetch; der Controller
 * schreibt die Meldung in die Logdatei und antwortet mit JSON
 * ({ok, meldung, satz} oder {ok, fehler}).
 *
 * Nach dem Absenden bekommt die Zeile die Marke „reklamiert", und die neue
 * Meldung wandert in data-bisher — ein zweites Öffnen zeigt sie schon.
 *
 * Dialog und Knöpfe erzeugt Wertungsarchiv\Archivreklamation (PHP); ohne
 * Anmeldung und Archivzugriff gibt es beides nicht, und dieses Skript tut
 * nichts. Aussehen wie der Reklamationsdialog (Klassen wp-reklamation).
 */
(function () {
	'use strict';

	/**
	 * Richtet den Dialog ein, sobald er im Dokument steht (er liegt am
	 * Seitenende, das Skript im Kopf).
	 */
	function einrichten() {
		var dialog = document.getElementById('wp-archivreklamation');

		if (!dialog) {
			return;
		}

		var form = dialog.querySelector('form');
		var felder = form.elements;
		var meldung = dialog.querySelector('.wp-reklamation-meldung');
		var senden = dialog.querySelector('.wp-reklamation-senden');
		var abbrechen = dialog.querySelector('.wp-reklamation-abbrechen');
		var titel = dialog.querySelector('[data-feld="titel"]');
		var ueberschrift = dialog.querySelector('[data-feld="ueberschrift"]');
		var bisherFeld = dialog.querySelector('[data-feld="bisher"]');
		var teile = dialog.querySelectorAll('[data-teil]');
		var aufrufer = null;
		var verschickt = false;

		/**
		 * Zeigt eine Meldung im Dialog.
		 *
		 * @param {string} text Meldung
		 * @param {string} art  '', 'fehler' oder 'erfolg'
		 */
		function zeige(text, art) {
			meldung.textContent = text;
			meldung.className = 'wp-reklamation-meldung' + (art ? ' ' + art : '');
		}

		/**
		 * Liest die bisherigen Meldungen eines Knopfs.
		 *
		 * @param {HTMLElement} knopf Knopf
		 * @returns {Array} Liste der Meldungen (datum, gruende, felder, text, eigen, status)
		 */
		function bisherVon(knopf) {
			try {
				var liste = JSON.parse(knopf.getAttribute('data-bisher') || '[]');

				return Array.isArray(liste) ? liste : [];
			} catch (fehler) {
				return [];
			}
		}

		/**
		 * Baut die Liste der bisherigen Meldungen im Dialog — über DOM-Knoten
		 * und textContent, nie über innerHTML (die Texte stammen von Mitgliedern).
		 *
		 * @param {Array} liste Meldungen
		 */
		function zeigeBisher(liste) {
			bisherFeld.textContent = '';
			bisherFeld.hidden = !liste.length;

			if (!liste.length) {
				return;
			}

			var kopf = document.createElement('p');
			kopf.className = 'wp-archivreklamation-bisher-kopf';
			kopf.textContent = 1 === liste.length ? 'Bisher eine Meldung:' : 'Bisher ' + liste.length + ' Meldungen:';
			bisherFeld.appendChild(kopf);

			var ul = document.createElement('ul');

			liste.forEach(function (satz) {
				var li = document.createElement('li');
				var datum = document.createElement('strong');
				datum.textContent = (satz.datum || '') + (satz.eigen ? ' (Ihre Meldung)' : '');
				li.appendChild(datum);

				var teile = [].concat(satz.gruende || []);

				Object.keys(satz.felder || {}).forEach(function (name) {
					teile.push(name + ': ' + satz.felder[name]);
				});

				if (satz.text) {
					teile.push('„' + satz.text + '“');
				}

				li.appendChild(document.createTextNode(' — ' + teile.join('; ')));
				ul.appendChild(li);
			});

			bisherFeld.appendChild(ul);
		}

		/**
		 * Öffnet den Dialog für einen Knopf.
		 *
		 * @param {HTMLElement} knopf Der angeklickte Knopf
		 */
		function oeffne(knopf) {
			var art = knopf.getAttribute('data-art') || 'eintrag';

			aufrufer = knopf;
			verschickt = false;
			form.reset();

			felder.namedItem('kontext').value = knopf.getAttribute('data-kontext') || '';
			felder.namedItem('signatur').value = knopf.getAttribute('data-signatur') || '';
			titel.textContent = knopf.getAttribute('data-titel') || '';
			ueberschrift.textContent = 'fehlt' === art ? 'Fehlendes Turnier melden' : 'Archiveintrag reklamieren';

			// Nur der passende Teil ist sichtbar und wird mitgeschickt
			Array.prototype.forEach.call(teile, function (teil) {
				var aktiv = teil.getAttribute('data-teil') === art;
				teil.hidden = !aktiv;
				teil.disabled = !aktiv;
			});

			zeigeBisher(bisherVon(knopf));

			Array.prototype.forEach.call(form.querySelectorAll('input, textarea'), function (feld) {
				if ('hidden' !== feld.type) {
					feld.readOnly = false;
				}
			});

			senden.disabled = false;
			senden.hidden = false;
			abbrechen.textContent = 'Abbrechen';
			zeige('', '');

			if (typeof dialog.showModal === 'function') {
				dialog.showModal();
			} else {
				dialog.setAttribute('open', '');
			}

			var erstes = dialog.querySelector('[data-teil="' + art + '"] input');

			if (erstes) {
				erstes.focus();
			}
		}

		/**
		 * Schließt den Dialog.
		 */
		function schliesse() {
			if (typeof dialog.close === 'function') {
				dialog.close();
			} else {
				dialog.removeAttribute('open');
			}
		}

		/**
		 * Vermerkt eine erfolgreiche Meldung am Knopf und in seiner Zeile.
		 *
		 * @param {HTMLElement} knopf Knopf
		 * @param {Object}      satz  Anzeige der neuen Meldung vom Server
		 */
		function vermerke(knopf, satz) {
			var liste = bisherVon(knopf);
			liste.push(satz);
			knopf.setAttribute('data-bisher', JSON.stringify(liste));
			knopf.title = 'Erneut reklamieren — bisherige Meldungen ansehen';

			var zeile = knopf.closest('tr');

			if (!zeile) {
				return;
			}

			zeile.classList.add('wp-reklamiert');

			if (!zeile.querySelector('.wp-marke-reklamiert')) {
				var marke = document.createElement('span');
				marke.className = 'wp-marke wp-marke-reklamiert';
				marke.title = 'Reklamiert am ' + (satz.datum || '');
				marke.textContent = 'reklamiert';
				knopf.parentNode.insertBefore(marke, knopf);
			}
		}

		dialog.addEventListener('close', function () {
			if (aufrufer) {
				aufrufer.focus();
			}
		});

		document.addEventListener('click', function (ereignis) {
			var knopf = ereignis.target.closest ? ereignis.target.closest('.wp-archivreklamation-knopf') : null;

			if (knopf) {
				ereignis.preventDefault();
				oeffne(knopf);
			}
		});

		abbrechen.addEventListener('click', schliesse);
		dialog.querySelector('.wp-reklamation-schliessen').addEventListener('click', schliesse);

		form.addEventListener('submit', function (ereignis) {
			ereignis.preventDefault();

			if (verschickt) {
				return;
			}

			senden.disabled = true;
			zeige('Wird gespeichert …', '');

			fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin',
				headers: {'Accept': 'application/json'}
			}).then(function (antwort) {
				// Nur Antworten des Controllers tragen „ok"; sonst hat Contao selbst
				// geantwortet (meist abgelaufene Sitzung, siehe reklamation.js)
				return antwort.json().catch(function () {
					return null;
				}).then(function (daten) {
					if (daten && 'boolean' === typeof daten.ok) {
						return daten;
					}

					return {
						ok: false,
						fehler: 400 === antwort.status
							? 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und versuchen Sie es noch einmal.'
							: 'Unerwartete Antwort des Servers (' + antwort.status + '). Bitte versuchen Sie es später noch einmal.'
					};
				});
			}).then(function (daten) {
				if (daten && daten.ok) {
					verschickt = true;

					if (aufrufer && daten.satz) {
						vermerke(aufrufer, daten.satz);
						zeigeBisher(bisherVon(aufrufer));
					}

					Array.prototype.forEach.call(form.querySelectorAll('input, textarea'), function (feld) {
						if ('hidden' !== feld.type) {
							feld.readOnly = true;
						}
					});

					senden.hidden = true;
					abbrechen.textContent = 'Schließen';
					zeige(daten.meldung || 'Ihre Meldung ist gespeichert.', 'erfolg');
					abbrechen.focus();
				} else {
					senden.disabled = false;
					zeige((daten && daten.fehler) || 'Die Meldung konnte nicht gespeichert werden.', 'fehler');
				}
			}).catch(function () {
				senden.disabled = false;
				zeige('Keine Verbindung zum Server. Bitte versuchen Sie es später noch einmal.', 'fehler');
			});
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', einrichten);
	} else {
		einrichten();
	}
})();
