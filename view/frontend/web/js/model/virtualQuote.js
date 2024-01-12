define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'ko'
], function (
    Component,
    customerData,
    ko
) {
    'use strict';
    return Component.extend({
        defaults: {
            isVirtual: ko.observable(0).extend({notify: 'always'})
        },

        getIsVirtual: function () {
            return this.isVirtual();
        },

        setIsVirtual: function (isPdp, initExpressResponse = null) {
            let isVirtual = false;

            if (isPdp && initExpressResponse['is_virtual_quote']) {
                isVirtual = true;
            } else {
                const cart = customerData.get('cart');
                isVirtual = cart().items.some(item => item.product_type == 'virtual');
            }

            return this.isVirtual(isVirtual);
        }
    });
});
