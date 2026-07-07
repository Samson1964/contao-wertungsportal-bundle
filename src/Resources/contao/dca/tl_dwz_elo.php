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
 * Table tl_dwz_elo
 */
$GLOBALS['TL_DCA']['tl_dwz_elo'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'switchToEdit'                => true, 
		'enableVersioning'            => true,
		'sql' => array
		(
			'keys' => array
			(
				'id'           => 'primary',
				'elodate'      => 'index',
				'fideid'       => 'index',
				'surname'      => 'index',
				'rating'       => 'index',
				'games'        => 'index',
				'rapid_rating' => 'index',
				'rapid_games'  => 'index',
				'blitz_rating' => 'index',
				'blitz_games'  => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 1,
			'flag'                    => 11,
			'fields'                  => array(),
			'headerFields'            => array(),
			'panelLayout'             => 'sort,filter;search,limit',
			'disableGrouping'         => true,
		),
		'label' => array
		(
			'fields'                  => array(),
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
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_elo']['edit'],
				'href'                => 'act=edit',
				'icon'                => 'edit.gif',
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_elo']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif',
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_elo']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_elo']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	//'palettes' => array
	//(
	//	'default'                     => '{fide_legend},datum,code,land,titel,elo,partien;{source_legend},elobase;{publish_legend},published'
	//),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'elodate' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'fideid' => array
		(
			'sql'                     => "int(16) unsigned NOT NULL default '0'"
		),
		'surname' => array
		(
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'prename' => array
		(
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'intent' => array
		(
			'sql'                     => "varchar(16) NOT NULL default ''"
		),
		'country' => array
		(
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'sex' => array
		(
			'sql'                     => "varchar(1) NOT NULL default ''"
		),
		'title' => array
		(
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'w_title' => array
		(
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'o_title' => array
		(
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'foa_title' => array
		(
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'country' => array
		(
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'rating' => array
		(
			'sql'                     => "int(4) unsigned NOT NULL default '0'"
		),
		'games' => array
		(
			'sql'                     => "int(4) unsigned NOT NULL default '0'"
		),
		'rapid_rating' => array
		(
			'sql'                     => "int(4) unsigned NOT NULL default '0'"
		),
		'rapid_games' => array
		(
			'sql'                     => "int(4) unsigned NOT NULL default '0'"
		),
		'blitz_rating' => array
		(
			'sql'                     => "int(4) unsigned NOT NULL default '0'"
		),
		'blitz_games' => array
		(
			'sql'                     => "int(4) unsigned NOT NULL default '0'"
		),
		'birthday' => array
		(
			'sql'                     => "int(8) unsigned NOT NULL default '0'"
		),
		'flag' => array
		(
			'sql'                     => "varchar(8) NOT NULL default ''"
		),
		'published' => array
		(
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'blitz_flag' => array
		(
			'sql'                     => "varchar(8) NOT NULL default ''"
		),
		'rapid_flag' => array
		(
			'sql'                     => "varchar(8) NOT NULL default ''"
		),
	)
);


/**
 * Class tl_dwz_elo
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Leo Feyer 2005-2014
 * @author     Leo Feyer <https://contao.org>
 * @package    News
 */
class tl_dwz_elo extends \Backend
{

	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('BackendUser', 'User');
	}

}
