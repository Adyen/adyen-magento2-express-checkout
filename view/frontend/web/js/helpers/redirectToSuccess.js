define([
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/adyen-express-configuration'
], function (
    configModel,
    adyenExpressConfiguration
) {
    'use strict';

    return function () {
        document.location = configModel().getConfig().actionSuccess ?? adyenExpressConfiguration.getActionSuccess();
    };
});
