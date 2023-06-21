<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface GuestExpressShippingInterface
{
    /**
     * Update Express Shipping Method & Carrier Codes
     *
     * @param string $maskedQuoteId
     * @param string $countryId
     * @param string $region
     * @param string $regionId
     * @param string postcode
     * @param string $shippingDescription
     * @param string $shippingMethodCode,
     * @param int $shippingAmount
     * @return void
     */
    public function execute(
        string $maskedQuoteId,
        string $countryId,
        string $region,
        string $regionId,
        string $postcode,
        string $shippingDescription,
        string $shippingMethodCode,
        int $shippingAmount
    ): void;
}
