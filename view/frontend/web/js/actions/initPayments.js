define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (
    storage,
    getIsLoggedIn,
    getMaskedIdFromCart,
    maskedIdModel
) {
    'use strict';

    return function (paymentData, isProductView) {
        let payload= {
            stateData: JSON.stringify(paymentData)
        };

        const url = getIsLoggedIn()
            ? 'rest/V1/adyen/express/init-payments/mine'
            : 'rest/V1/adyen/express/init-payments/guest';

        if (isProductView) {
            payload.adyenMaskedQuoteId = maskedIdModel().getMaskedId();
        } else {
            payload.guestMaskedId = getMaskedIdFromCart();
        }

        return new Promise(function (resolve, reject) {
            storage.post(
                url,
                JSON.stringify(payload)
            ).done(resolve).fail(reject);
        });
    };
});
