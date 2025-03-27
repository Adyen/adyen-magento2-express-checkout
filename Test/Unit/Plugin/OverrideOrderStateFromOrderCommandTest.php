<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Plugin;

use Adyen\ExpressCheckout\Plugin\OverrideOrderStateFromOrderCommand;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Directory\Model\Currency;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\State\OrderCommand;
use PHPUnit\Framework\MockObject\MockObject;

class OverrideOrderStateFromOrderCommandTest extends AbstractAdyenTestCase
{
    private const AMOUNT = 10.00;
    private const PAYPAL_EXPRESS = Button::PAYPAL_EXPRESS_METHOD_NAME;
    private const DEFAULT_STATUS = 'pending';

    private OverrideOrderStateFromOrderCommand $plugin;
    private MockObject $payment;
    private MockObject $order;
    private MockObject $orderCommand;

    protected function setUp(): void
    {
        $this->plugin = new OverrideOrderStateFromOrderCommand();
        $this->payment = $this->getMockBuilder(OrderPaymentInterface::class)
            ->setMethods(['getIsTransactionPending', 'getIsFraudDetected'])
            ->getMockForAbstractClass();
        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderCommand = $this->createMock(OrderCommand::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->plugin,
            $this->payment,
            $this->order,
            $this->orderCommand
        );
    }

    public function testOverridesStatusForPaypalExpressWhenNotPendingOrFraud(): void
    {
        $this->payment->method('getMethod')->willReturn(self::PAYPAL_EXPRESS);
        $this->payment->method('getIsTransactionPending')->willReturn(false);
        $this->payment->method('getIsFraudDetected')->willReturn(false);

        $currencyMock = $this->createMock(Currency::class);
        $currencyMock->method('formatTxt')->with(self::AMOUNT)->willReturn('€10.00');
        $this->order->method('getBaseCurrency')->willReturn($currencyMock);

        $this->order->expects($this->once())
            ->method('setState')
            ->with(Order::STATE_NEW)
            ->willReturnSelf();

        $this->order->expects($this->once())
            ->method('setStatus')
            ->with(self::DEFAULT_STATUS)
            ->willReturnSelf();

        $this->order->method('getConfig')->willReturn(
            new class {
                public function getStateDefaultStatus($state)
                {
                    return $state === Order::STATE_NEW ? 'pending' : 'processing';
                }
            }
        );

        $result = $this->plugin->aroundExecute(
            $this->orderCommand,
            fn() => 'Should not be called',
            $this->payment,
            self::AMOUNT,
            $this->order
        );

        $this->assertInstanceOf(Phrase::class, $result);
        $this->assertSame('Ordered amount of €10.00', (string)$result);
    }

    public function testFallsBackToProceedIfNotPaypalExpress(): void
    {
        $this->payment->method('getMethod')->willReturn('checkmo');

        $result = $this->plugin->aroundExecute(
            $this->orderCommand,
            fn() => 'called proceed',
            $this->payment,
            self::AMOUNT,
            $this->order
        );

        $this->assertSame('called proceed', $result);
    }

    public function testFallsBackToProceedIfTransactionPending(): void
    {
        $this->payment->method('getMethod')->willReturn(self::PAYPAL_EXPRESS);
        $this->payment->method('getIsTransactionPending')->willReturn(true);

        $result = $this->plugin->aroundExecute(
            $this->orderCommand,
            fn() => 'pending fallback',
            $this->payment,
            self::AMOUNT,
            $this->order
        );

        $this->assertSame('pending fallback', $result);
    }

    public function testFallsBackToProceedIfFraudDetected(): void
    {
        $this->payment->method('getMethod')->willReturn(self::PAYPAL_EXPRESS);
        $this->payment->method('getIsTransactionPending')->willReturn(false);
        $this->payment->method('getIsFraudDetected')->willReturn(true);

        $result = $this->plugin->aroundExecute(
            $this->orderCommand,
            fn() => 'fraud fallback',
            $this->payment,
            self::AMOUNT,
            $this->order
        );

        $this->assertSame('fraud fallback', $result);
    }
}
