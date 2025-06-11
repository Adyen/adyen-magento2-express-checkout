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

use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button as PaypalButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\ExpressCheckout\Observer\AddPaypalShortcuts;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddPaypalShortcutsTest extends AbstractPaymentMethodShortcutsTestCase
{
    protected function setUp(): void
    {
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);

        $this->configurationMock = $this->createMock(ConfigurationInterface::class);
        $this->buttonMock = $this->createMock(PaypalButton::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->addShortcuts = new AddPaypalShortcuts(
            $this->configurationMock,
            $this->buttonMock,
            $this->storeManagerMock
        );
    }
}
