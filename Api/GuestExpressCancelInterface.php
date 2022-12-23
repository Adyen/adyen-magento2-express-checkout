<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface GuestExpressCancelInterface
{
    /**
     * Cancel Express Checkout Quote for Guests
     *
     * @param string $maskedQuoteId
     * @return void
     */
    public function execute(
        string $maskedQuoteId
    ): void;
}
