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
    'Adyen_ExpressCheckout/js/model/adyen-payment-service',
    'Adyen_ExpressCheckout/js/actions/activateCart',
    'Adyen_ExpressCheckout/js/actions/cancelCart',
    'Adyen_ExpressCheckout/js/actions/createPayment',
    'Adyen_ExpressCheckout/js/actions/getShippingMethods',
    'Adyen_ExpressCheckout/js/actions/getExpressMethods',
    'Adyen_ExpressCheckout/js/actions/getPaymentStatus',
    'Adyen_ExpressCheckout/js/actions/setShippingInformation',
    'Adyen_ExpressCheckout/js/actions/setBillingAddress',
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
    'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
    'Adyen_ExpressCheckout/js/model/maskedId',
    'Adyen_ExpressCheckout/js/model/config',
    'Adyen_ExpressCheckout/js/model/countries',
    'Adyen_ExpressCheckout/js/model/totals',
    'Adyen_ExpressCheckout/js/model/currency',
    'Adyen_ExpressCheckout/js/model/virtualQuote',
    'Adyen_ExpressCheckout/js/helpers/getCurrentPage'
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
        getPaymentStatus,
        setShippingInformation,
        setBillingAddress,
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
        getMaskedIdFromCart,
        maskedIdModel,
        configModel,
        countriesModel,
        totalsModel,
        currencyModel,
        virtualQuoteModel,
        getCurrentPage
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
                    virtualQuoteModel().setIsVirtual(false);

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
                virtualQuoteModel().setIsVirtual(true, response);

                cart.subscribe(function () {
                    this.reloadGooglePayButton(element);
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
                const adyenData = window.adyenData;
                let currentPage = getCurrentPage(this.isProductView, element);

                this.checkoutComponent = await new AdyenCheckout({
                    locale: config.locale,
                    clientKey: config.originkey,
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
                    paymentMethodsResponse: getPaymentMethod('googlepay', this.isProductView),
                    onAdditionalDetails: this.handleOnAdditionalDetails.bind(this),
                    isExpress: true,
                    expressPage: currentPage,
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

                    virtualQuoteModel().setIsVirtual(true, pdpResponse);
                    setExpressMethods(pdpResponse);
                    totalsModel().setTotal(pdpResponse.totals.grand_total);
                } else {
                    virtualQuoteModel().setIsVirtual(false);
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
                const isVirtual = virtualQuoteModel().getIsVirtual();
                let currency;

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[googlePaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                }

                let configuration = {
                    showPayButton: true,
                    countryCode: config.countryCode,
                    environment: config.checkoutenv.toUpperCase(),
                    showButton: true,
                    emailRequired: true,
                    shippingAddressRequired: !isVirtual,
                    shippingOptionRequired: !isVirtual,
                    shippingAddressParameters: {
                        phoneNumberRequired: true
                    },
                    billingAddressRequired: true,
                    billingAddressParameters: {
                        format: 'FULL',
                        phoneNumberRequired: true
                    },
                    isExpress: true,
                    transactionInfo: {
                        totalPriceStatus: 'ESTIMATED',
                        totalPrice: this.isProductView
                            ? formatAmount(totalsModel().getTotal())
                            : formatAmount(getCartSubtotal()),
                        currencyCode: currency
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

                if (!isVirtual) {
                    configuration.callbackIntents = ['SHIPPING_ADDRESS', 'SHIPPING_OPTION'];
                    configuration.paymentDataCallbacks = {
                        onPaymentDataChanged: this.onPaymentDataChanged.bind(this)
                    };
                }

                return configuration;
            },

            onPaymentDataChanged: function (data) {
                return new Promise((resolve, reject) => {
                    if (virtualQuoteModel().getIsVirtual()) {
                        resolve();
                    }

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

                        // If the shipping_method is not available, remove it from the response array.
                        for (let key in response) {
                            if (response[key].available === false) {
                                response.splice(key, 1);
                            }
                        }

                        // Stop if no shipping methods.
                        if (response.length === 0) {
                            reject($t('There are no shipping methods available for you right now. Please try again or use an alternative payment method.'));
                            return;
                        }

                        this.shippingMethods = response;
                        const selectedShipping = data.shippingOptionData.id === 'shipping_option_unselected'
                            ? response[0]
                            : response.find(({ method_code: id }) => id === data.shippingOptionData.id);
                        const regionId = getRegionId(data.shippingAddress.countryCode,
                            data.shippingAddress.administrativeArea || data.shippingAddress.locality,
                            true
                        );

                        // Create payload to get totals and use Dummy values where we do not get them
                        const address = {
                            'countryId': data.shippingAddress.countryCode,
                            'region': data.shippingAddress.locality,
                            'regionId': regionId,
                            'regionCode': null,
                            'postcode': data.shippingAddress.postalCode,
                            'firstname': '',
                            'lastname': '',
                            'city': '',
                            'telephone' : '',
                            'street': ['', ''],

                        };
                        const totalsPayload = {
                            'addressInformation': {
                                'address': address,
                                'shipping_method_code': selectedShipping.method_code,
                                'shipping_carrier_code': selectedShipping.carrier_code
                            }
                        };
                        // Create payload to update quote and quote_address
                        const shippingInformationPayload = {
                            'addressInformation': {
                                'shipping_address': address,
                                'billing_address': address,
                                'shipping_method_code': selectedShipping.method_code,
                                'shipping_carrier_code': selectedShipping.carrier_code
                            }
                        };

                        setShippingInformation(shippingInformationPayload, this.isProductView);
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
                                        totalPrice: (totals.grand_total).toString(),
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
                const isVirtual = virtualQuoteModel().getIsVirtual();

                activateCart(this.isProductView).then(function () {
                    self.setBillingAddress(paymentData).done(function () {
                        if (!isVirtual) {
                            self.setShippingInformation(paymentData).done(function () {
                                self.placeOrder(paymentData);
                            });
                        } else {
                            self.placeOrder(paymentData);
                        }
                    });
                });
            },

            placeOrder: function (paymentData) {
                let self = this;
                let componentData = this.googlePayComponent.data;

                const payload = {
                    email: paymentData.email,
                    paymentMethod: {
                        method: 'adyen_googlepay',
                        additional_data: {
                            brand_code: this.googlePayComponent.props.type,
                            stateData: JSON.stringify(componentData),
                            frontendType: 'default'
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
                    .done(function (orderId) {
                        if (!!orderId) {
                            self.orderId = orderId;

                            getPaymentStatus(orderId, self.isProductView).then(function (responseJSON) {
                                self.handleAdyenResult(responseJSON, orderId);
                            });
                        }
                    })
                    .fail(function (e) {
                        console.error('Adyen GooglePay Unable to take payment', e);
                    });
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
                        'shipping_method_code': shippingMethod.method_code,
                        'shipping_carrier_code': shippingMethod.carrier_code,
                        'extension_attributes': getExtensionAttributes(paymentData)
                    }
                };

                return setShippingInformation(payload, this.isProductView);
            },

            setBillingAddress: function (paymentData) {
                let payload = {
                    'address': this.mapAddress(paymentData.paymentMethodData.info.billingAddress),
                    'useForShipping': false
                };

                return setBillingAddress(payload, this.isProductView);
            },

            handleOnAdditionalDetails: function (result) {
                const self = this;
                let request = result.data;
                adyenPaymentModal.hideModalLabel(this.modalLabel);
                fullScreenLoader.startLoader();
                let popupModal = self.showModal();

                adyenPaymentService.paymentDetails(request, self.orderId).
                done(function(responseJSON) {
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

                if (action.type === 'threeDS2' || action.type === 'await') {
                    popupModal = self.showModal();
                }

                try {
                    self.checkoutComponent.createFromAction(action, {
                        onActionHandled: function (event) {
                            if (event.componentType === "3DS2Challenge") {
                                fullScreenLoader.stopLoader();
                                popupModal.modal('openModal');
                            }
                        }
                    }).mount('#' + this.modalLabel);
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
                    this.isPlaceOrderActionAllowed,
                    false
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
