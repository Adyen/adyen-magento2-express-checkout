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

use Adyen\ExpressCheckout\Api\AdyenInitPaymentsInterface;
use Adyen\ExpressCheckout\Model\AdyenInitPayments;
use Adyen\ExpressCheckout\Model\Resolver\AdyenExpressInitPaymentsResolver;
use Adyen\Payment\Model\Resolver\DataProvider\GetAdyenPaymentStatus;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenExpressInitPaymentsResolverTest extends AbstractAdyenResolverTestCase
{
    protected MockObject&AdyenInitPaymentsInterface $adyenInitPaymentsMock;
    protected MockObject&GetAdyenPaymentStatus $adyenGetPaymentStatusMock;

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
        $this->adyenInitPaymentsMock = $this->createMock(AdyenInitPayments::class);
        $this->adyenGetPaymentStatusMock = $this->createMock(GetAdyenPaymentStatus::class);

        $this->resolver = new AdyenExpressInitPaymentsResolver(
            $this->adyenInitPaymentsMock,
            $this->valueFactoryMock,
            $this->quoteIdMaskFactoryMock,
            $this->loggerMock,
            $this->adyenGetPaymentStatusMock
        );
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
                'args' => [
                    'adyenCartId', 'adyenMaskedQuoteId'
                ]
            ],
            [
                'args' => [
                    'stateData' => 'mock_state_data'
                ]
            ],
            [
                'args' => [
                    'stateData' => 'mock_state_data',
                    'adyenCartId' => '',
                    'adyenMaskedQuoteId' => ''
                ]
            ],
            [
                'args' => [
                    'stateData' => 'mock_state_data',
                    'adyenMaskedQuoteId' => ''
                ]
            ]
        ];
    }

    /**
     * Data provider for abstract test case testSuccessfulResolving()
     *
     * @return array
     */
    protected static function successfulResolverDataProvider(): array
    {
        return [
            [
                'args' => [
                    'stateData' => 'json_encoded_state_data',
                    'adyenMaskedQuoteId' => 'adyenMaskedQuoteId',
                    'adyenCartId' => 'adyenCartId'
                ]
            ],
            [
                'args' => [
                    'stateData' => 'json_encoded_state_data',
                    'adyenMaskedQuoteId' => 'adyenMaskedQuoteId'
                ]
            ],
            [
                'args' => [
                    'stateData' => 'json_encoded_state_data',
                    'adyenCartId' => 'adyenCartId'
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
                    'stateData' => 'adyen_state_data_mock',
                    'adyenMaskedQuoteId' => 'mock_product_cart_params'
                ]
            ]
        ];
    }
}