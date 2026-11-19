define([], function () {
    'use strict';

    function isSelectable(method) {
        return typeof method.method_code === 'string' && method.available !== false;
    }

    /**
     * Picks the shipping method a wallet sheet should have selected: the one the shopper chose in the sheet,
     * then the one already selected on the quote (e.g. on the checkout page), then the first one.
     *
     * @param {Object[]} shippingMethods Magento shipping methods
     * @param {?string} sheetMethodCode method_code selected in the wallet sheet
     * @param {?string} quoteShippingMethod quote shipping method, "<carrier_code>_<method_code>"
     * @returns {Object|undefined}
     */
    return function (shippingMethods, sheetMethodCode, quoteShippingMethod) {
        return shippingMethods.find(function (method) {
            return isSelectable(method) && method.method_code === sheetMethodCode;
        }) || shippingMethods.find(function (method) {
            return isSelectable(method) && method.carrier_code + '_' + method.method_code === quoteShippingMethod;
        }) || shippingMethods[0];
    };
});
