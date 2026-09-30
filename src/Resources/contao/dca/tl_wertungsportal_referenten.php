<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Schachbulle\ContaoWertungsportalBundle\Classes\Referenten;
use Schachbulle\ContaoWertungsportalBundle\Helper\Adressverknuepfung;

/**
 * Tabelle tl_wertungsportal_referenten
 *
 * Die Wertungsreferenten der Verbände mit Zuständigkeit. Die Auswahl der
 * Verbände wird nicht gepflegt, sondern aus dem Vereinsbestand gelesen
 * (Referenten::getVerbaende) — eine Umgliederung bei nu wandert damit von
 * selbst in die Liste.
 *
 * Kontaktdaten kommen seit 1.52.0 nur noch aus der zugeordneten Adresse der
 * Adressverwaltung (schachbulle/contao-adressen-bundle, Helper\
 * Adressverknuepfung). Eigene Angaben sind Name, nu-ID und die
 * Funktions-E-Mail des Referats. Das Feld „Adresse" erscheint nur, wenn das
 * Bundle installiert ist — die Spalte gibt es immer, damit das
 * Datenbankschema nicht vom Paketbestand abhängt.
 *
 * Die früheren Felder telefon, strasse, plz und ort stehen unten nur noch als
 * Spalte (ohne Eingabe, ohne Anzeige). Ganz entfernt, schlüge contao:migrate
 * sie zum Löschen vor — und ein interaktiver Lauf führt DROP-Anweisungen nach
 * der Bestätigung mit aus. Die Spalten und ihre Daten sollen aber bleiben.
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
        'default'      => '{person_legend},nachname,vorname,nuId;{kontakt_legend},email;{verband_legend},verbaende;{published_legend},published',
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
            'eval'             => ['includeBlankOption' => true, 'blankOptionLabel' => '– keine Adresse: nur Name und Funktions-E-Mail –', 'chosen' => true, 'tl_class' => 'w50 wizard'],
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
        // Funktions-E-Mail des Referats (z. B. dwz@verband.de): steht in den
        // Ausgaben vor den E-Mail-Adressen der Adresse und empfängt die
        // Reklamationen (Adressverknuepfung::zusammenfuehren)
        'email' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_referenten']['email'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        // Seit 1.52.0 nur noch Spalten, siehe Kopfkommentar. doNotShow hält
        // die alten Werte auch aus der Detailansicht (act=show) heraus
        'telefon' => [
            'eval' => ['doNotShow' => true],
            'sql'  => "varchar(64) NOT NULL default ''",
        ],
        'strasse' => [
            'eval' => ['doNotShow' => true],
            'sql'  => "varchar(255) NOT NULL default ''",
        ],
        'plz' => [
            'eval' => ['doNotShow' => true],
            'sql'  => "varchar(16) NOT NULL default ''",
        ],
        'ort' => [
            'eval' => ['doNotShow' => true],
            'sql'  => "varchar(128) NOT NULL default ''",
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

// Mit Adressverwaltung: Feld „Adresse" vor den Namen. Den Knopf „Aus der
// Adressverwaltung übernehmen" (1.51.0) gibt es seit 1.52.0 nicht mehr — das
// Adressen-Bundle verliert das Feld wertungsreferent, aus dem er las
if (Adressverknuepfung::verfuegbar()) {
    $GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['palettes']['default'] = str_replace(
        '{person_legend},nachname,',
        '{person_legend},adresse,nachname,',
        $GLOBALS['TL_DCA']['tl_wertungsportal_referenten']['palettes']['default']
    );
}
