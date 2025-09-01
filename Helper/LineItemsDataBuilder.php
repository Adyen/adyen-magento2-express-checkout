<?php

namespace Adyen\ExpressCheckout\Helper;

use Adyen\Payment\Helper\OpenInvoice;
use Magento\Quote\Model\Quote;

class LineItemsDataBuilder extends OpenInvoice
{
    public function getOpenInvoiceDataForQuote(Quote $cart): array
    {
        $formFields = ['lineItems' => []];

        foreach ($cart->getAllVisibleItems() as $item) {
            $itemAmountCurrency = $this->chargedCurrency->getQuoteItemAmountCurrency($item);
            $formFields['lineItems'][] = $this->formatLineItem($itemAmountCurrency, $item);
        }

        return $formFields;
    }
}
