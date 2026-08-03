<?php

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['zeitpunkt'] = ['Time', 'When the request came in'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['datum']     = ['Date', 'Day of the request (YYYY-MM-DD)'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['vkz']       = ['Club number (VKZ)', 'Club whose list has been requested'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['ip']        = ['IP address', 'Address of the caller. Stored solely to detect and block abuse.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quelle']    = ['Source', 'Where the delivered data came from'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['status']    = ['HTTP status', 'Response status of the interface'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['anzahl']    = ['Players', 'Number of players delivered'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['dauer']     = ['Duration', 'Processing time in milliseconds'];

/**
 * References
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quellen'] = [
    'api'       => 'from the interface',
    'cache'     => 'from the cache',
    'lokal'     => 'from the local data',
    'fehler'    => 'request failed',
    'unbekannt' => 'rejected: unknown key',
    'gesperrt'  => 'rejected: key blocked',
    'fremd'     => 'rejected: wrong club',
    'ipsperre'  => 'rejected: IP blocked',
    'limit'     => 'rejected: too many requests',
    'parameter' => 'rejected: invalid parameters',
];

/**
 * Buttons and messages
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['show']              = ['Details', 'Show the details of request ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['aufraeumen']        = ['Delete old requests', 'Delete requests older than 90 days'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['aufraeumenConfirm'] = 'Do you really want to delete all requests older than 90 days?';
