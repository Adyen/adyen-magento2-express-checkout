<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\ExpressCheckout\Helper\PaypalUpdateOrder;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\DeliveryMethod;
use Adyen\Model\Checkout\PaypalUpdateOrderRequest;
use Adyen\Model\Checkout\PaypalUpdateOrderResponse;
use Adyen\Model\Checkout\TaxTotal;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\UtilityApi;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PaypalUpdateOrder::class)]
class PaypalUpdateOrderTest extends AbstractAdyenTestCase
{
    private Data $adyenHelper;
    private PaypalUpdateOrder $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adyenHelper = $this->createMock(Data::class);
        $this->subject = new PaypalUpdateOrder($this->adyenHelper);
    }

    public function testBuildPaypalUpdateOrderRequestWithoutDeliveryMethods(): void
    {
        $pspReference   = 'PSP123';
        $paymentData    = 'payment-data-token';
        $amountValue    = 12345;
        $taxAmount      = 2345;
        $currency       = 'EUR';

        $request = $this->subject->buildPaypalUpdateOrderRequest(
            $pspReference,
            $paymentData,
            $amountValue,
            $taxAmount,
            $currency,
            [] // no delivery methods
        );

        $this->assertInstanceOf(PaypalUpdateOrderRequest::class, $request);

        // Core fields
        $this->assertSame($pspReference, $request->getPspReference());
        $this->assertSame($paymentData, $request->getPaymentData());

        // Amount
        $this->assertInstanceOf(Amount::class, $request->getAmount());
        $this->assertSame($amountValue, $request->getAmount()->getValue());
        $this->assertSame($currency, $request->getAmount()->getCurrency());

        // Tax total
        $this->assertInstanceOf(TaxTotal::class, $request->getTaxTotal());
        $this->assertInstanceOf(Amount::class, $request->getTaxTotal()->getAmount());
        $this->assertSame($taxAmount, $request->getTaxTotal()->getAmount()->getValue());
        $this->assertSame($currency, $request->getTaxTotal()->getAmount()->getCurrency());

        // Delivery methods should not be set if input was empty
        $this->assertNull($request->getDeliveryMethods());
    }

    public function testBuildPaypalUpdateOrderRequestWithDeliveryMethods(): void
    {
        $pspReference   = 'PSP456';
        $paymentData    = 'another-payment-data';
        $amountValue    = 5000;
        $taxAmount      = 1000;
        $currency       = 'USD';

        // Shape is flexible; DeliveryMethod accepts an array and we only assert instances/count.
        $deliveryMethods = [
            ['id' => 'std', 'name' => 'Standard Shipping'],
            ['id' => 'exp', 'name' => 'Express Shipping'],
        ];

        $request = $this->subject->buildPaypalUpdateOrderRequest(
            $pspReference,
            $paymentData,
            $amountValue,
            $taxAmount,
            $currency,
            $deliveryMethods
        );

        $this->assertInstanceOf(PaypalUpdateOrderRequest::class, $request);
        $this->assertIsArray($request->getDeliveryMethods());
        $this->assertCount(2, $request->getDeliveryMethods());

        foreach ($request->getDeliveryMethods() as $dm) {
            $this->assertInstanceOf(DeliveryMethod::class, $dm);
        }
    }

    public function testHandlePaypalUpdateOrderResponse(): void
    {
        $response = $this->createMock(PaypalUpdateOrderResponse::class);
        $response->method('getStatus')->willReturn('success');
        $response->method('getPaymentData')->willReturn('pd-xyz');

        $result = $this->subject->handlePaypalUpdateOrderResponse($response);

        $this->assertSame(
            ['status' => 'success', 'paymentData' => 'pd-xyz'],
            $result
        );
    }

    public function testCreateAdyenUtilityApiServiceBubblesExceptions(): void
    {
        $storeId = 7;

        $this->adyenHelper
            ->expects($this->once())
            ->method('initializeAdyenClient')
            ->with($storeId)
            ->willThrowException(new NoSuchEntityException(__('Store not found')));

        $this->expectException(NoSuchEntityException::class);

        // Should bubble up the exception as declared in the signature
        $this->subject->createAdyenUtilityApiService($storeId);
    }
}
