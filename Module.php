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
	protected $bDemoUser = false;
	
	public function init() 
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser instanceof \CUser)
		{
			$aMatches = array();
			preg_match('/demo\d*@.+/', $oUser->PublicId, $aMatches, PREG_OFFSET_CAPTURE);
			if (count($aMatches) > 0)
			{
				$this->bDemoUser = true;
				$this->subscribeEvent('StandardAuth::UpdateAccount::before', array($this, 'onBeforeForbiddenAction'));
				$this->subscribeEvent('UpdateAutoresponder::before', array($this, 'onBeforeForbiddenAction'));
				$this->subscribeEvent('UpdateFilters::before', array($this, 'onBeforeForbiddenAction'));
				$this->subscribeEvent('UpdateForward::before', array($this, 'onBeforeForbiddenAction'));
				$this->subscribeEvent('SetupSystemFolders::before', array($this, 'onBeforeForbiddenAction'));
			}
		}
	}
	
	public function GetSettings()
	{
		return array(
			'IsDemoUser' => $this->bDemoUser,
		);
	}
	
	public function onBeforeForbiddenAction(&$aArgs, &$mResult)
	{
		throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::DemoAccount);
	}
}
