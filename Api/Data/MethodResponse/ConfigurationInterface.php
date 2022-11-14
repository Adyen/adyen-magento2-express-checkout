<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data\MethodResponse;

interface ConfigurationInterface
{
    public const MERCHANT_ID = 'merchant_id';
    public const GATEWAY_MERCHANT_ID = 'gateway_merchant_id';
    public const INTENT = 'intent';
    public const MERCHANT_NAME = 'merchant_name';

    /**
     * Get Payment Method Merchant ID
     *
     * @return string|null
     */
    public function getMerchantId(): ?string;

    /**
     * Set Payment Method Merchant ID
     *
     * @param string $merchantId
     * @return void
     */
    public function setMerchantId(
        string $merchantId
    ): void;

    /**
     * Get Payment Method Gateway Merchant ID
     *
     * @return string|null
     */
    public function getGatewayMerchantId(): ?string;

    /**
     * Set Payment Method Gateway Merchant ID
     *
     * @param string $gatewayMerchantId
     * @return void
     */
    public function setGatewayMerchantId(
        string $gatewayMerchantId
    ): void;

    /**
     * Get Payment Method Intent
     *
     * @return string|null
     */
    public function getIntent(): ?string;

    /**
     * Set Payment Method Intent
     *
     * @param string $intent
     * @return void
     */
    public function setIntent(
        string $intent
    ): void;

    /**
     * Get Payment Method Merhcant Name
     *
     * @return string|null
     */
    public function getMerchantName(): ?string;

    /**
     * Set Payment Method Merhcant Name
     *
     * @param string $merchantName
     * @return void
     */
    public function setMerchantName(
        string $merchantName
    ): void;
}
