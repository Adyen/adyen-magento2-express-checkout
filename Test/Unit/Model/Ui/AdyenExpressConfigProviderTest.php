<?php

declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Block\ApplePay\Shortcut\Button as ApplePayButton;
use Adyen\ExpressCheckout\Block\GooglePay\Shortcut\Button as GooglePayButton;
use Adyen\ExpressCheckout\Block\Paypal\Shortcut\Button as PayPalButton;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\ExpressCheckout\Model\Ui\AdyenExpressConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenExpressConfigProviderTest extends AbstractAdyenTestCase
{
    protected AdyenExpressConfigProvider $adyenExpressConfigProvider;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected ConfigurationInterface|MockObject $configHelperMock;

    public function setUp(): void
    {
        parent::setUp();
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->configHelperMock = $this->createMock(ConfigurationInterface::class);
        $this->adyenExpressConfigProvider = new AdyenExpressConfigProvider(
            $this->configHelperMock,
            $this->storeManagerMock
        );
    }

    public function testGetConfig(): void
    {
        $storeId = 1;
        $showApplepayOn = ['1', '2', '3'];
        $applepayButtonColor = 'black';
        $showGooglepayOn = ['1', '2', '3'];
        $showPaypalOn = ['1', '2', '3'];

        // Mock store
        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->expects($this->once())
            ->method('getId')
            ->willReturn($storeId);

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);

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
        $this->assertArrayHasKey('showApplepayOn', $adyenExpressConfig);
        $this->assertArrayHasKey('applepayButtonColor', $adyenExpressConfig);
        $this->assertArrayHasKey('showGooglepayOn', $adyenExpressConfig);
        $this->assertArrayHasKey('showPaypalOn', $adyenExpressConfig);

        $this->assertEquals($showApplepayOn, $adyenExpressConfig['showApplepayOn']);
        $this->assertEquals($applepayButtonColor, $adyenExpressConfig['applepayButtonColor']);
        $this->assertEquals($showGooglepayOn, $adyenExpressConfig['showGooglepayOn']);
        $this->assertEquals($showPaypalOn, $adyenExpressConfig['showPaypalOn']);
    }
}
