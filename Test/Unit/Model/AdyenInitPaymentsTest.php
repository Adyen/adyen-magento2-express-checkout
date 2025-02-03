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

    protected function setUp(): void
    {
        $this->transferMock = $this->createMock(\Magento\Payment\Gateway\Http\TransferInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $configHelper = $this->createMock(Config::class);
        $returnUrlHelper = $this->createMock(ReturnUrlHelper::class);
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->transferFactory = $this->createMock(TransferFactory::class);
        $this->transactionPaymentClient = $this->createMock(TransactionPayment::class);
        $adyenHelper = $this->createMock(Data::class);
        $this->paymentResponseHandler = $this->createMock(PaymentResponseHandler::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $vaultHelper = $this->createMock(Vault::class);
        $userContext = $this->createMock(UserContextInterface::class);
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

        $this->adyenInitPayments = new AdyenInitPayments(
            $this->cartRepository,
            $configHelper,
            $returnUrlHelper,
            $this->checkoutStateDataValidator,
            $this->transferFactory,
            $this->transactionPaymentClient,
            $adyenHelper,
            $this->paymentResponseHandler,
            $this->quoteIdMaskFactoryMock,
            $vaultHelper,
            $userContext,
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
        $this->transferFactory->expects($this->once())
            ->method('create')
            ->with([
                'body' => $this->paymentsRequest,
                'clientConfig' => ['storeId' => $this->quote->getStoreId()]
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

        $this->transferFactory->method('create')->with([
            'body' => $this->paymentsRequest,
            'clientConfig' => ['storeId' => $this->quote->getStoreId()]
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
}
