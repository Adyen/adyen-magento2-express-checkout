define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
], function (
    storage,
    urlBuilder,
    getIsLoggedIn,
    maskedIdModel,
    getMaskedIdFromCart
) {
    'use strict';

    function updateOrder(isProductView, paymentData, shippingMethods, currency, selectedShippingMethod = null) {
        let updateOrderUrl = getIsLoggedIn()
            ? urlBuilder.createUrl('/adyen/express/paypal-update-order/mine', {})
            : urlBuilder.createUrl('/adyen/express/paypal-update-order/guest', {});

        const updateOrderPayload = {
            paymentData: paymentData
        };

        if (isProductView) {
            updateOrderPayload.adyenMaskedQuoteId = maskedIdModel().getMaskedId();
        } else {
            updateOrderPayload.guestMaskedId = getMaskedIdFromCart();
        }

        let deliveryMethods = [];

        for (let i = 0; i < shippingMethods.length; i++) {
            let isSelected = false;

            if (!selectedShippingMethod && i === 0) {
                isSelected = true;
            } else if (selectedShippingMethod && selectedShippingMethod.label === shippingMethods[i].detail) {
                isSelected = true
            }

            let method = {
                reference: (i + 1).toString(),
                description: shippingMethods[i].detail,
                type: 'Shipping',
                amount: {
                    currency: currency,
                    value: Math.round(shippingMethods[i].amount * 100)
                },
                selected: isSelected
            };
            // Add method object to array.
            deliveryMethods.push(method);
        }

        updateOrderPayload.deliveryMethods = JSON.stringify(deliveryMethods);

        return storage.post(
            updateOrderUrl,
            JSON.stringify(updateOrderPayload)
        ).then(function (response) {
            return response;
        }).catch(function (error) {
            console.error('Failed to update PayPal order:', error);
            throw error;
        });
    }

    return {
        updateOrder: updateOrder
    };
});
