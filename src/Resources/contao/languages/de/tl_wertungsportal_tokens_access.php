<?php

/**
 * Felder
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['zeitpunkt'] = ['Zeitpunkt', 'Wann die Anfrage einging'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['datum']     = ['Datum', 'Tag der Anfrage (JJJJ-MM-TT)'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['vkz']       = ['Vereinskennzahl (VKZ)', 'Verein, dessen Liste abgefragt wurde'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['ip']        = ['IP-Adresse', 'Adresse des Aufrufers. Wird nur gespeichert, um Mißbrauch erkennen und sperren zu können.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quelle']    = ['Quelle', 'Woher die ausgelieferten Daten kamen'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['status']    = ['HTTP-Status', 'Antwortstatus der Schnittstelle'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['anzahl']    = ['Spieler', 'Anzahl der ausgelieferten Spieler'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['dauer']     = ['Dauer', 'Bearbeitungsdauer in Millisekunden'];

/**
 * Referenzen
 *
 * Die ersten drei Werte sagen, woher die Daten kamen, die übrigen nennen den
 * Grund einer Abweisung.
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quellen'] = [
    'api'        => 'von der Schnittstelle',
    'cache'      => 'aus dem Zwischenspeicher',
    'lokal'      => 'aus dem örtlichen Bestand',
    'fehler'     => 'Fehler bei der Abfrage',
    'unbekannt'  => 'abgewiesen: Schlüssel unbekannt',
    'gesperrt'   => 'abgewiesen: Schlüssel gesperrt',
    'fremd'      => 'abgewiesen: falscher Verein',
    'ipsperre'   => 'abgewiesen: IP gesperrt',
    'limit'      => 'abgewiesen: zu viele Anfragen',
    'parameter'  => 'abgewiesen: fehlerhafte Parameter',
];

/**
 * Buttons und Meldungen
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['show']             = ['Details', 'Details des Zugriffs ID %s anzeigen'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['aufraeumen']       = ['Alte Zugriffe löschen', 'Zugriffe löschen, die älter als 90 Tage sind'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['aufraeumenConfirm'] = 'Sollen wirklich alle Zugriffe gelöscht werden, die älter als 90 Tage sind?';
