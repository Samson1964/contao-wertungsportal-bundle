/**
 * Änderungsmeldungen des Wertungsportals (ab 1.54.0): „Foto ändern" auf der
 * Karteikarte und „Logo/Infos ändern" auf der Vereinsseite.
 *
 * Öffnet den Dialog, den ein Link .wp-aenderung-link in data-dialog nennt,
 * füllt ihn aus dessen data-Attributen (signierter Kontext, Betreff, beim
 * Verein Homepage und Vereinsbeschreibung) und schickt das Formular samt
 * Datei per fetch ab. Die Antwort des Controllers ist JSON ({ok, meldung}
 * oder {ok, fehler}) und erscheint im Dialog.
 *
 * Dialoge und Links erzeugt Helper\Aenderung (PHP) — nur für angemeldete
 * Mitglieder. Ohne sie tut dieses Skript nichts.
 *
 * Zwei Besonderheiten gegenüber reklamation.js:
 *
 * 1. Der Vereinsdialog trägt data-nichtmodal und wird NICHT mit showModal()
 *    geöffnet: Der Editor TinyMCE hängt Menüs und Fenster (etwa „Link
 *    einfügen") an das Ende der Seite. Ein modaler Dialog liegt in der
 *    obersten Ebene des Browsers — alles andere bliebe unsichtbar dahinter.
 *    Die Abdunklung macht deshalb ein eigenes Element, Escape ein eigener
 *    Tastenhorcher.
 *
 * 2. „Wird nichts verändert, wird auch nichts abgeschickt": Der Vereinsdialog
 *    vergleicht vor dem Absenden mit dem Stand beim Öffnen. Für die
 *    Vereinsbeschreibung zählt der Stand, den der EDITOR beim Öffnen daraus
 *    gemacht hat — er schreibt HTML um, auch wenn niemand tippt.
 */
