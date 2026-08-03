<?php

/**
 * Felder
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['token']          = ['Zugangsschlüssel', 'Wird bei der Registrierung erzeugt und per E-Mail verschickt. Er läßt sich hier nicht ändern — ein geänderter Wert würde den Empfänger aussperren, ohne daß er es erfährt.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['vkz']            = ['Vereinskennzahl (VKZ)', 'Der Schlüssel gilt nur für diesen einen Verein. Anfragen zu anderen Vereinen werden abgewiesen.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['vorname']        = ['Vorname', 'Vorname des Antragstellers aus der Registrierung'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['nachname']       = ['Nachname', 'Nachname des Antragstellers aus der Registrierung'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['email']          = ['E-Mail-Adresse', 'An diese Adresse wurde der Schlüssel geschickt'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['gesperrt']       = ['Schlüssel sperren', 'Der Schlüssel bleibt bestehen, Anfragen werden aber mit HTTP 403 abgewiesen.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['grund']          = ['Grund der Sperre', 'Nur zur internen Dokumentation, wird dem Aufrufer nicht mitgeteilt'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['ip']             = ['IP-Adresse der Registrierung', 'Von dieser Adresse aus wurde der Schlüssel angefordert. Wird nur gespeichert, um massenhaftes Anfordern zu erkennen.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['zugriffe']       = ['Zugriffe', 'Anzahl der aufgezeichneten Zugriffe mit diesem Schlüssel'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['letzterZugriff'] = ['Letzter Zugriff', 'Zeitpunkt der letzten Anfrage mit diesem Schlüssel'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['published']      = ['Schlüssel veröffentlichen', 'Nicht veröffentlichte Schlüssel werden wie gesperrte behandelt'];

/**
 * Legenden
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['token_legend']   = 'Schlüssel und Verein';
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['person_legend']  = 'Antragsteller';
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['sperre_legend']  = 'Sperre';
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['publish_legend'] = 'Veröffentlichung';

/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['new']        = ['Neuer Schlüssel', 'Einen Zugangsschlüssel von Hand anlegen'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['edit']       = ['Zugriffe', 'Zugriffe des Schlüssels ID %s anzeigen'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['editheader'] = ['Schlüssel bearbeiten', 'Schlüssel ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['delete']     = ['Löschen', 'Schlüssel ID %s löschen'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['toggle']     = ['Veröffentlichen', 'Schlüssel ID %s veröffentlichen/verbergen'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['show']       = ['Details', 'Details des Schlüssels ID %s anzeigen'];
