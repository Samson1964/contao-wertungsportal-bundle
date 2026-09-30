<?php

/**
 * Labels for table tl_wertungsportal_referenten (fallback)
 *
 * Since 1.52.0 without phone, street, postcode and city: these come from the
 * linked address book entry only.
 */

$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['adresse']   = array('Address book entry', 'Name, postal address, phone numbers and e-mail addresses are taken from this entry on every output, as far as they are marked public there. Without an entry only the name and the office e-mail are shown.');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['nachname']  = array('Last name', 'Last name of the rating officer');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['vorname']   = array('First name', 'First name of the rating officer');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['nuId']      = array('nu ID', 'Person ID in the rating portal, e.g. NU4093214');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['email']     = array('Office e-mail', 'E-mail address of the rating office, e.g. dwz@association.de. It is listed before the addresses of the address book entry and receives the complaints.');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['verbaende'] = array('Responsible for', 'Associations this officer is responsible for. The list is read from the club records.');
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['published'] = array('Publish', 'Only published officers appear in the front end');

$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['person_legend']    = 'Person';
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['kontakt_legend']   = 'Office e-mail';
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['verband_legend']   = 'Responsibility';
$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['published_legend'] = 'Publication';
