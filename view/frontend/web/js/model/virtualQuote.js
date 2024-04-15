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
            } else if (!isPdp) {
                const cart = customerData.get('cart');
                isVirtual = true;

                cart().items.forEach((item) => {
                   if (!["virtual", "downloadable"].includes(item.product_type)) {
                       isVirtual = false;
                   }
                });
            }

            return this.isVirtual(isVirtual);
        }
    });
});
