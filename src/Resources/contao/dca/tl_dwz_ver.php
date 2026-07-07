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
 * Table tl_dwz_ver
 */
$GLOBALS['TL_DCA']['tl_dwz_ver'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'onload_callback'             => array
		(
			array('tl_dwz_ver', 'applyAdvancedFilter'),
		),
		'switchToEdit'                => true, 
		'enableVersioning'            => true,
		'sql' => array
		(
			'keys' => array
			(
				'id'                  => 'primary',
				'zpsver'              => 'index',
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 1,
			'fields'                  => array('zpsver'),
			'flag'                    => 3,
			'panelLayout'             => 'myfilter;filter;sort,search,limit',
			'panel_callback'          => array('myfilter' => array('tl_dwz_ver', 'generateAdvancedFilter')),
		),
		'label' => array
		(
			'fields'                  => array('zpsver', 'status', 'name'),
			'format'                  => '%s %s %s',
			'label_callback'          => array('tl_dwz_ver', 'listVereine')
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
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_ver']['edit'],
				'href'                => 'act=edit',
				'icon'                => 'edit.gif',
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_ver']['copy'],
				'href'                => 'act=copy',
				'icon'                => 'copy.gif',
				//'button_callback'     => array('tl_dwz_ver', 'copyArchive')
			),
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_ver']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
				//'button_callback'     => array('tl_dwz_ver', 'deleteArchive')
			),
			'toggle' => array
			(
				'label'                => &$GLOBALS['TL_LANG']['tl_dwz_ver']['toggle'],
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
				'label'               => &$GLOBALS['TL_LANG']['tl_dwz_ver']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.gif'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'__selector__'                => array('addImage'), 
		'default'                     => '{name_legend},zpsver,verband,name,altname,abkuerzung,status,statusdatum;{adresse_legend},empfaenger,plz,ort,strasse,telefon,email,adressdatum;{image_legend},addImage;{info_legend},info,homepage;{source_legend},elobase;{publish_legend},published'
	),

	// Subpalettes
	'subpalettes' => array
	(
		'addImage'                    => 'singleSRC'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		), 
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'zpsver' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['zpsver'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => true, 
				'maxlength'           => 5, 
				'tl_class'            => 'w50',
				'unique'              => true
			),
			'sql'                     => "varchar(5) NOT NULL default ''"
		),
		'verband' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['verband'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>3, 'tl_class'=>'w50'),
			'sql'                     => "varchar(3) NOT NULL default ''"
		),
		'status' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['status'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'default'                 => 'A',
			'inputType'               => 'select',
			'options'                 => array
			(
				'A'                   => 'Aktiv',
				'L'                   => 'Abgemeldet',
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
		'statusdatum' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['statusdatum'],
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
				array('tl_dwz_ver', 'getDate')
			),
			'save_callback' => array
			(
				array('tl_dwz_ver', 'putDate')
			),
			'sql'                     => "int(8) unsigned NOT NULL default '0'"
		),
		'name' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['name'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 255, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'altname' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['altname'],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'flag'                    => 1,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'maxlength'           => 255, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'abkuerzung' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['abkuerzung'],
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
			'sql'                     => "varchar(4) NOT NULL default ''"
		),
		'empfaenger' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['empfaenger'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 40, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),  
		'telefon' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['telefon'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 20, 
				'tl_class'            => 'w50',
				'rgxp'                => 'phone'
			),
			'sql'                     => "varchar(20) NOT NULL default ''"
		),  
		'plz' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['plz'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 10, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(10) NOT NULL default ''"
		),  
		'ort' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['ort'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 40, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),  
		'strasse' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['strasse'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 40, 
				'tl_class'            => 'w50'
			),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),  
		'email' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['email'],
			'exclude'                 => true,
			'search'                  => false,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'maxlength'           => 40, 
				'tl_class'            => 'w50',
				'rgxp'                => 'email'
			),
			'sql'                     => "varchar(40) NOT NULL default ''"
		),  
		'adressdatum' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['adressdatum'],
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
				array('tl_dwz_ver', 'getDate')
			),
			'save_callback' => array
			(
				array('tl_dwz_ver', 'putDate')
			),
			'sql'                     => "int(8) unsigned NOT NULL default '0'"
		),
		'addImage' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['addImage'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('submitOnChange'=>true, 'tl_class'=>'clr'),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'singleSRC' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['singleSRC'],
			'exclude'                 => true,
			'inputType'               => 'fileTree',
			'eval'                    => array
			(
				'filesOnly'           => true, 
				'fieldType'           => 'radio',
				'mandatory'           => true,
				'tl_class'            => 'clr'
			),
			'sql'                     => "binary(16) NULL",
		),  
		'info' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['info'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('rte'=>'tinyMCE'),
			'explanation'             => 'insertTags', 
			'sql'                     => "text NULL"
		),
		'homepage' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['homepage'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array
			(
				'mandatory'           => false, 
				'rgxp'                => 'url', 
				'decodeEntities'      => true, 
				'maxlength'           => 255, 
				'tl_class'            => 'long clr'
			),
			'sql'                     => "varchar(255) NOT NULL default ''"
		), 
		'elobase' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['elobase'],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'eval'                    => array('tl_class' => 'w50','isBoolean' => true),
			'sql'                     => "char(1) NOT NULL default ''"
		),
		'published' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_dwz_ver']['published'],
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
	)
);


