define([
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (storage, isLoggedIn, getMaskedIdFromCart, configModel, maskedIdModel) {
    'use strict';

    return function (payload, isProductView) {
        const maskedId = isProductView
            ? maskedIdModel().getMaskedId()
            : getMaskedIdFromCart();

        const config = configModel().getConfig();

        const internalUrl = isLoggedIn()
            ? 'rest/' + config.storeCode + '/V1/adyen/express/update-shipping-information/mine'
            : 'rest/' + config.storeCode + '/V1/adyen/express/update-shipping-information/guest'

        const requestPayload = {
            countryId: payload.address.countryId,
            region: payload.address.region,
            regionId: payload.address.regionId,
            postcode: payload.address.postcode,
            shippingDescription: payload.shipping_description,
            shippingMethodCode: payload.shipping_method_code,
            shippingAmount: payload.shipping_amount,
            maskedQuoteId: maskedId
        };

        return storage.post(
            internalUrl,
            JSON.stringify(requestPayload)
        );
    };
});
