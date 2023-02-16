<?php
/**
 *
 * Adyen ExpressCheckout Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\ExpressCheckout\Block\Cart;

use \Magento\Framework\View\Element\Template;

class OrderReview extends Template
{
    public function getOrderReviewContent()
    {
        return 'Rok was here';
    }
}
