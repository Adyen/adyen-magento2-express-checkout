/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define(
    [
        'jquery',
        'underscore',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
        'mage/storage'
    ],
    function(
        $,
        _,
        quote,
        urlBuilder,
        getIsLoggedIn,
        storage
    ){
        'use strict';

        function paymentDetails(data, orderId, isMultishipping = false, quoteId = null) {
            let serviceUrl;
            let payload = {
                'payload': JSON.stringify(data),
                'orderId': orderId
            };
            const isLoggedIn = getIsLoggedIn();
            if (isLoggedIn || isMultishipping) {
                serviceUrl = urlBuilder.createUrl(
                    '/adyen/carts/mine/payments-details',
                    {}
                );
            } else {
                serviceUrl = urlBuilder.createUrl(
                    '/adyen/guest-carts/:cartId/payments-details', {
                        cartId: quoteId ?? quote.getQuoteId()
                    }
                );
            }

            return storage.post(
                serviceUrl,
                JSON.stringify(payload),
                true
            );
        }
        return {
            paymentDetails: paymentDetails
        };
    }
);
