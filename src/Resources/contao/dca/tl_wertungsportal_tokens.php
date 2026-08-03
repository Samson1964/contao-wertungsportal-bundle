<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Tabelle tl_wertungsportal_tokens
 *
 * Zugangsschlüssel für die örtliche Vereinslisten-Schnittstelle. Je Schlüssel
 * ein Datensatz mit dem Verein, für den er gilt, und den Angaben aus der
 * Registrierung. Die Zugriffe stehen in der Kindtabelle
 * tl_wertungsportal_tokens_access.
 */
$GLOBALS['TL_DCA']['tl_wertungsportal_tokens'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ctable'            => ['tl_wertungsportal_tokens_access'],
        'switchToEdit'      => true,
        'enableVersioning'  => true,
        'onload_callback'   => [
            ['Schachbulle\ContaoWertungsportalBundle\Classes\TokenVerwaltung', 'zaehlerAktualisieren'],
        ],
        // ACHTUNG: Hier steht mit Absicht KEIN ondelete_callback. Die Zugriffe
        // eines gelöschten Schlüssels bleiben stehen und erscheinen in der
        // Auswertung als „gelöschter Schlüssel" — die Nutzungszahlen der
        // Schnittstelle sollen durch eine Löschung keine Lücke bekommen.
        // Weggeräumt werden sie über „Alte Zugriffe löschen" (90 Tage).
        'sql'               => [
            'keys' => [
                'id'        => 'primary',
                'token'     => 'unique',
                'vkz'       => 'index',
                'email'     => 'index',
                'published' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['vkz'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'sort,filter;search,limit',
        ],
        'label' => [
            'fields'         => ['vkz', 'nachname', 'vorname', 'email', 'zugriffe', 'letzterZugriff'],
            'showColumns'    => true,
            'label_callback' => ['Schachbulle\ContaoWertungsportalBundle\Classes\TokenVerwaltung', 'zeile'],
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            // Werkzeug zuerst: den Schlüssel selbst bearbeiten
            'editheader' => [
                'href'  => 'act=edit',
                'icon'  => 'header.svg',
            ],
            // Die Zugriffe des Schlüssels öffnen
            'edit' => [
                'href'  => 'table=tl_wertungsportal_tokens_access',
                'icon'  => 'edit.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle' => [
                'href'         => 'act=toggle&amp;field=published',
                'icon'         => 'visible.svg',
                'showInHeader' => true,
            ],
            'show' => [
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        'default' => '{token_legend},token,vkz;{person_legend},vorname,nachname,email;{sperre_legend},gesperrt,grund;{publish_legend},published',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Der Schlüssel selbst. Nicht editierbar: Er wird bei der
        // Registrierung erzeugt; ein von Hand geänderter Wert würde den
        // Empfänger aussperren, ohne dass er es erfährt
        'token' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['token'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'readonly' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        'vkz' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['vkz'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 16, 'rgxp' => 'alnum', 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        'vorname' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['vorname'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],

        'nachname' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['nachname'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 128, 'tl_class' => 'w50'],
            'sql'       => "varchar(128) NOT NULL default ''",
        ],

        'email' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['email'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'rgxp' => 'email', 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Sperre: Der Schlüssel bleibt bestehen, wird aber abgewiesen. Der
        // Grund steht im Datensatz, damit später nachvollziehbar ist, warum
        'gesperrt' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['gesperrt'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50 clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        'grund' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['grund'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'clr long'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // IP-Adresse der Registrierung. Dient allein dazu, massenhaftes
        // Anfordern zu erkennen und zu bremsen — deshalb nur lesbar
        'ip' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['ip'],
            'search'  => true,
            'sql'     => "varchar(64) NOT NULL default ''",
        ],

        // Abgeleitete Werte, gepflegt beim Zugriff bzw. beim Öffnen der Liste
        'zugriffe' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['zugriffe'],
            'sorting' => true,
            'sql'     => "int(10) unsigned NOT NULL default 0",
        ],

        'letzterZugriff' => [
            'label'   => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['letzterZugriff'],
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'sql'     => "int(10) unsigned NOT NULL default 0",
        ],

        'published' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_wertungsportal_tokens']['published'],
            'exclude'   => true,
            'filter'    => true,
            // Ohne toggle => true weist DC_Table den act=toggle-Aufruf ab
            'toggle'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true, 'tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
    ],
];
