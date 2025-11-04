define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'ko'
], function (Component, customerData, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            isVirtual: ko.observable(false).extend({ notify: 'always' })
        },

        getIsVirtual: function () {
            return this.isVirtual();
        },

        /**
         * Ensures customer-data cart section is hydrated (items array exists).
         * Resolves with the latest cart payload.
         */
        _ensureCartReady: function () {
            var cartObs = customerData.get('cart');
            return new Promise(function (resolve) {
                function ready() {
                    var cart = cartObs() || {};
                    if (Array.isArray(cart.items)) {
                        resolve(cart);
                        return true;
                    }
                    return false;
                }

                if (ready()) {
                    return;
                }

                var sub = cartObs.subscribe(function () {
                    if (ready()) {
                        sub.dispose && sub.dispose();
                    }
                });

                // kick a refresh (true = force reload)
                customerData.reload(['cart'], true);

                // last-resort timeout so we never hang forever
                setTimeout(function () {
                    // resolve with empty items if still not ready
                    var cart = cartObs() || {};
                    if (!Array.isArray(cart.items)) {
                        cart.items = [];
                    }
                    resolve(cart);
                }, 3000);
            });
        },

        /**
         * Promise-based virtual flag setter.
         * @param {Boolean} isPdp
         * @param {Object|null} initExpressResponse
         * @returns {Promise<Boolean>}
         */
        setIsVirtual: function (isPdp, initExpressResponse) {
            var self = this;

            return new Promise(function (resolve) {
                if (isPdp && initExpressResponse && initExpressResponse.is_virtual_quote === true) {
                    self.isVirtual(true);
                    return resolve(true);
                }

                if (isPdp) {
                    // PDP but no flag present: assume non-virtual unless your PDP API says otherwise
                    self.isVirtual(false);
                    return resolve(false);
                }

                // Cart page: compute from cart items (async)
                self._ensureCartReady().then(function (cart) {
                    var items = Array.isArray(cart.items) ? cart.items : [];
                    var allVirtual = items.length > 0 && items.every(function (item) {
                        return item && (item.is_virtual === true || ['virtual', 'downloadable'].includes(item.product_type));
                    });

                    self.isVirtual(allVirtual);
                    resolve(allVirtual);

                    // keep it current after this first run
                    if (!self._subscribed) {
                        self._subscribed = true;
                        customerData.get('cart').subscribe(function () {
                            // fire and forget to refresh the observable
                            self.setIsVirtual(false, null);
                        });
                    }
                });
            });
        }
    });
});
