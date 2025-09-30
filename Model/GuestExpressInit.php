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
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GuestExpressInit implements GuestExpressInitInterface
{
    /**
     * @param ExpressInitInterface $expressInit
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly ExpressInitInterface $expressInit,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * Initialise Express Checkout, return data to use in FE JS
     *
     * @param ProductCartParamsInterface $productCartParams
     * @param string|null $guestMaskedId
     * @param string|null $adyenMaskedQuoteId
     * @return ExpressDataInterface|null
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(
        ProductCartParamsInterface $productCartParams,
        ?string $guestMaskedId = null,
        ?string $adyenMaskedQuoteId = null
    ): ?ExpressDataInterface {
        $quoteId = null;
        if ($guestMaskedId !== null) {
            try {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($guestMaskedId);
            } catch (NoSuchEntityException $e) {
                $this->adyenLogger->error(sprintf(
                    'The quote with ID %s could not be found: %s',
                    $guestMaskedId,
                    $e->getMessage()
                ));
                throw new LocalizedException(__('The quote with ID %1 could not be found.', $guestMaskedId));
            }
        }
        return $this->expressInit->execute(
            $productCartParams,
            $quoteId,
            $adyenMaskedQuoteId
        );
    }
}
