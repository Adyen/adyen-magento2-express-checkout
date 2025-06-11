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

use Adyen\ExpressCheckout\Block\ApplePay\Shortcut\Button as ApplePayButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\ExpressCheckout\Observer\AddApplePayShortcuts;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddApplePayShortcutsTest extends AbstractPaymentMethodShortcutsTestCase
{
    protected function setUp(): void
    {
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);

        $this->configurationMock = $this->createMock(ConfigurationInterface::class);
        $this->buttonMock = $this->createMock(ApplePayButton::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->addShortcuts = new AddApplePayShortcuts(
            $this->configurationMock,
            $this->buttonMock,
            $this->storeManagerMock
        );
    }
}
