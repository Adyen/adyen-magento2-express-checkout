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
    [
        'ko',
        'uiComponent',
        'Adyen_ExpressCheckout/js/model/adyen-express-configuration'
    ],
    function(
        ko,
        Component,
        adyenExpressConfiguration
    ) {
        return Component.extend({
            defaults: {
                template: 'Adyen_ExpressCheckout/checkout/shipping/express',
                componentRootNode: 'adyen-express-checkout__paypal'
            },

            initObservable: function() {
                this._super().observe([
                    'isAvailable',
                    'isPlaceOrderActionAllowed'
                ]);

                return this;
            },

            initialize: function () {
                this._super();
                this.isAvailable(adyenExpressConfiguration.getIsPayPalEnabledOnShipping());
            },

            getComponentRootNoteId: function () {
                return this.componentRootNode;
            },

            buildPaymentMethodComponent: function () {
                console.log("PayPal express layout on the shipping page has been rendered!");
            }
        });
    }
);
