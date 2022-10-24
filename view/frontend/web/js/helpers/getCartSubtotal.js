define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function () {
        const cartData = customerData.get('cart')();
        const total = cartData.subtotalAmount;

        return parseFloat(total);
    };
});
