define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (storage, getIsLoggedIn, getMaskedIdFromCart, maskedIdModel) {
    'use strict';

    return function (isProductView) {
        const isLoggedIn = getIsLoggedIn();
        const url = isLoggedIn
            ? 'rest/V1/adyen/express/cancel/mine'
            : 'rest/V1/adyen/express/cancel/guest';

        const maskedQuoteId = isProductView
            ? maskedIdModel().getMaskedId()
            : getMaskedIdFromCart();

        if (!isLoggedIn && !maskedQuoteId) {
            return Promise.resolve();
        }

        return storage.post(
            url,
            JSON.stringify({ maskedQuoteId })
        );
    };
});
