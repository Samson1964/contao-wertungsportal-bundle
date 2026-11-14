<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Tabelle tl_wertungsportal_altcodes (ab 1.58.0)
 *
 * Zuordnung der Turniere aus DeWIS (alter Turniercode, z. B. „C619-C05-WAM")
 * zu ihrer UUID im Wertungsportal von nu. Gefüllt wird sie einmalig mit
 * `wertungsportal:turniercodes <datei>` aus dem Export von portal64
 * (tournamentmaster, 66.685 Turniere, Juli 2026); danach ist sie der
 * dauerhafte Bestand.
 *
 * Gebraucht wird sie für
 * - die Weiterleitung alter Adressen (`turniere/<code>.html`) auf die neue,
 * - „Erste Berechnung am" (computedOn) — nu liefert dieses Datum nicht,
 * - die Anzeige des alten Codes im Kopf der Turnierseiten.
 *
 * Einen Code können mehrere Turniere tragen (74 Codes, 157 Turniere). Die
 * Weiterleitung bietet dann eine Auswahl an; im Backend lassen sich solche
 * Zeilen von Hand berichtigen.
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_altcodes'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'notCopyable'   => true,
        'sql'           => [
            'keys' => [
                'id'    => 'primary',
                'uuid'  => 'unique',
                'tcode' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['finishedOn DESC'],
            'flag'        => DataContainer::SORT_DESC,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields'      => ['tcode', 'tname', 'vkz', 'finishedOn', 'computedOn'],
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
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Diese Zuordnung wirklich löschen?\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    'palettes' => [
        'default' => '{code_legend},tcode,uuid;{turnier_legend},tname,organisation,vkz,acron,rounds;{datum_legend},finishedOn,computedOn,recomputedOn',
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        // Alter Turniercode aus DeWIS. Fast immer zwölf Zeichen, vereinzelt
        // mit Umlauten oder Sonderzeichen („B437-700-DÄH", „B341-K00-G!6")
        'tcode' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'uuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'unique' => true, 'maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],
        'tname' => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'long'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'organisation' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'vkz' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 8, 'tl_class' => 'w50'],
            'sql'       => "varchar(8) NOT NULL default ''",
        ],
        'acron' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'rounds' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'natural', 'maxlength' => 5, 'tl_class' => 'w50'],
            'sql'       => "smallint(5) unsigned NOT NULL default '0'",
        ],
        // Datumswerte wie im Export als JJJJ-MM-TT, leer = unbekannt
        'finishedOn' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_DESC,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
        'computedOn' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
        'recomputedOn' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],
    ],
];
