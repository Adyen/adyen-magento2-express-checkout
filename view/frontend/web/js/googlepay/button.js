define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Adyen_Payment/js/adyen',
    'Adyen_Payment/js/model/adyen-payment-modal',
    'Adyen_Payment/js/model/adyen-payment-service',
    'Adyen_ExpressCheckout/js/actions/activateCart',
    'Adyen_ExpressCheckout/js/actions/cancelCart',
    'Adyen_ExpressCheckout/js/actions/createPayment',
    'Adyen_ExpressCheckout/js/actions/getShippingMethods',
    'Adyen_ExpressCheckout/js/actions/getExpressMethods',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
    'Adyen_ExpressCheckout/js/helpers/formatAmount',
    'Adyen_ExpressCheckout/js/helpers/formatCurrency',
    'Adyen_ExpressCheckout/js/helpers/getCartSubtotal',
    'Adyen_ExpressCheckout/js/helpers/getExtensionAttributes',
    'Adyen_ExpressCheckout/js/helpers/getGooglePayStyles',
    'Adyen_ExpressCheckout/js/helpers/getPaymentMethod',
    'Adyen_ExpressCheckout/js/helpers/getPdpForm',
    'Adyen_ExpressCheckout/js/helpers/getPdpPriceBox',
    'Adyen_ExpressCheckout/js/helpers/getRegionId',
    'Adyen_ExpressCheckout/js/helpers/isConfigSet',
    'Adyen_ExpressCheckout/js/helpers/redirectToSuccess',
    'Adyen_ExpressCheckout/js/helpers/setExpressMethods',
    'Adyen_ExpressCheckout/js/helpers/validatePdpForm',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/countries',
    'Adyen_ExpressCheckout/js/model/totals',
    'Adyen_ExpressCheckout/js/model/currency',
    'mage/cookies',
],
    function (
        $,
        ko,
        Component,
        $t,
        customerData,
        fullScreenLoader,
        quote,
        AdyenCheckout,
        adyenPaymentModal,
        adyenPaymentService,
        activateCart,
        cancelCart,
        createPayment,
        getShippingMethods,
        getExpressMethods,
        setShippingInformation,
        setTotalsInfo,
        formatAmount,
        formatCurrency,
        getCartSubtotal,
        getExtensionAttributes,
        getGooglePayStyles,
        getPaymentMethod,
        getPdpForm,
        getPdpPriceBox,
        getRegionId,
        isConfigSet,
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
            isPlaceOrderActionAllowed: ko.observable(
                quote.billingAddress() != null),

            defaults: {
                shippingMethods: {},
                googlePayToken: null,
                googlePayAllowed: null,
                isProductView: false,
                maskedId: null,
                googlePayComponent: null,
                modalLabel: 'googlepay_actionmodal',
                orderId: 0
            },

            initialize: async function (config, element) {
                this._super()
                    .observe([
                        'googlePayToken',
                        'googlePayAllowed'
                    ]);

                configModel().setConfig(config);
                countriesModel();

                this.isProductView = config.isProductView;

                // If express methods is not set then set it.
                if (this.isProductView) {
                    this.initializeOnPDP(config, element);
                } else {
                    let googlePaymentMethod = await getPaymentMethod('googlepay', this.isProductView);

                    if (!googlePaymentMethod) {
                        const cart = customerData.get('cart');
                        cart.subscribe(function () {
                            this.reloadGooglePayButton(element);
                        }.bind(this));
                    } else {
                        if (!isConfigSet(googlePaymentMethod, ['gatewayMerchantId', 'merchantId'])) {
                            console.log('Required configuration for Google Pay is missing.');
                            return;
                        }
                        this.initialiseGooglePayComponent(googlePaymentMethod, element);
                    }
                }
            },

            initializeOnPDP: async function (config, element) {
                const response = await getExpressMethods().getRequest(element);
                const cart = customerData.get('cart');

                cart.subscribe(function () {
                    this.reloadGooglePayButton(element);
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
                        .then(function () {
                            this.reloadGooglePayButton(element);
                        }.bind(this))
                        .catch(function (error) {
                            console.log(error);
                        });
                }.bind(this));

                let googlePaymentMethod = await getPaymentMethod('googlepay', this.isProductView);

                if (!isConfigSet(googlePaymentMethod, ['gatewayMerchantId', 'merchantId'])) {
                }

                this.initialiseGooglePayComponent(googlePaymentMethod, element);
            },

            initialiseGooglePayComponent: async function (googlePaymentMethod, element) {
                const config = configModel().getConfig();
                this.checkoutComponent = await new AdyenCheckout({
                    locale: config.locale,
                    clientKey: config.originkey,
                    environment: config.checkoutenv,
                    paymentMethodsResponse: getPaymentMethod('googlepay', this.isProductView),
                    onAdditionalDetails: this.handleOnAdditionalDetails.bind(this),
                    risk: {
                        enabled: false
                    }
                });
                const googlePayConfig = this.getGooglePayConfig(googlePaymentMethod, element);

                this.googlePayComponent = this.checkoutComponent.create(googlePaymentMethod, googlePayConfig);

                this.googlePayComponent.isAvailable()
                    .then(function () {
                        this.googlePayAllowed(true);
                        this.googlePayComponent.mount(element);
                    }.bind(this))
                    .catch(function (error) {
                        console.log(error);
                        this.googlePayAllowed(false);
                    }.bind(this));
            },

            unmountGooglePay: function () {
                if (this.googlePayComponent) {
                    this.googlePayComponent.unmount();
                }
            },

            reloadGooglePayButton: async function (element) {
                let googlePaymentMethod = await getPaymentMethod('googlepay', this.isProductView);

                if (this.isProductView) {
                    const pdpResponse = await getExpressMethods().getRequest(element);

                    setExpressMethods(pdpResponse);
                    totalsModel().setTotal(pdpResponse.totals.grand_total);
                }

                this.unmountGooglePay();

                if (!isConfigSet(googlePaymentMethod, ['gatewayMerchantId', 'merchantId'])) {
                    return;
                }

                this.initialiseGooglePayComponent(googlePaymentMethod, element);
            },

            getGooglePayConfig: function (googlePaymentMethod, element) {
                const googlePayStyles = getGooglePayStyles();
                const config = configModel().getConfig();
                const pdpForm = getPdpForm(element);
                let currency;

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[googlePaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                }

                return {
                    showPayButton: true,
                    countryCode: config.countryCode,
                    environment: config.checkoutenv.toUpperCase(),
                    showButton: true,
                    emailRequired: true,
                    shippingAddressRequired: true,
                    shippingOptionRequired: true,
                    shippingAddressParameters: {
                        phoneNumberRequired: true
                    },
                    billingAddressRequired: true,
                    billingAddressParameters: {
                        format: 'FULL',
                        phoneNumberRequired: true
                    },
                    callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION'],
                    transactionInfo: {
                        totalPriceStatus: 'ESTIMATED',
                        totalPrice: this.isProductView
                            ? formatAmount(totalsModel().getTotal())
                            : formatAmount(getCartSubtotal()),
                        currencyCode: currency
                    },
                    paymentDataCallbacks: {
                    onPaymentDataChanged: this.onPaymentDataChanged.bind(this)
                    },
                    allowedPaymentMethods: ['CARD'],
                    phoneNumberRequired: true,
                    configuration: {
                        gatewayMerchantId: googlePaymentMethod.configuration.gatewayMerchantId,
                        merchantId: googlePaymentMethod.configuration.merchantId,
                        merchantName: config.merchantAccount
                    },
                    onAuthorized: this.startPlaceOrder.bind(this),
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function () {},
                    onError: () => cancelCart(this.isProductView),
                    ...googlePayStyles
                };
            },

            onPaymentDataChanged: function (data) {
                return new Promise((resolve, reject) => {
                    const payload = {
                        address: {
                            country_id: data.shippingAddress.countryCode,
                            postcode: data.shippingAddress.postalCode,
                            street: ['']
                        }
                    };

                    activateCart(this.isProductView)
                        .then(() => getShippingMethods(payload, this.isProductView))
                        .then(function (response) {
                        // Stop if no shipping methods.
                        if (response.length === 0) {
                            reject($t('There are no shipping methods available for you right now. Please try again or use an alternative payment method.'));
                            return;
                        }

                        this.shippingMethods = response;
                        const selectedShipping = data.shippingOptionData.id === 'shipping_option_unselected'
                            ? response[0]
                            : response.find(({ method_code: id }) => id === data.shippingOptionData.id);
                        const regionId = getRegionId(data.shippingAddress.countryCode, data.shippingAddress.locality);
                        // Create payload to get totals
                        const totalsPayload = {
                            'addressInformation': {
                                'address': {
                                    'countryId': data.shippingAddress.countryCode,
                                    'region': data.shippingAddress.locality,
                                    'regionId': regionId,
                                    'postcode': data.shippingAddress.postalCode
                                },
                                'shipping_method_code': selectedShipping.method_code,
                                'shipping_carrier_code': selectedShipping.carrier_code
                            }
                        };

                        setTotalsInfo(totalsPayload, this.isProductView)
                            .done(function (totals) {
                                const shippingMethods = response.map((shippingMethod) => {
                                    const label = shippingMethod.price_incl_tax
                                        ? formatCurrency(shippingMethod.price_incl_tax, totals.quote_currency_code) + ' - ' + shippingMethod.method_title
                                        : shippingMethod.method_title;

                                    return {
                                        id: shippingMethod.method_code,
                                        label: label,
                                        description: shippingMethod.carrier_title
                                    };
                                });

                                const paymentDataRequestUpdate = {
                                    newShippingOptionParameters: {
                                        defaultSelectedOptionId: selectedShipping.method_code,
                                        shippingOptions: shippingMethods
                                    },
                                    newTransactionInfo: {
                                        displayItems: [
                                            {
                                                label: 'Shipping',
                                                type: 'LINE_ITEM',
                                                price: totals.shipping_incl_tax.toString(),
                                                status: 'FINAL'
                                            }
                                        ],
                                        currencyCode: totals.quote_currency_code,
                                        totalPriceStatus: 'FINAL',
                                        totalPrice: totals.grand_total.toString(),
                                        totalPriceLabel: 'Total',
                                        countryCode: configModel().getConfig().countryCode
                                    }
                                };

                                resolve(paymentDataRequestUpdate);
                            })
                            .fail(reject);
                    }.bind(this));
                });
            },

            startPlaceOrder: function (paymentData) {
                let self = this;
                let componentData = self.googlePayComponent.data;

                this.setShippingInformation(paymentData)
                    .done(function () {
                        const payload = {
                            email: paymentData.email,
                            shippingAddress: this.mapAddress(paymentData.shippingAddress),
                            billingAddress: this.mapAddress(paymentData.paymentMethodData.info.billingAddress),
                            paymentMethod: {
                                method: 'adyen_googlepay',
                                additional_data: {
                                    brand_code: self.googlePayComponent.props.type,
                                    stateData: JSON.stringify(componentData)
                                },
                                extension_attributes: getExtensionAttributes(paymentData)
                            }
                        };

                        if (window.checkout && window.checkout.agreementIds) {
                            payload.paymentMethod.extension_attributes = {
                                agreement_ids: window.checkout.agreementIds
                            };
                        }

                        createPayment(JSON.stringify(payload), this.isProductView)
                            .done( function (orderId) {
                                if (!!orderId) {
                                    self.orderId = orderId;
                                    adyenPaymentService.getOrderPaymentStatus(orderId).
                                    done(function (responseJSON) {
                                        self.handleAdyenResult(responseJSON, orderId);
                                    })
                                }
                            })
                            .fail(function (e) {
                                console.error('Adyen GooglePay Unable to take payment', e);
                            });
                    }.bind(this));
            },



            setShippingInformation: function (paymentData) {
                const shippingMethod = this.shippingMethods.find(function (method) {
                    return method.method_code === paymentData.shippingOptionData.id;
                });
                let payload = {
                    'addressInformation': {
                        'shipping_address': {
                            ...this.mapAddress(paymentData.shippingAddress),
                            'same_as_billing': 0,
                            'customer_address_id': 0,
                            'save_in_address_book': 0
                        },
                        'billing_address': {
                            ...this.mapAddress(paymentData.paymentMethodData.info.billingAddress),
                            'same_as_billing': 0,
                            'customer_address_id': 0,
                            'save_in_address_book': 0
                        },
                        'shipping_method_code': shippingMethod.method_code,
                        'shipping_carrier_code': shippingMethod.carrier_code,
                        'extension_attributes': getExtensionAttributes(paymentData)
                    }
                };

                return setShippingInformation(payload, this.isProductView);
            },

            handleOnAdditionalDetails: function (result) {
                const self = this;
                let request = result.data;
                adyenPaymentModal.hideModalLabel(this.modalLabel);
                fullScreenLoader.startLoader();
                request.orderId = self.orderId;
                let popupModal = self.showModal();

                adyenPaymentService.paymentDetails(request).
                done(function(responseJSON) {
                    fullScreenLoader.stopLoader();
                    self.handleAdyenResult(responseJSON, self.orderId);
                }).
                fail(function(response) {
                    self.closeModal(popupModal);
                    errorProcessor.process(response, self.messageContainer);
                    self.isPlaceOrderActionAllowed(true);
                    fullScreenLoader.stopLoader();
                });
            },

            handleAdyenResult: function (responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    redirectToSuccess()
                } else {
                    // Handle action
                    self.handleAction(response.action, orderId);
                }
            },

            handleAction: function(action, orderId) {
                var self = this;
                let popupModal;

                fullScreenLoader.stopLoader();

                if (action.type === 'threeDS2' || action.type === 'await') {
                    popupModal = self.showModal();
                }

                try {
                    self.checkoutComponent.createFromAction(action).mount('#' + this.modalLabel);
                } catch (e) {
                    console.log(e);
                    self.closeModal(popupModal);
                }
            },

            showModal: function() {
                let actionModal = adyenPaymentModal.showModal(
                    adyenPaymentService,
                    fullScreenLoader,
                    this.messageContainer,
                    this.orderId,
                    this.modalLabel,
                    this.isPlaceOrderActionAllowed
                );

                $("." + this.modalLabel + " .action-close").hide();

                return actionModal;
            },

            closeModal: function(popupModal) {
                adyenPaymentModal.closeModal(popupModal, this.modalLabel)
            },

            mapAddress: function (address) {
                const [firstname, ...lastname] = address.name.split(' ');

                return {
                    'telephone': typeof address.phoneNumber !== 'undefined' ? address.phoneNumber : '',
                    'firstname': firstname,
                    'lastname': lastname.length ? lastname.join(' ') : '',
                    'street': [
                        address.address1,
                        address.address2
                    ],
                    'city': address.locality,
                    'region': address.administrativeArea,
                    'region_id': getRegionId(
                        address.countryCode.toUpperCase(),
                        address.administrativeArea
                    ),
                    'region_code': null,
                    'country_id': address.countryCode.toUpperCase(),
                    'postcode': address.postalCode
                };
            }
        });
    }
);
