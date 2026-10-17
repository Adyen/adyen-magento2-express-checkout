define(function () {
    'use strict';

    return function () {
        let agreements = null;

        /**
         * Only in cart and checkout context.
         * @type {{mode: string, agreementId: string}[]|undefined} checkoutAgreements
         */
        const checkoutAgreements = window.checkoutConfig?.checkoutAgreements?.agreements;
        if (checkoutAgreements && Array.isArray(checkoutAgreements)) {
            const requireAgreements = checkoutAgreements.filter((e) => e.mode == '1');
            agreements = requireAgreements.map((e) => e.agreementId)
        }

        if (!Array.isArray(agreements)) {
            agreements = window.checkout?.agreementIds;
        }

        return Array.isArray(agreements) ? agreements : [];
    };
});
