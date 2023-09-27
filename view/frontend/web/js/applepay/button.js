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
    'Adyen_ExpressCheckout/js/model/currency'
],
    function (
        Component,
        $t,
        customerData,
        AdyenConfiguration,
        AdyenCheckout,
        activateCart,
        cancelCart,
        createPayment,
        getShippingMethods,
        getExpressMethods,
        setShippingInformation,
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
        currencyModel
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                shippingMethods: {},
                isProductView: false,
                maskedId: null,
                applePayComponent: null
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

                    cart.subscribe(function () {
                        this.reloadApplePayButton(element);
                    }.bind(this));

                    setExpressMethods(response);
                    totalsModel().setTotal(response.totals.grand_total);
                    currencyModel().setCurrency(response.totals.quote_currency_code)

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
                const adyenCheckoutComponent = await new AdyenCheckout({
                    locale: config.locale,
                    originKey: config.originkey,
                    environment: config.checkoutenv,
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

                this.applePayComponent = adyenCheckoutComponent.create(
                    'applepay',
                    applePayConfiguration
                );

                this.applePayComponent
                    .isAvailable()
                    .then(() => {
                        this.applePayComponent.mount(element);
                    }).catch(e => {
                        console.log('Apple pay is unavailable.', e);
                    });
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

                    setExpressMethods(pdpResponse);
                    totalsModel().setTotal(pdpResponse.totals.grand_total);
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
                let currency;

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[applePaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                }

                return {
                    countryCode: countryCode,
                    currencyCode: currency,
                    totalPriceLabel: $t('Grand Total'),
                    configuration: {
                        domainName: window.location.hostname,
                        merchantId: applePaymentMethod.configuration.merchantId,
                        merchantName: applePaymentMethod.configuration.merchantName
                    },
                    amount: {
                        value: this.isProductView
                            ? formatAmount(totalsModel().getTotal() * 100)
                            : formatAmount(getCartSubtotal() * 100),
                        currency: currency
                    },
                    supportedNetworks: getSupportedNetworks(),
                    merchantCapabilities: ['supports3DS'],
                    requiredShippingContactFields: ['postalAddress', 'name', 'email', 'phone'],
                    requiredBillingContactFields: ['postalAddress', 'name'],
                    shippingMethods: [],
                    onAuthorized: this.startPlaceOrder.bind(this),
                    onShippingContactSelected: this.onShippingContactSelect.bind(this),
                    onShippingMethodSelected: this.onShippingMethodSelect.bind(this),
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function () {},
                    onError: () => cancelCart(this.isProductView),
                    ...applePayStyles
                };
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
                        if (result.length === 0) {
                            reject($t('There are no shipping methods available for you right now. Please try again or use an alternative payment method.'));
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
                                amount: parseFloat(result[i].amount).toFixed(2)
                            };
                            // Add method object to array.

                            shippingMethods.push(method);
                            self.shippingMethods[result[i].method_code] = result[i];
                            if (!self.shippingMethod) {
                                self.shippingMethod = result[i].method_code;
                            }
                        }
                        // Create payload to get totals
                        let totalsPayload = {
                            'addressInformation': {
                                'address': {
                                    'countryId': self.shippingAddress.country_id,
                                    'region': self.shippingAddress.region,
                                    'regionId': getRegionId(self.shippingAddress.country_id, self.shippingAddress.region),
                                    'postcode': self.shippingAddress.postcode
                                },
                                'shipping_method_code': self.shippingMethods[shippingMethods[0].identifier].method_code,
                                'shipping_carrier_code': self.shippingMethods[shippingMethods[0].identifier].carrier_code
                            }
                        };

                        setTotalsInfo(totalsPayload, this.isProductView)
                            .done((response) => {
                                let applePayShippingContactUpdate = {};

                                applePayShippingContactUpdate.newShippingMethods = shippingMethods;
                                applePayShippingContactUpdate.newTotal = {
                                    label: $t('Grand Total'),
                                    amount: response.grand_total.toString()
                                };
                                applePayShippingContactUpdate.newLineItems = [
                                    {
                                        type: 'final',
                                        label: $t('Subtotal'),
                                        amount: response.subtotal.toString()
                                    },
                                    {
                                        type: 'final',
                                        label: $t('Shipping'),
                                        amount: shippingMethods[0].amount.toString()
                                    }
                                ];
                                resolve(applePayShippingContactUpdate);
                                // Pass shipping methods back
                            }).fail((e) => {
                                console.error('Adyen ApplePay: Unable to get totals', e);
                                reject($t('We\'re unable to fetch the cart totals for you. Please try an alternative payment method.'));
                        });
                    }).catch(reject);
            },

            onShippingMethodSelect: function (resolve, reject, event) {
                let self = this;

                let shippingMethod = event.shippingMethod;
                let payload = {
                    'addressInformation': {
                        'address': {
                            'countryId': self.shippingAddress.country_id,
                            'region': self.shippingAddress.region,
                            'regionId': getRegionId(self.shippingAddress.country_id, self.shippingAddress.region),
                            'postcode': self.shippingAddress.postcode
                        },
                        'shipping_method_code': self.shippingMethods[shippingMethod.identifier].method_code,
                        'shipping_carrier_code': self.shippingMethods[shippingMethod.identifier].carrier_code
                    }
                };

                setTotalsInfo(payload, this.isProductView)
                    .done((response) => {
                        let applePayShippingMethodUpdate = {};

                        applePayShippingMethodUpdate.newTotal = {
                            type: 'final',
                            label: $t('Grand Total'),
                            amount: response.grand_total.toString()
                        };
                        applePayShippingMethodUpdate.newLineItems = [
                            {
                                type: 'final',
                                label: $t('Subtotal'),
                                amount: response.subtotal.toString()
                            },
                            {
                                type: 'final',
                                label: $t('Shipping'),
                                amount: shippingMethod.amount.toString()
                            }
                        ];
                        self.shippingMethod = shippingMethod.identifier;
                        resolve(applePayShippingMethodUpdate);
                    }).fail((e) => {
                        console.error('Adyen ApplePay: Unable to get totals', e);
                        reject($t('We\'re unable to fetch the cart totals for you. Please try an alternative payment method.'));
                });
            },

            /**
             * Place the order
             */
            startPlaceOrder: function (resolve, reject, event) {
                let self = this;
                let shippingContact = event.payment.shippingContact,
                    billingContact = event.payment.billingContact,
                    payload = {
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
                        'billing_address': {
                            'email': shippingContact.emailAddress,
                            'telephone': '0000000000',
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
                        'shipping_method_code': self.shippingMethods[self.shippingMethod].method_code,
                        'shipping_carrier_code': self.shippingMethods[self.shippingMethod].carrier_code,
                        'extension_attributes': getExtensionAttributes(event.payment)
                    }
                };

                let componentData = self.applePayComponent.data;

                setShippingInformation(payload, this.isProductView).done(function () {
                    // Submit payment information
                    const postData = {
                        email: shippingContact.emailAddress,
                        paymentMethod: {
                            method: 'adyen_applepay',
                            additional_data: {
                                brand_code: 'applepay',
                                stateData: JSON.stringify(componentData)
                            }
                        }
                    };

                    if (window.checkout && window.checkout.agreementIds) {
                        postData.paymentMethod.extension_attributes = {
                            agreement_ids: window.checkout.agreementIds
                        };
                    }

                    createPayment(JSON.stringify(postData), this.isProductView)
                        .done(function () {
                            redirectToSuccess();
                            resolve(window.ApplePaySession.STATUS_SUCCESS);
                        }).fail(function (r) {
                            reject(window.ApplePaySession.STATUS_FAILURE);
                            console.error('Adyen ApplePay Unable to take payment', r);
                        });

                }.bind(this)).fail(function (e) {
                    console.error('Adyen ApplePay Unable to set shipping information', e);
                    reject(window.ApplePaySession.STATUS_INVALID_BILLING_POSTAL_ADDRESS);
                });
            }
        });
    }
);