(function () {
	'use strict';

	/** Wird einmal geladen und dann von allen Dialogen geteilt. */
	var editorSkript = null;

	/** Gemeinsame Abdunklung der nicht modalen Dialoge. */
	var hintergrund = null;

	/**
	 * Lädt TinyMCE nach, falls es noch nicht da ist.
	 *
	 * @param {string} adresse Adresse von tinymce.min.js
	 * @returns {Promise<object>} Das globale tinymce-Objekt
	 */
	function ladeEditorSkript(adresse) {
		if (window.tinymce) {
			return Promise.resolve(window.tinymce);
		}

		if (!editorSkript) {
			editorSkript = new Promise(function (erfuellt, abgelehnt) {
				var skript = document.createElement('script');

				skript.src = adresse;
				skript.onload = function () {
					if (window.tinymce) {
						erfuellt(window.tinymce);
					} else {
						abgelehnt(new Error('TinyMCE fehlt'));
					}
				};
				skript.onerror = function () {
					abgelehnt(new Error('TinyMCE nicht ladbar'));
				};
				document.head.appendChild(skript);
			});
		}

		return editorSkript;
	}

	/**
	 * Vereinheitlicht Text für den Vergleich „hat sich etwas geändert?".
	 *
	 * @param {string|null} wert Text oder HTML
	 * @returns {string} Ohne Unterschiede in Zeilenenden und Leerraum
	 */
	function normal(wert) {
		return String(wert || '').replace(/\s+/g, ' ').trim();
	}

	/**
	 * Schreibt eine Dateigröße lesbar.
	 *
	 * @param {number} bytes Größe in Bytes
	 * @returns {string} „850 KB" oder „1,2 MB"
	 */
	function groesse(bytes) {
		if (bytes >= 1048576) {
			return String(Math.round(bytes / 104857.6) / 10).replace('.', ',') + ' MB';
		}

		return Math.max(1, Math.round(bytes / 1024)) + ' KB';
	}

	/**
	 * Richtet einen Dialog ein und liefert die Funktion, die ihn für einen
	 * Link öffnet.
	 *
	 * @param {HTMLDialogElement} dialog Dialog aus Helper\Aenderung
	 * @returns {function(HTMLElement): void} Öffnet den Dialog für einen Link
	 */
	function richteEin(dialog) {
		var form = dialog.querySelector('form');
		var felder = form.elements;
		var meldung = dialog.querySelector('.wp-reklamation-meldung');
		var senden = dialog.querySelector('.wp-reklamation-senden');
		var abbrechen = dialog.querySelector('.wp-reklamation-abbrechen');
		var nichtModal = dialog.hasAttribute('data-nichtmodal') || 'function' !== typeof dialog.showModal;
		var pflichtdatei = dialog.hasAttribute('data-pflichtdatei');
		var hoechstens = parseInt(dialog.getAttribute('data-max') || '0', 10);
		var infoFeld = felder.namedItem('info');
		var aufrufer = null;
		var verschickt = false;
		var editor = null;
		var editorStart = null;

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
		 * Sperrt oder entsperrt alle Eingaben (nach dem Absenden bleibt der
		 * Dialog zum Nachlesen stehen).
		 *
		 * @param {boolean} gesperrt true: nichts mehr änderbar
		 */
		function sperre(gesperrt) {
			Array.prototype.forEach.call(form.querySelectorAll('input:not([type="hidden"]), textarea'), function (feld) {
				feld.disabled = gesperrt;
			});

			if (editor && editor.mode && 'function' === typeof editor.mode.set) {
				editor.mode.set(gesperrt ? 'readonly' : 'design');
			}
		}

		/**
		 * Startet den Editor für die Vereinsbeschreibung — beim ersten Öffnen,
		 * danach wird er wiederverwendet. Fehlt TinyMCE, bleibt das Textfeld.
		 *
		 * Die Einstellungen kennen TinyMCE 5 (Contao 4.13) und neuere Fassungen
		 * (Contao 5) gleichermaßen; license_key und promotion ignoriert die
		 * ältere stillschweigend.
		 *
		 * @returns {Promise<object|null>} Der Editor oder null
		 */
		function starteEditor() {
			var adresse = dialog.getAttribute('data-tinymce') || '';

			if (editor || !infoFeld || !adresse) {
				return Promise.resolve(editor);
			}

			return ladeEditorSkript(adresse).then(function (tinymce) {
				var optionen = {
					target: infoFeld,
					base_url: dialog.getAttribute('data-tinymce-basis') || '',
					suffix: '.min',
					license_key: 'gpl',
					menubar: false,
					statusbar: false,
					branding: false,
					promotion: false,
					plugins: 'link lists',
					toolbar: 'bold italic | bullist numlist | link unlink | undo redo',
					height: 240,
					convert_urls: false,
					entity_encoding: 'raw',
					browser_spellcheck: true
				};
				var sprache = dialog.getAttribute('data-tinymce-sprache') || '';

				if (sprache) {
					optionen.language = sprache;
				}

				return tinymce.init(optionen);
			}).then(function (liste) {
				editor = liste && liste[0] ? liste[0] : null;

				return editor;
			}).catch(function () {
				// Ohne Editor geht es mit dem Textfeld weiter
				editor = null;

				return null;
			});
		}

		/**
		 * Liefert den aktuellen Inhalt der Vereinsbeschreibung.
		 *
		 * @returns {string} HTML aus dem Editor oder dem Textfeld
		 */
		function infoInhalt() {
			if (!infoFeld) {
				return '';
			}

			return editor ? editor.getContent() : infoFeld.value;
		}

		/**
		 * Öffnet den Dialog für einen Link.
		 *
		 * @param {HTMLElement} link Der angeklickte Link
		 */
		function oeffne(link) {
			aufrufer = link;
			verschickt = false;
			editorStart = null;

			form.reset();
			felder.namedItem('kontext').value = link.getAttribute('data-kontext') || '';
			felder.namedItem('signatur').value = link.getAttribute('data-signatur') || '';
			felder.namedItem('betreff').value = link.getAttribute('data-betreff') || '';

			if (felder.namedItem('homepage')) {
				felder.namedItem('homepage').value = link.getAttribute('data-homepage') || '';
			}

			if (infoFeld) {
				infoFeld.value = link.getAttribute('data-info') || '';
			}

			sperre(false);
			senden.disabled = false;
			senden.hidden = false;
			abbrechen.textContent = 'Abbrechen';
			zeige('', '');

			if (nichtModal) {
				if (!hintergrund) {
					hintergrund = document.createElement('div');
					hintergrund.className = 'wp-aenderung-hintergrund';
					document.body.appendChild(hintergrund);
				}

				hintergrund.hidden = false;
				document.documentElement.classList.add('wp-aenderung-offen');

				if ('function' === typeof dialog.show) {
					dialog.show();
				} else {
					dialog.setAttribute('open', '');
				}
			} else {
				dialog.showModal();
			}

			felder.namedItem('betreff').focus();

			// Der Editor braucht ein sichtbares Feld — deshalb erst jetzt
			starteEditor().then(function (ed) {
				if (ed) {
					ed.setContent(link.getAttribute('data-info') || '');
					ed.undoManager.clear();
				}

				// Vergleichsstand: was der Editor aus dem Text gemacht hat
				editorStart = infoInhalt();
			});
		}

		/**
		 * Schließt den Dialog und gibt den Fokus an den Link zurück.
		 */
		function schliesse() {
			if ('function' === typeof dialog.close) {
				dialog.close();
			} else {
				dialog.removeAttribute('open');
			}

			if (nichtModal) {
				if (hintergrund) {
					hintergrund.hidden = true;
				}

				document.documentElement.classList.remove('wp-aenderung-offen');

				if (aufrufer) {
					aufrufer.focus();
				}
			}
		}

		// Modal: Der Browser meldet das Schließen (auch per Escape)
		dialog.addEventListener('close', function () {
			if (!nichtModal && aufrufer) {
				aufrufer.focus();
			}
		});

		// Nicht modal: Escape selbst behandeln — aber nicht, solange ein
		// Fenster oder Menü des Editors offen ist, das Escape für sich braucht
		document.addEventListener('keydown', function (ereignis) {
			if (!nichtModal || !dialog.open || 'Escape' !== ereignis.key) {
				return;
			}

			if (document.querySelector('.tox-dialog, .tox-menu, .tox-pop')) {
				return;
			}

			schliesse();
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

			var dateifeld = felder.namedItem('datei');
			var datei = dateifeld && dateifeld.files && dateifeld.files[0] ? dateifeld.files[0] : null;

			if (pflichtdatei && !datei) {
				zeige('Bitte wählen Sie eine Bilddatei aus.', 'fehler');
				dateifeld.focus();

				return;
			}

			if (datei && hoechstens > 0 && datei.size > hoechstens) {
				zeige('Die Datei ist zu groß (' + groesse(datei.size) + ') — höchstens ' + groesse(hoechstens) + '.', 'fehler');
				dateifeld.focus();

				return;
			}

			if (datei && datei.type && !/^image\/(jpeg|png|gif|webp)$/.test(datei.type)) {
				zeige('Bitte wählen Sie ein Bild im Format JPEG, PNG, GIF oder WebP aus.', 'fehler');
				dateifeld.focus();

				return;
			}

			// Vereinsdaten: unverändert → nichts senden. Der Server prüft
			// Homepage, Datei und Nachricht selbst noch einmal; ob die
			// Beschreibung angefasst wurde, kann nur dieses Skript wissen
			if (infoFeld) {
				var angefasst = null !== editorStart && normal(infoInhalt()) !== normal(editorStart);
				var homepage = felder.namedItem('homepage');
				var homepageGleich = normal(homepage.value).replace(/\/+$/, '') === normal(aufrufer ? aufrufer.getAttribute('data-homepage') : '').replace(/\/+$/, '');

				felder.namedItem('info_angefasst').value = angefasst ? '1' : '';

				if (!datei && !angefasst && homepageGleich && !felder.namedItem('nachricht').value.trim()) {
					zeige('Sie haben noch nichts geändert. Bitte ändern Sie Logo, Homepage oder Beschreibung — oder schreiben Sie eine Nachricht.', 'fehler');

					return;
				}

				if (editor) {
					editor.save();
				}
			}

			senden.disabled = true;
			zeige(datei ? 'Wird hochgeladen …' : 'Wird gesendet …', '');

			fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				credentials: 'same-origin',
				headers: {'Accept': 'application/json'}
			}).then(function (antwort) {
				// Nur Antworten des eigenen Controllers tragen „ok" — alles
				// andere kommt von Contao oder dem Webserver (siehe reklamation.js)
				return antwort.json().catch(function () {
					return null;
				}).then(function (daten) {
					if (daten && 'boolean' === typeof daten.ok) {
						return daten;
					}

					var fehler = 'Unerwartete Antwort des Servers (' + antwort.status + '). Bitte versuchen Sie es später noch einmal.';

					if (400 === antwort.status) {
						fehler = 'Die Sitzung ist abgelaufen oder die Datei war zu groß für den Server. Bitte laden Sie die Seite neu und versuchen Sie es noch einmal.';
					} else if (413 === antwort.status) {
						fehler = 'Die Datei ist zu groß für den Server.';
					}

					return {ok: false, fehler: fehler};
				});
			}).then(function (daten) {
				if (daten && daten.ok) {
					verschickt = true;
					sperre(true);
					senden.hidden = true;
					abbrechen.textContent = 'Schließen';
					zeige(daten.meldung || 'Ihre Nachricht wurde verschickt.', 'erfolg');
					abbrechen.focus();
				} else {
					senden.disabled = false;
					zeige((daten && daten.fehler) || 'Die Nachricht konnte nicht verschickt werden.', 'fehler');
				}
			}).catch(function () {
				senden.disabled = false;
				zeige('Keine Verbindung zum Server. Bitte versuchen Sie es später noch einmal.', 'fehler');
			});
		});

		return oeffne;
	}

	/**
	 * Richtet alle Dialoge ein, sobald sie im Dokument stehen, und horcht auf
	 * Klicks auf die Links.
	 */
	function einrichten() {
		var oeffner = {};

		Array.prototype.forEach.call(document.querySelectorAll('dialog.wp-aenderung'), function (dialog) {
			oeffner[dialog.id] = richteEin(dialog);
		});

		document.addEventListener('click', function (ereignis) {
			var link = ereignis.target.closest ? ereignis.target.closest('.wp-aenderung-link') : null;
			var ziel = link ? oeffner[link.getAttribute('data-dialog') || ''] : null;

			if (ziel) {
				ereignis.preventDefault();
				ziel(link);
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', einrichten);
	} else {
		einrichten();
	}
})();
