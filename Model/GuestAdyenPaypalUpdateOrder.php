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
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenPaypalUpdateOrder implements GuestAdyenPaypalUpdateOrderInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @var AdyenPaypalUpdateOrder
     */
    private AdyenPaypalUpdateOrder $adyenUpdatePaypalOrder;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param AdyenPaypalUpdateOrderInterface $adyenUpdatePaypalOrder
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AdyenPaypalUpdateOrderInterface $adyenUpdatePaypalOrder
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
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
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load(
                $guestMaskedId,
                'masked_id'
            );
            $quoteId = (int) $quoteIdMask->getQuoteId();
        }

        return $this->adyenUpdatePaypalOrder->execute($paymentData, $quoteId, $adyenMaskedQuoteId, $deliveryMethods);
    }
}
