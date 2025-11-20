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
        'ko',
        'uiComponent',
        'Adyen_Payment/js/adyen',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_ExpressCheckout/js/model/adyen-express-configuration',
        'Adyen_ExpressCheckout/js/helpers/getGooglePayStyles'
    ],
    function(
        ko,
        Component,
        AdyenWeb,
        adyenConfiguration,
        adyenExpressConfiguration,
        getGooglePayStyles
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Adyen_ExpressCheckout/checkout/shipping/googlepay'
            },

            initObservable: function() {
                this._super().observe([
                    'isAvailable'
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
                        this.googlePayComponent.mount("#test-googlepay-node");
                    }.bind(this))
                    .catch(function (error) {
                        console.log(error);
                    }.bind(this));
            },

            handleOnSubmit : function() {

            },

            handleOnAdditionalDetails: function() {

            },

            handleOnPaymentDataChanged: function(data) {
                return new Promise((resolve, reject) => {
                    console.log(data);

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
                });
            },

            handleOnError: function() {

            }
        });
    }
);
