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
use Magento\Payment\Gateway\Http\TransferInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenInitPaymentsTest extends AbstractAdyenTestCase
{
    /** @var AdyenInitPayments */
    private $adyenInitPayments;

    /** @var CartRepositoryInterface|MockObject */
    private $cartRepository;
    /** @var Config|MockObject */
    private $configHelper;
    /** @var ReturnUrlHelper|MockObject */
    private $returnUrlHelper;
    /** @var CheckoutStateDataValidator|MockObject */
    private $checkoutStateDataValidator;
    /** @var TransferFactory|MockObject */
    private $transferFactory;
    /** @var TransactionPayment|MockObject */
    private $transactionPaymentClient;
    /** @var Data|MockObject */
    private $adyenHelper;
    /** @var PaymentResponseHandler|MockObject */
    private $paymentResponseHandler;
    /** @var MaskedQuoteIdToQuoteIdInterface|MockObject */
    private $maskedQuoteIdToQuoteId;
    /** @var Vault|MockObject */
    private $vaultHelper;
    /** @var UserContextInterface|MockObject */
    private $userContext;
    /** @var Requests|MockObject */
    private $requestHelper;
    /** @var PlatformInfo|MockObject */
    private $platformInfo;

    /** @var Quote|MockObject */
    private $quote;
    /** @var TransferInterface|MockObject */
    private $transferMock;

    private array $headers;
    private string $stateData;

    protected function setUp(): void
    {
        $this->cartRepository            = $this->createMock(CartRepositoryInterface::class);
        $this->configHelper              = $this->createMock(Config::class);
        $this->returnUrlHelper           = $this->createMock(ReturnUrlHelper::class);
        $this->checkoutStateDataValidator= $this->createMock(CheckoutStateDataValidator::class);
        $this->transferFactory           = $this->createMock(TransferFactory::class);
        $this->transactionPaymentClient  = $this->createMock(TransactionPayment::class);
        $this->adyenHelper               = $this->createMock(Data::class);
        $this->paymentResponseHandler    = $this->createMock(PaymentResponseHandler::class);
        $this->maskedQuoteIdToQuoteId    = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->vaultHelper               = $this->createMock(Vault::class);
        $this->userContext               = $this->createMock(UserContextInterface::class);
        $this->requestHelper             = $this->createMock(Requests::class);
        $this->platformInfo              = $this->createMock(PlatformInfo::class);

        $this->quote        = $this->createMock(Quote::class);
        $this->transferMock = $this->createMock(TransferInterface::class);

        $this->headers = [
            'external-platform-name'         => 'magento',
            'external-platform-version'      => '2.x.x',
            'external-platform-edition'      => 'Community',
            'merchant-application-name'      => 'adyen-magento2',
            'merchant-application-version'   => '1.2.3',
            'external-platform-frontendtype' => 'default',
        ];

        $this->stateData = json_encode([
            'paymentMethod' => ['type' => 'paypal'],
            'clientStateDataIndicator' => true,
        ], JSON_THROW_ON_ERROR);

        // Create SUT without invoking constructor (constructor arg count varies across branches)
        $this->adyenInitPayments = $this->getMockBuilder(AdyenInitPayments::class)
            ->disableOriginalConstructor()
            ->onlyMethods([]) // keep real methods
            ->getMock();

        // Inject only what the class actually uses
        $this->inject('cartRepository',            $this->cartRepository);
        $this->inject('configHelper',              $this->configHelper);
        $this->inject('returnUrlHelper',           $this->returnUrlHelper);
        $this->inject('checkoutStateDataValidator',$this->checkoutStateDataValidator);
        $this->inject('transferFactory',           $this->transferFactory);
        $this->inject('transactionPaymentClient',  $this->transactionPaymentClient);
        $this->inject('adyenHelper',               $this->adyenHelper);
        $this->inject('paymentResponseHandler',    $this->paymentResponseHandler);
        $this->inject('maskedQuoteIdToQuoteId',    $this->maskedQuoteIdToQuoteId);
        $this->inject('vaultHelper',               $this->vaultHelper);
        $this->inject('userContext',               $this->userContext);
        $this->inject('requestHelper',             $this->requestHelper);
        $this->inject('platformInfo',              $this->platformInfo);

        // Common default
        $this->returnUrlHelper->method('getStoreReturnUrl')->willReturn('https://example.test/adyen/return');
    }

    /** Small helper to set private properties via reflection */
    private function inject(string $prop, $value): void
    {
        $ref = new \ReflectionProperty(AdyenInitPayments::class, $prop);
        $ref->setAccessible(true);
        $ref->setValue($this->adyenInitPayments, $value);
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
        $bad = json_encode(['paymentMethod' => ['type' => 'ideal']], JSON_THROW_ON_ERROR);

        $this->expectException(ValidatorException::class);
        $this->adyenInitPayments->execute($bad, 1);
    }

    public function testExecuteUsesMaskedQuoteId(): void
    {
        $maskedId = 'abc123';
        $quoteId  = 42;

        $this->maskedQuoteIdToQuoteId->method('execute')->with($maskedId)->willReturn($quoteId);
        $this->cartRepository->method('get')->with($quoteId)->willReturn($this->quote);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->quote->method('getReservedOrderId')->willReturn('order123');
        // emulate magic getters
        $this->quote->method('__call')
            ->willReturnMap([
                ['getQuoteCurrencyCode', [], 'EUR'],
                ['getSubtotalWithDiscount', [], 100.0],
            ]);

        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->with(100.0, 'EUR')->willReturn(10000);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->with(1)->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null],
        ]);

        $this->paymentResponseHandler->method('formatPaymentResponse')
            ->with('Authorised', null)
            ->willReturn(['status' => 'success']);

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
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->adyenHelper->method('formatAmount')->with(99, 'USD')->willReturn(9900);
        $this->configHelper->method('getMerchantAccount')->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')
            ->willThrowException(new \Exception('Simulated error'));

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
            'someOtherKey' => 'value',
        ], JSON_THROW_ON_ERROR);

        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->platformInfo->method('buildRequestHeaders')->willReturn($this->headers);
        $this->adyenHelper->method('formatAmount')->with(25.5, 'USD')->willReturn(2550);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($stateData, true));
        $this->configHelper->method('getMerchantAccount')->willReturn('TestMerchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(10);
        $this->vaultHelper->method('getPaymentMethodRecurringActive')->willReturn(false);
        $this->requestHelper->method('getShopperReference')->with(10, null)->willReturn('ref-10');
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            ['resultCode' => 'Authorised', 'action' => null],
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
        $this->adyenHelper->method('formatAmount')->with(70, 'USD')->willReturn(7000);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($this->stateData, true));
        $this->configHelper->method('getMerchantAccount')->willReturn('Merchant');
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->transferFactory->method('create')->willReturn($this->transferMock);

        $this->transactionPaymentClient->method('placeRequest')->willReturn([
            'hasOnlyGiftCards' => true,
            ['resultCode' => 'Authorised', 'action' => null],
        ]);
        $this->paymentResponseHandler->method('formatPaymentResponse')->willReturn(['status' => 'cleaned']);

        $response = $this->adyenInitPayments->execute($this->stateData, 1);
        $this->assertJson($response);
        $this->assertStringContainsString('cleaned', $response);
    }

    public function testExecuteWithMalformedStateDataArray(): void
    {
        $malformedStateData = json_encode(['paymentMethod' => []], JSON_THROW_ON_ERROR);

        $this->quote->method('getStoreId')->willReturn(1);
        $this->cartRepository->method('get')->willReturn($this->quote);
        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(json_decode($malformedStateData, true));

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Error with payment method please select different payment method.');
        $this->adyenInitPayments->execute($malformedStateData, 1);
    }
}
