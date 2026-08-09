<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Tabelle tl_wertungsportal_sperren
 *
 * Ein Datensatz je gebremstem Besucher: wann, von welcher Adresse, mit welcher
 * Browserkennung und — falls angemeldet — unter welcher Mitgliedskennung.
 *
 * DATENSCHUTZ: Die Zeilen enthalten IP-Adresse, Browserkennung und
 * Mitgliedsbezug und damit personenbezogene Daten. Sie werden gebraucht, um
 * Massenabfragen zu erkennen und gezielt sperren zu können. Alte Zeilen
 * gehören regelmäßig gelöscht — dafür gibt es die Operation „Alte Einträge
 * löschen".
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_sperren'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed'        => true,
        'notCreatable'  => true,
        'notCopyable'   => true,
        'notEditable'   => true,
        'sql'           => [
            'keys' => [
                'id'       => 'primary',
                'datum'    => 'index',
                'ip'       => 'index',
                'memberId' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['zeitpunkt DESC'],
            'flag'        => DataContainer::SORT_DESC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields'         => ['zeitpunkt', 'ip', 'grund', 'anzahl', 'memberName'],
            'showColumns'    => true,
            'label_callback' => ['Schachbulle\ContaoWertungsportalBundle\Classes\Sperrenverwaltung', 'zeile'],
        ],
        'global_operations' => [
            'aufraeumen' => [
                'href'       => 'key=sperrenAufraeumen',
                'class'      => 'header_icon',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Wirklich alle Einträge löschen, die älter als 90 Tage sind?\'))return false;"',
            ],
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Diesen Eintrag wirklich löschen?\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'zeitpunkt' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['zeitpunkt'],
            'sorting' => true,
            'flag'   => DataContainer::SORT_DAY_DESC,
            'eval'   => ['rgxp' => 'datim'],
            'sql'    => "int(10) unsigned NOT NULL default 0",
        ],
        'datum' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['datum'],
            'filter' => true,
            'sql'   => "varchar(10) NOT NULL default ''",
        ],
        'ip' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['ip'],
            'search' => true,
            'sql'    => "varchar(64) NOT NULL default ''",
        ],
        'useragent' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['useragent'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'memberId' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['memberId'],
            'search' => true,
            'sql'    => "int(10) unsigned NOT NULL default 0",
        ],
        'memberName' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['memberName'],
            'search' => true,
            'sql'    => "varchar(128) NOT NULL default ''",
        ],
        'grund' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['grund'],
            'filter'    => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['gruende'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'anzahl' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['anzahl'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
        'grenze' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_sperren']['grenze'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
    ],
];
