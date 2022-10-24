define(['Adyen_ExpressCheckout/js/model/config'], function (configModel) {
    'use strict';

    const config = configModel().getConfig();

    return function (amount) {
        return String(Number(amount).toFixed(config.format));
    };
});
