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

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\AdyenPaypalUpdateOrderInterface;
use Adyen\ExpressCheckout\Api\GuestAdyenPaypalUpdateOrderInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;

class GuestAdyenPaypalUpdateOrder implements GuestAdyenPaypalUpdateOrderInterface
{
    /**
     * @var MaskedQuoteIdToQuoteId
     */
    private MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId;

    /**
     * @var AdyenPaypalUpdateOrder
     */
    private AdyenPaypalUpdateOrder $adyenUpdatePaypalOrder;

    /**
     * @param MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId
     * @param AdyenPaypalUpdateOrderInterface $adyenUpdatePaypalOrder
     */
    public function __construct(
        MaskedQuoteIdToQuoteId $maskedQuoteIdToQuoteId,
        AdyenPaypalUpdateOrderInterface $adyenUpdatePaypalOrder
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->adyenUpdatePaypalOrder = $adyenUpdatePaypalOrder;
    }

    public function execute(
        string $paymentData,
        ?string $guestMaskedId = null,
        ?string $adyenMaskedQuoteId = null,
        string $deliveryMethods = ''
    ): string {
        $quoteId = null;
        if ($guestMaskedId !== null) {
            /** @var $quoteIdMask QuoteIdMask */
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($guestMaskedId);
        }

        return $this->adyenUpdatePaypalOrder->execute($paymentData, $quoteId, $adyenMaskedQuoteId, $deliveryMethods);
    }
}
