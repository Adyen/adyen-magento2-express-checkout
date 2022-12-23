define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function () {
        const customer = customerData.get('customer')();

        return customer && customer.firstname;
    };
});
