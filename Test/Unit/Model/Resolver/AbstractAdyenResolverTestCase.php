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

use Adyen\ExpressCheckout\Exception\ExpressInitException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;

abstract class AbstractAdyenResolverTestCase extends AbstractAdyenTestCase
{
    abstract protected static function emptyArgumentAssertionDataProvider(): array;
    abstract protected static function successfulResolverDataProvider(): array;

    protected ?ResolverInterface $resolver;
    protected MockObject&Field $fieldMock;
    protected MockObject&Context $contextMock;
    protected MockObject&ResolveInfo $infoMock;
    protected MockObject&ValueFactory $valueFactoryMock;
    protected MockObject&QuoteIdMaskFactory $quoteIdMaskFactoryMock;
    protected MockObject&AdyenLogger $loggerMock;

    public function setUp(): void
    {
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->valueFactoryMock = $this->createMock(ValueFactory::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->loggerMock = $this->createMock(AdyenLogger::class);
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
     * Assets successful generation of the Value class after resolving
     * the mutation with correct set of inputs.
     *
     * @dataProvider successfulResolverDataProvider
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulResolving($args)
    {
        $returnValueMock = $this->createMock(Value::class);
        $this->valueFactoryMock->method('create')->willReturn($returnValueMock);

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(1);
        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMaskMock);

        $result = $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->assertInstanceOf(Value::class, $result);
    }

    /**
     * @dataProvider emptyArgumentAssertionDataProvider
     *
     * Assets expect exception if the required parameter is an empty string.
     * `emptyArgumentAssertionDataProvider()` data provider should be implemented in the test class.
     *
     * Method argument `parameter` can be string or array.
     * Provide an array to assert either one of those values should be set.
     * Provide a string to assert single required argument.
     *
     * @return void
     * @throws GraphQlInputException|Exception
     */
    public function testResolverShouldThrowExceptionWithEmptyArgument(string|array $parameter)
    {
        $this->expectException(GraphQlInputException::class);

        $args = [];

        if (is_array($parameter)) {
            foreach ($parameter as $value) {
                $args[$value] = '';
            }
        } else {
            $args = [
                "$parameter" => ''
            ];
        }

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * Asserts exception if the quote entity not found
     *
     * @return void
     * @throws Exception
     */
    public function testMissingQuoteShouldThrowException()
    {
        $this->expectException(LocalizedException::class);

        $args = [
            'adyenMaskedQuoteId' => 'mock_product_cart_params'
        ];

        $this->valueFactoryMock->method('create')
            ->willThrowException(new ExpressInitException(__('Localized test exception!')));

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->loggerMock->expects($this->once())->method('error');
    }
}