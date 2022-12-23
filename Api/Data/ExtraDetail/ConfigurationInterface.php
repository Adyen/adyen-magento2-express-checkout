<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data\ExtraDetail;

interface ConfigurationInterface
{
    public const AMOUNT = 'amount';
    public const CURRENCY = 'currency';

    /**
     * Get Configuration Amount
     *
     * @return \Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface|null
     */
    public function getAmount(): ?\Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface;

    /**
     * Set Configuration Amount
     *
     * @param \Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface
     * @return void
     */
    public function setAmount(
        \Adyen\ExpressCheckout\Api\Data\ExtraDetail\AmountInterface $amount
    ): void;

    /**
     * Get Configuration Currency
     *
     * @return string|null
     */
    public function getCurrency(): ?string;

    /**
     * Set Configuration Currency
     *
     * @param string
     * @return void
     */
    public function setCurrency(
        string $currency
    ): void;
}
