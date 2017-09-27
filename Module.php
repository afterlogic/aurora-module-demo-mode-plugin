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
	
	protected $bNewDemoUser = false;
	
	public function init() 
	{
		$this->subscribeEvent('Core::Login::before', array($this, 'onBeforeLogin'), 10);
		$this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'), 10);
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
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
	
	public function onBeforeLogin(&$aArgs, &$mResult)
	{
		$sDemoLogin = $this->getConfig('DemoLogin', '');
		$sDemoRealPass = $this->getConfig('DemoRealPass', '');
		$sApiUrl = $this->getConfig('ApiUrl', '');
		$sNewUserLogin = '';
		
		if ($sDemoLogin && $sDemoLogin === $aArgs['Login'] && $sApiUrl !== '')
		{
			$sDomain = preg_match("/.+@(localhost|.+\..+)/", $sDemoLogin, $matches) && isset($matches[1]) ? $matches[1] : '';
		
			$sNewUserLogin = @file_get_contents($sApiUrl.$sDomain);

			if ($sNewUserLogin) 
			{
				$sEmail = $sNewUserLogin."@".$sDomain;
				
				$aArgs['Login'] = $sEmail;
				$aArgs['Password'] = $sDemoRealPass;
				$this->bNewDemoUser = true;
			}
		}
	}
	
	public function onAfterLogin(&$aArgs, &$mResult)
	{
		if ($this->bNewDemoUser)
		{
			$this->populateInbox($aArgs);
			$this->populateContacts($aArgs, $mResult);
		}
	}
	
	public function populateContacts($aArgs, $mResult)
	{
		$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
		
		if ($oContactsDecorator)
		{
			//workaround for api get worked
			if (isset($mResult['AuthToken']))
			{
				\Aurora\System\Api::getAuthenticatedUserId($mResult['AuthToken']);
			}
				
			$oGroupResult = $oContactsDecorator->CreateGroup(array(
				'Name' => 'AfterLogic Support Team'
			));
			
			$aContactData = $this->getConfig('SampleContactData');
			
			if (!empty($aContactData))
			{
				if (isset($aContactData['PrimaryEmail']) && !is_numeric($aContactData['PrimaryEmail']))
				{
					$aContactData['PrimaryEmail'] = constant($aContactData['PrimaryEmail']);
				}
		
				if (is_array($aContactData))
				{
					if ($oGroupResult)
					{
						$aContactData['GroupUUIDs'] = array($oGroupResult);
					}

					$oContactsDecorator->CreateContact($aContactData);
				}
			}
			
			//Import of .vcf doesn't work properly because file should be uploaded to the server. It's impossible to import existing file.
//			$sampleContactPath = dirname(__FILE__).'\data\contact.vcf';
//			$sSampleContact = @file_get_contents(dirname(__FILE__).'\data\contact.vcf');
//			if (!empty($sSampleContact))
//			{
//				$aContactData = array(
//					'name' => 'contact.vcf',
//					'type' => 'text/x-vcard',
//					'tmp_name' => $sampleContactPath,
//					'error' => 0,
//					'size' => 796
//				);

//				$oContactsDecorator->Import($aContactData);
//			}
		}
	}
	
	public function populateInbox($aArgs)
	{
		$sResult = false;
		$result = preg_match("/(.+)@(?:localhost|.+\..+)/", $aArgs['Login'], $matches);
		
		if ($result && isset($matches[1]))
		{
			$sUserLogin = $matches[1];
			$sPostProcessScript = $this->getConfig('PostProcessScript', '');
			$sPostProcessType = $this->getConfig('PostProcessType', '');

			if (!empty($sPostProcessScript) && !empty($sPostProcessType))
			{
				$sResult = trim(shell_exec($sPostProcessScript. ' ' . $sPostProcessType . ' ' . $sUserLogin));
			}
		}
		
		return $sResult;
	}
}
