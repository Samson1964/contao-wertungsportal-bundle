<?php

/*
 * DCA für tl_wertungsportal_clubs (Contao 4.13)
 *
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/dwzliste/clubs (Array "data"):
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_clubs'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'        => 'primary',
                'clubVkz'   => 'index',
                'published' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['clubVkz'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'sort,filter;search,limit',
        ],
        'label' => [
            'fields'      => ['clubVkz', 'clubName', 'federation', 'parentFederation'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'importClubs' => [
                'href'       => 'key=importClubs',
                'class'      => 'header_theme_import',
                'attributes' => 'onclick="Backend.getScrollOffset()"',
            ],
            'importDwzVer' => [
                'href'       => 'key=importDwzVer',
                'class'      => 'header_theme_import',
                'attributes' => 'onclick="Backend.getScrollOffset()"',
            ],
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'copy' => [
                'href' => 'act=copy',
                'icon' => 'copy.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle' => [
                'href'         => 'act=toggle&amp;field=published',
                'icon'         => 'visible.svg',
                'showInHeader' => true,
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        '__selector__' => ['addImage'],
        'default' => '{club_legend},clubVkz,clubName,altname,kurzname,druckname;{federation_legend},federation,parentFederation,verbandName,regionName;{stammdaten_legend:hide},debitorNr,lsbNr,vereinsregisterNr,gruendungsjahr,eintrittsdatum,austrittsdatum,zahlungsart,istVerband,istReinerSchachverein,istMehrspartenverein,istFreizeitverein,istSpielgemeinschaft,istVereinOhneSpielbetrieb,personalisierteAnmeldung,istMitgliedsverein,istVeranstalter;{bank_legend:hide},bic,bank,iban,kontoinhaber;{adresse_legend:hide},adresseName,adresseStrasse,adressePlz,adresseOrt,adresseEmail1,adresseEmail2,adresseHomepage,adresseTelefonPrivat,adresseTelefonGeschaeft,adresseTelefonMobil,adresseFaxPrivat,adresseFaxGeschaeft;{sportstaette1_legend:hide},sportstaette1Name,sportstaette1Strasse,sportstaette1Plz,sportstaette1Ort,sportstaette1Email,sportstaette1Homepage,sportstaette1Telefon,sportstaette1Fax;{sportstaette2_legend:hide},sportstaette2Name,sportstaette2Strasse,sportstaette2Plz,sportstaette2Ort,sportstaette2Email,sportstaette2Homepage,sportstaette2Telefon,sportstaette2Fax;{sportstaette3_legend:hide},sportstaette3Name,sportstaette3Strasse,sportstaette3Plz,sportstaette3Ort,sportstaette3Email,sportstaette3Homepage,sportstaette3Telefon,sportstaette3Fax;{bemerkung_legend:hide},bemerkung;{image_legend},addImage;{info_legend:hide},info,homepage;{state_legend},state;{publish_legend},published',
    ],

    // Subpalettes
    'subpalettes' => [
        'addImage' => 'singleSRC',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Vereinskennziffer (VKZ) des Vereins
        'clubVkz' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Name des Vereins
        'clubName' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // VKZ des Verbands, dem der Verein angehört
        'federation' => [
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // VKZ des übergeordneten Verbands
        'parentFederation' => [
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Alternativer Vereinsname für die Anzeige (aus tl_dwz_ver übernommen)
        'altname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Vereinslogo (aus tl_dwz_ver übernommen)
        'addImage' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'singleSRC' => [
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
            'sql'       => 'binary(16) NULL',
        ],

        // Über den Verein (aus tl_dwz_ver übernommen)
        'info' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['rte' => 'tinyMCE'],
            'explanation' => 'insertTags',
            'sql'       => 'text NULL',
        ],

        // Homepage des Vereins (aus tl_dwz_ver übernommen)
        'homepage' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'long clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // ─────────────────────────────────────────────
        // Felder aus dem Vereins-Stammdaten-CSV-Import
        // (Vereine__Stammdaten__Adressen__Sportstaetten__JJJJMMTTHHIISS.csv)
        // ─────────────────────────────────────────────

        // Kurzname des Vereins
        'kurzname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Druckname des Vereins
        'druckname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Name des Verbands (aus dem CSV, im Gegensatz zur VKZ in federation)
        'verbandName' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Name der Region
        'regionName' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Debitorennummer
        'debitorNr' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Landessportbund-Nummer
        'lsbNr' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Vereinsregisternummer
        'vereinsregisterNr' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Gründungsjahr (JJJJ) oder vollständiges Gründungsdatum (TT.MM.JJJJ)
        'gruendungsjahr' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],

        // Eintritts-/Austrittsdatum (TT.MM.JJJJ)
        'eintrittsdatum' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
        'austrittsdatum' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],

        // Zahlungsart (Rechnung/Lasteinzug)
        'zahlungsart' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Kennzeichen aus dem CSV (ja/nein)
        'istVerband' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50 clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istReinerSchachverein' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istMehrspartenverein' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istFreizeitverein' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istSpielgemeinschaft' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istVereinOhneSpielbetrieb' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'personalisierteAnmeldung' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istMitgliedsverein' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'istVeranstalter' => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        // Bankverbindung
        'bic' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'bank' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'iban' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 34, 'tl_class' => 'w50'],
            'sql'       => "varchar(34) NOT NULL default ''",
        ],
        'kontoinhaber' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Vereinsadresse
        'adresseName' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'adresseStrasse' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'adressePlz' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'adresseOrt' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'adresseEmail1' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'adresseEmail2' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'adresseHomepage' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'adresseTelefonPrivat' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'adresseTelefonGeschaeft' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'adresseTelefonMobil' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'adresseFaxPrivat' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'adresseFaxGeschaeft' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Sportstätten 1-3
        'sportstaette1Name' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'sportstaette1Strasse' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'sportstaette1Plz' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'sportstaette1Ort' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette1Email' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette1Homepage' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 160, 'tl_class' => 'w50'],
            'sql'       => "varchar(160) NOT NULL default ''",
        ],
        'sportstaette1Telefon' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette1Fax' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette2Name' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'sportstaette2Strasse' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'sportstaette2Plz' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'sportstaette2Ort' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette2Email' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette2Homepage' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 160, 'tl_class' => 'w50'],
            'sql'       => "varchar(160) NOT NULL default ''",
        ],
        'sportstaette2Telefon' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette2Fax' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette3Name' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'sportstaette3Strasse' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'sportstaette3Plz' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'sportstaette3Ort' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette3Email' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette3Homepage' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 160, 'tl_class' => 'w50'],
            'sql'       => "varchar(160) NOT NULL default ''",
        ],
        'sportstaette3Telefon' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'sportstaette3Fax' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Bemerkung
        'bemerkung' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['style' => 'height:60px'],
            'sql'       => 'text NULL',
        ],

        // Status des Vereins (Löschkennzeichen)
        'state' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['DELETE_STATE_FALSE', 'DELETE_STATE_TRUE'],
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_clubs'],
            'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        'published' => [
            'exclude'   => true,
            'filter'    => true,
            'toggle'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true, 'tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
    ],
];
