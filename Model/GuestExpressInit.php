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
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestExpressInit implements GuestExpressInitInterface
{
    private ExpressInitInterface $expressInit;
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    /**
     * @param ExpressInitInterface $expressInit
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        ExpressInitInterface $expressInit,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->expressInit = $expressInit;
    }

    /**
     * Initialise Express Checkout, return data to use in FE JS
     *
     * @param ProductCartParamsInterface $productCartParams
     * @param string|null $guestMaskedId
     * @param string|null $adyenMaskedQuoteId
     * @return ExpressDataInterface|null
     * @throws NoSuchEntityException
     */
    public function execute(
        ProductCartParamsInterface $productCartParams,
        ?string $guestMaskedId = null,
        ?string $adyenMaskedQuoteId = null
    ): ?ExpressDataInterface {
        $quoteId = null;
        if ($guestMaskedId !== null) {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($guestMaskedId);
        }
        return $this->expressInit->execute(
            $productCartParams,
            $quoteId,
            $adyenMaskedQuoteId
        );
    }
}
