<?php

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['token']          = ['Access key', 'Created during registration and sent by e-mail. It cannot be changed here — a modified value would lock out the recipient without notice.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['vkz']            = ['Club number (VKZ)', 'The key is valid for this single club only. Requests for other clubs are rejected.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['vorname']        = ['First name', 'First name of the applicant taken from the registration'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['nachname']       = ['Last name', 'Last name of the applicant taken from the registration'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['email']          = ['E-mail address', 'The key was sent to this address'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['gesperrt']       = ['Block key', 'The key is kept but requests are rejected with HTTP 403.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['grund']          = ['Reason for blocking', 'For internal documentation only, not disclosed to the caller'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['ip']             = ['IP address of the registration', 'The key was requested from this address. Stored solely to detect bulk requests.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['zugriffe']       = ['Requests', 'Number of recorded requests made with this key'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['letzterZugriff'] = ['Last request', 'Time of the most recent request made with this key'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['published']      = ['Publish key', 'Unpublished keys are treated like blocked ones'];

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['token_legend']   = 'Key and club';
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['person_legend']  = 'Applicant';
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['sperre_legend']  = 'Blocking';
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['publish_legend'] = 'Publication';

/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['new']        = ['New key', 'Create an access key manually'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['edit']       = ['Requests', 'Show the requests of key ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['editheader'] = ['Edit key', 'Edit key ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['delete']     = ['Delete', 'Delete key ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['toggle']     = ['Publish', 'Publish/unpublish key ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['show']       = ['Details', 'Show the details of key ID %s'];
