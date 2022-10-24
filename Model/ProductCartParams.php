<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\ProductCartParamsInterface;
use Magento\Framework\DataObject;

class ProductCartParams extends DataObject implements ProductCartParamsInterface
{
    /**
     * Get Product ID
     *
     * @return int
     */
    public function getProduct(): int
    {
        return (int) $this->getData(self::PRODUCT);
    }

    /**
     * Set Product ID
     *
     * @param int $productId
     * @return void
     */
    public function setProduct(int $productId): void
    {
        $this->setData(
            self::PRODUCT,
            $productId
        );
    }

    /**
     * Get QTY
     *
     * @return float
     */
    public function getQty(): float
    {
        return (float) $this->getData(self::QTY);
    }

    /**
     * Set QTY
     *
     * @param float $qty
     * @return void
     */
    public function setQty(float $qty): void
    {
        $this->setData(
            self::QTY,
            $qty
        );
    }

    /**
     * Get Super Attribute
     *
     * @return string[]
     */
    public function getSuperAttribute(): array
    {
        $superAttribute = $this->getData(self::SUPER_ATTRIBUTE);
        return is_array($superAttribute) ?
            $superAttribute :
            [];
    }

    /**
     * Set Super Attribute
     *
     * @param string[] $superAttribute
     * @return void
     */
    public function setSuperAttribute(array $superAttribute): void
    {
        $this->setData(
            self::SUPER_ATTRIBUTE,
            $superAttribute
        );
    }
}
