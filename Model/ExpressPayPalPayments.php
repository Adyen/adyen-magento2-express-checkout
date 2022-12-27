<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\ExpressPayPalPaymentsInterface;
use Adyen\Payment\Helper\Data;

class ExpressPayPalPayments implements ExpressPayPalPaymentsInterface
{
    public function __construct(
        Data $adyenDataHelper
    ) {
        $this->adyenDataHelper = $adyenDataHelper;
    }
    /**
     * @param string $payload
     * @return mixed
     */
    public function execute($payload)
    {
        $client = $this->adyenDataHelper->initializeAdyenClient();
        $checkoutService = $this->adyenDataHelper->createAdyenCheckoutService($client);
        $result = $checkoutService->payments(json_decode($payload, true));
        return [
            "response" => $result
        ];
    }
}