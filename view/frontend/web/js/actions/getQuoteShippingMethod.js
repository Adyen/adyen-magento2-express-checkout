define([
    'require',
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getApiUrl',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart'
], function (
    require,
    storage,
    getApiUrl,
    getIsLoggedIn,
    getMaskedIdFromCart
) {
    'use strict';

    const CHECKOUT_QUOTE_MODEL = 'Magento_Checkout/js/model/quote';
    const REQUEST_TIMEOUT_MS = 3000;

    /**
     * On the checkout and cart pages this model holds the shopper's latest choice, which may not be saved
     * to the quote yet. Elsewhere it is not loaded, or holds no shipping method.
     */
    function getCheckoutPageShippingMethod() {
        if (!require.defined(CHECKOUT_QUOTE_MODEL)) {
            return null;
        }

        const method = require(CHECKOUT_QUOTE_MODEL).shippingMethod();

        return method && method.carrier_code && method.method_code
            ? method.carrier_code + '_' + method.method_code
            : null;
    }

    function getSavedShippingMethod() {
        if (!getIsLoggedIn() && !getMaskedIdFromCart()) {
            return Promise.resolve(null);
        }

        const url = getApiUrl('', false);

        return new Promise(function (resolve) {
            const timeout = setTimeout(function () {
                resolve(null);
            }, REQUEST_TIMEOUT_MS);

            storage.get(url, false)
                .done(function (cart) {
                    const shippingAssignment = cart?.extension_attributes?.shipping_assignments?.[0];

                    resolve(shippingAssignment?.shipping?.method || null);
                })
                .fail(function () {
                    resolve(null);
                })
                .always(function () {
                    clearTimeout(timeout);
                });
        });
    }

    /**
     * Resolves with the shipping method selected for the shopper's cart, as "<carrier_code>_<method_code>",
     * or null. It never rejects, so a failed lookup only means the wallet sheet opens on its first method.
     *
     * @returns {Promise<?string>}
     */
    return function () {
        try {
            const checkoutPageShippingMethod = getCheckoutPageShippingMethod();

            if (checkoutPageShippingMethod) {
                return Promise.resolve(checkoutPageShippingMethod);
            }

            return getSavedShippingMethod();
        } catch (e) {
            console.error('Adyen Express: Unable to read the selected shipping method', e);

            return Promise.resolve(null);
        }
    };
});
