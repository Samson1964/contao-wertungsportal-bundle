<?php
namespace Schachbulle\ContaoWertungsportalBundle\Models;

use Contao\Model;

/**
 * add properties for IDE support
 * 
 * @property string $hash
 */
class DwzVerModel extends \Model
{
	protected static $strTable = 'tl_dwz_ver';
	
	// if you have logic you need more often, you can implement it here
	public function setHash()
	{
		$this->hash = md5($this->id);
	}
}
