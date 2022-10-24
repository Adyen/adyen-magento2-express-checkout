define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function () {
        const cartData = customerData.get('cart')();

        return cartData.guest_masked_id
            ? cartData.guest_masked_id
            : null;
    };
});
