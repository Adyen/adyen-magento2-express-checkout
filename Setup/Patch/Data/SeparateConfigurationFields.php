<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Setup\Patch\Data;

use Adyen\ExpressCheckout\Setup\Patch\Abstract\AbstractConfigurationPathPatcher;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SeparateConfigurationFields extends AbstractConfigurationPathPatcher implements DataPatchInterface
{
    const REPLACE_CONFIG_PATHS = [
        'payment/adyen_express/show_apple_pay_on' => 'payment/adyen_applepay/express_show_on',
        'payment/adyen_express/show_google_pay_on' => 'payment/adyen_googlepay/express_show_on',
        'payment/adyen_express/apple_pay_button_color' => 'payment/adyen_applepay/express_button_color'
    ];

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            UpdateExpressDBPaths::class
        ];
    }
}
