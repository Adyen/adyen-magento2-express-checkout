<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data;

interface ProductCartParamsInterface
{
    public const PRODUCT = 'product';
    public const QTY = 'qty';
    public const SUPER_ATTRIBUTE = 'super_attribute';

    /**
     * Get Product ID
     *
     * @return int
     */
    public function getProduct(): int;

    /**
     * Set Product ID
     *
     * @param int $productId
     */
    public function setProduct(
        int $productId
    ): void;

    /**
     * Get Qty
     *
     * @return float
     */
    public function getQty(): float;

    /**
     * Set Qty
     *
     * @param float $qty
     */
    public function setQty(
        float $qty
    ): void;

    /**
     * Get Super Attribute data ['{option_id}' => '{option_value}', ...]
     *
     * @return string[]
     */
    public function getSuperAttribute(): array;

    /**
     * Set Super Attribute data ['{option_id}' => '{option_value}', ...]
     *
     * @param string[] $superAttribute
     */
    public function setSuperAttribute(
        array $superAttribute
    ): void;
}
