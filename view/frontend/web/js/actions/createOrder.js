define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getApiUrl'
], function (storage, getApiUrl) {
    'use strict';
    return function (payload, isProductView) {
        return storage.put(
            getApiUrl('order', isProductView),
            payload,
            false
        ).then(function(response) {
            // Assuming response contains orderId
            return response;
        }).catch(function(response) {
            throw new Error('Failed to place order');
        });
    };
});
