/**
 * PayPal Express on shipping step
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V.
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

define(
    [
        'jquery',
        'ko',
        'uiComponent',
        'mage/translate',
        'Adyen_Payment/js/adyen',
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_ExpressCheckout/js/model/adyen-express-configuration',
        'Adyen_ExpressCheckout/js/helpers/getPaypalStyles',
        'Adyen_ExpressCheckout/js/actions/getShippingMethods',
        'Adyen_ExpressCheckout/js/helpers/getRegionId',
        'Adyen_ExpressCheckout/js/actions/setShippingInformation',
        'Adyen_ExpressCheckout/js/actions/setBillingAddress',
        'Adyen_ExpressCheckout/js/actions/setTotalsInfo',
        'Adyen_ExpressCheckout/js/helpers/formatCurrency',
        'Adyen_ExpressCheckout/js/helpers/getExtensionAttributes',
        'Adyen_ExpressCheckout/js/actions/initPayments',
        'Adyen_ExpressCheckout/js/actions/updatePaypalOrder',
        'Adyen_ExpressCheckout/js/actions/createOrder',
        'Adyen_ExpressCheckout/js/model/adyen-loader',
        'Adyen_ExpressCheckout/js/helpers/redirectToSuccess',
        'Adyen_Payment/js/model/adyen-payment-modal',
        'Adyen_ExpressCheckout/js/helpers/getMaskedIdFromCart',
        'Adyen_ExpressCheckout/js/model/adyen-payment-service',
        'Magento_Checkout/js/model/error-processor'
    ],
    function (
        $,
        ko,
        Component,
        $t,
        AdyenWeb,
        adyenConfiguration,
        adyenExpressConfiguration,
        getPaypalStyles,
        getShippingMethods,
        getRegionId,
        setShippingInformation,
        setBillingAddress,
        setTotalsInfo,
        formatCurrency,
        getExtensionAttributes,
        initPayments,
        updatePaypalOrder,
        createOrderAction,
        loader,
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
                paypalComponent: null,
                modalLabel: 'adyen-checkout-action_modal',
                componentRootNode: 'adyen-express-checkout__paypal',
                orderId: 0,
                shippingMethods: {},
                shippingMethod: null,
                selectedShippingMethod: null,
                shippingAddress: null,
                isInitialized: false,
                isMounting: false,
                latestPaymentData: null
            },

            /**
             * @inheritdoc
             */
            initObservable: function () {
                this._super().observe([
                    'isAvailable',
                    'isPlaceOrderActionAllowed',
                    'isLoading',
                    'errorMessage'
                ]);

                this.isLoading(false);
                this.errorMessage('');

                return this;
            },

            /**
             * @inheritdoc
             */
            initialize: function () {
                this._super();

                this.isAvailable(adyenExpressConfiguration.getIsPayPalEnabledOnShipping());
                this.isPlaceOrderActionAllowed(true);
            },

            /**
             * Public entry point from template.
             */
            buildPaymentMethodComponent: function () {
                if (!this.isAvailable() || this.isInitialized) {
                    return;
                }

                this._buildComponentInternal();
            },

            /**
             *
             * @private
             */
            _buildComponentInternal: async function () {
                if (this.isMounting || this.isInitialized) {
                    return;
                }

                this.isMounting = true;
                this.isLoading(true);
                this.errorMessage('');

                try {
                    const elementId = this.componentRootNode;
                    const rootEl = document.getElementById(elementId);

                    if (!rootEl) {
                        console.warn('Adyen PayPal Express – root element not found:', elementId);
                        return;
                    }

                    const paymentMethodsResponse = adyenExpressConfiguration.getPaymentMethodsResponse();
                    const adyenData = window.adyenData || {};
                    const paypalStyles = getPaypalStyles();
                    const isVirtual = adyenExpressConfiguration.getIsVirtual();

                    const paypalMethod = paymentMethodsResponse.paymentMethods &&
                        paymentMethodsResponse.paymentMethods.find(function (pm) {
                            return pm.type === 'paypal';
                        });

                    if (!paypalMethod) {
                        console.warn('Adyen PayPal Express – PayPal is not available in paymentMethodsResponse');
                        return;
                    }

                    // Create checkout instance
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

                    const configuration = {
                        countryCode: adyenExpressConfiguration.getCountryCode(),
                        environment: adyenConfiguration.getCheckoutEnvironment().toUpperCase(),
                        isExpress: true,
                        expressPage: 'shipping',
                        configuration: paypalMethod.configuration,
                        amount: {
                            currency: adyenExpressConfiguration.getCurrency(),
                            value: adyenExpressConfiguration.getAmountValue()
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
                        style: paypalStyles,

                        onSubmit: this.handleOnSubmit.bind(this),
                        onShippingAddressChange: !isVirtual ?
                            this.handleOnShippingAddressChange.bind(this) : null,
                        onShippingOptionsChange: !isVirtual ?
                            this.handleOnShippingOptionsChange.bind(this) : null,
                        onAuthorized: this.handleOnAuthorized.bind(this),
                        onError: this.handleOnError.bind(this)
                    };

                    this.paypalComponent = await window.AdyenWeb.createComponent(
                        'paypal',
                        this.checkoutComponent,
                        configuration
                    );

                    if (typeof this.paypalComponent.isAvailable === 'function') {
                        this.paypalComponent.isAvailable()
                            .then(function () {
                                rootEl.style.display = 'block';
                                this.paypalComponent.mount('#' + this.componentRootNode);
                                this.isInitialized = true;
                            }.bind(this))
                            .catch(function (e) {
                                console.log('PayPal is unavailable on shipping.', e);
                            });
                    } else {
                        rootEl.style.display = 'block';
                        this.paypalComponent.mount('#' + this.componentRootNode);
                        this.isInitialized = true;
                    }
                } catch (e) {
                    console.error('Adyen PayPal Express – failed to build component', e);
                    this.showUserError(
                        $t('Something went wrong while loading PayPal. Please refresh the page or try again later.')
                    );
                } finally {
                    this.isMounting = false;
                    this.isLoading(false);
                }
            },

            /**
             * Handles /payments + opens the PayPal sheet.
             *
             * @param {Object} state
             * @param {Object} component
             * @param {Object} actions
             */
            handleOnSubmit: async function (state, component, actions) {
                const paymentData = state.data;

                paymentData.merchantAccount = adyenConfiguration.getMerchantAccount();
                loader.startLoader();
                this.isLoading(true);
                this.errorMessage('');

                try {
                    const responseJSON = await initPayments(paymentData, false);
                    const response = JSON.parse(responseJSON || '{}');

                    if (response.action) {
                        component.handleAction(response.action);
                        actions.resolve();
                    } else {
                        console.error('InitPayments for PayPal did not return an action', response);
                        this.showUserError(
                            $t('We could not start your PayPal payment. Please try again.')
                        );
                        actions.reject();
                    }
                } catch (error) {
                    errorProcessor.process(error, this.messageContainer);
                    this.showUserError(
                        $t('We could not start your PayPal payment. Please try again.')
                    );
                    actions.reject();
                } finally {
                    loader.stopLoader();
                    this.isLoading(false);
                }
            },

            /**
             * Called when PayPal returns shipping address.
             * - get Magento shipping methods
             * - update quote shipping & totals
             * - call updatePaypalOrder with shipping options
             */
            handleOnShippingAddressChange: async function (data, actions, component) {
                const shippingAddress = data && data.shippingAddress ? data.shippingAddress : null;

                if (!shippingAddress) {
                    return actions.reject();
                }

                this.shippingAddress = shippingAddress;

                const payload = {
                    address: {
                        country_id: shippingAddress.countryCode,
                        postcode: shippingAddress.postalCode,
                        street: ['']
                    }
                };

                this.isLoading(true);
                this.errorMessage('');

                try {
                    const result = await getShippingMethods(payload, false);

                    if (!result || !result.length) {
                        this.showUserError(
                            $t('There are no shipping methods available for your address. Please review your details or use another payment method.')
                        );
                        actions.reject();
                        return;
                    }

                    this.shippingMethods = {};
                    this.shippingMethod = null;
                    const shippingMethods = [];

                    result.forEach(function (method) {
                        if (typeof method.method_code !== 'string') {
                            return;
                        }

                        this.shippingMethods[method.method_code] = method;

                        const shippingMethodObj = this._createShippingMethodObject(method);
                        shippingMethods.push(shippingMethodObj);

                        if (!this.shippingMethod) {
                            this.shippingMethod = method.method_code;
                            this.selectedShippingMethod = method;
                        }
                    }.bind(this));

                    if (!this.shippingMethod) {
                        this.showUserError(
                            $t('No valid shipping methods were found. Please contact us if the problem persists.')
                        );
                        actions.reject();
                        return;
                    }

                    // Update quote shipping + totals
                    await this.setShippingAndTotals(
                        this._createShippingMethodObject(this.selectedShippingMethod),
                        shippingAddress
                    );

                    const displayCurrency = adyenExpressConfiguration.getCurrency();
                    const currentPaymentData = component.paymentData;

                    const response = await updatePaypalOrder.updateOrder(
                        false,
                        currentPaymentData,
                        shippingMethods,
                        displayCurrency
                    );

                    const parsedResponse = JSON.parse(response || '{}');

                    if (parsedResponse.paymentData) {
                        this.latestPaymentData = parsedResponse.paymentData;

                        if (typeof component.updatePaymentData === 'function') {
                            component.updatePaymentData(parsedResponse.paymentData);
                        }

                        if (this.paypalComponent) {
                            this.paypalComponent.paymentData = parsedResponse.paymentData;
                        }
                    }

                    actions.resolve();
                } catch (error) {
                    console.error('Failed to handle PayPal shipping address change', error);
                    this.showUserError(
                        $t('We could not update shipping options for your PayPal payment. Please try again.')
                    );
                    actions.reject();
                } finally {
                    this.isLoading(false);
                }
            },

            /**
             * Called when shopper changes shipping option in PayPal.
             * - find matching method
             * - update quote
             * - call updatePaypalOrder with selected option
             */
            handleOnShippingOptionsChange: async function (data, actions, component) {
                if (!data || !data.selectedShippingOption || !data.selectedShippingOption.label) {
                    return actions.reject();
                }

                const selectedLabel = data.selectedShippingOption.label?.trim();
                const shippingMethod = this._matchShippingMethodByLabel(selectedLabel);

                if (!shippingMethod) {
                    console.warn('Adyen PayPal Express – selected shipping option not found:', selectedLabel);
                    return actions.reject();
                }

                const paymentDataToUpdate = this.latestPaymentData || component.paymentData;
                const displayCurrency = adyenExpressConfiguration.getCurrency();

                this.isLoading(true);
                this.errorMessage('');

                try {
                    await this.setShippingAndTotals(shippingMethod, this.shippingAddress);

                    const response = await updatePaypalOrder.updateOrder(
                        false,
                        paymentDataToUpdate,
                        this.shippingMethods,
                        displayCurrency,
                        data.selectedShippingOption
                    );

                    const parsedResponse = JSON.parse(response || '{}');

                    if (parsedResponse.paymentData) {
                        this.latestPaymentData = parsedResponse.paymentData;

                        if (typeof component.updatePaymentData === 'function') {
                            component.updatePaymentData(parsedResponse.paymentData);
                        }

                        if (this.paypalComponent) {
                            this.paypalComponent.paymentData = parsedResponse.paymentData;
                        }
                    }

                    actions.resolve();
                } catch (error) {
                    console.error('Failed to handle PayPal shipping options change', error);

                    if (this.latestPaymentData && typeof component.updatePaymentData === 'function') {
                        component.updatePaymentData(this.latestPaymentData);
                    }

                    this.showUserError(
                        $t('We could not update the selected shipping method. Please try again.')
                    );
                    actions.reject();
                } finally {
                    this.isLoading(false);
                }
            },

            /**
             * Shopper authorized in PayPal.
             * - write billing + shipping to quote
             * - (optionally) set shipping method
             * - call our createOrder wrapper
             */
            handleOnAuthorized: async function (shopperDetails, actions) {
                if (!shopperDetails || !shopperDetails.authorizedEvent) {
                    actions.reject();
                    return;
                }

                const isVirtual = adyenExpressConfiguration.getIsVirtual();
                const addresses = this.mapAuthorizedAddresses(shopperDetails);
                const billingAddress = addresses.billingAddress;
                const shippingAddress = addresses.shippingAddress;

                const billingPayload = {
                    address: billingAddress,
                    useForShipping: false
                };

                const shippingInformationPayload = {
                    addressInformation: {
                        shipping_address: shippingAddress,
                        billing_address: billingAddress,
                        shipping_method_code: this.shippingMethod,
                        shipping_carrier_code: this.shippingMethods[this.shippingMethod]
                            ? this.shippingMethods[this.shippingMethod].carrier_code
                            : null,
                        extension_attributes: getExtensionAttributes(shopperDetails)
                    }
                };

                loader.startLoader();
                this.isLoading(true);
                this.errorMessage('');

                try {
                    await setBillingAddress(billingPayload, false);

                    if (!isVirtual && this.shippingMethod) {
                        await setShippingInformation(shippingInformationPayload, false);
                    }

                    const orderId = await this.createOrder(shopperDetails);
                    this.orderId = orderId;

                    actions.resolve();
                } catch (error) {
                    console.error('Adyen PayPal Express – handleOnAuthorized failed', error);
                    errorProcessor.process(error, this.messageContainer);
                    this.showUserError(
                        $t('We could not place your order with PayPal. Please try again or choose another payment method.')
                    );
                    actions.reject();
                } finally {
                    loader.stopLoader();
                    this.isLoading(false);
                }
            },

            /**
             * /payments/details
             */
            handleOnAdditionalDetails: function (state) {
                const self = this;
                const quoteId = getMaskedIdFromCart();

                if (!state || !state.data) {
                    return;
                }

                const request = state.data;
                const popupModal = self.showModal();

                adyenPaymentModal.hideModalLabel(this.modalLabel);
                loader.startLoader();
                this.isLoading(true);
                this.errorMessage('');

                request.orderId = self.orderId;

                adyenPaymentService.paymentDetails(request, self.orderId, quoteId)
                    .done(function (responseJSON) {
                        self.handleAdyenResult(responseJSON, self.orderId);
                    })
                    .fail(function (response) {
                        self.closeModal(popupModal);
                        errorProcessor.process(response, self.messageContainer);
                        self.isPlaceOrderActionAllowed(true);
                        loader.stopLoader();
                        self.isLoading(false);
                        self.showUserError(
                            $t('We could not complete your PayPal payment. Please try again.')
                        );
                    });
            },

            /**
             * Generic error callback from the component.
             */
            handleOnError: function (error) {
                console.error('Adyen PayPal Express – onError', error);
                errorProcessor.process(error, this.messageContainer);
                this.showUserError(
                    $t('Something went wrong with PayPal. Please try again or choose another payment method.')
                );
                loader.stopLoader();
                this.isLoading(false);
            },

            /**
             * Final sync via updatePaypalOrder, then Magento order creation.
             * Mirrors button.js behaviour and reuses the latest paymentData.
             */
            createOrder: function (shopperDetails) {
                const self = this;

                const payload = {
                    email: shopperDetails.authorizedEvent &&
                        shopperDetails.authorizedEvent.payer &&
                        shopperDetails.authorizedEvent.payer.email_address,
                    paymentMethod: {
                        method: 'adyen_paypal_express',
                        additional_data: {
                            brand_code: 'paypal'
                        },
                        extension_attributes: getExtensionAttributes(shopperDetails)
                    }
                };

                if (window.checkout && window.checkout.agreementIds) {
                    payload.paymentMethod.extension_attributes = payload.paymentMethod.extension_attributes || {};
                    payload.paymentMethod.extension_attributes.agreement_ids = window.checkout.agreementIds;
                }

                return new Promise(function (resolve, reject) {
                    const paymentDataForOrder =
                        self.latestPaymentData ||
                        (self.paypalComponent && self.paypalComponent.paymentData);

                    if (!paymentDataForOrder) {
                        reject(new Error('Missing PayPal paymentData'));
                        return;
                    }

                    updatePaypalOrder.updateOrder(
                        false,
                        paymentDataForOrder,
                        self.shippingMethods,
                        adyenExpressConfiguration.getCurrency()
                    ).then(function () {
                        return createOrderAction(JSON.stringify(payload), false);
                    }).then(function (orderId) {
                        if (orderId) {
                            self.orderId = orderId;
                            resolve(orderId);
                        } else {
                            reject(new Error('Order ID not returned'));
                        }
                    }).catch(function (e) {
                        console.error('Adyen PayPal Express – unable to create order', e);
                        reject(e);
                    });
                });
            },

            /**
             * Maps PayPal authorised details to Magento addresses.
             */
            mapAuthorizedAddresses: function (shopperDetails) {
                const payer = shopperDetails.authorizedEvent.payer;
                const billing = shopperDetails.billingAddress;
                const delivery = shopperDetails.deliveryAddress || billing;

                const billingAddress = {
                    email: payer.email_address,
                    telephone: payer.phone && payer.phone.phone_number
                        ? payer.phone.phone_number.national_number
                        : '',
                    firstname: payer.name && payer.name.given_name,
                    lastname: payer.name && payer.name.surname,
                    street: [billing.street],
                    city: billing.city,
                    region: billing.stateOrProvince,
                    region_id: getRegionId(
                        billing.country,
                        billing.stateOrProvince
                    ),
                    region_code: null,
                    country_id: billing.country.toUpperCase(),
                    postcode: billing.postalCode,
                    same_as_billing: 0,
                    customer_address_id: 0,
                    save_in_address_book: 0
                };

                const shippingAddress = {
                    email: payer.email_address,
                    telephone: payer.phone && payer.phone.phone_number
                        ? payer.phone.phone_number.national_number
                        : '',
                    firstname: payer.name && payer.name.given_name,
                    lastname: payer.name && payer.name.surname,
                    street: [delivery.street],
                    city: delivery.city,
                    region: delivery.stateOrProvince,
                    region_id: getRegionId(
                        delivery.country,
                        delivery.stateOrProvince
                    ),
                    region_code: null,
                    country_id: delivery.country.toUpperCase(),
                    postcode: delivery.postalCode,
                    same_as_billing: 0,
                    customer_address_id: 0,
                    save_in_address_book: 0
                };

                return {
                    billingAddress: billingAddress,
                    shippingAddress: shippingAddress
                };
            },

            /**
             * Handles Adyen result coming back from /payments/details.
             */
            handleAdyenResult: function (responseJSON) {
                const self = this;
                const response = JSON.parse(responseJSON || '{}');

                if (response.isFinal) {
                    loader.stopLoader();
                    this.isLoading(false);
                    redirectToSuccess();
                } else if (response.action) {
                    self.handleAction(response.action, self.orderId);
                } else {
                    loader.stopLoader();
                    this.isLoading(false);
                    console.log('Unhandled PayPal result', response);
                }
            },

            _createShippingMethodObject: function (method) {
                const description = method.carrier_title?.trim() ||
                    method.method_title?.trim() ||
                    method.carrier_code;

                const label = method.method_title?.trim() || method.carrier_code;

                return {
                    identifier: method.method_code,
                    label: label,
                    detail: description,
                    amount: method.amount,
                    carrierCode: method.carrier_code
                };
            },

            _matchShippingMethodByLabel: function (label) {
                label = label?.trim();

                for (const method of Object.values(this.shippingMethods || {})) {
                    if (
                        method.carrier_code === label ||
                        method.carrier_title === label ||
                        method.method_code === label ||
                        method.method_title === label
                    ) {
                        this.shippingMethod = method.method_code || method.carrier_code;
                        this.selectedShippingMethod = method;

                        return this._createShippingMethodObject(method);
                    }
                }

                console.warn('No matching shipping method found for label:', label, this.shippingMethods);
                return null;
            },

            _getAddressInformationPayload: function (shippingMethod, shippingAddress) {
                const address = {
                    countryId: shippingAddress.countryCode,
                    region: shippingAddress.state ||
                        shippingAddress.administrativeArea ||
                        shippingAddress.locality,
                    regionId: getRegionId(
                        shippingAddress.countryCode,
                        shippingAddress.state ||
                        shippingAddress.administrativeArea ||
                        shippingAddress.locality,
                        true
                    ),
                    postcode: shippingAddress.postalCode,
                    firstname: '',
                    lastname: '',
                    city: '',
                    telephone: '',
                    street: ['', '']
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

            /**
             * Updates shipping information and totals in Magento.
             */
            setShippingAndTotals: function (shippingMethod, shippingAddress) {
                const payloads = this._getAddressInformationPayload(shippingMethod, shippingAddress);

                return $.when(
                    setShippingInformation(payloads.shippingInformationPayload, false),
                    setTotalsInfo(payloads.totalsPayload, false)
                ).fail(function (error) {
                    console.error('Failed to set shipping and totals information:', error);
                    throw new Error($t('Failed to set shipping and totals information. Please try again later.'));
                });
            },

            /**
             * Handles 3DS2 / await actions through the modal.
             */
            handleAction: function (action) {
                const self = this;
                let popupModal;

                if (action.type === 'threeDS2' || action.type === 'await') {
                    popupModal = self.showModal();
                }

                try {
                    self.checkoutComponent.createFromAction(action, {
                        onActionHandled: function (event) {
                            if (event.componentType === '3DS2Challenge') {
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

            showModal: function () {
                const actionModal = adyenPaymentModal.showModal(
                    adyenPaymentService,
                    loader,
                    this.messageContainer,
                    this.orderId,
                    this.modalLabel,
                    this.isPlaceOrderActionAllowed,
                    false
                );

                $('.' + this.modalLabel + ' .action-close').hide();

                return actionModal;
            },

            closeModal: function (popupModal) {
                adyenPaymentModal.closeModal(popupModal, this.modalLabel);
            },

            /**
             * Exposed for template binding.
             */
            getComponentRootNoteId: function () {
                return this.componentRootNode;
            },

            /**
             * Shows a friendly error message in the UI.
             *
             * @param {String} message
             */
            showUserError: function (message) {
                this.errorMessage(message);
            },

            /**
             * Clean up PayPal / Adyen components and listeners when component is destroyed.
             */
            destroy: function () {
                try {
                    if (this.paypalComponent && typeof this.paypalComponent.unmount === 'function') {
                        this.paypalComponent.unmount();
                    }
                } catch (e) {
                    console.log('Error while unmounting PayPal component', e);
                }

                this.paypalComponent = null;
                this.checkoutComponent = null;
                this.isInitialized = false;

                this._super();
            }
        });
    }
);
