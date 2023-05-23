define([
    'Adyen_ExpressCheckout/js/model/config'
], function (configModel) {
    'use strict';

    return function (uri) {
        const config = configModel().getConfig();

        return 'rest/' + config.storeCode + '/V1/internal/adyen/' + uri;
    };
});
