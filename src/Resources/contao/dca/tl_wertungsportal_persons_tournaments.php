<?php

/*
 * DCA für tl_wertungsportal_persons_tournaments (Contao 4.13)
 *
 * Kindtabelle von tl_wertungsportal_persons (Turnierhistorie/Karteikarte).
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/persons/{id}/history, Array "entries":
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DSBPersonREST.html
 *
 * Aus dem Unter-Array "tournament" wird nur die uuid übernommen
 * (tournamentUuid); die Turnierdaten selbst liegen redundanzfrei in
 * tl_wertungsportal_tournaments. Die Felder des Unter-Arrays "player"
 * werden komplett übernommen.
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_persons_tournaments'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ptable'            => 'tl_wertungsportal_persons',
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'             => 'primary',
                'pid'            => 'index',
                'tournamentUuid' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'                  => DataContainer::MODE_PARENT,
            'fields'                => ['id'],
            'headerFields'          => ['lastname', 'firstname', 'birthyear', 'rating'],
            'disableGrouping'       => true, // Keine Zwischenüberschriften zwischen den Datensätzen
            'panelLayout'           => 'filter;search,limit',
            'child_record_callback' => static function (array $row)
            {
                $label = $row['tournamentUuid'];
                $tournament = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsModel::findByUuid($row['tournamentUuid']);

                if (null !== $tournament) {
                    $label = $tournament->label;
                }

                return '<div class="tl_content_left">' . $label . ' <span class="wp-meta">(DWZ ' . $row['ratingOld'] . ' &rarr; ' . $row['ratingNew'] . ', ' . $row['numberOfGames'] . ' Partien)</span></div>';
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
        'default' => '{tournament_legend},tournamentUuid;{player_legend},playerUuid,nuLigaPersonId,firstname,lastname,birthyear;{club_legend},vkz,memberNo,clubName;{dwz_legend},fideId,playerNo,eloPlayer,ratingOld,indexOld,ratingNew,indexNew,factorK,averageRatingCompetitors,wins,numberOfGames,winsExpected,tournamentPerformance;{publish_legend},published',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_wertungsportal_persons.lastname',
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // UUID des Turniers (Referenz auf tl_wertungsportal_tournaments.uuid)
        'tournamentUuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],

        // Felder aus dem Unter-Array "player"
        'playerUuid' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 36, 'tl_class' => 'w50'],
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
