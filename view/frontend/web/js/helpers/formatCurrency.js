define(['Adyen_ExpressCheckout/js/model/config'], function (configModel) {
    'use strict';

    return function (amount) {
        const config = configModel().getConfig();
        const locale = config.locale.replace('_', '-');
        const options = {
            style: 'currency',
            currency: config.currency
        };

        return new Intl.NumberFormat(locale, options).format(amount);
    };
});
