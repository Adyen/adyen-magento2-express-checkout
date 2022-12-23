define(['Adyen_ExpressCheckout/js/model/countries'], function (countriesModel) {
    'use strict';

    return function (countryCode, regionName) {
        const countries = countriesModel().getCountires();

        if (typeof regionName !== 'string') {
            return null;
        }
        regionName = regionName.toLowerCase().replace(/[^A-Z0-9]/ig, '');
        if (typeof countries[countryCode] !== 'undefined'
            && typeof countries[countryCode][regionName] !== 'undefined') {
            return countries[countryCode][regionName];
        }
        return 0;
    };
});
