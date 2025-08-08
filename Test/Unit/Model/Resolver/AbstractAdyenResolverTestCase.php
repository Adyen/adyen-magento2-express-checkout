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
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;

abstract class AbstractAdyenResolverTestCase extends AbstractAdyenTestCase
{
    protected ?ResolverInterface $resolver;
    protected MockObject&Field $fieldMock;
    protected MockObject&Context $contextMock;
    protected MockObject&ResolveInfo $infoMock;
    protected MockObject&ValueFactory $valueFactoryMock;
    protected MockObject&MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteIdMock;
    protected MockObject&AdyenLogger $loggerMock;

    /**
     * Data provider for abstract test case testResolverShouldThrowExceptionWithEmptyArgument()
     *
     * @return array[]
     */
    abstract public static function emptyArgumentAssertionDataProvider(): array;

    /**
     * Data provider for abstract test case testSuccessfulResolving()
     *
     * @return array
     */
    abstract public static function successfulResolverDataProvider(): array;

    /**
     * Data provider for abstract test case testMissingQuoteShouldThrowException()
     *
     * @return array
     */
    abstract public static function missingQuoteAssertionDataProvider(): array;

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function setUp(): void
    {
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->valueFactoryMock = $this->createMock(ValueFactory::class);
        $this->maskedQuoteIdToQuoteIdMock = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
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
     * @throws Exception|\PHPUnit\Framework\MockObject\Exception
     */
    public function testSuccessfulResolving($args)
    {
        $returnValueMock = $this->createMock(Value::class);
        $this->valueFactoryMock->method('create')->willReturn($returnValueMock);

        $quoteIdMaskMock = $this->getMockBuilder(QuoteIdMask::class)
            ->addMethods(['getQuoteId'])
            ->onlyMethods(['load'])
            ->disableOriginalConstructor()
            ->getMock();

        $quoteIdMaskMock->method('load')->willReturnSelf();
        $quoteIdMaskMock->method('getQuoteId')->willReturn(1);

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
     * @return void
     * @throws GraphQlInputException|Exception
     */
    public function testResolverShouldThrowExceptionWithEmptyArgument(array $args)
    {
        $this->expectException(GraphQlInputException::class);

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
     * @dataProvider missingQuoteAssertionDataProvider
     *
     * @return void
     * @throws Exception
     */
    public function testMissingQuoteShouldThrowException($args)
    {
        $this->expectException(LocalizedException::class);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->callback(function ($message) {
            return str_contains($message, 'An error occurred') ;
        }));

        // Simulate quote resolution failure
        $this->maskedQuoteIdToQuoteIdMock->method('execute')
            ->willThrowException(new \Exception('Quote resolution failed'));

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }
}
