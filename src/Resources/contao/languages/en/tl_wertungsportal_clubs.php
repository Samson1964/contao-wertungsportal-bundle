<?php

/*
 * English language file for tl_wertungsportal_clubs (Contao 4.13)
 */

// Legends
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['club_legend']         = 'Club';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['federation_legend']   = 'Federation';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['stammdaten_legend']   = 'Master data';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['bank_legend']         = 'Bank account';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresse_legend']      = 'Address & contact';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette1_legend'] = 'Venue 1';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette2_legend'] = 'Venue 2';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette3_legend'] = 'Venue 3';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['bemerkung_legend']    = 'Remark';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['image_legend']        = 'Club logo';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['info_legend']         = 'About the club';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['state_legend']        = 'State';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['publish_legend']      = 'Publishing';

// Fields
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['clubVkz']          = ['Club number (VKZ)', 'Please enter the club number (Vereinskennziffer).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['clubName']         = ['Club name', 'Please enter the name of the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['altname']          = ['Alternative club name', 'Shown on the website instead of the official club name.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['federation']       = ['Federation (VKZ)', 'Number of the federation the club belongs to.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['parentFederation'] = ['Parent federation (VKZ)', 'Number of the parent federation.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['addImage']         = ['Show club logo', 'Assign a logo to the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['singleSRC']        = ['Logo file', 'Please select the logo file.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['info']             = ['About the club', 'Short portrait of the club for the club list.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['homepage']         = ['Homepage', 'Website address of the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['state']            = ['State', 'Deletion state of the club (archive status of the master data import).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['published']        = ['Publish', 'Show the club on the website.'];

// Fields from the club master data CSV import
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['kurzname']                  = ['Short name', 'Short name of the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['druckname']                 = ['Print name', 'Print name of the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['verbandName']               = ['Federation name', 'Name of the federation (from the master data import).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['regionName']                = ['Region', 'Name of the region (from the master data import).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['debitorNr']                 = ['Debtor number', 'Debtor number of the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['lsbNr']                     = ['LSB number', 'Number at the regional sports association.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['vereinsregisterNr']         = ['Register number', 'Number in the register of associations.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['gruendungsjahr']            = ['Founding year/date', 'Founding year (YYYY) or complete founding date (DD.MM.YYYY).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['eintrittsdatum']            = ['Entry date', 'Entry date of the club (DD.MM.YYYY).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['austrittsdatum']            = ['Exit date', 'Exit date of the club (DD.MM.YYYY).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['zahlungsart']               = ['Payment method', 'Payment method of the club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istVerband']                = ['Federation/district', 'The entry is a federation, district or county (not a club).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istReinerSchachverein']     = ['Chess-only club', 'The club is a chess-only club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istMehrspartenverein']      = ['Multi-sport club', 'The club is a multi-sport club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istFreizeitverein']         = ['Leisure club', 'The club is a leisure club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istSpielgemeinschaft']      = ['Playing community', 'The club is a playing community.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istVereinOhneSpielbetrieb'] = ['No competitive play', 'Club without competitive play.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['personalisierteAnmeldung']  = ['Personalised registration', 'The club uses personalised registration.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istMitgliedsverein']        = ['Member club', 'The club is a member club.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['istVeranstalter']           = ['Organiser', 'The club is an organiser.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['bic']                       = ['BIC', 'BIC of the bank account.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['bank']                      = ['Bank', 'Name of the bank.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['iban']                      = ['IBAN', 'IBAN of the bank account.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['kontoinhaber']              = ['Account holder', 'Holder of the account.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseName']               = ['Contact person', 'Name of the contact person.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseStrasse']            = ['Street', 'Street and house number.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adressePlz']                = ['Postal code', 'Postal code.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseOrt']                = ['City', 'City.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseEmail1']             = ['E-mail 1', 'First e-mail address.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseEmail2']             = ['E-mail 2', 'Second e-mail address.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseHomepage']           = ['Homepage (import)', 'Homepage from the master data import (independent of the display field homepage).'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseTelefonPrivat']      = ['Phone (private)', 'Private phone number.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseTelefonGeschaeft']   = ['Phone (business)', 'Business phone number.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseTelefonMobil']       = ['Phone (mobile)', 'Mobile phone number.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseFaxPrivat']          = ['Fax (private)', 'Private fax number.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['adresseFaxGeschaeft']       = ['Fax (business)', 'Business fax number.'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['bemerkung']                 = ['Remark', 'Remark about the club.'];

// Venues 1-3
foreach(array(1, 2, 3) as $wpNr)
{
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Name']     = ['Name', 'Name of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Strasse']  = ['Street', 'Street and house number of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Plz']      = ['Postal code', 'Postal code of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Ort']      = ['City', 'City of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Email']    = ['E-mail', 'E-mail address of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Homepage'] = ['Homepage', 'Homepage of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Telefon']  = ['Phone', 'Phone number of venue '.$wpNr.'.'];
	$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['sportstaette'.$wpNr.'Fax']      = ['Fax', 'Fax number of venue '.$wpNr.'.'];
}
unset($wpNr);

// References (options of the "state" field)
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['DELETE_STATE_FALSE'] = 'Active (not deleted)';
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['DELETE_STATE_TRUE']  = 'Deleted';

// Buttons
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['importClubs']  = ['CSV import', 'Import clubs from the master data export file (Vereine__Stammdaten__Adressen__Sportstaetten)'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['importDwzVer'] = ['Import legacy data', 'Import logo, homepage, info and alternative name from the DWZ clubs (tl_dwz_ver)'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['new']    = ['New club', 'Create a new club'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['edit']   = ['Edit club', 'Edit club ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['copy']   = ['Duplicate club', 'Duplicate club ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['delete'] = ['Delete club', 'Delete club ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['toggle'] = ['Publish/unpublish club', 'Publish/unpublish club ID %s'];
$GLOBALS['TL_LANG']['tl_wertungsportal_clubs']['show']   = ['Show club details', 'Show details of club ID %s'];
