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

use Adyen\ExpressCheckout\Helper\DataPatch;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateExpressDBPaths implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private DataPatch $dataPatchHelper;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        DataPatch $dataPatchHelper
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->dataPatchHelper = $dataPatchHelper;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Update Apple Pay config path
        $this->dataPatchHelper->updateConfigValue(
            $this->moduleDataSetup,
            'payment/adyen_hpp/show_apple_pay_on',
            'payment/adyen_express/show_apple_pay_on'
        );

        // Update Google Pay config path
        $this->dataPatchHelper->updateConfigValue(
            $this->moduleDataSetup,
            'payment/adyen_hpp/show_google_pay_on',
            'payment/adyen_express/show_google_pay_on'
        );

        // Update Google Pay config path
        $this->dataPatchHelper->updateConfigValue(
            $this->moduleDataSetup,
            'payment/adyen_hpp/apple_pay_button_color',
            'payment/adyen_express/apple_pay_button_color'
        );

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }
}
