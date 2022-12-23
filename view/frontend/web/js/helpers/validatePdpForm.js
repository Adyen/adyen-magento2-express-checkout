define(['jquery'], function ($) {
    'use strict';

    return function (resolve, reject, form, clearError) {
        if (!form || !form.length) {
            resolve();
        }

        const isValid = $(form).valid();

        if (clearError) {
            $(form).validation('clearError');
        }

        isValid ? resolve() : reject('PDP form is not valid');
    };
});
