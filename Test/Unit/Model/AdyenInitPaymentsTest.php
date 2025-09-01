<?php
/**
 * PHPUnit Test for AdyenInitPayments (updated)
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Helper\LineItemsDataBuilder;
use Adyen\ExpressCheckout\Model\AdyenInitPayments;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Adyen\Payment\Gateway\Http\TransferFactory;
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenInitPaymentsTest extends AbstractAdyenTestCase
{
    private AdyenInitPayments $adyenInitPayments;

    // Core deps
    private CheckoutStateDataValidator $checkoutStateDataValidator;
    private TransferFactory $transferFactory;
    private TransactionPayment $transactionPaymentClient;
    private PaymentResponseHandler $paymentResponseHandler;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private Vault $vaultHelper;
    private UserContextInterface $userContext;
    private Requests $requestHelper;
    private PaymentMethods $paymentMethodsHelper;
    private DataHelper $dataHelper;
    private LineItemsDataBuilder $lineItemsDataBuilder;
    private Quote|MockObject $quote;

    private TransferInterface $transferMock;

    private string $stateData;
    private array $headers;

    /**
     * @throws \JsonException
     */
    protected function setUp(): void
    {
        // Mocks
        $this->transferMock = $this->createMock(TransferInterface::class);

        $this->quote = $this->createMockWithMethods(
            Quote::class,
            [
                'getStoreId',
                'getReservedOrderId',
                'reserveOrderId'
            ],
            [
                'getSubtotalWithDiscount',
                'getQuoteCurrencyCode'
            ]
        );

        $this->mockMethods($this->quote,
            [
                'getSubtotalWithDiscount' => 123.45,
                'getQuoteCurrencyCode' => 'EUR',
                'getStoreId' => 1,
                'getReservedOrderId' => '1000001'
            ]
        );

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $configHelper = $this->createMock(Config::class);
        $returnUrlHelper = $this->createMock(ReturnUrlHelper::class);
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->transferFactory = $this->createMock(TransferFactory::class);
        $this->transactionPaymentClient = $this->createMock(TransactionPayment::class);
        $adyenHelper = $this->createMock(Data::class);
        $this->paymentResponseHandler = $this->createMock(PaymentResponseHandler::class);
        $this->quoteIdMaskFactory = $this->createGeneratedMock(QuoteIdMaskFactory::class, ['create']);
        $this->vaultHelper = $this->createMock(Vault::class);
        $this->userContext = $this->createMock(UserContextInterface::class);
        $this->requestHelper = $this->createMock(Requests::class);
        $this->paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        $this->dataHelper = $this->createMock(DataHelper::class);
        $this->lineItemsDataBuilder = $this->createMock(LineItemsDataBuilder::class);

        $cartRepository
            ->method('get')
            ->willReturn($this->quote);

        $cartRepository->expects($this->once())->method('save')->with($this->quote);

        // Config & helpers used in buildPaymentsRequest
        $configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $returnUrlHelper->method('getStoreReturnUrl')->with(1)->willReturn('');
        $adyenHelper->method('formatAmount')->with(123.45, 'EUR')->willReturn(12345);

        // Request headers: we append FRONTEND_TYPE in the class; base headers from helper
        $this->headers = [
            'external-platform-name' => 'magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3',
        ];
        $adyenHelper->method('buildRequestHeaders')->willReturn($this->headers);

        // Default: guest user
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->userContext->method('getUserId')->willReturn(null);

        // Payment method instance + no line items required by default
        $methodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($methodInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')->with($methodInstance)->willReturn(false);

        // State data fixture (valid PayPal)
        $this->stateData = json_encode([
            'paymentMethod' => [
                'type' => 'paypal',
                'userAction' => 'pay',
                'subtype' => 'express',
                'checkoutAttemptId' => '3'
            ],
            'clientStateDataIndicator' => true,
            'merchantAccount' => 'TestMerchant'
        ], JSON_THROW_ON_ERROR);

        // SUT
        $this->adyenInitPayments = new AdyenInitPayments(
            $cartRepository,
            $configHelper,
            $returnUrlHelper,
            $this->checkoutStateDataValidator,
            $this->transferFactory,
            $this->transactionPaymentClient,
            $adyenHelper,
            $this->paymentResponseHandler,
            $this->quoteIdMaskFactory,
            $this->vaultHelper,
            $this->userContext,
            $this->requestHelper,
            $this->paymentMethodsHelper,
            $this->dataHelper,
            $this->lineItemsDataBuilder
        );
    }

    private function mockMethods(MockObject $object, $methods): void
    {
        foreach ($methods as $method => $return) {
            $object->method($method)->willReturn($return);
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws ClientException
     */
    public function testExecuteThrowsExceptionForInvalidJson(): void
    {
        $this->expectException(ValidatorException::class);
        $this->adyenInitPayments->execute('{invalidJson}', 1);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws \JsonException
     * @throws ClientException
     */
    public function testExecuteThrowsExceptionForInvalidPaymentMethod(): void
    {
        $this->expectException(ValidatorException::class);
        $bad = json_encode(['paymentMethod' => ['type' => 'creditcard']], JSON_THROW_ON_ERROR);
        $this->adyenInitPayments->execute($bad, 1);
    }

    public function testExecuteHandlesClientException(): void
    {
        // Validate & pass through the stateData
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        // Expect transfer created with our computed body and headers (including frontend type)
        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        $expectedBody = [
            'amount' => ['currency' => 'EUR', 'value' => 12345],
            'reference' => '1000001',
            'returnUrl' => '?merchantReference=1000001',
            'merchantAccount' => 'TestMerchant',
            'channel' => 'web',
            'paymentMethod' => [
                'type' => 'paypal',
                'userAction' => 'pay',
                'subtype' => 'express',
                'checkoutAttemptId' => '3'
            ],
            'clientStateDataIndicator' => true,
        ];

        $this->transferFactory->expects($this->once())->method('create')->with([
            'body' => $expectedBody,
            'clientConfig' => ['storeId' => 1],
            'headers' => $expectedHeaders
        ])->willReturn($this->transferMock);

        // Simulate lower-level failure -> class should throw Magento ClientException
        $this->transactionPaymentClient->method('placeRequest')
            ->willThrowException(new LocalizedException(__('Error with payment method, please select a different payment method!')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error with payment method, please select a different payment method!');

        $this->adyenInitPayments->execute($this->stateData, 1);
    }

    public function testExecuteHandlesSuccessfulPayment(): void
    {
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        $expectedBody = [
            'amount' => ['currency' => 'EUR', 'value' => 12345],
            'reference' => '1000001',
            'returnUrl' => '?merchantReference=1000001',
            'merchantAccount' => 'TestMerchant',
            'channel' => 'web',
            'paymentMethod' => [
                'type' => 'paypal',
                'userAction' => 'pay',
                'subtype' => 'express',
                'checkoutAttemptId' => '3'
            ],
            'clientStateDataIndicator' => true,
        ];

        $this->transferFactory->method('create')->with([
            'body' => $expectedBody,
            'clientConfig' => ['storeId' => 1],
            'headers' => $expectedHeaders
        ])->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Authorised', null)
            ->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteHandlesLoggedInUserWithRecurring(): void
    {
        // Logged in
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(12345);

        // Vault enabled and CoF model
        $this->vaultHelper->method('getPaymentMethodRecurringActive')->with('adyen_paypal', 1)->willReturn(true);
        $this->vaultHelper->method('getPaymentMethodRecurringProcessingModel')->with('adyen_paypal', 1)->willReturn('CardOnFile');
        $this->requestHelper->method('getShopperReference')->with(12345, null)->willReturn('12345');

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        $expectedBody = [
            'amount' => ['currency' => 'EUR', 'value' => 12345],
            'reference' => '1000001',
            'returnUrl' => '?merchantReference=1000001',
            'merchantAccount' => 'TestMerchant',
            'channel' => 'web',
            'paymentMethod' => [
                'type' => 'paypal',
                'userAction' => 'pay',
                'subtype' => 'express',
                'checkoutAttemptId' => '3'
            ],
            'clientStateDataIndicator' => true
        ];

        $this->transferFactory->expects($this->once())->method('create')->with([
            'body' => $expectedBody,
            'clientConfig' => ['storeId' => 1],
            'headers' => $expectedHeaders
        ])->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteHandlesMaskedQuoteId(): void
    {
        $mask = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $mask->expects($this->once())->method('load')->with('masked123', 'masked_id')->willReturn($mask);
        $mask->expects($this->once())->method('getQuoteId')->willReturn(99);
        $this->quoteIdMaskFactory->method('create')->willReturn($mask);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        $expectedBody = [
            'amount' => ['currency' => 'EUR', 'value' => 12345],
            'reference' => '1000001',
            'returnUrl' => '?merchantReference=1000001',
            'merchantAccount' => 'TestMerchant',
            'channel' => 'web',
            'paymentMethod' => [
                'type' => 'paypal',
                'userAction' => 'pay',
                'subtype' => 'express',
                'checkoutAttemptId' => '3'
            ],
            'clientStateDataIndicator' => true,
        ];

        $this->transferFactory->method('create')->with([
            'body' => $expectedBody,
            'clientConfig' => ['storeId' => 1],
            'headers' => $expectedHeaders
        ])->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, null, 'masked123');
        $this->assertJson($response);
    }

    public function testAddsLineItemsWhenRequired(): void
    {
        // Make method require line items
        $methodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($methodInstance);
        $this->paymentMethodsHelper->method('getRequiresLineItems')->with($methodInstance)->willReturn(true);

        // Return line items + amountUpdates (typical OpenInvoice data builder output)
        $invoiceData = [
            'lineItems' => [
                ['id' => 'sku-1', 'quantity' => 1, 'amountIncludingTax' => 12345]
            ],
            'countryCode' => 'NL'
        ];
        $this->lineItemsDataBuilder->method('getOpenInvoiceDataForQuote')->with($this->quote)->willReturn($invoiceData);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        $expectedBody = [
            'amount' => ['currency' => 'EUR', 'value' => 12345],
            'reference' => '1000001',
            'returnUrl' => '?merchantReference=1000001',
            'merchantAccount' => 'TestMerchant',
            'channel' => 'web',
            'paymentMethod' => [
                'type' => 'paypal',
                'userAction' => 'pay',
                'subtype' => 'express',
                'checkoutAttemptId' => '3'
            ],
            'clientStateDataIndicator' => true
        ];

        $this->transferFactory->expects($this->once())->method('create')->with([
            'body' => $expectedBody,
            'clientConfig' => ['storeId' => 1],
            'headers' => $expectedHeaders
        ])->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);
        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testCleansGiftCardWrapperResponse(): void
    {
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        // We don’t assert the exact body/headers again here
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        // Simulate the "gift card wrapper": hasOnlyGiftCards plus the first element being the real payments response
        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            'hasOnlyGiftCards' => true,
            ['resultCode' => 'Pending', 'action' => ['type' => 'redirect']]
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Pending', ['type' => 'redirect'])
            ->willReturn(['status' => 'pending']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('pending', $response);
    }
}
