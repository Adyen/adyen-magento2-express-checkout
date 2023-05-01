<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\MethodResponse;

use Adyen\ExpressCheckout\Api\Data\MethodResponse\ConfigurationInterface;
use Magento\Framework\DataObject;

class Configuration extends DataObject implements ConfigurationInterface
{
    /**
     * Get Payment Method Merchant ID
     *
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        $merchantId = $this->getData(self::MERCHANT_ID);
        return $merchantId ?
            (string) $merchantId :
            null;
    }

    /**
     * Set Payment Method Merchant ID
     *
     * @param string $merchantId
     * @return void
     */
    public function setMerchantId(string $merchantId): void
    {
        $this->setData(
            self::MERCHANT_ID,
            $merchantId
        );
    }

    /**
     * Get Payment Method Gateway Merchant ID
     *
     * @return string|null
     */
    public function getGatewayMerchantId(): ?string
    {
        $gatewayMerchantId = $this->getData(self::GATEWAY_MERCHANT_ID);
        return $gatewayMerchantId ?
            (string) $gatewayMerchantId :
            null;
    }

    /**
     * Set Payment Method Gateway Merchant ID
     *
     * @param string $gatewayMerchantId
     * @return void
     */
    public function setGatewayMerchantId(string $gatewayMerchantId): void
    {
        $this->setData(
            self::GATEWAY_MERCHANT_ID,
            $gatewayMerchantId
        );
    }

    /**
     * Get Payment Method Intent
     *
     * @return string|null
     */
    public function getIntent(): ?string
    {
        $intent = $this->getData(self::INTENT);
        return $intent ?
            (string) $intent :
            null;
    }

    /**
     * Set Payment Method Intent
     *
     * @param string $intent
     * @return void
     */
    public function setIntent(string $intent): void
    {
        $this->setData(
            self::INTENT,
            $intent
        );
    }

    /**
     * Get Payment Method Gateway Merchant Name
     *
     * @return string|null
     */
    public function getMerchantName(): ?string
    {
        $merchantName = $this->getData(self::MERCHANT_NAME);
        return $merchantName ?
            (string) $merchantName :
            null;
    }

    /**
     * Set Payment Method Gateway Merchant Name
     *
     * @param string $merchantName
     * @return void
     */
    public function setMerchantName(string $merchantName): void
    {
        $this->setData(
            self::MERCHANT_NAME,
            $merchantName
        );
    }

    /**
     * Get Payment Method Public Key ID
     *
     * @return string|null
     */
    public function getPublicKeyId(): ?string
    {
        $publicKeyId = $this->getData(self::PUBLIC_KEY_ID);
        return $publicKeyId ?
            (string) $publicKeyId :
            null;
    }

    /**
     * Set Payment Method Public Key ID
     *
     * @param string $publicKeyId
     * @return void
     */
    public function setPublicKeyId(string $publicKeyId): void
    {
        $this->setData(
            self::PUBLIC_KEY_ID,
            $publicKeyId
        );
    }

    /**
     * Get Payment Method Store ID
     *
     * @return string|null
     */
    public function getStoreId(): ?string
    {
        $storeId = $this->getData(self::STORE_ID);
        return $storeId ?
            (string) $storeId :
            null;
    }

    /**
     * Set Payment Method Store ID
     *
     * @param string $storeId
     * @return void
     */
    public function setStoreId(string $storeId): void
    {
        $this->setData(
            self::STORE_ID,
            $storeId
        );
    }
}
