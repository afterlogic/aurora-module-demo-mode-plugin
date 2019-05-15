'use strict';

module.exports = function (oAppData) {
	var
		App = require('%PathToCoreWebclientModule%/js/App.js'),
		
		oSettings = oAppData['%ModuleName%'],
		bDemoUser = !!oSettings.IsDemoUser
	;
	
	if (App.isUserNormalOrTenant() && bDemoUser)
	{
		return {
			start: function (ModulesManager) {
				//No more needed. With separate demo account for each user, we can allow to use social authorization
				// App.subscribeEvent('OAuthAccountChange::before', function (oParams) {
					// Screens.showError(TextUtils.i18n('COREWEBCLIENT/INFO_DEMO_THIS_FEATURE_IS_DISABLED'));
					// oParams.AllowConnect = false;
					// oParams.AllowDisconnect = false;
				// });
			}
		};
	}
	
	return null;
};
