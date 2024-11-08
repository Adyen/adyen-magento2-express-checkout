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
use PHPUnit\Framework\MockObject\MockObject;

class ExpressCancelResolverTest extends AbstractAdyenResolverTestCase
{
    protected MockObject&ExpressCancel $expressCancelMock;

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
        $this->expressCancelMock = $this->createMock(ExpressCancel::class);

        $this->resolver = new ExpressCancelResolver(
            $this->expressCancelMock,
            $this->valueFactoryMock,
            $this->quoteIdMaskFactoryMock,
            $this->loggerMock
        );
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
                    'adyenMaskedQuoteId' => 'mock_adyen_masked_quote_id'
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
                    'adyenMaskedQuoteId' => ''
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
                    'adyenMaskedQuoteId' => 'mock_adyen_masked_quote_id'
                ]
            ]
        ];
    }
}
