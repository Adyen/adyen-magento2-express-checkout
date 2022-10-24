<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;

interface ExpressCancelInterface
{
    /**
     * Cancel Express Checkout Quote
     *
     * @param int $adyenCartId
     * @return void
     */
    public function execute(int $adyenCartId): void;
}
