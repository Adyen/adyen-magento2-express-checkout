define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_Payment/js/helper/currencyHelper'
], function (
    storage,
    urlBuilder,
    getIsLoggedIn,
    maskedIdModel,
    getMaskedIdFromCart,
    currencyHelper
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
                isSelected = true;
            }

            // Fallback logic for description
            let description =
                shippingMethods[i].detail?.trim() ||
                shippingMethods[i].label?.trim() ||
                shippingMethods[i].carrierCode

            let method = {
                reference: (i + 1).toString(),
                description: description,
                type: 'Shipping',
                amount: {
                    currency: currency,
                    value: currencyHelper.formatAmount(shippingMethods[i].amount, currency)
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
