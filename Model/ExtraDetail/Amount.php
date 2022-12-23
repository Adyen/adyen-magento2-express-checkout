<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\ExtraDetail;

use Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface;
use Magento\Framework\DataObject;

class Amount extends DataObject implements AmountInterface
{
    /**
     * Get Payment Method Configuration Value
     *
     * @return int
     */
    public function getValue(): int
    {
        return (int) $this->getData(self::VALUE);
    }

    /**
     * Set Payment Method Configuration Value
     *
     * @param int $amount
     * @return void
     */
    public function setValue(int $amount): void
    {
        $this->setData(
            self::VALUE,
            $amount
        );
    }

    /**
     * Get Payment Method Icon width
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        $currency = $this->getData(self::CURRENCY);
        return $currency ?
            (string) $currency :
            null;
    }

    /**
     * Set Payment Method Icon width
     *
     * @param string $currency
     * @return void
     */
    public function setCurrency(string $currency): void
    {
        $this->setData(
            self::CURRENCY,
            $currency
        );
    }
}
