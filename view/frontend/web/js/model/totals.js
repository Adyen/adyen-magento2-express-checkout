define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';
    return Component.extend({
        defaults: {
            total: ko.observable(0).extend({notify: 'always'})
        },

        getTotal: function () {
            return this.total();
        },

        setTotal: function (price) {
            return this.total(price);
        }
    });
});
