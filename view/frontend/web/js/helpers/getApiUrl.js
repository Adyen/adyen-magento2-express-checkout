define([
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (isLoggedIn, getMaskedIdFromCart, configModel, maskedIdModel) {
    'use strict';

    return function (uri, isProductView) {
        const maskedId = isProductView
            ? maskedIdModel().getMaskedId()
            : getMaskedIdFromCart();

        const config = configModel().getConfig();

        return isLoggedIn()
            ? 'rest/' + config.storeCode + '/V1/carts/mine/' + uri
            : 'rest/' + config.storeCode + '/V1/guest-carts/' + maskedId + '/' + uri;
    };
});
