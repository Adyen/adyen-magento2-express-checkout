define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/model/config',
    'Magento_Checkout/js/model/url-builder',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart'
], function (
    storage,
    isLoggedIn,
    configModel,
    urlBuilder,
    getMaskedIdFromCart
) {
    'use strict';

    return function (paymentData, isProductView) {
        const config = configModel().getConfig();
        let payload;

        const urlPath = isLoggedIn()
            ? '/adyen/express/init-payments/mine'
            : '/adyen/express/init-payments/guest';

        const url = urlBuilder.createUrl(urlPath, {
            storeCode: config.storeCode
        });

        if (isLoggedIn()) {
            payload = {
                stateData: JSON.stringify(paymentData)
            };
        } else {
            payload = {
                maskedQuoteId: getMaskedIdFromCart(),
                stateData: JSON.stringify(paymentData)
            };
        }

        return new Promise(function (resolve, reject) {
            storage.post(
                url,
                JSON.stringify(payload)
            ).done(resolve).fail(reject);
        });
    };
});
