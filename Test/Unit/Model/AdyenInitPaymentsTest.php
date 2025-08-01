<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\AdyenInitPayments;
use Adyen\Payment\Gateway\Http\Client\TransactionPayment;
use Adyen\Payment\Gateway\Http\TransferFactory;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Helper\ReturnUrlHelper;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
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
    private array $headers;
    private string $stateData;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->configHelper = $this->createMock(Config::class);
        $returnUrlHelper = $this->createMock(ReturnUrlHelper::class);
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
            $returnUrlHelper,
            $this->checkoutStateDataValidator,
            $this->transferFactory,
            $this->transactionPaymentClient,
            $this->adyenHelper,
            $this->paymentResponseHandler,
            $this->maskedQuoteIdToQuoteId,
            $this->vaultHelper,
            $this->userContext,
            $this->requestHelper,
            $this->platformInfo
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
        $quoteId = 42;

        $this->maskedQuoteIdToQuoteId->method('execute')->with($maskedId)->willReturn($quoteId);
        $this->cartRepository->method('get')->with($quoteId)->willReturn($this->quote);
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('__call')
            ->willReturnMap([
                ['getQuoteCurrencyCode', [], 'EUR'],
                ['getSubtotalWithDiscount', [], 100.0],
            ]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->willReturn(10000);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);
        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $result = $this->adyenInitPayments->execute($this->stateData, null, $maskedId);
        $this->assertJson($result);
        $this->assertStringContainsString('success', $result);
    }

    public function testExecuteThrowsClientException(): void
    {
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 99],
        ]);
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('formatAmount')->willReturn(9900);
        $this->configHelper->method('getMerchantAccount')->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);
        $this->transactionPaymentClient->method('placeRequest')->willThrowException(new \Exception('Simulated error'));

        $this->expectException(ClientException::class);
        $this->adyenInitPayments->execute($this->stateData, 1);
    }

    public function testExecuteWithLoggedInUserRecurringDisabled(): void
    {
        $this->quote->method('getReservedOrderId')->willReturn('order321');
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 25.5],
        ]);

        $stateData = json_encode([
            'paymentMethod' => ['type' => 'paypal'],
            'someOtherKey' => 'value'
        ]);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->willReturn(2550);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($stateData, true));
        $this->configHelper->method('getMerchantAccount')->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(10);
        $this->vaultHelper->method('getPaymentMethodRecurringActive')->willReturn(false);
        $this->requestHelper->method('getShopperReference')->willReturn('ref-10');
        $this->transferFactory->method('create')->willReturn($this->transferMock);
        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null]
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'success']);

        $response = $this->adyenInitPayments->execute($stateData, 1);
        $this->assertJson($response);
        $this->assertStringContainsString('success', $response);
    }

    public function testExecuteWithHasOnlyGiftCardsCleansUpResponse(): void
    {
        $this->quote->method('getReservedOrderId')->willReturn('order789');
        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('__call')->willReturnMap([
            ['getQuoteCurrencyCode', [], 'USD'],
            ['getSubtotalWithDiscount', [], 70],
        ]);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->willReturn(7000);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->willReturn('Merchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            'hasOnlyGiftCards' => true,
            ['resultCode' => 'Authorised', 'action' => null]
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'cleaned']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);
        $this->assertJson($response);
        $this->assertStringContainsString('cleaned', $response);
    }

    public function testExecuteWithMalformedStateDataArray(): void
    {
        $malformedStateData = json_encode([
            'paymentMethod' => [] // Missing 'type'
        ]);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn(json_decode($malformedStateData, true));

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Error with payment method please select different payment method.');

        $this->adyenInitPayments->execute($malformedStateData, 1);
    }
}
