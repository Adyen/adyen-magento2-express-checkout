<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Helper;

use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\DeliveryMethod;
use Adyen\Model\Checkout\PaypalUpdateOrderRequest;
use Adyen\Model\Checkout\PaypalUpdateOrderResponse;
use Adyen\Payment\Helper\Data;
use Adyen\Service\Checkout\UtilityApi;
use Adyen\AdyenException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class UpdatePaypalOrder
{
    protected UtilityApi $utilityApi;
    private Data $adyenHelper;
    private StoreManagerInterface $storeManager;

    /**
     * @param Data $adyenHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $adyenHelper,
        StoreManagerInterface  $storeManager
    )
    {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param string $pspReference
     * @param string $paymentData
     * @param array $amount
     * @param array|null $deliveryMethods
     * @return array
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function updatePaypalOrder(
        string $pspReference,
        string $paymentData,
        array $amount,
        array $deliveryMethods = null
    ): array
    {
        $paypalUpdateOrderRequest = new PaypalUpdateOrderRequest();

        try {
            $paypalUpdateOrderRequest->setPspReference($pspReference);
            $paypalUpdateOrderRequest->setPaymentData($paymentData);
            $paypalUpdateOrderRequest->setAmount(new Amount($amount));
            $paypalUpdateOrderRequest->setDeliveryMethods([new DeliveryMethod($deliveryMethods)]);

            $storeId = $this->storeManager->getStore()->getId();
            $service = $this->createAdyenUtilityApiService($storeId);
            $paypalUpdateOrderResponse = $service->updatesOrderForPaypalExpressCheckout($paypalUpdateOrderRequest);

            return json_decode(json_encode($paypalUpdateOrderResponse->jsonSerialize()), true);
        } catch (AdyenException $e) {
            throw new AdyenException(__('Error processing PayPal Update Order: %1', $e->getMessage()));
        }
    }

    /**
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    private function createAdyenUtilityApiService($storeId): UtilityApi
    {
        $client = $this->adyenHelper->initializeAdyenClient($storeId);

        return new UtilityApi($client);
    }
}
