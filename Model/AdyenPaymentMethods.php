<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface;
use Adyen\ExpressCheckout\Api\Data\MethodResponseInterface;
use Magento\Framework\DataObject;

class AdyenPaymentMethods extends DataObject implements AdyenPaymentMethodsInterface
{
    /**
     * Get Payment Methods Extra Details
     *
     * @return ExtraDetailInterface[]
     */
    public function getExtraDetails(): array
    {
        $paymentMethodsExtraDetails = $this->getData(self::EXTRA_DETAILS);
        return is_array($paymentMethodsExtraDetails) ?
            $paymentMethodsExtraDetails :
            [];
    }

    /**
     * Set Payment Methods Extra Details
     *
     * @param ExtraDetailInterface[] $paymentMethodsExtraDetails
     * @return void
     */
    public function setExtraDetails(array $paymentMethodsExtraDetails): void
    {
        $this->setData(
            self::EXTRA_DETAILS,
            $paymentMethodsExtraDetails
        );
    }

    /**
     * Get Payment Methods Extra Response
     *
     * @return MethodResponseInterface[]
     */
    public function getMethodsResponse(): array
    {
        $paymentMethodsResponse = $this->getData(self::METHODS_RESPONSE);
        return is_array($paymentMethodsResponse) ?
            $paymentMethodsResponse :
            [];
    }

    /**
     * Set Payment Methods Extra Response
     *
     * @param MethodResponseInterface[] $paymentMethodsResponse
     * @return void
     */
    public function setMethodsResponse(array $paymentMethodsResponse): void
    {
        $this->setData(
            self::METHODS_RESPONSE,
            $paymentMethodsResponse
        );
    }
}
