define([
    'uiComponent',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/adyen',
    'Adyen_ExpressCheckout/js/actions/activateCart',
    'Adyen_ExpressCheckout/js/actions/createOrder',
    'Adyen_ExpressCheckout/js/actions/getShippingMethods',
    'Adyen_ExpressCheckout/js/actions/getExpressMethods',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
    'Adyen_ExpressCheckout/js/helpers/formatAmount',
    'Adyen_ExpressCheckout/js/helpers/getPaypalStyles',
    'Adyen_ExpressCheckout/js/helpers/getCartSubtotal',
    'Adyen_ExpressCheckout/js/helpers/getPaymentMethod',
    'Adyen_ExpressCheckout/js/helpers/getPdpForm',
    'Adyen_ExpressCheckout/js/helpers/getPdpPriceBox',
    'Adyen_ExpressCheckout/js/helpers/isConfigSet',
    'Adyen_ExpressCheckout/js/helpers/getRegionId',
    'Adyen_ExpressCheckout/js/helpers/redirectToSuccess',
    'Adyen_ExpressCheckout/js/helpers/setExpressMethods',
    'Adyen_ExpressCheckout/js/helpers/validatePdpForm',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/countries',
    'Adyen_ExpressCheckout/js/model/totals',
    'Adyen_ExpressCheckout/js/model/currency',
    'knockout',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/quote',
    'Adyen_ExpressCheckout/js/actions/initPayments',
    'Adyen_ExpressCheckout/js/actions/updatePaypalOrder',
    'Adyen_ExpressCheckout/js/actions/setBillingAddress',
    'Magento_Checkout/js/model/full-screen-loader',
    'Adyen_ExpressCheckout/js/model/adyen-payment-service',
    'jquery',
    'Adyen_ExpressCheckout/js/model/virtualQuote',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/helpers/getCurrentPage',
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_Payment/js/helper/currencyHelper',
    'Adyen_ExpressCheckout/js/actions/cancelCart'
], function (
    Component,
    $t,
    customerData,
    AdyenConfiguration,
    AdyenWeb,
    activateCart,
    createOrder,
    getShippingMethods,
    getExpressMethods,
    setShippingInformation,
    setTotalsInfo,
    formatAmount,
    getPaypalStyles,
    getCartSubtotal,
    getPaymentMethod,
    getPdpForm,
    getPdpPriceBox,
    isConfigSet,
    getRegionId,
    redirectToSuccess,
    setExpressMethods,
    validatePdpForm,
    configModel,
    countriesModel,
    totalsModel,
    currencyModel,
    ko,
    customer,
    quote,
    initPayments,
    updatePaypalOrder,
    setBillingAddress,
    fullScreenLoader,
    adyenPaymentService,
    $,
    virtualQuoteModel,
    maskedIdModel,
    getCurrentPage,
    getMaskedIdFromCart,
    currencyHelper,
    cancelCart
) {
    'use strict';

    return Component.extend({
        isPlaceOrderActionAllowed: ko.observable(
            quote.billingAddress() != null),

        defaults: {
            shippingMethods: {},
            isProductView: false,
            maskedId: null,
            paypalComponent: null,
            shippingAddress: {},
            shippingMethod: null,
            shopperEmail: null,
            billingAddress : {},
            orderId: null,
            quoteId: null,
            currency: null
        },

        initialize: async function (config, element) {
            this._super();

            // Set the config and countries model
            configModel().setConfig(config);
            countriesModel();

            // Determine if this is a product view page
            this.isProductView = config.isProductView;

            if (!this.isProductView) {
                await virtualQuoteModel().setIsVirtual(false);

                // Retrieve the PayPal payment method
                let paypalPaymentMethod = await getPaymentMethod('paypal', this.isProductView);

                if (!paypalPaymentMethod) {
                    // Subscribe to cart updates if PayPal method is not immediately available
                    const cart = customerData.get('cart');
                    cart.subscribe(function () {
                        this.reloadPaypalButton(element);
                    }.bind(this));
                } else {
                    // Initialize the PayPal component if config is set
                    if (!isConfigSet(paypalPaymentMethod, ['merchantId'])) {
                        return;
                    }
                    this.initialisePaypalComponent(paypalPaymentMethod, element);
                }
            } else {
                // Initialize PayPal component on product view page
                this.initialiseonPDP(config, element);
            }
        },

        initialiseonPDP: async function (config, element) {
            // Configuration setup
            try {
                const response = await getExpressMethods().getRequest(element);
                await virtualQuoteModel().setIsVirtual(true, response);

                const cart = customerData.get('cart');

                cart.subscribe(function () {
                    this.reloadPaypalButton(element);
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
                            this.reloadPaypalButton(element);
                        }.bind(this))
                        .catch(function (error) {
                            console.log(error);
                        });
                }.bind(this));

                let paypalPaymentMethod = await getPaymentMethod('paypal', this.isProductView);

                if (!paypalPaymentMethod) {
                    console.error('PayPal payment method not found');
                    return;
                }

                await this.initialisePaypalComponent(paypalPaymentMethod, element);
            } catch (error) {
                console.error('Error in initialiseonPDP:', error);
            }
        },

        _createShippingMethodObject: function(method) {
            const description = method.carrier_title?.trim() || method.method_title?.trim() || method.carrier_code;
            const label = method.method_title?.trim() || method.carrier_code;

            return {
                identifier: method.method_code,
                label: label,
                detail: description,
                amount: method.amount,
                carrierCode: method.carrier_code
            };
        },

        _matchShippingMethodByLabel: function(label) {
            label = label?.trim();
            for (const method of Object.values(this.shippingMethods)) {
                if (
                    method.carrier_code === label ||
                    method.carrier_title === label ||
                    method.method_code === label ||
                    method.method_title === label
                ) {
                    this.shippingMethod = method.method_code || method.carrier_code;
                    return this._createShippingMethodObject(method);
                }
            }
            console.warn(`No matching shipping method found for label: ${label}`, this.shippingMethods);
            return null;
        },

        _getAddressInformationPayload: function(shippingMethod, shippingAddress) {
            const address = {
                countryId: shippingAddress.countryCode,
                region: shippingAddress.state,
                regionId: getRegionId(shippingAddress.country_id, shippingAddress.state),
                postcode: shippingAddress.postalCode
            };

            return {
                shippingInformationPayload: {
                    addressInformation: {
                        shipping_address: address,
                        billing_address: address,
                        shipping_method_code: this.shippingMethod,
                        shipping_carrier_code: shippingMethod?.carrierCode || ''
                    }
                },
                totalsPayload: {
                    addressInformation: {
                        address: address,
                        shipping_method_code: this.shippingMethod,
                        shipping_carrier_code: shippingMethod?.carrierCode || ''
                    }
                }
            };
        },

        initialisePaypalComponent: async function (paypalPaymentMethod, element) {
            // Configuration setup
            const config = configModel().getConfig();
            const adyenData = window.adyenData;
            let currentPage = getCurrentPage(this.isProductView, element);

            const adyenCheckoutComponent = await window.AdyenWeb.AdyenCheckout({
                locale: config.locale,
                countryCode: config.countryCode,
                originKey: config.originkey,
                environment: config.checkoutenv,
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

            const paypalConfiguration = this.getPaypalConfiguration(paypalPaymentMethod, element);

            if (this.isProductView) {
                paypalConfiguration.currencyCode = currencyModel().getCurrency();
                paypalConfiguration.amount.currency = currencyModel().getCurrency();
            }

            try {
                this.paypalComponent = await window.AdyenWeb.createComponent('paypal', adyenCheckoutComponent, paypalConfiguration);

                if (typeof this.paypalComponent.isAvailable === 'function') {
                    this.paypalComponent
                        .isAvailable()
                        .then(() => {
                            this.onAvailable(element);
                        })
                        .catch((e) => {
                            this.onNotAvailable(e);
                        });
                } else {
                    this.onAvailable(element);
                }
            } catch (error) {
                console.error('Error creating PayPal component', error);
            }
        },

        onNotAvailable: function (error) {
            console.log('PayPal is unavailable.', error);
        },

        onAvailable: function (element) {
            element.style.display = 'block';
            this.paypalComponent.mount(element);
        },

        unmountPaypal: function () {
            if (this.paypalComponent) {
                this.paypalComponent.unmount();
            }
        },

        reloadPaypalButton: async function (element) {
            const paypalPaymentMethod = await getPaymentMethod('paypal', this.isProductView);

            if (this.isProductView) {
                const pdpResponse = await getExpressMethods().getRequest(element);

                await virtualQuoteModel().setIsVirtual(true, pdpResponse);
                setExpressMethods(pdpResponse);
                totalsModel().setTotal(pdpResponse.totals.grand_total);
            } else {
                await virtualQuoteModel().setIsVirtual(false);
            }

            this.unmountPaypal();

            if (!isConfigSet(paypalPaymentMethod, ['merchantId'])) {
                return;
            }

            this.initialisePaypalComponent(paypalPaymentMethod, element);
        },

        getPaypalConfiguration: function (paypalPaymentMethod, element) {
            const paypalStyles = getPaypalStyles();
            const config = configModel().getConfig();
            const countryCode = config.countryCode;
            const currentPage = getCurrentPage(this.isProductView, element);
            let currency;
            let paypalBaseConfiguration;

            if (this.isProductView) {
                currency = currencyModel().getCurrency();
            } else {
                const cartData = customerData.get('cart');
                const adyenMethods = cartData()['adyen_payment_methods'];
                const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[paypalPaymentMethod.type];
                currency = paymentMethodExtraDetails.configuration.amount.currency;
            }

            this.currency = currency;

            paypalBaseConfiguration = {
                countryCode: countryCode,
                environment: config.checkoutenv.toUpperCase(),
                isExpress: true,
                expressPage: currentPage,
                configuration: paypalPaymentMethod.configuration,
                amount: {
                    currency: currency,
                    value: this.isProductView
                        ? currencyHelper.formatAmount(
                            totalsModel().getTotal(),
                            currency
                        )
                        : currencyHelper.formatAmount(
                            getCartSubtotal(),
                            currency
                        )
                },
                onSubmit: (state, component) => {
                    const paymentData = state.data;

                    paymentData.merchantAccount = config.merchantAccount;
                    initPayments(paymentData, this.isProductView).then((responseJSON) => {
                        let response = JSON.parse(responseJSON);
                        if (response.action) {
                            component.handleAction(response.action);
                        } else {
                            console.log('Init Payments call failed', response);
                        }
                    }).catch((error) => {
                        console.error('Payment initiation failed', error);
                    });
                },
                onShippingAddressChange: async (data, actions, component) => {
                    const isVirtual = virtualQuoteModel().getIsVirtual();

                    try {
                        if (this.isProductView) {
                            await activateCart(this.isProductView);
                        }

                        let shippingMethods = [];

                        if (isVirtual) {
                            // Use the shipping address as the billing for correct taxation for virtual quotes
                            this.setBillingAndTotalsInfo(data.shippingAddress);
                        } else {
                            this.shippingAddress = data.shippingAddress;
                            shippingMethods = await this.getShippingMethods(data.shippingAddress);
                            let shippingMethod = shippingMethods.find(method => method.identifier === this.shippingMethod);
                            await this.setShippingAndTotals(shippingMethod, data.shippingAddress);
                        }

                        const currentPaymentData = component.paymentData;

                        await updatePaypalOrder.updateOrder(
                            this.isProductView,
                            currentPaymentData,
                            shippingMethods,
                            currency
                        ).then(function (response) {
                            let parsedResponse = JSON.parse(response);
                            component.updatePaymentData(parsedResponse.paymentData);
                        }).catch(function () {
                            component.updatePaymentData(currentPaymentData);
                            return actions.reject();
                        });
                    } catch (error) {
                        return actions.reject();
                    }
                },
                onShippingOptionsChange: async (data, actions, component) => {
                    const currentPaymentData = component.paymentData;
                    const selectedShippingLabel = data.selectedShippingOption.label?.trim();

                    const shippingMethod = this._matchShippingMethodByLabel(selectedShippingLabel);
                    if (!shippingMethod) {
                        return actions.reject();
                    }

                    await this.setShippingAndTotals(shippingMethod, this.shippingAddress);

                    await updatePaypalOrder.updateOrder(
                        this.isProductView,
                        currentPaymentData,
                        this.shippingMethods,
                        currency,
                        data.selectedShippingOption
                    ).then(function (response) {
                        let parsedResponse = JSON.parse(response);
                        component.updatePaymentData(parsedResponse.paymentData);
                    }).catch(function () {
                        component.updatePaymentData(currentPaymentData);
                        return actions.reject();
                    });
                },

                onAuthorized: async (shopperDetails, actions) => {
                    try {
                        const isVirtual = virtualQuoteModel().getIsVirtual();
                        const { billingAddress, shippingAddress } = await this.setupAddresses(shopperDetails);

                        await activateCart(this.isProductView);

                        if (!isVirtual) {
                            let shippingInformationPayload = {
                                addressInformation: {
                                    shipping_address: shippingAddress,
                                    billing_address: billingAddress,
                                    shipping_method_code: this.shippingMethod,
                                    shipping_carrier_code: this.shippingMethods[this.shippingMethod].carrier_code
                                }
                            };

                            await setShippingInformation(shippingInformationPayload, this.isProductView);
                        } else {
                            // Use the shipping address as the billing for correct taxation for virtual quotes
                            let billingAddressPayload = {
                                address: shippingAddress,
                                'useForShipping': false
                            };

                            await setBillingAddress(billingAddressPayload, this.isProductView);
                        }

                        this.createOrder().then(() => {
                            actions.resolve();
                        });
                    } catch (error) {
                        console.error('Failed to complete order:', error);
                        actions.reject();
                    }
                },

                onAdditionalDetails: async (state, component) => {
                    let request = state.data;
                    let self = this;
                    fullScreenLoader.startLoader();
                    request.orderId = self.orderId;

                    let quoteId = this.isProductView ? maskedIdModel().getMaskedId() : getMaskedIdFromCart();

                    adyenPaymentService.paymentDetails(request, self.orderId, quoteId).
                    done(function(responseJSON) {
                        fullScreenLoader.stopLoader();
                        self.handleAdyenResult(responseJSON, self.orderId);
                    }).
                    fail(function(response) {
                        self.isPlaceOrderActionAllowed(true); //Complete this function
                        fullScreenLoader.stopLoader();
                    });

                },

                onError: () => cancelCart(this.isProductView),

                style: paypalStyles
            };

            return paypalBaseConfiguration;
        },

        setupAddresses: async function (shopperDetails) {
            let billingAddress = {
                'email': shopperDetails.authorizedEvent.payer.email_address,
                'telephone': shopperDetails.authorizedEvent.payer.phone.phone_number.national_number,
                'firstname': shopperDetails.authorizedEvent.payer.name.given_name,
                'lastname': shopperDetails.authorizedEvent.payer.name.surname,
                'street': [
                    shopperDetails.billingAddress.street
                ],
                'city': shopperDetails.billingAddress.city,
                'region': shopperDetails.billingAddress.stateOrProvince,
                'region_id': getRegionId(shopperDetails.billingAddress.country, shopperDetails.billingAddress.stateOrProvince),
                'region_code': null,
                'country_id': shopperDetails.billingAddress.country.toUpperCase(),
                'postcode': shopperDetails.billingAddress.postalCode,
                'same_as_billing': 0,
                'customer_address_id': 0,
                'save_in_address_book': 0
            };

            let shippingAddress = {
                'email': shopperDetails.authorizedEvent.payer.email_address,
                'telephone': shopperDetails.authorizedEvent.payer.phone.phone_number.national_number,
                'firstname': shopperDetails.authorizedEvent.payer.name.given_name,
                'lastname': shopperDetails.authorizedEvent.payer.name.surname,
                'street': [
                    shopperDetails.deliveryAddress.street
                ],
                'city': shopperDetails.deliveryAddress.city,
                'region': shopperDetails.deliveryAddress.stateOrProvince,
                'region_id': getRegionId(shopperDetails.deliveryAddress.country, shopperDetails.deliveryAddress.stateOrProvince),
                'region_code': null,
                'country_id': shopperDetails.deliveryAddress.country.toUpperCase(),
                'postcode': shopperDetails.deliveryAddress.postalCode,
                'same_as_billing': 0,
                'customer_address_id': 0,
                'save_in_address_book': 0
            };

            return {
                billingAddress: billingAddress,
                shippingAddress: shippingAddress
            };
        },

        // Extracted method to get shipping methods
        getShippingMethods: function (shippingAddress) {
            const payload = {
                address: {
                    country_id: shippingAddress.countryCode,
                    postcode: shippingAddress.postalCode,
                    street: ['']
                }
            };

            return new Promise((resolve, reject) => {
                getShippingMethods(payload, this.isProductView).then(result => {
                    if (result.length === 0) {
                        reject(new Error($t('There are no shipping methods available for you right now. Please try again or use an alternative payment method.')));
                    }

                    let shippingMethods = [];
                    this.shippingMethod = null;

                    for (let method of result) {
                        if (typeof method.method_code !== 'string') {
                            continue;
                        }
                        let shippingMethod = this._createShippingMethodObject(method);
                        shippingMethods.push(shippingMethod);
                        this.shippingMethods[method.method_code] = method;
                        if (!this.shippingMethod) {
                            this.shippingMethod = method.method_code;
                        }
                    }
                    resolve(shippingMethods);
                }).catch(error => {
                    console.error('Failed to retrieve shipping methods:', error);
                    reject(new Error($t('Failed to retrieve shipping methods. Please try again later.')));
                });
            });
        },
        createOrder: function(email) {
            let self = this;

            const payload = {
                paymentMethod: {
                    method: 'adyen_paypal_express',
                    additional_data: {
                        brand_code: 'paypal'
                    },
                }
            };

            if (window.checkout && window.checkout.agreementIds) {
                payload.paymentMethod.extension_attributes = {
                    agreement_ids: window.checkout.agreementIds
                };
            }

            return new Promise((resolve, reject) => {
                updatePaypalOrder.updateOrder(
                    self.isProductView,
                    self.paypalComponent.paymentData,
                    self.shippingMethods,
                    self.currency
                ).then(function (response) {
                    createOrder(JSON.stringify(payload), self.isProductView)
                        .then(function(orderId) {
                            if (orderId) {
                                self.orderId = orderId;
                                resolve(orderId);
                            } else {
                                reject(new Error('Order ID not returned'));
                            }
                        }.bind(this))
                        .catch(function(e) {
                            console.error('Adyen Paypal Unable to take payment', e);
                            reject(e);
                        });
                }).catch(function () {
                    reject(new Error('Payment data mismatch'));
                });
            });
        },

        handleAdyenResult: function (responseJSON, orderId) {
            let self = this;
            let response = JSON.parse(responseJSON);

            if (response.isFinal) {
                // Status is final redirect to the success page
                redirectToSuccess();
            } else {
                // Handle action
                self.handleAction(response.action, orderId); // Complete this
            }
        },

        handleAction: function(action, orderId) {
            let self = this;
            let popupModal;

            fullScreenLoader.stopLoader();

            if (action.type === 'threeDS2' || action.type === 'await') {
                popupModal = self.showModal();
            }

            try {
                self.adyenCheckoutComponent.createFromAction(action).mount('#' + this.modalLabel);
            } catch (e) {
                console.log(e);
                self.closeModal(popupModal);
            }
        },

        // Extracted method to set shipping information and totals
        setShippingAndTotals: function (shippingMethod, shippingAddress) {
            const { shippingInformationPayload, totalsPayload } =
                this._getAddressInformationPayload(shippingMethod, shippingAddress);
            return Promise.all([
                setShippingInformation(shippingInformationPayload, this.isProductView),
                setTotalsInfo(totalsPayload, this.isProductView)
            ]).catch(error => {
                console.error('Failed to set shipping and totals information:', error);
                throw new Error($t('Failed to set shipping and totals information. Please try again later.'));
            });
        },

        setBillingAndTotalsInfo: async function (addressData) {
            const address = {
                countryId: addressData.countryCode,
                region: addressData.state,
                regionId: getRegionId(addressData.country_id, addressData.state),
                postcode: addressData.postalCode
            };

            let billingAddressPayload = {
                address: address,
                'useForShipping': false
            };

            await setBillingAddress(billingAddressPayload, this.isProductView);

            let totalsPayload= {
                addressInformation: {
                    address: address
                }
            }

            await setTotalsInfo(totalsPayload, this.isProductView)
        }
    });
});
