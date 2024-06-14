define([
    'uiComponent',
    'ko',
    'mage/storage',
    'Adyen_ExpressCheckout/js/helpers/getFormData',
    'Adyen_ExpressCheckout/js/helpers/getIsLoggedIn',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/helpers/getPdpForm',
    'Adyen_ExpressCheckout/js/model/maskedId'
], function (Component, ko, storage, getFormData, getIsLoggedIn, getMaskedIdFromCart, getPdpForm, maskedIdModel) {
    'use strict';

    return Component.extend({
        defaults: {
            request: ko.observable(null).extend({notify: 'always'})
        },

        initialize: function () {
            this._super();
        },

        getRequest: function (element) {
            const existingRequest = this.request();

            if (existingRequest) {
                return existingRequest;
            }

            const pdpForm = getPdpForm(element);
            const formData = getFormData(pdpForm);
            const cartMaskedId = getMaskedIdFromCart();

            const lastQuoteId = localStorage.getItem("lastQuoteId");
            // console.log(lastQuoteId);
            const adyenMaskedQuoteId = lastQuoteId != null ? lastQuoteId : maskedIdModel().getMaskedId();

            const payload = {
                productCartParams: {
                    product: formData['product'],
                    qty: formData['qty'],
                    super_attribute: formData['super_attribute']
                }
            };
            const url = getIsLoggedIn()
                ? 'rest/V1/adyen/express/init/mine'
                : 'rest/V1/adyen/express/init/guest';

            if (cartMaskedId) {
                payload.guestMaskedId = cartMaskedId;
            }

            if (adyenMaskedQuoteId) {
                payload.adyenMaskedQuoteId = adyenMaskedQuoteId;
            }

            const request = storage.post(
                url,
                JSON.stringify(payload)
            ).done(function (response) {
                this.request(null);
                return response;
            }.bind(this));

            this.request(request);

            return request;
        }
    });
});
