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

            toAmountString: function (val) {
                const n = Number(val);
                return Number.isFinite(n) ? n.toFixed(2) : '0.00';
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
                    onAuthorized: this.onAuthorized.bind(this),
                    onSubmit: (state, component, actions) => this.onSubmit(state, component, actions),
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
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
                        if (result.length === 0) {
                            console.info('There are no shipping methods available for you right now. Please try again or use an alternative payment method.');
                            reject({
                                newTotal: {
                                    label: this.getMerchantName(),
                                    amount: this.isProductView
                                        ? formatAmount(totalsModel().getTotal() * 100)
                                        : formatAmount(getCartSubtotal() * 100),
                                },
                                errors: [new ApplePayError('addressUnserviceable')],
                            });                        }
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
                                amount: this.toAmountString(result[i].amount)
                            };

                            // Add method object to array.
                            shippingMethods.push(method);
                            self.shippingMethods[result[i].method_code] = result[i];
                            if (!self.shippingMethod) {
                                self.shippingMethod = result[i].method_code;
                            }
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
                                .fail((e) => this._onGetTotalsError(e, reject));
                        });
                    });
            },

            onShippingMethodSelect: function (resolve, reject, event) {
                let self = this;
                let shippingMethod = event && event.shippingMethod;

                // Fallback to the first known method if the event is missing anything
                if (!shippingMethod || !self.shippingMethods || !self.shippingMethods[shippingMethod.identifier]) {
                    const firstKey = self.shippingMethods && Object.keys(self.shippingMethods)[0];
                    if (!firstKey) {
                        reject($t('No shipping methods available.'));
                        return;
                    }
                    shippingMethod = { identifier: firstKey, amount: self.shippingMethods[firstKey].amount };
                }

                const address = {
                    countryId: self.shippingAddress.country_id,
                    region: self.shippingAddress.region,
                    regionId: getRegionId(self.shippingAddress.country_id, self.shippingAddress.region),
                    postcode: self.shippingAddress.postcode
                };

                const totalsPayload = {
                    addressInformation: {
                        address: address,
                        shipping_method_code: self.shippingMethods[shippingMethod.identifier].method_code,
                        shipping_carrier_code: self.shippingMethods[shippingMethod.identifier].carrier_code
                    }
                };

                const shippingInformationPayload = {
                    addressInformation: {
                        shipping_method_code: self.shippingMethods[shippingMethod.identifier].method_code,
                        shipping_carrier_code: self.shippingMethods[shippingMethod.identifier].carrier_code,
                        shipping_address: address
                    }
                };

                setShippingInformation(shippingInformationPayload, this.isProductView).then(() => {
                    setTotalsInfo(totalsPayload, self.isProductView)
                        .done((response) => {
                            // this ensures newTotal/newLineItems are valid and well-formed
                            self.afterSetTotalsInfo(response, shippingMethod, self.isProductView, resolve);
                        }).fail((e) => this._onGetTotalsError(e, reject));
                });
            },

            afterSetTotalsInfo: function (response, shippingMethod, isPdp, resolve) {
                // Guard against missing/NaN totals
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
                        { type: 'final', label: $t('Subtotal'), amount: subtotal }
                    ]
                };

                // If the shipping methods is an array, pass them all and use the first one for line items
                if (Array.isArray(shippingMethod)) {
                    update.newShippingMethods = shippingMethod;
                    shippingMethod = shippingMethod[0]; // first item becomes the selected one
                }

                // shipping line (guard amount)
                const shippingAmount = this.toAmountString(shippingMethod && shippingMethod.amount);
                update.newLineItems.push({
                    type: 'final',
                    label: $t('Shipping'),
                    amount: shippingAmount
                });

                // tax line (only if > 0.00)
                if (Number(tax) > 0) {
                    update.newLineItems.push({
                        type: 'final',
                        label: $t('Tax'),
                        amount: tax
                    });
                }

                // store identifier defensively (if present)
                if (shippingMethod && shippingMethod.identifier) {
                    this.shippingMethod = shippingMethod.identifier;
                }

                resolve(update);
            },

            onAuthorized: function (data, actions) {
                const isVirtual = virtualQuoteModel().getIsVirtual();
                const event = data && data.authorizedEvent;
                if (!event || !event.payment) {
                    console.error('Adyen ApplePay: authorizedEvent missing');
                    actions.reject();
                    return;
                }

                try {
                    const shippingContact = event.payment.shippingContact || {};
                    const billingContact  = event.payment.billingContact  || {};

                    // 1) Build and stash the /payments payload for onSubmit
                    const componentData = this.applePayComponent.data;
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
                    this._applePayPostData = postData;

                    // 2) Prepare billing (and shipping for non-virtual), then resolve
                    const billingAddressPayload = {
                        address: {
                            'email': shippingContact.emailAddress,
                            'telephone': shippingContact.phoneNumber,
                            'firstname': billingContact.givenName,
                            'lastname': billingContact.familyName,
                            'street': billingContact.addressLines,
                            'city': billingContact.locality,
                            'region': billingContact.administrativeArea,
                            'region_id': getRegionId(
                                (billingContact.countryCode || '').toUpperCase(),
                                billingContact.administrativeArea
                            ),
                            'region_code': null,
                            'country_id': (billingContact.countryCode || '').toUpperCase(),
                            'postcode': billingContact.postalCode,
                            'same_as_billing': 0,
                            'customer_address_id': 0,
                            'save_in_address_book': 0
                        },
                        'useForShipping': false
                    };

                    activateCart(this.isProductView).then(() => {
                        setBillingAddress(billingAddressPayload, this.isProductView).done(() => {
                            if (isVirtual) {
                                // Done preparing – move to onSubmit
                                actions.resolve();
                                return;
                            }

                            const shippingInformationPayload = {
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
                                    'shipping_method_code': this.shippingMethods[this.shippingMethod].method_code,
                                    'shipping_carrier_code': this.shippingMethods[this.shippingMethod].carrier_code,
                                    'extension_attributes': getExtensionAttributes(event.payment)
                                }
                            };

                            setShippingInformation(shippingInformationPayload, this.isProductView)
                                .done(() => actions.resolve())
                                .fail((e) => {
                                    console.error('Adyen ApplePay Unable to set shipping information', e);
                                    actions.reject();
                                });
                        });
                    });
                } catch (e) {
                    console.error('Adyen ApplePay onAuthorized error', e);
                    actions.reject();
                }
            },

            onSubmit: function (state, component, actions) {
                if (!this._applePayPostData) {
                    console.error('Adyen ApplePay: missing post data for /payments.');
                    actions.reject();
                    return;
                }

                createPayment(JSON.stringify(this._applePayPostData), this.isProductView)
                    .done((res) => {
                        redirectToSuccess();
                        actions.resolve(window.ApplePaySession.STATUS_SUCCESS);
                    })
                    .fail((err) => {
                        this._onPlaceOrderError('payment', err);
                        actions.reject(window.ApplePaySession.STATUS_FAILURE);
                    });
            },

            /**
             * @param {*} error
             * @param {function} reject
             * @private
             */
            _onGetTotalsError: function (error, reject) {
                reject({
                    status: window.ApplePaySession.STATUS_FAILURE,
                });

                console.error('Adyen ApplePay: Unable to get totals', error);
                this._displayError($t('We\'re unable to fetch the cart totals for you. Please try an alternative payment method.'));
            },

            /**
             * @param {string} step
             * @param {*} error
             * @param {function} reject
             * @private
             */
            _onPlaceOrderError: function(step, error) {
                console.error(
                    `Adyen ApplePay: Unable to take payment, something went wrong during ${step} step.`,
                    error,
                );

                const errorMessage = error?.responseJSON.message ?? $t('Your payment failed, Please try again later');
                this._displayError(errorMessage);
            },

            /**
             * @see https://magento.stackexchange.com/questions/237959/how-to-use-javascript-to-display-page-messages
             * @param {string} error
             * @private
             */
            _displayError: function (error) {
                setTimeout(() => {
                    customerData.set('messages', {
                        messages: [{
                            text: error,
                            type: 'error',
                        }],
                    });

                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }, 1000);
            },

            getMerchantName: function() {
                const config = configModel().getConfig();
                return config?.merchantAccount ?? $t('Grand Total');
            },
        });
    }
);
