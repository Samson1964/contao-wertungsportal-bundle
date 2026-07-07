<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package News
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Table tl_dwz_kar
 */
$GLOBALS['TL_DCA']['tl_dwz_kar'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'ptable'                      => 'tl_dwz_spi',
		'switchToEdit'                => true, 
		'enableVersioning'            => true,
		'sql' => array
		(
			'keys' => array
			(
				'id'       => 'primary',
				'pid'      => 'index',
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 4,
			'flag'                    => 11,
			'fields'                  => array('eintrag', 'zaehler'),
			'headerFields'            => array('nachname', 'vorname'),
			'panelLayout'             => 'sort,filter;search,limit',
			'disableGrouping'         => true,
			'child_record_callback'   => array('tl_dwz_kar', 'listKartei')
		),
		'label' => array
		(
			'fields'                  => array('eintrag', 'zaehler'),
			'format'                  => '%s',
		),
		'global_operations' => array
		(
			'all' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_kar']['edit'],
				'href'                => 'act=edit',
				'icon'                => 'edit.gif',
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_kar']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif',
				//'button_callback'     => array('tl_dwz_kar', 'copyArchive')
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_kar']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
				//'button_callback'     => array('tl_dwz_kar', 'deleteArchive')
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_kar']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{dwz_legend},eintrag,zaehler,turnier,partien,punkte,gegner,we,erfolg,dwz,dwzindex;{source_legend},elobase;{publish_legend},published'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array
		(
			'foreignKey'              => 'tl_dwz_spi.nachname',
			'relation'                => array('type'=>'belongsTo', 'load'=>'eager'),
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'eintrag' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['eintrag'],
			'exclude'                 => true,
			'search'                  => false,
			'sorting'                 => true,
			'flag'                    => 11,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => true, 
				'maxlength'           => 5, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(5) unsigned NOT NULL default '0'"
		),
		'zaehler' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['zaehler'],
			'exclude'                 => true,
			'search'                  => false,
			'sorting'                 => false,
			'flag'                    => 11,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => true, 
				'maxlength'           => 5, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(5) unsigned NOT NULL default '0'"
		),
		'turnier' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['turnier'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 10, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(12) NOT NULL default ''"
		),
		'partien' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['partien'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 3, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(3) unsigned NULL"
		),
		'punkte' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['punkte'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 3, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "float(4,1) unsigned NULL"
		),
		'gegner' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['gegner'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 4, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(4) unsigned NULL"
		),
		'we' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['we'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 6, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "float(6,3) unsigned NULL"
		),
		'erfolg' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['erfolg'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 4, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(4) unsigned NULL"
		),
		'dwz' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['dwz'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 4, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(4) unsigned NULL"
		),
		'dwzindex' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['dwzindex'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 3, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "int(3) unsigned NULL"
		),
		'elobase' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['elobase'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('tl_class' => 'w50','isBoolean' => true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'published' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_kar']['published'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('tl_class' => 'w50','isBoolean' => true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
	)
);


/**
 * Class tl_dwz_kar
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Leo Feyer 2005-2014
 * @author     Leo Feyer <https://contao.org>
 * @package    News
 */
class tl_dwz_kar extends Backend
{

	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('BackendUser', 'User');
	}

	/**
	 * Datensätze auflisten
	 * @param array
	 * @return string
	 */
	public function listKartei($arrRow)
	{ 
		static $head;

		$temp = '';

		if(!$head)
		{
			$GLOBALS['TL_CSS'][] = 'bundles/contaodewis/css/backend.css';
			// Kopfspalte ausgeben
			$temp .= '<div style="display: inline-block">';
			$temp .= '<div class="tl_folder_tlist inline" style="">Eintrag</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Lfd.Nr.</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Turniercode</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Punkte</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Partien</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Gegner</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Erwartungswert</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">Erfolgszahl</div>';
			$temp .= '<div class="tl_folder_tlist inline" style="">DWZ</div>';
			$temp .= '<div style="clear:both"></div>';
		}
		// Eintrag ausgeben
		$temp .= '<div class="inline item align-right" style="width:47px;">'.$arrRow['eintrag'].'</div> ';
		$temp .= '<div class="inline item align-right" style="width:40px;">'.$arrRow['zaehler'].'</div> ';
		$temp .= '<div class="inline item" style="width:74px;">'.$arrRow['turnier'].'</div> ';
		$temp .= '<div class="inline item" style="width:41px;">'.$arrRow['punkte'].'</div> ';
		$temp .= '<div class="inline item" style="width:43px;">'.$arrRow['partien'].'</div> ';
		$temp .= '<div class="inline item" style="width:42px;">'.$arrRow['gegner'].'</div> ';
		$temp .= '<div class="inline item" style="width:100px;">'.$arrRow['we'].'</div> ';
		$temp .= '<div class="inline item" style="width:63px;">'.$arrRow['erfolg'].'</div> ';
		$temp .= '<div class="inline item align-right" style="width:30px; font-weight:bold;">'.$arrRow['dwz'].'-'.$arrRow['dwzindex'].'</div>';
		if(!$head)
		{
			// Kopfspalte ausgeben
			$head = true;
			$temp .= '</div>';
		}
		return $temp;
	}



	/**
	 * Datumswert aus Datenbank umwandeln
	 * @param mixed
	 * @return mixed
	 */
	public function getDate($varValue)
	{
		$laenge = strlen($varValue);
		$temp = '';
		switch($laenge)
		{
			case 8: // JJJJMMTT
				$temp = substr($varValue,6,2).'.'.substr($varValue,4,2).'.'.substr($varValue,0,4);
				break;
			case 6: // JJJJMM
				$temp = substr($varValue,4,2).'.'.substr($varValue,0,4);
				break;
			case 4: // JJJJ
				$temp = $varValue;
				break;
			default: // anderer Wert
				$temp = '';
		}

		return $temp;
	}

	/**
	 * Datumswert für Datenbank umwandeln
	 * @param mixed
	 * @return mixed
	 */
	public function putDate($varValue)
	{
		$laenge = strlen(trim($varValue));
		$temp = '';
		switch($laenge)
		{
			case 10: // TT.MM.JJJJ
				$temp = substr($varValue,6,4).substr($varValue,3,2).substr($varValue,0,2);
				break;
			case 7: // MM.JJJJ
				$temp = substr($varValue,3,4).substr($varValue,0,2);
				break;
			case 4: // JJJJ
				$temp = $varValue;
				break;
			default: // anderer Wert
				$temp = 0;
		}

		return $temp;
	}

}
