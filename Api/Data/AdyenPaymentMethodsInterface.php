<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data;

interface AdyenPaymentMethodsInterface
{
    public const EXTRA_DETAILS = 'extra_details';
    public const METHODS_RESPONSE = 'methods_response';

    /**
     * Get Payment Methods Extra Details
     *
     * @return \Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface[]
     */
    public function getExtraDetails(): array;

    /**
     * Set Payment Methods Extra Details
     *
     * @param \Adyen\ExpressCheckout\Api\Data\ExtraDetailInterface[]
     * @return void
     */
    public function setExtraDetails(
        array $extraDetails
    ): void;

    /**
     * Get Payment Methods Response
     *
     * @return \Adyen\ExpressCheckout\Api\Data\MethodResponseInterface[]
     */
    public function getMethodsResponse(): array;

    /**
     * Set Payment Methods Response
     *
     * @param \Adyen\ExpressCheckout\Api\Data\MethodResponseInterface[]
     * @return void
     */
    public function setMethodsResponse(
        array $maskedQuoteId
    ): void;
}
