/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'mage/translate',
        'Magento_Customer/js/customer-data',
        'Adyen_Payment/js/adyen',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_ExpressCheckout/js/model/adyen-express-configuration',
        'Adyen_ExpressCheckout/js/helpers/getApplePayStyles',
        'Adyen_ExpressCheckout/js/actions/getShippingMethods',
        'Adyen_ExpressCheckout/js/helpers/getRegionId',
        'Adyen_ExpressCheckout/js/actions/setShippingInformation',
        'Adyen_ExpressCheckout/js/actions/setBillingAddress',
        'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
        'Adyen_ExpressCheckout/js/helpers/getExtensionAttributes',
        'Adyen_ExpressCheckout/js/actions/createPayment',
        'Adyen_ExpressCheckout/js/model/adyen-loader',
        'Adyen_ExpressCheckout/js/helpers/redirectToSuccess',
        'Adyen_ExpressCheckout/js/helpers/getSupportedNetworks',
        'Adyen_Payment/js/helper/currencyHelper'
    ],
    function (
        $,
        ko,
        Component,
        $t,
        customerData,
        AdyenWeb,
        adyenConfiguration,
        adyenExpressConfiguration,
        getApplePayStyles,
        getShippingMethods,
        getRegionId,
        setShippingInformation,
        setBillingAddress,
        setTotalsInfo,
        getExtensionAttributes,
        createPayment,
        loader,
        redirectToSuccess,
        getSupportedNetworks,
        currencyHelper
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Adyen_ExpressCheckout/checkout/shipping/express',
                checkoutComponent: null,
                applePayComponent: null,
                componentRootNode: 'adyen-express-checkout__applepay',
                shippingMethods: {},
                shippingMethodsList: [],
                shippingAddress: null,
                shippingMethod: null,
                _applePayPostData: null
            },

            initObservable: function () {
                this._super().observe([
                    'isAvailable',
                    'isPlaceOrderActionAllowed'
                ]);

                return this;
            },

            initialize: function () {
                this._super();
                this.isAvailable(adyenExpressConfiguration.getIsApplePayEnabledOnShipping ?
                    adyenExpressConfiguration.getIsApplePayEnabledOnShipping() :
                    true
                );
            },

            /**
             * Utility – always return a valid amount string for Apple Pay line items
             */
            toAmountString: function (val) {
                const n = Number(val);
                return Number.isFinite(n) ? n.toFixed(2) : '0.00';
            },

            /**
             * Creates AdyenCheckout instance and Apple Pay component, then mounts it.
             */
            buildPaymentMethodComponent: async function () {
                const paymentMethodsResponse = adyenExpressConfiguration.getPaymentMethodsResponse();
                const adyenData = window.adyenData;
                const applePayStyles = getApplePayStyles();
                const isVirtual = adyenExpressConfiguration.getIsVirtual();

                if (!paymentMethodsResponse || !paymentMethodsResponse.paymentMethods) {
                    return;
                }

                const applePayMethod = paymentMethodsResponse.paymentMethods.find(function (paymentMethod) {
                    return paymentMethod.type === 'applepay';
                });

                if (!applePayMethod || !applePayMethod.configuration) {
                    return;
                }

                try {
                    this.checkoutComponent = await window.AdyenWeb.AdyenCheckout({
                        locale: adyenConfiguration.getLocale(),
                        countryCode: adyenExpressConfiguration.getCountryCode(),
                        clientKey: adyenConfiguration.getClientKey(),
                        environment: adyenConfiguration.getCheckoutEnvironment(),
                        analytics: {
                            analyticsData: {
                                applicationInfo: {
                                    merchantApplication: {
                                        name: adyenData['merchant-application-name'],
                                        version: adyenData['merchant-application-version']
                                    },
                                    externalPlatform: {
                                        name: adyenData['external-platform-name'],
                                        version: adyenData['external-platform-version']
                                    }
                                }
                            }
                        },
                        risk: {
                            enabled: false
                        }
                    });

                    const countryCode = adyenExpressConfiguration.getCountryCode() === 'UK'
                        ? 'GB'
                        : adyenExpressConfiguration.getCountryCode();

                    const currency = adyenExpressConfiguration.getCurrency();
                    const amountValue = adyenExpressConfiguration.getAmountValue();
                    const minorAmount = currencyHelper.formatAmount(amountValue, currency);

                    let configuration = {
                        countryCode: countryCode,
                        currencyCode: currency,
                        totalPriceLabel: applePayMethod.configuration.merchantName,
                        configuration: {
                            domainName: window.location.hostname,
                            merchantId: applePayMethod.configuration.merchantId,
                            merchantName: this.getMerchantName()
                        },
                        amount: {
                            value: minorAmount,
                            currency: currency
                        },
                        isExpress: true,
                        expressPage: 'shipping',
                        supportedNetworks: getSupportedNetworks(),
                        merchantCapabilities: ['supports3DS'],
                        requiredShippingContactFields: isVirtual ? [] : ['postalAddress', 'name', 'email', 'phone'],
                        requiredBillingContactFields: ['postalAddress', 'name'],
                        shippingMethods: [],
                        onAuthorized: this.handleOnAuthorized.bind(this),
                        onSubmit: (state, component, actions) => this.handleOnSubmit(state, component, actions),
                        onClick: function (resolve /*, reject */) {
                            resolve();
                        },
                        onError: this.handleOnError.bind(this),
                        ...applePayStyles
                    };

                    if (!isVirtual) {
                        configuration.onShippingContactSelected = this.handleOnShippingContactSelected.bind(this);
                        configuration.onShippingMethodSelected = this.handleOnShippingMethodSelected.bind(this);
                    }

                    this.applePayComponent = await window.AdyenWeb.createComponent(
                        'applepay',
                        this.checkoutComponent,
                        configuration
                    );

                    await this.applePayComponent.isAvailable();
                    this.applePayComponent.mount('#' + this.componentRootNode);
                } catch (e) {
                    console.error('Apple Pay unavailable on shipping page', e);
                }
            },

            /**
             * Shipping contact selected in Apple Pay sheet
             */
            handleOnShippingContactSelected: async function (resolve, reject, event) {
                const contact = event && event.shippingContact;

                if (!contact) {
                    this._onGetTotalsError('Missing shipping contact', reject);
                    return;
                }

                const amountValue = adyenExpressConfiguration.getAmountValue();

                const payload = {
                    address: {
                        city: contact.locality,
                        region: contact.administrativeArea,
                        country_id: (contact.countryCode || '').toUpperCase(),
                        postcode: contact.postalCode,
                        save_in_address_book: 0
                    }
                };

                this.shippingAddress = payload.address;

                try {
                    let result = await getShippingMethods(payload, false);

                    result = result.filter(function (method) {
                        return typeof method.method_code === 'string' && method.available !== false;
                    });

                    if (result.length === 0) {
                        console.info('No shipping methods available for current Apple Pay address.');
                        reject({
                            newTotal: {
                                label: this.getMerchantName(),
                                amount: this.toAmountString(amountValue)
                            },
                            errors: [new ApplePayError('addressUnserviceable')]
                        });
                        return;
                    }

                    const appleShippingMethods = [];
                    this.shippingMethods = {};
                    this.shippingMethodsList = [];

                    result.forEach((method, index) => {
                        const appleMethod = {
                            identifier: method.method_code,
                            label: method.method_title,
                            detail: method.carrier_title ? method.carrier_title : '',
                            amount: this.toAmountString(method.amount)
                        };

                        appleShippingMethods.push(appleMethod);
                        this.shippingMethods[method.method_code] = method;
                        this.shippingMethodsList.push(appleMethod);

                        if (!this.shippingMethod && index === 0) {
                            this.shippingMethod = method.method_code;
                        }
                    });

                    const regionId = getRegionId(
                        this.shippingAddress.country_id,
                        this.shippingAddress.region
                    );

                    const address = {
                        countryId: this.shippingAddress.country_id,
                        region: this.shippingAddress.region,
                        regionId: regionId,
                        regionCode: null,
                        postcode: this.shippingAddress.postcode
                    };

                    const totalsPayload = {
                        addressInformation: {
                            address: address,
                            shipping_method_code: result[0].method_code,
                            shipping_carrier_code: result[0].carrier_code
                        }
                    };

                    const shippingInformationPayload = {
                        addressInformation: {
                            shipping_address: address,
                            shipping_method_code: result[0].method_code,
                            shipping_carrier_code: result[0].carrier_code
                        }
                    };

                    await setShippingInformation(shippingInformationPayload, false);
                    const totalsResponse = await setTotalsInfo(totalsPayload, false);

                    this.afterSetTotalsInfo(totalsResponse, appleShippingMethods, resolve);
                } catch (e) {
                    this._onGetTotalsError(e, reject);
                }
            },

            /**
             * Shipping method changed in Apple Pay sheet
             */
            handleOnShippingMethodSelected: async function (resolve, reject, event) {
                let shippingMethod = event && event.shippingMethod;

                if (!shippingMethod || !this.shippingMethods || !this.shippingMethods[shippingMethod.identifier]) {
                    const firstKey = this.shippingMethods && Object.keys(this.shippingMethods)[0];
                    if (!firstKey) {
                        reject($t('No shipping methods available.'));
                        return;
                    }
                    shippingMethod = {
                        identifier: firstKey,
                        amount: this.shippingMethods[firstKey].amount
                    };
                }

                const mapping = this.shippingMethods[shippingMethod.identifier];

                const regionId = getRegionId(
                    this.shippingAddress.country_id,
                    this.shippingAddress.region
                );

                const address = {
                    countryId: this.shippingAddress.country_id,
                    region: this.shippingAddress.region,
                    regionId: regionId,
                    regionCode: null,
                    postcode: this.shippingAddress.postcode
                };

                const totalsPayload = {
                    addressInformation: {
                        address: address,
                        shipping_method_code: mapping.method_code,
                        shipping_carrier_code: mapping.carrier_code
                    }
                };

                const shippingInformationPayload = {
                    addressInformation: {
                        shipping_address: address,
                        shipping_method_code: mapping.method_code,
                        shipping_carrier_code: mapping.carrier_code
                    }
                };

                try {
                    await setShippingInformation(shippingInformationPayload, false);
                    const response = await setTotalsInfo(totalsPayload, false);

                    this.afterSetTotalsInfo(response, shippingMethod, resolve);
                } catch (e) {
                    this._onGetTotalsError(e, reject);
                }
            },

            /**
             * Build Apple Pay update object after fetching totals
             */
            afterSetTotalsInfo: function (response, shippingMethod, resolve) {
                const grandTotal = this.toAmountString(response && response.grand_total);
                const subtotal   = this.toAmountString(response && response.subtotal);
                const tax        = this.toAmountString(response && response.tax_amount);

                const update = {
                    newTotal: {
                        type: 'final',
                        label: this.getMerchantName(),
                        amount: grandTotal
                    },
                    newLineItems: [
                        {
                            type: 'final',
                            label: $t('Subtotal'),
                            amount: subtotal
                        }
                    ]
                };

                // Array → list of shipping methods
                if (Array.isArray(shippingMethod)) {
                    update.newShippingMethods = shippingMethod;
                    shippingMethod = shippingMethod[0];
                }

                const shippingAmount = this.toAmountString(shippingMethod && shippingMethod.amount);
                update.newLineItems.push({
                    type: 'final',
                    label: $t('Shipping'),
                    amount: shippingAmount
                });

                if (Number(tax) > 0) {
                    update.newLineItems.push({
                        type: 'final',
                        label: $t('Tax'),
                        amount: tax
                    });
                }

                if (shippingMethod && shippingMethod.identifier) {
                    this.shippingMethod = shippingMethod.identifier;
                }

                resolve(update);
            },

            /**
             * Called after shopper authorizes Apple Pay payment
             */
            handleOnAuthorized: async function (data, actions) {
                const isVirtual = adyenExpressConfiguration.getIsVirtual();
                const event = data && data.authorizedEvent;

                if (!event || !event.payment) {
                    console.error('Adyen ApplePay: authorizedEvent missing');
                    actions.reject();
                    return;
                }

                try {
                    const shippingContact = event.payment.shippingContact || {};
                    const billingContact  = event.payment.billingContact  || {};

                    const componentData = this.applePayComponent.data;
                    const payload = {
                        email: shippingContact.emailAddress,
                        paymentMethod: {
                            method: 'adyen_applepay',
                            additional_data: {
                                brand_code: 'applepay',
                                stateData: JSON.stringify(componentData),
                                frontendType: 'default'
                            },
                            extension_attributes: getExtensionAttributes(event.payment)
                        }
                    };

                    if (window.checkout && window.checkout.agreementIds) {
                        payload.paymentMethod.extension_attributes = {
                            agreement_ids: window.checkout.agreementIds
                        };
                    }

                    this._applePayPostData = payload;

                    const billingAddressPayload = {
                        address: {
                            email: shippingContact.emailAddress,
                            telephone: shippingContact.phoneNumber,
                            firstname: billingContact.givenName,
                            lastname: billingContact.familyName,
                            street: billingContact.addressLines,
                            city: billingContact.locality,
                            region: billingContact.administrativeArea,
                            region_id: getRegionId(
                                (billingContact.countryCode || '').toUpperCase(),
                                billingContact.administrativeArea
                            ),
                            region_code: null,
                            country_id: (billingContact.countryCode || '').toUpperCase(),
                            postcode: billingContact.postalCode,
                            same_as_billing: 0,
                            customer_address_id: 0,
                            save_in_address_book: 0
                        },
                        useForShipping: false
                    };

                    await setBillingAddress(billingAddressPayload, false);

                    if (isVirtual) {
                        actions.resolve();
                        return;
                    }

                    if (!this.shippingMethods || !this.shippingMethod) {
                        console.error('Adyen ApplePay: shipping methods not set before authorization');
                        actions.reject();
                        return;
                    }

                    const selected = this.shippingMethods[this.shippingMethod];

                    const shippingInformationPayload = {
                        addressInformation: {
                            shipping_address: {
                                email: shippingContact.emailAddress,
                                telephone: shippingContact.phoneNumber,
                                firstname: shippingContact.givenName,
                                lastname: shippingContact.familyName,
                                street: shippingContact.addressLines,
                                city: shippingContact.locality,
                                region: shippingContact.administrativeArea,
                                region_id: getRegionId(
                                    shippingContact.countryCode.toUpperCase(),
                                    shippingContact.administrativeArea
                                ),
                                region_code: null,
                                country_id: shippingContact.countryCode.toUpperCase(),
                                postcode: shippingContact.postalCode,
                                same_as_billing: 0,
                                customer_address_id: 0,
                                save_in_address_book: 0
                            },
                            shipping_method_code: selected.method_code,
                            shipping_carrier_code: selected.carrier_code,
                            extension_attributes: getExtensionAttributes(event.payment)
                        }
                    };

                    await setShippingInformation(shippingInformationPayload, false);

                    actions.resolve();
                } catch (e) {
                    console.error('Adyen ApplePay onAuthorized error', e);
                    actions.reject();
                }
            },

            /**
             * Final submission → /payments
             */
            handleOnSubmit: async function (state, component, actions) {
                if (!this._applePayPostData) {
                    console.error('Adyen ApplePay: missing post data for /payments.');
                    actions.reject();
                    return;
                }

                loader.startLoader();

                try {
                    await createPayment(JSON.stringify(this._applePayPostData), false);
                    loader.stopLoader();
                    redirectToSuccess();
                    actions.resolve(window.ApplePaySession.STATUS_SUCCESS);
                } catch (err) {
                    loader.stopLoader();
                    this._onPlaceOrderError('payment', err);
                    actions.reject(window.ApplePaySession.STATUS_FAILURE);
                }
            },

            handleOnError: function (error /*, component */) {
                console.error('Adyen ApplePay error on shipping page', error);
                this._displayError(
                    $t('Your Apple Pay payment failed. Please try again or use a different payment method.')
                );
            },

            /**
             * Totals error handler
             */
            _onGetTotalsError: function (error, reject) {
                reject({
                    status: window.ApplePaySession && window.ApplePaySession.STATUS_FAILURE
                });

                console.error('Adyen ApplePay: Unable to get totals', error);
                this._displayError(
                    $t('We\'re unable to fetch the cart totals for you. Please try an alternative payment method.')
                );
            },

            _onPlaceOrderError: function (step, error) {
                console.error(
                    'Adyen ApplePay: Unable to take payment, something went wrong during ' + step + ' step.',
                    error
                );

                const errorMessage = error && error.responseJSON && error.responseJSON.message
                    ? error.responseJSON.message
                    : $t('Your payment failed, please try again later.');
                this._displayError(errorMessage);
            },

            _displayError: function (error) {
                setTimeout(function () {
                    customerData.set('messages', {
                        messages: [{
                            text: error,
                            type: 'error'
                        }]
                    });

                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 1000);
            },

            getMerchantName: function () {
                return adyenConfiguration.getMerchantAccount() || $t('Grand Total');
            },

            getComponentRootNodeId: function () {
                return this.componentRootNode;
            }
        });
    }
);
