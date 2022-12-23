define(['Adyen_ExpressCheckout/js/model/config'], function (configModel) {
    'use strict';

    return function (amount, currency) {
        const config = configModel().getConfig();
        const locale = config.locale.replace('_', '-');
        const options = {
            style: 'currency',
            currency: currency
        };

        return new Intl.NumberFormat(locale, options).format(amount);
    };
});
