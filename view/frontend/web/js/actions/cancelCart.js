define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (storage, getIsLoggedIn, maskedIdModel) {
    'use strict';

    return function (isProductView) {
        // If this is not a product view we can ignore this cancelation step.
        if (!isProductView) {
            return Promise.resolve();
        }

        const isLoggedIn = getIsLoggedIn();
        const url = isLoggedIn
            ? 'rest/V1/adyen/express/cancel/mine'
            : 'rest/V1/adyen/express/cancel/guest';
        const maskedQuoteId = maskedIdModel().getMaskedId();

        return storage.post(
            url,
            JSON.stringify({ maskedQuoteId })
        );
    };
});
