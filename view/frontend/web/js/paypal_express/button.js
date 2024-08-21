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
], function (
    Component,
    $t,
    customerData,
    AdyenConfiguration,
    AdyenCheckout,
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
    getMaskedIdFromCart
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
            quoteId: null
        },

        initialize: async function (config, element) {
            this._super();

            // Set the config and countries model
            configModel().setConfig(config);
            countriesModel();

            // Determine if this is a product view page
            this.isProductView = config.isProductView;

            if (!this.isProductView) {
                // Retrieve the PayPal payment method
                let paypalPaymentMethod = await getPaymentMethod('paypal', this.isProductView);
                virtualQuoteModel().setIsVirtual(false);

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
                const cart = customerData.get('cart');

                virtualQuoteModel().setIsVirtual(true, response);

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

                if (!isConfigSet(paypalPaymentMethod, ['gatewayMerchantId', 'merchantId'])) {
                }

                this.initialisePaypalComponent(paypalPaymentMethod, element);
            } catch (error) {
                console.error('Error in initialiseonPDP:', error);
            }
        },


        initialisePaypalComponent: async function (paypalPaymentMethod, element) {
            // Configuration setup
            const config = configModel().getConfig();
            const adyenData = window.adyenData;
            let currentPage = getCurrentPage(this.isProductView, element);

            const adyenCheckoutComponent = await new AdyenCheckout({
                locale: config.locale,
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
                isExpress: true,
                expressPage: currentPage,
                clientKey: AdyenConfiguration.getClientKey()
            });

            const paypalConfiguration = this.getPaypalConfiguration(paypalPaymentMethod, element);

            if (this.isProductView) {
                paypalConfiguration.currencyCode = currencyModel().getCurrency();
                paypalConfiguration.amount.currency = currencyModel().getCurrency();
            }

            try {
                this.paypalComponent = adyenCheckoutComponent.create('paypal', paypalConfiguration);

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

                virtualQuoteModel().setIsVirtual(true, pdpResponse);
                setExpressMethods(pdpResponse);
                totalsModel().setTotal(pdpResponse.totals.grand_total);
            } else {
                virtualQuoteModel().setIsVirtual(false);
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

            paypalBaseConfiguration = {
                countryCode: countryCode,
                environment: config.checkoutenv.toUpperCase(),
                isExpress: true,
                configuration: paypalPaymentMethod.configuration,
                amount: {
                    currency: currency,
                    value: this.isProductView
                        ? formatAmount(totalsModel().getTotal() * 100)
                        : formatAmount(getCartSubtotal() * 100)
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
                    try {
                        this.shippingAddress = data.shippingAddress;
                        if(this.isProductView) {
                            await activateCart(this.isProductView);
                        }

                        const shippingMethods = await this.getShippingMethods(data.shippingAddress);
                        let shippingMethod = shippingMethods.find(method => method.identifier === this.shippingMethod);
                        await this.setShippingAndTotals(shippingMethod, data.shippingAddress);

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
                    let shippingMethod = [];
                    const currentPaymentData = component.paymentData;
                    for (const method of Object.values(this.shippingMethods)) {
                        if (method.carrier_title === data.selectedShippingOption.label) {
                            this.shippingMethod = method.method_code;
                            shippingMethod = {
                                identifier: method.method_code,
                                label: method.method_title,
                                detail: method.carrier_title ? method.carrier_title : '',
                                amount: parseFloat(method.amount).toFixed(2),
                                carrierCode: method.carrier_code,
                            };
                            break;
                        }
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
                onShopperDetails: async (shopperDetails, rawData, actions) => {
                    try {
                        const isVirtual = virtualQuoteModel().getIsVirtual();

                        const { billingAddress, shippingAddress } = await this.setupAddresses(shopperDetails);

                        let billingAddressPayload = {
                            address: billingAddress,
                            'useForShipping': false
                        };

                        let shippingInformationPayload = {
                            addressInformation: {
                                shipping_address: shippingAddress,
                                billing_address: billingAddress,
                                shipping_method_code: this.shippingMethod,
                                shipping_carrier_code: this.shippingMethods[this.shippingMethod].carrier_code
                            }
                        };
                        activateCart(this.isProductView)
                            .then(() => {
                                return setBillingAddress(billingAddressPayload, this.isProductView);
                            })
                            .then(() => {
                                if (!isVirtual) {
                                    return setShippingInformation(shippingInformationPayload, this.isProductView)
                                        .then(() => {
                                            return this.createOrder();
                                        })
                                        .then(() => {
                                            actions.resolve();
                                        });
                                } else {
                                    return this.createOrder().then(() => {
                                        actions.resolve();
                                    });
                                }
                            })
                            .catch((error) => {
                                console.error('An error occurred:', error);
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
                    request.orderId = this.orderId;

                    let quoteId = this.isProductView ? maskedIdModel().getMaskedId() : getMaskedIdFromCart();

                    adyenPaymentService.paymentDetails(request, this.orderId, quoteId).
                    done(function(responseJSON) {
                        fullScreenLoader.stopLoader();
                        self.handleAdyenResult(responseJSON, self.orderId);
                    }).
                    fail(function(response) {
                        self.isPlaceOrderActionAllowed(true); //Complete this function
                        fullScreenLoader.stopLoader();
                    });

                },
                style: paypalStyles
            };

            return paypalBaseConfiguration;
        },

        setupAddresses: async function (shopperDetails) {
            let billingAddress = {
                'email': shopperDetails.shopperEmail,
                'telephone': shopperDetails.telephoneNumber,
                'firstname': shopperDetails.shopperName.firstName,
                'lastname': shopperDetails.shopperName.lastName,
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
                'email': shopperDetails.shopperEmail,
                'telephone': shopperDetails.telephoneNumber,
                'firstname': shopperDetails.shopperName.firstName,
                'lastname': shopperDetails.shopperName.lastName,
                'street': [
                    shopperDetails.shippingAddress.street
                ],
                'city': shopperDetails.shippingAddress.city,
                'region': shopperDetails.shippingAddress.stateOrProvince,
                'region_id': getRegionId(shopperDetails.shippingAddress.country, shopperDetails.shippingAddress.stateOrProvince),
                'region_code': null,
                'country_id': shopperDetails.shippingAddress.country.toUpperCase(),
                'postcode': shopperDetails.shippingAddress.postalCode,
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

                    for (let method of result) {
                        if (typeof method.method_code !== 'string') {
                            continue;
                        }
                        let shippingMethod = {
                            identifier: method.method_code,
                            label: method.method_title,
                            detail: method.carrier_title ? method.carrier_title : '',
                            amount: parseFloat(method.amount).toFixed(2),
                            carrierCode: method.carrier_code,
                        };
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
            const payload = {
                paymentMethod: {
                    method: 'adyen_paypal_express',
                    additional_data: [
                        'brand_code:paypal'
                    ]
                }
            };

            if (window.checkout && window.checkout.agreementIds) {
                payload.paymentMethod.extension_attributes = {
                    agreement_ids: window.checkout.agreementIds
                };
            }

            return new Promise((resolve, reject) => {
                createOrder(JSON.stringify(payload), this.isProductView)
                    .then(function(orderId) {
                        if (orderId) {
                            this.orderId = orderId;
                            resolve(orderId);
                        } else {
                            reject(new Error('Order ID not returned'));
                        }
                    }.bind(this))
                    .catch(function(e) {
                        console.error('Adyen Paypal Unable to take payment', e);
                        reject(e);
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
            let address = {
                'countryId': shippingAddress.countryCode,
                'region': shippingAddress.state,
                'regionId': getRegionId(shippingAddress.country_id, shippingAddress.state),
                'postcode': shippingAddress.postalCode
            };

            let shippingInformationPayload = {
                addressInformation: {
                    shipping_address: address,
                    billing_address: address,
                    shipping_method_code: this.shippingMethod,
                    shipping_carrier_code: shippingMethod ? shippingMethod.carrierCode : ''
                }
            };

            let totalsPayload = {
                'addressInformation': {
                    'address': address,
                    'shipping_method_code': this.shippingMethod,
                    'shipping_carrier_code': shippingMethod ? shippingMethod.carrierCode : ''
                }
            };

            return Promise.all([
                setShippingInformation(shippingInformationPayload, this.isProductView),
                setTotalsInfo(totalsPayload, this.isProductView)
            ]).catch(error => {
                console.error('Failed to set shipping and totals information:', error);
                throw new Error($t('Failed to set shipping and totals information. Please try again later.'));
            });
        }
    });
});
