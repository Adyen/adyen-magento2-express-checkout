define([
    'uiComponent',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/adyen',
    'Adyen_ExpressCheckout/js/actions/activateCart',
    'Adyen_ExpressCheckout/js/actions/cancelCart',
    'Adyen_ExpressCheckout/js/actions/createPayment',
    'Adyen_ExpressCheckout/js/actions/createOrder',
    'Adyen_ExpressCheckout/js/actions/getShippingMethods', // Use the new action
    'Adyen_ExpressCheckout/js/actions/getExpressMethods',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
    'Adyen_ExpressCheckout/js/helpers/formatAmount',
    'Adyen_ExpressCheckout/js/helpers/getPaypalStyles',
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
    'Adyen_ExpressCheckout/js/model/virtualQuote',
    'knockout',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/cart/totals-processor/default',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'Adyen_ExpressCheckout/js/actions/initPayments',
    'Adyen_ExpressCheckout/js/actions/updatePaypalOrder',
    'Adyen_ExpressCheckout/js/actions/setBillingAddress',
    'Magento_Checkout/js/model/full-screen-loader',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Adyen_ExpressCheckout/js/helpers/getApiUrl',
    'jquery'
], function (
    Component,
    $t,
    customerData,
    AdyenConfiguration,
    AdyenCheckout,
    activateCart,
    cancelCart,
    createPayment,
    createOrder,
    getShippingMethods, // Use the new action
    getExpressMethods,
    setShippingInformation,
    setTotalsInfo,
    formatAmount,
    getPaypalStyles,
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
    virtualQuoteModel,
    ko,
    customer,
    defaultTotalProcessor,
    quote,
    urlBuilder,
    initPayments,
    updatePaypalOrder,
    setBillingAddress,
    fullScreenLoader,
    adyenPaymentService,
    getApiUrl,
    $
) {
    'use strict';

    return Component.extend({
        defaults: {
            shippingMethods: {},
            isProductView: false,
            maskedId: null,
            paypalComponent: null,
            shippingAddress: {},
            shippingMethod: null,
            shopperEmail: null,
            billingAddress : {},
            paymentsResultCode: null,
            orderId: null
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
                paypalPaymentMethod.configuration.intent = 'authorize';

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
                this.initialisePaypalComponent(paypalPaymentMethod, element);
            }
        },

        initialisePaypalComponent: async function (paypalPaymentMethod, element) {
            // Configuration setup
            const config = configModel().getConfig();
            const adyenData = window.adyenData;
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

            if (!isConfigSet(paypalPaymentMethod, ['merchantId', 'merchantName'])) {
                return;
            }

            this.initialisePaypalComponent(paypalPaymentMethod, element);
        },

        getPaypalConfiguration: function (paypalPaymentMethod, element) {
            const paypalStyles = getPaypalStyles();
            const config = configModel().getConfig();
            const countryCode = "NL";
            const pdpForm = getPdpForm(element);
            const isVirtual = virtualQuoteModel().getIsVirtual();
            const isGuest = ko.observable(!customer.isLoggedIn());
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
                environment: "test",
                isExpress: true,
                configuration: {
                    domainName: window.location.hostname,
                    merchantId: paypalPaymentMethod.configuration.merchantId,
                    merchantName: config.merchantAccount
                },
                amount: {
                    currency: currency,
                    value: this.isProductView
                        ? formatAmount(totalsModel().getTotal() * 100)
                        : formatAmount(getCartSubtotal() * 100)
                },
                onSubmit: (state, component) => {
                    const paymentData = state.data;
                    paymentData.amount = {
                        currency: currency,
                        value: this.isProductView
                            ? formatAmount(totalsModel().getTotal() * 100)
                            : formatAmount(getCartSubtotal() * 100)
                    };
                    paymentData.merchantAccount = config.merchantAccount;
                    initPayments(paymentData).then((responseJSON) => {
                        var response = JSON.parse(responseJSON);
                        console.log("This is the response of init Payments call - ",response)
                        if (response.action) {
                            console.log(response);
                            this.paymentsResultCode = response.action.resultCode;
                            component.handleAction(response.action);
                        } else {
                            showFinalResult(response);
                        }
                    }).catch((error) => {
                        console.error('Payment initiation failed', error);
                    });
                },
                onShippingAddressChange: async (data, actions, component) => {
                    try {
                        // Store the shipping address in the global variable
                        this.shippingAddress = data.shippingAddress;
                        console.log("Fetching shipping methods...");
                        const shippingMethods = await this.getShippingMethods(data.shippingAddress);
                        console.log("Shipping methods fetched:", shippingMethods);
                        let shippingMethod = shippingMethods.find(method => method.identifier === this.shippingMethod);
                        await this.setShippingAndTotals(shippingMethod, data.shippingAddress);
                        console.log("Shipping Information Set");

                        const currentPaymentData = component.paymentData;
                        console.log("Current payment data:", currentPaymentData);

                        console.log("Updating PayPal order...");
                        let response = await updatePaypalOrder.updateOrder(
                            quote.getQuoteId(),
                            currentPaymentData,
                            shippingMethods,
                            currency
                        );
                        response = JSON.parse(response);
                        console.log("PayPal order updated:", response);

                        component.updatePaymentData(response.paymentData);


                    } catch (error) {
                        console.error('Failed to update PayPal order:', error);
                    }
                },
                onShippingOptionsChange: async (data, actions, component) => {
                    console.log("Data is here",data);
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
                    //let shippingMethod = this.shippingMethods.find(method => method.carrier_title === this.shippingMethod);
                    await this.setShippingAndTotals(shippingMethod, this.shippingAddress);

                    console.log("Updating PayPal order...");
                    let response = await updatePaypalOrder.updateOrder(
                        quote.getQuoteId(),
                        currentPaymentData,
                        this.shippingMethods,
                        currency,
                        data.selectedShippingOption
                    );
                    response = JSON.parse(response);
                    console.log("PayPal order updated:", response);

                    component.updatePaymentData(response.paymentData);
                },
                onShopperDetails: async (shopperDetails, rawData, actions) => {
                    try {
                        let self = this;
                        const isVirtual = virtualQuoteModel().getIsVirtual();
                        let billingAddress = {
                            'email': shopperDetails.shopperEmail,
                            'telephone': shopperDetails.telephoneNumber,
                            'firstname': shopperDetails.shopperName.firstName,
                            'lastname': shopperDetails.shopperName.lastName,
                            'street': [
                                shopperDetails.billingAddress.street
                            ],
                            'city': shopperDetails.billingAddress.city,
                            'region': shopperDetails.billingAddress.region,
                            'region_id': getRegionId(shopperDetails.billingAddress.country, shopperDetails.billingAddress.region),
                            'region_code': null,
                            'country_id': shopperDetails.countryCode.toUpperCase(),
                            'postcode': shopperDetails.billingAddress.postalCode,
                            'same_as_billing': 0,
                            'customer_address_id': 0,
                            'save_in_address_book': 0
                        }

                        let shippingAddress = {
                            'email': shopperDetails.shopperEmail,
                            'telephone': shopperDetails.telephoneNumber,
                            'firstname': shopperDetails.shopperName.firstName,
                            'lastname': shopperDetails.shopperName.lastName,
                            'street': [
                                shopperDetails.shippingAddress.street
                            ],
                            'city': shopperDetails.shippingAddress.city,
                            'region': shopperDetails.shippingAddress.region,
                            'region_id': getRegionId(shopperDetails.shippingAddress.country, shopperDetails.shippingAddress.region),
                            'region_code': null,
                            'country_id': shopperDetails.countryCode.toUpperCase(),
                            'postcode': shopperDetails.shippingAddress.postalCode,
                            'same_as_billing': 0,
                            'customer_address_id': 0,
                            'save_in_address_book': 0
                        }

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
                                    return setShippingInformation(shippingInformationPayload)
                                        .then(() => {
                                            console.log("finalize order");
                                            return this.createOrder();
                                        })
                                        .then(() => {
                                            actions.resolve();
                                        });
                                } else {
                                    console.log("finalize order");
                                    return this.createOrder(l).then(() => {
                                        actions.resolve();
                                    });
                                }
                            })
                            .catch((error) => {
                                console.error('An error occurred:', error);
                            });
                            console.log(this.orderId);

                    } catch (error) {
                        console.error('Failed to complete order:', error);
                        actions.reject();
                    }
                },

                onAdditionalDetails: async (state, component) => {
                    console.log("Moved to handle additional details. Component data", component.data);
                    console.log("Moved to handle additional details of state", state.data);
                    //just make payment-details call here line 491 in googlepay button_old.js

                    const self = this;
                    console.log(this.orderId);
                    let request = state.data;
                    fullScreenLoader.startLoader();
                    request.orderId = this.orderId;

                    adyenPaymentService.paymentDetails(request,self.orderId).
                    done(function(responseJSON) {
                        fullScreenLoader.stopLoader();
                        self.handleAdyenResult(responseJSON, self.orderId);
                    }).
                    fail(function(response) {
                        let errorProcessor;
                        //errorProcessor.process(response, self.messageContainer);
                        console.log("Error occured", response)
                        self.isPlaceOrderActionAllowed(true);
                        fullScreenLoader.stopLoader();
                    });

                },
                style: paypalStyles,
                isVirtual: isVirtual,
                isGuest: isGuest
            };

            return paypalBaseConfiguration;
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

                    for (let i = 0; i < result.length; i++) {
                        if (typeof result[i].method_code !== 'string') {
                            continue;
                        }
                        let method = {
                            identifier: result[i].method_code,
                            label: result[i].method_title,
                            detail: result[i].carrier_title ? result[i].carrier_title : '',
                            amount: parseFloat(result[i].amount).toFixed(2),
                            carrierCode: result[i].carrier_code,
                        };
                        shippingMethods.push(method);
                        this.shippingMethods[result[i].method_code] = result[i];
                        console.log("GlobalShippingMethods Array",this.shippingMethods);
                        if (!this.shippingMethod) {
                            this.shippingMethod = result[i].method_code;
                        }
                        console.log("GlobalShippingMethod Code",this.shippingMethod);
                    }
                    resolve(shippingMethods);
                }).catch(error => {
                    console.error('Failed to retrieve shipping methods:', error);
                    reject(new Error($t('Failed to retrieve shipping methods. Please try again later.')));
                });
            });
        },


        // createOrderDirectly: async function() {
        //     let serviceUrl = getApiUrl('order', this.isProductView);
        //     const resultCode = this.paymentsResultCode;
        //
        //     const payload = {
        //         paymentMethod: {
        //             method: 'adyen_paypal_express',
        //             additional_data: [
        //                 'brand_code:paypal',
        //                 `result_code:${resultCode}` // Include the resultCode
        //             ]
        //         }
        //     };
        //     const baseUrl = 'https://localhost:8443/';
        //     serviceUrl = baseUrl+serviceUrl;
        //     console.log(serviceUrl);
        //     if (window.checkout && window.checkout.agreementIds) {
        //         payload.paymentMethod.extension_attributes = {
        //             agreement_ids: window.checkout.agreementIds
        //         };
        //     }
        //     return $.ajax({
        //         url: serviceUrl,
        //         type: 'PUT',
        //         data: JSON.stringify(payload),
        //         contentType: 'application/json',
        //         success: function (response) {
        //             console.log('Order created:', response);
        //             return response;
        //         },
        //         error: function (response) {
        //             console.error('Error creating order:', response);
        //             throw new Error(response);
        //         }
        //     });
        // },


        createOrder: function(email) {
            let self = this;

            const payload = {
                paymentMethod: {
                    method: 'adyen_paypal_express',
                    additional_data: [
                        'brand_code:paypal'
                    ]
                }
            };
            // const payload = {
            //     email: email,
            //     paymentMethod: {
            //         method: 'adyen_paypal_express',
            //         additional_data: {
            //             brand_code: paypal,
            //             stateData: JSON.stringify(componentData)
            //         },
            //         extension_attributes: getExtensionAttributes(paymentData)
            //     }
            // };

            if (window.checkout && window.checkout.agreementIds) {
                payload.paymentMethod.extension_attributes = {
                    agreement_ids: window.checkout.agreementIds
                };
            }

            return new Promise((resolve, reject) => {
                createOrder(JSON.stringify(payload), this.isProductView)
                    .then(function(orderId) {
                        if (orderId) {
                            console.log("Order ID:", orderId);
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

            if (!!response.isFinal) {
                // Status is final redirect to the success page
                redirectToSuccess()
            } else {
                // Handle action
                self.handleAction(response.action, orderId);
            }
        },

        // Extracted method to set shipping information and totals
        setShippingAndTotals: function (shippingMethod, shippingAddress) {
            console.log(shippingAddress);
            let address = {
                'countryId': shippingAddress.countryCode,
                'region': shippingAddress.region,
                'regionId': getRegionId(shippingAddress.country_id, shippingAddress.region),
                'postcode': shippingAddress.postalCode
            };


            console.log("selected shipping method",shippingMethod);
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
                    'shipping_carrier_code': shippingMethod ? shippingMethod.label : ''
                }
            };

            return Promise.all([
                setShippingInformation(shippingInformationPayload, this.isProductView),
                setTotalsInfo(totalsPayload, this.isProductView)
            ]).then(() => {
                console.log("Shipping and totals information set");
            }).catch(error => {
                console.error('Failed to set shipping and totals information:', error);
                throw new Error($t('Failed to set shipping and totals information. Please try again later.'));
            });
        }
    });
});
