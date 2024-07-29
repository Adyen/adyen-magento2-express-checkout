<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Model\Config\Source\ApplePay\ButtonColor;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Configuration constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param string $paymentMethodVariant
     * @param string $scopeType
     * @param $scopeCode
     * @return array
     */
    public function getShowPaymentMethodOn(
        string $paymentMethodVariant,
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): array {
        $configPath = sprintf(
            "%s/%s_%s/%s",
            self::CONFIG_PATH_PAYMENT,
            self::CONFIG_PATH_ADYEN_PREFIX,
            $paymentMethodVariant,
            self::CONFIG_PATH_SHOW_EXPRESS_ON
        );

        $value = $this->scopeConfig->getValue($configPath, $scopeType, $scopeCode);

        return $value ? explode(',', $value) : [];
    }

    /**
     * @inheritDoc
     */
    public function getApplePayButtonColor(
        string $scopeType = ScopeInterface::SCOPE_STORE,
               $scopeCode = null
    ): string {
        $value = $this->scopeConfig->getValue(
            self::APPLE_PAY_BUTTON_COLOR_CONFIG_PATH,
            $scopeType,
            $scopeCode
        );

        return $value ?: ButtonColor::BLACK;
    }

    /**
     * @deprecated use getShowPaymentMethodOn() instead
     *
     * Returns configuration value for where to show apple pay
     *
     * @param string $scopeType
     * @param null|int|string $scopeCode
     * @return array
     */
    public function getShowApplePayOn(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): array {
        $value = $this->scopeConfig->getValue(
            self::SHOW_APPLE_PAY_ON_CONFIG_PATH,
            $scopeType,
            $scopeCode
        );
        return $value ?
            explode(
                ',',
                $value
            ) : [];
    }

    /**
     * @deprecated use getShowPaymentMethodOn() instead
     *
     * Returns configuration value for where to show google pay
     *
     * @param string $scopeType
     * @param null|int|string $scopeCode
     * @return array
     */
    public function getShowGooglePayOn(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): array {
        $value = $this->scopeConfig->getValue(
            self::SHOW_GOOGLE_PAY_ON_CONFIG_PATH,
            $scopeType,
            $scopeCode
        );
        return $value ?
            explode(
                ',',
                $value
            ) : [];
    }
}
