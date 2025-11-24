define([
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_Payment/js/model/adyen-configuration'
], function (
    configModel,
    adyenConfiguration
) {
    'use strict';

    return function (amount, currency) {
        const locale = configModel().getConfig().locale ?? adyenConfiguration.getLocale();
        const options = {
            style: 'currency',
            currency: currency
        };

        return new Intl.NumberFormat(locale.replace('_', '-'), options).format(amount);
    };
});
