<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\ExpressCheckout\Test\Unit\Model\Resolver\StoreConfig;

use Adyen\ExpressCheckout\Model\Configuration;
use Adyen\ExpressCheckout\Model\Resolver\StoreConfig\DisplayAreasResolver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class DisplayAreasResolverTest extends AbstractAdyenTestCase
{
    private DisplayAreasResolver $displayAreasResolver;
    private MockObject&Configuration $configurationMock;
    private MockObject&StoreManagerInterface $storeManagerMock;
    private MockObject&Field $fieldMock;
    private MockObject&ContextInterface $contextMock;
    private MockObject&ResolveInfo $infoMock;
    private MockObject&StoreInterface $storeMock;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationMock = $this->createMock(Configuration::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->storeMock = $this->createMock(StoreInterface::class);

        $this->displayAreasResolver = new DisplayAreasResolver(
            $this->configurationMock,
            $this->storeManagerMock
        );
    }

    /**
     * Setup field and configuration mocks
     *
     * @param string $fieldName
     * @param array $configuredAreas
     * @param string $expectedVariant
     * @param int $storeId
     * @return void
     */
    private function setupResolverMocks(
        string $fieldName,
        array $configuredAreas,
        string $expectedVariant,
        int $storeId = 1
    ): void {
        $this->fieldMock->expects($this->once())
            ->method('getName')
            ->willReturn($fieldName);

        $this->storeMock->expects($this->once())
            ->method('getId')
            ->willReturn($storeId);

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($this->storeMock);

        $this->configurationMock->expects($this->once())
            ->method('getShowPaymentMethodOn')
            ->with($expectedVariant, ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn($configuredAreas);
    }

    /**
     * Test resolve returns all mapped display areas
     *
     * @return void
     * @throws \Exception
     */
    public function testResolveReturnsAllMappedDisplayAreas(): void
    {
        $this->setupResolverMocks(
            'adyen_express_paypal_express_display_areas',
            [1, 2, 3],
            'paypal_express'
        );

        $result = $this->displayAreasResolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock);

        $this->assertEquals(['PRODUCT_PAGE', 'CART_PAGE', 'MINI_CART'], $result);
    }

    /**
     * Test resolve returns partial mapped display areas
     *
     * @return void
     * @throws \Exception
     */
    public function testResolveReturnsPartialMappedDisplayAreas(): void
    {
        $this->setupResolverMocks(
            'adyen_express_applepay_display_areas',
            [2, 3],
            'applepay'
        );

        $result = $this->displayAreasResolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock);

        $this->assertEquals(['CART_PAGE', 'MINI_CART'], $result);
    }

    /**
     * Test resolve returns empty array when no areas are configured
     *
     * @return void
     * @throws \Exception
     */
    public function testResolveReturnsEmptyArrayWhenNoAreasConfigured(): void
    {
        $this->setupResolverMocks(
            'adyen_express_googlepay_display_areas',
            [],
            'googlepay'
        );

        $result = $this->displayAreasResolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock);

        $this->assertEquals([], $result);
    }

    /**
     * Test resolve filters out invalid area values
     *
     * @return void
     * @throws \Exception
     */
    public function testResolveFiltersOutInvalidAreaValues(): void
    {
        $this->setupResolverMocks(
            'adyen_express_paypal_express_display_areas',
            [1, 99, 2],
            'paypal_express'
        );

        $result = $this->displayAreasResolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock);

        $this->assertEquals(['PRODUCT_PAGE', 'CART_PAGE'], $result);
    }

    /**
     * Test resolve throws exception for invalid field name
     *
     * @return void
     * @throws \Exception
     */
    public function testResolveThrowsExceptionForInvalidFieldName(): void
    {
        $this->fieldMock->expects($this->once())
            ->method('getName')
            ->willReturn('invalid_field_name');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid variant.');

        $this->displayAreasResolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock);
    }

    /**
     * Test resolve correctly extracts variant from complex field names
     *
     * @dataProvider fieldNameProvider
     * @param string $fieldName
     * @param string $expectedVariant
     * @return void
     * @throws \Exception
     */
    public function testResolveExtractsVariantCorrectly(string $fieldName, string $expectedVariant): void
    {
        $this->setupResolverMocks($fieldName, [], $expectedVariant);

        $this->displayAreasResolver->resolve($this->fieldMock, $this->contextMock, $this->infoMock);
    }

    /**
     * Data provider for field names and expected variants
     *
     * @return array
     */
    public function fieldNameProvider(): array
    {
        return [
            'applepay' => ['adyen_express_applepay_display_areas', 'applepay'],
            'googlepay' => ['adyen_express_googlepay_display_areas', 'googlepay'],
            'paypal_express' => ['adyen_express_paypal_express_display_areas', 'paypal_express'],
            'complex_variant' => ['adyen_express_some_complex_method_name_display_areas', 'some_complex_method_name']
        ];
    }
}
