define([
        'jquery',
        'uiComponent',
        'mage/translate',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/quote',
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
        quote,
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
                        console.log('shopper details: ', details);
                        let self = this,
                            shippingAddress = details.shippingAddress,
                            buyer = details.buyer,
                            streetAddress = shippingAddress.addressLine1.split(" "),
                            nameArr = buyer.name.split(" "),
                            firstname = nameArr[0],
                            lastname = nameArr.slice(1).join(" "),
                            shippingMethods = [],
                            payload = {
                                address: {
                                    city: shippingAddress.city.toLowerCase(),
                                    country_id: shippingAddress.countryCode,
                                    email: buyer.email,
                                    firstname: firstname,
                                    lastname: lastname,
                                    postcode: shippingAddress.postalCode,
                                    region: shippingAddress.stateOrRegion,
                                    region_id: getRegionId(shippingAddress.countryCode, shippingAddress.stateOrRegion),
                                    street: streetAddress,
                                    telephone: buyer.phoneNumber,
                                    save_in_address_book: 0
                                }
                        };

                        getShippingMethods(payload, this.isProductView)
                            .then((result) => {
                                if (result.length === 0) {
                                    reject($t('There are no shipping methods available for you right now. ' +
                                        'Please try again or use an alternative payment method.'));
                                }

                                for (let i = 0; i < result.length; i++) {
                                    if (typeof result[i].method_code !== 'string') {
                                        continue;
                                    }

                                    let method = {
                                        method_code: result[i].method_code,
                                        method_title: result[i].method_title,
                                        carrier_title: result[i].carrier_title ? result[i].carrier_title : '',
                                        carrier_code: result[i].carrier_code ? result[i].carrier_code : '',
                                        amount: parseFloat(result[i].amount).toFixed(2)
                                    };

                                    shippingMethods.push(method);
                                }

                                let shippingStreetAddress = details.shippingAddress.addressLine1.split(" "),
                                    shippingNameArr = details.shippingAddress.name.split(" "),
                                    shippingFirstname = shippingNameArr[0],
                                    shippingLastname = shippingNameArr.slice(1).join(" "),
                                    billingStreetAddress = details.billingAddress.addressLine2.split(" "),
                                    billingNameArr = details.billingAddress.name.split(" "),
                                    billingFirstname = billingNameArr[0],
                                    billingLastname = billingNameArr.slice(1).join(" "),
                                    quotePayload = {
                                        'addressInformation': {
                                            'shipping_address': {
                                                'email': details.buyer.email,
                                                'telephone': details.buyer.phoneNumber,
                                                'firstname': shippingFirstname,
                                                'lastname': shippingLastname,
                                                'street': shippingStreetAddress,
                                                'city': details.shippingAddress.city.toLowerCase(),
                                                'region': details.shippingAddress.stateOrRegion,
                                                'region_id': getRegionId(details.shippingAddress.countryCode, details.shippingAddress.stateOrRegion),
                                                'region_code': null,
                                                'country_id': details.shippingAddress.countryCode,
                                                'postcode': details.shippingAddress.postalCode,
                                                'same_as_billing': 0,
                                                'customer_address_id': 0,
                                                'save_in_address_book': 0
                                            },
                                            'billing_address': {
                                                'email': details.buyer.email,
                                                'telephone': details.buyer.phoneNumber,
                                                'firstname': shippingFirstname,
                                                'lastname': shippingLastname,
                                                'street': shippingStreetAddress,
                                                'city': details.shippingAddress.city.toLowerCase(),
                                                'region': details.shippingAddress.stateOrRegion,
                                                'region_id': getRegionId(details.shippingAddress.countryCode, details.shippingAddress.stateOrRegion),
                                                'region_code': null,
                                                'country_id': details.shippingAddress.countryCode,
                                                'postcode': details.shippingAddress.postalCode,
                                                'same_as_billing': 0,
                                                'customer_address_id': 0,
                                                'save_in_address_book': 0
                                            },
                                            'shipping_method_code': shippingMethods[0].method_code,
                                            'shipping_carrier_code': shippingMethods[0].carrier_code,
                                        }
                                    };

                                setShippingInformation(quotePayload, this.isProductView);
                                console.log('quote updated');
                            })

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

                // TODO -> create a js helper where you use the magento url builder interface to build the return url
                const returnUrl = url.origin + '/checkout/cart';

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

                console.log('quote shipping address: ', quote.shippingAddress());


                if (this.isProductView) {
                    currency = currencyModel().getCurrency();
                } else {
                    const cartData =  customerData.get('cart');
                    const adyenMethods = cartData()['adyen_payment_methods'];
                    const paymentMethodExtraDetails = adyenMethods.paymentMethodsExtraDetails[amazonPaymentMethod.type];
                    currency = paymentMethodExtraDetails.configuration.amount.currency;
                };

                url.searchParams.delete('amazonCheckoutSessionId');
                url.searchParams.delete('amazonExpress');
                let returnUrl = url.origin + '/checkout/onepage/success/'

                return {
                    amazonCheckoutSessionId: amazonPaySessionKey,
                    returnUrl: returnUrl,
                    showOrderButton: false,
                    onClick: function (resolve, reject) {validatePdpForm(resolve, reject, pdpForm);},
                    onSubmit: function (state, component) {
                        component.setStatus('loading');
                        const stateData = JSON.stringify({ paymentMethod: state.data.paymentMethod });

                        console.log('quote: ', quote);
                        console.log('quote shipping address: ', quote.shippingAddress());

                        const payload = {
                            email: "rok.popovledinski@adyen.com",
                            paymentMethod: {
                                method: 'adyen_hpp',
                                additional_data: {
                                    brand_code: state.data.paymentMethod.type,
                                    stateData
                                }
                            }
                        }

                        createPayment(JSON.stringify(payload), false)
                            .then(response => {
                                console.log('response: ', response);
                                component.setStatus('ready');
                                if (response.action) {
                                    console.log('action: ', response.action);
                                } else {
                                    console.log('fail hihi');
                                }
                            })
                    },
                    onError: () => cancelCart(this.isProductView),
                    ...amazonPayStyles
                }
            }
        });
    }
);
