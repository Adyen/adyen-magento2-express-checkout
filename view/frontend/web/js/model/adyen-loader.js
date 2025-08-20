/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    ['jquery'], function ($) {
        'use strict';
        return {
            startLoader: function () {
                var body = $('body').loader();
                body.loader('show');
            },
            stopLoader: function () {
                var body = $('body').loader();
                body.loader('hide');
            }
        };
    }
);
