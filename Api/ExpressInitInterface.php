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

interface ExpressInitInterface
{
    /**
     * Initialise Express Checkout Quote
     *
     * @param ProductCartParamsInterface $productCartParams
     * @param int|null $adyenCartId
     * @param string|null $adyenMaskedQuoteId
     * @return \Adyen\ExpressCheckout\Api\Data\ExpressDataInterface|null
     */
    public function execute(
        \Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface $productCartParams,
        ?int $adyenCartId = null,
        ?string $adyenMaskedQuoteId = null
    ): ?\Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
}
