<?php

/*
 * DCA für tl_wertungsportal_persons (Contao 4.13)
 *
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/dwzliste/persons (Array "data"):
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html
 *
 * Das Unter-Array "memberships" wird in der Kindtabelle
 * tl_wertungsportal_persons_memberships gespeichert.
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_persons'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ctable'            => [
            'tl_wertungsportal_persons_memberships',
            'tl_wertungsportal_persons_tournaments',
            'tl_wertungsportal_persons_upgrades',
        ],
        'switchToEdit'      => true,
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'             => 'primary',
                'uuid'           => 'index',
                'nuLigaPersonId' => 'index',
                'externeNr'      => 'index',
                'blocked'        => 'index',
                'published'      => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['lastname'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'sort,filter;search,limit',
        ],
        'label' => [
            'fields'      => ['lastname', 'firstname', 'birthyear', 'rating', 'fideId'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'importPersons' => [
                'href'       => 'key=importPersons',
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
            'editheader' => [
                'href' => 'act=edit',
                'icon' => 'header.svg',
            ],
            'edit' => [
                'href' => 'table=tl_wertungsportal_persons_memberships',
                'icon' => 'bundles/contaowertungsportal/images/icon_vereine.png',
            ],
            'tournaments' => [
                'href' => 'table=tl_wertungsportal_persons_tournaments',
                'icon' => 'bundles/contaowertungsportal/images/history.png',
            ],
            'upgrades' => [
                'href' => 'table=tl_wertungsportal_persons_upgrades',
                'icon' => 'bundles/contaowertungsportal/images/chart.png',
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
        '__selector__' => ['addImage', 'blocked'],
        'default' => '{person_legend},anrede,titel,firstname,lastname,geburtsname,birthyear,geburtsort,gender,geschlechtSpielbetrieb,verstorben,verstorbenAm;{dwz_legend},rating,index,weekOfLastTournamentEvaluation;{nation_legend},nation,fideNation;{adresse_legend:hide},strasse,plz,ort,land,email1,email2,telPrivat,telMobil,telGeschaeft,faxPrivat,faxGeschaeft;{id_legend},uuid,nuLigaPersonId,externeNr,fideId;{datenschutz_legend:hide},datenschutzDatum,datenschutzBenutzer,datenschutzInfoDatum,datenschutzInfoBenutzer;{image_legend:hide},addImage;{ban_legend:hide},blocked;{publish_legend},published',
    ],

    // Subpalettes
    'subpalettes' => [
        'addImage' => 'singleSRC',
        'blocked'  => 'grund,melder',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Eindeutige ID der Person (UUID)
        'uuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],

        // nuLiga-Personen-ID
        'nuLigaPersonId' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Vorname
        'firstname' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Nachname
        'lastname' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Geburtsjahr oder vollständiges Geburtsdatum.
        // Die API liefert nur das Jahr (JJJJ); bei Importen oder manueller
        // Pflege kann auch ein komplettes Datum (z. B. TT.MM.JJJJ) stehen.
        'birthyear' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],

        // Geschlecht
        'gender' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['MALE', 'FEMALE'],
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_persons'],
            'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // FIDE-ID
        'fideId' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // Aktuelle DWZ
        'rating' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DESC,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // DWZ-Index
        // Achtung: "index" ist ein reserviertes Wort in MySQL, daher hier
        // bewusst ohne sorting/filter (unquotierte ORDER BY/WHERE-Klauseln)!
        'index' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // Woche der letzten Turnierauswertung (Format JJJJWW)
        'weekOfLastTournamentEvaluation' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // ─────────────────────────────────────────────
        // Felder aus dem Vereinsmitglieder-CSV-Import
        // ─────────────────────────────────────────────

        // Externe Nummer (entspricht größtenteils der alten dewisID aus tl_dwz_spi)
        'externeNr' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Anrede
        'anrede' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Akademischer Titel
        'titel' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Geburtsname
        'geburtsname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Geburtsort
        'geburtsort' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Verstorben
        'verstorben' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        // Verstorben am (TT.MM.JJJJ)
        'verstorbenAm' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],

        // Nation (IOC-Code)
        'nation' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // FIDE-Nation (IOC-Code)
        'fideNation' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // Geschlecht im Spielbetrieb (m/w)
        'geschlechtSpielbetrieb' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // Adresse
        'strasse' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'plz' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'ort' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'land' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'email1' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'email2' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'telPrivat' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'telMobil' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'telGeschaeft' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'faxPrivat' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'faxGeschaeft' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Datenschutz
        'datenschutzDatum' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'datenschutzBenutzer' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'datenschutzInfoDatum' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'datenschutzInfoBenutzer' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // ─────────────────────────────────────────────
        // Spielerbild (aus tl_dwz_spi übernommen)
        // ─────────────────────────────────────────────

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

        // ─────────────────────────────────────────────
        // Blacklist (analog tl_dwz_spi: blocked/grund/melder)
        // ─────────────────────────────────────────────

        'blocked' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'grund' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['rte' => 'tinyMCE'],
            'explanation' => 'insertTags',
            'sql'       => 'mediumtext NULL',
        ],
        'melder' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 40, 'tl_class' => 'long'],
            'sql'       => "varchar(40) NOT NULL default ''",
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
