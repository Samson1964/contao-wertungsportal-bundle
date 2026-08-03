<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Tabelle tl_wertungsportal_tokens_access
 *
 * Ein Datensatz je Zugriff auf die örtliche Vereinslisten-Schnittstelle,
 * Kindtabelle von tl_wertungsportal_tokens. Daraus entstehen sowohl die
 * Einzelheiten am Schlüssel als auch die Gesamtübersicht im Statistik-Modul.
 *
 * DATENSCHUTZ: Die Zeilen enthalten die IP-Adresse des Aufrufers und damit ein
 * personenbezogenes Datum. Sie werden gebraucht, um Missbrauch zu erkennen und
 * gezielt sperren zu können. Alte Zeilen sollten regelmäßig gelöscht werden —
 * dafür gibt es die Operation „Alte Zugriffe löschen".
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_tokens_access'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ptable'            => 'tl_wertungsportal_tokens',
        'closed'            => true,
        'notCreatable'      => true,
        'notCopyable'       => true,
        'notEditable'       => true,
        'sql'               => [
            'keys' => [
                'id'        => 'primary',
                'pid'       => 'index',
                'datum'     => 'index',
                'ip'        => 'index',
                // Für die Auswertung „Zugriffe je Schlüssel und Tag"
                'pid,datum' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'                  => DataContainer::MODE_PARENT,
            'fields'                => ['zeitpunkt DESC'],
            'flag'                  => DataContainer::SORT_DESC,
            'panelLayout'           => 'filter;search,limit',
            'disableGrouping'       => true,
            'headerFields'          => ['vkz', 'vorname', 'nachname', 'email', 'zugriffe'],
            'child_record_callback' => ['Schachbulle\ContaoWertungsportalBundle\Classes\TokenVerwaltung', 'zugriffszeile'],
        ],
        'global_operations' => [
            // Aufräumen: Zugriffe, die älter als 90 Tage sind, entfernen
            'aufraeumen' => [
                'href'       => 'key=aufraeumen',
                'class'      => 'header_delete_all',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['aufraeumenConfirm'] ?? '') . '\'))return false"',
            ],
        ],
        'operations' => [
            'show' => [
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_wertungsportal_tokens.token',
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        'zeitpunkt' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['zeitpunkt'],
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'sql'     => "int(10) unsigned NOT NULL default 0",
        ],

        // Tag als JJJJ-MM-TT, damit sich die Auswertung ohne Datumsrechnung
        // gruppieren lässt (wie in tl_wertungsportal_stats)
        'datum' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['datum'],
            'filter' => true,
            'search' => true,
            'sql'    => "varchar(10) NOT NULL default ''",
        ],

        'vkz' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['vkz'],
            'filter' => true,
            'search' => true,
            'sql'    => "varchar(16) NOT NULL default ''",
        ],

        'ip' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['ip'],
            'filter' => true,
            'search' => true,
            'sql'    => "varchar(64) NOT NULL default ''",
        ],

        // api, cache, lokal oder der Grund der Abweisung
        'quelle' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quelle'],
            'filter'    => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['quellen'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        'status' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['status'],
            'filter' => true,
            'sql'    => "int(10) unsigned NOT NULL default 0",
        ],

        'anzahl' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['anzahl'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],

        'dauer' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens_access']['dauer'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
    ],
];
