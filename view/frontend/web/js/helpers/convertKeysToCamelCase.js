define(function () {
    'use strict';

    const toCamel = (s) => {
        return s.replace(/([-_][a-z])/ig, ($1) => {
            return $1.toUpperCase()
                .replace('-', '')
                .replace('_', '');
        });
    };

    return function (originalObject) {
        const convertedObject = {};

        Object.keys(originalObject).forEach((key) => {
            convertedObject[toCamel(key)] = originalObject[key];
        });

        return convertedObject;
    };
});
