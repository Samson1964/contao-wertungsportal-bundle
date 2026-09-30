<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Tabelle tl_wertungsportal_reklamationen
 *
 * Eine Zeile je Reklamation aus dem Frontend (Helper\Reklamation). Angelegt
 * wird sie nur dort; im Backend lassen sich lediglich „erledigt" setzen und
 * eine Notiz ergänzen. Alles andere ist die Aufzeichnung dessen, was
 * verschickt wurde — die soll niemand nachträglich ändern. Die Operation
 * „Details" zeigt sie vollständig.
 *
 * DATENSCHUTZ: Die Zeilen enthalten Namen und E-Mail-Adressen von Mitgliedern
 * und Referenten. Erledigte Reklamationen sollten nicht unbegrenzt liegen
 * bleiben — löschen geht einzeln oder über „Mehrere bearbeiten".
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_reklamationen'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed'        => true,
        'notCreatable'  => true,
        'notCopyable'   => true,
        'sql'           => [
            'keys' => [
                'id'       => 'primary',
                'datum'    => 'index',
                'memberId' => 'index',
                'erledigt' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['datum DESC'],
            'flag'        => DataContainer::SORT_DAY_DESC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields'         => ['datum', 'memberName', 'empfaengerName', 'betreff', 'gesendet'],
            'showColumns'    => true,
            'label_callback' => ['Schachbulle\ContaoWertungsportalBundle\Classes\Reklamationen', 'zeile'],
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
                'attributes' => 'onclick="if(!confirm(\'Diese Reklamation wirklich löschen?\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    // Bearbeitbar sind nur diese beiden Felder, alles andere zeigt „Details"
    'palettes' => [
        'default' => '{bearbeitung_legend},erledigt,notiz',
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'datum' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['datum'],
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'eval'    => ['rgxp' => 'datim'],
            'sql'     => 'int(10) unsigned NOT NULL default 0',
        ],
        'memberId' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberId'],
            'sql'   => 'int(10) unsigned NOT NULL default 0',
        ],
        'memberName' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberName'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'memberEmail' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['memberEmail'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'empfaengerName' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerName'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'empfaengerEmail' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerEmail'],
            'sql'   => 'text NULL',
        ],
        'empfaengerArt' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['empfaengerArt'],
            'filter'    => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['arten'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'bcc' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bcc'],
            'sql'   => "varchar(255) NOT NULL default ''",
        ],
        'bereich' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bereich'],
            'filter'    => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['bereiche'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'url' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['url'],
            'sql'   => 'text NULL',
        ],
        'turnierUuid' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['turnierUuid'],
            'search' => true,
            'sql'    => "varchar(64) NOT NULL default ''",
        ],
        'turnierName' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['turnierName'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'spielerId' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['spielerId'],
            'search' => true,
            'sql'    => "varchar(64) NOT NULL default ''",
        ],
        'spielerName' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['spielerName'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'betreff' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['betreff'],
            'search' => true,
            'sql'    => "varchar(255) NOT NULL default ''",
        ],
        'text' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['text'],
            'search' => true,
            'sql'    => 'text NULL',
        ],
        'gesendet' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['gesendet'],
            'filter' => true,
            'eval'   => ['isBoolean' => true],
            'sql'    => "char(1) NOT NULL default ''",
        ],
        'fehler' => [
            'label' => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['fehler'],
            'sql'   => 'text NULL',
        ],
        'erledigt' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['erledigt'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['isBoolean' => true, 'tl_class' => 'w50 m12'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'notiz' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_reklamationen']['notiz'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['rows' => 6, 'tl_class' => 'clr long'],
            'sql'       => 'text NULL',
        ],
    ],
];
