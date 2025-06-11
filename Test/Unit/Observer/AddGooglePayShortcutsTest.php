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

namespace Adyen\ExpressCheckout\Test\Unit\Observer;

use Adyen\ExpressCheckout\Block\GooglePay\Shortcut\Button as GooglePayButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\ExpressCheckout\Observer\AddGooglePayShortcuts;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddGooglePayShortcutsTest extends AbstractPaymentMethodShortcutsTestCase
{
    protected function setUp(): void
    {
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);

        $this->configurationMock = $this->createMock(ConfigurationInterface::class);
        $this->buttonMock = $this->createMock(GooglePayButton::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->addShortcuts = new AddGooglePayShortcuts(
            $this->configurationMock,
            $this->buttonMock,
            $this->storeManagerMock
        );
    }
}
