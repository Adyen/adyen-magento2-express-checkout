define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';
    return Component.extend({
        defaults: {
            hasAdded: ko.observable(false).extend({notify: 'always'})
        },

        getHasAdded: function () {
            return this.hasAdded();
        },

        setHasAdded: function (hasAdded) {
            return this.hasAdded(hasAdded);
        }
    });
});
