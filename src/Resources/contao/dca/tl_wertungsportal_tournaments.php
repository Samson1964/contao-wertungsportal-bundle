<?php

/*
 * DCA für tl_wertungsportal_tournaments (Contao 4.13)
 *
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/persons/{id}/history, Unter-Array "entries.tournament":
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DSBPersonREST.html
 * sowie GET /dwz/tournaments/{uuid} (zusätzlich playerCount und matchCount):
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DSBTournamentREST.html
 *
 * Die Turniere werden zentral gespeichert; tl_wertungsportal_persons_tournaments
 * referenziert sie über das Feld tournamentUuid (keine Redundanz).
 * Kindtabellen: tl_wertungsportal_tournaments_evaluation (DWZ-Auswertung)
 * und tl_wertungsportal_tournaments_matches (Partien).
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_tournaments'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ctable'            => [
            'tl_wertungsportal_tournaments_evaluation',
            'tl_wertungsportal_tournaments_matches',
        ],
        'switchToEdit'      => true,
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'        => 'primary',
                'uuid'      => 'index',
                'published' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['startdate DESC'],
            'flag'        => DataContainer::SORT_DESC,
            'panelLayout' => 'sort,filter;search,limit',
        ],
        'label' => [
            'fields'      => ['label', 'vkz', 'startdate', 'enddate', 'ratingState'],
            'showColumns' => true,
        ],
        'global_operations' => [
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
                'href' => 'table=tl_wertungsportal_tournaments_evaluation',
                'icon' => 'bundles/contaowertungsportal/images/rating2.png',
            ],
            'matches' => [
                'href' => 'table=tl_wertungsportal_tournaments_matches',
                'icon' => 'bundles/contaowertungsportal/images/games.png',
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
        'default' => '{tournament_legend},uuid,label,vkz,location,url,rounds,playerCount,matchCount;{date_legend},startdate,enddate,lastCalculated;{state_legend},processingState,ratingState;{referent_legend},referentFirstname,referentLastname,referentEmail;{referent2_legend:hide},additionalReferentFirstname,additionalReferentLastname,additionalReferentEmail;{publish_legend},published',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Eindeutige ID des Turniers (UUID)
        'uuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],

        // Turnierbezeichnung
        'label' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // VKZ des ausrichtenden Vereins/Verbands
        'vkz' => [
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Startdatum
        'startdate' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DESC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Enddatum
        'enddate' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Bearbeitungsstatus (z. B. NEW)
        'processingState' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Auswertungsstatus (z. B. RATED)
        'ratingState' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Auswerter
        'referentFirstname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'referentLastname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'referentEmail' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Zusätzlicher Auswerter
        'additionalReferentFirstname' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'additionalReferentLastname' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'additionalReferentEmail' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Austragungsort
        'location' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Turnier-URL
        'url' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Zeitpunkt der letzten Berechnung
        'lastCalculated' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Anzahl Runden
        'rounds' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // Anzahl Spieler
        'playerCount' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // Anzahl Partien
        'matchCount' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
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
