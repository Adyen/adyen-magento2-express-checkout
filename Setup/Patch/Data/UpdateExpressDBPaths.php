<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Setup\Patch\Data;

use Adyen\ExpressCheckout\Setup\Patch\Abstract\AbstractConfigurationPathPatcher;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateExpressDBPaths extends AbstractConfigurationPathPatcher implements DataPatchInterface
{
    protected const array REPLACE_CONFIG_PATHS = [
        'payment/adyen_hpp/show_apple_pay_on' => 'payment/adyen_express/show_apple_pay_on',
        'payment/adyen_hpp/show_google_pay_on' => 'payment/adyen_express/show_google_pay_on',
        'payment/adyen_hpp/apple_pay_button_color' => 'payment/adyen_express/apple_pay_button_color'
    ];
}
