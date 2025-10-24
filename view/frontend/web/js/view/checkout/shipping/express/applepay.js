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
        'uiComponent'
    ],
    function(
        ko,
        Component
    ) {
        return Component.extend({
            defaults: {
                template: 'Adyen_ExpressCheckout/checkout/shipping/express',
                salutation: ko.observable()
            },

            initialize: function () {
                this._super();

                console.log("Apple Pay express layout on the shipping page has been rendered!");
                this.salutation('Hello World: Apple Pay')
            }
        });
    }
);
