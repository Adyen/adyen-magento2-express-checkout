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

interface GuestAdyenInitPaymentsInterface
{
    /**
     * @param string $maskedQuoteId
     * @param string $stateData
     * @return string
     */
    public function execute(string $maskedQuoteId, string $stateData): string;
}
