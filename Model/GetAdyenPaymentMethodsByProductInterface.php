<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartInterface;

interface GetAdyenPaymentMethodsByProductInterface
{
    /**
     * Return Adyen Retrieve Payment Methods response for Product without Quote
     * Used in PDP for ExpressCheckout when we don't have all options selected yet for Composite
     *
     * @param ProductInterface $product
     * @param CartInterface $quote
     * @return array
     */
    public function execute(
        ProductInterface $product,
        CartInterface $quote
    ): array;
}
