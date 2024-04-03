<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api;


interface AdyenUpdateOrderInterface
{

    /**
     * @param string $pspReference
     * @param string $paymentData
     * @param string[] $amount
     * @param string[]|null $deliveryMethods
     * @return mixed
     */
    public function execute($pspReference, $paymentData, $amount,  $deliveryMethods = []);
}
