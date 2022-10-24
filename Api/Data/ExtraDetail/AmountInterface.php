<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data\ExtraDetail;

interface AmountInterface
{
    public const VALUE = 'value';
    public const CURRENCY = 'currency';

    /**
     * Get Configuration Value
     *
     * @return int
     */
    public function getValue(): int;

    /**
     * Set Configuration Value
     *
     * @param int
     * @return void
     */
    public function setValue(
        int $amount
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
