<?php
/**
 * PHPUnit Test for AdyenInitPayments
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\AdyenInitPayments;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Adyen\Payment\Gateway\Http\TransferFactory;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Adyen\Payment\Helper\Requests;

class AdyenInitPaymentsTest extends AbstractAdyenTestCase
{
    private AdyenInitPayments $adyenInitPayments;
    private CartRepositoryInterface $cartRepository;
    private CheckoutStateDataValidator $checkoutStateDataValidator;
    private TransferFactory $transferFactory;
    private TransactionPayment $transactionPaymentClient;
    private PaymentResponseHandler $paymentResponseHandler;
    private QuoteIdMaskFactory $quoteIdMaskFactoryMock;
    private Vault $vaultHelper;
    private Requests $requestHelper;

    protected function setUp(): void
    {
        $this->transferMock = $this->createMock(\Magento\Payment\Gateway\Http\TransferInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $configHelper = $this->createMock(Config::class);
        $returnUrlHelper = $this->createMock(ReturnUrlHelper::class);
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->transferFactory = $this->createMock(TransferFactory::class);
        $this->transactionPaymentClient = $this->createMock(TransactionPayment::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->vaultHelper = $this->createConfiguredMock(Vault::class, [
            'getPaymentMethodRecurringActive' => true,
            'getPaymentMethodRecurringProcessingModel' => 'CardOnFile'
        ]);
        $this->requestHelper = $this->createMock(Requests::class);
        $this->paymentResponseHandler = $this->createMock(PaymentResponseHandler::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(
            QuoteIdMaskFactory::class,
            ['create']
        );
        $this->quoteIdMaskMock = $this->createMock(QuoteIdMask::class);
        $this->quoteIdMaskFactoryMock->method('create')->willReturn($this->quoteIdMaskMock);
        $this->userContext = $this->createMock(UserContextInterface::class);
        $requestHelper = $this->createMock(Requests::class);
        $this->quote = $this->createMock(Quote::class);

        $this->quote->method('getReservedOrderId')->willReturn('1000001');
        $this->paymentsRequest = [
            'amount' => [
                'currency' => null,
                'value' => null
            ],
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

        $this->paymentsRequestLoggedInUser = [
            'amount' => [
                'currency' => null,
                'value' => null
            ],
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
            'storePaymentMethod' => true,
            'shopperReference' => '',
            'shopperInteraction' => 'Ecommerce',
            'recurringProcessingModel' => 'CardOnFile'
        ];

        $this->headers = [
            'external-platform-name' => 'magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3',
            'external-platform-frontendtype' => 'expresscheckout'
        ];
        $this->adyenInitPayments = new AdyenInitPayments(
            $this->cartRepository,
            $configHelper,
            $returnUrlHelper,
            $this->checkoutStateDataValidator,
            $this->transferFactory,
            $this->transactionPaymentClient,
            $this->adyenHelper,
            $this->paymentResponseHandler,
            $this->quoteIdMaskFactoryMock,
            $this->vaultHelper,
            $this->userContext,
            $requestHelper
        );

        $this->stateData = '{"paymentMethod":{"type":"paypal","userAction":"pay","subtype":"express","checkoutAttemptId":"3"},"clientStateDataIndicator":true,"merchantAccount":"TestMerchant"}';

    }

    public function testExecuteThrowsExceptionForInvalidJson(): void
    {
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->expectException(ValidatorException::class);
        $this->adyenInitPayments->execute('{invalidJson}', 1);
    }

    public function testExecuteThrowsExceptionForInvalidPaymentMethod(): void
    {
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->expectException(ValidatorException::class);
        $this->adyenInitPayments->execute(json_encode(['paymentMethod' => ['type' => 'creditcard']]), 1);
    }

    public function testExecuteHandlesClientException(): void
    {

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('buildRequestHeaders')
            ->willReturn($this->headers);
        $this->transferFactory->expects($this->once())
            ->method('create')
            ->with([
                'body' => $this->paymentsRequest,
                'clientConfig' => ['storeId' => $this->quote->getStoreId()],
                'headers' => $this->headers
            ])
            ->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')
            ->willThrowException(new LocalizedException(__('Error with payment method, please select a different payment method!')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Error with payment method, please select a different payment method!');

        // Call the method to test exception handling
        $this->adyenInitPayments->execute($this->stateData, 1);
    }

    public function testExecuteHandlesSuccessfulPayment()
    {
        $this->cartRepository->method('get')->willReturn($this->quote);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('buildRequestHeaders')
            ->willReturn($this->headers);
        $this->transferFactory->method('create')->with([
            'body' => $this->paymentsRequest,
            'clientConfig' => ['storeId' => $this->quote->getStoreId()],
            'headers' => $this->headers
        ])
            ->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteHandlesLoggedInUser(): void
    {
        $customerId = 12345;
        $this->cartRepository->method('get')->willReturn($this->quote);
        $paymentMethodCode = 'adyen_paypal';
        $storeId = $this->quote
            ->method('getStoreId')
            ->willReturn(1);
        $this->userContext->expects($this->once())
            ->method('getUserType')
            ->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);

        $this->userContext->expects($this->once())
            ->method('getUserId')
            ->willReturn($customerId);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true)
        );

        // Mock RequestHelper method
        $this->requestHelper
            ->method('getShopperReference')
            ->with($customerId, null)
            ->willReturn('12345');

        $this->adyenHelper->method('buildRequestHeaders')
            ->willReturn($this->headers);

        $this->transferFactory->method('create')->with([
            'body' => $this->paymentsRequestLoggedInUser,
            'clientConfig' => ['storeId' => $this->quote->getStoreId()],
            'headers' => $this->headers
        ])
            ->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);

        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteHandlesMaskedQuoteId()
    {
        $adyenMaskedQuoteId = '231';
        $expectedQuoteId = 99;

        $this->quoteIdMaskMock->expects($this->once())
            ->method('load')
            ->with($adyenMaskedQuoteId, 'masked_id')
            ->willReturn($this->quoteIdMaskMock);


        $this->quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $this->quoteIdMaskMock->method('load')->willReturn($this->quoteIdMaskMock);
        $this->quoteIdMaskMock->method('getQuoteId')->willReturn($expectedQuoteId);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true)
            );

        $this->adyenHelper->method('buildRequestHeaders')
            ->willReturn($this->headers);

        $this->transferFactory->method('create')->with([
            'body' => $this->paymentsRequest,
            'clientConfig' => ['storeId' => $this->quote->getStoreId()],
            'headers' => $this->headers
        ])
            ->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);


        // Call the method under test
        $response = $this->adyenInitPayments->execute($this->stateData, null, $adyenMaskedQuoteId);

        $this->assertJson($response);
    }

}
