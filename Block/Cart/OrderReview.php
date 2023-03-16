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
    protected $request;

    public function __construct(
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isVisible()
    {
        $url = $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true]);
        $url_parts = parse_url($url);

        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query);

            if (isset($query['amazonCheckoutSessionId'])) {
                return true;
            } else {
                return false;
            }
        }
    }
}
