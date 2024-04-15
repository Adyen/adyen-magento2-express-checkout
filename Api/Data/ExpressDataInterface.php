<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Api\Data;

interface ExpressDataInterface
{
    public const MASKED_QUOTE_ID = 'masked_quote_id';
    public const ADYEN_PAYMENT_METHODS = 'adyen_payment_methods';
    public const TOTALS = 'totals';
    public const IS_VIRTUAL_QUOTE = 'is_virtual_quote';

    /**
     * Get Masked Quote ID
     *
     * @return string|null
     */
    public function getMaskedQuoteId(): ?string;

    /**
     * Set Masked Quote ID
     *
     * @param string $maskedQuoteId
     * @return void
     */
    public function setMaskedQuoteId(
        string $maskedQuoteId
    ): void;

    /**
     * Get Adyen Payment Methods data
     *
     * @return \Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface|null
     */
    public function getAdyenPaymentMethods(): ?\Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;

    /**
     * Set Adyen Payment Methods data
     *
     * @param \Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface $adyenPaymentMethods
     * @return void
     */
    public function setAdyenPaymentMethods(
        \Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface $adyenPaymentMethods
    ): void;

    /**
     * Get Totals For Expres Checkout Data
     *
     * @return \Magento\Quote\Api\Data\TotalsInterface|null
     */
    public function getTotals(): ?\Magento\Quote\Api\Data\TotalsInterface;

    /**
     * Set Totals For Expres Checkout Data
     *
     * @param \Magento\Quote\Api\Data\TotalsInterface $totals
     * @return void
     */
    public function setTotals(
        \Magento\Quote\Api\Data\TotalsInterface $totals
    ): void;

    /**
     * Is quote virtual
     *
     * @return bool|null
     */
    public function getIsVirtualQuote(): ?bool;

    /**
     * Set is quote virtual
     *
     * @param bool $isVirtual
     * @return void
     */
    public function setIsVirtualQuote(
        bool $isVirtual
    ): void;
}
