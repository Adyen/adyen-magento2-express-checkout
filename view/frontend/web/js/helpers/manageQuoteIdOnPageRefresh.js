define(function () {
    'use strict';

    return async function(){
        const navigationEntries = performance.getEntriesByType('navigation');
        const pageAccessedByReload = navigationEntries.length > 0 &&
            navigationEntries[0].type === 'reload';
        if(!pageAccessedByReload){
            localStorage.removeItem("quoteId");
        }
    };
});
