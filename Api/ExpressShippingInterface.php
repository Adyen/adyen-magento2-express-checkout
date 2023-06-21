<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface ExpressShippingInterface
{
    /**
     * Update Express Shipping Method & Carrier Code
     *
     * @param int $adyenCartId
     * @param string $shippingMethodCode
     * @param string $shippingCarrierCode
     * @return void
     */
    public function execute(int $adyenCartId, string $shippingMethodCode, string $shippingCarrierCode): void;
}
