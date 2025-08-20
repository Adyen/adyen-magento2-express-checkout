define([
    'uiComponent',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/adyen',
    'Adyen_ExpressCheckout/js/actions/activateCart',
    'Adyen_ExpressCheckout/js/actions/cancelCart',
    'Adyen_ExpressCheckout/js/actions/createPayment',
    'Adyen_ExpressCheckout/js/actions/getShippingMethods',
    'Adyen_ExpressCheckout/js/actions/getExpressMethods',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/actions/setBillingAddress',
    'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
    'Adyen_ExpressCheckout/js/helpers/formatAmount',
    'Adyen_ExpressCheckout/js/helpers/getApplePayStyles',
    'Adyen_ExpressCheckout/js/helpers/getCartSubtotal',
    'Adyen_ExpressCheckout/js/helpers/getExtensionAttributes',
    'Adyen_ExpressCheckout/js/helpers/getPaymentMethod',
    'Adyen_ExpressCheckout/js/helpers/getPdpForm',
    'Adyen_ExpressCheckout/js/helpers/getPdpPriceBox',
    'Adyen_ExpressCheckout/js/helpers/getSupportedNetworks',
    'Adyen_ExpressCheckout/js/helpers/isConfigSet',
    'Adyen_ExpressCheckout/js/helpers/getRegionId',
    'Adyen_ExpressCheckout/js/helpers/redirectToSuccess',
    'Adyen_ExpressCheckout/js/helpers/setExpressMethods',
    'Adyen_ExpressCheckout/js/helpers/validatePdpForm',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/countries',
    'Adyen_ExpressCheckout/js/model/totals',
    'Adyen_ExpressCheckout/js/model/currency',
    'Adyen_ExpressCheckout/js/helpers/getCurrentPage',
    'Adyen_ExpressCheckout/js/model/virtualQuote',
    'Adyen_Payment/js/helper/currencyHelper'
    ],
    function (
        Component,
        $t,
        customerData,
        AdyenConfiguration,
        AdyenWeb,
        activateCart,
        cancelCart,
        createPayment,
        getShippingMethods,
        getExpressMethods,
        setShippingInformation,
        setBillingAddress,
        setTotalsInfo,
        formatAmount,
        getApplePayStyles,
        getCartSubtotal,
        getExtensionAttributes,
        getPaymentMethod,
        getPdpForm,
        getPdpPriceBox,
        getSupportedNetworks,
        isConfigSet,
        getRegionId,
        redirectToSuccess,
        setExpressMethods,
        validatePdpForm,
        configModel,
        countriesModel,
        totalsModel,
        currencyModel,
        getCurrentPage,
        virtualQuoteModel,
        currencyHelper
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                shippingMethods: {},
                isProductView: false,
                maskedId: null,
                applePayComponent: null,
            },

            initialize: async function (config, element) {
                this._super();

                configModel().setConfig(config);
                countriesModel();

                this.isProductView = config.isProductView;

                // If express methods is not set then set it.
                if (this.isProductView) {
                    const response = await getExpressMethods().getRequest(element);
                    const cart = customerData.get('cart');

                    virtualQuoteModel().setIsVirtual(true, response);

                    cart.subscribe(function () {
                        this.reloadApplePayButton(element);
                    }.bind(this));

                    setExpressMethods(response);
                    totalsModel().setTotal(response.totals.grand_total);
                    currencyModel().setCurrency(response.totals.quote_currency_code);

                    const $priceBox = getPdpPriceBox();
                    const pdpForm = getPdpForm(element);

                    $priceBox.on('priceUpdated', async function () {
                        const isValid = new Promise((resolve, reject) => {
                            return validatePdpForm(resolve, reject, pdpForm, true);
                        });

                        isValid
                            .then(async function () {
                                this.reloadApplePayButton(element);
                            }.bind(this))
                            .catch(function (error) {
                                console.log(error);
                            });
                    }.bind(this));

                    let applePaymentMethod = await getPaymentMethod('applepay', this.isProductView);

                    if (!isConfigSet(applePaymentMethod, ['merchantId', 'merchantName'])) {
                        return;
                    }

                    this.initialiseApplePayComponent(applePaymentMethod, element);
                } else {
                    let applePaymentMethod = await getPaymentMethod('applepay', this.isProductView);
                    virtualQuoteModel().setIsVirtual(false);

                    if (!applePaymentMethod) {
                        const cart = customerData.get('cart');
                        cart.subscribe(function () {
                            this.reloadApplePayButton(element);
                        }.bind(this));
                    } else {
                        if (!isConfigSet(applePaymentMethod, ['merchantId', 'merchantName'])) {
                            console.log('Required configuration for Apple Pay is missing.');
                            return;
                        }
                        this.initialiseApplePayComponent(applePaymentMethod, element);
                    }
                }
            },

            initialiseApplePayComponent: async function (applePaymentMethod, element) {
                const config = configModel().getConfig();
                const adyenData = window.adyenData;
                let currentPage = getCurrentPage(this.isProductView, element);

                const adyenCheckoutComponent = await window.AdyenWeb.AdyenCheckout({
                    locale: config.locale,
                    environment: config.checkoutenv,
                    countryCode: config.countryCode,
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
                    },
                    clientKey: AdyenConfiguration.getClientKey()
                });
                const applePayConfiguration = this.getApplePayConfiguration(applePaymentMethod, element);

                if (this.isProductView) {
                    applePayConfiguration.currencyCode = currencyModel().getCurrency();
                    applePayConfiguration.amount.currency = currencyModel().getCurrency();
                }

                this.applePayComponent = await window.AdyenWeb.createComponent(
                    'applepay',
                    adyenCheckoutComponent,
                    applePayConfiguration
                );

                this.applePayComponent
                    .isAvailable()
                    .then(() => {
                        this.onAvailable(element);
                    })
                    .catch((e) => {
                        this.onNotAvailable(e);
                    });
            },

            /**
             * @param {*} error
             */
            onNotAvailable: function (error) {
                console.log('Apple pay is unavailable.', error);
            },

            /**
             * @param {HTMLElement} element
             */
            onAvailable: function (element) {
                element.style.display = 'block';
                this.applePayComponent.mount(element);
            },

            unmountApplePay: function () {
                if (this.applePayComponent) {
                    this.applePayComponent.unmount();
                }
            },

            reloadApplePayButton: async function (element) {
                const applePaymentMethod = await getPaymentMethod('applepay', this.isProductView);

                if (this.isProductView) {
                    const pdpResponse = await getExpressMethods().getRequest(element);

                    virtualQuoteModel().setIsVirtual(true, pdpResponse);
                    setExpressMethods(pdpResponse);
                    totalsModel().setTotal(pdpResponse.totals.grand_total);
                } else {
                    virtualQuoteModel().setIsVirtual(false);
                }

                this.unmountApplePay();

                if (!isConfigSet(applePaymentMethod, ['merchantId', 'merchantName'])) {
                    return;
                }

                this.initialiseApplePayComponent(applePaymentMethod, element);
            },

            getApplePayConfiguration: function (applePaymentMethod, element) {
                const applePayStyles = getApplePayStyles();
                const config = configModel().getConfig();
                const countryCode = config.countryCode === 'UK' ? 'GB' :  config.countryCode;
                const pdpForm = getPdpForm(element);
                const isVirtual = virtualQuoteModel().getIsVirtual();
                let currency;
                let applepayBaseConfiguration;
                const currentPage = getCurrentPage(this.isProductView, element);

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[applePaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                }

                applepayBaseConfiguration = {
                    countryCode: countryCode,
                    currencyCode: currency,
                    totalPriceLabel: this.getMerchantName(),
                    configuration: {
                        domainName: window.location.hostname,
                        merchantId: applePaymentMethod.configuration.merchantId,
                        merchantName: applePaymentMethod.configuration.merchantName
                    },
                    amount: {
                        value: this.isProductView
                            ? currencyHelper.formatAmount(
                                totalsModel().getTotal(),
                                currency
                            )
                            : currencyHelper.formatAmount(
                                getCartSubtotal(),
                                currency
                            ),
                        currency: currency
                    },
                    isExpress: true,
                    expressPage: currentPage,
                    supportedNetworks: getSupportedNetworks(),
                    merchantCapabilities: ['supports3DS'],
                    requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone'],
                    requiredBillingContactFields: ['postalAddress', 'name'],
                    shippingMethods: [],
                    onAuthorized: this.startPlaceOrder.bind(this),
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function () {},
                    onError: () => cancelCart(this.isProductView),
                    ...applePayStyles
                };

                if (!isVirtual) {
                    applepayBaseConfiguration.onShippingContactSelected = this.onShippingContactSelect.bind(this);
                    applepayBaseConfiguration.onShippingMethodSelected = this.onShippingMethodSelect.bind(this);
                }

                return applepayBaseConfiguration;
            },

            onShippingContactSelect: function (resolve, reject, event) {
                let self = this;

                // Get the address.
                let address = event.shippingContact,
                    // Create a payload.
                    payload = {
                        address: {
                            city: address.locality,
                            region: address.administrativeArea,
                            country_id: address.countryCode.toUpperCase(),
                            postcode: address.postalCode,
                            save_in_address_book: 0
                        }
                    };

                self.shippingAddress = payload.address;

                activateCart(this.isProductView)
                    .then(() => getShippingMethods(payload, this.isProductView))
                    .then((result) => {
                    // Stop if no shipping methods.
                    if (!Array.isArray(result) || result.length === 0) {
                         return resolve(buildErrorUpdate(
                             $t('There are no shipping methods available for this address.'),
                             'shippingContactInvalid',
                             'postalAddress'
                             ));
                    }

                    let shippingMethods = [];

                    self.shippingMethods = {};
                    // Format shipping methods array.
                    for (let i = 0; i < result.length; i++) {
                        if (typeof result[i].method_code !== 'string') {
                            continue;
                        }
                        let method = {
                            identifier: result[i].method_code,
                            label: result[i].method_title,
                            detail: result[i].carrier_title ? result[i].carrier_title : '',
                            amount: String(parseFloat(result[i].amount).toFixed(2))
                        };
                        // Add method object to array.

                        shippingMethods.push(method);
                        self.shippingMethods[result[i].method_code] = result[i];
                        if (!self.shippingMethod) {
                            self.shippingMethod = result[i].method_code;
                        }
                    }

                    // If all methods were filtered out, bail with a clear ApplePayError.
                    if (shippingMethods.length === 0) {
                         return resolve(buildErrorUpdate(
                            $t('No valid shipping methods for this address.'),
                            'shippingContactInvalid',
                            'postalAddress'
                         ));
                    }

                    let address = {
                        'countryId': self.shippingAddress.country_id,
                        'region': self.shippingAddress.region,
                        'regionId': getRegionId(self.shippingAddress.country_id, self.shippingAddress.region),
                        'postcode': self.shippingAddress.postcode
                    };

                    // Create payload to get totals
                    let totalsPayload = {
                        'addressInformation': {
                            'address': address,
                            'shipping_method_code': self.shippingMethods[shippingMethods[0].identifier].method_code,
                            'shipping_carrier_code': self.shippingMethods[shippingMethods[0].identifier].carrier_code
                        }
                    };

                    // Create payload to update quote
                    let shippingInformationPayload = {
                        'addressInformation': {
                            'shipping_address': address,
                            'shipping_method_code': self.shippingMethods[shippingMethods[0].identifier].method_code,
                            'shipping_carrier_code': self.shippingMethods[shippingMethods[0].identifier].carrier_code
                        }
                    };

                    setShippingInformation(shippingInformationPayload, self.isProductView).then(() => {
                        setTotalsInfo(totalsPayload, self.isProductView)
                            .done((response) => {
                                self.afterSetTotalsInfo(response, shippingMethods, self.isProductView, resolve);
                            })
                            .fail((e) => {
                                console.error('Adyen ApplePay: Unable to get totals', e);
                                resolve(buildErrorUpdate($t('We\'re unable to fetch the cart totals. Please try again.')));
                            });
                    });
                });
            },

            onShippingMethodSelect: function (resolve, reject, event) {
                let self = this;
                let shippingMethod = event.shippingMethod;

                let address = {
                    'countryId': self.shippingAddress.country_id,
                    'region': self.shippingAddress.region,
                    'regionId': getRegionId(self.shippingAddress.country_id, self.shippingAddress.region),
                    'postcode': self.shippingAddress.postcode
                };

                let totalsPayload = {
                    'addressInformation': {
                        'address': address,
                        'shipping_method_code': self.shippingMethods[shippingMethod.identifier].method_code,
                        'shipping_carrier_code': self.shippingMethods[shippingMethod.identifier].carrier_code
                    }
                };

                let shippingInformationPayload = {
                    'addressInformation': {
                        'shipping_method_code': self.shippingMethods[shippingMethod.identifier].method_code,
                        'shipping_carrier_code': self.shippingMethods[shippingMethod.identifier].carrier_code,
                        'shipping_address': address
                    }
                };

                setShippingInformation(shippingInformationPayload, this.isProductView).then(() => {
                    setTotalsInfo(totalsPayload, self.isProductView)
                        .done((response) => {
                            self.afterSetTotalsInfo(response, shippingMethod, self.isProductView, resolve);
                        }).fail((e) => {
                        console.error('Adyen ApplePay: Unable to get totals', e);
                        resolve(buildErrorUpdate($t('We\'re unable to fetch the cart totals. Please try again.')));

                    });
                });
            },

            afterSetTotalsInfo: function (response, shippingMethod, isPdp, resolve) {
                // Normalize and harden numbers into valid Apple Pay strings
                const toAmt = (v) => {
                        const n = Number(v);
                        return Number.isFinite(n) ? n.toFixed(2) : '0.00';
                    };

                    const applePayShippingMethodUpdate = {
                        newTotal: {
                            type: 'final',
                                label: this.getMerchantName(),
                                amount: toAmt(response?.grand_total)
                        }
                };

                // If the shipping methods is an array pass all methods to Apple Pay to show in payment window.
                if (Array.isArray(shippingMethod)) {
                    applePayShippingMethodUpdate.newShippingMethods = shippingMethod;
                    shippingMethod = shippingMethod[0];
                }

                const lineItems = [
                    {
                        type: 'final',
                        label: $t('Subtotal'),
                        amount: toAmt(response?.subtotal)
                    },
                    {
                        type: 'final',
                        label: $t('Shipping'),
                        amount: toAmt(shippingMethod?.amount)
                    }
                ];

                if (Number(response?.tax_amount) > 0) {
                    lineItems.push({
                        type: 'final',
                        label: $t('Tax'),
                        amount: toAmt(response.tax_amount)
                    })
                }

                applePayShippingMethodUpdate.newLineItems = lineItems;

                // Guard: Only set identifier if present
                if (shippingMethod && shippingMethod.identifier) {
                        this.shippingMethod = shippingMethod.identifier;
                }
                resolve(applePayShippingMethodUpdate);
            },

            /**
             * Place the order
             */
            startPlaceOrder: function (resolve, reject, event) {
                const isVirtual = virtualQuoteModel().getIsVirtual();

                let self = this;
                let componentData = self.applePayComponent.data;

                let shippingContact = event.payment.shippingContact;
                let billingContact = event.payment.billingContact;

                let billingAddressPayload = {
                    address: {
                        'email': shippingContact.emailAddress,
                        'telephone': shippingContact.phoneNumber,
                        'firstname': billingContact.givenName,
                        'lastname': billingContact.familyName,
                        'street': billingContact.addressLines,
                        'city': billingContact.locality,
                        'region': billingContact.administrativeArea,
                        'region_id': getRegionId(billingContact.countryCode.toUpperCase(), billingContact.administrativeArea),
                        'region_code': null,
                        'country_id': billingContact.countryCode.toUpperCase(),
                        'postcode': billingContact.postalCode,
                        'same_as_billing': 0,
                        'customer_address_id': 0,
                        'save_in_address_book': 0
                    },
                    'useForShipping': false
                };

                const postData = {
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
                    postData.paymentMethod.extension_attributes = {
                        agreement_ids: window.checkout.agreementIds
                    };
                }

                activateCart(this.isProductView).then(() => {
                    setBillingAddress(billingAddressPayload, self.isProductView).done(() => {
                        if (!isVirtual) {
                            let shippingInformationPayload = {
                                'addressInformation': {
                                    'shipping_address': {
                                        'email': shippingContact.emailAddress,
                                        'telephone': shippingContact.phoneNumber,
                                        'firstname': shippingContact.givenName,
                                        'lastname': shippingContact.familyName,
                                        'street': shippingContact.addressLines,
                                        'city': shippingContact.locality,
                                        'region': shippingContact.administrativeArea,
                                        'region_id': getRegionId(
                                            shippingContact.countryCode.toUpperCase(),
                                            shippingContact.administrativeArea
                                        ),
                                        'region_code': null,
                                        'country_id': shippingContact.countryCode.toUpperCase(),
                                        'postcode': shippingContact.postalCode,
                                        'same_as_billing': 0,
                                        'customer_address_id': 0,
                                        'save_in_address_book': 0
                                    },
                                    'shipping_method_code': self.shippingMethods[self.shippingMethod].method_code,
                                    'shipping_carrier_code': self.shippingMethods[self.shippingMethod].carrier_code,
                                    'extension_attributes': getExtensionAttributes(event.payment)
                                }
                            };

                            setShippingInformation(shippingInformationPayload, self.isProductView).done(function () {
                                // Submit payment information
                                createPayment(JSON.stringify(postData), self.isProductView)
                                    .done(function () {
                                        redirectToSuccess();
                                        resolve({ status: window.ApplePaySession.STATUS_SUCCESS });
                                    }).fail(function (r) {
                                    reject({ status: window.ApplePaySession.STATUS_FAILURE });
                                    console.error('Adyen ApplePay Unable to take payment', r);
                                });

                            }.bind(self)).fail(function (e) {
                                console.error('Adyen ApplePay Unable to set shipping information', e);
                                reject(window.ApplePaySession.STATUS_INVALID_BILLING_POSTAL_ADDRESS);
                            });
                        } else {
                            createPayment(JSON.stringify(postData), self.isProductView)
                                .done(function () {
                                    redirectToSuccess();
                                    resolve({ status: window.ApplePaySession.STATUS_SUCCESS });
                                }).fail(function (r) {
                                reject({ status: window.ApplePaySession.STATUS_FAILURE });
                                console.error('Adyen ApplePay Unable to take payment', r);
                            });
                        }
                    });
                });
            },

            getMerchantName: function() {
                const config = configModel().getConfig();
                return config?.merchantAccount ?? $t('Grand Total');
            },
        });
    }
);
