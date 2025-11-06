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
use Adyen\ExpressCheckout\Block\GooglePay\Shortcut\Button as GooglePayButton;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button as PayPalButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdyenExpressConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ConfigurationInterface $configHelper,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function getConfig(): array
    {
        $storeId = $this->storeManager->getStore()->getId();

        $showApplepayOn = $this->configHelper->getShowPaymentMethodOn(
            ApplePayButton::PAYMENT_METHOD_VARIANT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $applepayButtonColor = $this->configHelper->getApplePayButtonColor(
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $showGooglepayOn = $this->configHelper->getShowPaymentMethodOn(
            GooglePayButton::PAYMENT_METHOD_VARIANT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $showPaypalOn = $this->configHelper->getShowPaymentMethodOn(
            PayPalButton::PAYMENT_METHOD_VARIANT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return [
            'payment' => [
                'adyenExpress' => [
                    'showApplepayOn' => $showApplepayOn,
                    'applepayButtonColor' => $applepayButtonColor,
                    'showGooglepayOn' => $showGooglepayOn,
                    'showPaypalOn' => $showPaypalOn
                ]
            ]
        ];
    }
}
