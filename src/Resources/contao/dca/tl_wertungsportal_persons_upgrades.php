<?php

/*
 * DCA für tl_wertungsportal_persons_upgrades (Contao 4.13)
 *
 * Kindtabelle von tl_wertungsportal_persons (DWZ-Hochstufungen).
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/persons/{id}/history, Array "upgrades":
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DSBPersonREST.html
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_persons_upgrades'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ptable'            => 'tl_wertungsportal_persons',
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'                  => DataContainer::MODE_PARENT,
            'fields'                => ['referenceDate'],
            'headerFields'          => ['lastname', 'firstname', 'birthyear', 'rating'],
            'disableGrouping'       => true, // Keine Zwischenüberschriften zwischen den Datensätzen
            'panelLayout'           => 'filter;search,limit',
            'child_record_callback' => static function (array $row)
            {
                return '<div class="tl_content_left">' . $row['name'] . ' <span class="wp-meta">(' . $row['referenceDate'] . ', DWZ ' . $row['ratingOld'] . ' &rarr; ' . $row['ratingNew'] . ')</span></div>';
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
        'default' => '{upgrade_legend},referenceDate,name;{dwz_legend},ratingOld,indexOld,ratingNew,indexNew;{publish_legend},published',
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

        // Stichtag der Hochstufung
        'referenceDate' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DESC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Bezeichnung der Hochstufung
        'name' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // DWZ und Index vor der Hochstufung
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

        // DWZ und Index nach der Hochstufung
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
