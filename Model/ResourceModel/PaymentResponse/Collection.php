<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\ResourceModel\PaymentResponse;

use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection as AdyenPaymentResponseCollection;

class Collection extends AdyenPaymentResponseCollection
{
    /**
     * Fetch the payment response for the merchant reference supplied
     *
     * @param string $merchantReference
     * @return array|null
     */
    public function getPaymentResponseWithMerchantReference(string $merchantReference): ?array
    {
        return $this->addFieldToFilter('merchant_reference', $merchantReference)->getLastItem()->getData();
    }
}
