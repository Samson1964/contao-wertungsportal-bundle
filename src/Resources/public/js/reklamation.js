/**
 * Reklamationsformular des Wertungsportals (ab 1.51.0).
 *
 * Öffnet den Dialog #wp-reklamation, wenn ein Link .wp-reklamation-link
 * angeklickt wird, füllt Betreff, Text, Empfänger und den signierten Kontext
 * aus den data-Attributen des Links und schickt das Formular per fetch ab.
 * Die Antwort des Controllers ist JSON ({ok, meldung} oder {ok, fehler}) und
 * erscheint im Dialog selbst — die Seite wird nicht verlassen. Antworten
 * ohne „ok" kommen von Contao selbst (etwa ein abgewiesenes Anfragetoken)
 * und werden nach dem HTTP-Status gedeutet.
 *
 * Dialog und Links erzeugt Helper\Reklamation (PHP). Ohne Anmeldung gibt es
 * beides nicht, und dieses Skript tut nichts.
 *
 * Ohne Bibliothek: Das Bundle läuft unter Contao 4.13 und 5 mit beliebigen
 * Themes; ein natives <dialog> bringt Abdunklung, Fokusfang und Escape mit.
 */
(function () {
	'use strict';

	/**
	 * Richtet den Dialog ein, sobald er im Dokument steht.
	 *
	 * Das Skript liegt im Kopf der Seite, der Dialog am Ende — deshalb erst
	 * nach DOMContentLoaded.
	 */
	function einrichten() {
		var dialog = document.getElementById('wp-reklamation');

		if (!dialog) {
			return;
		}

		var form = dialog.querySelector('form');
		var felder = form.elements;
		var meldung = dialog.querySelector('.wp-reklamation-meldung');
		var senden = dialog.querySelector('.wp-reklamation-senden');
		var abbrechen = dialog.querySelector('.wp-reklamation-abbrechen');
		var empfaenger = dialog.querySelector('[data-feld="empfaenger"]');
		var kopie = dialog.querySelector('[data-feld="kopie"]');
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
		 * Öffnet den Dialog für einen Link und setzt die Schreibmarke unter die
		 * Zeile „Was stimmt nicht?".
		 *
		 * @param {HTMLElement} link Der angeklickte Link
		 */
		function oeffne(link) {
			aufrufer = link;
			verschickt = false;

			felder.namedItem('kontext').value = link.getAttribute('data-kontext') || '';
			felder.namedItem('signatur').value = link.getAttribute('data-signatur') || '';
			felder.namedItem('betreff').value = link.getAttribute('data-betreff') || '';
			felder.namedItem('text').value = link.getAttribute('data-text') || '';
			empfaenger.textContent = link.getAttribute('data-empfaenger') || '';

			// Geht die Reklamation direkt an den Admin, gibt es keine Blindkopie
			if (kopie) {
				kopie.hidden = '0' === link.getAttribute('data-kopie');
			}

			felder.namedItem('betreff').disabled = false;
			felder.namedItem('text').disabled = false;
			senden.disabled = false;
			senden.hidden = false;
			abbrechen.textContent = 'Abbrechen';
			zeige('', '');

			if (typeof dialog.showModal === 'function') {
				dialog.showModal();
			} else {
				dialog.setAttribute('open', '');
			}

			var text = felder.namedItem('text');
			var marke = parseInt(link.getAttribute('data-cursor') || '0', 10);

			text.focus();

			if (marke > 0 && typeof text.setSelectionRange === 'function') {
				text.setSelectionRange(marke, marke);
			}
		}

		/**
		 * Schließt den Dialog und gibt den Fokus an den Link zurück.
		 */
		function schliesse() {
			if (typeof dialog.close === 'function') {
				dialog.close();
			} else {
				dialog.removeAttribute('open');
			}
		}

		dialog.addEventListener('close', function () {
			if (aufrufer) {
				aufrufer.focus();
			}
		});

		// Ein Klick-Beobachter für alle Links der Seite, auch nachgeladene
		document.addEventListener('click', function (ereignis) {
			var link = ereignis.target.closest ? ereignis.target.closest('.wp-reklamation-link') : null;

			if (link) {
				ereignis.preventDefault();
				oeffne(link);
			}
		});

		abbrechen.addEventListener('click', schliesse);
		dialog.querySelector('.wp-reklamation-schliessen').addEventListener('click', schliesse);

		form.addEventListener('submit', function (ereignis) {
			ereignis.preventDefault();

			if (verschickt) {
				return;
			}

			if (!felder.namedItem('betreff').value.trim()) {
				zeige('Bitte geben Sie einen Betreff an.', 'fehler');
				felder.namedItem('betreff').focus();

				return;
			}

			senden.disabled = true;
			zeige('Wird gesendet …', '');

			fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin',
				headers: {'Accept': 'application/json'}
			}).then(function (antwort) {
				// Nur Antworten des eigenen Controllers tragen „ok". Weist Contao
				// das Anfragetoken ab, antwortet es selbst mit 400 — in 4.13 als
				// HTML-Seite, in 5.7 wegen „Accept: JSON" als Symfony-
				// Fehlerbeschreibung {type, title, status, detail}. Meist ist
				// dann die Sitzung abgelaufen
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
					felder.namedItem('betreff').disabled = true;
					felder.namedItem('text').disabled = true;
					senden.hidden = true;
					abbrechen.textContent = 'Schließen';
					zeige(daten.meldung || 'Ihre Reklamation wurde verschickt.', 'erfolg');
					abbrechen.focus();
				} else {
					senden.disabled = false;
					zeige((daten && daten.fehler) || 'Die Reklamation konnte nicht verschickt werden.', 'fehler');
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
