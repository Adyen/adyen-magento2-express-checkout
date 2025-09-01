<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Test\Unit\Helper;

use Adyen\ExpressCheckout\Helper\LineItemsDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Adyen\Payment\Model\AdyenAmountCurrency;

class LineItemsDataBuilderTest extends AbstractAdyenTestCase
{
    /** @var LineItemsDataBuilder&\PHPUnit\Framework\MockObject\MockObject */
    private $builder;

    /** @var ChargedCurrency&\PHPUnit\Framework\MockObject\MockObject */
    private $chargedCurrency;

    protected function setUp(): void
    {
        $this->builder = $this->getMockBuilder(LineItemsDataBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['formatLineItem'])
            ->getMock();

        $this->chargedCurrency = $this->createMock(ChargedCurrency::class);
        $refClass = new \ReflectionClass($this->builder);
        $parent = $refClass->getParentClass(); // OpenInvoice
        $prop = $parent->getProperty('chargedCurrency');
        $prop->setAccessible(true);
        $prop->setValue($this->builder, $this->chargedCurrency);
    }

    public function testReturnsEmptyArrayWhenNoVisibleItems(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([]);

        $this->chargedCurrency->expects($this->never())->method('getQuoteItemAmountCurrency');
        $this->builder->expects($this->never())->method('formatLineItem');

        $result = $this->builder->getOpenInvoiceDataForQuote($quote);

        $this->assertSame(['lineItems' => []], $result);
    }

    public function testBuildsSingleLineItem(): void
    {
        $item1  = $this->createMock(Item::class);
        $quote  = $this->createMock(Quote::class);
        $amount = $this->createMock(AdyenAmountCurrency::class);

        $quote->method('getAllVisibleItems')->willReturn([$item1]);

        $this->chargedCurrency->expects($this->once())
            ->method('getQuoteItemAmountCurrency')
            ->with($item1)
            ->willReturn($amount);

        $this->builder->expects($this->once())
            ->method('formatLineItem')
            ->with($amount, $item1)
            ->willReturn(['id' => 'SKU-1', 'amountIncludingTax' => 1000]);

        $result = $this->builder->getOpenInvoiceDataForQuote($quote);

        $this->assertEquals(
            ['lineItems' => [['id' => 'SKU-1', 'amountIncludingTax' => 1000]]],
            $result
        );
    }

    public function testBuildsMultipleLineItemsPreservingOrder(): void
    {
        $item1   = $this->createMock(Item::class);
        $item2   = $this->createMock(Item::class);
        $quote   = $this->createMock(Quote::class);
        $amount1 = $this->createMock(AdyenAmountCurrency::class);
        $amount2 = $this->createMock(AdyenAmountCurrency::class);

        $quote->method('getAllVisibleItems')->willReturn([$item1, $item2]);

        $this->chargedCurrency->method('getQuoteItemAmountCurrency')
            ->willReturnMap([
                [$item1, $amount1],
                [$item2, $amount2],
            ]);

        $result = $this->builder->getOpenInvoiceDataForQuote($quote);

        // top-level array with "lineItems"
        $this->assertIsArray($result);
        $this->assertArrayHasKey('lineItems', $result);
        $this->assertIsArray($result['lineItems']);

        // exactly 2 items, in list order (0,1)
        $this->assertCount(2, $result['lineItems']);
        $this->assertSame([0, 1], array_keys($result['lineItems']));

        // each item is an array (don’t care about the keys/values)
        $this->assertContainsOnly('array', $result['lineItems']);
    }
}
