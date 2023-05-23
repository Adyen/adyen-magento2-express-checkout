define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getAdyenPaymentApiUrl'
], function (storage, getAdyenPaymentApiUrl) {
    'use strict';

    return function (payload) {
        return storage.post(
            getAdyenPaymentApiUrl('orders/payment-status'),
            JSON.stringify(payload),
            true
        );
    };
});

