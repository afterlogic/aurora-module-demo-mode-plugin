<?php

class DemoModePluginModule extends AApiModule
{
	public function init() 
	{
		$this->subscribeEvent('StandardAuth::UpdateAccount::before', array($this, 'onBeforeUpdateAccount'));
	}
	
	public function onBeforeUpdateAccount(&$aArgs, &$mResult)
	{
		$oEavManager = \CApi::GetSystemManager('eav', 'db');
		$oAccount = $oEavManager->getEntity($aArgs['AccountId']);
		if (strpos($oAccount->Login, 'demo') !== false)
		{
			throw new \System\Exceptions\AuroraApiException(\System\Notifications::DemoAccount);
		}
	}
}
