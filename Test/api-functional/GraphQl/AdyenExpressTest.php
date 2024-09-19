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
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

class AdyenExpressTest extends GraphQlAbstract
{
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
        $query = <<<QUERY
mutation {
    expressInit(productCartParams: "{\"product\":1562,\"qty\":\"5\",\"super_attribute\":{\"93\":56,\"144\":166}}") {
        masked_quote_id
    }
}
QUERY;

        $response = $this->graphQlMutation($query);

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
        $query = <<<QUERY
mutation {
    expressInit(
        productCartParams: "{\"product\":1562,\"qty\":\"1\",\"super_attribute\":{\"93\":56,\"144\":166}}"
    ) {
        masked_quote_id
    }
}
QUERY;

        $initialResponse = $this->graphQlMutation($query);

        $this->assertArrayHasKey('expressInit', $initialResponse);
        $this->assertArrayHasKey('masked_quote_id', $initialResponse['expressInit']);

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
}
