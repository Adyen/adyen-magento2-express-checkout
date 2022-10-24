<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface;

interface GuestExpressInitInterface
{
    /**
     * Initialise Guest Express Checkout Quote
     *
     * @param ProductCartParamsInterface $productCartParams
     * @param string|null $guestMaskedId
     * @param string|null $adyenMaskedQuoteId
     * @return \Adyen\ExpressCheckout\Api\Data\ExpressDataInterface|null
     */
    public function execute(
        \Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface $productCartParams,
        ?string $guestMaskedId = null,
        ?string $adyenMaskedQuoteId = null
    ): ?\Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
}
