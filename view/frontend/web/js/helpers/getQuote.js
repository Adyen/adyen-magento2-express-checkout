define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (storage, isLoggedIn, getMaskedIdFromCart, configModel, maskedIdModel) {
    'use strict';

    return function (isProductView) {
        const maskedId = isProductView
            ? maskedIdModel().getMaskedId()
            : getMaskedIdFromCart();

        const config = configModel().getConfig();

        let endpoint =  isLoggedIn()
            ? 'rest/' + config.storeCode + '/V1/carts/mine/'
            : 'rest/' + config.storeCode + '/V1/guest-carts/' + maskedId ;

        return storage.get(endpoint);
    };
});
