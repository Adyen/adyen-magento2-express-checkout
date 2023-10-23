define(['Adyen_ExpressCheckout/js/model/countries'], function (countriesModel) {
    'use strict';

    return function (countryCode, regionName, byRegionCode = false) {
        const countries = countriesModel().getCountires(byRegionCode);
        if (typeof regionName !== 'string') {
            return null;
        }
        if(!byRegionCode){
            regionName = regionName.toLowerCase().replace(/[^A-Z0-9]/ig, '');
        }
        if (typeof countries[countryCode] !== 'undefined'
            && typeof countries[countryCode][regionName] !== 'undefined') {
            return countries[countryCode][regionName];
        }
        return 0;
    };
});
