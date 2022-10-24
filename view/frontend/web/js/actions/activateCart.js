define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (storage, getIsLoggedIn, getMaskedIdFromCart, maskedIdModel) {
    'use strict';

    return function (isProductView) {
        // If this is not a product view we can skip this activation step.
        if (!isProductView) {
            return Promise.resolve();
        }

        const isLoggedIn = getIsLoggedIn();
        const url = isLoggedIn
            ? 'rest/V1/adyen/express/activate/mine'
            : 'rest/V1/adyen/express/activate/guest';
        const params = {
            adyenMaskedQuoteId: maskedIdModel().getMaskedId()
        };

        if (!isLoggedIn) {
            params.currentMaskedQuoteId = getMaskedIdFromCart();
        }

        return storage.post(
            url,
            JSON.stringify(params)
        );
    };
});
