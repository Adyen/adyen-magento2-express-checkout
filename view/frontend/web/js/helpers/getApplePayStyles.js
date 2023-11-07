define([
    'Adyen_ExpressCheckout/js/model/config',
], function (configModel) {
    'use strict';

    return function () {
        const config = configModel().getConfig();

        // Default styles that can be overridden by themes.
        return {
            buttonType: 'plain',
            buttonColor: config?.buttonColor ?? 'black'
        };
    };
});
