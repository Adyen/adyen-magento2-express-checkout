define([
    'uiComponent',
    'ko',
    'Adyen_ExpressCheckout/js/actions/getCountries',
    'Adyen_ExpressCheckout/js/helpers/processCountries'
], function (Component, ko, getCountries, processCountries) {
    'use strict';

    return Component.extend({
        defaults: {
            countries: ko.observable({}).extend({notify: 'always'}),
            fetchingCountries: false // Variable to prevent multiple requests.
        },

        initialize: function () {
            this._super();
            if (!Object.keys(this.countries).length || !this.fetchingCountries) {
                this.fetchingCountries = true;
                getCountries()
                    .done(function (countries) {
                        const processedCountries = processCountries(countries);

                        this.setCountries(processedCountries);
                        this.fetchingCountries = false;
                    }.bind(this));
            }
        },

        getCountires: function () {
            return this.countries();
        },

        setCountries: function (countries) {
            return this.countries(countries);
        }
    });
});
