<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Model;

use Adyen\ExpressCheckout\Model\GetAdyenPaymentMethodsByProduct;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Helper\ShopperConversionId;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenAmountCurrencyFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Catalog\Model\Product;

class GetAdyenPaymentMethodsByProductTest extends AbstractAdyenTestCase
{
    /** @var AdyenAmountCurrencyFactory|MockObject */
    private $adyenAmountCurrencyFactory;
    /** @var Data|MockObject */
    private $adyenHelper;
    /** @var Locale|MockObject */
    private $localeHelper;
    /** @var Config|MockObject */
    private $adyenConfigHelper;
    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfig;
    /** @var AdyenLogger|MockObject */
    private $adyenLogger;
    /** @var ShopperConversionId|MockObject */
    private $shopperConversionId;

    /** @var GetAdyenPaymentMethodsByProduct */
    private $sut;

    /** Common mocks */
    /** @var ProductInterface|MockObject */
    private $product;
    /** @var Quote|MockObject */
    private $quote; // <-- Quote mock
    /** @var StoreInterface|MockObject */
    private $store;

    protected function setUp(): void
    {
        $this->adyenAmountCurrencyFactory = $this->createMock(AdyenAmountCurrencyFactory::class);
        $this->adyenHelper                 = $this->createMock(Data::class);
        $this->localeHelper                = $this->createMock(Locale::class);
        $this->adyenConfigHelper           = $this->createMock(Config::class);
        $this->scopeConfig                 = $this->createMock(ScopeConfigInterface::class);
        $this->adyenLogger                 = $this->createMock(AdyenLogger::class);
        $this->shopperConversionId         = $this->createMock(ShopperConversionId::class);
        $this->product = $this->createMock(Product::class);

        $this->quote   = $this->createMock(Quote::class);
        $this->store   = $this->createMock(StoreInterface::class);

        $this->sut = new GetAdyenPaymentMethodsByProduct(
            $this->adyenAmountCurrencyFactory,
            $this->adyenHelper,
            $this->localeHelper,
            $this->adyenConfigHelper,
            $this->scopeConfig,
            $this->adyenLogger,
            $this->shopperConversionId
        );
    }

    public function testExecuteReturnsEmptyArrayWhenNoStore(): void
    {
        $this->quote->method('getStore')->willReturn(null);

        $result = $this->sut->execute($this->product, $this->quote);

        $this->assertSame([], $result);
    }

    public function testExecuteReturnsEmptyArrayWhenNoMerchantAccount(): void
    {
        $this->quote->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->adyenConfigHelper->method('getAdyenAbstractConfigData')
            ->with('merchant_account', 1)
            ->willReturn(null);

        $result = $this->sut->execute($this->product, $this->quote);

        $this->assertSame([], $result);
    }
}
