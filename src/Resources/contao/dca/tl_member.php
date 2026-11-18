<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * tl_member: Zugriff auf das Wertungsarchiv (ab 2.0.0).
 *
 * Die Ausgabe des Wertungsarchivs ist für Besucher abgeschaltet, solange es
 * erprobt wird (Frank Binding, 18.11.2026). Nur Mitglieder mit diesem
 * Schalter sehen sie (Wertungsarchiv\Zugriff::erlaubt()). tl_member stammt aus
 * dem Kern und ist geladen, bevor diese Datei an der Reihe ist — die Palette
 * kann deshalb hier erweitert werden.
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['wertungsarchiv'] = array
(
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => array('tl_class' => 'w50 m12'),
    'sql'       => "char(1) NOT NULL default ''",
);

PaletteManipulator::create()
    ->addLegend('wertungsarchiv_legend', 'account_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('wertungsarchiv', 'wertungsarchiv_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_member')
;
