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

use Magento\Store\Model\ScopeInterface;

/**
 * Configuration Service Contract provides methods to get values of express payment specific config
 *
 * @api
 */
interface ConfigurationInterface
{
    public const SHOW_APPLE_PAY_ON_CONFIG_PATH = 'payment/adyen_hpp/show_apple_pay_on';
    public const SHOW_GOOGLE_PAY_ON_CONFIG_PATH = 'payment/adyen_hpp/show_google_pay_on';
    public const SHOW_AMAZON_PAY_ON_CONFIG_PATH = 'payment/adyen_hpp/show_amazon_pay_on';
    public const APPLE_PAY_BUTTON_COLOR_CONFIG_PATH = 'payment/adyen_hpp/apple_pay_button_color';

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
    ): array;

    /**
     * Returns Apple Pay button color, if no value set, black is returned
     *
     * @param string $scopeType
     * @param $scopeCode
     * @return string
     */
    public function getApplePayButtonColor(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        $scopeCode = null
    ): string;

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
    ): array;

    /**
     * Returns configuration value for where to show amazon pay
     *
     * @param string $scopeType
     * @param null|int|string $scopeCode
     * @return array
     */
    public function getShowAmazonPayOn(
        string $scopeType = ScopeInterface::SCOPE_STORE,
               $scopeCode = null
    ): array;
}
