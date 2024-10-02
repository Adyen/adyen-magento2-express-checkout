<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Helper;

use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\DeliveryMethod;
use Adyen\Model\Checkout\PaypalUpdateOrderRequest;
use Adyen\Model\Checkout\PaypalUpdateOrderResponse;
use Adyen\Model\Checkout\TaxTotal;
use Adyen\Payment\Helper\Data;
use Adyen\Service\Checkout\UtilityApi;
use Adyen\AdyenException;
use Magento\Framework\Exception\NoSuchEntityException;

class PaypalUpdateOrder
{
    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @param Data $adyenHelper
     */
    public function __construct(
        Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Creates and returns service class for Adyen Utility API
     *
     * @param $storeId
     * @return UtilityApi
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function createAdyenUtilityApiService($storeId): UtilityApi
    {
        return new UtilityApi($this->adyenHelper->initializeAdyenClient($storeId));
    }

    /**
     * Builds the request object for /paypal/updateOrder endpoint of Adyen Checkout API
     *
     * @param string $pspReference
     * @param string $paymentData
     * @param int $amountValue
     * @param string $amountCurrency
     * @param array $deliveryMethods
     * @return PaypalUpdateOrderRequest
     */
    public function buildPaypalUpdateOrderRequest(
        string $pspReference,
        string $paymentData,
        int $amountValue,
        int $taxAmount,
        string $amountCurrency,
        array $deliveryMethods = []
    ): PaypalUpdateOrderRequest {
        $amount = new Amount();
        $amount->setValue($amountValue);
        $amount->setCurrency($amountCurrency);

        $taxTotalAmount = new Amount();
        $taxTotalAmount->setValue($taxAmount);
        $taxTotalAmount->setCurrency($amountCurrency);

        $taxTotal = new TaxTotal();
        $taxTotal->setAmount($taxTotalAmount);

        $paypalUpdateOrderRequest = new PaypalUpdateOrderRequest();

        $paypalUpdateOrderRequest->setPspReference($pspReference);
        $paypalUpdateOrderRequest->setPaymentData($paymentData);
        $paypalUpdateOrderRequest->setAmount($amount);
        $paypalUpdateOrderRequest->setTaxTotal($taxTotal);

        if (!empty($deliveryMethods)) {
            $deliveryMethodsObjectArray = [];

            foreach ($deliveryMethods as $deliveryMethod) {
                $deliveryMethodObject = new DeliveryMethod($deliveryMethod);
                $deliveryMethodsObjectArray[] = $deliveryMethodObject;
            }

            $paypalUpdateOrderRequest->setDeliveryMethods($deliveryMethodsObjectArray);
        }

        return $paypalUpdateOrderRequest;
    }

    /**
     * Returns paypal/updateOrder response in a structured array.
     *
     * @param PaypalUpdateOrderResponse $paypalUpdateOrderResponse
     * @return array
     */
    public function handlePaypalUpdateOrderResponse(PaypalUpdateOrderResponse $paypalUpdateOrderResponse): array
    {
        return [
            'status' => $paypalUpdateOrderResponse->getStatus(),
            'paymentData' => $paypalUpdateOrderResponse->getPaymentData()
        ];
    }
}
