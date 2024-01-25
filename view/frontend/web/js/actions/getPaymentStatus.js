define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart'
], function (
    storage,
    isLoggedIn,
    maskedIdModel,
    configModel,
    getMaskedIdFromCart
) {
    'use strict';

    return function (orderId, isProductView) {

        return new Promise(function (resolve, reject) {
            const maskedId = isProductView
                ? maskedIdModel().getMaskedId()
                : getMaskedIdFromCart();

            const config = configModel().getConfig();

            let url = isLoggedIn()
                ? 'rest/' + config.storeCode + '/V1/adyen/orders/carts/mine/payment-status'
                : 'rest/' + config.storeCode + '/V1/adyen/orders/guest-carts/' + maskedId + '/payment-status';

            let payload = {
                orderId: orderId
            }

            storage.post(
                url,
                JSON.stringify(payload)
            ).done(resolve).fail(reject);
        });
    };
});
