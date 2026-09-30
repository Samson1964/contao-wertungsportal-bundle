<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Schachbulle\ContaoWertungsportalBundle\Classes\Referenten;
use Schachbulle\ContaoWertungsportalBundle\Helper\Adressverknuepfung;

/**
 * Tabelle tl_wertungsportal_referenten
 *
 * Die Wertungsreferenten der Verbände mit Anschrift und Zuständigkeit. Die
 * Auswahl der Verbände wird nicht gepflegt, sondern aus dem Vereinsbestand
 * gelesen (Referenten::getVerbaende) — eine Umgliederung bei nu wandert damit
 * von selbst in die Liste.
 *
 * Ab 1.51.0 kann ein Referent einer Adresse aus der Adressverwaltung
 * (schachbulle/contao-adressen-bundle) zugeordnet werden; dann kommen Name und
 * Kontaktdaten von dort (Helper\Adressverknuepfung). Feld und Übernahme-Knopf
 * erscheinen nur, wenn das Bundle installiert ist — die Spalte gibt es immer,
 * damit das Datenbankschema nicht vom Paketbestand abhängt.
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_referenten'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'onload_callback' => [
            [Referenten::class, 'pflichtfelder'],
        ],
        'onsubmit_callback' => [
            [Referenten::class, 'nameUebernehmen'],
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'nachname' => 'index',
                'nuId' => 'index',
                'published' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['nachname', 'vorname'],
            'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields'         => ['nachname', 'vorname'],
            'format'         => '%s, %s',
            'label_callback' => [Referenten::class, 'zeile'],
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
                'attributes' => 'onclick="if(!confirm(\'Diesen Datensatz wirklich löschen?\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle' => [
                'href'   => 'act=toggle&amp;field=published',
                'icon'   => 'visible.svg',
                'toggle' => true,
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    'palettes' => [
        '__selector__' => [],
        'default'      => '{person_legend},nachname,vorname,nuId;{kontakt_legend},email,telefon;{adresse_legend},strasse,plz,ort;{verband_legend},verbaende;{published_legend},published',
    ],

    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'adresse' => [
            'label'            => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['adresse'],
            'exclude'          => true,
            'inputType'        => 'select',
            'options_callback' => [Referenten::class, 'getAdressen'],
            'wizard'           => [
                [Referenten::class, 'adresseBearbeiten'],
            ],
            'eval'             => ['includeBlankOption' => true, 'blankOptionLabel' => '– keine, nur die Angaben unten –', 'chosen' => true, 'tl_class' => 'w50 wizard'],
            'sql'              => "int(10) unsigned NOT NULL default 0",
        ],
        'nachname' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['nachname'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'inputType' => 'text',
            // Pflicht nur ohne Adresse — Referenten::pflichtfelder() nimmt die
            // Pflicht zurück, wenn eine gewählt ist
            'eval'      => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50 clr'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'vorname' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['vorname'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'nuId' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['nuId'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50 clr'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'email' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['email'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'telefon' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['telefon'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'phone', 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'strasse' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['strasse'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'plz' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['plz'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],
        'ort' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['ort'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],
        'verbaende' => [
            'label'            => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['verbaende'],
            'exclude'          => true,
            'filter'           => true,
            'inputType'        => 'checkboxWizard',
            // Die Verbände kommen aus dem Vereinsbestand, nicht aus einer
            // gepflegten Liste — siehe Klassenkommentar
            'options_callback' => [Referenten::class, 'getVerbaende'],
            'eval'             => ['multiple' => true, 'tl_class' => 'clr'],
            'sql'              => 'blob NULL',
        ],
        'published' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['published'],
            'exclude'   => true,
            'filter'    => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_DESC,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true, 'tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
    ],
];

// Mit Adressverwaltung: Feld „Adresse" vor den Namen und der Knopf für die
// einmalige Übernahme (Classes\Referentenuebernahme)
if (Adressverknuepfung::verfuegbar()) {
    $GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['palettes']['default'] = str_replace(
        '{person_legend},nachname,',
        '{person_legend},adresse,nachname,',
        $GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['palettes']['default']
    );

    $GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['list']['global_operations'] = [
        'uebernehmen' => [
            'href'       => 'key=uebernehmen',
            'class'      => 'header_theme_import',
            'attributes' => 'onclick="Backend.getScrollOffset()"',
        ],
    ] + $GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['list']['global_operations'];
}
