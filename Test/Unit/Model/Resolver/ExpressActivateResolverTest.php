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
use Adyen\ExpressCheckout\Model\ExpressActivate;
use Adyen\ExpressCheckout\Model\Resolver\ExpressActivateResolver;
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
use PHPUnit\Framework\MockObject\MockObject;

class ExpressActivateResolverTest extends AbstractAdyenTestCase
{
    // Mocks for class builder
    protected ?ExpressActivateResolver $expressActivateResolver;
    protected MockObject&ExpressActivate $expressActivateMock;
    protected MockObject&ValueFactory $valueFactoryMock;
    protected MockObject&QuoteIdMaskFactory $quoteIdMaskFactoryMock;
    protected MockObject&AdyenLogger $loggerMock;

    // Mocks for `resolve()` method
    protected MockObject&Field $fieldMock;
    protected MockObject&Context $contextMock;
    protected MockObject&ResolveInfo $infoMock;

    public function setUp(): void
    {
        $this->expressActivateMock = $this->createMock(ExpressActivate::class);

        $this->valueFactoryMock = $this->createMock(ValueFactory::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->loggerMock = $this->createMock(AdyenLogger::class);

        $this->expressActivateResolver = new ExpressActivateResolver(
            $this->expressActivateMock,
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
        $this->expressActivateResolver = null;
    }

    /**
     * Assets expect exception if the required parameter `adyenMaskedQuoteId` is an empty string.
     *
     * @return void
     * @throws GraphQlInputException|Exception
     */
    public function testResolverShouldThrowExceptionWithEmptyArgument()
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'adyenMaskedQuoteId' => ""
        ];

        $this->expressActivateResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    public static function successfulResolverDataProvider(): array
    {
        return [
            [
                'args' => [
                    'adyenMaskedQuoteId' => 'mock_adyen_masked_quote_id',
                    'adyenCartId' => 'mock_adyen_cart_id'
                ]
            ],
            [
                'args' => [
                    'adyenMaskedQuoteId' => 'mock_adyen_masked_quote_id',
                    'adyenCartId' => null
                ]
            ],
            [
                'args' => [
                    'adyenMaskedQuoteId' => 'mock_adyen_masked_quote_id'
                ]
            ]
        ];
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

        $result = $this->expressActivateResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->assertInstanceOf(Value::class, $result);
    }

    /**
     * Asserts exception if the quote entity not found
     *
     * @return void
     * @throws GraphQlInputException
     * @throws LocalizedException
     */
    public function testMissingQuoteShouldThrowException()
    {
        $this->expectException(LocalizedException::class);

        $args = [
            'adyenMaskedQuoteId' => 'mock_product_cart_params'
        ];

        $this->valueFactoryMock->method('create')
            ->willThrowException(new ExpressInitException(__('Localized test exception!')));

        $this->expressActivateResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->loggerMock->expects($this->once())->method('error');
    }
}