<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DemoModePlugin;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init() 
	{
		$this->subscribeEvent('StandardAuth::UpdateAccount::before', array($this, 'onBeforeUpdateAccount'));
	}
	
	public function onBeforeUpdateAccount(&$aArgs, &$mResult)
	{
		$oEavManager = \Aurora\System\Api::GetSystemManager('eav', 'db');
		$oAccount = $oEavManager->getEntity($aArgs['AccountId']);
		if (strpos($oAccount->Login, 'demo') !== false)
		{
			throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::DemoAccount);
		}
	}
}
