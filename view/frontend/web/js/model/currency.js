define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';
    return Component.extend({
        defaults: {
            currency: ko.observable(0).extend({notify: 'always'})
        },

        getCurrency: function () {
            return this.total();
        },

        setCurrency: function (currency) {
            return this.currency(currency);
        }
    });
});
