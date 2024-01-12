<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\AdyenPaymentMethodsInterface;
use Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\TotalsInterface;

class ExpressData extends DataObject implements ExpressDataInterface
{
    /**
     * Get Masked Quote ID
     *
     * @return string|null
     */
    public function getMaskedQuoteId(): ?string
    {
        $maskedQuoteId = $this->getData(self::MASKED_QUOTE_ID);
        return $maskedQuoteId ?
            (string) $maskedQuoteId :
            null;
    }

    /**
     * Set Masked Quote ID
     *
     * @param string $maskedQuoteId
     * @return void
     */
    public function setMaskedQuoteId(string $maskedQuoteId): void
    {
        $this->setData(
            self::MASKED_QUOTE_ID,
            $maskedQuoteId
        );
    }

    /**
     * Get Adyen Payment Methods
     *
     * @return AdyenPaymentMethodsInterface|null
     */
    public function getAdyenPaymentMethods(): ?AdyenPaymentMethodsInterface
    {
        $adyenPaymentMethods = $this->getData(self::ADYEN_PAYMENT_METHODS);
        if (!$adyenPaymentMethods instanceof AdyenPaymentMethodsInterface) {
            $adyenPaymentMethods = null;
        }
        return $adyenPaymentMethods;
    }

    /**
     * Set Adyen Payment Methods
     *
     * @param AdyenPaymentMethodsInterface $adyenPaymentMethods
     * @return void
     */
    public function setAdyenPaymentMethods(
        AdyenPaymentMethodsInterface $adyenPaymentMethods
    ): void {
        $this->setData(
            self::ADYEN_PAYMENT_METHODS,
            $adyenPaymentMethods
        );
    }

    /**
     * Get Totals for Express Checkout Data
     *
     * @return TotalsInterface|null
     */
    public function getTotals(): ?TotalsInterface
    {
        $totals = $this->getData(self::TOTALS);
        if (!$totals instanceof TotalsInterface) {
            $totals = null;
        }
        return $totals;
    }

    /**
     * Set Totals for Express Checkout Data
     *
     * @param TotalsInterface $totals
     * @return void
     */
    public function setTotals(TotalsInterface $totals): void
    {
        $this->setData(
            self::TOTALS,
            $totals
        );
    }

    /**
     * @return bool|null
     */
    public function getIsVirtualQuote(): ?bool
    {
        $isVirtual = $this->getData(self::IS_VIRTUAL_QUOTE);

        return $isVirtual ?
            (bool) $isVirtual :
            null;
    }

    /**
     * @param bool $isVirtual
     * @return void
     */
    public function setIsVirtualQuote(bool $isVirtual): void
    {
        $this->setData(
            self::IS_VIRTUAL_QUOTE,
            $isVirtual
        );
    }
}
