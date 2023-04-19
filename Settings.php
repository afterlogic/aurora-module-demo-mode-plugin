<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\DemoModePlugin;

use Aurora\System\SettingsProperty;
use Aurora\Modules\DemoModePlugin\Enums;

/**
 * @property bool $Disabled
 * @property Enums\DemoUserType $DemoUserType
 * @property string $DemoLogin
 * @property string $DemoRealPass
 * @property string $ApiUrl
 * @property string $PostProcessScript
 * @property string $PostProcessType
 * @property array $SampleContactData
 * @property bool $AdvancedMode
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "",
            ),
            "DemoUserType" => new SettingsProperty(
                Enums\DemoUserType::Mail,
                "spec",
                Enums\DemoUserType::class,
                "",
            ),
            "DemoLogin" => new SettingsProperty(
                "",
                "string",
                null,
                "",
            ),
            "DemoRealPass" => new SettingsProperty(
                "",
                "string",
                null,
                "",
            ),
            "ApiUrl" => new SettingsProperty(
                "",
                "string",
                null,
                "",
            ),
            "PostProcessScript" => new SettingsProperty(
                "",
                "string",
                null,
                "",
            ),
            "PostProcessType" => new SettingsProperty(
                "",
                "string",
                null,
                "",
            ),
            "SampleContactData" => new SettingsProperty(
                "",
                "array",
                null,
                "",
            ),
            "AdvancedMode" => new SettingsProperty(
                true,
                "bool",
                null,
                "",
            ),
        ];
    }
}
