define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    return function (selectShippingMethod) {
        return wrapper.wrap(selectShippingMethod, function (_super, shippingMethod) {
            debugger;
            if (shippingMethod) {
                console.log('shipping method before set to quote: ', shippingMethod)
            }

            _super(shippingMethod);

            debugger;
            if (shippingMethod) {
                console.log('shipping method after set to quote: ', shippingMethod)
            }
        });
    };
});
