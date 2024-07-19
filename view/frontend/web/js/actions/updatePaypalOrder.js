define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Customer/js/model/customer',
    'Adyen_ExpressCheckout/js/helpers/formatAmount',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
], function (storage, urlBuilder, customer, formatAmount, configModel, getIsLoggedIn) {
    'use strict';

    function updateOrder(cartId, paymentData, shippingMethods, currency, selectedShippingMethod = null) {
        const isLoggedIn = getIsLoggedIn();
        const updateOrderUrl = isLoggedIn
            ? urlBuilder.createUrl('/adyen/express/paypal-update-order/mine', {})
            : urlBuilder.createUrl('/adyen/express/paypal-update-order/guest', {});

        const updateOrderPayload = {
            maskedQuoteId: cartId,
            paymentData: paymentData
        };

        let deliveryMethods = [];
        if (selectedShippingMethod) {
            let method = {
                reference: selectedShippingMethod.id,
                description: selectedShippingMethod.label,
                type: 'Shipping',
                amount: {
                    currency: currency,
                    value: Math.round(selectedShippingMethod.amount.value * 100)
                },
                selected: true
            };
            deliveryMethods.push(method);
            updateOrderPayload.deliveryMethods = JSON.stringify(deliveryMethods);
        }
        else {
            for (let i = 0; i < shippingMethods.length; i++) {
                let method = {
                    reference: (i + 1).toString(),
                    description: shippingMethods[i].detail,
                    type: 'Shipping',
                    amount: {
                        currency: currency,
                        value: Math.round(shippingMethods[i].amount * 100)
                    },
                    selected: i === 0
                };
                // Add method object to array.
                deliveryMethods.push(method);
            }
            updateOrderPayload.deliveryMethods = JSON.stringify(deliveryMethods);
        }

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