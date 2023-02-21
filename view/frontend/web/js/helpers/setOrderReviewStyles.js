define(['jquery'], function ($) {
    'use strict';

    return function (styles) {
        Object.keys(styles).forEach(function (prop) {
            $('.amazonpay_order_review_details').css(prop, styles[prop]);
        });
    };
});
