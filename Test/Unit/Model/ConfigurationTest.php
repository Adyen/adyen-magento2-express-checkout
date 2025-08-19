<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\Configuration;
use Adyen\ExpressCheckout\Model\Config\Source\ApplePay\ButtonColor;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(Configuration::class)]
class ConfigurationTest extends AbstractAdyenTestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    private Configuration $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->subject = new Configuration($this->scopeConfig);
    }

    #[Test]
    public function get_show_payment_method_on_returns_empty_array_when_config_missing(): void
    {
        $variant = 'paypal';
        $path = sprintf(
            '%s/%s_%s/%s',
            Configuration::CONFIG_PATH_PAYMENT,
            Configuration::CONFIG_PATH_ADYEN_PREFIX,
            $variant,
            Configuration::CONFIG_PATH_SHOW_EXPRESS_ON
        );

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with($path, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame([], $this->subject->getShowPaymentMethodOn($variant));
    }

    #[Test]
    public function get_show_payment_method_on_returns_exploded_values_and_respects_scope(): void
    {
        $variant = 'applepay';
        $scopeType = 'websites';
        $scopeCode = 'nl';
        $path = sprintf(
            '%s/%s_%s/%s',
            Configuration::CONFIG_PATH_PAYMENT,
            Configuration::CONFIG_PATH_ADYEN_PREFIX,
            $variant,
            Configuration::CONFIG_PATH_SHOW_EXPRESS_ON
        );

        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with($path, $scopeType, $scopeCode)
            ->willReturn('pdp,cart,checkout');

        $this->assertSame(['pdp','cart','checkout'], $this->subject->getShowPaymentMethodOn(
            $variant,
            $scopeType,
            $scopeCode
        ));
    }

    #[Test]
    public function get_apple_pay_button_color_returns_configured_value(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Configuration::APPLE_PAY_BUTTON_COLOR_CONFIG_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(ButtonColor::WHITE);

        $this->assertSame(ButtonColor::WHITE, $this->subject->getApplePayButtonColor());
    }

    #[Test]
    public function get_apple_pay_button_color_defaults_to_black_when_empty(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Configuration::APPLE_PAY_BUTTON_COLOR_CONFIG_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('');

        $this->assertSame(ButtonColor::BLACK, $this->subject->getApplePayButtonColor());
    }

    #[Test]
    public function get_show_apple_pay_on_returns_exploded_values(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Configuration::SHOW_APPLE_PAY_ON_CONFIG_PATH, ScopeInterface::SCOPE_STORE, 3)
            ->willReturn('minicart,product');

        $this->assertSame(['minicart','product'], $this->subject->getShowApplePayOn(
            ScopeInterface::SCOPE_STORE,
            3
        ));
    }

    #[Test]
    public function get_show_apple_pay_on_returns_empty_array_when_missing(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Configuration::SHOW_APPLE_PAY_ON_CONFIG_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertSame([], $this->subject->getShowApplePayOn());
    }

    #[Test]
    public function get_show_google_pay_on_returns_exploded_values(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Configuration::SHOW_GOOGLE_PAY_ON_CONFIG_PATH, 'websites', 'be')
            ->willReturn('cart,checkout');

        $this->assertSame(['cart','checkout'], $this->subject->getShowGooglePayOn('websites', 'be'));
    }

    #[Test]
    public function get_show_google_pay_on_returns_empty_array_when_missing(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(Configuration::SHOW_GOOGLE_PAY_ON_CONFIG_PATH, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('');

        $this->assertSame([], $this->subject->getShowGooglePayOn());
    }
}
