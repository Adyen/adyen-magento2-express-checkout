define(function () {
    'use strict';

    return function () {
        // Provide in helper so themes can override.
        return [
            'visa',
            'masterCard',
            'amex',
            'discover',
            'maestro',
            'vPay',
            'jcb',
            'elo'
        ];
    };
});
