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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Http\ClientException;
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
     * @throws NoSuchEntityException
     * @throws ValidatorException
     * @throws ClientException
     */
    public function execute(string $maskedQuoteId, string $stateData): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedQuoteId, 'masked_id');
        $quoteId = (int) $quoteIdMask->getQuoteId();

        return $this->adyenInitPayments->execute($quoteId, $stateData);
    }
}
