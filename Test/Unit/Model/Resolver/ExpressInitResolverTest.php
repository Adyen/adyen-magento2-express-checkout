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

use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterfaceFactory;
use Adyen\ExpressCheckout\Exception\ExpressInitException;
use Adyen\ExpressCheckout\Model\ExpressInit;
use Adyen\ExpressCheckout\Model\ProductCartParams;
use Adyen\ExpressCheckout\Model\Resolver\ExpressInitResolver;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Laminas\Soap\Client\Local;
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

class ExpressInitResolverTest extends AbstractAdyenTestCase
{
    // Mocks for class builder
    protected ?ExpressInitResolver $expressInitResolver;
    protected MockObject&ExpressInit $expressInitMock;
    protected MockObject&ProductCartParamsInterfaceFactory $cartParamsInterfaceFactoryMock;
    protected MockObject&ValueFactory $valueFactoryMock;
    protected MockObject&QuoteIdMaskFactory $quoteIdMaskFactoryMock;
    protected MockObject&AdyenLogger $loggerMock;

    // Mocks for `resolve()` method
    protected MockObject&Field $fieldMock;
    protected MockObject&Context $contextMock;
    protected MockObject&ResolveInfo $infoMock;

    const SUCCESSFUL_CART_PARAMS = "{\"product\":1562,\"qty\":\"5\",\"super_attribute\":{\"93\":56,\"144\":166}}";

    public function setUp(): void
    {
        $this->expressInitMock = $this->createMock(ExpressInit::class);
        $this->cartParamsInterfaceFactoryMock = $this->createGeneratedMock(
            ProductCartParamsInterfaceFactory::class,
            ['create']
        );
        $this->valueFactoryMock = $this->createMock(ValueFactory::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->loggerMock = $this->createMock(AdyenLogger::class);

        $this->expressInitResolver = new ExpressInitResolver(
            $this->expressInitMock,
            $this->cartParamsInterfaceFactoryMock,
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
        $this->expressInitResolver = null;
    }

    /**
     * Data provider for case testProductCartParamsShouldThrowException
     *
     * @return array[]
     */
    public static function invalidProductCartParamsDataProvider(): array
    {
        return [
            // Simulate empty string for input field `productCartParams` and expect `GraphQlInputException`
            ['productCartParams' => ''],
            // Simulate invalid JSON object for input field `productCartParams` and expect `GraphQlInputException`
            ['productCartParams' => '{\"product\":1562,\"qty\":\"5\",\"super_att.._invalidJSON']
        ];
    }

    /**
     * See the data provider for test case explanations.
     *
     * @dataProvider invalidProductCartParamsDataProvider
     *
     * @return void
     * @throws GraphQlInputException|LocalizedException
     */
    public function testProductCartParamsShouldThrowException($productCartParams)
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'productCartParams' => $productCartParams
        ];

        $this->expressInitResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * Data provider for case testSuccessfulResolving
     *
     * @return array[]
     */
    public static function validInputArgumentsForSuccessTest(): array
    {
        return [
            [
                'args' => [
                    'productCartParams' => self::SUCCESSFUL_CART_PARAMS,
                    'adyenCartId' => 'Mock_adyenCartId',
                    'adyenMaskedQuoteId' => 'Mock_adyenMaskedQuoteId'
                ]
            ],
            [
                'args' => [
                    'productCartParams' => self::SUCCESSFUL_CART_PARAMS,
                    'adyenCartId' => null,
                    'adyenMaskedQuoteId' => null
                ]
            ],
            [
                'args' => [
                    'productCartParams' => self::SUCCESSFUL_CART_PARAMS,
                    'adyenCartId' => "",
                    'adyenMaskedQuoteId' => ""
                ]
            ],
            [
                'args' => [
                    'productCartParams' => self::SUCCESSFUL_CART_PARAMS
                ]
            ]
        ];
    }

    /**
     * Assets successful generation of the Value class after resolving
     * the mutation with different set of inputs.
     *
     * @dataProvider validInputArgumentsForSuccessTest
     *
     * @return void
     * @throws GraphQlInputException|LocalizedException
     */
    public function testSuccessfulResolving($args)
    {
        $productCartParamsMock = $this->createMock(ProductCartParams::class);
        $this->cartParamsInterfaceFactoryMock->method('create')->willReturn($productCartParamsMock);

        $returnValueMock = $this->createMock(Value::class);
        $this->valueFactoryMock->method('create')->willReturn($returnValueMock);

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        $result = $this->expressInitResolver->resolve(
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
            'productCartParams' => self::SUCCESSFUL_CART_PARAMS
        ];

        $productCartParamsMock = $this->createMock(ProductCartParams::class);
        $this->cartParamsInterfaceFactoryMock->method('create')->willReturn($productCartParamsMock);

        $this->valueFactoryMock->method('create')
            ->willThrowException(new ExpressInitException(__('Localized test exception!')));

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, [
            'load',
            'getQuoteId'
        ]);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->quoteIdMaskFactoryMock->method('create')
            ->willReturn($quoteIdMaskMock);

        $this->expressInitResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->loggerMock->expects($this->once())->method('error');
    }
}