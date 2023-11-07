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
    public const SHOW_APPLE_PAY_ON_CONFIG_PATH = 'payment/adyen_express/show_apple_pay_on';
    public const SHOW_GOOGLE_PAY_ON_CONFIG_PATH = 'payment/adyen_express/show_google_pay_on';

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
}
