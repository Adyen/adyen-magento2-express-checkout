<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface GuestExpressActivateInterface
{
    /**
     * Activate Current Adyen Quote and disable current quote if one exists
     *
     * @param string $adyenMaskedQuoteId
     * @param string|null $currentMaskedQuoteId
     * @return void
     */
    public function execute(
        string $adyenMaskedQuoteId,
        ?string $currentMaskedQuoteId = null
    ): void;
}
