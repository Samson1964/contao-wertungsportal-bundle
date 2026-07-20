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
        'default' => '{club_legend},clubVkz,clubName,altname;{federation_legend},federation,parentFederation;{image_legend},addImage;{info_legend:hide},info,homepage;{state_legend},state;{publish_legend},published',
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
