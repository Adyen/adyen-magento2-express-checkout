define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Customer/js/customer-data',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/helpers/getQuote'
    ],
    function (
        $,
        wrapper,
        customerData,
        setShippingInformation,
        getQuote
    ) {
    'use strict';

    const shippingMethodChangedEvent = 'Adyen_ExpressCheckout_Event:shippingMethodChanged';

    return function (selectShippingMethod) {
        return wrapper.wrap(selectShippingMethod, function (_super, shippingMethod) {

            if (shippingMethod) {
                // update quote with selected shipping method
                window.checkoutConfig.quoteData.shipping_method = shippingMethod;

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
                    });

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
                            'shipping_method_code': shippingMethod['method_code'],
                            'shipping_carrier_code': shippingMethod['carrier_code']
                        }
                    };

                    // update quote_address table with selected shipping method
                    setShippingInformation(payload, false).then(function () {
                        customerData.set(shippingMethodChangedEvent, shippingMethod['method_code']);
                    });
                });

                // update checkout data with selected shipping method
                let result = _super(shippingMethod);

                if (shippingMethod) {
                    // update quote data
                    window.checkoutConfig.quoteData.grand_total = parseFloat(window.checkoutConfig.totalsData.subtotal) + parseFloat(shippingMethod['amount']);
                }

                return result;
            }
        });
    };
});
