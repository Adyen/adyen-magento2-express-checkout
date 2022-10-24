define(['Adyen_ExpressCheckout/js/model/config'], function (configModel) {
    'use strict';

    return function () {
        const config = configModel().getConfig();

        document.location = config.actionSuccess;
    };
});
