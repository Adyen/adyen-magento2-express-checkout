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

use Adyen\ExpressCheckout\Api\GuestAdyenInitPaymentsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenInitPayments implements GuestAdyenInitPaymentsInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private QuoteIdMaskFactory $quoteIdMaskFactory;

    /**
     * @var AdyenInitPayments
     */
    private AdyenInitPayments $adyenInitPayments;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param AdyenInitPayments $adyenInitPayments
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AdyenInitPayments $adyenInitPayments
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->adyenInitPayments = $adyenInitPayments;
    }

    /**
     * @param string $stateData
     * @param string|null $guestMaskedId
     * @param string|null $adyenMaskedQuoteId
     * @return string
     * @throws ClientException
     * @throws NoSuchEntityException
     * @throws ValidatorException
     * @throws LocalizedException
     */
    public function execute(
        string $stateData,
        ?string $guestMaskedId = null,
        ?string $adyenMaskedQuoteId = null
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

        return $this->adyenInitPayments->execute($stateData, $quoteId, $adyenMaskedQuoteId);
    }
}
