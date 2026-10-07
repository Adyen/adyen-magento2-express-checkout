define([
    'uiComponent',
    'ko',
    'Adyen_ExpressCheckout/js/helpers/processCountries'
], function (Component, ko, processCountries) {
    'use strict';

    return Component.extend({
        defaults: {
            countries: ko.observable([]).extend({notify: 'always'})
        },

        getCountries: function (byRegionCode = false) {
            return !!byRegionCode ?
                processCountries(this.countries(), byRegionCode) :
                processCountries(this.countries());
        },

        setCountries: function (countries) {
            return this.countries(countries);
        }
    });
});
