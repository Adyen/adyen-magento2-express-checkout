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
use Adyen\ExpressCheckout\Model\ExpressInit;
use Adyen\ExpressCheckout\Model\ProductCartParams;
use Adyen\ExpressCheckout\Model\Resolver\ExpressInitResolver;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use PHPUnit\Framework\MockObject\MockObject;

class ExpressInitResolverTest extends AbstractAdyenResolverTestCase
{
    const SUCCESSFUL_CART_PARAMS = "{\"product\":1562,\"qty\":\"5\",\"super_attribute\":{\"93\":56,\"144\":166}}";

    protected MockObject&ExpressInit $expressInitMock;
    protected MockObject&ProductCartParamsInterfaceFactory $cartParamsInterfaceFactoryMock;

    /**
     * Build the class under test and the dependencies
     *
     * @return void
     */
    public function setUp(): void
    {
        // Build generic mocks for Adyen resolver tests
        parent::setUp();

        // Build class specific mocks
        $this->expressInitMock = $this->createMock(ExpressInit::class);
        $this->cartParamsInterfaceFactoryMock = $this->createGeneratedMock(
            ProductCartParamsInterfaceFactory::class,
            ['create']
        );

        $this->resolver = new ExpressInitResolver(
            $this->expressInitMock,
            $this->cartParamsInterfaceFactoryMock,
            $this->valueFactoryMock,
            $this->quoteIdMaskFactoryMock,
            $this->loggerMock
        );
    }

    /**
     * See the data provider for test case explanations.
     *
     * @dataProvider invalidProductCartParamsDataProvider
     *
     * @return void
     * @throws GraphQlInputException|LocalizedException|Exception
     */
    public function testProductCartParamsShouldThrowException($productCartParams)
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'productCartParams' => $productCartParams
        ];

        $this->resolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );
    }

    /**
     * This test case extends the abstract testSuccessfulResolving() test case
     *
     * @dataProvider successfulResolverDataProvider
     *
     * @param $args
     * @return void
     * @throws Exception
     */
    public function testSuccessfulResolving($args)
    {
        $productCartParamsMock = $this->createMock(ProductCartParams::class);
        $this->cartParamsInterfaceFactoryMock->method('create')->willReturn($productCartParamsMock);

        parent::testSuccessfulResolving($args);
    }

    /**
     * This test case extends the abstract testMissingQuoteShouldThrowException() test case
     *
     * @dataProvider missingQuoteAssertionDataProvider
     *
     * @param $args
     * @return void
     * @throws Exception
     */
    public function testMissingQuoteShouldThrowException($args)
    {
        $productCartParamsMock = $this->createMock(ProductCartParams::class);
        $this->cartParamsInterfaceFactoryMock->method('create')->willReturn($productCartParamsMock);

        parent::testSuccessfulResolving($args);
    }

    /**
     * Data provider for case testProductCartParamsShouldThrowException
     *
     * @return array[]
     */
    protected static function invalidProductCartParamsDataProvider(): array
    {
        return [
            // Simulate empty string for input field `productCartParams` and expect `GraphQlInputException`
            ['productCartParams' => ''],
            // Simulate invalid JSON object for input field `productCartParams` and expect `GraphQlInputException`
            ['productCartParams' => '{\"product\":1562,\"qty\":\"5\",\"super_att.._invalidJSON']
        ];
    }

    /**
     * Data provider for abstract test case testSuccessfulResolving()
     *
     * @return array
     */
    public static function successfulResolverDataProvider(): array
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
     * Data provider for abstract test case testResolverShouldThrowExceptionWithEmptyArgument()
     *
     * @return array[]
     */
    protected static function emptyArgumentAssertionDataProvider(): array
    {
        return [
            [
                'args' => []
            ],
            [
                'args' => [
                    'productCartParams' => ''
                ]
            ]
        ];
    }

    /**
     * Data provider for abstract test case testMissingQuoteShouldThrowException()
     *
     * @return array
     */
    protected static function missingQuoteAssertionDataProvider(): array
    {
        return [
            [
                'args' => [
                    'productCartParams' => self::SUCCESSFUL_CART_PARAMS,
                    'adyenCartId' => 'Mock_adyenCartId'
                ]
            ]
        ];
    }
}