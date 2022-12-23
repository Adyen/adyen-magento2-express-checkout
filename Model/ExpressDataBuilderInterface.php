<?php
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model;

use Adyen\ExpressCheckout\Api\Data\ExpressDataInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;

interface ExpressDataBuilderInterface
{
    /**
     * @param CartInterface $quote
     * @param ProductInterface $product
     * @return ExpressDataInterface
     */
    public function execute(
        CartInterface $quote,
        ProductInterface $product
    ): ExpressDataInterface;
}
