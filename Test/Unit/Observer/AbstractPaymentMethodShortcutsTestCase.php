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

use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

abstract class AbstractPaymentMethodShortcutsTestCase extends AbstractAdyenTestCase
{
    protected ?ObserverInterface $addShortcuts;
    protected MockObject|ConfigurationInterface $configurationMock;
    protected MockObject|ShortcutInterface $buttonMock;
    protected MockObject|StoreManagerInterface $storeManagerMock;

    protected function tearDown(): void
    {
        $this->addShortcuts = null;
    }

    private static function executeDataProvider(): array
    {
        return [
            [
                'expressEnabledResult' => true,
                'identifierHandles' => [],
                'isMinicart' =>  true,
                'enabledOn' => [1, 3]
            ],
            [
                'expressEnabledResult' => true,
                'identifierHandles' => ['catalog_product_view'],
                'isMinicart' =>  false,
                'enabledOn' => [1]
            ],
            [
                'expressEnabledResult' => true,
                'identifierHandles' => ['checkout_cart_index'],
                'isMinicart' =>  false,
                'enabledOn' => [1, 2, 3]
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
     * @param array $enabledOn
     * @return void
     * @throws Exception
     */
    public function testExecute(
        $expressEnabledResult,
        array $identifierHandles = [],
        bool $isMinicart = false,
        array $enabledOn = []
    ) {
        $processorMock = $this->createMock(ProcessorInterface::class);
        $processorMock->method('getHandles')->willReturn($identifierHandles);

        $shortcutButtonMock = $this->getMockBuilder(AbstractBlock::class)
            ->addMethods(['setIsProductView', 'setIsCart', 'getAlias'])
            ->disableOriginalConstructor()
            ->getMock();

        $shortcutButtonMock->method('getAlias')->willReturn('mockAlias');


        $layoutMock = $this->createMock(Layout::class);
        $layoutMock->method('getUpdate')->willReturn($processorMock);
        $layoutMock->method('createBlock')->willReturn($shortcutButtonMock);

        $blockMock = $this->getMockBuilder(AbstractBlock::class)
            ->onlyMethods(['getData', 'getLayout'])
            ->addMethods(['getAlias', 'addShortcut'])
            ->disableOriginalConstructor()
            ->getMock();

        $blockMock->method('getData')->willReturn($isMinicart);
        $blockMock->method('getLayout')->willReturn($layoutMock);
        $blockMock->method('getAlias')->willReturn('mock-alias');


        $eventMock = $this->getMockBuilder(AbstractBlock::class)
            ->addMethods(['getContainer'])
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->method('getContainer')->willReturn($blockMock);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($eventMock);

        $this->configurationMock->expects($this->once())
            ->method('getShowPaymentMethodOn')
            ->willReturn($enabledOn);

        if ($expressEnabledResult) {
            $blockMock->expects($this->once())->method('addShortcut');
        }

        $this->addShortcuts->execute($observerMock);
    }
}
