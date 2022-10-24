define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function (paymentType) {

        function getPaymentMethod(paymentMethods, type) {
            if (!paymentMethods.payment_methods
                || !paymentMethods.payment_methods.paymentMethodsResponse) {
                return null;
            }

            return paymentMethods.payment_methods.paymentMethodsResponse.find(function (paymentMethod) {
                return paymentMethod.type === type;
            });
        }

        return customerData.getInitCustomerData()
            .then(function () {
                const adyenPaymentMethods = customerData.get('adyen-express-pdp');
                const paymentMethods = adyenPaymentMethods();

                return getPaymentMethod(paymentMethods, paymentType);
            });

    };
});
