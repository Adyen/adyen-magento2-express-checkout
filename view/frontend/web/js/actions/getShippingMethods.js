define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getApiUrl'
], function (storage, getApiUrl) {
    'use strict';

    return function (payload, isProductView) {

        return new Promise(function (resolve, reject) {
            storage.post(
                getApiUrl('estimate-shipping-methods', isProductView),
                JSON.stringify(payload)
            ).done(resolve).fail(reject);
        });
    };
});
