define(function () {
    'use strict';

    return function (countires) {
        const countryDirectory = {};

        countires.forEach(function (country) {
            countryDirectory[country.two_letter_abbreviation] = {};
            if (typeof country.available_regions !== 'undefined') {
                country.available_regions.forEach(function (region) {
                    const regionName = region.name.toLowerCase().replace(/[^A-Z0-9]/ig, '');

                    countryDirectory[country.two_letter_abbreviation][regionName] = region.id;
                });
            }
        });
        return countryDirectory;
    };
});
