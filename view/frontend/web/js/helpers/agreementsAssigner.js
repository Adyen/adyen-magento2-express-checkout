define([
    'Adyen_ExpressCheckout/js/model/config',
], function (configModel) {
    'use strict';

    return function (paymentData) {
        const config = configModel().getConfig();
        const agreementIds = config?.agreementIds ?? null;

        if (Array.isArray(agreementIds) && agreementIds.length > 0) {
            if (paymentData['extension_attributes'] === undefined) {
                paymentData['extension_attributes'] = {};
            }

            paymentData['extension_attributes']['agreement_ids'] = agreementIds;
        }
        return paymentData;
    };
});
