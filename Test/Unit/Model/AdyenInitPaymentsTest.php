<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Helper\LineItemsDataBuilder;
use Adyen\ExpressCheckout\Model\AdyenInitPayments;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Adyen\Payment\Gateway\Http\TransferFactory;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\ShopperConversionId;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenInitPaymentsTest extends AbstractAdyenTestCase
{
    private AdyenInitPayments $adyenInitPayments;
    private CartRepositoryInterface|MockObject $cartRepository;
    private Config|MockObject $configHelper;
    private CheckoutStateDataValidator|MockObject $checkoutStateDataValidator;
    private TransferFactory|MockObject $transferFactory;
    private TransactionPayment|MockObject $transactionPaymentClient;
    private Data|MockObject $adyenHelper;
    private PaymentResponseHandler|MockObject $paymentResponseHandler;
    private MaskedQuoteIdToQuoteIdInterface|MockObject $maskedQuoteIdToQuoteId;
    private Vault|MockObject $vaultHelper;
    private UserContextInterface|MockObject $userContext;
    private Requests|MockObject $requestHelper;
    private PlatformInfo|MockObject $platformInfo;
    private MockObject $quote;
    private MockObject $transferMock;
    private MockObject $returnUrlHelper;
    private MockObject $shopperConversionId;
    private MockObject $paymentMethodsHelper;
    private MockObject $dataHelper;
    private MockObject $lineItemsDataBuilder;
    private array $headers;
    private string $stateData;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->returnUrlHelper = $this->createMock(ReturnUrlHelper::class);
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->transferFactory = $this->createMock(TransferFactory::class);
        $this->transactionPaymentClient = $this->createMock(TransactionPayment::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);
        $this->paymentResponseHandler = $this->createMock(PaymentResponseHandler::class);
        $this->maskedQuoteIdToQuoteId = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->vaultHelper = $this->createMock(Vault::class);
        $this->userContext = $this->createMock(UserContextInterface::class);
        $this->requestHelper = $this->createMock(Requests::class);

        $this->quote = $this->createMock(Quote::class);
        $this->transferMock = $this->createMock(\Magento\Payment\Gateway\Http\TransferInterface::class);
        $this->shopperConversionId = $this->createMock(ShopperConversionId::class);
        $this->paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        $this->dataHelper = $this->createMock(DataHelper::class);
        $this->lineItemsDataBuilder = $this->createMock(LineItemsDataBuilder::class);

        $this->headers = [
            'external-platform-name' => 'magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3',
            'external-platform-frontendtype' => 'default'
        ];

        $this->stateData = json_encode([
            'paymentMethod' => ['type' => 'paypal'],
            'clientStateDataIndicator' => true
        ]);

        $this->adyenInitPayments = new AdyenInitPayments(
            $this->cartRepository,
            $this->configHelper,
            $this->returnUrlHelper,
            $this->checkoutStateDataValidator,
            $this->transferFactory,
            $this->transactionPaymentClient,
            $this->adyenHelper,
            $this->paymentResponseHandler,
            $this->maskedQuoteIdToQuoteId,
            $this->vaultHelper,
            $this->userContext,
            $this->requestHelper,
            $this->platformInfo,
            $this->paymentMethodsHelper,
            $this->dataHelper,
            $this->lineItemsDataBuilder,
            $this->shopperConversionId
        );
    }

    public function testExecuteThrowsExceptionForInvalidJson(): void
    {
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->expectException(ValidatorException::class);
        $this->adyenInitPayments->execute('{invalid_json}', 1);
    }

    public function testExecuteThrowsExceptionForInvalidPaymentMethod(): void
    {
        $this->cartRepository->method('get')->willReturn($this->quote);
        $stateData = json_encode(['paymentMethod' => ['type' => 'ideal']]);
        $this->expectException(ValidatorException::class);
        $this->adyenInitPayments->execute($stateData, 1);
    }

    public function testExecuteUsesMaskedQuoteId(): void
    {
        $maskedId = 'abc123';
        $quoteId  = 42;

        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('save')->with($this->quote);

        $this->maskedQuoteIdToQuoteId->method('execute')
            ->with($maskedId)
            ->willReturn($quoteId);

        $this->cartRepository->method('get')->with($quoteId)->willReturn($this->quote);
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'EUR'],
            ['getSubtotalWithDiscount', [], 100.0],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')
            ->willReturn('https://example.test/adyen/return');
        $this->shopperConversionId->method('getShopperConversionId')
            ->willReturn(null);

        $pmInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')
            ->with('adyen_paypal')
            ->willReturn($pmInstance);

        $this->paymentMethodsHelper->method('getRequiresLineItems')
            ->with($pmInstance)
            ->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')
            ->willReturn([]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->with(100.0, 'EUR')->willReturn(10000);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');

        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);

        $this->transferFactory->method('create')->willReturn($this->transferMock);
        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Pending', 'action' => null],
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Pending', null)
            ->willReturn(['status' => 'success']);

        $result = $this->adyenInitPayments->execute($this->stateData, null, $maskedId);

        $this->assertJson($result);
        $this->assertStringContainsString('success', $result);
    }

    public function testExecuteThrowsClientException(): void
    {
        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('save')->with($this->quote);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 99],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')
            ->willReturn('https://example.test/adyen/return');
        $this->shopperConversionId->method('getShopperConversionId')
            ->willReturn(null);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')
            ->with('adyen_paypal')
            ->willReturn($pmInstance);

        $this->paymentMethodsHelper->method('getRequiresLineItems')
            ->with($pmInstance)
            ->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')
            ->willReturn([]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('formatAmount')->with(99, 'USD')->willReturn(9900);
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')
            ->willThrowException(new \Exception('Simulated error'));

        $this->expectException(ClientException::class);

        $this->adyenInitPayments->execute($this->stateData, 1);
    }

    public function testExecuteWithLoggedInUserRecurringDisabled(): void
    {
        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('save')->with($this->quote);

        $this->quote->method('getReservedOrderId')->willReturn('order321');
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 25.5],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')
            ->willReturn('https://example.test/adyen/return');

        $this->shopperConversionId->method('getShopperConversionId')
            ->willReturn(null);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')
            ->with('adyen_paypal')
            ->willReturn($pmInstance);

        $this->paymentMethodsHelper->method('getRequiresLineItems')
            ->with($pmInstance)
            ->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')
            ->willReturn([]);

        $stateData = json_encode([
            'paymentMethod' => ['type' => 'paypal'],
            'someOtherKey' => 'value',
        ]);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->with(25.5, 'USD')->willReturn(2550);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($stateData, true));
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');

        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(10);
        $this->vaultHelper->method('getPaymentMethodRecurringActive')
            ->with('adyen_paypal', 1)
            ->willReturn(false);
        $this->requestHelper->method('getShopperReference')
            ->with(10, null)
            ->willReturn('ref-10');

        $this->transferFactory->method('create')->willReturn($this->transferMock);
        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Pending', 'action' => null],
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Pending', null)
            ->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteWithHasOnlyGiftCardsCleansUpResponse(): void
    {
        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('save')->with($this->quote);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->quote->method('getReservedOrderId')->willReturn('order789');
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 70],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')
            ->willReturn('https://example.test/adyen/return');
        $this->shopperConversionId->method('getShopperConversionId')
            ->willReturn(null);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')
            ->with('adyen_paypal')
            ->willReturn($pmInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')
            ->with($pmInstance)
            ->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')
            ->willReturn([]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->with(70, 'USD')->willReturn(7000);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('Merchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            'hasOnlyGiftCards' => true,
            ['resultCode' => 'Pending', 'action' => null],
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Pending', null)
            ->willReturn(['status' => 'cleaned']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('cleaned', $response);
    }

    public function testExecuteWithMalformedStateDataArray(): void
    {
        $malformedStateData = json_encode([
            'paymentMethod' => []
        ]);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($malformedStateData, true));

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Error with payment method please select different payment method.');

        $this->adyenInitPayments->execute($malformedStateData, 1);
    }

    public function testExecuteAddsLineItemsWhenRequired(): void
    {
        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('save')->with($this->quote);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('orderLI');
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'EUR'],
            ['getSubtotalWithDiscount', [], 12.34],
        ]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->with(12.34, 'EUR')->willReturn(1234);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('MA');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($pmInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')->with($pmInstance)->willReturn(true);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')->willReturn([
            'lineItems' => [['id' => 'sku-1', 'amountIncludingTax' => 1234, 'description' => 'Test']]
        ]);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Pending', 'action' => null]
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'ok-with-lines']);

        $resp = $this->adyenInitPayments->execute($this->stateData, 1);
        $this->assertJson($resp);
        $this->assertStringContainsString('ok-with-lines', $resp);
    }

    public function testExecuteSetsAdditionalInformationOnQuotePaymentWithAllFields(): void
    {
        $paymentMock = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);

        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('get')->willReturn($this->quote);

        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('getPayment')->willReturn($paymentMock);

        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'EUR'],
            ['getSubtotalWithDiscount', [], 100.0],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')->willReturn('https://example.test/adyen/return');
        $this->shopperConversionId->method('getShopperConversionId')->willReturn(null);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($pmInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')->with($pmInstance)->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')->willReturn([]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('formatAmount')->with(100.0, 'EUR')->willReturn(10000);
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);

        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $action = ['type' => 'redirect', 'url' => 'https://example.test/redirect'];
        $additionalData = ['foo' => 'bar'];
        $details = ['payload' => 'abc'];

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            [
                'resultCode' => 'Pending',
                'pspReference' => 'PSP-123',
                'action' => $action,
                'additionalData' => $additionalData,
                'details' => $details,
            ],
        ]);

        $calls = [];
        $paymentMock->expects($this->exactly(5))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, $value) use (&$calls) {
                $calls[] = [$key, $value];
                return null;
            });

        $this->paymentResponseHandler->expects($this->once())
            ->method('formatPaymentResponse')
            ->with('Pending', $action)
            ->willReturn(['status' => 'success']);

        $result = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($result);
        $this->assertStringContainsString('success', $result);
        $this->assertSame([
            ['resultCode', 'Pending'],
            ['pspReference', 'PSP-123'],
            ['action', $action],
            ['additionalData', $additionalData],
            ['details', $details],
        ], $calls);
    }

    public function testExecuteSetsOnlyResultCodeWhenOptionalFieldsAreMissing(): void
    {
        $paymentMock = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);

        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('getPayment')->willReturn($paymentMock);

        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 99.0],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')->willReturn('https://example.test/adyen/return');
        $this->shopperConversionId->method('getShopperConversionId')->willReturn(null);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($pmInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')->with($pmInstance)->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')->willReturn([]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('formatAmount')->with(99.0, 'USD')->willReturn(9900);
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);

        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Refused', 'action' => null],
        ]);

        $calls = [];
        $paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, $value) use (&$calls) {
                $calls[] = [$key, $value];
                return null;
            });

        $this->paymentResponseHandler->expects($this->once())
            ->method('formatPaymentResponse')
            ->with('Refused', null)
            ->willReturn(['status' => 'refused']);

        $result = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertSame([['resultCode', 'Refused']], $calls);
        $this->assertJson($result);
        $this->assertStringContainsString('refused', $result);
    }

    public function testExecuteDoesNotSetAdditionalInformationWhenQuotePaymentIsNull(): void
    {
        $this->quote->method('reserveOrderId')->willReturnSelf();
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('getPayment')->willReturn(null);

        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'EUR'],
            ['getSubtotalWithDiscount', [], 10.0],
        ]);

        $this->returnUrlHelper->method('getStoreReturnUrl')->willReturn('https://example.test/adyen/return');
        $this->shopperConversionId->method('getShopperConversionId')->willReturn(null);

        $pmInstance = $this->createMock(MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($pmInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')->with($pmInstance)->willReturn(false);
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')->willReturn([]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('formatAmount')->with(10.0, 'EUR')->willReturn(1000);
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);

        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Pending', 'action' => null],
        ]);

        $this->paymentResponseHandler->expects($this->once())
            ->method('formatPaymentResponse')
            ->with('Pending', null)
            ->willReturn(['status' => 'no-payment-object']);

        $result = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($result);
        $this->assertStringContainsString('no-payment-object', $result);
    }

}
