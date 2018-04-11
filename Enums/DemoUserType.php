<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 * 
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 */

namespace Aurora\Modules\DemoModePlugin\Enums;

class DemoUserType extends \Aurora\System\Enums\AbstractEnumeration
{
	const Mail = 1;
	const Db = 2;
	
	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Mail' => self::Mail,
		'Db' => self::Db
	);	
}
