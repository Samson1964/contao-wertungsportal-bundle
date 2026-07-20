<?php

/*
 * DCA für tl_wertungsportal_tournaments_matches (Contao 4.13)
 *
 * Kindtabelle von tl_wertungsportal_tournaments (Partien).
 * Felddefinitionen gemäß DSB-Wertungsportal-API,
 * Endpunkt GET /dwz/tournaments/{uuid}/matches, Array "data":
 * https://schachde-apps.liga.nu/dsbwertungsportal/apidocs/resource_DSBTournamentREST.html
 *
 * Die Unter-Arrays "whitePlayer" und "blackPlayer" werden redundanzfrei
 * nur über whitePlayerUuid/blackPlayerUuid referenziert; die Spielerdaten
 * selbst liegen in tl_wertungsportal_tournaments_evaluation.
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_wertungsportal_tournaments_matches'] = [
    // Config
    'config' => [
        'dataContainer'     => DC_Table::class,
        'ptable'            => 'tl_wertungsportal_tournaments',
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
            'fields'                => ['round'],
            'headerFields'          => ['label', 'vkz', 'startdate', 'rounds'],
            'disableGrouping'       => true, // Keine Zwischenüberschriften zwischen den Datensätzen
            'panelLayout'           => 'filter;search,limit',
            'child_record_callback' => static function (array $row)
            {
                // Spielernamen aus der Auswertungstabelle auflösen
                $names = [];

                foreach (['whitePlayerUuid', 'blackPlayerUuid'] as $field) {
                    $player = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsEvaluationModel::findByPidAndPlayerUuid((int) $row['pid'], $row[$field]);
                    $names[] = null !== $player ? $player->lastname . ', ' . $player->firstname : $row[$field];
                }

                $meta = $row['result'] . ($row['restpartie'] ? ', Restpartie' : '');

                return '<div class="tl_content_left">Runde ' . $row['round'] . ': ' . $names[0] . ' &ndash; ' . $names[1] . ' <span class="wp-meta">(' . $meta . ')</span></div>';
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
        'default' => '{match_legend},round,result,expected,restpartie;{player_legend},whitePlayerUuid,blackPlayerUuid,whitePlayerName,blackPlayerName;{publish_legend},published',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'foreignKey' => 'tl_wertungsportal_tournaments.label',
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],

        // Runde
        'round' => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_ASC,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],

        // Ergebnis (z. B. REMIS, ZERO_HALF)
        'result' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],

        // Punkterwartung
        'expected' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "double NOT NULL default 0",
        ],

        // Restpartie
        'restpartie' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50 m12'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        // UUID des Weiß-Spielers (Referenz auf tl_wertungsportal_tournaments_evaluation.playerUuid)
        'whitePlayerUuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],

        // UUID des Schwarz-Spielers (Referenz auf tl_wertungsportal_tournaments_evaluation.playerUuid)
        'blackPlayerUuid' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 36, 'tl_class' => 'w50'],
            'sql'       => "varchar(36) NOT NULL default ''",
        ],

        // Reine Anzeigefelder (keine Datenbankspalten): Namen der Spieler aus
        // der Auswertungstabelle. Die Spieler-UUIDs gelten nur innerhalb des
        // Turniers - systemweit identifiziert die nuLigaPersonId die Person.
        'whitePlayerName' => [
            'input_field_callback' => static function (Contao\DataContainer $dc)
            {
                $name = '-';

                if (null !== $dc->activeRecord && $dc->activeRecord->whitePlayerUuid) {
                    $player = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsEvaluationModel::findByPidAndPlayerUuid((int) $dc->activeRecord->pid, (string) $dc->activeRecord->whitePlayerUuid);
                    $name = null !== $player ? $player->lastname . ', ' . $player->firstname . ' (nuLiga-ID ' . $player->nuLigaPersonId . ')' : $dc->activeRecord->whitePlayerUuid;
                }

                $label = $GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_matches']['whitePlayerName'][0] ?? 'whitePlayerName';

                return '<div class="w50 widget"><h3><label>' . $label . '</label></h3><p style="margin:2px 0;padding:5px 0;">' . $name . '</p></div>';
            },
        ],
        'blackPlayerName' => [
            'input_field_callback' => static function (Contao\DataContainer $dc)
            {
                $name = '-';

                if (null !== $dc->activeRecord && $dc->activeRecord->blackPlayerUuid) {
                    $player = \Schachbulle\ContaoWertungsportalBundle\Models\WertungsportalTournamentsEvaluationModel::findByPidAndPlayerUuid((int) $dc->activeRecord->pid, (string) $dc->activeRecord->blackPlayerUuid);
                    $name = null !== $player ? $player->lastname . ', ' . $player->firstname . ' (nuLiga-ID ' . $player->nuLigaPersonId . ')' : $dc->activeRecord->blackPlayerUuid;
                }

                $label = $GLOBALS['TL_LANG']['tl_wertungsportal_tournaments_matches']['blackPlayerName'][0] ?? 'blackPlayerName';

                return '<div class="w50 widget"><h3><label>' . $label . '</label></h3><p style="margin:2px 0;padding:5px 0;">' . $name . '</p></div>';
            },
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
