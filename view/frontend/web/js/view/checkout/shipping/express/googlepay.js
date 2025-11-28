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
        'Adyen_Payment/js/adyen',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_ExpressCheckout/js/model/adyen-express-configuration',
        'Adyen_ExpressCheckout/js/helpers/getGooglePayStyles',
        'Adyen_ExpressCheckout/js/actions/getShippingMethods',
        'Adyen_ExpressCheckout/js/helpers/getRegionId',
        'Adyen_ExpressCheckout/js/actions/setShippingInformation',
        'Adyen_ExpressCheckout/js/actions/setBillingAddress',
        'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
        'Adyen_ExpressCheckout/js/helpers/formatCurrency',
        'Adyen_ExpressCheckout/js/helpers/getExtensionAttributes',
        'Adyen_ExpressCheckout/js/actions/createPayment',
        'Adyen_ExpressCheckout/js/model/adyen-loader',
        'Adyen_ExpressCheckout/js/actions/getPaymentStatus',
        'Adyen_ExpressCheckout/js/helpers/redirectToSuccess',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
        'Adyen_ExpressCheckout/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/error-processor'
    ],
    function(
        $,
        ko,
        Component,
        AdyenWeb,
        adyenConfiguration,
        adyenExpressConfiguration,
        getGooglePayStyles,
        getShippingMethods,
        getRegionId,
        setShippingInformation,
        setBillingAddress,
        setTotalsInfo,
        formatCurrency,
        getExtensionAttributes,
        createPayment,
        loader,
        getPaymentStatus,
        redirectToSuccess,
        adyenPaymentModal,
        getMaskedIdFromCart,
        adyenPaymentService,
        errorProcessor
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Adyen_ExpressCheckout/checkout/shipping/express',
                checkoutComponent: null,
                modalLabel: 'adyen-checkout-action_modal',
                componentRootNode: 'adyen-express-checkout__googlepay',
                orderId: 0,
                shippingMethods: {},
            },

            initObservable: function() {
                this._super().observe([
                    'isAvailable',
                    'isPlaceOrderActionAllowed'
                ]);

                return this;
            },


            initialize: function () {
                this._super();
                this.isAvailable(adyenExpressConfiguration.getIsGooglePayEnabledOnShipping());
            },

            buildPaymentMethodComponent: async function() {
                const paymentMethodsResponse = adyenExpressConfiguration.getPaymentMethodsResponse();
                const adyenData = window.adyenData;
                const googlePayStyles = getGooglePayStyles();
                const isVirtual = adyenExpressConfiguration.getIsVirtual();
                let self = this;

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
                    paymentMethodsResponse: paymentMethodsResponse,
                    onAdditionalDetails: this.handleOnAdditionalDetails.bind(this),
                    risk: {
                        enabled: false
                    }
                });

                const paymentMethodsConfigurationObject =
                    paymentMethodsResponse['paymentMethods'].find(function (paymentMethod) {
                        return paymentMethod.type === "googlepay";
                    })

                let configuration = {
                    configuration: {
                        gatewayMerchantId: paymentMethodsConfigurationObject['configuration'].gatewayMerchantId,
                        merchantId: paymentMethodsConfigurationObject['configuration'].merchantId,
                        merchantName: adyenConfiguration.getMerchantAccount()
                    },
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
                    expressPage: 'shipping',
                    transactionInfo: {
                        totalPriceStatus: 'ESTIMATED',
                        totalPrice: adyenExpressConfiguration.getAmountValue(),
                        currencyCode: adyenExpressConfiguration.getCurrency()
                    },
                    allowedPaymentMethods: ['CARD'],
                    phoneNumberRequired: true,
                    onSubmit: this.handleOnSubmit.bind(this),
                    onError: this.handleOnError.bind(this),
                    ...googlePayStyles
                };

                if (!isVirtual) {
                    configuration.callbackIntents = ['SHIPPING_ADDRESS', 'SHIPPING_OPTION'];
                    configuration.paymentDataCallbacks = {
                        onPaymentDataChanged: this.handleOnPaymentDataChanged.bind(this)
                    };
                }

                this.googlePayComponent = await window.AdyenWeb.createComponent(
                    "googlepay",
                    this.checkoutComponent,
                    configuration
                );

                this.googlePayComponent.isAvailable()
                    .then(function () {
                        this.googlePayComponent.mount("#" + self.componentRootNode);
                    }.bind(this))
                    .catch(function (error) {
                        console.log(error);
                    }.bind(this));
            },

            handleOnSubmit: async function (state, component, actions) {
                let self = this;
                const paymentData = component.state;

                loader.startLoader();

                await self.setBillingAddress(paymentData);

                if (!adyenExpressConfiguration.getIsVirtual()) {
                    await self.setShippingInformation(paymentData);
                }

                const payload = {
                    email: paymentData.authorizedEvent.email,
                    paymentMethod: {
                        method: 'adyen_googlepay',
                        additional_data: {
                            stateData: JSON.stringify(state.data),
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
                                const response = JSON.parse(responseJSON);
                                actions.resolve({resultCode: response.resultCode});

                                self.handleAdyenResult(responseJSON, orderId);
                            });
                        }
                    })
                    .fail(function (e) {
                        actions.reject();
                        console.error('Adyen GooglePay Unable to take payment', e);
                        loader.stopLoader();
                    });
            },

            handleOnAdditionalDetails: function (result) {
                const self = this;
                const quoteId = getMaskedIdFromCart();

                let request = result.data;
                let popupModal = self.showModal();

                adyenPaymentModal.hideModalLabel(this.modalLabel);
                loader.startLoader();

                adyenPaymentService.paymentDetails(request, self.orderId, quoteId)
                    .done(function(responseJSON) {
                        self.handleAdyenResult(responseJSON, self.orderId);
                    })
                    .fail(function(response) {
                        self.closeModal(popupModal);
                        errorProcessor.process(response, self.messageContainer);
                        self.isPlaceOrderActionAllowed(true);
                        loader.stopLoader();
                    });
            },

            handleOnPaymentDataChanged: function(data) {
                return new Promise((resolve, reject) => {
                    if (adyenExpressConfiguration.getIsVirtual()) {
                        resolve();
                    }

                    const payload = {
                        address: {
                            country_id: data.shippingAddress.countryCode,
                            postcode: data.shippingAddress.postalCode,
                            street: ['']
                        }
                    };

                    getShippingMethods(payload, false).then(function (response) {
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
                        setTotalsInfo(totalsPayload, false)
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
                                        countryCode: adyenExpressConfiguration.getCountryCode()
                                    }
                                };

                                resolve(paymentDataRequestUpdate);
                            })
                            .fail(reject);
                    }.bind(this));
                });
            },

            handleOnError: function(error, component) {
                errorProcessor.process(error, this.messageContainer);
            },

            handleAdyenResult: function (responseJSON, orderId) {
                var self = this;
                var response = JSON.parse(responseJSON);

                if (!!response.isFinal) {
                    // Status is final redirect to the success page
                    loader.stopLoader();
                    redirectToSuccess();
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
                                loader.stopLoader();
                                popupModal.modal('openModal');
                            }
                        }
                    }).mount('#' + this.modalLabel);
                } catch (e) {
                    console.log(e);
                    loader.stopLoader();
                    self.closeModal(popupModal);
                }
            },

            showModal: function() {
                let actionModal = adyenPaymentModal.showModal(
                    adyenPaymentService,
                    loader,
                    this.messageContainer,
                    this.orderId,
                    this.modalLabel,
                    this.isPlaceOrderActionAllowed,
                    false
                );

                $("." + this.modalLabel + " .action-close").hide();

                return actionModal;
            },

            setShippingInformation: function (paymentData) {
                const shippingMethod = this.shippingMethods.find(function (method) {
                    return method.method_code === paymentData.authorizedEvent.shippingOptionData.id;
                });
                let payload = {
                    'addressInformation': {
                        'shipping_address': {
                            ...this.mapAddress(paymentData.authorizedEvent.shippingAddress),
                            'same_as_billing': 0,
                            'customer_address_id': 0,
                            'save_in_address_book': 0
                        },
                        'shipping_method_code': shippingMethod.method_code,
                        'shipping_carrier_code': shippingMethod.carrier_code,
                        'extension_attributes': getExtensionAttributes(paymentData)
                    }
                };

                return setShippingInformation(payload, false);
            },

            setBillingAddress: function (paymentData) {
                let payload = {
                    'address': this.mapAddress(paymentData.authorizedEvent.paymentMethodData.info.billingAddress),
                    'useForShipping': false
                };

                return setBillingAddress(payload, false);
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
            },

            closeModal: function(popupModal) {
                adyenPaymentModal.closeModal(popupModal, this.modalLabel)
            },

            getComponentRootNoteId: function () {
                return this.componentRootNode;
            }
        });
    }
);
