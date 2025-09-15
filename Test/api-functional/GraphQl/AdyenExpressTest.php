<?php declare(strict_types=1);
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\GraphQl;

use Exception;
use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class AdyenExpressTest extends GraphQlAbstract
{
    protected ?GetMaskedQuoteIdByReservedOrderId $getMaskedQuoteIdByReservedOrderId;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function initializeExpressQuoteOnPdp(): array
    {
        // Generate express quote for PDP
        $query = <<<QUERY
mutation {
    expressInit(
        productCartParams: "{\"product\":1562,\"qty\":\"1\",\"super_attribute\":{\"93\":56,\"144\":166}}"
    ) {
        masked_quote_id
    }
}
QUERY;

        return $this->graphQlMutation($query);
    }

    /**
     * Test case for expressInit mutation should throw an exception
     * with an empty `productCartParams` input field.
     *
     * @return void
     * @throws Exception
     */
    public function testEmptyCartParamsShouldThrowException(): void
    {
        $query = <<<QUERY
mutation {
    expressInit(productCartParams: "") {
        masked_quote_id
    }
}
QUERY;

        $this->expectException(ResponseContainsErrorsException::class);
        $this->graphQlMutation($query);
    }

    /**
     * Test case for expressInit mutation with valid productCartParams
     * should generate a valid quote and return `masked_quote_id`.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulQuoteInitiation(): void
    {
        $response = $this->initializeExpressQuoteOnPdp();

        $this->assertArrayHasKey('expressInit', $response);
        $this->assertArrayHasKey('masked_quote_id', $response['expressInit']);
    }

    /**
     * Test case for expressInit mutation with valid productCartParams
     * should update the quote and return the updated quote with the same masked_quote_id.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulQuoteUpdate(): void
    {
        // Initialize express quote to perform the test
        $initialResponse = $this->initializeExpressQuoteOnPdp();
        $maskedQuoteId = $initialResponse['expressInit']['masked_quote_id'];

        $query = <<<QUERY
mutation {
    expressInit(
        productCartParams: "{\"product\":1562,\"qty\":\"5\",\"super_attribute\":{\"93\":56,\"144\":166}}",
        adyenMaskedQuoteId: "$maskedQuoteId"
    ) {
        masked_quote_id
        totals {
            items_qty
        }
    }
}
QUERY;

        $updateCallResponse = $this->graphQlMutation($query);

        $this->assertArrayHasKey('expressInit', $updateCallResponse);
        $this->assertArrayHasKey('totals', $updateCallResponse['expressInit']);
        $this->assertArrayHasKey('items_qty', $updateCallResponse['expressInit']['totals']);
        $this->assertEquals('5', $updateCallResponse['expressInit']['totals']['items_qty']);
        $this->assertArrayHasKey('masked_quote_id', $updateCallResponse['expressInit']);
        $this->assertEquals($maskedQuoteId, $updateCallResponse['expressInit']['masked_quote_id']);
    }

    /**
     * Test case for expressActivate mutation with valid adyenMaskedQuoteId
     * should activate the quote and return TRUE.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulExpressQuoteActivation(): void
    {
        // Initialize express quote to perform the test
        $initiateQuote = $this->initializeExpressQuoteOnPdp();
        $maskedQuoteId = $initiateQuote['expressInit']['masked_quote_id'];

        $query = <<<QUERY
mutation {
    expressActivate(
        adyenMaskedQuoteId: "$maskedQuoteId"
    )
}
QUERY;

        $response = $this->graphQlMutation($query);

        $this->assertArrayHasKey('expressActivate', $response);
        $this->assertTrue($response['expressActivate']);
    }

    /**
     * Test case for expressCancel mutation with valid adyenCartId
     * should activate the quote and return TRUE.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulExpressQuoteCancellation(): void
    {
        // Initialize express quote to perform the test
        $initiateQuote = $this->initializeExpressQuoteOnPdp();
        $expressMaskedQuoteId = $initiateQuote['expressInit']['masked_quote_id'];

        $query = <<<QUERY
mutation {
    expressCancel(
        adyenMaskedQuoteId: "$expressMaskedQuoteId"
    )
}
QUERY;

        $response = $this->graphQlMutation($query);

        $this->assertArrayHasKey('expressCancel', $response);
        $this->assertTrue($response['expressCancel']);
    }

    /**
     * Test case for adyenExpressInitPayments mutation with valid cartId
     * should return `/payments` response with `action` and `resultCode`.
     *
     * @return void
     * @throws Exception
     */
    public function testSuccessfulPaymentsCallInitiation(): void
    {
        // Initialize express quote to perform the test
        $initiateQuoteResponse = $this->initializeExpressQuoteOnPdp();
        $expressMaskedQuoteId = $initiateQuoteResponse['expressInit']['masked_quote_id'];

        // Start the test case
        $query = <<<QUERY
mutation {
    adyenExpressInitPayments(
        stateData: "{\"paymentMethod\":{\"type\":\"paypal\",\"userAction\":\"pay\",\"subtype\":\"express\"},\"clientStateDataIndicator\":true}",
        adyenMaskedQuoteId: "$expressMaskedQuoteId"
    ) {
        isFinal
        resultCode
        action
    }
}
QUERY;

        $response = $this->graphQlMutation($query);

        $this->assertArrayHasKey('adyenExpressInitPayments', $response);
        $this->assertArrayHasKey('isFinal', $response['adyenExpressInitPayments']);
        $this->assertArrayHasKey('resultCode', $response['adyenExpressInitPayments']);
        $this->assertArrayHasKey('action', $response['adyenExpressInitPayments']);
        $this->assertEquals('Pending', $response['adyenExpressInitPayments']['resultCode']);
        $this->assertFalse($response['adyenExpressInitPayments']['isFinal']);
    }

    /**
     * Test case for adyenExpressPaypalUpdateOrder mutation with valid paymentData
     *  should return `status: success` with the updated `paymentData`.
     *
     * @magentoApiDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/guest/set_guest_email.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_new_billing_address.php
     * @magentoApiDataFixture Magento/GraphQl/Quote/_files/set_flatrate_shipping_method.php
     */
    public function testExpressPaypalUpdateOrder(): void
    {
        // Fetch the quote ID from the data fixture
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_quote');

        // Make /payments call to obtain initial `paymentData`
        $query = <<<QUERY
mutation {
    adyenExpressInitPayments(
        stateData: "{\"paymentMethod\":{\"type\":\"paypal\",\"userAction\":\"pay\",\"subtype\":\"express\"},\"clientStateDataIndicator\":true}",
        adyenMaskedQuoteId: "$maskedQuoteId"
    ) {
        isFinal
        resultCode
        action
    }
}
QUERY;
        $expressInitResponse = $this->graphQlMutation($query);
        $this->assertArrayHasKey('action', $expressInitResponse['adyenExpressInitPayments']);

        $action = json_decode($expressInitResponse['adyenExpressInitPayments']['action'], true);
        $this->assertArrayHasKey('paymentData', $action);

        // Start the test case and update the PayPal order with the new amount including shipping cost
        $query = <<<'MUTATION'
            mutation AdyenExpressPaypalUpdateOrder(
                $paymentData: String!,
                $deliveryMethods: [PaypalDeliveryMethodInput],
                $adyenCartId: String
            ) {
                adyenExpressPaypalUpdateOrder(
                    paymentData: $paymentData,
                    adyenCartId: $adyenCartId,
                    deliveryMethods: $deliveryMethods
                ) {
                    status
                    paymentData
                }
            }
        MUTATION;

        $variables = [
            'paymentData' => $action['paymentData'],
            'deliveryMethods' => [
                [
                    'reference' => '1',
                    'description' => 'Flat rate',
                    'type' => 'Shipping',
                    'amount' => [
                        'currency' => 'EUR',
                        'value' => 1000,
                    ],
                    'selected' => true
                ]
            ],
            'adyenCartId' => $maskedQuoteId,
        ];

        $adyenExpressPaypalUpdateOrderResponse = $this->graphQlMutation($query, $variables);

        $this->assertArrayHasKey(
            'status',
            $adyenExpressPaypalUpdateOrderResponse['adyenExpressPaypalUpdateOrder']
        );
        $this->assertEquals(
            'success',
            $adyenExpressPaypalUpdateOrderResponse['adyenExpressPaypalUpdateOrder']['status']
        );
    }
}
