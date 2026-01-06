define([
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/model/adyen-express-configuration'
], function (
    isLoggedIn,
    getMaskedIdFromCart,
    configModel,
    maskedIdModel,
    adyenExpressConfiguration
) {
    'use strict';

    return function (uri, isProductView) {
        const maskedId = isProductView
            ? maskedIdModel().getMaskedId()
            : getMaskedIdFromCart();

        const storeCode = configModel().getConfig().storeCode ?? adyenExpressConfiguration.getStoreCode();

        return isLoggedIn()
            ? 'rest/' + storeCode + '/V1/carts/mine/' + uri
            : 'rest/' + storeCode + '/V1/guest-carts/' + maskedId + '/' + uri;
    };
});
