define([
    'Adyen_ExpressCheckout/js/model/config',
], function (config) {
    'use strict';

    return function () {
        // Default styles that can be overridden by themes.
        return {
            buttonType: 'plain',
            buttonColor: config?.buttonColor ?? 'black'
        };
    };
});
