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

use Adyen\ExpressCheckout\Api\AdyenPaypalUpdateOrderInterface;
use Adyen\ExpressCheckout\Helper\PaypalUpdateOrder;
use Adyen\ExpressCheckout\Model\Resolver\AdyenExpressPaypalUpdateOrderResolver;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenExpressPaypalUpdateOrderResolverTest extends AbstractAdyenResolverTestCase
{
    protected MockObject|AdyenPaypalUpdateOrderInterface $adyenPaypalUpdateOrderMock;
    protected MockObject|PaypalUpdateOrder $paypalUpdateOrderHelperMock;

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
        $this->adyenPaypalUpdateOrderMock = $this->createMock(AdyenPaypalUpdateOrderInterface::class);
        $this->paypalUpdateOrderHelperMock = $this->createMock(PaypalUpdateOrder::class);

        $this->resolver = new AdyenExpressPaypalUpdateOrderResolver(
            $this->adyenPaypalUpdateOrderMock,
            $this->valueFactoryMock,
            $this->quoteIdMaskFactoryMock,
            $this->loggerMock,
            $this->paypalUpdateOrderHelperMock
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
                'args' => []
            ],
            [
                'args' => [
                    'paymentData' => 'mock_payment_data'
                ]
            ],
            [
                'args' => [
                    'paymentData' => 'mock_payment_data',
                    'adyenCartId' => '',
                    'adyenMaskedQuoteId' => ''
                ]
            ],
            [
                'args' => [
                    'paymentData' => 'mock_payment_data',
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
                    'paymentData' => 'mock_payment_data',
                    'adyenMaskedQuoteId' => 'adyenMaskedQuoteId',
                    'adyenCartId' => 'adyenCartId',
                    'deliveryMethods' => [
                        ['reference' => 1, 'description' => 'Flat rate', 'selected' => true],
                        ['reference' => 2, 'description' => 'Express delivery', 'selected' => false]
                    ]
                ]
            ],
            [
                'args' => [
                    'paymentData' => 'mock_payment_data',
                    'adyenMaskedQuoteId' => 'adyenMaskedQuoteId'
                ]
            ],
            [
                'args' => [
                    'paymentData' => 'mock_payment_data',
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
                    'paymentData' => 'mock_payment_data',
                    'adyenMaskedQuoteId' => 'mock_product_cart_params',
                    'adyenCartId' => 'mock_adyenCartId'
                ]
            ]
        ];
    }
}