define([
    'Adyen_ExpressCheckout/js/helpers/getPreferredShippingMethod'
], function (getPreferredShippingMethod) {
    'use strict';

    /**
     * Moves the preferred shipping method to the front, as Apple Pay and PayPal select the first one.
     *
     * @see Adyen_ExpressCheckout/js/helpers/getPreferredShippingMethod
     * @param {Object[]} shippingMethods Magento shipping methods
     * @param {?string} sheetMethodCode method_code selected in the wallet sheet
     * @param {?string} quoteShippingMethod quote shipping method, "<carrier_code>_<method_code>"
     * @returns {Object[]}
     */
    return function (shippingMethods, sheetMethodCode, quoteShippingMethod) {
        const preferred = getPreferredShippingMethod(shippingMethods, sheetMethodCode, quoteShippingMethod);

        if (!preferred) {
            return shippingMethods;
        }

        return [preferred].concat(shippingMethods.filter(function (method) {
            return method !== preferred;
        }));
    };
});
