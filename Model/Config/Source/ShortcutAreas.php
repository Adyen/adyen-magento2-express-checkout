<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ShortcutAreas implements OptionSourceInterface
{
    public const PRODUCT_VIEW_VALUE = 1;
    public const CART_PAGE_VALUE = 2;
    public const MINICART_VALUE = 3;
    public const SHIPPING_PAGE_VALUE = 4;

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::PRODUCT_VIEW_VALUE,
                'label' => 'Product View'
            ],
            [
                'value' => self::CART_PAGE_VALUE,
                'label' => 'Cart Page'
            ],
            [
                'value' => self::MINICART_VALUE,
                'label' => 'Minicart'
            ],
            [
                'value' => self::SHIPPING_PAGE_VALUE,
                'label' => 'Shipping Page'
            ]
        ];
    }
}
