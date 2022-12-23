<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface ExpressActivateInterface
{
    /**
     * Set Adyen Quote to be active and disable current active cart
     *
     * @param string $adyenMaskedQuoteId
     * @param int|null $adyenCartId
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(
        string $adyenMaskedQuoteId,
        ?int $adyenCartId = null
    ): void;
}
