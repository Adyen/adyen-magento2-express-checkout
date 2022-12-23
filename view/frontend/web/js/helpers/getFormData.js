define(function () {
    'use strict';

    return function (form) {
        const object = {};
        const formData = new FormData(form);

        formData.forEach((value, key) => {
            // Special case for super attributes.
            if (key.includes('super_attribute')) {
                object['super_attribute'] = object['super_attribute'] || {};
                const test = /\[(.+)\]/.exec(key);

                if (test && test[1]) {
                    object['super_attribute'][test[1]] = parseInt(value, 10);
                }
                return;
            }

            if (!Reflect.has(object, key)) {
                object[key] = value;
                return;
            }

            if (!Array.isArray(object[key])) {
                object[key] = [object[key]];
            }
            object[key].push(value);
        });

        if (typeof object.product === 'string') {
            object.product = parseInt(object.product, 10);
        }

        return object;
    };
});
