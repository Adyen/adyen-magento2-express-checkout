<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface GuestExpressShippingInterface
{
    /**
     * Update Express Shipping Method & Carrier Codes
     *
     * @param string $maskedQuoteId
     * @return void
     */
    public function execute(
        string $maskedQuoteId,
        string $shippingMethodCode,
        string $shippingCarrierCode
    ): void;
}
