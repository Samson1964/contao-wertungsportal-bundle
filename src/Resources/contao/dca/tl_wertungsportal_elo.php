<?php

/*
 * DCA für tl_wertungsportal_elo (Contao 4.13)
 *
 * FIDE-Elo-Daten für die FIDE-Anreicherung der Frontend-Ausgaben.
 * Feldbestand wie die Alttabelle tl_dwz_elo des contao-dewis-bundles
 * (dort ohne Eingabemasken); befüllt über den XML-Import (key=importElo)
 * aus der FIDE-Ratingliste.
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_elo'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'switchToEdit'      => true,
        // Kein Versioning: sehr große Tabelle (komplette FIDE-Ratingliste)
        'enableVersioning'  => false,
        'sql'               => [
            // Bewusst weniger Indizes als tl_dwz_elo — die Anreicherung
            // fragt nur per fideid ab, jeder weitere Index bremst den Import
            'keys' => [
                'id'        => 'primary',
                'fideid'    => 'index',
                'surname'   => 'index',
                'published' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['surname'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'sort,filter;search,limit',
        ],
        'label' => [
            'fields'      => ['fideid', 'surname', 'prename', 'country', 'title', 'rating'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'importElo' => [
                'href'       => 'key=importElo',
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
        'default' => '{person_legend},fideid,surname,prename,intent,country,sex,birthday;{titel_legend},title,w_title,o_title,foa_title;{elo_legend},rating,games,flag;{rapid_legend:hide},rapid_rating,rapid_games,rapid_flag;{blitz_legend:hide},blitz_rating,blitz_games,blitz_flag;{datum_legend:hide},elodate;{publish_legend},published',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Datum der importierten FIDE-Ratingliste (Timestamp des Importlaufs)
        'elodate' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DAY_DESC,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // FIDE-Identifikationsnummer
        'fideid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(16) unsigned NOT NULL default 0",
        ],

        // Nachname
        'surname' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Vorname
        'prename' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Namenszusatz (dritter Namensbestandteil, aus tl_dwz_elo übernommen)
        'intent' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Nation (FIDE-Code)
        'country' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 3, 'tl_class' => 'w50'],
            'sql'       => "varchar(3) NOT NULL default ''",
        ],

        // Geschlecht (M/F)
        'sex' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 1, 'tl_class' => 'w50'],
            'sql'       => "varchar(1) NOT NULL default ''",
        ],

        // FIDE-Titel (GM, IM, ...)
        'title' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 3, 'tl_class' => 'w50'],
            'sql'       => "varchar(3) NOT NULL default ''",
        ],

        // Frauentitel (WGM, WIM, ...)
        'w_title' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 3, 'tl_class' => 'w50'],
            'sql'       => "varchar(3) NOT NULL default ''",
        ],

        // Sonstige Titel
        'o_title' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 3, 'tl_class' => 'w50'],
            'sql'       => "varchar(3) NOT NULL default ''",
        ],

        // FIDE-Online-Arena-Titel
        'foa_title' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 3, 'tl_class' => 'w50'],
            'sql'       => "varchar(3) NOT NULL default ''",
        ],

        // Standard-Elo
        'rating' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DESC,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(4) unsigned NOT NULL default 0",
        ],
        'games' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(4) unsigned NOT NULL default 0",
        ],
        'flag' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // Schnellschach
        'rapid_rating' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(4) unsigned NOT NULL default 0",
        ],
        'rapid_games' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(4) unsigned NOT NULL default 0",
        ],
        'rapid_flag' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // Blitzschach
        'blitz_rating' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(4) unsigned NOT NULL default 0",
        ],
        'blitz_games' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(4) unsigned NOT NULL default 0",
        ],
        'blitz_flag' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],

        // Geburtsjahr (JJJJ)
        'birthday' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "int(8) unsigned NOT NULL default 0",
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
