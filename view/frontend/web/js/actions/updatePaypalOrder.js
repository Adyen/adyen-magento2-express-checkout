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
        if (selectedShippingMethod) {
            let method = {
                reference: selectedShippingMethod.id,
                description: selectedShippingMethod.label,
                type: 'Shipping',
                amount: {
                    currency: currency,
                    value: currencyHelper.formatAmount(
                        Math.round(selectedShippingMethod.amount.value),
                        currency
                    )
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
                        value: currencyHelper.formatAmount(
                            Math.round(shippingMethods[i].amount),
                            currency
                        )
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
