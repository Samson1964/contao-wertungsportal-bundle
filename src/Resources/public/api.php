<?php
/**
 * Contao Open Source CMS, Copyright (C) 2005-2013 Leo Feyer
 *
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
use Contao\Controller;

/**
 * Initialize the system
 */
define('TL_MODE', 'FE');
define('TL_SCRIPT', 'bundles/contaowertungsportal/api.php');
require($_SERVER['DOCUMENT_ROOT'].'/../system/initialize.php');

/**
 * Class Wertungsportal
 *
 */
class Wertungsportal
{

	public function __construct()
	{
	}

	public function run()
	{
		$id = intval(\Input::get('id'));
		$option = \Input::get('option');

	}

}

/**
 * Instantiate controller
 */
$objWertungsportal = new Wertungsportal();
$objWertungsportal->run();
