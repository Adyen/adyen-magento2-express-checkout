define([
        'jquery',
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
        'Adyen_ExpressCheckout/js/helpers/formatCurrency',
        'Adyen_ExpressCheckout/js/helpers/getCartSubtotal',
        'Adyen_ExpressCheckout/js/helpers/getExtensionAttributes',
        'Adyen_ExpressCheckout/js/helpers/getAmazonPayStyles',
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
        'Adyen_ExpressCheckout/js/model/currency'
    ],
    function (
        $,
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
        formatCurrency,
        getCartSubtotal,
        getExtensionAttributes,
        getAmazonPayStyles,
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
            defaults: {
                shippingMethods: {},
                isProductView: false,
                maskedId: null,
                amazonPayComponent: null
            },

            initialize: async function (config, element) {
                this._super();

                configModel().setConfig(config);
                countriesModel();

                this.isProductView = config.isProductView;

                // If express methods is not set then set it.
                if (this.isProductView) {
                    this.initializeOnPDP(config, element);
                } else {
                    let amazonPaymentMethod = await getPaymentMethod('amazonpay', this.isProductView);

                    if (!amazonPaymentMethod) {
                        const cart = customerData.get('cart');
                        cart.subscribe(function () {
                            this.reloadAmazonPayButton(element);
                        }.bind(this));
                    } else {
                        if (!isConfigSet(amazonPaymentMethod)) {
                            console.log('Required configuration for Amazon Pay is missing.');
                            return;
                        }

                        const url = new URL(location.href);
                        debugger;
                        if (!url.searchParams.has('amazonCheckoutSessionId')) {
                            this.initialiseAmazonPayButtonComponent(amazonPaymentMethod, element, config);
                        } else {
                            if (!url.searchParams.has('amazonExpress')) {
                                this.initialiseAmazonPayOrderComponent(amazonPaymentMethod, element, config);
                            } else {
                                this.initialiseAmazonPayPaymentComponent(amazonPaymentMethod, element, config);
                            }
                        }
                    }
                }
            },

            initializeOnPDP: async function (config, element) {
                const response = await getExpressMethods().getRequest(element);
                const cart = customerData.get('cart');

                cart.subscribe(function () {
                    this.reloadAmazonPayButton(element);
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
                            this.reloadAmazonPayButton(element);
                        }.bind(this))
                        .catch(function (error) {
                            console.log(error);
                        });
                }.bind(this));

                let amazonPaymentMethod = await getPaymentMethod('amazonpay', this.isProductView);

                if (!isConfigSet(amazonPaymentMethod)) {
                    return;
                }

                const url = new URL(location.href);
                if (!url.searchParams.has('amazonCheckoutSessionId')) {
                    this.initialiseAmazonPayButtonComponent(amazonPaymentMethod, element, config);
                } else {
                    if (!url.searchParams.has('amazonExpress')) {
                        this.initialiseAmazonPayOrderComponent(amazonPaymentMethod, element, config);
                    } else {
                        this.initialiseAmazonPayPaymentComponent(amazonPaymentMethod, element, config);
                    }
                }
            },

            initialiseAmazonPayButtonComponent: async function (amazonPaymentMethod, element, config) {
                const checkoutComponent = await new AdyenCheckout({
                    locale: config.locale,
                    originKey: config.originkey,
                    environment: config.checkoutenv,
                    risk: {
                        enabled: false
                    },
                    clientKey: AdyenConfiguration.getClientKey(),
                });
                const amazonPayButtonConfig = this.getAmazonPayButtonConfig(amazonPaymentMethod, element);

                this.amazonPayComponent = checkoutComponent
                    .create(amazonPaymentMethod, amazonPayButtonConfig)
                    .mount(element);
            },

            initialiseAmazonPayOrderComponent: async function(amazonPaymentMethod, element, config) {
                const checkoutComponent = await new AdyenCheckout({
                    locale: config.locale,
                    originKey: config.originkey,
                    environment: config.checkoutenv,
                    risk: {
                        enabled: false
                    },
                    clientKey: AdyenConfiguration.getClientKey(),
                });
                const amazonPayOrderConfig = this.getAmazonPayOrderConfig(amazonPaymentMethod, element);

                this.amazonPayComponent = checkoutComponent
                    .create(amazonPaymentMethod, amazonPayOrderConfig)
                    .mount(element)

                this.amazonPayComponent.getShopperDetails()
                    .then(details => {
                        let displayHtmlKeys = '';
                        let displayHtmlValues = '';
                        if (details.shippingAddress) {
                            let shippingAddress = details.shippingAddress;
                            const keyMap = {
                                name: 'Name',
                                addressLine1: 'Address',
                                addressLine2: 'Address',
                                addressLine3: 'Address',
                                city: 'City',
                                countryCode: 'Country Code',
                                district: 'District',
                                stateOrRegion: 'State/Region',
                                postalCode: 'Postal Code',
                                phoneNumber: 'Phone Number'
                            };
                            const keys = Object.keys(shippingAddress);
                            keys.forEach((key, index) => {
                                if (shippingAddress[key] != null) {
                                    let label = keyMap[key] || key;
                                    displayHtmlKeys += `${label} <br>`;
                                    displayHtmlValues += `${shippingAddress[key]} <br>`;
                                }
                            });
                        }

                        if (details.paymentDescriptor) {
                            let paymentDescriptor = details.paymentDescriptor;
                            displayHtmlKeys += `Payment Descriptor <br>`;
                            displayHtmlValues += `${paymentDescriptor} <br>`;
                        }

                        $('#amazonpay_shopper_details_keys').html(displayHtmlKeys);
                        $('#amazonpay_shopper_details_values').html(displayHtmlValues);
                    }
                )

            },

            initialiseAmazonPayPaymentComponent: async function (amazonPaymentMethod, element, config) {
                const checkoutComponent = await new AdyenCheckout({
                    locale: config.locale,
                    originKey: config.originkey,
                    environment: config.checkoutenv,
                    risk: {
                        enabled: false
                    },
                    clientKey: AdyenConfiguration.getClientKey()
                });

                debugger;
                const amazonPayPaymentConfig = this.getAmazonPayPaymentConfig(amazonPaymentMethod, element);

                const amazonPayComponent = checkoutComponent
                    .create(amazonPaymentMethod, amazonPayPaymentConfig)
                    .mount(element);

                amazonPayComponent.submit();

            },

            unmountAmazonPay: function () {
                if (this.amazonPayComponent) {
                    this.amazonPayComponent.unmount();
                }
            },

            reloadAmazonPayButton: async function (element) {
                const config = configModel().getConfig();
                let amazonPaymentMethod = await getPaymentMethod('amazonpay', this.isProductView);

                if (this.isProductView) {
                    const pdpResponse = await getExpressMethods().getRequest(element);

                    setExpressMethods(pdpResponse);
                    totalsModel().setTotal(pdpResponse.totals.grand_total);
                }

                this.unmountAmazonPay();

                if (!isConfigSet(amazonPaymentMethod)) {
                    return;
                }

                const url = new URL(location.href);
                if (!url.searchParams.has('amazonCheckoutSessionId')) {
                    this.initialiseAmazonPayButtonComponent(amazonPaymentMethod, element, config);
                } else {
                    if (!url.searchParams.has('amazonExpress')) {
                        this.initialiseAmazonPayOrderComponent(amazonPaymentMethod, element, config);
                    } else {
                        this.initialiseAmazonPayPaymentComponent(amazonPaymentMethod, element, config);
                    }
                }
            },

            getAmazonPayButtonConfig: function (amazonPaymentMethod, element) {
                const amazonPayStyles = getAmazonPayStyles();
                const config = configModel().getConfig();
                const pdpForm = getPdpForm(element);
                let url = new URL(location.href);
                let currency;

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[amazonPaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                };

                url.searchParams.delete('amazonCheckoutSessionId');

                // TODO -> create a js helper where you use the magento url builder to build the return url
                const returnUrl = url.href + '/checkout/cart';

                return {
                    showPayButton:true,
                    productType: 'PayAndShip',
                    currency: config.currency,
                    environment: config.checkoutenv.toUpperCase(),
                    returnUrl: returnUrl,
                    configuration: {
                        // TODO -> obtain the values dinamically
                        merchantId: 'A1WI30W6FEGXWD',
                        publicKeyId: 'SANDBOX-AG7IJTK25RUCVKNHKQGBWHK7',
                        storeId: 'amzn1.application-oa2-client.c1175ec6e8f14a0497c486f4bd3a99f5'
                    },
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function () {},
                    onError: () => cancelCart(this.isProductView),
                    ...amazonPayStyles
                };
            },

            getAmazonPayOrderConfig: function (amazonPaymentMethod, element) {
                const amazonPayStyles = getAmazonPayStyles();
                const pdpForm = getPdpForm(element);
                const amazonSessionKey = 'amazonCheckoutSessionId';
                const url = new URL(location.href);
                const amazonPaySessionKey = url.searchParams.get(amazonSessionKey);
                let currency;

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[amazonPaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                };

                url.searchParams.delete('amazonCheckoutSessionId');

                const returnUrl = url.href + '/?amazonExpress=finalize';

                return {
                    amount: {
                        value: this.isProductView
                            ? formatAmount(totalsModel().getTotal() * 100)
                            : formatAmount(getCartSubtotal() * 100),
                        currency: currency
                    },
                    amazonCheckoutSessionId: amazonPaySessionKey,
                    returnUrl: returnUrl,
                    showChangePaymentDetailsButton: true,
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function () {},
                    onError: () => cancelCart(this.isProductView),
                    ...amazonPayStyles
                }
            },

            getAmazonPayPaymentConfig: function (amazonPaymentMethod, element) {
                const amazonPayStyles = getAmazonPayStyles();
                const pdpForm = getPdpForm(element);
                const amazonSessionKey = 'amazonCheckoutSessionId';
                const url = new URL(location.href);
                const amazonPaySessionKey = url.searchParams.get(amazonSessionKey);
                let currency;

                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[amazonPaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                };

                url.searchParams.delete('amazonCheckoutSessionId');

                return {
                    amount: {
                        value: this.isProductView
                            ? formatAmount(totalsModel().getTotal() * 100)
                            : formatAmount(getCartSubtotal() * 100),
                        currency: currency
                    },
                    amazonCheckoutSessionId: amazonPaySessionKey,
                    returnUrl: url.href,
                    showOrderButton: false,
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function (state, component) {
                        console.log('state: ', state);
                        console.log('component: ', component);
                    },
                    onError: () => cancelCart(this.isProductView),
                    ...amazonPayStyles
                }
            }
        });
    }
);
