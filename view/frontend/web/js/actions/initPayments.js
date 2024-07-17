define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/model/config',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Magento_Customer/js/customer-data'
], function (
    storage,
    isLoggedIn,
    maskedIdModel,
    configModel,
    quote,
    urlBuilder,
    getMaskedIdFromCart,
    customerData
) {
    'use strict';

    function getGuestCartId() {
        const cartData = customerData.get('cart')();
        return cartData.guest_masked_id
            ? cartData.guest_masked_id
            : null;
    }

    function getCartId() {
        // This is just a placeholder. You might need to adjust this to properly get the cart ID for a logged-in user.
        // The implementation may vary depending on how you retrieve the cart ID in your Magento setup.
        return quote.getQuoteId();
    }

    return function (paymentData, isProductView) {
        const isGuest = !isLoggedIn();
        const config = configModel().getConfig();
        const adyenCartId = isGuest ? getGuestCartId() : getCartId();

        const urlPath = isGuest
            ? '/adyen/express/init-payments/guest'
            : '/adyen/express/init-payments/mine';

        const urlParams = {
            storeCode: config.storeCode
        };

        if (isGuest) {
            urlParams.adyenCartId = adyenCartId;
        }

        const url = urlBuilder.createUrl(urlPath, urlParams);
        paymentData.paymentMethod.subtype = "express";
        const payload = {
            stateData: JSON.stringify(paymentData),
            maskedQuoteId: adyenCartId
        };

        return new Promise(function (resolve, reject) {
            storage.post(
                url,
                JSON.stringify(payload)
            ).done(resolve).fail(reject);
        });
    };
});
