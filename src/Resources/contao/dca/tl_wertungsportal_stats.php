<?php

declare(strict_types=1);

use Contao\DataContainer;

/**
 * Tabelle tl_wertungsportal_stats
 *
 * Zählt die Abrufe der Schnittstellenfunktionen je Tag und Quelle.
 * Die Auswertung erfolgt im Backend-Modul „Statistik" (Classes/Statistik.php);
 * diese DCA legt vor allem die Tabelle an. Die Liste ist bewusst
 * schreibgeschützt — die Werte entstehen ausschließlich durch Zählung.
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_stats'] = [
    // Config
    'config' => [
        'dataContainer'    => 'Table',
        'closed'           => true,
        'notCreatable'     => true,
        'notCopyable'      => true,
        'notEditable'      => true,
        'enableAdvancedFilters' => true,
        'sql' => [
            'keys' => [
                'id'                       => 'primary',
                // Ein Datensatz je Tag, Funktion und Quelle — Grundlage für
                // das hochzählende INSERT ... ON DUPLICATE KEY UPDATE
                'datum,funktion,quelle'    => 'unique',
                'datum'                    => 'index',
                'funktion'                 => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['datum DESC', 'funktion'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields'      => ['datum', 'funktion', 'quelle', 'anzahl'],
            'showColumns' => true,
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
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'datum' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_stats']['datum'],
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'filter'  => true,
            'search'  => true,
            'sql'     => "varchar(10) NOT NULL default ''",
        ],
        'funktion' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_stats']['funktion'],
            'sorting' => true,
            'filter'  => true,
            'search'  => true,
            'sql'     => "varchar(64) NOT NULL default ''",
        ],
        'endpunkt' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_stats']['endpunkt'],
            'search' => true,
            'sql'    => "varchar(128) NOT NULL default ''",
        ],
        'quelle' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_stats']['quelle'],
            'filter'    => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_stats']['quellen'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],
        'anzahl' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_stats']['anzahl'],
            'sorting' => true,
            'sql'     => "int(10) unsigned NOT NULL default 0",
        ],
    ],
];
