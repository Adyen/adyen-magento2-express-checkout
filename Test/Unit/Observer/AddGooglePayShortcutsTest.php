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
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AddGooglePayShortcutsTest extends AbstractAdyenTestCase
{
    protected ?AddGooglePayShortcuts $addGooglePayShortcuts;
    protected MockObject|ConfigurationInterface $configurationMock;
    protected MockObject|GooglePayButton $googlepayButtonMock;
    protected MockObject|StoreManagerInterface $storeManagerMock;

    protected function setUp(): void
    {
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);

        $this->configurationMock = $this->createMock(ConfigurationInterface::class);
        $this->googlepayButtonMock = $this->createMock(GooglePayButton::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->addGooglePayShortcuts = new AddGooglePayShortcuts(
            $this->configurationMock,
            $this->googlepayButtonMock,
            $this->storeManagerMock
        );
    }

    protected function tearDown(): void
    {
        $this->addGooglePayShortcuts = null;
    }

    private static function executeDataProvider(): array
    {
        return [
            [
                'expressEnabledResult' => true,
                'identifierHandles' => [],
                'isMinicart' =>  true
            ],
            [
                'expressEnabledResult' => true,
                'identifierHandles' => ['catalog_product_view']
            ],
            [
                'expressEnabledResult' => true,
                'identifierHandles' => ['checkout_cart_index']
            ],
            [
                'expressEnabledResult' => false
            ]
        ];
    }

    /**
     * @dataProvider executeDataProvider
     *
     * @param $expressEnabledResult
     * @param array $identifierHandles
     * @param bool $isMinicart
     * @return void
     * @throws LocalizedException
     */
    public function testExecute(
        $expressEnabledResult,
        array $identifierHandles = [],
        bool $isMinicart = false
    ) {
        $processorMock = $this->createMock(ProcessorInterface::class);
        $processorMock->method('getHandles')->willReturn($identifierHandles);

        $shortcutButtonMock = $this->createGeneratedMock(ShortcutInterface::class, [
            'setIsProductView',
            'setIsCart',
            'getAlias'
        ]);

        $layoutMock = $this->createMock(Layout::class);
        $layoutMock->method('getUpdate')->willReturn($processorMock);
        $layoutMock->method('createBlock')->willReturn($shortcutButtonMock);

        $blockMock = $this->createGeneratedMock(ShortcutInterface::class, [
            'getAlias', 'getLayout', 'getData', 'addShortcut'
        ]);
        $blockMock->method('getLayout')->willReturn($layoutMock);
        $blockMock->method('getData')->willReturn($isMinicart);

        $eventMock = $this->createGeneratedMock(Event::class, ['getContainer']);
        $eventMock->method('getContainer')->willReturn($blockMock);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($eventMock);

        $this->configurationMock->expects($this->once())
            ->method('getShowPaymentMethodOn')
            ->willReturn([1,2,3]);

        if ($expressEnabledResult) {
            $blockMock->expects($this->once())->method('addShortcut');
        }

        $this->addGooglePayShortcuts->execute($observerMock);
    }
}
