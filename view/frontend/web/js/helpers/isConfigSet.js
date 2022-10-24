define(function () {
    'use strict';

    return function (paymentMethod, configurations = []) {
        if (!paymentMethod || typeof paymentMethod.configuration === 'undefined') {
            return false;
        }

        const configurationKeys = Object.keys(paymentMethod.configuration);

        return configurations.every(function (configuration) {
            return configurationKeys.includes(configuration);
        });
    };
});
