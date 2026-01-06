<?php

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Block\ApplePay\Shortcut\Button as ApplePayButton;
use Adyen\ExpressCheckout\Block\GooglePay\Shortcut\Button as GooglePayButton;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button as PayPalButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\ExpressCheckout\Model\Ui\AdyenExpressConfigProvider;
use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Checkout\Model\Session;

class AdyenExpressConfigProviderTest extends AbstractAdyenTestCase
{
    protected AdyenExpressConfigProvider $adyenExpressConfigProvider;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected ConfigurationInterface|MockObject $configHelperMock;
    protected AdyenPaymentMethodManagementInterface|MockObject $adyenPaymentMethodManagementMock;
    protected Session|MockObject $checkoutSessionMock;
    protected ChargedCurrency|MockObject $chargeCurrencyHelperMock;
    protected Data|MockObject $adyenHelperMock;
    protected ScopeConfigInterface|MockObject $scopeConfigMock;
    protected UrlInterface|MockObject $urlMock;


    public function setUp(): void
    {
        parent::setUp();
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->configHelperMock = $this->createMock(ConfigurationInterface::class);
        $this->adyenPaymentMethodManagementMock = $this->createMock(AdyenPaymentMethodManagementInterface::class);
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->chargeCurrencyHelperMock = $this->createMock(ChargedCurrency::class);
        $this->adyenHelperMock = $this->createPartialMock(Data::class, []);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->urlMock = $this->createMock(UrlInterface::class);

        $this->adyenExpressConfigProvider = new AdyenExpressConfigProvider(
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->adyenPaymentMethodManagementMock,
            $this->checkoutSessionMock,
            $this->chargeCurrencyHelperMock,
            $this->adyenHelperMock,
            $this->scopeConfigMock,
            $this->urlMock
        );
    }

    public function testGetConfig(): void
    {
        $storeId = 1;
        $storeCode = 'default';
        $showApplepayOn = ['1', '2', '3', '4'];
        $applepayButtonColor = 'black';
        $showGooglepayOn = ['1', '2', '3', '4'];
        $showPaypalOn = ['1', '2', '3', '4'];
        $amount = 10.10;
        $currency = 'EUR';
        $expectedUrl = 'checkout/onepage/success';
        $paymentMethodsResponse = '{"paymentMethodsResponse":{"paymentMethods":[{"brands":["amex","mc","visa"],"configuration":{"merchantId":"000000000310978","merchantName":"Can Demiralp"},"name":"Apple Pay","type":"applepay"}]}}';

        $isVirtual = false;

        $this->adyenPaymentMethodManagementMock->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn($paymentMethodsResponse);

        $this->urlMock->expects($this->once())->method('getUrl')->willReturn($expectedUrl);

        // Mock store
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn($storeId);
        $storeMock->method('getCode')->willReturn($storeCode);
        $this->storeManagerMock->method('getStore')->willReturn($storeMock);

        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn($isVirtual);
        $quote->method('getId')->willReturn('1');
        $this->checkoutSessionMock->method('getQuote')->willReturn($quote);

        $quoteAmountCurrency = $this->createMock(AdyenAmountCurrency::class);
        $quoteAmountCurrency->method('getAmount')->willReturn($amount);
        $quoteAmountCurrency->method('getCurrencyCode')->willReturn($currency);
        $this->chargeCurrencyHelperMock->expects($this->once())
            ->method('getQuoteAmountCurrency')
            ->with($quote)
            ->willReturn($quoteAmountCurrency);

        // Mock config helper for Apple Pay
        $this->configHelperMock->expects($this->exactly(3))
            ->method('getShowPaymentMethodOn')
            ->willReturnCallback(function ($variant, $scope, $id) use ($storeId, $showApplepayOn, $showGooglepayOn, $showPaypalOn) {
                $this->assertEquals(ScopeInterface::SCOPE_STORE, $scope);
                $this->assertEquals($storeId, $id);

                return match ($variant) {
                    ApplePayButton::PAYMENT_METHOD_VARIANT => $showApplepayOn,
                    GooglePayButton::PAYMENT_METHOD_VARIANT => $showGooglepayOn,
                    PayPalButton::PAYMENT_METHOD_VARIANT => $showPaypalOn,
                    default => null
                };
            });

        $this->configHelperMock->expects($this->once())
            ->method('getApplePayButtonColor')
            ->with(ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn($applepayButtonColor);

        // Call the method
        $result = $this->adyenExpressConfigProvider->getConfig();

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('adyenExpress', $result['payment']);

        $adyenExpressConfig = $result['payment']['adyenExpress'];

        $this->assertArrayHasKey('applepay', $adyenExpressConfig);
        $this->assertArrayHasKey('isEnabledOnShipping', $adyenExpressConfig['applepay']);
        $this->assertArrayHasKey('buttonColor', $adyenExpressConfig['applepay']);
        $this->assertArrayHasKey('googlepay', $adyenExpressConfig);
        $this->assertArrayHasKey('isEnabledOnShipping', $adyenExpressConfig['googlepay']);
        $this->assertArrayHasKey('paypal', $adyenExpressConfig);
        $this->assertArrayHasKey('isEnabledOnShipping', $adyenExpressConfig['paypal']);
        $this->assertArrayHasKey('quote', $adyenExpressConfig);
        $this->assertArrayHasKey('amount', $adyenExpressConfig['quote']);
        $this->assertArrayHasKey('value', $adyenExpressConfig['quote']['amount']);
        $this->assertArrayHasKey('currency', $adyenExpressConfig['quote']['amount']);
        $this->assertArrayHasKey('countryCode', $adyenExpressConfig);
        $this->assertArrayHasKey('storeCode', $adyenExpressConfig);
        $this->assertArrayHasKey('actionSuccess', $adyenExpressConfig);

        $this->assertTrue($adyenExpressConfig['applepay']['isEnabledOnShipping']);
        $this->assertTrue($adyenExpressConfig['paypal']['isEnabledOnShipping']);
        $this->assertTrue($adyenExpressConfig['googlepay']['isEnabledOnShipping']);
        $this->assertEquals($isVirtual, $adyenExpressConfig['quote']['isVirtual']);
        $this->assertEquals($amount, $adyenExpressConfig['quote']['amount']['value']);
        $this->assertEquals($currency, $adyenExpressConfig['quote']['amount']['currency']);
        $this->assertEquals($storeCode, $adyenExpressConfig['storeCode']);
        $this->assertEquals($expectedUrl, $adyenExpressConfig['actionSuccess']);
    }
}
