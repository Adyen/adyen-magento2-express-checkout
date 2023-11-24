define(function () {
    'use strict';

    return function (countires, byRegionCode) {
        const countryDirectory = {};

        countires.forEach(function (country) {
            countryDirectory[country.two_letter_abbreviation] = {};
            if (typeof country.available_regions !== 'undefined') {
                country.available_regions.forEach(function (region) {
                    const regionName = region.name.toLowerCase().replace(/[^A-Z0-9]/ig, '');
                    const regionCode = region.code;

                    if(!!byRegionCode){
                        countryDirectory[country.two_letter_abbreviation][regionCode] = region.id;
                    }
                    else{
                        countryDirectory[country.two_letter_abbreviation][regionName] = region.id;
                    }
                });
            }
        });
        return countryDirectory;
    };
});
