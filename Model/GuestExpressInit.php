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

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface;
use Adyen\ExpressCheckout\Api\ExpressInitInterface;
use Adyen\ExpressCheckout\Api\GuestExpressInitInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestExpressInit implements GuestExpressInitInterface
{
    /**
     * @var ExpressInitInterface
     */
    private $expressInit;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteMaskFactory;

    /**
     * @param ExpressInitInterface $expressInit
     * @param QuoteIdMaskFactory $quoteMaskFactory
     */
    public function __construct(
        ExpressInitInterface $expressInit,
        QuoteIdMaskFactory $quoteMaskFactory
    ) {
        $this->quoteMaskFactory = $quoteMaskFactory;
        $this->expressInit = $expressInit;
    }

    /**
     * Initialise Express Checkout, return data to use in FE JS
     *
     * @param ProductCartParamsInterface $productCartParams
     * @param string|null $guestMaskedId
     * @param string|null $adyenMaskedQuoteId
     * @return ExpressDataInterface|null
     * @throws ExpressInitException
     */
    public function execute(
        ProductCartParamsInterface $productCartParams,
        ?string $guestMaskedId = null,
        ?string $adyenMaskedQuoteId = null
    ): ?ExpressDataInterface {
        $quoteId = null;
        if ($guestMaskedId !== null) {
            /** @var $quoteIdMask QuoteIdMask */
            $quoteIdMask = $this->quoteMaskFactory->create()->load(
                $guestMaskedId,
                'masked_id'
            );
            $quoteId = (int) $quoteIdMask->getQuoteId();
        }
        return $this->expressInit->execute(
            $productCartParams,
            $quoteId,
            $adyenMaskedQuoteId
        );
    }
}
