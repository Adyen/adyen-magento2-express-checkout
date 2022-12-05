define([
    'Magento_Customer/js/customer-data',
    'Adyen_ExpressCheckout/js/helpers/convertKeysToCamelCase'
], function (customerData, convertKeysToCamelCase) {
    'use strict';

    return function (paymentType, isPdp) {
        function getPaymentMethodsFromAdyen() {
            const adyenPaymentMethods = customerData.get('adyen-express-pdp');
            const adyenMethods = adyenPaymentMethods();

            if (!adyenMethods || !adyenMethods.payment_methods
                || !adyenMethods.payment_methods.paymentMethodsResponse) {
                return null;
            }
            return adyenMethods.payment_methods.paymentMethodsResponse;
        }

        function getPaymentMethodsFromCart() {
            const adyenPaymentMethods = customerData.get('cart');
            const adyenMethods = adyenPaymentMethods()['adyen_payment_methods'];

            if (!adyenMethods || !adyenMethods.paymentMethodsResponse
                || !adyenMethods.paymentMethodsResponse.paymentMethods) {
                return null;
            }
            return adyenMethods.paymentMethodsResponse.paymentMethods;
        }

        function findPaymentMethod(paymentMethods, type) {
            let found = paymentMethods.find(function (paymentMethod) {
                return paymentMethod.type === type;
            });

            if (!found && 'googlepay' === type) {
                found = paymentMethods.find(function (paymentMethod) {
                    return paymentMethod.type === 'paywithgoogle';
                });
            }

            return found;
        }

        return customerData.getInitCustomerData()
            .then(function () {
                const paymentMethods = isPdp
                    ? getPaymentMethodsFromAdyen()
                    : getPaymentMethodsFromCart();

                if (!paymentMethods) {
                    return null;
                }

                const foundMethods = findPaymentMethod(paymentMethods, paymentType);

                if (!!foundMethods) {
                    foundMethods.configuration = convertKeysToCamelCase(foundMethods.configuration);
                    return foundMethods;
                }

                return null;
            });
    };
});
