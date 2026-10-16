define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getApiUrl'
], function (storage, getApiUrl) {
    'use strict';

    return function (isProductView) {
        return new Promise(function (resolve, reject) {
            storage.get(
                getApiUrl('totals', isProductView),
                false
            ).done(resolve).fail(reject);
        });
    };
});
