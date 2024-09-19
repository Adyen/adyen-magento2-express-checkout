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

use Adyen\ExpressCheckout\Model\ExpressActivate;
use Adyen\ExpressCheckout\Model\Resolver\ExpressActivateResolver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use PHPUnit\Framework\MockObject\MockObject;

class ExpressActivateResolverTest extends AbstractAdyenTestCase
{
    // Mocks for class builder
    protected ?ExpressActivateResolver $expressActivateResolver;
    protected MockObject&ExpressActivate $expressActivateMock;
    protected MockObject&ValueFactory $valueFactoryMock;

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

        $this->expressActivateResolver = new ExpressActivateResolver(
            $this->expressActivateMock,
            $this->valueFactoryMock
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

    /**
     * Assets successful generation of the Value class after resolving
     * the mutation with correct set of inputs.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulResolving()
    {
        $args['adyenMaskedQuoteId'] = "Mock_adyenMaskedQuoteId";

        $returnValueMock = $this->createMock(Value::class);
        $this->valueFactoryMock->method('create')->willReturn($returnValueMock);

        $result = $this->expressActivateResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            [],
            $args
        );

        $this->assertInstanceOf(Value::class, $result);
    }
}