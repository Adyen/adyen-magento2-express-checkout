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

        setIsVirtual: function (isPdp, initExpressResponse) {
            // PDP path: trust backend flag when provided
            if (isPdp) {
                var pdpVirtual = !!(initExpressResponse && initExpressResponse['is_virtual_quote']);
                this.isVirtual(pdpVirtual);
                return this.isVirtual();
            }

            // Cart page path: read customerData safely
            var cartGetter = customerData.get('cart');
            var state = (typeof cartGetter === 'function') ? cartGetter() : null;
            var items = (state && Array.isArray(state.items)) ? state.items : null;

            if (!items) {
                // While customer-data is refreshing, default to non-virtual (safer)
                this.isVirtual(false);
                return this.isVirtual();
            }

            // Compute: all items are virtual/downloadable → virtual quote
            var allVirtual = items.length > 0 && items.every(function (it) {
                return it && (it.product_type === 'virtual' || it.product_type === 'downloadable');
            });

            this.isVirtual(allVirtual);
            return this.isVirtual();
        }

    });
});
