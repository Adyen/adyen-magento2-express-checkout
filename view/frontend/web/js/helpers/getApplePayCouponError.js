define(['mage/translate'], function ($t) {
    'use strict';

    return function (error) {
        const response = error && error.responseJSON;
        const hasParameters = response && response.parameters && Object.keys(response.parameters).length > 0;
        // Magento returns un-substituted placeholders (%1) when parameters are present.
        const message = response && response.message && !hasParameters
            ? response.message
            : $t('The coupon code isn\'t valid. Verify the code and try again.');

        return new ApplePayError('couponCodeInvalid', undefined, message);
    };
});
