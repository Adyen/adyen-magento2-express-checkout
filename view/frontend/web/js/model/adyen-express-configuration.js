/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [],
    function() {
        'use strict';

        return {
            getCountryCode: function () {
                return window.checkoutConfig.payment.adyenExpress.countryCode;
            },
            getIsVirtual: function () {
                return window.checkoutConfig.payment.adyenExpress.quote.isVirtual;
            },
            getAmountValue: function () {
                return window.checkoutConfig.payment.adyenExpress.quote.amount.value;
            },
            getCurrency: function () {
                return window.checkoutConfig.payment.adyenExpress.quote.amount.currency;
            },
            getIsGooglePayEnabledOnShipping: function () {
                return window.checkoutConfig.payment.adyenExpress.googlepay.isEnabledOnShipping;
            },
            getIsApplePayEnabledOnShipping: function () {
                return window.checkoutConfig.payment.adyenExpress.applepay.isEnabledOnShipping;
            },
            getApplePayButtonColor: function () {
                return window.checkoutConfig.payment.adyenExpress.applepay.buttonColor;
            },
            getIsPayPalEnabledOnShipping: function () {
                return window.checkoutConfig.payment.adyenExpress.paypal.isEnabledOnShipping;
            },
            getPaymentMethodsResponse: function () {
                return window.checkoutConfig.payment.adyenExpress.paymentMethodsResponse;
            }
        };
    },
);
