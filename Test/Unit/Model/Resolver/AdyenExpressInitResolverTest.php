<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\ExpressCheckout\Test\Unit\Model\Resolver;

use Adyen\ExpressCheckout\Model\ExpressQuoteCreator;
use Adyen\ExpressCheckout\Model\Resolver\AdyenExpressInitResolver;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use Magento\Quote\Model\Cart\AddProductsToCart;
use Magento\Quote\Model\Cart\Data\AddProductsToCartOutput;
use Magento\Quote\Model\Cart\Data\CartItem;
use Magento\Quote\Model\Cart\Data\CartItemFactory;
use Magento\Quote\Model\Cart\Data\Error;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\CartItem\PrecursorInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenExpressInitResolverTest extends AbstractAdyenTestCase
{
    const MASKED_QUOTE_ID = 'mock_masked_quote_id';
    const CART_ITEM = ['sku' => 'MOCK-SKU', 'quantity' => 1];

    protected ?AdyenExpressInitResolver $resolver;
    protected MockObject&ExpressQuoteCreator $expressQuoteCreatorMock;
    protected MockObject&AddProductsToCart $addProductsToCartMock;
    protected MockObject&CartItemFactory $cartItemFactoryMock;
    protected MockObject&PrecursorInterface $cartItemPrecursorMock;
    protected MockObject&AdyenLogger $loggerMock;
    protected MockObject&Field $fieldMock;
    protected MockObject&Context $contextMock;
    protected MockObject&ResolveInfo $infoMock;

    /**
     * Build the class under test and the dependencies
     *
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function setUp(): void
    {
        $this->expressQuoteCreatorMock = $this->createMock(ExpressQuoteCreator::class);
        $this->addProductsToCartMock = $this->createMock(AddProductsToCart::class);
        $this->cartItemFactoryMock = $this->createMock(CartItemFactory::class);
        $this->cartItemPrecursorMock = $this->createMock(PrecursorInterface::class);
        $this->loggerMock = $this->createMock(AdyenLogger::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->resolver = new AdyenExpressInitResolver(
            $this->expressQuoteCreatorMock,
            $this->addProductsToCartMock,
            $this->cartItemFactoryMock,
            $this->cartItemPrecursorMock,
            $this->loggerMock
        );
    }

    /**
     * Reset the target class after each test
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->resolver = null;
    }

    /**
     * Asserts the express quote is created, the product is added
     * and the cart model is returned with the mapped user errors.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulResolving()
    {
        $cartItemMock = $this->createMock(CartItem::class);
        $quoteMock = $this->createMock(Quote::class);

        $this->expressQuoteCreatorMock->expects($this->once())
            ->method('execute')
            ->with($this->contextMock)
            ->willReturn(self::MASKED_QUOTE_ID);

        $this->cartItemPrecursorMock->expects($this->once())
            ->method('process')
            ->with([self::CART_ITEM], $this->contextMock)
            ->willReturn([self::CART_ITEM]);
        $this->cartItemPrecursorMock->method('getErrors')->willReturn([]);

        $this->cartItemFactoryMock->expects($this->once())
            ->method('create')
            ->with(self::CART_ITEM)
            ->willReturn($cartItemMock);

        $this->addProductsToCartMock->expects($this->once())
            ->method('execute')
            ->with(self::MASKED_QUOTE_ID, [$cartItemMock])
            ->willReturn(new AddProductsToCartOutput($quoteMock, [
                new Error('The requested qty is not available', 'INSUFFICIENT_STOCK', 0)
            ]));

        $result = $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            ['input' => ['cartItem' => self::CART_ITEM]]
        );

        $this->assertSame($quoteMock, $result['cart']['model']);
        $this->assertSame([
            [
                'code' => 'INSUFFICIENT_STOCK',
                'message' => 'The requested qty is not available',
                'path' => [0]
            ]
        ], $result['user_errors']);
    }

    /**
     * Asserts exception if the required `cartItem` input is missing
     *
     * @dataProvider emptyArgumentAssertionDataProvider
     *
     * @return void
     * @throws Exception
     */
    public function testResolverShouldThrowExceptionWithEmptyArgument(array $args)
    {
        $this->expectException(GraphQlInputException::class);
        $this->expressQuoteCreatorMock->expects($this->never())->method('execute');

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * Asserts the error is logged and a generic exception is thrown if the quote can not be initiated
     *
     * @return void
     * @throws Exception
     */
    public function testQuoteCreationFailureShouldThrowException()
    {
        $this->expectException(LocalizedException::class);

        $this->expressQuoteCreatorMock->method('execute')
            ->willThrowException(new Exception('Quote creation failed'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->callback(function ($message) {
                return str_contains($message, 'An error occurred') ;
            }));

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            ['input' => ['cartItem' => self::CART_ITEM]]
        );
    }

    /**
     * Data provider for testResolverShouldThrowExceptionWithEmptyArgument()
     *
     * @return array[]
     */
    public static function emptyArgumentAssertionDataProvider(): array
    {
        return [
            ['args' => []],
            ['args' => ['input' => []]],
            ['args' => ['input' => ['cartItem' => []]]]
        ];
    }
}
