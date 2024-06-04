define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Customer/js/model/customer'
], function (storage, urlBuilder, customer) {
    'use strict';

    function updateOrder(cartId, paymentData, deliveryMethods) {
        const updateOrderUrl = customer.isLoggedIn()
            ? urlBuilder.createUrl('/adyen/express/paypal-update-order/mine', {})
            : urlBuilder.createUrl('/adyen/express/paypal-update-order/guest', {});

        const updateOrderPayload = {
            maskedQuoteId: cartId,
            paymentData: paymentData,
            deliveryMethods: deliveryMethods
        };

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