/**
 * Class tl_dwz_ver
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 * @copyright  Leo Feyer 2005-2014
 * @author     Leo Feyer <https://contao.org>
 * @package    News
 */
class tl_dwz_ver extends Backend
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
	public function listVereine($arrRow)
	{ 
		$temp = '<b>' . $arrRow['zpsver'] . '</b> ';
		($arrRow['status'] == 'A') ? $temp .= '(A) ' : $temp .= '(L) ';
		$temp .= $arrRow['name'];
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
			if(substr($k, 0, 4) != 'tdv_')
			{
				continue;
			}

			// Filter zurücksetzen
			if($k == \Input::post($k))
			{
				unset($session['filter']['tl_dwz_verFilter'][$k]);
			}
			// Filter zuweisen
			else
			{
				$session['filter']['tl_dwz_verFilter'][$k] = \Input::post($k);
			}
		}

		$this->Session->setData($session);

		if(\Input::get('id') > 0 || !isset($session['filter']['tl_dwz_verFilter']))
		{
			return;
		}

		$arrClubs = null;

		if(isset($session['filter']['tl_dwz_verFilter']['tdv_filter']))
		{
			switch($session['filter']['tl_dwz_verFilter']['tdv_filter'])
			{
				case '1': // Alle Vereine mit Homepage-Link
					$objClubs = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_ver WHERE homepage != ?")
					                                    ->execute('');
					$arrClubs = is_array($arrClubs) ? array_intersect($arrClubs, $objClubs->fetchEach('id')) : $objClubs->fetchEach('id');
					break;

				case '2': // Alle Vereine ohne Homepage-Link
					$objClubs = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_ver WHERE homepage = ?")
					                                    ->execute('');
					$arrClubs = is_array($arrClubs) ? array_intersect($arrClubs, $objClubs->fetchEach('id')) : $objClubs->fetchEach('id');
					break;

				case '3': // Alle Vereine mit Kurzporträt
					$objClubs = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_ver WHERE info != ?")
					                                    ->execute('');
					$arrClubs = is_array($arrClubs) ? array_intersect($arrClubs, $objClubs->fetchEach('id')) : $objClubs->fetchEach('id');
					break;

				case '4': // Alle Vereine ohne Kurzporträt
					$objClubs = \Database::getInstance()->prepare("SELECT * FROM tl_dwz_ver WHERE info IS ?")
					                                    ->execute(NULL);
					$arrClubs = is_array($arrClubs) ? array_intersect($arrClubs, $objClubs->fetchEach('id')) : $objClubs->fetchEach('id');
					break;

				default:

			}
		}

		if(is_array($arrClubs) && empty($arrClubs))
		{
			$arrClubs = array(0);
		}

		$GLOBALS['TL_DCA']['tl_dwz_ver']['list']['sorting']['root'] = $arrClubs;

	}

	public function generateAdvancedFilter(DataContainer $dc)
	{

		if(\Input::get('id') > 0) return '';

		$session = \Session::getInstance()->getData();

		// Filters
		$arrFilters = array
		(
			'tdv_filter'   => array
			(
				'name'    => 'tdv_filter',
				'label'   => $GLOBALS['TL_LANG']['tl_dwz_ver']['filter_extended'],
				'options' => array
				(
					'1'   => $GLOBALS['TL_LANG']['tl_dwz_ver']['filter_active_homepage'],
					'2'   => $GLOBALS['TL_LANG']['tl_dwz_ver']['filter_without_homepage'],
					'3'   => $GLOBALS['TL_LANG']['tl_dwz_ver']['filter_active_portrait'],
					'4'   => $GLOBALS['TL_LANG']['tl_dwz_ver']['filter_without_portrait'],
				)
			),
		);

		$strBuffer = '<div class="tl_filter tdv_filter tl_subpanel"><strong>' . $GLOBALS['TL_LANG']['tl_dwz_ver']['filter'] . ':</strong> ' . "\n";

		// Generate filters
		foreach ($arrFilters as $arrFilter)
		{
			$strOptions = '<option value="' . $arrFilter['name'] . '">' . $arrFilter['label'] . '</option>' . "\n";
			$strOptions .= '<option value="' . $arrFilter['name'] . '">---</option>' . "\n";

			// Prüfen ob ein Filter gesetzt ist
			if(isset($session['filter']['tl_dwz_verFilter']['tdv_filter']))
			{
				$filterwert = $session['filter']['tl_dwz_verFilter']['tdv_filter'];
			}
			else $filterwert = '';

			// Generate options
			foreach($arrFilter['options'] as $k => $v)
			{
				$strOptions .= '<option value="' . $k . '"' . (((string)$filterwert === (string)$k) ? ' selected' : '') . '>' . $v . '</option>' . "\n";
			}

		}

		$strBuffer .= '<select name="' . $arrFilter['name'] . '" id="' . $arrFilter['name'] . '" class="tl_select' . (isset($session['filter']['tl_dwz_verFilter'][$arrFilter['name']]) ? ' active' : '') . '">'.$strOptions.'</select>' . "\n";
		
		return $strBuffer . '</div>';

	}

}
