<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Observer;

use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;
use Adyen\ExpressCheckout\Observer\DisableOrderEmailForPaypalExpress;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class DisableOrderEmailForPaypalExpressTest extends AbstractAdyenTestCase
{
    private DisableOrderEmailForPaypalExpress $observerInstance;

    protected function setUp(): void
    {
        $this->observerInstance = new DisableOrderEmailForPaypalExpress();
    }

    public function testSetCanSendEmailFalseForPaypalExpress(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn(Button::PAYPAL_EXPRESS_METHOD_NAME);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->expects($this->once())->method('setCanSendNewEmailFlag')->with(false);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn(
            new class($order) {
                public function __construct(private $order) {}
                public function getOrder() { return $this->order; }
            }
        );

        $this->observerInstance->execute($observer);
    }

    public function testDoesNotModifyOrderForOtherPaymentMethods(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('checkmo');

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->expects($this->never())->method('setCanSendNewEmailFlag');

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn(
            new class($order) {
                public function __construct(private $order) {}
                public function getOrder() { return $this->order; }
            }
        );

        $this->observerInstance->execute($observer);
    }

    public function testDoesNothingWhenNoOrderPresent(): void
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn(
            new class {
                public function getOrder() { return null; }
            }
        );

        // No exception should occur
        $this->observerInstance->execute($observer);
        $this->assertTrue(true);
    }
}
