define(['jquery',], function ($) {
    'use strict';

    return function (isProductView, element) {
        let currentPage = '';

        if (isProductView) {
            currentPage = 'pdp';
        } else if ($(element).closest('.minicart-wrapper').length > 0) {
            currentPage = 'minicart';
        } else if ($('.cart-container').length > 0) {
            currentPage = 'cart';
        } else if ($('body').hasClass('checkout-index-index')) {
            currentPage = 'checkout';
        }

        return currentPage;
    };
});
