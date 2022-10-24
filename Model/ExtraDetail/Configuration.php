<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\ExtraDetail;

use Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface;
use Adyen\ExpressCheckout\Api\Data\ExtraDetail\ConfigurationInterface;
use Magento\Framework\DataObject;

class Configuration extends DataObject implements ConfigurationInterface
{

    /**
     * Get Payment Method Configuration Amount
     *
     * @return AmountInterface|null
     */
    public function getAmount(): ?AmountInterface
    {
        $amount = $this->getData(self::AMOUNT);
        if (!$amount instanceof AmountInterface) {
            $amount = null;
        }
        return $amount;
    }

    /**
     * Set Payment Method Configuration Amount
     *
     * @param AmountInterface $amount
     * @return void
     */
    public function setAmount(AmountInterface $amount): void
    {
        $this->setData(
            self::AMOUNT,
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
