define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';
    return Component.extend({
        defaults: {
            maskedId: ko.observable('').extend({notify: 'always'})
        },

        getMaskedId: function () {
            return this.maskedId();
        },

        setMaskedId: function (maskedId) {
            return this.maskedId(maskedId);
        }
    });
});
