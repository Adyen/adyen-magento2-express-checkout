<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\ExpressCheckout\Test\Unit\Observer;

use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;
use Adyen\ExpressCheckout\Observer\UpdatePaypalOrderStatus;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;
use PHPUnit\Framework\MockObject\MockObject;

class UpdatePaypalOrderStatusTest extends AbstractAdyenTestCase
{
    protected ?UpdatePaypalOrderStatus $updatePaypalOrderStatusObserver;
    protected MockObject|Observer $observerMock;
    protected MockObject|Order $orderMock;
    protected MockObject|PaymentInterface $paymentMock;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->paymentMock = $this->createMock(PaymentInterface::class);

        // Setting the value and the methods of the mock globally since this is not variable among test cases.
        $orderConfigMock = $this->createMock(Config::class);
        $orderConfigMock->method('getStateDefaultStatus')
            ->with(Order::STATE_NEW)
            ->willReturn('pending');

        $this->orderMock = $this->createGeneratedMock(Order::class,
            ['save', 'getPayment', 'setState', 'getConfig']
        );
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getConfig')->willReturn($orderConfigMock);


        $eventMock = $this->getMockBuilder(AbstractBlock::class)
            ->addMethods(['getOrder'])
            ->disableOriginalConstructor()
            ->getMock();

        $eventMock->method('getOrder')->willReturn($this->orderMock);

        $this->observerMock = $this->createMock(Observer::class);
        $this->observerMock->method('getEvent')->willReturn($eventMock);

        $this->updatePaypalOrderStatusObserver = new UpdatePaypalOrderStatus();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->updatePaypalOrderStatusObserver = null;
    }

    /**
     * Test case for triggering the observer for Adyen PayPal express payments
     *
     * @return void
     */
    public function testUpdatePaypalOrderStatusAdyenExpressOrders()
    {
        $this->paymentMock->method('getMethod')->willReturn(Button::PAYPAL_EXPRESS_METHOD_NAME);

        $this->paymentMock->expects($this->once())
            ->method('setMethod')
            ->with(Button::PAYPAL_METHOD_NAME);

        $this->orderMock->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_NEW);

        $this->orderMock->expects($this->once())->method('save');

        $this->updatePaypalOrderStatusObserver->execute($this->observerMock);
    }

    /**
     * Test case for skipping the observer for non-Adyen PayPal express payments
     *
     * @dataProvider nonAdyenPayPalExpressMethodProvider
     * @return void
     */
    public function testUpdatePaypalOrderStatusNonAdyenExpressOrders($paymentMethodName)
    {
        $this->paymentMock->method('getMethod')->willReturn($paymentMethodName);

        $this->paymentMock->expects($this->never())->method('setMethod');
        $this->orderMock->expects($this->never())->method('setState');
        $this->orderMock->expects($this->never())->method('save');

        $this->updatePaypalOrderStatusObserver->execute($this->observerMock);
    }

    /**
     * Data provider for test case `testUpdatePaypalOrderStatusNonAdyenExpressOrders()`
     *
     * @return array[]
     */
    private static function nonAdyenPayPalExpressMethodProvider(): array
    {
        return [
            ['paymentMethodName' => 'irrelevant_payment_method'],
            ['paymentMethodName' => 'adyen_ideal'],
            ['paymentMethodName' => 'adyen_paypal']
        ];
    }
}
