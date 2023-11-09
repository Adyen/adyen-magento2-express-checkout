/**
 * Copyright © 2023 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*jshint browser:true jquery:true*/
/*global alert*/
let config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/select-shipping-method': {
                'Adyen_ExpressCheckout/js/action/select-shipping-method-mixin': true
            }
        }
    }
};
