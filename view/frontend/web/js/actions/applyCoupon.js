define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getApiUrl'
], function (storage, getApiUrl) {
    'use strict';

    return function (couponCode, isProductView) {
        return new Promise(function (resolve, reject) {
            storage.put(
                getApiUrl('coupons/' + encodeURIComponent(couponCode), isProductView),
                {},
                false
            ).done(resolve).fail(reject);
        });
    };
});
