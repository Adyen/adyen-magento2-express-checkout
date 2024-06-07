define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Customer/js/model/customer',
    'Adyen_ExpressCheckout/js/helpers/formatAmount',
    'Adyen_ExpressCheckout/js/model/config'
], function (storage, urlBuilder, customer, formatAmount, configModel) {
    'use strict';

    function updateOrder(cartId, paymentData, shippingMethods, currency) {
        const updateOrderUrl = customer.isLoggedIn()
            ? urlBuilder.createUrl('/adyen/express/paypal-update-order/mine', {})
            : urlBuilder.createUrl('/adyen/express/paypal-update-order/guest', {});

        const updateOrderPayload = {
            maskedQuoteId: cartId,
            paymentData: paymentData
        };
        const config = configModel().getConfig();

        let deliveryMethods = [];
        if (shippingMethods) {
            //updateOrderPayload.shippingMethods = shippingMethods.map(method => method.identifier);
            for (let i = 0; i < shippingMethods.length; i++) {
                let method = {
                    reference: i+1,
                    description: shippingMethods[i].carrier_title,
                    type: 'Shipping',
                    amount: {
                        currency: currency,
                        value: Math.round(shippingMethods[i].amount * 100)
                    },
                    selected: 'true'
                };
                // Add method object to array.
                deliveryMethods.push(method);
            }
            updateOrderPayload.deliveryMethods = JSON.stringify(deliveryMethods)
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
