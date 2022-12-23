define([
    'Magento_Customer/js/customer-data',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (customerData, maskedIdModel) {
    'use strict';

    return function (paymentMethods) {
        if (paymentMethods.adyen_payment_methods) {

            const methods = {
                payment_methods: {
                    paymentMethodsExtraDetails: paymentMethods.adyen_payment_methods.extra_details,
                    paymentMethodsResponse: paymentMethods.adyen_payment_methods.methods_response
                }
            };

            customerData.getInitCustomerData().then(function () {
                customerData.set('adyen-express-pdp', methods);
            });
        }

        if (paymentMethods.masked_quote_id) {
            maskedIdModel().setMaskedId(paymentMethods.masked_quote_id);
        }
    };
});
