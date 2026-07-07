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
 * Table tl_dwz_spi
 */
$GLOBALS['TL_DCA']['tl_dwz_spi'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'ctable'                      => array('tl_dwz_spiver', 'tl_dwz_inf', 'tl_dwz_fid', 'tl_dwz_kar'),
		'onload_callback'             => array
		(
			array('tl_dwz_spi', 'applyAdvancedFilter'),
		),
		'switchToEdit'                => true,
		'enableVersioning'            => true,
		'sql' => array
		(
			'keys' => array
			(
				'id'             => 'primary',
				'dewisID'        => 'index',
				'nuLigaPersonId' => 'index',
				'elobase'        => 'index',
				'verstorben'     => 'index',
				'published'      => 'index',
				'nachname'       => 'index',
				'vorname'        => 'index',
				'blocked'        => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 1,
			'fields'                  => array('nachname', 'vorname'),
			'flag'                    => 3,
			'panelLayout'             => 'myfilter;filter;sort,search,limit',
			'panel_callback'          => array('myfilter' => array('tl_dwz_spi', 'generateAdvancedFilter')),
		),
		'label' => array
		(
			'fields'                  => array('nachname', 'vorname'),
			'format'                  => '%s, %s',
			'label_callback'          => array('tl_dwz_spi', 'listSpieler')
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
			'editHeader' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['editHeader'],
				'href'                => 'act=edit',
				'icon'                => 'header.gif',
			),
			'editKartei' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['editKartei'],
				'href'                => 'table=tl_dwz_kar',
				'icon'                => 'bundles/contaodewis/images/icon_kartei.png',
			),
			'editVereine' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['editVereine'],
				'href'                => 'table=tl_dwz_spiver',
				'icon'                => 'bundles/contaodewis/images/icon_vereine.png',
			),
			'editInfo' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['editInfo'],
				'href'                => 'table=tl_dwz_inf',
				'icon'                => 'bundles/contaodewis/images/icon_info.png',
			),
			'editElo' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['editElo'],
				'href'                => 'table=tl_dwz_fid',
				'icon'                => 'bundles/contaodewis/images/icon_fide.png',
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif',
				//'button_callback'     => array('tl_dwz_spi', 'copyArchive')
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
				//'button_callback'     => array('tl_dwz_spi', 'deleteArchive')
			),
			'toggle' => array
			(
				'label'                => &$GLOBALS['TL_LANG']['tl_dwz_spi']['toggle'],
				'attributes'           => 'onclick="Backend.getScrollOffset()"',
				'haste_ajax_operation' => array
				(
					'field'            => 'published',
					'options'          => array
					(
						array('value' => '', 'icon' => 'invisible.svg'),
						array('value' => '1', 'icon' => 'visible.svg'),
					),
				),
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_spi']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'__selector__'                => array('verstorben', 'addImage', 'blocked'),
		'default'                     => '{name_legend},vorname,nachname,titel,geschlecht,typ;{geburt_legend:hide},geburtstag,geburtsort,verstorben;{contact_legend:hide},plz,ort,strasse,telefon1,email1,telefon2,email2,telefon3,email3,telefax;{club_legend:hide},dewisID,nuLigaPersonId,zpsver,zpsmgl,status,funktion;{fide_legend:hide},fideID,fideNation,fideElo,fideTitel;{elobase_legend:hide},altpkz,elobase;{dwz_legend:hide},dwzwoche,dwz,dwzindex;{image_legend:hide},addImage;{info_legend:hide},info,homepage;{ban_legend:hide},blocked,link_altkartei;{publish_legend},published'
	),

	// Subpalettes
	'subpalettes' => array
	(
		'verstorben'                  => 'sterbetag,sterbeort',
		'addImage'                    => 'singleSRC',
		'blocked'                     => 'grund,melder'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'search'                  => true,
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array
		(
			'sorting'                 => true,
			'flag'                    => 1,
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'nachname' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['nachname'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>40, 'tl_class'=>'w50'),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),
		'vorname' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['vorname'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>40, 'tl_class'=>'w50'),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),
		'titel' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['titel'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>20, 'tl_class'=>'w50'),
			'sql'                     => "varchar(20) NOT NULL default ''"
		),
		'geschlecht' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['geschlecht'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'select',
			'options'                 => array
			(
				'M'                   => 'Männlich',
				'W'                   => 'Weiblich',
			),
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 1,
				'tl_class'            => 'w50 clr',
				'includeBlankOption'  => true,
			),
			'sql'                     => "varchar(1) NOT NULL default ''"
		),
		'typ' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['typ'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'default'                 => 'D',
			'inputType'               => 'select',
			'options'                 => array
			(
				'D'                   => 'Deutsche/r',
				'A'                   => 'Ausländer/in',
				'G'                   => 'Gleichgestellte/r',
			),
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 1,
				'tl_class'            => 'w50',
				'includeBlankOption'  => true,
			),
			'sql'                     => "varchar(1) NOT NULL default ''"
		),
		'geburtstag' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['geburtstag'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 8,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 10,
				'tl_class'            => 'w50 clr',
				'rgxp'                => 'alnum'
			),
			'load_callback'           => array
			(
				array('tl_dwz_spi', 'getDate')
			),
			'save_callback' => array
			(
				array('tl_dwz_spi', 'putDate')
			),
			'sql'                     => "int(8) unsigned NOT NULL default '0'"
		),
		'geburtsort' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['geburtsort'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>40, 'tl_class'=>'w50'),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),
		'verstorben' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['verstorben'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('submitOnChange'=>true, 'tl_class'=>'clr'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'sterbetag' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['sterbetag'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 10,
				'tl_class'            => 'w50',
				'rgxp'                => 'alnum'
			),
			'load_callback'           => array
			(
				array('tl_dwz_spi', 'getDate')
			),
			'save_callback' => array
			(
				array('tl_dwz_spi', 'putDate')
			),
			'sql'                     => "int(8) unsigned NOT NULL default '0'"
		),
		'sterbeort' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['sterbeort'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>40, 'tl_class'=>'w50'),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),
		'plz' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['plz'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'filter'                  => true,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>64, 'tl_class'=>'w50 clr'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'ort' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['ort'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'filter'                  => true,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'strasse' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['strasse'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'telefon1' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['telefon1'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>64, 'tl_class'=>'w50 clr'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'telefon2' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['telefon2'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>64, 'tl_class'=>'w50'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'telefon3' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['telefon3'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>64, 'tl_class'=>'w50'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'telefax' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['telefax'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>64, 'tl_class'=>'w50'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'email1' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['email1'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50', 'rgxp'=>'email'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'email2' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['email2'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50', 'rgxp'=>'email'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'email3' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['email3'],
			'inputType'               => 'text',
			'exclude'                 => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'search'                  => true,
			'eval'                    => array('mandatory'=>false, 'maxlength'=>255, 'tl_class'=>'w50', 'rgxp'=>'email'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'zpsver' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['zpsver'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'			  => 5,
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(5) NOT NULL default ''"
		),
		'zpsmgl' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['zpsmgl'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'			  => 4,
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(4) NOT NULL default ''"
		),
		'status' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['status'],
			'exclude'                 => true,
			'search'                  => true,
			'filter'                  => true,
			'inputType'               => 'select',
			'options'                 => array
			(
				'A'                   => 'Aktiv',
				'P'                   => 'Passiv',
				'G'                   => 'Gastspielgenehmigung',
			),
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 1,
				'tl_class'            => 'w50',
			),
			'sql'                     => "varchar(1) NOT NULL default ''"
		),
		'funktion' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['funktion'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'maxlength'=>64, 'tl_class'=>'w50'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'dewisID' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['dewisID'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>10, 'tl_class'=>'w50'),
			'sql'                     => "int(10) unsigned NOT NULL default '0'",
		),
		'nuLigaPersonId' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['nuLigaPersonId'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>16, 'tl_class'=>'w50'),
			'sql'                     => "varchar(16) NOT NULL default ''"
		),
		'altpkz' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['altpkz'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>8, 'tl_class'=>'w50'),
			'sql'                     => "varchar(8) NOT NULL default ''"
		),
		'dwzwoche' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['dwzwoche'],
			'exclude'                 => false,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 7,
				'tl_class'            => 'w50',
			),
			'sql'                     => "varchar(7) NOT NULL default ''"
		),
		'dwz' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['dwz'],
			'exclude'                 => false,
			'search'                  => false,
			'default'                 => 0,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'rgxp'                => 'digit',
				'maxlength'           => 4,
				'tl_class'            => 'w50',
			),
			'sql'                     => "smallint(4) unsigned NOT NULL default '0'"
		),
		'dwzindex' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['dwzindex'],
			'exclude'                 => false,
			'search'                  => false,
			'default'                 => 0,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'rgxp'                => 'digit',
				'maxlength'           => 3,
				'tl_class'            => 'w50',
			),
			'sql'                     => "smallint(3) unsigned NOT NULL default '0'"
		),
		'fideTitel' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['fideTitel'],
			'exclude'                 => true,
			'search'                  => false,
			'sorting'                 => true,
			'flag'                    => 1,
			'default'                 => '',
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 3,
				'tl_class'            => 'w50',
			),
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'fideElo' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['fideElo'],
			'exclude'                 => false,
			'search'                  => false,
			'default'                 => 0,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'rgxp'                => 'digit',
				'maxlength'           => 4,
				'tl_class'            => 'w50',
			),
			'sql'                     => "smallint(4) unsigned NOT NULL default '0'"
		),
		'fideID' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['fideID'],
			'exclude'                 => true,
			'search'                  => false,
			'default'                 => '',
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 12,
				'tl_class'            => 'w50',
			),
			'sql'                     => "varchar(12) NOT NULL default ''"
		),
		'fideNation' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['fideNation'],
			'exclude'                 => true,
			'search'                  => false,
			'default'                 => '',
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 3,
				'tl_class'            => 'w50',
			),
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'addImage' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['addImage'],
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('submitOnChange'=>true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'singleSRC' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['singleSRC'],
			'exclude'                 => true,
			'inputType'               => 'fileTree',
			'eval'                    => array('filesOnly'=>true, 'extensions'=>Config::get('validImageTypes'), 'fieldType'=>'radio', 'mandatory'=>true),
			'sql'                     => "binary(16) NULL"
		),
		'alt' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['alt'],
			'exclude'                 => true,
			'search'                  => true,
			'default'                 => '',
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255, 'tl_class'=>'long'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'size' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['size'],
			'exclude'                 => true,
			'inputType'               => 'imageSize',
			'default'                 => '',
			'options'                 => $GLOBALS['TL_CROP'],
			'reference'               => &$GLOBALS['TL_LANG']['MSC'],
			'eval'                    => array('rgxp'=>'digit', 'nospace'=>true, 'helpwizard'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'imagemargin' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['imagemargin'],
			'exclude'                 => true,
			'default'                 => '',
			'inputType'               => 'trbl',
			'options'                 => array('px', '%', 'em', 'ex', 'pt', 'pc', 'in', 'cm', 'mm'),
			'eval'                    => array('includeBlankOption'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(128) NOT NULL default ''"
		),
		'imageUrl' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['imageUrl'],
			'exclude'                 => true,
			'search'                  => true,
			'default'                 => '',
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'url', 'decodeEntities'=>true, 'maxlength'=>255, 'tl_class'=>'w50 wizard'),
			'wizard' => array
			(
				array('tl_dwz_spi', 'pagePicker')
			),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'fullsize' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['fullsize'],
			'exclude'                 => true,
			'default'                 => '',
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50 m12'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'caption' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['caption'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255, 'allowHtml'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'floating' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['floating'],
			'exclude'                 => true,
			'inputType'               => 'radioTable',
			'options'                 => array('above', 'left', 'right', 'below'),
			'eval'                    => array('cols'=>4, 'tl_class'=>'w50'),
			'reference'               => &$GLOBALS['TL_LANG']['MSC'],
			'sql'                     => "varchar(12) NOT NULL default ''"
		),
		'info' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['info'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('rte'=>'tinyMCE'),
			'explanation'             => 'insertTags',
			'sql'                     => "text NULL"
		),
		'homepage' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['homepage'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>false, 'rgxp'=>'url', 'decodeEntities'=>true, 'maxlength'=>255, 'tl_class'=>'long clr'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'elobase' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['elobase'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('tl_class' => 'w50','isBoolean' => true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'blocked' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['blocked'],
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('submitOnChange'=>true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'link_altkartei' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['link_altkartei'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('tl_class' => 'w50','isBoolean' => true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'grund' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['grund'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('rte'=>'tinyMCE', 'helpwizard'=>true),
			'explanation'             => 'insertTags',
			'sql'                     => "mediumtext NULL"
		),
		'melder' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['melder'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false,
				'maxlength'           => 40,
				'tl_class'            => 'long'
			),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),
		'published' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_spi']['published'],
			'inputType'               => 'checkbox',
			'exclude'                 => true,
			'filter'                  => true,
			'default'                 => 1,
			'eval'                    => array
			(
				'tl_class'            => 'w50',
				'isBoolean'           => true
			),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'contaoMemberID' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
	)
);


/**
 * Class tl_dwz_spi
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Leo Feyer 2005-2014
 * @author     Leo Feyer <https://contao.org>
 * @package    News
 */
class tl_dwz_spi extends Backend
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
	public function listSpieler($arrRow)
	{
		// Name und Vorname ausgeben
		$temp = $arrRow['nachname'];
		if($arrRow['vorname']) $temp .= ',' . $arrRow['vorname'];
		if($arrRow['titel']) $temp .= ',' . $arrRow['titel'];
		// Lebensdaten ausgeben
		$birthday = $this->getDate($arrRow['geburtstag']);
		$temp .= ' (* ' . $birthday . ')';
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

	/**
	 * Return the link picker wizard
	 * @param \DataContainer
	 * @return string
	 */
	public function pagePicker(DataContainer $dc)
	{
		return ' <a href="contao/page.php?do='.Input::get('do').'&amp;table='.$dc->table.'&amp;field='.$dc->field.'&amp;value='.str_replace(array('{{link_url::', '}}'), '', $dc->value).'" onclick="Backend.getScrollOffset();Backend.openModalSelector({\'width\':768,\'title\':\''.specialchars(str_replace("'", "\\'", $GLOBALS['TL_LANG']['MOD']['page'][0])).'\',\'url\':this.href,\'id\':\''.$dc->field.'\',\'tag\':\'ctrl_'.$dc->field . ((Input::get('act') == 'editAll') ? '_' . $dc->id : '').'\',\'self\':this});return false">' . Image::getHtml('pickpage.gif', $GLOBALS['TL_LANG']['MSC']['pagepicker'], 'style="vertical-align:top;cursor:pointer"') . '</a>';
	}

	/**
	 * Funktion applyAdvancedFilter
	 * Spezialfilter anwenden
	 * @return -
	 */
	public function applyAdvancedFilter()
	{

		$session = \Session::getInstance()->getData();

		// Filterwerte in der Sitzung speichern
		foreach($_POST as $k => $v)
		{
			if(substr($k, 0, 4) != 'tds_')
			{
				continue;
			}

			// Filter zurücksetzen
			if($k == \Input::post($k))
			{
				unset($session['filter']['tl_dwz_spiFilter'][$k]);
			}
			// Filter zuweisen
			else
			{
				$session['filter']['tl_dwz_spiFilter'][$k] = \Input::post($k);
			}
		}

		$this->Session->setData($session);

		if(\Input::get('id') > 0 || !isset($session['filter']['tl_dwz_spiFilter']))
		{
			return;
		}

		$arrPlayers = null;

		if(isset($session['filter']['tl_dwz_spiFilter']['tds_filter']))
		{
			switch($session['filter']['tl_dwz_spiFilter']['tds_filter'])
			{
				case '1': // Alle Spieler mit Homepage-Link
					$objPlayers = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_spi WHERE homepage != ?")
					                                      ->execute('');
					$arrPlayers = is_array($arrPlayers) ? array_intersect($arrPlayers, $objPlayers->fetchEach('id')) : $objPlayers->fetchEach('id');
					break;

				case '2': // Alle Spieler ohne Homepage-Link
					$objPlayers = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_spi WHERE homepage = ?")
					                                      ->execute('');
					$arrPlayers = is_array($arrPlayers) ? array_intersect($arrPlayers, $objPlayers->fetchEach('id')) : $objPlayers->fetchEach('id');
					break;

				default:

			}
		}

		if(is_array($arrPlayers) && empty($arrPlayers))
		{
			$arrPlayers = array(0);
		}

		//$log = print_r($arrPlayers, true);
		//log_message($log, 'fernschachverwaltung.log');

		$GLOBALS['TL_DCA']['tl_dwz_spi']['list']['sorting']['root'] = $arrPlayers;

	}

	public function generateAdvancedFilter(DataContainer $dc)
	{

		if(\Input::get('id') > 0) return '';

		$session = \Session::getInstance()->getData();

		// Filters
		$arrFilters = array
		(
			'tds_filter'   => array
			(
				'name'    => 'tds_filter',
				'label'   => $GLOBALS['TL_LANG']['tl_dwz_spi']['filter_extended'],
				'options' => array
				(
					'1'   => $GLOBALS['TL_LANG']['tl_dwz_spi']['filter_active_homepage'],
					'2'   => $GLOBALS['TL_LANG']['tl_dwz_spi']['filter_without_homepage'],
				)
			),
		);

		$strBuffer = '<div class="tl_filter tds_filter tl_subpanel"><strong>' . $GLOBALS['TL_LANG']['tl_dwz_spi']['filter'] . ':</strong> ' . "\n";

		// Generate filters
		foreach ($arrFilters as $arrFilter)
		{
			$strOptions = '<option value="' . $arrFilter['name'] . '">' . $arrFilter['label'] . '</option>' . "\n";
			$strOptions .= '<option value="' . $arrFilter['name'] . '">---</option>' . "\n";

			// Prüfen ob ein Filter gesetzt ist
			if(isset($session['filter']['tl_dwz_spiFilter']['tds_filter']))
			{
				$filterwert = $session['filter']['tl_dwz_spiFilter']['tds_filter'];
			}
			else $filterwert = '';

			// Generate options
			foreach($arrFilter['options'] as $k => $v)
			{
				$strOptions .= '<option value="' . $k . '"' . (((string)$filterwert === (string)$k) ? ' selected' : '') . '>' . $v . '</option>' . "\n";
			}

		}

		$strBuffer .= '<select name="' . $arrFilter['name'] . '" id="' . $arrFilter['name'] . '" class="tl_select' . (isset($session['filter']['tl_dwz_spiFilter'][$arrFilter['name']]) ? ' active' : '') . '">'.$strOptions.'</select>' . "\n";
		
		return $strBuffer . '</div>';

	}

}