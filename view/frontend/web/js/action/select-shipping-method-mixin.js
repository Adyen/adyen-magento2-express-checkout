define([
    'jquery',
    'mage/utils/wrapper',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/helpers/getQuote'
], function ($, wrapper, setShippingInformation, getQuote) {
    'use strict';

    return function (selectShippingMethod) {
        return wrapper.wrap(selectShippingMethod, function (_super, shippingMethod) {

            if (shippingMethod) {
                console.log('shipping method before set to quote: ', shippingMethod);

                // update quote with selected shipping method
                window.checkoutConfig.quoteData.shipping_method = shippingMethod;

                debugger;

                getQuote(false).then(function (response) {
                    let {shipping_assignments} = response.extension_attributes,
                        shippingEmail,
                        shippingTelephone,
                        shippingFirstname,
                        shippingLastname,
                        shippingStreet,
                        shippingCity,
                        shippingRegion,
                        shippingRegionId,
                        shippingRegionCode,
                        shippingCountryId,
                        shippingPostcode;

                    shipping_assignments.forEach(function (element, index) {
                        let {
                            email,
                            telephone,
                            firstname,
                            lastname,
                            street,
                            city,
                            region,
                            region_id,
                            region_code,
                            country_id,
                            postcode
                        } = element.shipping.address;

                        shippingEmail = email;
                        shippingTelephone = telephone;
                        shippingFirstname = firstname;
                        shippingLastname = lastname;
                        shippingStreet = street;
                        shippingCity = city;
                        shippingRegion = region;
                        shippingRegionId = region_id;
                        shippingRegionCode = region_code;
                        shippingCountryId = country_id;
                        shippingPostcode = postcode;

                        console.log('element shipping address: ', element.shipping.address);
                    });

                    debugger;

                    console.log('newly selected shipping method code: ', shippingMethod['method_code']);

                    let payload = {
                        'addressInformation': {
                            'shipping_address': {
                                'email': shippingEmail,
                                'telephone': shippingTelephone,
                                'firstname': shippingFirstname,
                                'lastname': shippingLastname,
                                'street': shippingStreet,
                                'city': shippingCity,
                                'region': shippingRegion,
                                'region_id': shippingRegionId,
                                'region_code': shippingRegionCode,
                                'country_id': shippingCountryId,
                                'postcode': shippingPostcode,
                                'same_as_billing': 0,
                                'customer_address_id': 0,
                                'save_in_address_book': 0
                            },
                            'billing_address': {
                                'email': response.billing_address.email,
                                'telephone': response.billing_address.telephone,
                                'firstname': response.billing_address.firstname,
                                'lastname': response.billing_address.lastname,
                                'street': response.billing_address.street,
                                'city': response.billing_address.city,
                                'region': response.billing_address.region,
                                'region_id': response.billing_address.region_id,
                                'region_code': response.billing_address.region_code,
                                'country_id': response.billing_address.country_id,
                                'postcode': response.billing_address.postcode,
                                'same_as_billing': 0,
                                'customer_address_id': 0,
                                'save_in_address_book': 0
                            },
                            // update quote_address table with selected shipping method
                            'shipping_method_code': shippingMethod['method_code'],
                            'shipping_carrier_code': shippingMethod['carrier_code']
                        }
                    };

                    debugger;

                    setShippingInformation(payload, false);
                });


                // update checkout data with selected shipping method
                let result = _super(shippingMethod);

                if (shippingMethod) {
                    console.log('shipping method after set to quote: ', shippingMethod);

                    // update quote totals
                    window.checkoutConfig.totalsData = {
                        ...window.checkoutConfig.totalsData,
                        base_shipping_amount: shippingMethod['base_amount'],
                        base_shipping_tax_amount: shippingMethod['base_tax_amount'],
                        shipping_amount: shippingMethod['amount'],
                        shipping_tax_amount: shippingMethod['tax_amount'],
                        grand_total: parseFloat(window.checkoutConfig.totalsData.subtotal) + parseFloat(shippingMethod['amount']) + parseFloat(shippingMethod['tax_amount']),
                        base_grand_total: parseFloat(window.checkoutConfig.totalsData.base_subtotal) + parseFloat(shippingMethod['base_amount']) + parseFloat(shippingMethod['base_tax_amount'])
                    };
                }

                return result;
            }
        });
    };
});
