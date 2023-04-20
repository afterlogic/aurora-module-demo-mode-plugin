<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DemoModePlugin;

use Aurora\Modules\Core\Module as CoreModule;
use Aurora\Modules\Mail\Classes\SieveFilter;
use Aurora\Modules\Mail\Models\MailAccount;
use Aurora\System\Exceptions\ApiException;
use MailSo\Mime\Email;
use MailSo\Mime\EmailCollection;

/**
 * Makes restriction of access to some functionality for demo users.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $bDemoUser = false;

    protected $bNewDemoUser = false;

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    public function init()
    {
        $this->aErrors = [
            Enums\ErrorCodes::CannotAddFilterToExternalEmail => $this->i18N('ERROR_CANNOT_ADD_FILTER_TO_EXTERNAL_EMAIL'),
        ];

        $this->subscribeEvent('Core::Login::before', array($this, 'onBeforeLogin'), 10);
        $this->subscribeEvent('Core::Login::after', array($this, 'onAfterLogin'), 10);
        $this->subscribeEvent('Core::GetDigestHash::after', array($this, 'onAfterGetDigestHash'), 10);

        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser instanceof \Aurora\Modules\Core\Models\User) {
            //			$aMatches = array();
            //			preg_match('/demo\d*@.+/', $oUser->PublicId, $aMatches, PREG_OFFSET_CAPTURE);

            $sDemoLogin = $this->oModuleSettings->DemoLogin;

            $sCurrentDomain = preg_match("/.+@(localhost|.+\..+)/", $oUser->PublicId, $matches) && isset($matches[1]) ? $matches[1] : '';
            $sDemoDomain = preg_match("/.+@(localhost|.+\..+)/", $sDemoLogin, $matches) && isset($matches[1]) ? $matches[1] : '';

            if ($this->CheckDemoUser($oUser->PublicId)) {
                $this->bDemoUser = true;
                $this->subscribeEvent('StandardAuth::UpdateAccount::before', array($this, 'onBeforeForbiddenAction'));
                $this->subscribeEvent('UpdateAutoresponder::before', array($this, 'onBeforeForbiddenAction'));
                //				$this->subscribeEvent('UpdateFilters::before', array($this, 'onBeforeForbiddenAction'));
                $this->subscribeEvent('UpdateForward::before', array($this, 'onBeforeForbiddenAction'));
                $this->subscribeEvent('SetupSystemFolders::before', array($this, 'onBeforeForbiddenAction'));
                $this->subscribeEvent('CreatePublicLink::before', array($this, 'onBeforeForbiddenAction'));
                $this->subscribeEvent('CreateFilterInstance', array($this, 'onCreateFilterInstance'));
            }
        }

        $this->denyMethodsCallByWebApi([
            'CheckDemoUser',
        ]);
    }

    public function CheckDemoUser($sPublicId)
    {
        $sDemoLogin = $this->oModuleSettings->DemoLogin;

        $sCurrentDomain = preg_match("/.+@(localhost|.+\..+)/", $sPublicId, $matches) && isset($matches[1]) ? $matches[1] : '';
        $sDemoDomain = preg_match("/.+@(localhost|.+\..+)/", $sDemoLogin, $matches) && isset($matches[1]) ? $matches[1] : '';

        return ($sCurrentDomain === $sDemoDomain);
    }

    public function onBeforeForbiddenAction(&$aArgs, &$mResult)
    {
        throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::DemoAccount);
    }

    public function onBeforeLogin(&$aArgs, &$mResult)
    {
        $DemoUserType = $this->oModuleSettings->DemoUserType;
        $sDemoLogin = $this->oModuleSettings->DemoLogin;

        $bAdvancedMode = $this->oModuleSettings->AdvancedMode;
        if ($bAdvancedMode) { # Advanced mode
            if ($sDemoLogin === $aArgs['Login']) {
                switch ($DemoUserType) {
                    case \Aurora\Modules\DemoModePlugin\Enums\DemoUserType::Mail:
                        $userCredentials = $this->createMailbox();
                        break;
                    case \Aurora\Modules\DemoModePlugin\Enums\DemoUserType::Db:
                        $userCredentials = $this->createDbUser();
                        break;
                }

                if (!empty($userCredentials)) {
                    $aArgs['Login'] = $userCredentials['login'];
                    $aArgs['Password'] = $userCredentials['password'];
                    $aArgs['NewDemoUser'] = true;
                    $this->bNewDemoUser = true;
                }
            } else {
                $aLogin = explode('@', $aArgs['Login']);
                $aDemoLogin = explode('@', $sDemoLogin);
                if (isset($aLogin[1], $aDemoLogin[1])) {
                    $sDomain = $aLogin[1];
                    $sDemoDomain = $aDemoLogin[1];
                    if ($sDomain === $sDemoDomain && $aArgs['Password'] === 'demo') {
                        $sDemoRealPass = $this->oModuleSettings->DemoRealPass;
                        $aArgs['Password'] = $sDemoRealPass;
                    }
                }
            }
        } else { # Simple mode
            if ($sDemoLogin === $aArgs['Login']) {
                $aArgs['Password'] = $this->oModuleSettings->DemoRealPass;
            }
        }
    }

    protected function createMailbox()
    {
        $result = null;
        $sDemoLogin = $this->oModuleSettings->DemoLogin;
        $sDemoRealPass = $this->oModuleSettings->DemoRealPass;
        $sApiUrl = $this->oModuleSettings->ApiUrl;
        $sNewUserLogin = '';

        if ($sDemoLogin && $sApiUrl !== '') {
            $sDomain = preg_match("/.+@(localhost|.+\..+)/", $sDemoLogin, $matches) && isset($matches[1]) ? $matches[1] : '';

            $sNewUserLogin = @file_get_contents($sApiUrl.$sDomain);

            if ($sNewUserLogin) {
                $sEmail = $sNewUserLogin."@".$sDomain;

                $result = array(
                    'login' => $sEmail,
                    'password' => $sDemoRealPass
                );
            }
        }

        return $result;
    }

    protected function createDbUser()
    {
        $result = null;
        $sDemoLogin = $this->oModuleSettings->DemoLogin;
        $sDemoRealPass = $this->oModuleSettings->DemoRealPass;
        $sDomain = preg_match("/.+@(localhost|.+\..+)/", $sDemoLogin, $matches) && isset($matches[1]) ? $matches[1] : '';
        $iDemoTenantId = false;

        $sLogin = 'user-'.base_convert(substr(str_pad(microtime(true)*100, 15, '0'), -11, 8), 10, 32).'@'.$sDomain;
        $sPassword = !empty($sDemoRealPass) ? $sDemoRealPass : substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890___---%%%$$$&&&'), 0, 20);

        \Aurora\Api::skipCheckUserRole(true);

        $oSettings =&\Aurora\System\Api::GetSettings();
        if ($oSettings->GetConf('EnableMultiTenant')) {
            $sDemoTenantName = 'Demo';
            $iDemoTenantId = \Aurora\Modules\Core\Module::Decorator()->GetTenantIdByName($sDemoTenantName);
            if (!$iDemoTenantId) {
                $iDemoTenantId = \Aurora\Modules\Core\Module::Decorator()->CreateTenant(0, $sDemoTenantName);
            }
        } else {
            $oTenant = CoreModule::Decorator()->GetDefaultGlobalTenant();
            if ($oTenant instanceof \Aurora\Modules\Core\Models\Tenant) {
                $iDemoTenantId = $oTenant->Id;
            }
        }

        $dbAccont = null;
        if ($iDemoTenantId) {
            $dbAccont = \Aurora\Modules\StandardAuth\Module::Decorator()->CreateAccount($iDemoTenantId, 0, $sLogin, $sPassword);
        }

        \Aurora\Api::skipCheckUserRole(false);

        if (isset($dbAccont) && isset($dbAccont['EntityId'])) {
            $result = array(
                'login' => $sLogin,
                'password' => $sPassword
            );
        }

        return $result;
    }

    public function onAfterLogin(&$aArgs, &$mResult)
    {
        if ($this->bNewDemoUser) {
            $this->populateInbox($aArgs);
            $this->populateContacts($aArgs);
        }
    }

    public function onAfterGetDigestHash(&$aArgs, &$mResult)
    {
        if ($this->CheckDemoUser($aArgs['Login'])) {
            $mResult = \md5($aArgs['Login'] . ':' . $aArgs['Realm'] . ':demo');
        }
    }

    public function onCreateFilterInstance($aArgs)
    {
        if ($this->IsDemoUser()) {
            $oAccount = $aArgs['Account'];
            if ($oAccount instanceof MailAccount) {
                $sEmail = $oAccount->Email;
                $sEmailDomain = Email::Parse($sEmail)->getDomain();
                if ($aArgs['Filter'] instanceof SieveFilter && !empty($aArgs['Filter']->Email)) {
                    $sFilterDomain = Email::Parse($aArgs['Filter']->Email)->getDomain();
                    if (strtolower($sEmailDomain) !== strtolower($sFilterDomain)) {
                        throw new ApiException(Enums\ErrorCodes::CannotAddFilterToExternalEmail);
                    }
                }
            }
        }
    }

    protected function populateContacts($aArgs)
    {
        $oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
        $sLogin = isset($aArgs['Login']) ? $aArgs['Login'] : '';
        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sLogin);

        if ($oContactsDecorator
            && $oUser instanceof \Aurora\Modules\Core\Models\User) {
            $oGroupResult = $oContactsDecorator->CreateGroup(
                ['Name' => 'Afterlogic Support Team'],
                $oUser->Id
            );

            $aContactData = $this->oModuleSettings->SampleContactData;

            if (!empty($aContactData)) {
                if (isset($aContactData['PrimaryEmail']) && !is_numeric($aContactData['PrimaryEmail'])) {
                    $aContactData['PrimaryEmail'] = constant($aContactData['PrimaryEmail']);
                }

                if (is_array($aContactData)) {
                    if ($oGroupResult) {
                        $aContactData['GroupUUIDs'] = array($oGroupResult);
                    }

                    $oContactsDecorator->CreateContact($aContactData, $oUser->Id);
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

    protected function populateInbox($aArgs)
    {
        $sResult = false;
        $result = preg_match("/(.+)@(?:localhost|.+\..+)/", $aArgs['Login'], $matches);

        if ($result && isset($matches[1])) {
            $sUserLogin = $matches[1];
            $sPostProcessScript = $this->oModuleSettings->PostProcessScript;
            $sPostProcessType = $this->oModuleSettings->PostProcessType;

            if (!empty($sPostProcessScript) && !empty($sPostProcessType)) {
                $sResult = trim(shell_exec($sPostProcessScript. ' ' . $sPostProcessType . ' ' . $sUserLogin));
            }
        }

        return $sResult;
    }

    /***** private functions *****/

    /***** public functions might be called with web API *****/

    public function IsDemoUser()
    {
        return $this->bDemoUser || $this->bNewDemoUser;
    }

    public function GetSettings()
    {
        return array(
            'IsDemoUser' => $this->IsDemoUser()
        );
    }

    /***** public functions might be called with web API *****/
}
