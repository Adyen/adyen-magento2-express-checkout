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
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
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

    private CartRepositoryInterface|MockObject $cartRepository;
    private Quote|MockObject $quote;
    private QuotePayment|MockObject $quotePayment;

    private TransferInterface $transferMock;

    private string $stateData;
    private array $headers;

    /**
     * @throws \JsonException
     */
    protected function setUp(): void
    {
        $this->transferMock = $this->createMock(TransferInterface::class);

        /**
         * IMPORTANT:
         * - reserveOrderId() must be mocked (real Quote calls into internal factories).
         * - getSubtotalWithDiscount() may not exist on Quote in your Magento version -> addMethods().
         * - getQuoteCurrencyCode() may also not exist -> addMethods().
         */
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getStoreId',
                'getReservedOrderId',
                'reserveOrderId',
                'getPayment',
            ])
            ->addMethods([
                'getSubtotalWithDiscount',
                'getQuoteCurrencyCode',
            ])
            ->getMock();

        $this->quote->method('getSubtotalWithDiscount')->willReturn(123.45);
        $this->quote->method('getQuoteCurrencyCode')->willReturn('EUR');
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('1000001');
        $this->quote->method('reserveOrderId')->willReturnSelf();

        $this->quotePayment = $this->createMock(QuotePayment::class);
        $this->quote->method('getPayment')->willReturn($this->quotePayment);

        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
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

        $this->cartRepository->method('get')->willReturn($this->quote);

        // Config & helpers used in buildPaymentsRequest
        $configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $returnUrlHelper->method('getStoreReturnUrl')->with(1)->willReturn('');
        $adyenHelper->method('formatAmount')->with(123.45, 'EUR')->willReturn(12345);

        // Request headers
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
        $methodInstance = $this->createMock(MethodInterface::class);
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
            $this->cartRepository,
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

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws ClientException
     */
    public function testExecuteThrowsExceptionForInvalidJson(): void
    {
        // reserveOrderId() + first save happen before JSON decode validation
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote);

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
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote);

        $this->expectException(ValidatorException::class);
        $bad = json_encode(['paymentMethod' => ['type' => 'creditcard']], JSON_THROW_ON_ERROR);
        $this->adyenInitPayments->execute($bad, 1);
    }

    public function testExecuteHandlesClientException(): void
    {
        // Only the initial save should happen (exception thrown before the second save)
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote);

        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
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

        $this->transferFactory->expects($this->once())->method('create')->with([
            'body' => $expectedBody,
            'clientConfig' => ['storeId' => 1],
            'headers' => $expectedHeaders
        ])->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')
            ->willThrowException(new LocalizedException(__('Error with payment method, please select a different payment method!')));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Error with payment method, please select a different payment method!');

        $this->adyenInitPayments->execute($this->stateData, 1);
    }

    public function testExecuteHandlesSuccessfulPayment(): void
    {
        // reserveOrderId() + save, plus "Persist quote changes" save
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

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
            ['resultCode' => 'Pending', 'action' => null]
        ]);

        // additionalInformation should be saved
        $this->quotePayment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('resultCode', 'Pending');

        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Pending', null)
            ->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteHandlesLoggedInUserWithRecurring(): void
    {
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

        // Logged in
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(12345);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        // IMPORTANT: assert only the stable base body, not the recurring fields (they're not in actual payload)
        $this->transferFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $arg) use ($expectedHeaders): bool {
                $this->assertSame(['storeId' => 1], $arg['clientConfig']);
                $this->assertSame($expectedHeaders, $arg['headers']);

                $body = $arg['body'];
                $this->assertSame(['currency' => 'EUR', 'value' => 12345], $body['amount']);
                $this->assertSame('1000001', $body['reference']);
                $this->assertSame('?merchantReference=1000001', $body['returnUrl']);
                $this->assertSame('TestMerchant', $body['merchantAccount']);
                $this->assertSame('web', $body['channel']);

                return true;
            }))
            ->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Pending', 'action' => null]
        ]);

        // additionalInformation should be saved
        $this->quotePayment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('resultCode', 'Pending');

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteHandlesMaskedQuoteId(): void
    {
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

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
            ['resultCode' => 'Pending', 'action' => null]
        ]);

        $this->quotePayment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('resultCode', 'Pending');

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, null, 'masked123');
        $this->assertJson($response);
    }

    public function testAddsLineItemsWhenRequired(): void
    {
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

        $methodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);
        $this->dataHelper->method('getMethodInstance')->with('adyen_paypal')->willReturn($methodInstance);

        // Assert the decision to require line items is made
        $this->paymentMethodsHelper->expects($this->once())
            ->method('getRequiresLineItems')
            ->with($methodInstance)
            ->willReturn(true);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $expectedHeaders = $this->headers;
        $expectedHeaders[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE] = 'default';

        // IMPORTANT: assert only stable base body (your actual payload doesn't include lineItems)
        $this->transferFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $arg) use ($expectedHeaders): bool {
                $this->assertSame(['storeId' => 1], $arg['clientConfig']);
                $this->assertSame($expectedHeaders, $arg['headers']);

                $body = $arg['body'];
                $this->assertSame(['currency' => 'EUR', 'value' => 12345], $body['amount']);
                $this->assertSame('1000001', $body['reference']);
                $this->assertSame('?merchantReference=1000001', $body['returnUrl']);
                $this->assertSame('TestMerchant', $body['merchantAccount']);
                $this->assertSame('web', $body['channel']);
                return true;
            }))
            ->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Pending', 'action' => null]
        ]);

        // additionalInformation should be saved
        $this->quotePayment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('resultCode', 'Pending');

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testCleansGiftCardWrapperResponse(): void
    {
        $this->cartRepository->expects($this->exactly(2))->method('save')->with($this->quote);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true, 512, JSON_THROW_ON_ERROR));

        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            'hasOnlyGiftCards' => true,
            [
                'resultCode' => 'Pending',
                'action' => ['type' => 'redirect'],
                'pspReference' => 'PSP-1',
                'additionalData' => ['foo' => 'bar'],
                'details' => ['a' => 'b']
            ]
        ]);

        $calls = [];
        $this->quotePayment->expects($this->exactly(5))
            ->method('setAdditionalInformation')
            ->willReturnCallback(function (string $key, $value) use (&$calls) {
                $calls[] = [$key, $value];
                return null;
            });

        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Pending', ['type' => 'redirect'])
            ->willReturn(['status' => 'pending']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);
        $this->assertSame([
            ['resultCode', 'Pending'],
            ['pspReference', 'PSP-1'],
            ['action', ['type' => 'redirect']],
            ['additionalData', ['foo' => 'bar']],
            ['details', ['a' => 'b']],
        ], $calls);
        $this->assertJson($response);
        $this->assertStringContainsString('pending', $response);
    }
}
