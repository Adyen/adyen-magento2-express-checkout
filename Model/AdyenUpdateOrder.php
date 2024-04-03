<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\AdyenException;
use Adyen\ExpressCheckout\Api\AdyenUpdateOrderInterface;
use Adyen\ExpressCheckout\Helper\UpdatePaypalOrder;
use Magento\Framework\Exception\NoSuchEntityException;

class AdyenUpdateOrder implements AdyenUpdateOrderInterface
{
    protected UpdatePaypalOrder $updatePaypalOrderHelper;

    /**
     * @param UpdatePaypalOrder $updatePaypalOrderHelper
     */
    public function __construct(UpdatePaypalOrder $updatePaypalOrderHelper)
    {
        $this->updatePaypalOrderHelper = $updatePaypalOrderHelper;
    }

    /**
     * @param $pspReference
     * @param $paymentData
     * @param $amount
     * @param $deliveryMethods
     * @return array
     * @throws AdyenException|NoSuchEntityException
     */
    public function execute($pspReference, $paymentData, $amount, $deliveryMethods = []): array
    {
        return $this->updatePaypalOrderHelper->updatePaypalOrder(
            $pspReference,
            $paymentData,
            $amount,
            $deliveryMethods
        );
    }
}
