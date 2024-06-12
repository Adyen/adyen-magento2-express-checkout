define([
    'uiComponent',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Adyen_Payment/js/model/adyen-configuration',
    'Adyen_Payment/js/adyen',
    'Adyen_ExpressCheckout/js/actions/activateCart',
    'Adyen_ExpressCheckout/js/actions/cancelCart',
    'Adyen_ExpressCheckout/js/actions/createPayment',
    'Adyen_ExpressCheckout/js/actions/getShippingMethods', // Use the new action
    'Adyen_ExpressCheckout/js/actions/getExpressMethods',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/actions/setBillingAddress',
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
    'Adyen_ExpressCheckout/js/actions/updatePaypalOrder'
], function (
    Component,
    $t,
    customerData,
    AdyenConfiguration,
    AdyenCheckout,
    activateCart,
    cancelCart,
    createPayment,
    getShippingMethods, // Use the new action
    getExpressMethods,
    setShippingInformation,
    setBillingAddress,
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
    updatePaypalOrder
) {
    'use strict';

    return Component.extend({
        defaults: {
            shippingMethods: {},
            isProductView: false,
            maskedId: null,
            paypalComponent: null,
            shippingAddress: {},
            shippingMethod: null
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

        getAndSetShippingMethods: async function (shippingAddress, isProductView) {
            try {
                const payload = {
                    address: {
                        country_id: shippingAddress.countryCode,
                        postcode: shippingAddress.postalCode,
                        street: ['']
                    }
                };
                const result = await getShippingMethods(payload, isProductView);

                if (result.length === 0) {
                    throw new Error($t('There are no shipping methods available for you right now. Please try again or use an alternative payment method.'));
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
                        amount: parseFloat(result[i].amount).toFixed(2)
                    };
                    shippingMethods.push(method);
                    this.shippingMethods[result[i].method_code] = result[i];
                    if (!this.shippingMethod) {
                        this.shippingMethod = result[i].method_code;
                    }
                }

                let address = {
                    'countryId': shippingAddress.countryCode,
                    'region': shippingAddress.region,
                    'regionId': getRegionId(shippingAddress.country_id, shippingAddress.region),
                    'postcode': shippingAddress.postalCode
                };

                let shippingInformationPayload = {
                    addressInformation: {
                        shipping_address: address,
                        billing_address: address,
                        shipping_method_code: this.shippingMethod,
                        shipping_carrier_code: this.shippingMethods[this.shippingMethod].carrier_code
                    }
                };

                const totalsPayload = {
                    'addressInformation': {
                        'address': address,
                        'shipping_method_code': this.shippingMethod,
                        'shipping_carrier_code': this.shippingMethods[this.shippingMethod].carrier_code
                    }
                };

                await setShippingInformation(shippingInformationPayload, this.isProductView);
                console.log("Shipping information set");

                await setTotalsInfo(totalsPayload, this.isProductView);
                console.log("Totals information set");

                return shippingMethods;
            } catch (error) {
                console.error('Failed to retrieve shipping methods:', error);
                throw new Error($t('Failed to retrieve shipping methods. Please try again later.'));
            }
        },


        getPaypalConfiguration: function (paypalPaymentMethod, element) {
            const paypalStyles = getPaypalStyles();
            const config = configModel().getConfig();
            //const countryCode = config.countryCode;
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
                        if (response.action) {
                            console.log(response);
                            // The Component handles the action object from the /payments response.
                            component.handleAction(response.action);
                        } else {
                            // Your function to show the final result to the shopper.
                            showFinalResult(response);
                        }
                    }).catch((error) => {
                        console.error('Payment initiation failed', error);
                    });
                },
                onShippingAddressChange: async (data, actions, component) => {
                    try {
                        console.log("Fetching shipping methods...");
                        const shippingMethods = await this.getAndSetShippingMethods(data.shippingAddress, this.isProductView);
                        console.log("Shipping methods fetched:", shippingMethods);

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
                    // Example: PostNL is unavailable.
                    // if (data.selectedShippingOption.label.includes('PostNL')) {
                    //     return actions.reject(data.errors.METHOD_UNAVAILABLE);
                    // }
                    console.log(data);

                    const currentPaymentData = component.paymentData;
                    console.log(currentPaymentData);
                    try {
                        // Call your backend to update the order with the selected shipping method
                        const response = await updatePaypalOrder.updateOrder(
                            quote.getQuoteId(),
                            currentPaymentData,
                            data.selectedShippingOption
                        );

                        // Update the Component paymentData value with the new one.
                        component.updatePaymentData(response.paymentData);
                    } catch (error) {
                        console.error('Failed to update PayPal order:', error);
                    }
                },
                onShopperDetails: (shopperDetails, rawData, actions) => {
                    // Handle the shopper's details
                    console.log('Shopper Details:', shopperDetails);
                    console.log('Raw Data:', rawData);

                    // Example: Use the shopper's details for shipping label or other purposes
                    const shippingAddress = shopperDetails.deliveryAddress;

                    // Here you can save the shopper's details to your order or do other necessary actions

                    // Resolve the action to continue the flow
                    actions.resolve();
                },
                amount: {
                    currency: currency,
                    value: this.isProductView
                        ? formatAmount(totalsModel().getTotal() * 100)
                        : formatAmount(getCartSubtotal() * 100)
                },
            };

            return paypalBaseConfiguration;
        }
    });
});
