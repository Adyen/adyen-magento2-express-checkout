<?php

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Helper\PaypalUpdateOrder;
use Adyen\ExpressCheckout\Helper\Util\PaypalDeliveryMethodValidator;
use Adyen\ExpressCheckout\Model\AdyenPaypalUpdateOrder;
use Adyen\ExpressCheckout\Model\ResourceModel\PaymentResponse\Collection as AdyenPaymentResponseCollection;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenPaypalUpdateOrderTest extends AbstractAdyenTestCase
{
    private AdyenPaypalUpdateOrder $model;

    private MockObject $cartRepository;
    private MockObject $deliveryMethodValidator;
    private MockObject $chargedCurrency;
    private MockObject $adyenHelper;
    private MockObject $paymentResponseCollection;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $paypalUpdateOrderHelper = $this->createMock(PaypalUpdateOrder::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->deliveryMethodValidator = $this->createMock(PaypalDeliveryMethodValidator::class);
        $this->chargedCurrency = $this->createMock(ChargedCurrency::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->paymentResponseCollection = $this->createMock(AdyenPaymentResponseCollection::class);
        $maskedQuoteIdToQuoteId = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);

        $this->model = new AdyenPaypalUpdateOrder(
            $paypalUpdateOrderHelper,
            $this->cartRepository,
            $this->deliveryMethodValidator,
            $this->chargedCurrency,
            $this->adyenHelper,
            $this->paymentResponseCollection,
            $maskedQuoteIdToQuoteId
        );
    }

    /**
     * @throws NoSuchEntityException|Exception
     */
    public function testExecuteThrowsExceptionForMissingMerchantReference(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getReservedOrderId')->willReturn(null);

        $this->cartRepository->method('get')->willReturn($quote);

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Order ID has not been reserved!');

        $deliveryMethods = json_encode([
            ['reference' => '1', 'description' => 'flatrate', 'type' => 'Shipping', 'amount' => ['currency' => 'MXN', 'value' => 100], 'selected' => true]
        ]);
        $this->deliveryMethodValidator->method('getValidatedDeliveryMethod')->willReturn([
            'reference' => '1',
            'description' => 'flatrate',
            'type' => 'Shipping',
            'amount' => ['currency' => 'MXN', 'value' => 100],
            'selected' => true
        ]);

        $this->model->execute('somePaymentData', 1, null, $deliveryMethods);
    }

    public function testExecuteThrowsExceptionIfPaymentResponseMissing(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getReservedOrderId')->willReturn('ORD123');
        $quote->method('getStoreId')->willReturn(1);
        $this->cartRepository->method('get')->willReturn($quote);

        $this->paymentResponseCollection
            ->method('getPaymentResponseWithMerchantReference')
            ->with('ORD123')
            ->willReturn(null);

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage("Payment response couldn't be found!");

        $deliveryMethods = json_encode([
            ['reference' => '1', 'description' => 'flatrate', 'type' => 'Shipping', 'amount' => ['currency' => 'MXN', 'value' => 100], 'selected' => true]
        ]);

        $this->deliveryMethodValidator->method('getValidatedDeliveryMethod')->willReturn([
            'reference' => '1',
            'description' => 'flatrate',
            'type' => 'Shipping',
            'amount' => ['currency' => 'MXN', 'value' => 100],
            'selected' => true
        ]);

        $this->model->execute('somePaymentData', 1, null, $deliveryMethods);
    }

    /**
     * @throws NoSuchEntityException
     * @throws ValidatorException
     * @throws Exception
     */
    public function testExecuteReturnsJsonOnSuccess(): void
    {
        $quoteId = 10;
        $merchantReference = 'ORD123';
        $currency = 'USD';
        $grandTotal = 100;
        $taxAmount = 5;
        $formattedAmount = 10000;
        $formattedTaxAmount = 500;

        $quote = $this->createMockWithMethods(
            Quote::class,
            ['getStoreId', 'getReservedOrderId', 'isVirtual', 'getShippingAddress'],
            ['getGrandTotal']
        );

        $quote->method('getReservedOrderId')->willReturn($merchantReference);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getGrandTotal')->willReturn($grandTotal);
        $quote->method('isVirtual')->willReturn(false);

        $address = $this->getMockBuilder(\Magento\Quote\Model\Quote\Address::class)
            ->disableOriginalConstructor()
            ->addMethods(['getTaxAmount'])
            ->getMock();
        $address->method('getTaxAmount')->willReturn($taxAmount);

        $quote->method('getShippingAddress')->willReturn($address);

        $this->cartRepository->method('get')->with($quoteId)->willReturn($quote);

        $this->paymentResponseCollection->method('getPaymentResponseWithMerchantReference')
            ->willReturn(['response' => json_encode(['pspReference' => 'PSP123'])]);

        $this->chargedCurrency->method('getQuoteAmountCurrency')->willReturn(
            $this->createConfiguredMock(AdyenAmountCurrency::class, [
                'getCurrencyCode' => $currency
            ])
        );

        $this->adyenHelper->method('formatAmount')->willReturnMap([
            [$grandTotal, $currency, $formattedAmount],
            [$taxAmount, $currency, $formattedTaxAmount]
        ]);

        $validatedDeliveryMethod = [
            'reference' => '1',
            'description' => 'flatrate',
            'type' => 'Shipping',
            'amount' => ['currency' => $currency, 'value' => 1000],
            'selected' => true
        ];

        $this->deliveryMethodValidator->method('getValidatedDeliveryMethod')->willReturn($validatedDeliveryMethod);

        $this->adyenHelper->expects($this->once())->method('logRequest');

        $deliveryMethods = json_encode([$validatedDeliveryMethod]);
        $this->model->execute('somePaymentData', $quoteId, null, $deliveryMethods);
    }
}
