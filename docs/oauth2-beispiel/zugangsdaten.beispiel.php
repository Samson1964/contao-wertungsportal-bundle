<?php

declare(strict_types=1);

/**
 * Vorlage für die Zugangsdaten.
 *
 * Kopieren Sie diese Datei nach `zugangsdaten.php` und tragen Sie dort Ihre
 * Angaben ein. Die Beispiele binden `zugangsdaten.php` ein.
 *
 * **Die Kopie gehört nicht in die Versionsverwaltung** und nicht in ein
 * Verzeichnis, das der Webserver ausliefert. Am besten liegt sie außerhalb des
 * öffentlichen Bereichs, und nur der Benutzer, unter dem PHP läuft, darf sie
 * lesen (`chmod 600`).
 *
 * Noch besser als eine Datei sind Umgebungsvariablen — dann steht das Geheimnis
 * nirgends auf der Platte:
 *
 *     return array(
 *         'clientId'     => getenv('DSB_CLIENT_ID'),
 *         'clientSecret' => getenv('DSB_CLIENT_SECRET'),
 *     );
 *
 * Client-ID und Client Secret bekommen Sie über eine API-Freischaltung beim
 * Deutschen Schachbund. Die Client-ID wird erzeugt und Ihnen per E-Mail
 * mitgeteilt; das Client Secret ist das Passwort, das Sie beim Abschluß der
 * Freischaltung selbst festlegen.
 */

return array(

	// ── Turniere und Personen (Scope dsb_tournament) ──────────────────
	'clientId'     => 'hier-die-client-id-eintragen',
	'clientSecret' => 'hier-das-passwort-eintragen',

	// ── DWZ-Liste (Scope dwz_liste) ───────────────────────────────────
	// Eine eigene Freischaltung. Leer lassen, wenn Sie keine haben: Die
	// Endpunkte der DWZ-Liste sind zur Zeit noch ohne Anmeldung erreichbar.
	// Deckt EINE Kennung beide Bereiche ab, tragen Sie sie hier noch einmal
	// ein — die Klasse fordert dann beide Scopes in einer Anfrage an.
	'listeClientId'     => '',
	'listeClientSecret' => '',

	// ── Ablage der Token ──────────────────────────────────────────────
	// Ein Verzeichnis, in das PHP schreiben darf — und zwar über alle Wege
	// hinweg, die die Schnittstelle benutzen (Webserver UND Cronjob). Nicht
	// das Systemverzeichnis für temporäre Dateien nehmen, siehe Klasse.
	'tokenverzeichnis' => __DIR__.'/token',

	// ── Abweichende Adressen ──────────────────────────────────────────
	// Leer lassen. Nur ausfüllen, wenn Sie gegen eine andere Umgebung
	// arbeiten — die Zugangsdaten der Produktivumgebung gelten dort nicht.
	'apiUrl'   => '',
	'tokenUrl' => '',
);
