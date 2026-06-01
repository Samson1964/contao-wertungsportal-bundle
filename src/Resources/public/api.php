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
 * Class Wertungsportal_API
 *
 */
class Wertungsportal_API
{

	public function __construct()
	{
	}

	public function run()
	{
		
		try 
		{
			$portal = new \Schachbulle\ContaoWertungsportalBundle\Classes\Wertungsportal();
			
			$result = $portal->callApiWithRefresh('/dwz/dwzliste/clubs');
			
			echo "\n?? API-Antwort (HTTP {$result['http_code']}):\n";
			echo json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
		
		} 
		catch(RuntimeException $e) 
		{
			echo "\n? Fehler: " . $e->getMessage() . "\n";
			exit(1);
		}
	}

}

/**
 * Instantiate controller
 */
$objWertungsportal = new Wertungsportal_API();
$objWertungsportal->run();
