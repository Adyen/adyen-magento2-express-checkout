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

namespace Adyen\ExpressCheckout\Api;

interface AdyenInitPaymentsInterface
{
    const PAYMENT_CHANNEL_WEB = 'web';

    /**
     * @param string $stateData
     * @param int|null $adyenCartId
     * @param string|null $adyenMaskedQuoteId
     * @return string
     */
    public function execute(
        string $stateData,
        ?int $adyenCartId = null,
        ?string $adyenMaskedQuoteId = null
    ): string;
}
