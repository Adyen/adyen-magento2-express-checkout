define([
    'jquery',
    'mage/utils/wrapper',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
], function ($, wrapper, setShippingInformation) {
    'use strict';

    return function (selectShippingMethod) {
        return wrapper.wrap(selectShippingMethod, function (_super, shippingMethod) {

            if (shippingMethod) {
                console.log('shipping method before set to quote: ', shippingMethod);

                // update quote with selected shipping method
                window.checkoutConfig.quoteData.shipping_method = shippingMethod;

                // update quote_address table with selected shipping method
                var payload = {
                    'addressInformation': {
                        // TODO add the shipping and billing address from window.checkoutConfig
                        'shipping_method_code': shippingMethod['method_code'],
                        'shipping_carrier_code': shippingMethod['carrier_code']
                    }
                };

                setShippingInformation(payload, false);
            }

            // update checkout data with selected shipping method
            let result = _super(shippingMethod);

            if (shippingMethod) {
                console.log('shipping method after set to quote: ', shippingMethod);

                // update quote totals
                window.checkoutConfig.totalsData = {
                    ...window.checkoutConfig.totalsData,
                    base_shipping_amount: shippingMethod['base_amount'],
                    base_shipping_tax_amount: shippingMethod['base_tax_amount'],
                    shipping_amount: shippingMethod['amount'],
                    shipping_tax_amount: shippingMethod['tax_amount'],
                    grand_total: parseFloat(window.checkoutConfig.totalsData.subtotal) + parseFloat(shippingMethod['amount']) + parseFloat(shippingMethod['tax_amount']),
                    base_grand_total: parseFloat(window.checkoutConfig.totalsData.base_subtotal) + parseFloat(shippingMethod['base_amount']) + parseFloat(shippingMethod['base_tax_amount'])
                };
            }

            return result;
        });
    };
});
