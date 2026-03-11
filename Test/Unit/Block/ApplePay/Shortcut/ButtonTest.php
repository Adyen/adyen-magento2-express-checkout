<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Block\ApplePay\Shortcut;

use Adyen\ExpressCheckout\Block\ApplePay\Shortcut\Button;
use Adyen\ExpressCheckout\Model\ConfigurationInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Button::class)]
class ButtonTest extends AbstractAdyenTestCase
{
    private Button $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $checkoutSessionMock = $this->createMock(Session::class);
        $urlMock = $this->createMock(UrlInterface::class);
        $storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $adyenHelperMock = $this->createMock(AdyenHelper::class);
        $localeHelperMock = $this->createMock(Locale::class);
        $configHelperMock = $this->createMock(Config::class);
        $expressConfigMock = $this->createMock(ConfigurationInterface::class);

        $storeMock = $this->getMockBuilder(StoreInterface::class)
            ->addMethods(['getBaseCurrencyCode'])
            ->getMockForAbstractClass();
        $storeMock->method('getId')->willReturn(1);
        $storeMock->method('getCode')->willReturn('default');
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $currencyMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getBaseCurrencyCode'])
            ->getMock();
        $currencyMock->method('getBaseCurrencyCode')->willReturn('EUR');

        $quoteMock = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getCurrency', 'getBaseGrandTotal'])
            ->getMock();
        $quoteMock->method('getCurrency')->willReturn($currencyMock);
        $checkoutSessionMock->method('getQuote')->willReturn($quoteMock);

        $urlMock->method('getUrl')->willReturn('https://store.test/success/');
        $scopeConfigMock->method('getValue')->willReturn('NL');
        $adyenHelperMock->method('getAdyenMerchantAccount')->willReturn('TestMerchant');
        $adyenHelperMock->method('decimalNumbers')->willReturn(2);
        $localeHelperMock->method('getStoreLocale')->willReturn('en_US');
        $configHelperMock->method('isDemoMode')->willReturn(true);
        $configHelperMock->method('getClientKey')->willReturn('test_key');
        $adyenHelperMock->method('getCheckoutEnvironment')->willReturn('test');
        $expressConfigMock->method('getApplePayButtonColor')->willReturn('black');

        $this->subject = new Button(
            $this->createMock(Context::class),
            $checkoutSessionMock,
            $this->createMock(MethodInterface::class),
            $urlMock,
            $this->createMock(CustomerSession::class),
            $storeManagerMock,
            $scopeConfigMock,
            $adyenHelperMock,
            $localeHelperMock,
            $configHelperMock,
            $this->createMock(DefaultConfigProvider::class),
            $expressConfigMock
        );
    }

    #[Test]
    public function build_configuration_uses_applepay_variant_key(): void
    {
        $result = $this->subject->buildConfiguration();

        $expectedKey = 'Adyen_ExpressCheckout/js/applepay/button';
        $this->assertArrayHasKey($expectedKey, $result);

        $config = $result[$expectedKey];
        $this->assertSame('https://store.test/success/', $config['actionSuccess']);
        $this->assertSame('default', $config['storeCode']);
        $this->assertSame('NL', $config['countryCode']);
        $this->assertSame('EUR', $config['currency']);
        $this->assertSame('TestMerchant', $config['merchantAccount']);
        $this->assertSame(2, $config['format']);
        $this->assertSame('en_US', $config['locale']);
        $this->assertSame('test_key', $config['originkey']);
        $this->assertSame('test', $config['checkoutenv']);
        $this->assertFalse($config['isProductView']);
        $this->assertSame('black', $config['buttonColor']);
    }
}
