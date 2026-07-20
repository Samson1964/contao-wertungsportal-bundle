<?php

/*
 * DCA für tl_wertungsportal_tournaments_evaluation (Contao 4.13)
 *
 * Kindtabelle von tl_wertungsportal_tournaments (DWZ-Auswertung).
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/tournaments/{uuid}/evaluation, Array "players":
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DSBTournamentREST.html
 *
 * tl_wertungsportal_tournaments_matches referenziert diese Tabelle über
 * whitePlayerUuid/blackPlayerUuid (keine Redundanz).
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_tournaments_evaluation'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ptable'            => 'tl_wertungsportal_tournaments',
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'         => 'primary',
                'pid'        => 'index',
                'playerUuid' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'                  => DataContainer::MODE_PARENT,
            'fields'                => ['playerNo'],
            'headerFields'          => ['label', 'vkz', 'startdate', 'rounds'],
            'disableGrouping'       => true, // Keine Zwischenüberschriften zwischen den Datensätzen
            'panelLayout'           => 'filter;search,limit',
            'child_record_callback' => static function (array $row)
            {
                return '<div class="tl_content_left">' . $row['lastname'] . ', ' . $row['firstname'] . ' <span class="wp-meta">(Nr. ' . $row['playerNo'] . ', DWZ ' . $row['ratingOld'] . ' &rarr; ' . $row['ratingNew'] . ', ' . $row['numberOfGames'] . ' Partien)</span></div>';
            },
        ],
        'global_operations' => [
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
        'default' => '{player_legend},playerUuid,nuLigaPersonId,firstname,lastname,birthyear;{club_legend},vkz,memberNo,clubName;{dwz_legend},fideId,playerNo,eloPlayer,ratingOld,indexOld,ratingNew,indexNew,factorK,averageRatingCompetitors,wins,numberOfGames,winsExpected,tournamentPerformance;{publish_legend},published',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_wertungsportal_tournaments.label',
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Felder aus dem Array "players" (Spieler-DTO)
        'playerUuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],
        'nuLigaPersonId' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'firstname' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'lastname' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'birthyear' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'maxlength' => 4, 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'vkz' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'memberNo' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'clubName' => [
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'fideId' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'playerNo' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'eloPlayer' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50 m12'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'ratingOld' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'indexOld' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'ratingNew' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'indexNew' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'factorK' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "double NOT NULL default 0",
        ],
        'averageRatingCompetitors' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'wins' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "double NOT NULL default 0",
        ],
        'numberOfGames' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
        'winsExpected' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "double NOT NULL default 0",
        ],
        'tournamentPerformance' => [
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
