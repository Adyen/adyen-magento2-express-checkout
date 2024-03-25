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

    /**
     * Returns configuration value for where to show PayPal
     *
     * @param string $scopeType
     * @param null|int|string $scopeCode
     * @return array
     */
    public function getShowPaypalOn(
        string $scopeType = ScopeInterface::SCOPE_STORE,
               $scopeCode = null
    ): array {
        $value = $this->scopeConfig->getValue(
            self::SHOW_PAYPAL_ON_CONFIG_PATH,
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
