<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\Ui;

use Adyen\ExpressCheckout\Block\ApplePay\Shortcut\Button as ApplePayButton;
use Adyen\ExpressCheckout\Block\Buttons\AbstractButton;
use Adyen\ExpressCheckout\Block\GooglePay\Shortcut\Button as GooglePayButton;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button as PayPalButton;
use Adyen\ExpressCheckout\Model\Config\Source\ShortcutAreas;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdyenExpressConfigProvider implements ConfigProviderInterface
{
    /**
     * @param ConfigurationInterface $configHelper
     * @param StoreManagerInterface $storeManager
     * @param AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
     * @param Session $checkoutSession
     * @param ChargedCurrency $chargeCurrencyHelper
     * @param Data $adyenHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly ConfigurationInterface $configHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement,
        private readonly Session $checkoutSession,
        private readonly ChargedCurrency $chargeCurrencyHelper,
        private readonly Data $adyenHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url
    ) {}

    /**
     * @return array[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $quoteAmountCurrency = $this->chargeCurrencyHelper->getQuoteAmountCurrency($quote);

        $isGooglePayEnabledOnShipping = $this->isExpressEnabledOnShipping(
            GooglePayButton::PAYMENT_METHOD_VARIANT
        );
        $isApplePayEnabledOnShipping = $this->isExpressEnabledOnShipping(
            ApplePayButton::PAYMENT_METHOD_VARIANT
        );
        $isPayPalEnabledOnShipping = $this->isExpressEnabledOnShipping(
            PayPalButton::PAYMENT_METHOD_VARIANT
        );

        return [
            'payment' => [
                'adyenExpress' => [
                    'paymentMethodsResponse' =>
                        ($isGooglePayEnabledOnShipping || $isApplePayEnabledOnShipping || $isPayPalEnabledOnShipping)
                            ? $this->getPaymentMethodsResponse()
                            : [],
                    'quote' => [
                        'isVirtual' => $quote->isVirtual(),
                        'amount' => [
                            'value' => number_format(
                                floatval($quoteAmountCurrency->getAmount()),
                                $this->adyenHelper->decimalNumbers($quoteAmountCurrency->getCurrencyCode())
                            ),
                            'currency' => $quoteAmountCurrency->getCurrencyCode()
                        ]
                    ],
                    'countryCode' => $this->scopeConfig->getValue(
                        AbstractButton::COUNTRY_CODE_PATH,
                        ScopeInterface::SCOPE_STORE,
                        $this->storeManager->getStore()->getId()
                    ),
                    'googlepay' => [
                        'isEnabledOnShipping' => $isGooglePayEnabledOnShipping
                    ],
                    'applepay' => [
                        'isEnabledOnShipping' => $isApplePayEnabledOnShipping,
                        'buttonColor' => $this->configHelper->getApplePayButtonColor(
                            ScopeInterface::SCOPE_STORE,
                            $this->storeManager->getStore()->getId()
                        )
                    ],
                    'paypal' => [
                        'isEnabledOnShipping' => $isPayPalEnabledOnShipping
                    ],
                    'storeCode' => $this->storeManager->getStore()->getCode(),
                    'actionSuccess' => $this->url->getUrl('checkout/onepage/success', ['_secure' => true])
                ]
            ]
        ];
    }

    /**
     * Checks if the express payment is enabled on the shipping page for the given variant.
     *
     * @param string $paymentMethodVariant
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isExpressEnabledOnShipping(string $paymentMethodVariant): bool
    {
        return in_array(
            ShortcutAreas::SHIPPING_PAGE_VALUE,
            $this->configHelper->getShowPaymentMethodOn(
                $paymentMethodVariant,
                ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getId()
            )
        );
    }

    /**
     * Makes an Adyen `/paymentMethods` call and returns decoded response
     *
     * @return array|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function getPaymentMethodsResponse(): ?array
    {
        $paymentMethodsResponse = $this->adyenPaymentMethodManagement->getPaymentMethods(
            $this->checkoutSession->getQuote()->getId()
        );
        $paymentMethods = json_decode($paymentMethodsResponse, true);

        if (json_last_error() === JSON_ERROR_NONE &&
            isset($paymentMethods['paymentMethodsResponse']['paymentMethods'])) {
            return $paymentMethods['paymentMethodsResponse'];
        } else {
            return null;
        }
    }
}
