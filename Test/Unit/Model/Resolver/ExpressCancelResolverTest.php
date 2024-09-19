<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\ExpressCheckout\Test\Unit\Model\Resolver;

use Adyen\ExpressCheckout\Model\ExpressCancel;
use Adyen\ExpressCheckout\Model\Resolver\ExpressCancelResolver;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use PHPUnit\Framework\MockObject\MockObject;

class ExpressCancelResolverTest extends AbstractAdyenTestCase
{
    // Mocks for class builder
    protected ?ExpressCancelResolver $expressCancelResolver;
    protected MockObject&ExpressCancel $expressCancelMock;
    protected MockObject&ValueFactory $valueFactoryMock;
    protected MockObject&QuoteIdMaskFactory $quoteIdMaskFactoryMock;
    protected MockObject&AdyenLogger $loggerMock;

    // Mocks for `resolve()` method
    protected MockObject&Field $fieldMock;
    protected MockObject&Context $contextMock;
    protected MockObject&ResolveInfo $infoMock;


    public function setUp(): void
    {
        $this->expressCancelMock = $this->createMock(ExpressCancel::class);
        $this->valueFactoryMock = $this->createMock(ValueFactory::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->loggerMock = $this->createMock(AdyenLogger::class);

        $this->expressCancelResolver = new ExpressCancelResolver(
            $this->expressCancelMock,
            $this->valueFactoryMock,
            $this->quoteIdMaskFactoryMock,
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
        $this->expressCancelResolver = null;
    }

    /**
     * Assets expect exception if the required parameter `adyenCartId` is an empty string.
     *
     * @return void
     * @throws GraphQlInputException|Exception
     */
    public function testResolverShouldThrowExceptionWithEmptyArgument()
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'adyenCartId' => ""
        ];

        $this->expressCancelResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * Assets expect exception if the masked quote id doesn't belong to any quote
     *
     * @return void
     * @throws Exception
     */
    public function testResolverShouldThrowExceptionNonExistingQuote()
    {
        $this->expectException(GraphQlNoSuchEntityException::class);

        $args = [
            'adyenCartId' => "Mock_adyen_cart_id"
        ];

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        $this->valueFactoryMock->method('create')->willThrowException(new NoSuchEntityException());

        $this->expressCancelResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * Assets expect exception if the quote id can not be unmasked
     *
     * @return void
     * @throws Exception
     */
    public function testResolverShouldThrowExceptionInvalidQuoteId()
    {
        $this->expectException(LocalizedException::class);

        $args = [
            'adyenCartId' => "Mock_adyen_cart_id"
        ];

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willThrowException(new Exception());

        $this->quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        $this->expressCancelResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * Assets successful generation of the Value class after resolving
     * the mutation with correct set of inputs.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulResolving()
    {
        $args['adyenCartId'] = "Mock_adyenCartId";

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        $returnValueMock = $this->createMock(Value::class);
        $this->valueFactoryMock->method('create')->willReturn($returnValueMock);

        $result = $this->expressCancelResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->assertInstanceOf(Value::class, $result);
    }
}
