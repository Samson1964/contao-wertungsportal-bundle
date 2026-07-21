<?php

/*
 * DCA für tl_wertungsportal_persons_memberships (Contao 4.13)
 *
 * Kindtabelle von tl_wertungsportal_persons.
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/dwzliste/persons, Unter-Array "memberships":
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DWZListeREST.html
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_persons_memberships'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ptable'            => 'tl_wertungsportal_persons',
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id'           => 'primary',
                'pid'          => 'index',
                'vkz'          => 'index',
                'vkz,memberNo' => 'index',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode'                  => DataContainer::MODE_PARENT,
            // Zuerst die unbeendeten Mitgliedschaften (leeres Bis-Datum),
            // danach nach Mitgliedschaftsende absteigend (jüngste zuerst).
            // Das Bis-Datum liegt als TT.MM.JJJJ vor und wird für die
            // Sortierung per SQL nach JJJJMMTT umgestellt.
            'fields'                => [
                "(spielgenehmigungBis = '') DESC",
                "CONCAT(SUBSTRING(spielgenehmigungBis, 7, 4), SUBSTRING(spielgenehmigungBis, 4, 2), SUBSTRING(spielgenehmigungBis, 1, 2)) DESC",
                'clubName',
            ],
            'headerFields'          => ['lastname', 'firstname', 'birthyear', 'rating'],
            'disableGrouping'       => true, // Keine Zwischenüberschriften zwischen den Datensätzen
            'panelLayout'           => 'filter;search,limit',
            'child_record_callback' => static function (array $row)
            {
                $licence = $GLOBALS['TL_LANG']['tl_wertungsportal_persons_memberships'][$row['licenceState']] ?? $row['licenceState'];

                // Zeitraum der Spielgenehmigung anzeigen (leeres Bis = laufend)
                $zeitraum = '';
                if ($row['spielgenehmigungVon'] || $row['spielgenehmigungBis']) {
                    $zeitraum = ', ' . ($row['spielgenehmigungVon'] ?: '?') . ' &ndash; ' . ($row['spielgenehmigungBis'] ?: 'laufend');
                }

                return '<div class="tl_content_left">' . $row['clubName'] . ' <span class="wp-meta">(' . $row['vkz'] . ($licence ? ', ' . $licence : '') . $zeitraum . ')</span></div>';
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
        'default' => '{membership_legend},vkz,memberNo,clubName;{licence_legend},licenceState,spielgenehmigungVon,spielgenehmigungBis;{antrag_legend:hide},antragstyp,antragszeitpunkt,antragsteller;{region_legend},regionName,federationName;{publish_legend},published',
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

        // Vereinskennziffer (VKZ) des Vereins
        'vkz' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 16, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // DSB-Mitglieds-/Lizenznummer
        'memberNo' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Name des Vereins
        'clubName' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Lizenzstatus (aktiv/passiv/Sondermitgliedschaft/ohne Spielgenehmigung)
        'licenceState' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['ACTIVE', 'PASSIVE', 'SONDER', 'OHNE'],
            'reference' => &$GLOBALS['TL_LANG']['tl_wertungsportal_persons_memberships'],
            'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(16) NOT NULL default ''",
        ],

        // Spielgenehmigung von (TT.MM.JJJJ, aus dem Vereinsmitglieder-CSV-Import)
        'spielgenehmigungVon' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],

        // Spielgenehmigung bis (TT.MM.JJJJ, aus dem Vereinsmitglieder-CSV-Import)
        'spielgenehmigungBis' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50'],
            'sql'       => "varchar(10) NOT NULL default ''",
        ],

        // ─────────────────────────────────────────────
        // Antragsdaten aus dem Spielgenehmigungen-CSV-Import
        // (Angemeldete/Abgemeldete im Zeitraum)
        // ─────────────────────────────────────────────

        // Antragstyp (z. B. Neuantrag Aktiv, Ummeldung, Vereinswechsel)
        'antragstyp' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Zeitpunkt des Antrags (TT.MM.JJJJ HH:MM)
        'antragszeitpunkt' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 20, 'tl_class' => 'w50'],
            'sql'       => "varchar(20) NOT NULL default ''",
        ],

        // Antragsteller (z. B. SYSTEM oder Benutzername)
        'antragsteller' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],

        // Name der Region
        'regionName' => [
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        // Name des Verbands
        'federationName' => [
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
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
