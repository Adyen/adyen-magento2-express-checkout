define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';
    return Component.extend({
        defaults: {
            config: ko.observable({}).extend({notify: 'always'})
        },

        getConfig: function () {
            return this.config();
        },

        setConfig: function (config) {
            return this.config(config);
        }
    });
});
