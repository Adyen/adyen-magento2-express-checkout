define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function (paymentType, isPdp) {
        function getPaymentMethodsFromAdyen() {
            const adyenPaymentMethods = customerData.get('adyen-express-pdp');
            const adyenMethods = adyenPaymentMethods();

            if (!adyenMethods.payment_methods
                || !adyenMethods.payment_methods.paymentMethodsResponse) {
                return null;
            }
            return adyenMethods.payment_methods.paymentMethodsResponse;
        }

        function getPaymentMethodsFromCart() {
            const adyenPaymentMethods = customerData.get('cart');
            const adyenMethods = adyenPaymentMethods()['adyen_payment_methods'];

            if (!adyenMethods.paymentMethodsResponse
                || !adyenMethods.paymentMethodsResponse.paymentMethods) {
                return null;
            }
            return adyenMethods.paymentMethodsResponse.paymentMethods;
        }

        function findPaymentMethod(paymentMethods, type) {
            return paymentMethods.find(function (paymentMethod) {
                return paymentMethod.type === type;
            });
        }

        return customerData.getInitCustomerData()
            .then(function () {
                const paymentMethods = isPdp
                    ? getPaymentMethodsFromAdyen()
                    : getPaymentMethodsFromCart();

                if (!paymentMethods) {
                    return null;
                }

                return findPaymentMethod(paymentMethods, paymentType);
            });

    };
});
